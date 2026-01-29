<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLanguage;
use App\Models\PickerSetting;
use App\Models\OrdererSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserService
{
    public function getProfile(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'country' => $user->country,
            'roles' => $user->roles,
            'avatar_url' => $user->avatar_url,
            'languages' => $user->languages->map(fn($lang) => [
                'id' => $lang->id,
                'language_name' => $lang->language_name,
            ])->toArray(),
        ];
    }

    public function updateProfile(User $user, array $data): User
    {
        $updateData = [
            'full_name' => $data['full_name'] ?? $user->full_name,
            'phone_number' => $data['phone_number'] ?? $user->phone_number,
            'country' => $data['country'] ?? $user->country,
        ];

        // Handle image upload
        if (isset($data['image']) && $data['image']) {
            $file = $data['image'];
            
            // Delete old avatar if exists
            if ($user->avatar_url) {
                $oldPath = str_replace('/storage/', '', $user->avatar_url);
                Storage::disk('public')->delete($oldPath);
            }
            
            // Store new avatar in public disk
            $path = $file->store('avatars', 'public');
            
            // Save the full URL path
            $updateData['avatar_url'] = '/storage/' . $path;
        }

        // Update user with new data
        $user->update($updateData);

        return $user->fresh(['languages']);
    }

    public function getPublicProfile(User $user): array
    {
        $completedDeliveries = $user->ordersAsPicker()
            ->where('status', 'COMPLETED')
            ->count();

        $averageRating = $user->reviewsReceived()
            ->avg('rating') ?? 0;

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'avatar_url' => $user->avatar_url,
            'roles' => $user->roles,
            'rating' => round($averageRating, 1),
            'completed_deliveries' => $completedDeliveries,
            'languages' => $user->languages->pluck('language_name')->toArray(),
        ];
    }

    public function getPickerProfile(User $user): array
    {
        $completedDeliveries = $user->ordersAsPicker()
            ->where('status', 'COMPLETED')
            ->count();

        $averageRating = $user->reviewsReceived()
            ->avg('rating') ?? 0;

        $travelJourneys = $user->travelJourneys()
            ->select('id', 'origin_city', 'origin_country', 'destination_city', 'destination_country', 'departure_date', 'arrival_date')
            ->get()
            ->map(fn($j) => [
                'id' => $j->id,
                'origin_city' => $j->origin_city,
                'origin_country' => $j->origin_country,
                'destination_city' => $j->destination_city,
                'destination_country' => $j->destination_country,
                'departure_date' => $j->departure_date,
                'arrival_date' => $j->arrival_date,
            ])
            ->toArray();

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'avatar_url' => $user->avatar_url,
            'rating' => round($averageRating, 1),
            'completed_deliveries' => $completedDeliveries,
            'languages' => $user->languages->pluck('language_name')->toArray(),
            'travel_journeys' => $travelJourneys,
        ];
    }

    public function getOrdererProfile(User $user): array
    {
        $completedOrders = $user->ordersAsOrderer()
            ->where('status', 'COMPLETED')
            ->count();

        $averageRating = $user->reviewsReceived()
            ->avg('rating') ?? 0;

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'avatar_url' => $user->avatar_url,
            'rating' => round($averageRating, 1),
            'completed_orders' => $completedOrders,
            'languages' => $user->languages->pluck('language_name')->toArray(),
        ];
    }

    public function addLanguage(User $user, string $languageName): UserLanguage
    {
        return UserLanguage::create([
            'user_id' => $user->id,
            'language_name' => $languageName,
        ]);
    }

    public function removeLanguage(User $user, string $languageId): bool
    {
        return UserLanguage::where('id', $languageId)
            ->where('user_id', $user->id)
            ->delete() > 0;
    }

    public function updateLanguages(User $user, array $languages): void
    {
        UserLanguage::where('user_id', $user->id)->delete();
        
        foreach ($languages as $language) {
            UserLanguage::create([
                'user_id' => $user->id,
                'language_name' => $language,
            ]);
        }
    }

    public function updateSettings(User $user, array $data, string $role = 'picker'): PickerSetting|OrdererSetting
    {
        // Determine which settings table to use based on role
        if ($role === 'orderer') {
            $settings = $user->ordererSettings;
            if (!$settings) {
                $settings = OrdererSetting::create([
                    'user_id' => $user->id,
                    'push_notifications_enabled' => $data['push_notifications_enabled'] ?? true,
                    'in_app_notifications_enabled' => $data['in_app_notifications_enabled'] ?? true,
                    'message_notifications_enabled' => $data['message_notifications_enabled'] ?? true,
                    'location_services_enabled' => $data['location_services_enabled'] ?? true,
                    'translation_language' => $data['translation_language'] ?? 'English',
                    'auto_translate_messages' => $data['auto_translate_messages'] ?? false,
                    'show_original_and_translated' => $data['show_original_and_translated'] ?? true,
                ]);
            } else {
                $settings->update([
                    'push_notifications_enabled' => $data['push_notifications_enabled'] ?? $settings->push_notifications_enabled,
                    'in_app_notifications_enabled' => $data['in_app_notifications_enabled'] ?? $settings->in_app_notifications_enabled,
                    'message_notifications_enabled' => $data['message_notifications_enabled'] ?? $settings->message_notifications_enabled,
                    'location_services_enabled' => $data['location_services_enabled'] ?? $settings->location_services_enabled,
                    'translation_language' => $data['translation_language'] ?? $settings->translation_language,
                    'auto_translate_messages' => $data['auto_translate_messages'] ?? $settings->auto_translate_messages,
                    'show_original_and_translated' => $data['show_original_and_translated'] ?? $settings->show_original_and_translated,
                ]);
            }
        } else {
            // Default to picker
            $settings = $user->pickerSettings;
            if (!$settings) {
                $settings = PickerSetting::create([
                    'user_id' => $user->id,
                    'push_notifications_enabled' => $data['push_notifications_enabled'] ?? true,
                    'in_app_notifications_enabled' => $data['in_app_notifications_enabled'] ?? true,
                    'message_notifications_enabled' => $data['message_notifications_enabled'] ?? true,
                    'location_services_enabled' => $data['location_services_enabled'] ?? true,
                    'translation_language' => $data['translation_language'] ?? 'English',
                    'auto_translate_messages' => $data['auto_translate_messages'] ?? false,
                    'show_original_and_translated' => $data['show_original_and_translated'] ?? true,
                ]);
            } else {
                $settings->update([
                    'push_notifications_enabled' => $data['push_notifications_enabled'] ?? $settings->push_notifications_enabled,
                    'in_app_notifications_enabled' => $data['in_app_notifications_enabled'] ?? $settings->in_app_notifications_enabled,
                    'message_notifications_enabled' => $data['message_notifications_enabled'] ?? $settings->message_notifications_enabled,
                    'location_services_enabled' => $data['location_services_enabled'] ?? $settings->location_services_enabled,
                    'translation_language' => $data['translation_language'] ?? $settings->translation_language,
                    'auto_translate_messages' => $data['auto_translate_messages'] ?? $settings->auto_translate_messages,
                    'show_original_and_translated' => $data['show_original_and_translated'] ?? $settings->show_original_and_translated,
                ]);
            }
        }

        return $settings->fresh();
    }

    public function getSettings(User $user, string $role = 'picker'): ?array
    {
        // settings to fetch based on role
        if ($role === 'orderer') {
            $settings = $user->ordererSettings;
        } else {
            $settings = $user->pickerSettings;
        }

        if (!$settings) {
            return null;
        }
        return [
            'push_notifications_enabled' => $settings->push_notifications_enabled,
            'in_app_notifications_enabled' => $settings->in_app_notifications_enabled,
            'message_notifications_enabled' => $settings->message_notifications_enabled,
            'location_services_enabled' => $settings->location_services_enabled,
            'translation_language' => $settings->translation_language,
            'auto_translate_messages' => $settings->auto_translate_messages,
            'show_original_and_translated' => $settings->show_original_and_translated,
        ];
    }

    public function updateAvatar(User $user, $imageFile): User
    {
        // Delete old avatar if exists
        if ($user->avatar_url) {
            $oldPath = str_replace('/storage/', '', $user->avatar_url);
            Storage::disk('public')->delete($oldPath);
        }

        // Store new avatar
        $path = $imageFile->store('avatars', 'public');
        $user->update(['avatar_url' => '/storage/' . $path]);

        return $user->fresh();
    }
}
