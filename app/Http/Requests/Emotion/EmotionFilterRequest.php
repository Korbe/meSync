<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmotionFilterRequest extends FormRequest
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
    public function rules()
    {
        return [
            'startDate' => 'nullable|date|before_or_equal:endDate',
            'endDate' => 'nullable|date|after_or_equal:startDate',
        ];
    }
}
