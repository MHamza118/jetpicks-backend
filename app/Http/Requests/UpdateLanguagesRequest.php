<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLanguagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'language_name' => 'required|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'language_name.required' => 'Language name is required',
            'language_name.string' => 'Language name must be a string',
            'language_name.max' => 'Language name cannot exceed 50 characters',
        ];
    }
}
