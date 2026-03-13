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

            // Verify the access token with Facebook's token debug endpoint
            $verifyResponse = Http::timeout(10)->get('https://graph.facebook.com/debug_token', [
                'input_token' => $accessToken,
                'access_token' => "{$appId}|{$appSecret}",
            ]);

            if (!$verifyResponse->successful()) {
                throw ValidationException::withMessages([
                    'token' => ['Invalid or expired Facebook token'],
                ]);
            }

            $tokenData = $verifyResponse->json();

            // Check if token is valid
            if (!isset($tokenData['data']['is_valid']) || !$tokenData['data']['is_valid']) {
                throw ValidationException::withMessages([
                    'token' => ['Invalid Facebook token'],
                ]);
            }

            // Check token expiration
            $expiresAt = $tokenData['data']['expires_at'] ?? 0;
            if ($expiresAt > 0 && $expiresAt <= time()) {
                throw ValidationException::withMessages([
                    'token' => ['Facebook token has expired'],
                ]);
            }

            // Get user info from Facebook
            $userResponse = Http::timeout(10)->withToken($accessToken)->get('https://graph.facebook.com/me', [
                'fields' => 'id,name,email,picture',
            ]);

            if (!$userResponse->successful()) {
                throw ValidationException::withMessages([
                    'token' => ['Failed to fetch user info from Facebook'],
                ]);
            }

            $userInfo = $userResponse->json();

            // Validate email exists
            if (empty($userInfo['email'])) {
                throw ValidationException::withMessages([
                    'token' => ['Facebook account does not have an email. Please link an email to your Facebook account.'],
                ]);
            }

            // Extract user data
            $email = $userInfo['email'];
            $fullName = $userInfo['name'] ?? 'User';
            $avatarUrl = $userInfo['picture']['data']['url'] ?? null;

            // Check if user exists by email
            $user = User::where('email', $email)->first();

            if ($user) {
                // User exists - just login
                $token = $user->createToken('auth_token')->plainTextToken;
                return [
                    'user' => $user,
                    'token' => $token,
                    'isNewUser' => false,
                ];
            }

            // User doesn't exist - create new user with specified role
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
