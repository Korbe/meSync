<?php

namespace App\Http\Requests\Emotion;

use Illuminate\Foundation\Http\FormRequest;

class EmotionRequest extends FormRequest
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
            'score' => 'required|integer|min:1|max:100',
            'primary' => 'required|string',
            'secondary' => 'required|string',
            'description' => 'nullable|string',
        ];
    }
}
