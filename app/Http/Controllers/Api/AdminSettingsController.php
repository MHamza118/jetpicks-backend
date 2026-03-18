<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminSettingsController extends Controller
{
    public function getSettings(): JsonResponse
    {
        return response()->json([
            'data' => [
                'support_email' => SystemSetting::get('support_email', 'support@jetpicker.com'),
                'support_phone' => SystemSetting::get('support_phone', '+1 (555) 000-0000'),
                'jetpicker_commission' => (float) SystemSetting::get('jetpicker_commission', 6.5),
                'payment_fee' => (float) SystemSetting::get('payment_fee', 4),
                'platform_status' => SystemSetting::get('platform_status', 'active'),
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'support_email' => 'required|email',
            'support_phone' => 'required|string',
            'jetpicker_commission' => 'required|numeric|min:0|max:100',
            'payment_fee' => 'required|numeric|min:0|max:100',
            'platform_status' => 'required|in:active,maintenance,inactive',
        ]);

        SystemSetting::set('support_email', $request->support_email);
        SystemSetting::set('support_phone', $request->support_phone);
        SystemSetting::set('jetpicker_commission', $request->jetpicker_commission);
        SystemSetting::set('payment_fee', $request->payment_fee);
        SystemSetting::set('platform_status', $request->platform_status);

        return response()->json([
            'message' => 'Settings updated successfully',
            'data' => [
                'support_email' => $request->support_email,
                'support_phone' => $request->support_phone,
                'jetpicker_commission' => (float) $request->jetpicker_commission,
                'payment_fee' => (float) $request->payment_fee,
                'platform_status' => $request->platform_status,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email,' . $request->user()->id,
        ]);

        $admin = $request->user();
        $admin->name = $request->name;
        $admin->email = $request->email;
        $admin->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
                'is_active' => $admin->is_active,
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|different:current_password',
        ]);

        $admin = $request->user();

        if (!Hash::check($request->current_password, $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect'],
            ]);
        }

        $admin->password = Hash::make($request->new_password);
        $admin->save();

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }
}
