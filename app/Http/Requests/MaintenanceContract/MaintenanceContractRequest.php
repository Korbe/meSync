<?php

namespace App\Http\Requests\MaintenanceContract;

use App\Enums\MaintenanceContractType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Only the user's own customers can be assigned
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('user_id', $this->user()->id)],
            'type' => ['required', Rule::enum(MaintenanceContractType::class)],
            'name' => 'required|string|max:255',
            'url' => 'nullable|url|max:255',
            'monthly_price' => 'required|numeric|min:0|max:99999',
            'start_date' => 'required|date',
            'next_invoice_date' => 'required|date|after_or_equal:start_date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:5000',
            'monitor_enabled' => 'boolean',
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
            'name.required' => 'Bitte gib einen Projektnamen an.',
            'url.url' => 'Bitte gib eine gültige URL an, z. B. https://beispiel.at',
            'monthly_price.required' => 'Bitte gib einen Monatspreis an.',
            'next_invoice_date.after_or_equal' => 'Die nächste Rechnung kann nicht vor Vertragsbeginn liegen.',
            'end_date.after_or_equal' => 'Das Vertragsende kann nicht vor Vertragsbeginn liegen.',
        ];
    }
}
