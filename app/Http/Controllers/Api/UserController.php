<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Requests\UpdateLanguagesRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }


    public function profile(Request $request): JsonResponse
    {
        $profile = $this->userService->getProfile($request->user());
        return response()->json([
            'data' => $profile,
        ]);
    }


    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        // Get validated data (excluding files)
        $validatedData = $request->validated();
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $validatedData['image'] = $file;
        }
        
        // Pass to service
        $user = $this->userService->updateProfile($request->user(), $validatedData);
        
        // Update languages if provided
        if ($request->has('languages')) {
            $this->userService->updateLanguages($request->user(), $request->input('languages'));
            $user = $user->fresh(['languages']);
        }
        
        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $this->userService->getProfile($user),
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        $user = $this->userService->updateAvatar($request->user(), $request->file('image'));

        return response()->json([
            'message' => 'Avatar updated successfully',
            'data' => [
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }


    public function getPublicProfile(string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $profile = $this->userService->getPublicProfile($user);
        return response()->json([
            'data' => $profile,
        ]);
    }

    public function getPickerProfile(string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $profile = $this->userService->getPickerProfile($user);
        return response()->json([
            'data' => $profile,
        ]);
    }

    public function getOrdererProfile(string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $profile = $this->userService->getOrdererProfile($user);
        return response()->json([
            'data' => $profile,
        ]);
    }
    public function addLanguage(UpdateLanguagesRequest $request): JsonResponse
    {
        $language = $this->userService->addLanguage($request->user(), $request->validated()['language_name']);
        return response()->json([
            'message' => 'Language added successfully',
            'data' => [
                'id' => $language->id,
                'language_name' => $language->language_name,
            ],
        ], 201);
    }


    public function removeLanguage(Request $request, string $languageId): JsonResponse
    {
        $deleted = $this->userService->removeLanguage($request->user(), $languageId);
        if (!$deleted) {
            return response()->json([
                'message' => 'Language not found',
            ], 404);
        }
        return response()->json([
            'message' => 'Language removed successfully',
        ]);
    }

    public function updateLanguages(Request $request): JsonResponse
    {
        $request->validate([
            'languages' => 'required|array|min:1',
            'languages.*' => 'string|max:50',
        ]);
        $this->userService->updateLanguages($request->user(), $request->input('languages'));
        return response()->json([
            'message' => 'Languages updated successfully',
            'data' => $this->userService->getProfile($request->user()),
        ]);
    }


    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        $settings = $this->userService->updateSettings($request->user(), $request->validated());
        return response()->json([
            'message' => 'Settings updated successfully',
            'data' => [
                'push_notifications_enabled' => $settings->push_notifications_enabled,
                'in_app_notifications_enabled' => $settings->in_app_notifications_enabled,
                'message_notifications_enabled' => $settings->message_notifications_enabled,
                'location_services_enabled' => $settings->location_services_enabled,
                'translation_language' => $settings->translation_language,
                'auto_translate_messages' => $settings->auto_translate_messages,
                'show_original_and_translated' => $settings->show_original_and_translated,
            ],
        ]);
    }


    public function getSettings(Request $request): JsonResponse
    {
        $settings = $this->userService->getSettings($request->user());
        if (!$settings) {
            return response()->json([
                'data' => null,
            ]);
        }
        return response()->json([
            'data' => $settings,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();
        $user->update([
            'password_hash' => bcrypt($request->input('new_password')),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    public function getPickerSettings(Request $request): JsonResponse
    {
        $settings = $this->userService->getSettings($request->user(), 'picker');
        if (!$settings) {
            return response()->json([
                'data' => null,
            ]);
        }
        return response()->json([
            'data' => $settings,
        ]);
    }

    public function updatePickerSettings(UpdateSettingsRequest $request): JsonResponse
    {
        $settings = $this->userService->updateSettings($request->user(), $request->validated(), 'picker');
        return response()->json([
            'message' => 'Picker settings updated successfully',
            'data' => [
                'push_notifications_enabled' => $settings->push_notifications_enabled,
                'in_app_notifications_enabled' => $settings->in_app_notifications_enabled,
                'message_notifications_enabled' => $settings->message_notifications_enabled,
                'location_services_enabled' => $settings->location_services_enabled,
                'translation_language' => $settings->translation_language,
                'auto_translate_messages' => $settings->auto_translate_messages,
                'show_original_and_translated' => $settings->show_original_and_translated,
            ],
        ]);
    }

    public function getOrdererSettings(Request $request): JsonResponse
    {
        $settings = $this->userService->getSettings($request->user(), 'orderer');
        if (!$settings) {
            return response()->json([
                'data' => null,
            ]);
        }
        return response()->json([
            'data' => $settings,
        ]);
    }

    public function updateOrdererSettings(UpdateSettingsRequest $request): JsonResponse
    {
        $settings = $this->userService->updateSettings($request->user(), $request->validated(), 'orderer');
        return response()->json([
            'message' => 'Orderer settings updated successfully',
            'data' => [
                'push_notifications_enabled' => $settings->push_notifications_enabled,
                'in_app_notifications_enabled' => $settings->in_app_notifications_enabled,
                'message_notifications_enabled' => $settings->message_notifications_enabled,
                'location_services_enabled' => $settings->location_services_enabled,
                'translation_language' => $settings->translation_language,
                'auto_translate_messages' => $settings->auto_translate_messages,
                'show_original_and_translated' => $settings->show_original_and_translated,
            ],
        ]);
    }

    /**
     * DELETE /api/user
     *
     * Permanently delete the authenticated user's account and all personal data.
     * Required for GDPR / UK data law compliance.
     *
     * This hard-deletes:
     *   - The user record
     *   - All personal data (languages, settings)
     *   - Avatar file from storage
     *   - Stripe Connect account (if exists)
     *
     * Orders, reviews and chat history are anonymised (user_id set to null)
     * rather than deleted, to preserve records for the other party.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'confirmation' => 'required|string|in:DELETE', // Extra safety: client must send "DELETE"
        ]);

        $user = $request->user();

        try {
            \DB::transaction(function () use ($user) {
                // Anonymise orders (keep records for other party, remove personal link)
                \DB::table('orders')
                    ->where('orderer_id', $user->id)
                    ->update(['orderer_id' => null]);

                \DB::table('orders')
                    ->where('assigned_picker_id', $user->id)
                    ->update(['assigned_picker_id' => null]);

                // Anonymise chat messages
                \DB::table('chat_messages')
                    ->where('sender_id', $user->id)
                    ->update(['sender_id' => null]);

                // Delete avatar from storage
                if ($user->avatar_url) {
                    $path = str_replace('/storage/', '', $user->avatar_url);
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
                }

                // Delete Stripe Connect account if exists
                if ($user->stripe_connect_account_id) {
                    try {
                        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY', env('STRIPE_SECRET')));
                        \Stripe\Account::retrieve($user->stripe_connect_account_id)->delete();
                    } catch (\Exception $e) {
                        \Log::warning('Could not delete Stripe account during user deletion', [
                            'user_id' => $user->id,
                            'error'   => $e->getMessage(),
                        ]);
                    }
                }

                // Revoke all auth tokens
                $user->tokens()->delete();

                // Hard delete the user (cascades to languages, settings via DB constraints)
                $user->delete();
            });

            \Log::info('User account deleted', ['user_id' => $user->id]);

            return response()->json([
                'message' => 'Account deleted successfully. All personal data has been removed.',
            ]);

        } catch (\Exception $e) {
            \Log::error('Account deletion failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Account deletion failed. Please contact support.'], 500);
        }
    }
}
