<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayoutMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_default' => 'boolean',
            'bank_name' => 'string|max:100',
            'account_number' => 'string|max:50',
            'paypal_email' => 'email|max:100',
            'wallet_type' => 'string|max:50',
            'wallet_mobile_number' => 'string|max:20',
        ];
    }
}
