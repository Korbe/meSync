<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoicePaymentController extends Controller
{
    /**
     * Record a (partial) payment for an invoice.
     */
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        // Cancelled invoices and cancellation invoices cannot receive payments
        Gate::authorize('update', $invoice);

        $request->merge([
            'amount' => str_replace(',', '.', (string) $request->input('amount')),
            'method' => $request->input('method', PaymentMethod::Transfer->value),
        ]);

        $validated = $request->validate([
            'paid_at' => 'required|date',
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max(0.01, $invoice->outstanding)],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'note' => 'nullable|string|max:255',
        ], [
            'amount.max' => 'Der Betrag ist höher als der offene Betrag ('.number_format($invoice->outstanding, 2, ',', '.').' €).',
            'amount.min' => 'Bitte gib einen Betrag an.',
        ]);

        $invoice->payments()->create($validated);

        $message = $invoice->fresh()->payment_status === 'paid'
            ? "Rechnung {$invoice->number} ist vollständig bezahlt."
            : 'Zahlung wurde erfasst.';

        return back()->with('success', $message);
    }

    public function destroy(Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $payment->delete();

        return back()->with('success', 'Zahlung wurde entfernt.');
    }
}
