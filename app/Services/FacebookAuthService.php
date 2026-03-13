<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;

class FacebookAuthService
{
    /**
     * Verify Facebook access token and authenticate/register user
     */
    public function authenticateWithFacebook(string $accessToken, ?string $role = null): array
    {
        try {
            $appId = env('FACEBOOK_APP_ID');
            $appSecret = env('FACEBOOK_APP_SECRET');

            if (empty($appId) || empty($appSecret)) {
                throw new \RuntimeException('Facebook app credentials are not configured.');
            }

            $appToken = $appId . '|' . $appSecret;

            // Validate the access token using Facebook's debug_token endpoint
            $debugResponse = Http::timeout(10)->get('https://graph.facebook.com/debug_token', [
                'input_token' => $accessToken,
                'access_token' => $appToken,
            ]);

            if (!$debugResponse->successful()) {
                throw ValidationException::withMessages([
                    'token' => ['Invalid or expired Facebook token'],
                ]);
            }

            $debugData = $debugResponse->json('data');

            if (empty($debugData) || empty($debugData['is_valid']) || ($debugData['app_id'] ?? '') !== $appId) {
                throw ValidationException::withMessages([
                    'token' => ['Invalid Facebook token'],
                ]);
            }

            // Get user info from Facebook
            $userResponse = Http::timeout(10)->get('https://graph.facebook.com/me', [
                'fields' => 'id,name,email,picture.type(large)',
                'access_token' => $accessToken,
            ]);

            if (!$userResponse->successful()) {
                throw ValidationException::withMessages([
                    'token' => ['Failed to fetch user info from Facebook'],
                ]);
            }

            $userInfo = $userResponse->json();

            if (empty($userInfo['email'])) {
                throw ValidationException::withMessages([
                    'token' => ['Facebook account does not have an email'],
                ]);
            }

            $email = $userInfo['email'];
            $fullName = $userInfo['name'] ?? 'User';
            $avatarUrl = $userInfo['picture']['data']['url'] ?? null;

            // Check if user exists by email
            $user = User::where('email', $email)->first();

            if ($user) {
                $token = $user->createToken('auth_token')->plainTextToken;

                return [
                    'user' => $user,
                    'token' => $token,
                    'isNewUser' => false,
                ];
            }

            // Create new user with specified role
            $userRole = $role && in_array($role, ['ORDERER', 'PICKER']) ? $role : 'ORDERER';

            $user = User::create([
                'full_name' => $fullName,
                'email' => $email,
                'phone_number' => '',
                'password_hash' => null,
                'avatar_url' => $avatarUrl,
                'roles' => [$userRole],
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
                'isNewUser' => true,
            ];
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'token' => ['Facebook authentication failed: ' . $e->getMessage()],
            ]);
        }
    }
}
