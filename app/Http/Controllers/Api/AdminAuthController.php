<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /**
     * Admin login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('email', $request->email)
                     ->where('is_active', true)
                     ->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid admin credentials.'],
            ]);
        }

        // Revoke existing tokens
        $admin->tokens()->delete();

        // Create new token
        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'message' => 'Admin login successful.',
            'data' => [
                'user' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'is_active' => $admin->is_active,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * Admin logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Admin logout successful.',
        ]);
    }

    /**
     * Get current admin user
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();
        
        return response()->json([
            'data' => [
                'user' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'is_active' => $admin->is_active,
                ],
            ],
        ]);
    }
}