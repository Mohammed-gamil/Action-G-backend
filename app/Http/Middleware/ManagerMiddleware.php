<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ManagerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 401,
                    'message' => 'Unauthenticated'
                ]
            ], 401);
        }

        $user = Auth::user();

    $managerRoles = ['DIRECT_MANAGER', 'FINAL_MANAGER', 'ADMIN'];

        if (!in_array($user->role, $managerRoles)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Access denied. Manager privileges required.'
                ]
            ], 403);
        }

        return $next($request);
    }
}
