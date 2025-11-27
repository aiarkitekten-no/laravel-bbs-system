<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTimeRemaining
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->hasTimeRemaining()) {
            // Release the node
            if ($user->currentNode) {
                $user->currentNode->releaseUser();
            }

            return response()->json([
                'success' => false,
                'message' => __('auth.time_expired'),
                'time_remaining' => 0,
            ], 403);
        }

        return $next($request);
    }
}
