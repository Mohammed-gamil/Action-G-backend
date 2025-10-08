<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function uploadAvatar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'details' => $validator->errors()->all(),
                ],
            ], 422);
        }

        $user = Auth::user();
        $file = $request->file('avatar');
        $path = $file->store('avatars', 'public');

        // Optionally delete old avatar
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = $path;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'data' => [
                'avatar_url' => Storage::disk('public')->url($path),
                'message' => 'Avatar uploaded successfully',
            ],
        ]);
    }

    public function getNotificationPreferences(): JsonResponse
    {
        $user = Auth::user();
        $prefs = $user->notification_preferences ?? [
            'email' => true,
            'push' => false,
            'sms' => false,
            'request_updates' => true,
            'approval_reminders' => true,
            'system_updates' => true,
        ];

        return response()->json([
            'success' => true,
            'data' => [ 'preferences' => $prefs ],
        ]);
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'preferences' => 'required|array',
            'preferences.email' => 'boolean',
            'preferences.push' => 'boolean',
            'preferences.sms' => 'boolean',
            'preferences.request_updates' => 'boolean',
            'preferences.approval_reminders' => 'boolean',
            'preferences.system_updates' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'details' => $validator->errors()->all(),
                ],
            ], 422);
        }

        $user = Auth::user();
        $user->notification_preferences = $request->preferences;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences updated',
        ]);
    }
}
