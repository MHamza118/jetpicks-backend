<?php

namespace App\Http\Requests;

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
            'push_notifications_enabled' => 'sometimes|boolean',
            'in_app_notifications_enabled' => 'sometimes|boolean',
            'message_notifications_enabled' => 'sometimes|boolean',
            'location_services_enabled' => 'sometimes|boolean',
            'translation_language' => 'sometimes|string|max:50',
            'auto_translate_messages' => 'sometimes|boolean',
            'show_original_and_translated' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'push_notifications_enabled.boolean' => 'Push notifications enabled must be a boolean',
            'in_app_notifications_enabled.boolean' => 'In-app notifications enabled must be a boolean',
            'message_notifications_enabled.boolean' => 'Message notifications enabled must be a boolean',
            'location_services_enabled.boolean' => 'Location services enabled must be a boolean',
            'translation_language.string' => 'Translation language must be a string',
            'translation_language.max' => 'Translation language cannot exceed 50 characters',
            'auto_translate_messages.boolean' => 'Auto translate messages must be a boolean',
            'show_original_and_translated.boolean' => 'Show original and translated must be a boolean',
        ];
    }
}
