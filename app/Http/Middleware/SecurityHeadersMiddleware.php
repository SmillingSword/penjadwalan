<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add security headers
        $this->addSecurityHeaders($response, $request);

        return $response;
    }

    /**
     * Add comprehensive security headers to the response.
     */
    private function addSecurityHeaders(Response $response, Request $request): void
    {
        $headers = [
            // Prevent clickjacking attacks
            'X-Frame-Options' => 'DENY',
            
            // Prevent MIME type sniffing
            'X-Content-Type-Options' => 'nosniff',
            
            // Enable XSS protection
            'X-XSS-Protection' => '1; mode=block',
            
            // Referrer policy
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            
            // Permissions policy (formerly Feature Policy)
            'Permissions-Policy' => $this->getPermissionsPolicy(),
            
            // Strict Transport Security (HTTPS only)
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            
            // Content Security Policy
            'Content-Security-Policy' => $this->getContentSecurityPolicy($request),
            
            // Cross-Origin policies
            'Cross-Origin-Embedder-Policy' => 'require-corp',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            
            // Cache control for sensitive pages
            'Cache-Control' => $this->getCacheControl($request),
            
            // Server information hiding
            'Server' => 'Calendar-API',
            
            // Custom security headers
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        // Add headers to response
        foreach ($headers as $key => $value) {
            if ($value !== null) {
                $response->headers->set($key, $value);
            }
        }

        // Remove potentially sensitive headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
    }

    /**
     * Get Content Security Policy based on request type.
     */
    private function getContentSecurityPolicy(Request $request): string
    {
        $isApiRequest = $request->is('api/*');
        
        if ($isApiRequest) {
            // Strict CSP for API endpoints
            return "default-src 'none'; " .
                   "script-src 'none'; " .
                   "style-src 'none'; " .
                   "img-src 'none'; " .
                   "connect-src 'self'; " .
                   "font-src 'none'; " .
                   "object-src 'none'; " .
                   "media-src 'none'; " .
                   "frame-src 'none'; " .
                   "base-uri 'none'; " .
                   "form-action 'none';";
        }

        // CSP for web pages
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "img-src 'self' data: https: blob:",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "connect-src 'self' https: wss: ws:",
            "media-src 'self' blob:",
            "object-src 'none'",
            "frame-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "upgrade-insecure-requests"
        ];

        return implode('; ', $csp) . ';';
    }

    /**
     * Get Permissions Policy header.
     */
    private function getPermissionsPolicy(): string
    {
        $policies = [
            'accelerometer=()',
            'ambient-light-sensor=()',
            'autoplay=()',
            'battery=()',
            'camera=()',
            'cross-origin-isolated=()',
            'display-capture=()',
            'document-domain=()',
            'encrypted-media=()',
            'execution-while-not-rendered=()',
            'execution-while-out-of-viewport=()',
            'fullscreen=(self)',
            'geolocation=()',
            'gyroscope=()',
            'keyboard-map=()',
            'magnetometer=()',
            'microphone=()',
            'midi=()',
            'navigation-override=()',
            'payment=()',
            'picture-in-picture=()',
            'publickey-credentials-get=()',
            'screen-wake-lock=()',
            'sync-xhr=()',
            'usb=()',
            'web-share=()',
            'xr-spatial-tracking=()',
        ];

        return implode(', ', $policies);
    }

    /**
     * Get cache control header based on request.
     */
    private function getCacheControl(Request $request): string
    {
        $isApiRequest = $request->is('api/*');
        $isAuthRequest = $request->is('login') || $request->is('register') || $request->is('password/*');
        $isPrivateData = $request->is('dashboard') || $request->is('calendars/*') || $request->is('events/*');

        if ($isApiRequest || $isAuthRequest || $isPrivateData) {
            // No caching for sensitive endpoints
            return 'no-cache, no-store, must-revalidate, private, max-age=0';
        }

        // Allow caching for public assets
        return 'public, max-age=3600';
    }

    /**
     * Check if request is from a trusted source.
     */
    private function isTrustedSource(Request $request): bool
    {
        $trustedIps = config('security.trusted_ips', []);
        $trustedDomains = config('security.trusted_domains', []);
        
        // Check IP address
        if (!empty($trustedIps) && !in_array($request->ip(), $trustedIps)) {
            return false;
        }

        // Check referrer domain
        $referrer = $request->header('Referer');
        if ($referrer && !empty($trustedDomains)) {
            $referrerDomain = parse_url($referrer, PHP_URL_HOST);
            if (!in_array($referrerDomain, $trustedDomains)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add additional security headers for high-risk endpoints.
     */
    private function addHighSecurityHeaders(Response $response): void
    {
        $additionalHeaders = [
            'X-Download-Options' => 'noopen',
            'X-DNS-Prefetch-Control' => 'off',
            'Expect-CT' => 'max-age=86400, enforce',
            'X-Request-ID' => uniqid('req_', true),
        ];

        foreach ($additionalHeaders as $key => $value) {
            $response->headers->set($key, $value);
        }
    }

    /**
     * Log security violations.
     */
    private function logSecurityViolation(Request $request, string $violation): void
    {
        $auditService = app(\App\Services\AuditLogService::class);
        
        $auditService->logSecurityEvent('security_violation', [
            'violation_type' => $violation,
            'request_url' => $request->fullUrl(),
            'request_method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'threat_level' => 'medium',
            'blocked' => false,
        ]);
    }

    /**
     * Validate request headers for security threats.
     */
    private function validateRequestHeaders(Request $request): bool
    {
        $suspiciousPatterns = [
            'user-agent' => [
                '/bot/i',
                '/crawler/i',
                '/scanner/i',
                '/hack/i',
                '/exploit/i',
            ],
            'x-forwarded-for' => [
                '/tor-exit/i',
                '/proxy/i',
            ],
        ];

        foreach ($suspiciousPatterns as $header => $patterns) {
            $headerValue = $request->header($header);
            if ($headerValue) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $headerValue)) {
                        $this->logSecurityViolation($request, "suspicious_header_{$header}");
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Check for common attack patterns in request.
     */
    private function detectAttackPatterns(Request $request): bool
    {
        $attackPatterns = [
            // SQL Injection patterns
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            
            // XSS patterns
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            
            // Path traversal
            '/\.\.\//i',
            '/\.\.\\/i',
            
            // Command injection
            '/;\s*(cat|ls|pwd|whoami|id)/i',
            '/\|\s*(cat|ls|pwd|whoami|id)/i',
        ];

        $requestData = array_merge(
            $request->query(),
            $request->request->all(),
            [$request->getPathInfo()]
        );

        foreach ($requestData as $value) {
            if (is_string($value)) {
                foreach ($attackPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->logSecurityViolation($request, 'attack_pattern_detected');
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
