<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceContract;
use App\Services\MonitoringReport;
use App\Services\SiteMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends Controller
{
    /**
     * Monitoring center: all websites with status, uptime, response times and outages.
     */
    public function index(Request $request, MonitoringReport $report): Response
    {
        return Inertia::render('Monitoring/Index', [
            ...$report->forUser($request->user()),
            'interval' => 5,
            'failuresBeforeDown' => SiteMonitor::FAILURES_BEFORE_DOWN,
        ]);
    }

    /**
     * Save the own order of the websites (ids top to bottom).
     */
    public function order(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ])['ids'];

        $contracts = $request->user()->maintenanceContracts()->whereIn('id', $ids)->get()->keyBy('id');

        DB::transaction(function () use ($ids, $contracts) {
            foreach (array_values(array_unique($ids)) as $position => $id) {
                $contracts->get($id)?->forceFill(['monitor_position' => $position])->save();
            }
        });

        return back();
    }

    /**
     * Back to the order of creation.
     */
    public function resetOrder(Request $request): RedirectResponse
    {
        $request->user()->maintenanceContracts()->update(['monitor_position' => null]);

        return back()->with('success', 'Reihenfolge zurückgesetzt – sortiert nach Erstellung.');
    }

    /**
     * Switch the monitoring of a contract on or off.
     */
    public function toggle(Request $request, MaintenanceContract $contract): RedirectResponse
    {
        Gate::authorize('update', $contract);

        $enabled = $request->validate(['enabled' => 'required|boolean'])['enabled'];

        // Switching off also resets the status (see MaintenanceContract::booted)
        $contract->update(['monitor_enabled' => $enabled]);

        return back()->with('success', $enabled
            ? "Überwachung für {$contract->name} ist an – die erste Prüfung folgt in wenigen Minuten."
            : "Überwachung für {$contract->name} ist aus.");
    }
}
