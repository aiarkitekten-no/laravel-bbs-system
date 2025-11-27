<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;

class SetLocale
{
    /**
     * Available locales
     */
    protected array $availableLocales = ['en', 'no'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);
        App::setLocale($locale);

        // Add locale to response header
        $response = $next($request);
        
        if (method_exists($response, 'header')) {
            $response->header('Content-Language', $locale);
        }

        return $response;
    }

    /**
     * Determine the locale to use
     */
    protected function determineLocale(Request $request): string
    {
        // 1. Check header (for API requests)
        if ($request->hasHeader('Accept-Language')) {
            $headerLocale = substr($request->header('Accept-Language'), 0, 2);
            if (in_array($headerLocale, $this->availableLocales)) {
                return $headerLocale;
            }
        }

        // 2. Check query parameter
        if ($request->has('lang') && in_array($request->query('lang'), $this->availableLocales)) {
            return $request->query('lang');
        }

        // 3. Check authenticated user's preference
        if ($request->user() && in_array($request->user()->locale, $this->availableLocales)) {
            return $request->user()->locale;
        }

        // 4. Default to English
        return config('app.locale', 'en');
    }
}
