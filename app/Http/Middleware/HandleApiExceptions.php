<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * PUNKTET BBS Exception Handler Middleware
 * 
 * Sentral feilhåndtering for API-endepunkter
 * Sikrer konsistente feilmeldinger og logging
 */
class HandleApiExceptions
{
    /**
     * Feilkoder tilpasset BBS-stil
     */
    protected array $errorMessages = [
        400 => 'DÅRLIG FORESPØRSEL - Systemet forstår ikke kommandoen',
        401 => 'IKKE AUTORISERT - Logg inn for å fortsette',
        403 => 'TILGANG NEKTET - Du har ikke rettigheter til denne funksjonen',
        404 => 'IKKE FUNNET - Den forespurte ressursen eksisterer ikke',
        405 => 'METODE IKKE TILLATT - Denne operasjonen støttes ikke',
        408 => 'TIDSAVBRUDD - Forespørselen tok for lang tid',
        409 => 'KONFLIKT - Ressursen er allerede i bruk eller modifisert',
        422 => 'VALIDERINGSFEIL - Sjekk input-data',
        429 => 'FOR MANGE FORESPØRSLER - Vennligst vent litt',
        500 => 'SYSTEMFEIL - Noe gikk galt på serveren',
        502 => 'GATEWAY FEIL - Problemer med ekstern tjeneste',
        503 => 'TJENESTE UTILGJENGELIG - Systemet er midlertidig nede',
        504 => 'GATEWAY TIDSAVBRUDD - Ekstern tjeneste svarer ikke',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
            
            // Håndter HTTP-feil fra response
            if ($response->getStatusCode() >= 400) {
                return $this->formatErrorResponse($request, $response);
            }
            
            return $response;
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->handleValidationException($e, $request);
            
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return $this->handleAuthenticationException($e, $request);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->handleAuthorizationException($e, $request);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->handleModelNotFoundException($e, $request);
            
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->handleDatabaseException($e, $request);
            
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->handleHttpException($e, $request);
            
        } catch (\Exception $e) {
            return $this->handleGenericException($e, $request);
        }
    }

    /**
     * Formater feilresponse for HTTP-feil
     */
    protected function formatErrorResponse(Request $request, Response $response): Response
    {
        $statusCode = $response->getStatusCode();
        
        // Bare modifiser JSON-responser
        if (!$request->expectsJson() && !$request->is('api/*')) {
            return $response;
        }
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $statusCode,
                'message' => $this->errorMessages[$statusCode] ?? 'Ukjent feil',
                'timestamp' => now()->toIso8601String(),
            ],
        ], $statusCode);
    }

    /**
     * Håndter valideringsfeil
     */
    protected function handleValidationException(\Illuminate\Validation\ValidationException $e, Request $request): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 422,
                'message' => $this->errorMessages[422],
                'details' => $e->errors(),
                'timestamp' => now()->toIso8601String(),
            ],
        ], 422);
    }

    /**
     * Håndter autentiseringsfeil
     */
    protected function handleAuthenticationException(\Illuminate\Auth\AuthenticationException $e, Request $request): Response
    {
        Log::warning('Authentication failed', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'user_agent' => $request->userAgent(),
        ]);
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 401,
                'message' => $this->errorMessages[401],
                'timestamp' => now()->toIso8601String(),
            ],
        ], 401);
    }

    /**
     * Håndter autorisasjonsfeil
     */
    protected function handleAuthorizationException(\Illuminate\Auth\Access\AuthorizationException $e, Request $request): Response
    {
        Log::warning('Authorization denied', [
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 403,
                'message' => $this->errorMessages[403],
                'timestamp' => now()->toIso8601String(),
            ],
        ], 403);
    }

    /**
     * Håndter "ikke funnet" feil
     */
    protected function handleModelNotFoundException(\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request): Response
    {
        $model = class_basename($e->getModel());
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 404,
                'message' => $this->errorMessages[404],
                'resource' => $model,
                'timestamp' => now()->toIso8601String(),
            ],
        ], 404);
    }

    /**
     * Håndter database-feil
     */
    protected function handleDatabaseException(\Illuminate\Database\QueryException $e, Request $request): Response
    {
        Log::error('Database error', [
            'error' => $e->getMessage(),
            'sql' => $e->getSql() ?? 'N/A',
            'path' => $request->path(),
            'user_id' => auth()->id(),
        ]);
        
        // Sjekk for spesifikke feilkoder
        $errorCode = $e->getCode();
        
        // Duplikat nøkkel
        if ($errorCode == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 409,
                    'message' => 'Ressursen eksisterer allerede',
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 409);
        }
        
        // Generell database-feil
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 500,
                'message' => 'Database-feil oppstod',
                'timestamp' => now()->toIso8601String(),
            ],
        ], 500);
    }

    /**
     * Håndter HTTP-exceptions
     */
    protected function handleHttpException(\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request): Response
    {
        $statusCode = $e->getStatusCode();
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $statusCode,
                'message' => $e->getMessage() ?: ($this->errorMessages[$statusCode] ?? 'HTTP-feil'),
                'timestamp' => now()->toIso8601String(),
            ],
        ], $statusCode);
    }

    /**
     * Håndter alle andre exceptions
     */
    protected function handleGenericException(\Exception $e, Request $request): Response
    {
        // Logg alltid unhandled exceptions
        Log::error('Unhandled exception', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'path' => $request->path(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
        ]);
        
        // I produksjon, vis ikke detaljert feilmelding
        $message = config('app.debug') 
            ? $e->getMessage() 
            : $this->errorMessages[500];
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 500,
                'message' => $message,
                'timestamp' => now()->toIso8601String(),
                // Inkluder trace kun i debug-modus
                ...(config('app.debug') ? [
                    'debug' => [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ],
                ] : []),
            ],
        ], 500);
    }
}
