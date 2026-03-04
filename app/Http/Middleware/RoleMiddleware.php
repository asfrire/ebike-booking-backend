<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $role
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check if user has the required role
        switch ($role) {
            case 'admin':
                if (!$user->isAdmin()) {
                    return response()->json(['message' => 'Admin access required'], 403);
                }
                break;
            case 'rider':
                if (!$user->isRider()) {
                    return response()->json(['message' => 'Rider access required'], 403);
                }
                break;
            case 'customer':
                if (!$user->isCustomer()) {
                    return response()->json(['message' => 'Customer access required'], 403);
                }
                break;
            default:
                return response()->json(['message' => 'Invalid role'], 403);
        }

        return $next($request);
    }
}
