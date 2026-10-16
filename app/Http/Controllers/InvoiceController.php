<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\Invoice\InvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MaintenanceContract;
use App\Services\InvoicePdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $invoices = $request->user()->invoices()
            ->with(['items', 'payments', 'cancels:id,number', 'cancellation:id,number,cancels_invoice_id'])
            ->withExists(['media as has_original' => fn ($query) => $query->where('collection_name', 'original')])
            ->latest('invoice_date')
            ->latest('id')
            ->get();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->businessProfile;
        $today = today();

        $invoice = [
            'customer_id' => $request->integer('customer') ?: null,
            'maintenance_contract_id' => null,
            'number' => Invoice::nextNumberFor($user, $today->year),
            'invoice_date' => $today->toDateString(),
            'due_date' => $today->copy()->addDays($profile->payment_terms_days ?? 14)->toDateString(),
            'tax_rate' => $profile->default_tax_rate ?? 20,
            'paid_amount' => 0,
            'notes' => '',
            'items' => [['description' => '', 'quantity' => 1, 'unit' => 'Stück', 'unit_price' => '']],
        ];

        // Prefill from a due maintenance contract
        if ($contractId = $request->integer('contract')) {
            $contract = $user->maintenanceContracts()->findOrFail($contractId);
            $periodEnd = $contract->next_invoice_date->copy()->addYear()->subDay();

            $invoice['customer_id'] = $contract->customer_id;
            $invoice['maintenance_contract_id'] = $contract->id;
            $invoice['items'] = [[
                'description' => "{$contract->type_label} Wartung & Betreuung – {$contract->name}\n"
                    .'Zeitraum: '.$contract->next_invoice_date->format('d.m.Y').' – '.$periodEnd->format('d.m.Y'),
                'quantity' => 1,
                'unit' => 'Stück',
                'unit_price' => $contract->annual_price,
            ]];
        }

        return Inertia::render('Invoices/Create', [
            ...$this->formOptions($request),
            'invoice' => $invoice,
            'contract' => isset($contract) ? $contract->only(['id', 'name', 'next_invoice_date']) : null,
        ]);
    }

    public function store(InvoiceRequest $request): RedirectResponse
    {
        $user = $request->user();

        $invoice = DB::transaction(function () use ($request, $user) {
            $invoice = $user->invoices()->create([
                ...$this->invoiceAttributes($request),
                'maintenance_contract_id' => $request->validated('maintenance_contract_id'),
            ]);

            $this->syncItems($invoice, $request->validated('items'));

            // Paid in cash on the spot: record the payment right away
            if ($request->boolean('paid_in_cash') && $invoice->fresh()->outstanding > 0) {
                $invoice->payments()->create([
                    'paid_at' => $invoice->invoice_date,
                    'amount' => $invoice->fresh()->outstanding,
                    'method' => PaymentMethod::Cash,
                    'note' => 'Bar bezahlt',
                ]);

                // The PDF shows "Bar erhalten" as a receipt only for these invoices
                $invoice->update(['paid_in_cash' => true]);
            }

            // Created from a maintenance contract: record the yearly billing and move the due date on
            if ($invoice->maintenance_contract_id) {
                $contract = MaintenanceContract::findOrFail($invoice->maintenance_contract_id);

                if ($contract->isBillable()) {
                    $contract->markInvoiced($invoice->invoice_date, $invoice->number)
                        ->update(['invoice_id' => $invoice->id]);
                }
            }

            return $invoice;
        });

        if ($request->hasFile('original')) {
            $invoice->addMediaFromRequest('original')->toMediaCollection('original');
        }

        return to_route('invoices.edit', $invoice)->with('success', "Rechnung {$invoice->number} wurde erstellt.");
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        return Inertia::render('Invoices/Update', [
            ...$this->formOptions($request),
            'paymentMethods' => PaymentMethod::options(),
            'invoice' => $invoice->load(['items', 'payments', 'maintenanceContract', 'cancels:id,number,invoice_date', 'cancellation:id,number,cancels_invoice_id']),
            'original' => ($media = $invoice->getFirstMedia('original')) ? ['name' => $media->file_name, 'size' => $media->size] : null,
            'locked' => $invoice->isLocked(),
            'canCancel' => Gate::allows('cancel', $invoice),
            'canDelete' => Gate::allows('delete', $invoice),
        ]);
    }

    /**
     * Cancel an invoice with a cancellation invoice (Stornorechnung) instead of deleting it.
     */
    public function cancel(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('cancel', $invoice);

        $cancellation = DB::transaction(function () use ($invoice) {
            // A cancelled maintenance invoice has to be billed again: roll the contract back
            if ($this->undoesContractBilling($invoice)) {
                $invoice->maintenanceContract->undoInvoice($invoice->maintenanceInvoice);
            }

            return $invoice->load('items')->cancel();
        });

        return to_route('invoices.edit', $cancellation)
            ->with('success', "Rechnung {$invoice->number} wurde mit {$cancellation->number} storniert.");
    }

    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        DB::transaction(function () use ($request, $invoice) {
            $invoice->update($this->invoiceAttributes($request));
            $this->syncItems($invoice, $request->validated('items'));
        });

        return to_route('invoices.edit', $invoice)->with('success', 'Rechnung wurde gespeichert.');
    }

    /**
     * Show the delete confirmation page.
     */
    public function delete(Invoice $invoice): Response
    {
        Gate::authorize('delete', $invoice);

        return Inertia::render('Invoices/Delete', [
            'invoice' => $invoice->load(['items', 'maintenanceContract', 'maintenanceInvoice', 'cancels:id,number']),
            'undoesContractBilling' => $this->undoesContractBilling($invoice),
        ]);
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);

        DB::transaction(function () use ($invoice) {
            // If this invoice was the latest billing of a maintenance contract, roll the contract back
            if ($this->undoesContractBilling($invoice)) {
                $invoice->maintenanceContract->undoInvoice($invoice->maintenanceInvoice);
            }

            $invoice->delete();
        });

        return to_route('invoices.index')->with('success', 'Rechnung wurde gelöscht.');
    }

    /**
     * Generate the PDF on the fly. Nothing is stored on the server.
     */
    public function download(Invoice $invoice, InvoicePdf $pdf)
    {
        Gate::authorize('view', $invoice);

        return $pdf->download($invoice);
    }

    /**
     * Attach an existing invoice as PDF: it replaces the generated one from now on.
     */
    public function attachOriginal(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('view', $invoice);

        $request->validate(self::ORIGINAL_RULES, self::ORIGINAL_MESSAGES);

        $invoice->addMediaFromRequest('original')->toMediaCollection('original');

        return back()->with('success', 'Original-PDF wurde angehängt und ersetzt ab jetzt das erzeugte PDF.');
    }

    public function removeOriginal(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('view', $invoice);

        $invoice->clearMediaCollection('original');

        return back()->with('success', 'Original-PDF wurde entfernt – meSync erzeugt das PDF wieder selbst.');
    }

    private const ORIGINAL_RULES = ['original' => 'required|file|mimes:pdf|max:10240'];

    private const ORIGINAL_MESSAGES = [
        'original.required' => 'Bitte wähle ein PDF aus.',
        'original.mimes' => 'Die Rechnung muss ein PDF sein.',
        'original.max' => 'Das PDF darf maximal 10 MB groß sein.',
    ];

    /**
     * @return array<string, mixed>
     */
    private function invoiceAttributes(InvoiceRequest $request): array
    {
        $customer = Customer::findOrFail($request->validated('customer_id'));

        return [
            ...$request->safe()->only(['customer_id', 'number', 'tax_rate', 'notes']),
            'invoice_date' => Carbon::parse($request->validated('invoice_date')),
            'due_date' => Carbon::parse($request->validated('due_date')),
            'paid_amount' => $request->validated('paid_amount') ?? 0,
            'recipient' => $customer->toRecipient(),
            'issuer' => $request->user()->businessProfile?->toIssuer() ?? [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $invoice->items()->create([
                'position' => $index + 1,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? null,
                'unit_price' => $item['unit_price'],
            ]);
        }
    }

    private function undoesContractBilling(Invoice $invoice): bool
    {
        $billing = $invoice->maintenanceInvoice;

        return $billing !== null
            && $invoice->maintenanceContract !== null
            && $invoice->maintenanceContract->invoices()->first()?->is($billing);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $profile = $request->user()->businessProfile;

        return [
            'customers' => $request->user()->customers()
                ->orderBy('last_name')
                ->orderBy('company_name')
                ->get()
                ->map(fn (Customer $customer) => [
                    'value' => $customer->id,
                    'label' => $customer->display_name,
                    'recipient' => $customer->toRecipient(),
                ]),
            'catalog' => $request->user()->catalogItems()->orderBy('name')->get(['id', 'name', 'description', 'unit', 'unit_price', 'pricing', 'base_minutes', 'step_minutes', 'step_price']),
            'paymentTermsDays' => $profile->payment_terms_days ?? 14,
            // Missing sender or bank data makes the invoice incomplete; the form shows a hint
            'profileComplete' => $profile !== null && filled($profile->name) && filled($profile->street) && filled($profile->iban),
        ];
    }
}
