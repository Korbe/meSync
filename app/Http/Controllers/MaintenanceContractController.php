<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceContractType;
use App\Http\Requests\MaintenanceContract\MaintenanceContractRequest;
use App\Models\MaintenanceContract;
use App\Services\ContractPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceContractController extends Controller
{
    public function index(Request $request): Response
    {
        // A freelancer has few enough contracts to load them all; filtering and stats happen client-side
        $contracts = $request->user()->maintenanceContracts()
            ->with(['customer', 'monitor'])
            ->orderBy('next_invoice_date')
            ->get();

        return Inertia::render('MaintenanceContracts/Index', [
            'contracts' => $contracts,
            'dueSoonDays' => MaintenanceContract::DUE_SOON_DAYS,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('MaintenanceContracts/Create', [
            ...$this->formOptions($request),
            'customerId' => $request->integer('customer') ?: null,
        ]);
    }

    public function store(MaintenanceContractRequest $request): RedirectResponse
    {
        [$attributes, $monitoring] = $this->attributes($request);
        $request->user()->maintenanceContracts()->create($attributes)->syncMonitor($monitoring);

        return to_route('contracts.index')->with('success', 'Wartungsvertrag wurde angelegt.');
    }

    public function edit(Request $request, MaintenanceContract $contract): Response
    {
        Gate::authorize('update', $contract);

        return Inertia::render('MaintenanceContracts/Update', [
            ...$this->formOptions($request),
            'contract' => $contract->load(['customer', 'invoices', 'monitor', 'project:id,completed_at']),
            'dueSoonDays' => MaintenanceContract::DUE_SOON_DAYS,
        ]);
    }

    public function update(MaintenanceContractRequest $request, MaintenanceContract $contract): RedirectResponse
    {
        Gate::authorize('update', $contract);

        [$attributes, $monitoring] = $this->attributes($request, $contract);
        $contract->update($attributes);
        $contract->syncMonitor($monitoring);

        return to_route('contracts.edit', $contract)->with('success', 'Wartungsvertrag wurde gespeichert.');
    }

    /**
     * Information sheet for the customer: who, what, how long, how much.
     */
    public function pdf(MaintenanceContract $contract, ContractPdf $pdf)
    {
        Gate::authorize('update', $contract);

        return $pdf->download($contract);
    }


    /**
     * Go-live: the contract starts on the given date, and its first year is billed right away.
     */
    public function start(Request $request, MaintenanceContract $contract): RedirectResponse
    {
        Gate::authorize('update', $contract);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'monitor_enabled' => 'boolean',
        ], ['start_date.required' => 'Bitte gib an, wann die Seite live gegangen ist.']);

        if (! $contract->isPending()) {
            return back()->with('error', 'Dieser Vertrag läuft bereits.');
        }

        $contract->start(Carbon::parse($validated['start_date']));
        $contract->syncMonitor(['enabled' => $request->boolean('monitor_enabled')]);

        // On to the maintenance invoice for the first 12 months (also when the go-live date lies ahead), sent right after saving
        return to_route('invoices.create', ['contract' => $contract, 'send' => 1, 'early' => 1])
            ->with('success', "Wartungsvertrag „{$contract->name}“ läuft ab {$contract->start_date->format('d.m.Y')} – jetzt die Wartungsrechnung erstellen.");
    }

    /**
     * Show the delete confirmation page.
     */
    public function delete(MaintenanceContract $contract): Response
    {
        Gate::authorize('delete', $contract);

        return Inertia::render('MaintenanceContracts/Delete', [
            'contract' => $contract->load('customer')->loadCount('invoices'),
        ]);
    }

    public function destroy(MaintenanceContract $contract): RedirectResponse
    {
        Gate::authorize('delete', $contract);

        $contract->delete();

        return to_route('contracts.index')->with('success', 'Wartungsvertrag wurde gelöscht.');
    }

    /**
     * Contract data and the monitoring settings for its website (see MaintenanceContract::syncMonitor):
     * an empty password field keeps the stored one, without "basic" no credentials are kept at all.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function attributes(MaintenanceContractRequest $request, ?MaintenanceContract $contract = null): array
    {
        $data = $request->validated();
        $monitoring = [
            'enabled' => (bool) ($data['monitor_enabled'] ?? $contract?->monitor?->enabled ?? false),
            'auth' => $data['monitor_auth'] ?? 'none',
            'auth_user' => $data['monitor_auth_user'] ?? null,
            'auth_password' => $data['monitor_auth_password'] ?? null,
        ];
        $data = array_diff_key($data, array_flip(['monitor_enabled', 'monitor_auth', 'monitor_auth_user', 'monitor_auth_password']));

        if ($monitoring['auth'] === 'basic' && blank($monitoring['auth_password']) && ! $contract?->monitor?->has_password) {
            throw ValidationException::withMessages(['monitor_auth_password' => 'Bitte gib das Passwort für den Passwortschutz an.']);
        }

        return [$data, $monitoring];
    }

    private function formOptions(Request $request): array
    {
        return [
            'customers' => $request->user()->customers()
                ->orderBy('last_name')
                ->orderBy('company_name')
                ->get()
                ->map(fn ($customer) => ['value' => $customer->id, 'label' => $customer->display_name]),
            'types' => MaintenanceContractType::options(),
        ];
    }
}
