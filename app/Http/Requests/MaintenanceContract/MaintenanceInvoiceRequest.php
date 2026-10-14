<?php

namespace App\Http\Requests\MaintenanceContract;

use Illuminate\Foundation\Http\FormRequest;

class MaintenanceInvoiceRequest extends FormRequest
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
            'invoiced_at' => 'nullable|date',
            'invoice_number' => 'nullable|string|max:50',
        ];
    }
}
