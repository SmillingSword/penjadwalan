<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $limiter = 'api'): Response
    {
        $key = $this->resolveRequestSignature($request, $limiter);
        
        if (RateLimiter::tooManyAttempts($key, $this->getMaxAttempts($limiter))) {
            return $this->buildResponse($key, $this->getMaxAttempts($limiter));
        }

        RateLimiter::hit($key, $this->getDecayMinutes($limiter) * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $this->getMaxAttempts($limiter),
            $this->calculateRemainingAttempts($key, $this->getMaxAttempts($limiter))
        );
    }

    /**
     * Resolve the request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request, string $limiter): string
    {
        $user = Auth::user();
        $organizationId = $request->get('current_organization_id');
        
        switch ($limiter) {
            case 'api':
                // General API rate limiting per user
                return $user ? "api:user:{$user->id}" : "api:ip:{$request->ip()}";
                
            case 'scheduling':
                // Stricter limits for scheduling endpoints
                return $user ? "scheduling:user:{$user->id}" : "scheduling:ip:{$request->ip()}";
                
            case 'ics_import':
                // Very strict limits for ICS import (resource intensive)
                return $user ? "ics_import:user:{$user->id}" : "ics_import:ip:{$request->ip()}";
                
            case 'search':
                // Moderate limits for search endpoints
                return $user ? "search:user:{$user->id}" : "search:ip:{$request->ip()}";
                
            case 'invitations':
                // Limits for invitation sending
                return $user ? "invitations:user:{$user->id}" : "invitations:ip:{$request->ip()}";
                
            case 'organization':
                // Per-organization limits for shared resources
                return $organizationId ? "org:{$organizationId}" : "org:unknown:{$request->ip()}";
                
            default:
                return $user ? "default:user:{$user->id}" : "default:ip:{$request->ip()}";
        }
    }

    /**
     * Get the maximum number of attempts for the given limiter.
     */
    protected function getMaxAttempts(string $limiter): int
    {
        return match ($limiter) {
            'api' => 1000,           // 1000 requests per hour for general API
            'scheduling' => 200,      // 200 requests per hour for scheduling
            'ics_import' => 10,       // 10 imports per hour (resource intensive)
            'search' => 500,          // 500 searches per hour
            'invitations' => 100,     // 100 invitations per hour
            'organization' => 5000,   // 5000 requests per hour per organization
            default => 60,            // Default: 60 requests per hour
        };
    }

    /**
     * Get the decay time in minutes for the given limiter.
     */
    protected function getDecayMinutes(string $limiter): int
    {
        return match ($limiter) {
            'api' => 60,              // 1 hour window
            'scheduling' => 60,       // 1 hour window
            'ics_import' => 60,       // 1 hour window
            'search' => 60,           // 1 hour window
            'invitations' => 60,      // 1 hour window
            'organization' => 60,     // 1 hour window
            default => 60,            // Default: 1 hour window
        };
    }

    /**
     * Calculate the number of remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return RateLimiter::retriesLeft($key, $maxAttempts);
    }

    /**
     * Add rate limit headers to the response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remainingAttempts),
        ]);

        return $response;
    }

    /**
     * Build the rate limit exceeded response.
     */
    protected function buildResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = RateLimiter::availableIn($key);
        
        return response()->json([
            'message' => 'Too Many Requests',
            'error' => 'Rate limit exceeded',
            'retry_after' => $retryAfter,
            'limit' => $maxAttempts,
        ], 429, [
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Get rate limit information for a specific key.
     */
    public static function getRateLimitInfo(string $key, int $maxAttempts): array
    {
        return [
            'limit' => $maxAttempts,
            'remaining' => RateLimiter::retriesLeft($key, $maxAttempts),
            'reset_at' => now()->addSeconds(RateLimiter::availableIn($key))->timestamp,
            'retry_after' => RateLimiter::availableIn($key),
        ];
    }

    /**
     * Clear rate limit for a specific key (admin function).
     */
    public static function clearRateLimit(string $key): bool
    {
        return RateLimiter::clear($key);
    }

    /**
     * Check if rate limit is exceeded without incrementing.
     */
    public static function isRateLimited(string $key, int $maxAttempts): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }
}
