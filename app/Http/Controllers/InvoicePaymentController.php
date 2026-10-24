<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Mail\PaymentReceiptMail;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\PaymentReceiptPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * Confirmation for the customer that the payment was received (e.g. cash), in the browser.
     */
    public function receipt(Invoice $invoice, InvoicePayment $payment, PaymentReceiptPdf $pdf): Response
    {
        Gate::authorize('view', $invoice);
        abort_unless($payment->invoice_id === $invoice->id, 404);

        return $pdf->download($payment->setRelation('invoice', $invoice));
    }

    /**
     * Email the confirmation (PDF attached) to the customer, through the queue.
     */
    public function sendReceipt(Request $request, Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        Gate::authorize('view', $invoice);
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $to = $request->validate(['to' => 'required|email:rfc|max:255'], [
            'to.required' => 'Bitte gib die E-Mail-Adresse des Kunden an.',
            'to.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
        ])['to'];

        // The public demo must not become a way to send mails to any address
        if ($request->user()->isDemo()) {
            return back()->with('error', 'Im Demo-Modus werden keine E-Mails verschickt.');
        }

        if (blank($invoice->issuer['email'] ?? $request->user()->businessProfile?->email)) {
            throw ValidationException::withMessages(['to' => 'Hinterlege zuerst im Profil deine Firmen-E-Mail – an sie gehen die Antworten des Kunden.']);
        }

        Mail::to($to)->queue(new PaymentReceiptMail($payment->setRelation('invoice', $invoice)));

        return back()->with('success', "Zahlungsbestätigung wird an {$to} verschickt.");
    }

    public function destroy(Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $payment->delete();

        return back()->with('success', 'Zahlung wurde entfernt.');
    }
}
