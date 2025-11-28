<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class LoginThrottle
{
    /**
     * Maximum login attempts before lockout
     */
    protected int $maxAttempts = 5;

    /**
     * Lockout duration in seconds (15 minutes)
     */
    protected int $decaySeconds = 900;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            
            return response()->json([
                'success' => false,
                'message' => __('auth.throttle', ['seconds' => $seconds]),
                'retry_after' => $seconds,
            ], 429);
        }

        $response = $next($request);

        // If login failed (401), increment the counter
        if ($response->getStatusCode() === 401) {
            RateLimiter::hit($key, $this->decaySeconds);
        } else {
            // Successful login - clear the attempts
            RateLimiter::clear($key);
        }

        return $response;
    }

    /**
     * Get the throttle key for the given request.
     */
    protected function throttleKey(Request $request): string
    {
        $login = strtolower($request->input('login', $request->input('email', '')));
        
        return 'login_throttle:' . sha1($login . '|' . $request->ip());
    }
}
