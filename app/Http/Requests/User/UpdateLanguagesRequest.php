<?php

namespace App\Http\Requests\User;

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
            'languages' => ['required', 'array'],
            'languages.*' => ['string', 'max:50'],
        ];
    }
}
