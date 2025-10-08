<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Request;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        // Middleware is handled in routes
    }

    /**
     * Get dashboard statistics
     */
    public function stats(): JsonResponse
    {
        $user = Auth::user();

        $stats = [];

        if ($user->role === 'USER') {
            // User dashboard stats (disjoint buckets)
            $stats = [
                'totalRequests' => Request::where('requester_id', $user->id)->count(),
                // Pending = awaiting approvals (not yet approved at any stage)
                'pendingRequests' => Request::where('requester_id', $user->id)
                    ->whereIn('state', ['DRAFT', 'SUBMITTED'])
                    ->count(),
                // Approved = ONLY final approved
                'approvedRequests' => Request::where('requester_id', $user->id)
                    ->where('state', 'FINAL_APPROVED')
                    ->count(),
                'rejectedRequests' => Request::where('requester_id', $user->id)
                    ->whereIn('state', ['DM_REJECTED', 'ACCT_REJECTED', 'FINAL_REJECTED'])
                    ->count(),
                'totalSpent' => Request::where('requester_id', $user->id)
                    ->where('state', 'FUNDS_TRANSFERRED')
                    ->sum('desired_cost'),
            ];
        } elseif ($user->canApproveRequests()) {
            // Approver dashboard stats
            $pendingCount = Request::forApprover($user->id, $user->role)->count();

            $stats = [
                'pendingApprovals' => $pendingCount,
                'totalApprovals' => DB::table('approvals')
                    ->where('approver_id', $user->id)
                    ->count(),
                'approvedThisMonth' => DB::table('approvals')
                    ->where('approver_id', $user->id)
                    ->where('decision', 'APPROVED')
                    ->whereMonth('decided_at', now()->month)
                    ->whereYear('decided_at', now()->year)
                    ->count(),
                'rejectedThisMonth' => DB::table('approvals')
                    ->where('approver_id', $user->id)
                    ->where('decision', 'REJECTED')
                    ->whereMonth('decided_at', now()->month)
                    ->whereYear('decided_at', now()->year)
                    ->count(),
            ];
        }

        if ($user->isAdmin()) {
            // Admin gets additional system-wide stats
            $stats = array_merge($stats, [
                'totalUsers' => User::count(),
                'activeUsers' => User::where('status', 'active')->count(),
                'totalSystemRequests' => Request::count(),
                'pendingSystemApprovals' => Request::whereIn('state', ['SUBMITTED'])->count(),
                'totalSystemValue' => Request::where('state', 'FUNDS_TRANSFERRED')->sum('desired_cost'),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get recent activity
     */
    public function recentActivity(): JsonResponse
    {
        $user = Auth::user();
        $limit = request()->get('limit', 10);

        $query = AuditLog::with('user')
            ->when(!$user->isAdmin(), function ($q) use ($user) {
                // Non-admin users see only their own activity and related requests
                return $q->where(function ($subQ) use ($user) {
                    $subQ->where('user_id', $user->id)
                         ->orWhere(function ($entityQ) use ($user) {
                             $entityQ->where('entity_type', 'Request')
                                     ->whereIn('entity_id', function ($requestQ) use ($user) {
                                         $requestQ->select('id')
                                                 ->from('requests')
                                                 ->where('requester_id', $user->id);
                                     });
                         });
                });
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        $activities = $query->get();

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    /**
     * Admin overview (Admin only)
     */
    public function adminOverview(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Access denied'
                ]
            ], 403);
        }

        $overview = [
            'users' => [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'byRole' => User::select('role', DB::raw('count(*) as count'))
                    ->groupBy('role')
                    ->get()
                    ->pluck('count', 'role')
            ],
            'requests' => [
                'total' => Request::count(),
                'byState' => Request::select('state', DB::raw('count(*) as count'))
                    ->groupBy('state')
                    ->get()
                    ->pluck('count', 'state'),
                'byType' => Request::select('type', DB::raw('count(*) as count'))
                    ->groupBy('type')
                    ->get()
                    ->pluck('count', 'type'),
                'totalValue' => Request::sum('desired_cost'),
                'transferredValue' => Request::where('state', 'FUNDS_TRANSFERRED')
                    ->sum('desired_cost')
            ],
            'monthlyStats' => Request::select(
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(desired_cost) as total_value')
                )
                ->whereYear('created_at', now()->year)
                ->groupBy('month')
                ->orderBy('month')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $overview
        ]);
    }

    /**
     * Team requests report (Managers only)
     */
    public function teamRequests(): JsonResponse
    {
        $user = Auth::user();

    if (!in_array($user->role, ['DIRECT_MANAGER', 'ADMIN'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Access denied'
                ]
            ], 403);
        }

        $query = Request::with(['requester'])
            ->when($user->role === 'DIRECT_MANAGER', function ($q) use ($user) {
                return $q->whereHas('requester', function ($userQ) use ($user) {
                    $userQ->where('team_id', $user->team_id);
                });
            });

        $requests = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $requests->items(),
            'meta' => [
                'pagination' => [
                    'page' => $requests->currentPage(),
                    'limit' => $requests->perPage(),
                    'total' => $requests->total(),
                    'totalPages' => $requests->lastPage()
                ]
            ]
        ]);
    }
}
