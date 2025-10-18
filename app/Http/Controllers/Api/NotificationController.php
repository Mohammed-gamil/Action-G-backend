<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $perPage = (int) $request->get('per_page', 50);
        $unreadOnly = $request->boolean('unread_only', false);

        // Fetch from notifications table (default Laravel notifications schema)
        $query = DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey())
            ->when($unreadOnly, function ($q) {
                return $q->whereNull('read_at');
            })
            ->orderByDesc('created_at');

        $notifications = $query->paginate($perPage);

        // Transform to match frontend format
        $items = collect($notifications->items())->map(function ($notification) {
            $data = json_decode($notification->data, true);
            
            return [
                'id' => $notification->id,
                'title' => $this->getNotificationTitle($notification->type),
                'message' => $data['message'] ?? '',
                'type' => $this->getNotificationType($notification->type),
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at,
                'related_request_id' => $data['request_id'] ?? null,
                'action_url' => $data['action_url'] ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'pagination' => [
                    'total' => $notifications->total(),
                    'page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'totalPages' => $notifications->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get notification title based on type
     */
    private function getNotificationTitle(string $type): string
    {
        return match($type) {
            'request_submitted' => 'New Request Submitted',
            'request_assigned' => 'Request Assigned',
            'request_approved' => 'Request Approved',
            'request_rejected' => 'Request Rejected',
            'request_pending_approval' => 'Pending Approval',
            'request_pending_payment' => 'Pending Payment',
            'quote_uploaded' => 'Quote Uploaded',
            'quote_selected' => 'Quote Selected',
            'funds_transferred' => 'Funds Transferred',
            'project_started' => 'Project Started',
            'project_done' => 'Project Completed',
            'payment_confirmed' => 'Payment Confirmed',
            'comment_added' => 'New Comment',
            default => 'Notification',
        };
    }

    /**
     * Get notification type (UI style) based on Laravel notification type
     */
    private function getNotificationType(string $type): string
    {
        return match($type) {
            'request_approved', 'funds_transferred', 'payment_confirmed' => 'success',
            'request_rejected' => 'error',
            'request_pending_approval', 'request_pending_payment', 'quote_uploaded' => 'warning',
            default => 'info',
        };
    }

    public function unreadCount(): JsonResponse
    {
        $user = Auth::user();

        $count = DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey())
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [ 'count' => $count ],
        ]);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $user = Auth::user();

        $updated = DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey())
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => (bool) $updated,
        ], $updated ? 200 : 404);
    }

    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();

        DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();

        $deleted = DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey())
            ->delete();

        return response()->json([
            'success' => (bool) $deleted,
        ], $deleted ? 200 : 404);
    }
}
