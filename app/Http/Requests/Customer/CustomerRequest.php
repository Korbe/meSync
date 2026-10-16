<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerInterest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the UID number ("atu 123 456 78" -> "ATU12345678").
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('uid_number')) {
            $this->merge([
                'uid_number' => strtoupper(preg_replace('/\s+/', '', $this->input('uid_number'))),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title_prefix' => 'nullable|string|max:50',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255|required_without:company_name',
            'title_suffix' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255|required_without:last_name',
            'company_suffix' => 'nullable|string|max:50',
            'street' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            // EU VAT ID: two-letter country code + 2-13 characters, e.g. ATU12345678
            'uid_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z]{2}[0-9A-Z]{2,13}$/'],
            'notes' => 'nullable|string|max:5000',
            'interests' => 'nullable|array',
            'interests.*' => ['distinct', Rule::enum(CustomerInterest::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'last_name.required_without' => 'Bitte gib einen Nachnamen oder einen Firmennamen an.',
            'company_name.required_without' => 'Bitte gib einen Firmennamen oder einen Nachnamen an.',
            'uid_number.regex' => 'Die UID-Nummer muss das Format ATU12345678 haben.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
        ];
    }
}
