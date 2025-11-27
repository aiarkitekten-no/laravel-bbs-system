<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class CheckUserLevel
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $minimumLevel
     */
    public function handle(Request $request, Closure $next, string $minimumLevel): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthenticated'),
            ], 401);
        }

        if (!$user->hasMinimumLevel($minimumLevel)) {
            return response()->json([
                'success' => false,
                'message' => __('auth.insufficient_level'),
                'required_level' => $minimumLevel,
                'your_level' => $user->level,
            ], 403);
        }

        return $next($request);
    }
}
