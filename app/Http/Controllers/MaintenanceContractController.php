<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceContractType;
use App\Http\Requests\MaintenanceContract\MaintenanceContractRequest;
use App\Models\MaintenanceContract;
use App\Services\SiteMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceContractController extends Controller
{
    public function index(Request $request): Response
    {
        // A freelancer has few enough contracts to load them all; filtering and stats happen client-side
        $contracts = $request->user()->maintenanceContracts()
            ->with('customer')
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
        $request->user()->maintenanceContracts()->create($request->validated());

        return to_route('contracts.index')->with('success', 'Wartungsvertrag wurde angelegt.');
    }

    public function edit(Request $request, MaintenanceContract $contract): Response
    {
        Gate::authorize('update', $contract);

        return Inertia::render('MaintenanceContracts/Update', [
            ...$this->formOptions($request),
            'contract' => $contract->load(['customer', 'invoices']),
        ]);
    }

    public function update(MaintenanceContractRequest $request, MaintenanceContract $contract): RedirectResponse
    {
        Gate::authorize('update', $contract);

        $contract->update($request->validated());

        return to_route('contracts.edit', $contract)->with('success', 'Wartungsvertrag wurde gespeichert.');
    }

    /**
     * Check the website right now (instead of waiting for the scheduler).
     */
    public function check(MaintenanceContract $contract, SiteMonitor $monitor): RedirectResponse
    {
        Gate::authorize('update', $contract);

        abort_if(blank($contract->url), 422, 'Für diesen Vertrag ist keine URL hinterlegt.');

        $monitor->check($contract->load(['user', 'customer']));

        return back()->with(
            $contract->monitor_status === 'down' || $contract->monitor_error ? 'error' : 'success',
            $contract->monitor_error ? "Website nicht erreichbar: {$contract->monitor_error}" : 'Website ist erreichbar.',
        );
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
     * @return array<string, mixed>
     */
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
