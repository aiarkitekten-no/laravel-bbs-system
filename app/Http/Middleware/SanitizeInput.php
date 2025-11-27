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
     */
    protected function sanitizeString(string $value): string
    {
        // Trim whitespace
        $value = trim($value);

        // Remove null bytes
        $value = str_replace(chr(0), '', $value);

        // Convert special characters to HTML entities
        // Note: We use ENT_QUOTES to handle both single and double quotes
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);

        // Remove potential JavaScript event handlers
        $value = preg_replace('/on\w+\s*=/i', '', $value);

        // Remove javascript: protocol
        $value = preg_replace('/javascript\s*:/i', '', $value);

        // Remove data: protocol (can be used for XSS)
        $value = preg_replace('/data\s*:/i', '', $value);

        return $value;
    }
}
