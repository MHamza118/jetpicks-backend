<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone_number' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8'],
            'confirm_password' => ['required', 'same:password'],
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'in:PICKER,ORDERER'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered. Please use a different email or login to your existing account.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'full_name.required' => 'Full name is required.',
            'phone_number.required' => 'Phone number is required.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'confirm_password.required' => 'Please confirm your password.',
            'confirm_password.same' => 'Passwords do not match.',
            'roles.required' => 'Please select at least one role.',
        ];
    }
}
