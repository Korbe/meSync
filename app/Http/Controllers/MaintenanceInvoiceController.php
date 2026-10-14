<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaintenanceContract\MaintenanceInvoiceRequest;
use App\Models\MaintenanceContract;
use App\Models\MaintenanceInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class MaintenanceInvoiceController extends Controller
{
    /**
     * Mark the currently due yearly invoice as sent.
     */
    public function store(MaintenanceInvoiceRequest $request, MaintenanceContract $contract): RedirectResponse
    {
        Gate::authorize('update', $contract);

        if (! $contract->isBillable()) {
            return back()->with('error', 'Dieser Vertrag ist beendet, es ist keine Rechnung mehr fällig.');
        }

        $invoice = $contract->markInvoiced(
            Carbon::parse($request->validated('invoiced_at') ?? today()),
            $request->validated('invoice_number'),
        );

        return back()->with('success', sprintf(
            'Rechnung für %s – %s vermerkt. Nächste Rechnung: %s',
            $invoice->period_start->format('d.m.Y'),
            $invoice->period_end->format('d.m.Y'),
            $contract->next_invoice_date->format('d.m.Y'),
        ));
    }

    /**
     * Undo the most recent invoice (only the latest one, so the schedule stays consistent).
     */
    public function destroy(MaintenanceContract $contract, MaintenanceInvoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $contract);

        abort_unless($invoice->maintenance_contract_id === $contract->id, 404);
        abort_unless($contract->invoices()->first()?->is($invoice), 422, 'Nur die letzte Rechnung kann rückgängig gemacht werden.');

        $contract->undoInvoice($invoice);

        return back()->with('success', 'Rechnung wurde rückgängig gemacht.');
    }
}
