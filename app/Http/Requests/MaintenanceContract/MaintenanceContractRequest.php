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
     * Only the domain may be typed in: "musterfirma.at" becomes "https://musterfirma.at".
     */
    protected function prepareForValidation(): void
    {
        $url = trim((string) $this->input('url'));

        if ($url !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }

        $this->merge(['url' => $url === '' ? null : rtrim($url, '/')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Waiting for the go-live: dates and monitoring are set when the contract is started
        $pending = (bool) $this->route('contract')?->isPending();

        return [
            // Only the user's own customers can be assigned
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('user_id', $this->user()->id)],
            'type' => ['required', Rule::enum(MaintenanceContractType::class)],
            'name' => 'required|string|max:255',
            'url' => 'nullable|url:http,https|max:255',
            'monthly_price' => 'required|numeric|min:0|max:99999',
            'start_date' => $pending ? 'exclude' : 'required|date',
            'next_invoice_date' => $pending ? 'exclude' : 'required|date|after_or_equal:start_date',
            'end_date' => $pending ? 'nullable|date' : 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:5000',
            'monitor_enabled' => $pending ? 'exclude' : 'boolean',
            // Password protected websites (htpasswd)
            'monitor_auth' => 'nullable|in:none,basic,accept_401',
            'monitor_auth_user' => 'nullable|required_if:monitor_auth,basic|string|max:255',
            'monitor_auth_password' => 'nullable|string|max:255',
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
            'url.url' => 'Bitte gib eine gültige Domain an, z. B. musterfirma.at',
            'monthly_price.required' => 'Bitte gib einen Monatspreis an.',
            'next_invoice_date.after_or_equal' => 'Die nächste Rechnung kann nicht vor Vertragsbeginn liegen.',
            'end_date.after_or_equal' => 'Das Vertragsende kann nicht vor Vertragsbeginn liegen.',
            'monitor_auth_user.required_if' => 'Bitte gib den Benutzernamen für den Passwortschutz an.',
        ];
    }
}
