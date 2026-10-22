<?php

namespace App\Http\Requests\Offer;

use App\Enums\MaintenanceContractType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfferRequest extends FormRequest
{
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
            'maintenance_monthly_price' => $decimal($this->input('maintenance_monthly_price')),
            'features' => $this->input('features', []) ?? [],
            'integrations' => $this->input('integrations', []) ?? [],
            'items' => collect($this->input('items', []))->map(fn ($item) => [
                ...$item,
                'quantity' => $decimal($item['quantity'] ?? null),
                'unit_price' => $decimal($item['unit_price'] ?? null),
                'list_price' => $decimal($item['list_price'] ?? null) ?: null,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;
        $offer = $this->route('offer');
        $type = $this->input('type');

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('user_id', $userId)],
            'type' => ['required', Rule::enum(MaintenanceContractType::class)],
            'number' => ['required', 'string', 'max:50', Rule::unique('offers', 'number')->where('user_id', $userId)->ignore($offer?->id)],
            'title' => 'required|string|max:255',
            'url' => 'nullable|string|max:255',
            'offer_date' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:offer_date',
            'tax_rate' => 'required|numeric|min:0|max:100',
            // A package of the chosen type
            'package' => ['nullable', Rule::in(collect(config('offers.packages'))->where('type', $type)->keys()->all())],
            'features' => 'array',
            'features.*' => ['distinct', Rule::in(array_keys(config('offers.features')))],
            'integrations' => 'array',
            'integrations.*' => ['distinct', Rule::in(array_keys(config('offers.integrations')))],
            'other_function' => 'nullable|string|max:255',
            'content_delivery' => ['nullable', Rule::in(array_keys(config('offers.content_delivery')))],
            'payment_terms' => ['required', Rule::in(array_keys(config('offers.payment_terms')))],
            'reference_customer' => 'boolean',
            // Maintenance is part of every offer
            'maintenance_package' => 'nullable|string|max:50',
            'maintenance_monthly_price' => 'required|numeric|min:1|max:99999',
            'intro' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:5000',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:2000',
            'items.*.quantity' => 'required|numeric|min:0.01|max:999999',
            'items.*.unit' => 'nullable|string|max:30',
            'items.*.unit_price' => 'required|numeric|min:0|max:999999',
            'items.*.list_price' => 'nullable|numeric|min:0|max:999999',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Bitte wähle einen Kunden aus.',
            'customer_id.exists' => 'Bitte wähle einen Kunden aus.',
            'number.required' => 'Bitte gib eine Angebotsnummer an.',
            'number.unique' => 'Diese Angebotsnummer gibt es bereits.',
            'title.required' => 'Bitte gib das Projekt an, z. B. „Website Musterfirma“.',
            'valid_until.after_or_equal' => 'Das Angebot kann nicht vor dem Angebotsdatum ablaufen.',
            'package.in' => 'Dieses Paket passt nicht zur Projektart.',
            'maintenance_monthly_price.required' => 'Die laufende Betreuung ist Teil jedes Angebots – bitte gib den Monatspreis an.',
            'maintenance_monthly_price.min' => 'Die laufende Betreuung ist Teil jedes Angebots – bitte gib den Monatspreis an.',
            'items.required' => 'Das Angebot braucht mindestens eine Position.',
            'items.min' => 'Das Angebot braucht mindestens eine Position.',
            'items.*.description.required' => 'Bitte gib eine Beschreibung an.',
            'items.*.quantity.required' => 'Menge fehlt.',
            'items.*.quantity.min' => 'Menge muss größer als 0 sein.',
            'items.*.unit_price.required' => 'Preis fehlt.',
        ];
    }
}
