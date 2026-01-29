<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    //Authorization check
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:5000'],
            'translate' => ['sometimes', 'boolean'],
        ];
    }

    //custom error messages
    public function messages(): array
    {
        return [
            'content.required' => 'Message content is required',
            'content.string' => 'Message content must be a string',
            'content.min' => 'Message content must be at least 1 character',
            'content.max' => 'Message content cannot exceed 5000 characters',
        ];
    }
}
