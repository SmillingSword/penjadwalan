<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InputSanitizationService
{
    /**
     * Sanitize user input to prevent XSS and other attacks.
     */
    public function sanitize($input, array $options = []): mixed
    {
        if (is_array($input)) {
            return $this->sanitizeArray($input, $options);
        }

        if (is_string($input)) {
            return $this->sanitizeString($input, $options);
        }

        return $input;
    }

    /**
     * Sanitize an array of inputs.
     */
    private function sanitizeArray(array $input, array $options = []): array
    {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            $sanitizedKey = $this->sanitizeString($key, ['allow_html' => false]);
            $sanitized[$sanitizedKey] = $this->sanitize($value, $options);
        }

        return $sanitized;
    }

    /**
     * Sanitize a string input.
     */
    private function sanitizeString(string $input, array $options = []): string
    {
        $allowHtml = $options['allow_html'] ?? false;
        $allowMarkdown = $options['allow_markdown'] ?? false;
        $maxLength = $options['max_length'] ?? null;
        $stripTags = $options['strip_tags'] ?? !$allowHtml;
        $encodeHtml = $options['encode_html'] ?? !$allowHtml;

        // Trim whitespace
        $sanitized = trim($input);

        // Check for suspicious patterns
        if ($this->containsSuspiciousPatterns($sanitized)) {
            Log::warning('Suspicious input detected', [
                'input' => $sanitized,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
            
            // For security, we'll sanitize aggressively
            $sanitized = $this->aggressiveSanitize($sanitized);
        }

        // Handle HTML content
        if ($allowHtml) {
            $sanitized = $this->sanitizeHtml($sanitized);
        } elseif ($stripTags) {
            $sanitized = strip_tags($sanitized);
        }

        // Handle Markdown content
        if ($allowMarkdown) {
            $sanitized = $this->sanitizeMarkdown($sanitized);
        }

        // Encode HTML entities if needed
        if ($encodeHtml && !$allowHtml) {
            $sanitized = htmlspecialchars($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Remove null bytes
        $sanitized = str_replace("\0", '', $sanitized);

        // Remove control characters except newlines and tabs
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);

        // Normalize Unicode
        if (function_exists('normalizer_normalize')) {
            $sanitized = normalizer_normalize($sanitized, Normalizer::FORM_C);
        }

        // Truncate if max length specified
        if ($maxLength && strlen($sanitized) > $maxLength) {
            $sanitized = Str::limit($sanitized, $maxLength);
        }

        return $sanitized;
    }

    /**
     * Sanitize HTML content allowing only safe tags.
     */
    private function sanitizeHtml(string $input): string
    {
        $allowedTags = [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre'
        ];

        $allowedAttributes = [
            'class', 'id'
        ];

        // Use HTMLPurifier if available, otherwise basic sanitization
        if (class_exists('\HTMLPurifier')) {
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', implode(',', $allowedTags));
            $config->set('HTML.AllowedAttributes', implode(',', $allowedAttributes));
            $purifier = new \HTMLPurifier($config);
            return $purifier->purify($input);
        }

        // Basic HTML sanitization
        $input = strip_tags($input, '<' . implode('><', $allowedTags) . '>');
        
        // Remove dangerous attributes
        $input = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/', '', $input);
        $input = preg_replace('/\s*javascript\s*:\s*[^"\'>\s]*/', '', $input);
        $input = preg_replace('/\s*data\s*:\s*[^"\'>\s]*/', '', $input);

        return $input;
    }

    /**
     * Sanitize Markdown content.
     */
    private function sanitizeMarkdown(string $input): string
    {
        // Remove potentially dangerous markdown patterns
        $dangerousPatterns = [
            '/\[.*?\]\s*\(\s*javascript:/i',
            '/\[.*?\]\s*\(\s*data:/i',
            '/\[.*?\]\s*\(\s*vbscript:/i',
            '/!\[.*?\]\s*\(\s*javascript:/i',
            '/!\[.*?\]\s*\(\s*data:/i',
            '/!\[.*?\]\s*\(\s*vbscript:/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            $input = preg_replace($pattern, '[REMOVED]', $input);
        }

        // Sanitize HTML within markdown
        $input = preg_replace_callback(
            '/<[^>]+>/',
            function ($matches) {
                return $this->sanitizeHtml($matches[0]);
            },
            $input
        );

        return $input;
    }

    /**
     * Check if input contains suspicious patterns.
     */
    private function containsSuspiciousPatterns(string $input): bool
    {
        $suspiciousPatterns = [
            // XSS patterns
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript\s*:/i',
            '/on\w+\s*=\s*["\'][^"\']*["\']/',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/<object[^>]*>.*?<\/object>/is',
            '/<embed[^>]*>.*?<\/embed>/is',
            
            // SQL injection patterns
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/update\s+\w+\s+set/i',
            
            // Command injection patterns
            '/;\s*(cat|ls|pwd|whoami|id|rm|mv|cp)/i',
            '/\|\s*(cat|ls|pwd|whoami|id|rm|mv|cp)/i',
            '/`[^`]*`/',
            '/\$\([^)]*\)/',
            
            // Path traversal
            '/\.\.\//i',
            '/\.\.\\/i',
            '/\.\.%2f/i',
            '/\.\.%5c/i',
            
            // LDAP injection
            '/\(\|\(/i',
            '/\)\(\|/i',
            
            // XML injection
            '/<!ENTITY/i',
            '/<!DOCTYPE/i',
            
            // Server-side includes
            '/<!--\s*#\s*exec/i',
            '/<!--\s*#\s*include/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Aggressively sanitize suspicious input.
     */
    private function aggressiveSanitize(string $input): string
    {
        // Remove all HTML tags
        $input = strip_tags($input);
        
        // Remove all non-printable characters
        $input = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $input);
        
        // Remove suspicious characters
        $input = preg_replace('/[<>"\'\(\)\{\}\[\]\\\\\/\|`$;]/', '', $input);
        
        // Limit length
        $input = Str::limit($input, 1000);
        
        return $input;
    }

    /**
     * Sanitize email addresses.
     */
    public function sanitizeEmail(string $email): string
    {
        $email = trim(strtolower($email));
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        // Additional validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        // Check for suspicious patterns in email
        $suspiciousPatterns = [
            '/\+.*script/i',
            '/javascript/i',
            '/\<.*\>/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $email)) {
                throw new \InvalidArgumentException('Suspicious email format detected');
            }
        }

        return $email;
    }

    /**
     * Sanitize URLs.
     */
    public function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        
        // Check for dangerous protocols
        $dangerousProtocols = [
            'javascript:',
            'data:',
            'vbscript:',
            'file:',
            'ftp:',
        ];

        foreach ($dangerousProtocols as $protocol) {
            if (stripos($url, $protocol) === 0) {
                throw new \InvalidArgumentException('Dangerous URL protocol detected');
            }
        }

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid URL format');
        }

        return $url;
    }

    /**
     * Sanitize file names.
     */
    public function sanitizeFileName(string $fileName): string
    {
        // Remove path information
        $fileName = basename($fileName);
        
        // Remove dangerous characters
        $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
        
        // Prevent hidden files
        $fileName = ltrim($fileName, '.');
        
        // Ensure file has an extension
        if (!pathinfo($fileName, PATHINFO_EXTENSION)) {
            $fileName .= '.txt';
        }

        // Limit length
        $fileName = Str::limit($fileName, 255);

        return $fileName;
    }

    /**
     * Sanitize phone numbers.
     */
    public function sanitizePhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except + and spaces
        $phone = preg_replace('/[^0-9+\s-()]/', '', $phone);
        
        // Trim whitespace
        $phone = trim($phone);
        
        // Basic validation
        if (strlen($phone) < 7 || strlen($phone) > 20) {
            throw new \InvalidArgumentException('Invalid phone number length');
        }

        return $phone;
    }

    /**
     * Sanitize timezone strings.
     */
    public function sanitizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone);
        
        // Validate against PHP timezone list
        if (!in_array($timezone, timezone_identifiers_list())) {
            throw new \InvalidArgumentException('Invalid timezone');
        }

        return $timezone;
    }

    /**
     * Sanitize color hex codes.
     */
    public function sanitizeColorHex(string $color): string
    {
        $color = trim($color);
        
        // Remove # if present
        $color = ltrim($color, '#');
        
        // Validate hex format
        if (!preg_match('/^[a-fA-F0-9]{6}$/', $color)) {
            throw new \InvalidArgumentException('Invalid color hex format');
        }

        return '#' . strtoupper($color);
    }

    /**
     * Sanitize JSON input.
     */
    public function sanitizeJson(string $json): array
    {
        $decoded = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON format');
        }

        // Recursively sanitize the decoded array
        return $this->sanitizeArray($decoded);
    }

    /**
     * Get sanitization statistics.
     */
    public function getSanitizationStats(): array
    {
        return [
            'total_requests_processed' => cache()->get('sanitization.total_requests', 0),
            'suspicious_patterns_detected' => cache()->get('sanitization.suspicious_patterns', 0),
            'aggressive_sanitizations' => cache()->get('sanitization.aggressive_count', 0),
            'last_reset' => cache()->get('sanitization.last_reset', now()),
        ];
    }

    /**
     * Reset sanitization statistics.
     */
    public function resetStats(): void
    {
        cache()->forget('sanitization.total_requests');
        cache()->forget('sanitization.suspicious_patterns');
        cache()->forget('sanitization.aggressive_count');
        cache()->put('sanitization.last_reset', now(), now()->addDays(30));
    }
}
