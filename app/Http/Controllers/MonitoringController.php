<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Services\MonitoringReport;
use App\Services\SiteMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'customers' => $request->user()->customers()->orderBy('last_name')->orderBy('company_name')->get()
                ->map(fn ($customer) => ['value' => $customer->id, 'label' => $customer->display_name]),
        ]);
    }

    /**
     * A website without maintenance contract, e.g. an own app.
     */
    public function store(Request $request, SiteMonitor $checker): RedirectResponse
    {
        $monitor = $request->user()->monitors()->make($this->validated($request));
        $monitor->forceFill(['user_id' => $request->user()->id])->save();

        // First result right away instead of in up to five minutes
        $checker->check($monitor->load(['user', 'customer']));

        return back()->with('success', "„{$monitor->name}“ wird ab jetzt überwacht.");
    }

    /**
     * Name, address and protection of a standalone monitor. Those of a contract are edited there.
     */
    public function update(Request $request, Monitor $monitor): RedirectResponse
    {
        Gate::authorize('update', $monitor);
        abort_if($monitor->maintenance_contract_id !== null, 422, 'Diese Website wird im Wartungsvertrag bearbeitet.');

        $data = $this->validated($request, $monitor);
        $monitor->fill($data);
        if ($monitor->isDirty('url')) {
            // Another address: the old status says nothing about it
            $monitor->forceFill(['status' => null, 'status_since' => null, 'failures' => 0, 'error' => null, 'ssl_expires_at' => null]);
        }
        $monitor->save();

        return back()->with('success', 'Überwachung wurde gespeichert.');
    }

    public function destroy(Monitor $monitor): RedirectResponse
    {
        Gate::authorize('delete', $monitor);
        abort_if($monitor->maintenance_contract_id !== null, 422, 'Diese Website gehört zu einem Wartungsvertrag – dort die URL entfernen.');

        $monitor->delete();

        return back()->with('success', "„{$monitor->name}“ wird nicht mehr überwacht.");
    }

    /**
     * Check the website right now (instead of waiting for the scheduler).
     */
    public function check(Monitor $monitor, SiteMonitor $checker): RedirectResponse
    {
        Gate::authorize('update', $monitor);

        $checker->check($monitor->load(['user', 'customer']));

        return back()->with(
            $monitor->status === 'down' || $monitor->error ? 'error' : 'success',
            $monitor->error ? "Website nicht erreichbar: {$monitor->error}" : 'Website ist erreichbar.',
        );
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

        $monitors = $request->user()->monitors()->whereIn('id', $ids)->get()->keyBy('id');

        DB::transaction(function () use ($ids, $monitors) {
            foreach (array_values(array_unique($ids)) as $position => $id) {
                $monitors->get($id)?->forceFill(['position' => $position])->save();
            }
        });

        return back();
    }

    /**
     * Back to the order of creation.
     */
    public function resetOrder(Request $request): RedirectResponse
    {
        $request->user()->monitors()->update(['position' => null]);

        return back()->with('success', 'Reihenfolge zurückgesetzt – sortiert nach Erstellung.');
    }

    /**
     * Switch the monitoring of a website on or off.
     */
    public function toggle(Request $request, Monitor $monitor): RedirectResponse
    {
        Gate::authorize('update', $monitor);

        $enabled = $request->validate(['enabled' => 'required|boolean'])['enabled'];

        // Switching off also resets the status (see Monitor::booted)
        $monitor->update(['enabled' => $enabled]);

        return back()->with('success', $enabled
            ? "Überwachung für {$monitor->name} ist an – die erste Prüfung folgt in wenigen Minuten."
            : "Überwachung für {$monitor->name} ist aus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Monitor $monitor = null): array
    {
        $url = trim((string) $request->input('url'));
        $request->merge(['url' => $url === '' ? null : rtrim($url, '/')]);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url:http,https|max:255',
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('user_id', $request->user()->id)],
            'auth' => 'nullable|in:none,basic,accept_401',
            'auth_user' => 'nullable|required_if:auth,basic|string|max:255',
            'auth_password' => 'nullable|string|max:255',
        ], [
            'name.required' => 'Bitte gib einen Namen an, z. B. „Meine App“.',
            'url.required' => 'Bitte gib die Adresse an.',
            'url.url' => 'Bitte gib eine gültige Domain an, z. B. app.korbitsch.at',
            'auth_user.required_if' => 'Bitte gib den Benutzernamen für den Passwortschutz an.',
        ]);
        $data['auth'] ??= 'none';

        // Empty password keeps the stored one; without "basic" no credentials are kept
        if ($data['auth'] !== 'basic') {
            return [...$data, 'auth_user' => null, 'auth_password' => null];
        }

        if (blank($data['auth_password'] ?? null)) {
            unset($data['auth_password']);
            if (! $monitor?->has_password) {
                throw ValidationException::withMessages(['auth_password' => 'Bitte gib das Passwort für den Passwortschutz an.']);
            }
        }

        return $data;
    }
}
