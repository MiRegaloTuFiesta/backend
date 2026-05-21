<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBlockedUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->is_currently_blocked) {
            // Delete current token so they are signed out
            if ($request->user()->currentAccessToken()) {
                $request->user()->currentAccessToken()->delete();
            }
            return response()->json([
                'message' => 'Tu cuenta ha sido bloqueada. Se ha cerrado tu sesión.'
            ], 403);
        }

        return $next($request);
    }
}
