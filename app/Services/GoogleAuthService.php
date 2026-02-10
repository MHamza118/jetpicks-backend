<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;

class GoogleAuthService
{
    /**
     * Verify Google access token and authenticate/register user
     */
    public function authenticateWithGoogle(string $accessToken, ?string $role = null): array
    {
        try {
            // Verify the access token with Google's tokeninfo endpoint
            $response = Http::timeout(10)->get('https://www.googleapis.com/oauth2/v1/tokeninfo', [
                'access_token' => $accessToken,
            ]);

            if (!$response->successful()) {
                throw ValidationException::withMessages([
                    'token' => ['Invalid or expired Google token'],
                ]);
            }

            $tokenInfo = $response->json();

            // Check token expiration
            $expiresIn = $tokenInfo['expires_in'] ?? 0;
            if ($expiresIn <= 0) {
                throw ValidationException::withMessages([
                    'token' => ['Google token has expired'],
                ]);
            }

            // Get user info from Google
            $userResponse = Http::timeout(10)->withToken($accessToken)->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if (!$userResponse->successful()) {
                throw ValidationException::withMessages([
                    'token' => ['Failed to fetch user info from Google'],
                ]);
            }

            $userInfo = $userResponse->json();

            // Validate email exists
            if (empty($userInfo['email'])) {
                throw ValidationException::withMessages([
                    'token' => ['Google account does not have an email'],
                ]);
            }

            // Extract user data
            $email = $userInfo['email'];
            $fullName = $userInfo['name'] ?? 'User';
            $avatarUrl = $userInfo['picture'] ?? null;

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
                'token' => ['Google authentication failed: ' . $e->getMessage()],
            ]);
        }
    }
}
