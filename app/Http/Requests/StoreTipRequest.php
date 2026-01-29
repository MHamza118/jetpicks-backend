<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|uuid|exists:orders,id',
            'amount' => 'required|numeric|min:0.01|max:999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Tip amount must be at least 0.01',
            'amount.max' => 'Tip amount cannot exceed 999.99',
        ];
    }
}
