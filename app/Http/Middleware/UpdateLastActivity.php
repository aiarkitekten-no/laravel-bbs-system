<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $user->updateLastActivity();

            // Also update node activity
            if ($user->currentNode) {
                $user->currentNode->update([
                    'last_activity_at' => now(),
                ]);
            }
        }

        return $next($request);
    }
}
