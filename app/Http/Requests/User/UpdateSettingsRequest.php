<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'push_notifications_enabled' => ['sometimes', 'boolean'],
            'in_app_notifications_enabled' => ['sometimes', 'boolean'],
            'message_notifications_enabled' => ['sometimes', 'boolean'],
            'location_services_enabled' => ['sometimes', 'boolean'],
            'translation_language' => ['sometimes', 'string', 'max:50'],
            'auto_translate_messages' => ['sometimes', 'boolean'],
            'show_original_and_translated' => ['sometimes', 'boolean'],
        ];
    }
}
