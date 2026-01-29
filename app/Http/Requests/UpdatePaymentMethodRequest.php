<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'card_holder_name' => 'nullable|string|max:255',
            'billing_address' => 'nullable|string|max:500',
            'paypal_email' => 'nullable|email|max:255',
            'is_default' => 'nullable|boolean',
        ];
    }
}
