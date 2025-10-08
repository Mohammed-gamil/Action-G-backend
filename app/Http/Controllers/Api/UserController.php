<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct()
    {
        // Middleware is handled in routes
    }

    /**
     * Display a listing of users
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $role = $request->get('role');
        $status = $request->get('status');

        $query = User::with(['team', 'department'])
            ->when($search, function ($q, $search) {
                return $q->where(function ($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($role, function ($q, $role) {
                return $q->where('role', $role);
            })
            ->when($status, function ($q, $status) {
                return $q->where('status', $status);
            })
            ->orderBy('created_at', 'desc');

        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'pagination' => [
                    'page' => $users->currentPage(),
                    'limit' => $users->perPage(),
                    'total' => $users->total(),
                    'totalPages' => $users->lastPage()
                ]
            ]
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:USER,DIRECT_MANAGER,FINAL_MANAGER,ACCOUNTANT,ADMIN',
            'team_id' => 'nullable|exists:teams,id',
            'department_id' => 'nullable|exists:departments,id',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'language_preference' => 'nullable|in:en,ar',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
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

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'team_id' => $request->team_id,
            'department_id' => $request->department_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'position' => $request->position,
            'language_preference' => $request->get('language_preference', 'en'),
            'timezone' => $request->get('timezone', 'UTC'),
            'currency' => $request->get('currency', 'USD'),
            'status' => 'active'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user->load(['team', 'department'])
        ], 201);
    }

    /**
     * Display the specified user
     */
    public function show(string $id): JsonResponse
    {
        $user = User::with(['team', 'department', 'requests'])
            ->where('id', $id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'User not found'
                ]
            ], 404);
        }

        // Add user statistics
        $user->statistics = [
            'total_requests' => $user->requests->count(),
            'pending_requests' => $user->requests()->whereIn('state', [
                'DRAFT', 'SUBMITTED', 'DM_APPROVED'
            ])->count(),
            'approved_requests' => $user->requests()->where('state', 'FINAL_APPROVED')->count(),
            'rejected_requests' => $user->requests()->whereIn('state', [
                'DM_REJECTED', 'ACCT_REJECTED'
            ])->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'User not found'
                ]
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|between:2,100',
            'email' => 'sometimes|string|email|max:100|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:6',
            'role' => 'sometimes|in:USER,DIRECT_MANAGER,FINAL_MANAGER,ACCOUNTANT,ADMIN',
            'team_id' => 'nullable|exists:teams,id',
            'department_id' => 'nullable|exists:departments,id',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'language_preference' => 'nullable|in:en,ar',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            'status' => 'sometimes|in:active,inactive'
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

        $updateData = $request->only([
            'name', 'email', 'role', 'team_id', 'department_id',
            'first_name', 'last_name', 'phone', 'position',
            'language_preference', 'timezone', 'currency', 'status'
        ]);

        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user->fresh(['team', 'department'])
        ]);
    }

    /**
     * Remove the specified user
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'User not found'
                ]
            ], 404);
        }

        // Check if user has pending requests
        $pendingRequests = $user->requests()
            ->whereIn('state', ['DRAFT', 'SUBMITTED', 'DM_APPROVED'])
            ->count();

        if ($pendingRequests > 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Cannot delete user with pending requests'
                ]
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Toggle user status
     */
    public function toggleStatus(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'User not found'
                ]
            ], 404);
        }

        $newStatus = $user->status === 'active' ? 'inactive' : 'active';
        $user->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => "User status changed to {$newStatus}",
            'data' => $user
        ]);
    }

    /**
     * Lightweight list of users filtered by role for selection (e.g. DIRECT_MANAGER)
     * Accessible to any authenticated user.
     */
    public function listByRole(Request $request): JsonResponse
    {
        $role = $request->get('role');
        $perPage = min((int) $request->get('per_page', 50), 200);

        if (!$role) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'details' => ['The role parameter is required']
                ]
            ], 422);
        }

        $query = User::query()
            ->where('role', $role)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('name');

        $users = $query->paginate($perPage);

        // Return minimal fields for selection
        $data = collect($users->items())->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'email' => $u->email,
            ];
        })->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'page' => $users->currentPage(),
                    'limit' => $users->perPage(),
                    'total' => $users->total(),
                    'totalPages' => $users->lastPage()
                ]
            ]
        ]);
    }
}
