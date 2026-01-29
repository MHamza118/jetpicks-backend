<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetOrderRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reward_amount' => 'required|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'reward_amount.required' => 'Reward amount is required',
            'reward_amount.numeric' => 'Reward amount must be a number',
            'reward_amount.min' => 'Reward amount must be greater than 0',
        ];
    }
}
