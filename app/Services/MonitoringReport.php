<?php

namespace App\Services;

use App\Models\Monitor;
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
        $monitors = $user->monitors()
            ->with(['customer', 'maintenanceContract'])
            // Own order first, then by creation (new websites are added at the end)
            ->orderByRaw('CASE WHEN position IS NULL THEN 1 ELSE 0 END')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $monitored = $monitors->filter(fn (Monitor $monitor) => $monitor->isActive());
        $ids = $monitored->pluck('id');

        $uptime = [
            '24h' => $this->uptime($ids, now()->subDay()),
            '7d' => $this->uptime($ids, now()->subDays(7)),
            '30d' => $this->uptime($ids, now()->subDays(30)),
        ];

        $lastDay = SiteCheck::whereIn('monitor_id', $ids)
            ->where('checked_at', '>=', now()->subDay())
            ->orderBy('checked_at')
            ->get(['monitor_id', 'checked_at', 'up', 'response_ms'])
            ->groupBy('monitor_id');

        $incidents = $this->incidents($monitored, now()->subDays(30));

        $sites = $monitored->map(fn (Monitor $monitor) => [
            ...$this->site($monitor),
            'uptime' => [
                '24h' => $uptime['24h'][$monitor->id]['percent'] ?? null,
                '7d' => $uptime['7d'][$monitor->id]['percent'] ?? null,
                '30d' => $uptime['30d'][$monitor->id]['percent'] ?? null,
            ],
            'avg_response_ms' => $uptime['24h'][$monitor->id]['avg_ms'] ?? null,
            'timeline' => $this->timeline($lastDay->get($monitor->id, collect())),
            'incidents_30d' => $incidents->where('monitor_id', $monitor->id)->count(),
        ])->values();

        $all30 = collect($uptime['30d']);
        $sslSoon = $sites->filter(fn ($site) => $site['ssl_days'] !== null && $site['ssl_days'] <= SiteMonitor::SSL_WARNING_DAYS);

        return [
            'sites' => $sites,
            'unmonitored' => $monitors->reject(fn (Monitor $monitor) => $monitored->contains($monitor))->map(fn (Monitor $monitor) => $this->site($monitor))->values(),
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
    private function site(Monitor $monitor): array
    {
        return [
            'id' => $monitor->id,
            'contract_id' => $monitor->maintenance_contract_id,
            'customer_id' => $monitor->customer_id,
            'name' => $monitor->name,
            'url' => $monitor->url,
            'created_at' => $monitor->created_at?->toIso8601String(),
            'customer' => $monitor->customer?->display_name,
            'enabled' => $monitor->enabled,
            // Contract ended or waiting for the go-live: not checked
            'paused' => $monitor->maintenance_contract_id !== null && ! $monitor->maintenanceContract?->isBillable(),
            'paused_reason' => $monitor->maintenanceContract?->isPending() ? 'Wartet auf Go-Live' : 'Vertrag beendet',
            'auth' => $monitor->auth,
            'auth_user' => $monitor->auth_user,
            'has_password' => $monitor->has_password,
            'status' => $monitor->status,
            'status_since' => $monitor->status_since?->toIso8601String(),
            'checked_at' => $monitor->checked_at?->toIso8601String(),
            'response_ms' => $monitor->response_ms,
            'failures' => $monitor->failures,
            'error' => $monitor->error,
            'ssl_expires_at' => $monitor->ssl_expires_at?->toIso8601String(),
            'ssl_days' => $monitor->ssl_expires_at ? (int) floor(now()->diffInDays($monitor->ssl_expires_at, false)) : null,
        ];
    }

    /**
     * Share of successful checks and average response time per monitor since a point in time.
     *
     * @return array<int, array{checks: int, up: int, percent: float, avg_ms: int|null}>
     */
    private function uptime(Collection $ids, Carbon $since): array
    {
        return SiteCheck::whereIn('monitor_id', $ids)
            ->where('checked_at', '>=', $since)
            ->groupBy('monitor_id')
            ->get([
                'monitor_id',
                DB::raw('COUNT(*) as checks'),
                DB::raw('SUM(CASE WHEN up THEN 1 ELSE 0 END) as up_checks'),
                DB::raw('AVG(CASE WHEN up THEN response_ms END) as avg_ms'),
            ])
            ->mapWithKeys(fn ($row) => [$row->monitor_id => [
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
    private function incidents(Collection $monitors, Carbon $since): Collection
    {
        if ($monitors->isEmpty()) {
            return collect();
        }

        $failed = SiteCheck::whereIn('monitor_id', $monitors->pluck('id'))
            ->where('checked_at', '>=', $since)
            ->where('up', false)
            ->orderBy('checked_at')
            ->get()
            ->groupBy('monitor_id');

        $incidents = collect();

        foreach ($failed as $monitorId => $checks) {
            $monitor = $monitors->firstWhere('id', $monitorId);

            foreach ($this->runs($monitorId, $checks) as $run) {
                if ($run->count() < SiteMonitor::FAILURES_BEFORE_DOWN) {
                    continue;
                }

                $endedAt = SiteCheck::where('monitor_id', $monitorId)
                    ->where('checked_at', '>', $run->last()->checked_at)
                    ->where('up', true)
                    ->orderBy('checked_at')
                    ->value('checked_at');

                $started = $run->first()->checked_at;
                $ended = $endedAt ? Carbon::parse($endedAt) : null;

                $incidents->push([
                    'monitor_id' => $monitorId,
                    'contract_id' => $monitor->maintenance_contract_id,
                    'name' => $monitor->name,
                    'url' => $monitor->url,
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
    private function runs(int $monitorId, Collection $failed): array
    {
        $runs = [];
        $current = collect();

        foreach ($failed as $check) {
            if ($current->isNotEmpty()) {
                $upBetween = SiteCheck::where('monitor_id', $monitorId)
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
