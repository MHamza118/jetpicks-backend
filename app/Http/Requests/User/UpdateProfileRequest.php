<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'string', 'max:50'],
            'country' => ['sometimes', 'string', 'max:100'],
            'image' => ['sometimes', 'nullable', 'file', 'max:10240'], // 10MB Max, accepts all file types
            'languages' => ['sometimes', 'array'],
            'languages.*' => ['string', 'max:50'],
        ];
    }
}
