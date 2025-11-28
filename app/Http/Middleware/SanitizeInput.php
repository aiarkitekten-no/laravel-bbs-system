<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Fields that should not be sanitized (passwords, etc.)
     */
    protected array $except = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();
        
        $request->merge($this->sanitize($input));

        return $next($request);
    }

    /**
     * Recursively sanitize input data.
     */
    protected function sanitize(array $data, string $prefix = ''): array
    {
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (in_array($key, $this->except)) {
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitize($value, $fullKey);
            } elseif (is_string($value)) {
                $data[$key] = $this->sanitizeString($value);
            }
        }

        return $data;
    }

    /**
     * Sanitize a string value.
     * 
     * NOTE: We do NOT use htmlspecialchars here because:
     * 1. Blade templates already escape output with {{ }}
     * 2. Double-encoding breaks legitimate characters
     * 3. JSON API responses don't need HTML encoding
     * 
     * We only remove dangerous patterns that could cause XSS.
     */
    protected function sanitizeString(string $value): string
    {
        // Trim whitespace
        $value = trim($value);

        // Remove null bytes (can bypass security filters)
        $value = str_replace(chr(0), '', $value);

        // Remove potential JavaScript event handlers (onerror=, onclick=, etc.)
        $value = preg_replace('/\bon\w+\s*=/i', '', $value);

        // Remove javascript: protocol
        $value = preg_replace('/javascript\s*:/i', '', $value);

        // Remove vbscript: protocol (IE specific)
        $value = preg_replace('/vbscript\s*:/i', '', $value);

        // Remove data: protocol with dangerous MIME types
        $value = preg_replace('/data\s*:\s*(text\/html|application\/javascript)/i', 'data:blocked', $value);

        // Remove <script> tags entirely
        $value = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $value);

        // Remove style expressions (IE specific XSS)
        $value = preg_replace('/expression\s*\(/i', '', $value);

        return $value;
    }
}
