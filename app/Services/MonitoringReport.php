<?php

namespace App\Services;

use App\Models\MaintenanceContract;
use App\Models\SiteCheck;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Figures for the monitoring center, built from the site check history.
 */
class MonitoringReport
{
    /** Segments of the 24 hour status bar (30 minutes each). */
    public const SEGMENTS = 48;

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $contracts = $user->maintenanceContracts()
            ->with('customer')
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->orderBy('name')
            ->get();

        $monitored = $contracts->filter(fn (MaintenanceContract $contract) => $contract->monitor_enabled && $contract->isBillable());
        $ids = $monitored->pluck('id');

        $uptime = [
            '24h' => $this->uptime($ids, now()->subDay()),
            '7d' => $this->uptime($ids, now()->subDays(7)),
            '30d' => $this->uptime($ids, now()->subDays(30)),
        ];

        $lastDay = SiteCheck::whereIn('maintenance_contract_id', $ids)
            ->where('checked_at', '>=', now()->subDay())
            ->orderBy('checked_at')
            ->get(['maintenance_contract_id', 'checked_at', 'up', 'response_ms'])
            ->groupBy('maintenance_contract_id');

        $incidents = $this->incidents($monitored, now()->subDays(30));

        $sites = $monitored->map(fn (MaintenanceContract $contract) => [
            ...$this->site($contract),
            'uptime' => [
                '24h' => $uptime['24h'][$contract->id]['percent'] ?? null,
                '7d' => $uptime['7d'][$contract->id]['percent'] ?? null,
                '30d' => $uptime['30d'][$contract->id]['percent'] ?? null,
            ],
            'avg_response_ms' => $uptime['24h'][$contract->id]['avg_ms'] ?? null,
            'timeline' => $this->timeline($lastDay->get($contract->id, collect())),
            'incidents_30d' => $incidents->where('contract_id', $contract->id)->count(),
        ])->values();

        $all30 = collect($uptime['30d']);
        $sslSoon = $sites->filter(fn ($site) => $site['ssl_days'] !== null && $site['ssl_days'] <= SiteMonitor::SSL_WARNING_DAYS);

