<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API requests, return null (will throw AuthenticationException)
        // This is handled by the exception handler to return JSON
        return null;
    }

    /**
     * Handle unauthenticated user for API
     */
    protected function unauthenticated($request, array $guards)
    {
        // Always throw exception for API - no redirects
        abort(response()->json([
            'success' => false,
            'message' => 'Unauthenticated. Please login.',
        ], 401));
    }
}
