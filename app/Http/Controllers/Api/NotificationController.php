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

        $perPage = (int) $request->get('per_page', 20);
        $page = (int) $request->get('page', 1);

        // Fetch from notifications table (default Laravel notifications schema)
        $query = DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->getKey())
            ->orderByDesc('created_at');

        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                ],
            ],
        ]);
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
            'data' => [ 'unread' => $count ],
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