        return [
            'sites' => $sites,
            'unmonitored' => $contracts->reject(fn ($contract) => $monitored->contains($contract))->map(fn ($contract) => $this->site($contract))->values(),
            'incidents' => $incidents->sortByDesc('started_at')->values(),
            'summary' => [
                'total' => $sites->count(),
                'up' => $sites->where('status', 'up')->count(),
                'down' => $sites->where('status', 'down')->count(),
                'unknown' => $sites->whereNull('status')->count(),
                'ssl_soon' => $sslSoon->count(),
                'uptime_30d' => $all30->sum('checks') > 0 ? round($all30->sum('up') / $all30->sum('checks') * 100, 2) : null,
                'avg_response_ms' => $sites->whereNotNull('avg_response_ms')->avg('avg_response_ms') !== null ? (int) round($sites->whereNotNull('avg_response_ms')->avg('avg_response_ms')) : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function site(MaintenanceContract $contract): array
    {
        return [
            'id' => $contract->id,
            'name' => $contract->name,
            'url' => $contract->url,
            'customer' => $contract->customer?->display_name,
            'monitor_enabled' => $contract->monitor_enabled,
            'ended' => ! $contract->isBillable(),
            'status' => $contract->monitor_status,
            'status_since' => $contract->monitor_status_since?->toIso8601String(),
            'checked_at' => $contract->monitor_checked_at?->toIso8601String(),
            'response_ms' => $contract->monitor_response_ms,
            'failures' => $contract->monitor_failures,
            'error' => $contract->monitor_error,
            'ssl_expires_at' => $contract->ssl_expires_at?->toIso8601String(),
            'ssl_days' => $contract->ssl_expires_at ? (int) floor(now()->diffInDays($contract->ssl_expires_at, false)) : null,
        ];
    }

    /**
     * Share of successful checks and average response time per contract since a point in time.
     *
     * @return array<int, array{checks: int, up: int, percent: float, avg_ms: int|null}>
     */
    private function uptime(Collection $ids, Carbon $since): array
    {
        return SiteCheck::whereIn('maintenance_contract_id', $ids)
            ->where('checked_at', '>=', $since)
            ->groupBy('maintenance_contract_id')
            ->get([
                'maintenance_contract_id',
                DB::raw('COUNT(*) as checks'),
                DB::raw('SUM(CASE WHEN up THEN 1 ELSE 0 END) as up_checks'),
                DB::raw('AVG(CASE WHEN up THEN response_ms END) as avg_ms'),
            ])
            ->mapWithKeys(fn ($row) => [$row->maintenance_contract_id => [
                'checks' => (int) $row->checks,
                'up' => (int) $row->up_checks,
                'percent' => round($row->up_checks / max(1, $row->checks) * 100, 2),
                'avg_ms' => $row->avg_ms !== null ? (int) round($row->avg_ms) : null,
            ]])
            ->all();
    }

    /**
     * The last 24 hours in 30 minute segments: share of successful checks and average response time.
     *
     * @return array<int, array{from: string, up: float|null, ms: int|null}>
     */
    private function timeline(Collection $checks): array
    {
        $start = now()->subDay();
        $size = 86400 / self::SEGMENTS;
        $segments = array_fill(0, self::SEGMENTS, []);

        foreach ($checks as $check) {
            $index = min(self::SEGMENTS - 1, max(0, (int) floor($start->diffInSeconds($check->checked_at) / $size)));
            $segments[$index][] = $check;
        }

        return array_map(function ($checks, $index) use ($start, $size) {
            $checks = collect($checks);
            $responses = $checks->where('up', true)->pluck('response_ms')->filter();

            return [
                'from' => $start->copy()->addSeconds((int) ($index * $size))->toIso8601String(),
                'up' => $checks->isEmpty() ? null : round($checks->where('up', true)->count() / $checks->count(), 3),
                'ms' => $responses->isEmpty() ? null : (int) round($responses->avg()),
            ];
        }, $segments, array_keys($segments));
    }

    /**
     * Outages: runs of failed checks long enough to count as down (like the alert mails),
     * ending with the next successful check.
     */
    private function incidents(Collection $contracts, Carbon $since): Collection
    {
        if ($contracts->isEmpty()) {
            return collect();
        }

        $failed = SiteCheck::whereIn('maintenance_contract_id', $contracts->pluck('id'))
            ->where('checked_at', '>=', $since)
            ->where('up', false)
            ->orderBy('checked_at')
            ->get()
            ->groupBy('maintenance_contract_id');

        $incidents = collect();

        foreach ($failed as $contractId => $checks) {
            $contract = $contracts->firstWhere('id', $contractId);

            foreach ($this->runs($contractId, $checks) as $run) {
                if ($run->count() < SiteMonitor::FAILURES_BEFORE_DOWN) {
                    continue;
                }

                $endedAt = SiteCheck::where('maintenance_contract_id', $contractId)
                    ->where('checked_at', '>', $run->last()->checked_at)
                    ->where('up', true)
                    ->orderBy('checked_at')
                    ->value('checked_at');

                $started = $run->first()->checked_at;
                $ended = $endedAt ? Carbon::parse($endedAt) : null;

                $incidents->push([
                    'contract_id' => $contractId,
                    'name' => $contract->name,
                    'url' => $contract->url,
                    'started_at' => $started->toIso8601String(),
                    'ended_at' => $ended?->toIso8601String(),
                    'minutes' => (int) round($started->diffInMinutes($ended ?? now())),
                    'error' => $run->pluck('error')->filter()->first(),
                    'checks' => $run->count(),
                ]);
            }
        }

        return $incidents;
    }

    /**
     * Split failed checks into runs: a successful check in between starts a new run.
     *
     * @return array<int, Collection>
     */
    private function runs(int $contractId, Collection $failed): array
    {
        $runs = [];
        $current = collect();

        foreach ($failed as $check) {
            if ($current->isNotEmpty()) {
                $upBetween = SiteCheck::where('maintenance_contract_id', $contractId)
                    ->whereBetween('checked_at', [$current->last()->checked_at, $check->checked_at])
                    ->where('up', true)
                    ->exists();

                if ($upBetween) {
                    $runs[] = $current;
                    $current = collect();
                }
            }

            $current->push($check);
        }

        if ($current->isNotEmpty()) {
            $runs[] = $current;
        }

        return $runs;
    }
}
