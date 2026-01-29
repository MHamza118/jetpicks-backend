<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayoutMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method_type' => 'required|in:BANK_ACCOUNT,PAYPAL,MOBILE_WALLET',
            'is_default' => 'boolean',
            'bank_name' => 'required_if:method_type,BANK_ACCOUNT|string|max:100',
            'account_number' => 'required_if:method_type,BANK_ACCOUNT|string|max:50',
            'paypal_email' => 'required_if:method_type,PAYPAL|email|max:100',
            'wallet_type' => 'required_if:method_type,MOBILE_WALLET|string|max:50',
            'wallet_mobile_number' => 'required_if:method_type,MOBILE_WALLET|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'bank_name.required_if' => 'Bank name is required for bank account',
            'account_number.required_if' => 'Account number is required for bank account',
            'paypal_email.required_if' => 'PayPal email is required for PayPal',
            'wallet_type.required_if' => 'Wallet type is required for mobile wallet',
            'wallet_mobile_number.required_if' => 'Mobile number is required for mobile wallet',
        ];
    }
}
