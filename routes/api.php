<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\CommentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [AuthController::class, 'login']);
    if (config('app.allow_registration')) {
        Route::post('register', [AuthController::class, 'register']);
    }
});

// Protected routes
Route::group(['middleware' => 'auth:api'], function () {

    // Auth routes
    Route::group(['prefix' => 'auth'], function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('profile', [AuthController::class, 'profile']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });

    // User routes
    Route::get('user', [AuthController::class, 'me']); // Alias for auth/me
    // List users by role (e.g., DIRECT_MANAGER) - accessible to any authenticated user
    Route::get('users/by-role', [UserController::class, 'listByRole']);
    Route::post('user/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::get('user/notification-preferences', [ProfileController::class, 'getNotificationPreferences']);
    Route::put('user/notification-preferences', [ProfileController::class, 'updateNotificationPreferences']);

    // Dashboard routes
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('dashboard/recent-activity', [DashboardController::class, 'recentActivity']);

    // Notification routes
    Route::group(['prefix' => 'notifications'], function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('read-all', [NotificationController::class, 'markAllAsRead']);
        Route::put('{id}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('{id}', [NotificationController::class, 'destroy']);
    });

    // Request routes
    Route::group(['prefix' => 'requests'], function () {
        Route::get('/', [RequestController::class, 'index']);
        Route::post('/', [RequestController::class, 'store']);
        Route::get('pending-approvals', [RequestController::class, 'pendingApprovals']);
        Route::get('user/{userId}', [RequestController::class, 'getUserRequests']);
        Route::get('{id}', [RequestController::class, 'show']);
        Route::post('{id}/quotes', [RequestController::class, 'uploadQuote']);
        // Comments
        Route::get('{id}/comments', [CommentController::class, 'index']);
        Route::post('{id}/comments', [CommentController::class, 'store']);
        // Inventory items for request
        Route::get('{id}/inventory', [RequestController::class, 'getInventoryItems']);
        Route::post('{id}/inventory', [RequestController::class, 'attachInventoryItems']);
        Route::put('{id}', [RequestController::class, 'update']);
        Route::delete('{id}', [RequestController::class, 'destroy']);
        Route::post('{id}/submit', [RequestController::class, 'submit']);
    });

    // Inventory routes
    Route::group(['prefix' => 'inventory'], function () {
        Route::get('/', [\App\Http\Controllers\Api\InventoryController::class, 'index']);
        Route::get('categories', [\App\Http\Controllers\Api\InventoryController::class, 'categories']);
        Route::get('{id}', [\App\Http\Controllers\Api\InventoryController::class, 'show']);
        Route::get('{id}/transactions', [\App\Http\Controllers\Api\InventoryController::class, 'transactions']);
        
        // Manager/Admin only routes
        Route::post('/', [\App\Http\Controllers\Api\InventoryController::class, 'store']);
        Route::put('{id}', [\App\Http\Controllers\Api\InventoryController::class, 'update']);
        Route::post('{id}/adjust', [\App\Http\Controllers\Api\InventoryController::class, 'adjustQuantity']);
        Route::delete('{id}', [\App\Http\Controllers\Api\InventoryController::class, 'destroy']);
    });

    // Approval routes
    Route::group(['prefix' => 'approvals'], function () {
        Route::post('{requestId}/approve', [ApprovalController::class, 'approve']);
        Route::post('{requestId}/reject', [ApprovalController::class, 'reject']);
        Route::post('{requestId}/transfer-funds', [ApprovalController::class, 'transferFunds']);
        Route::post('{requestId}/select-quote', [ApprovalController::class, 'selectQuote']);
        Route::get('{requestId}/history', [ApprovalController::class, 'history']);
        // Project-specific actions
        Route::post('{requestId}/mark-done', [ApprovalController::class, 'markProjectDone']);
        Route::post('{requestId}/confirm-paid', [ApprovalController::class, 'confirmClientPaid']);
    });

    // Admin routes
    Route::group(['middleware' => 'admin', 'prefix' => 'admin'], function () {
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{id}', [UserController::class, 'show']);
        Route::put('users/{id}', [UserController::class, 'update']);
        Route::delete('users/{id}', [UserController::class, 'destroy']);
        Route::post('users/{id}/toggle-status', [UserController::class, 'toggleStatus']);

        // Admin request management
        Route::delete('requests/{id}', [RequestController::class, 'adminDestroy']);

        Route::get('reports/overview', [DashboardController::class, 'adminOverview']);
        Route::get('reports/requests', [DashboardController::class, 'requestsReport']);
        Route::get('reports/users', [DashboardController::class, 'usersReport']);
    });

    // Reports routes (for direct managers and admins)
    Route::group(['middleware' => 'manager', 'prefix' => 'reports'], function () {
        Route::get('team-requests', [DashboardController::class, 'teamRequests']);
        Route::get('budget-utilization', [DashboardController::class, 'budgetUtilization']);
        Route::get('approval-times', [DashboardController::class, 'approvalTimes']);
    });
});

// Health check
Route::get('health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working',
        'timestamp' => now()->toISOString()
    ]);
});

// Fallback route for API
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'error' => [
            'code' => 404,
            'message' => 'API endpoint not found'
        ]
    ], 404);
});