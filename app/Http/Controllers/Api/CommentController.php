<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Request;
use App\Services\NotificationService;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    public function index(string $requestId)
    {
        $user = Auth::user();
        $query = Request::with([]);
        if (ctype_digit($requestId)) {
            $query->where('id', $requestId);
        } else {
            $query->where('request_id', $requestId);
        }
        $req = $query->first();
        if (!$req) {
            return response()->json(['success' => false, 'error' => ['code' => 404, 'message' => 'Request not found']], 404);
        }
        // All authenticated users can view comments for any request.

        $comments = Comment::with('user')
            ->where('request_id', $req->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'author' => $c->user?->name,
                    'author_id' => $c->user_id,
                    'content' => $c->content,
                    'created_at' => $c->created_at,
                ];
            });

        return response()->json(['success' => true, 'data' => $comments]);
    }

    public function store(HttpRequest $httpRequest, string $requestId)
    {
        $user = Auth::user();
        $query = Request::query();
        if (ctype_digit($requestId)) $query->where('id', $requestId); else $query->where('request_id', $requestId);
        $req = $query->first();
        if (!$req) return response()->json(['success' => false, 'error' => ['code' => 404, 'message' => 'Request not found']], 404);

        // All authenticated users can post comments for any request.

        $validator = Validator::make($httpRequest->all(), [ 'content' => 'required|string|max:2000' ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => ['code' => 422, 'message' => 'Validation failed', 'details' => $validator->errors()->all()]], 422);
        }

        $comment = Comment::create([
            'request_id' => $req->id,
            'user_id' => $user->id,
            'content' => $httpRequest->get('content'),
        ]);

        // Send notifications
        NotificationService::commentAdded($req, $user, $httpRequest->get('content'));

        return response()->json(['success' => true, 'message' => 'Comment added', 'data' => $comment->load('user')], 201);
    }
}
