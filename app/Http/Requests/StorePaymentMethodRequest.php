<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method_type' => 'required|in:CREDIT_CARD,PAYPAL',
            'card_holder_name' => 'required_if:method_type,CREDIT_CARD|nullable|string|max:255',
            'card_last_four' => 'required_if:method_type,CREDIT_CARD|nullable|string|size:4|regex:/^\d{4}$/',
            'card_brand' => 'required_if:method_type,CREDIT_CARD|nullable|string|in:VISA,MASTERCARD,AMEX',
            'expiry_month' => 'required_if:method_type,CREDIT_CARD|nullable|integer|min:1|max:12',
            'expiry_year' => 'required_if:method_type,CREDIT_CARD|nullable|integer|min:' . date('Y'),
            'billing_address' => 'nullable|string|max:500',
            'paypal_email' => 'required_if:method_type,PAYPAL|nullable|email|max:255',
            'payment_token' => 'required|string|max:255',
            'is_default' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'card_last_four.regex' => 'Card last four must be 4 digits',
            'expiry_year.min' => 'Card expiry year must be current year or later',
        ];
    }
}
