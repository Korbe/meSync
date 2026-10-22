<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept "12,50" as well as "12.50".
     */
    protected function prepareForValidation(): void
    {
        $decimal = fn ($value) => is_string($value) ? str_replace(',', '.', $value) : $value;

        $this->merge([
            'tax_rate' => $decimal($this->input('tax_rate')),
            'paid_amount' => $decimal($this->input('paid_amount', 0)) ?: 0,
            'items' => collect($this->input('items', []))->map(fn ($item) => [
                ...$item,
                'quantity' => $decimal($item['quantity'] ?? null),
                'unit_price' => $decimal($item['unit_price'] ?? null),
            ])->all(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;
        $invoice = $this->route('invoice');

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('user_id', $userId)],
            'maintenance_contract_id' => ['nullable', Rule::exists('maintenance_contracts', 'id')->where('user_id', $userId)],
            'number' => ['required', 'string', 'max:50', Rule::unique('invoices', 'number')->where('user_id', $userId)->ignore($invoice?->id)],
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'paid_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
            'paid_in_cash' => 'boolean',
            // Existing invoice as PDF, replaces the generated one
            'original' => 'nullable|file|mimes:pdf|max:10240',
            // Accepted offer billed with this invoice
            'offer_id' => ['nullable', 'integer', Rule::exists('offers', 'id')->where('user_id', $userId)->whereNotNull('accepted_at')->whereNull('invoice_id')],
            // Stopwatch runs billed with this invoice
            'time_session_ids' => 'nullable|array',
            'time_session_ids.*' => ['integer', Rule::exists('time_sessions', 'id')->where('user_id', $userId)->whereNull('invoice_id')],
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:2000',
            'items.*.quantity' => 'required|numeric|min:0.01|max:999999',
            'items.*.unit' => 'nullable|string|max:30',
            'items.*.unit_price' => 'required|numeric|min:-999999|max:999999',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'original.mimes' => 'Die Rechnung muss ein PDF sein.',
            'original.max' => 'Das PDF darf maximal 10 MB groß sein.',
            'customer_id.required' => 'Bitte wähle einen Kunden aus.',
            'customer_id.exists' => 'Bitte wähle einen Kunden aus.',
            'number.required' => 'Bitte gib eine Rechnungsnummer an.',
            'number.unique' => 'Diese Rechnungsnummer gibt es bereits.',
            'due_date.after_or_equal' => 'Das Fälligkeitsdatum kann nicht vor dem Rechnungsdatum liegen.',
            'items.required' => 'Die Rechnung braucht mindestens eine Position.',
            'items.min' => 'Die Rechnung braucht mindestens eine Position.',
            'items.*.description.required' => 'Bitte gib eine Beschreibung an.',
            'items.*.quantity.required' => 'Menge fehlt.',
            'items.*.quantity.min' => 'Menge muss größer als 0 sein.',
            'items.*.unit_price.required' => 'Preis fehlt.',
        ];
    }
}
