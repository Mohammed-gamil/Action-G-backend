<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     */
    public function __construct()
    {
        // Middleware is handled in routes, not in constructor for Laravel 11+
    }

    /**
     * Get a JWT via given credentials.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'remember' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'details' => $validator->errors()->all()
                ]
            ], 422);
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->get('remember', false);

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 401,
                        'message' => 'Invalid credentials'
                    ]
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Could not create token'
                ]
            ], 500);
        }

        $user = Auth::user();

        // Update last login timestamp
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->load(['team', 'department']),
                'token' => $token,
                'expires_at' => now()->addMinutes(config('jwt.ttl'))->toISOString()
            ]
        ]);
    }

    /**
     * Register a User.
     */
    public function register(Request $request): JsonResponse
    {
        // Respect env flag to disable open registration in non-demo environments
        $allowRegistration = filter_var(env('ALLOW_REGISTRATION', false), FILTER_VALIDATE_BOOLEAN);
        if (!$allowRegistration) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Registration is disabled'
                ]
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'team_id' => 'nullable|exists:teams,id',
            'department_id' => 'nullable|exists:departments,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'details' => $validator->errors()->all()
                ]
            ], 422);
        }

        // Always assign default role on registration; do not accept role from client
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'USER',
            'team_id' => $request->team_id,
            'department_id' => $request->department_id,
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'message' => 'User successfully registered',
            'data' => [
                'user' => $user->load(['team', 'department']),
                'token' => $token,
                'expires_at' => now()->addMinutes(config('jwt.ttl'))->toISOString()
            ]
        ], 201);
    }

    /**
     * Log the user out (Invalidate the token).
     */
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to logout, please try again'
                ]
            ], 500);
        }
    }

    /**
     * Refresh a token.
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            $user = Auth::user();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user->load(['team', 'department']),
                    'token' => $token,
                    'expires_at' => now()->addMinutes(config('jwt.ttl'))->toISOString()
                ]
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 401,
                    'message' => 'Token cannot be refreshed'
                ]
            ], 401);
        }
    }

    /**
     * Get the authenticated User.
     */
    public function me(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => $user->load(['team', 'department'])
        ]);
    }

    /**
     * Get user profile
     */
    public function profile(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => $user->load(['team.manager', 'department'])
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|between:2,100',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'language_preference' => 'nullable|in:en,ar',
            'timezone' => 'nullable|string|max:50',
            'date_format' => 'nullable|string|max:20',
            'currency' => 'nullable|string|size:3',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'details' => $validator->errors()->all()
                ]
            ], 422);
        }

        $data = $request->only([
            'name', 'first_name', 'last_name', 'phone', 'position',
            'language_preference', 'timezone', 'date_format', 'currency'
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $avatarPath;
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user->fresh(['team', 'department'])
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'details' => $validator->errors()->all()
                ]
            ], 422);
        }

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Current password is incorrect'
                ]
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }
}