<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class HealthController extends Controller
{
    /**
     * Basic health check endpoint.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
        ]);
    }

    /**
     * Comprehensive health check with all system components.
     */
    public function detailed(): JsonResponse
    {
        $startTime = microtime(true);
        
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'memory' => $this->checkMemory(),
            'disk_space' => $this->checkDiskSpace(),
            'external_services' => $this->checkExternalServices(),
        ];

        $overallStatus = $this->determineOverallStatus($checks);
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'response_time_ms' => $responseTime,
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'checks' => $checks,
            'system_info' => $this->getSystemInfo(),
        ], $overallStatus === 'healthy' ? 200 : 503);
    }

    /**
     * Check database connectivity and performance.
     */
    private function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            
            // Test basic connectivity
            DB::connection()->getPdo();
            
            // Test a simple query
            $result = DB::select('SELECT 1 as test');
            
            // Test write capability
            DB::table('audit_logs')->where('id', 'health-check-test')->delete();
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Get database statistics
            $stats = $this->getDatabaseStats();
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'connection' => 'active',
                'statistics' => $stats,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'connection' => 'failed',
            ];
        }
    }

    /**
     * Check cache system.
     */
    private function checkCache(): array
    {
        try {
            $startTime = microtime(true);
            $testKey = 'health_check_' . time();
            $testValue = 'test_value_' . uniqid();
            
            // Test write
            Cache::put($testKey, $testValue, 60);
            
            // Test read
            $retrieved = Cache::get($testKey);
            
            // Test delete
            Cache::forget($testKey);
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $isWorking = $retrieved === $testValue;
            
            return [
                'status' => $isWorking ? 'healthy' : 'unhealthy',
                'response_time_ms' => $responseTime,
                'driver' => config('cache.default'),
                'operations' => [
                    'write' => 'success',
                    'read' => $isWorking ? 'success' : 'failed',
                    'delete' => 'success',
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'driver' => config('cache.default'),
            ];
        }
    }

    /**
     * Check queue system.
     */
    private function checkQueue(): array
    {
        try {
            $queueDriver = config('queue.default');
            $queueSize = Queue::size();
            
            // Get failed jobs count
            $failedJobs = DB::table('failed_jobs')->count();
            
            return [
                'status' => 'healthy',
                'driver' => $queueDriver,
                'pending_jobs' => $queueSize,
                'failed_jobs' => $failedJobs,
                'workers_active' => $this->getActiveWorkers(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'driver' => config('queue.default'),
            ];
        }
    }

    /**
     * Check storage system.
     */
    private function checkStorage(): array
    {
        try {
            $testFile = 'health_check_' . time() . '.txt';
            $testContent = 'Health check test content';
            
            // Test write
            Storage::put($testFile, $testContent);
            
            // Test read
            $retrieved = Storage::get($testFile);
            
            // Test delete
            Storage::delete($testFile);
            
            $isWorking = $retrieved === $testContent;
            
            return [
                'status' => $isWorking ? 'healthy' : 'unhealthy',
                'driver' => config('filesystems.default'),
                'operations' => [
                    'write' => 'success',
                    'read' => $isWorking ? 'success' : 'failed',
                    'delete' => 'success',
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'driver' => config('filesystems.default'),
            ];
        }
    }

    /**
     * Check memory usage.
     */
    private function checkMemory(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        $usagePercentage = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;
        
        $status = 'healthy';
        if ($usagePercentage > 90) {
            $status = 'critical';
        } elseif ($usagePercentage > 75) {
            $status = 'warning';
        }
        
        return [
            'status' => $status,
            'current_usage_bytes' => $memoryUsage,
            'current_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'peak_usage_bytes' => $memoryPeak,
            'peak_usage_mb' => round($memoryPeak / 1024 / 1024, 2),
            'limit_bytes' => $memoryLimit,
            'limit_mb' => round($memoryLimit / 1024 / 1024, 2),
            'usage_percentage' => round($usagePercentage, 2),
        ];
    }

    /**
     * Check disk space.
     */
    private function checkDiskSpace(): array
    {
        $path = storage_path();
        $totalBytes = disk_total_space($path);
        $freeBytes = disk_free_space($path);
        $usedBytes = $totalBytes - $freeBytes;
        
        $usagePercentage = ($usedBytes / $totalBytes) * 100;
        
        $status = 'healthy';
        if ($usagePercentage > 95) {
            $status = 'critical';
        } elseif ($usagePercentage > 85) {
            $status = 'warning';
        }
        
        return [
            'status' => $status,
            'total_bytes' => $totalBytes,
            'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 2),
            'free_bytes' => $freeBytes,
            'free_gb' => round($freeBytes / 1024 / 1024 / 1024, 2),
            'used_bytes' => $usedBytes,
            'used_gb' => round($usedBytes / 1024 / 1024 / 1024, 2),
            'usage_percentage' => round($usagePercentage, 2),
            'path' => $path,
        ];
    }

    /**
     * Check external services.
     */
    private function checkExternalServices(): array
    {
        $services = [];
        
        // Check email service
        $services['email'] = $this->checkEmailService();
        
        // Check any configured external APIs
        if (config('services.google.client_id')) {
            $services['google_api'] = $this->checkGoogleApi();
        }
        
        return $services;
    }

    /**
     * Check email service.
     */
    private function checkEmailService(): array
    {
        try {
            $mailer = config('mail.default');
            
            // Basic configuration check
            $config = config("mail.mailers.{$mailer}");
            
            return [
                'status' => 'healthy',
                'driver' => $mailer,
                'host' => $config['host'] ?? 'N/A',
                'port' => $config['port'] ?? 'N/A',
                'encryption' => $config['encryption'] ?? 'none',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Google API connectivity.
     */
    private function checkGoogleApi(): array
    {
        try {
            // This is a basic check - in production you might want to make an actual API call
            $clientId = config('services.google.client_id');
            $clientSecret = config('services.google.client_secret');
            
            $status = ($clientId && $clientSecret) ? 'configured' : 'not_configured';
            
            return [
                'status' => $status,
                'client_id_configured' => !empty($clientId),
                'client_secret_configured' => !empty($clientSecret),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get database statistics.
     */
    private function getDatabaseStats(): array
    {
        try {
            return [
                'users_count' => DB::table('users')->count(),
                'organizations_count' => DB::table('organizations')->count(),
                'calendars_count' => DB::table('calendars')->count(),
                'events_count' => DB::table('events')->count(),
                'audit_logs_count' => DB::table('audit_logs')->count(),
                'connection_name' => DB::connection()->getName(),
                'driver_name' => DB::connection()->getDriverName(),
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to retrieve database statistics',
            ];
        }
    }

    /**
     * Get active queue workers count.
     */
    private function getActiveWorkers(): int
    {
        try {
            // This is a simplified check - in production you might use Redis or other methods
            return 1; // Placeholder
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get system information.
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_time' => now()->toISOString(),
            'timezone' => config('app.timezone'),
            'debug_mode' => config('app.debug'),
            'maintenance_mode' => app()->isDownForMaintenance(),
            'uptime' => $this->getUptime(),
        ];
    }

    /**
     * Get application uptime.
     */
    private function getUptime(): array
    {
        $uptimeFile = storage_path('framework/uptime');
        
        if (!file_exists($uptimeFile)) {
            file_put_contents($uptimeFile, time());
        }
        
        $startTime = (int) file_get_contents($uptimeFile);
        $uptime = time() - $startTime;
        
        return [
            'seconds' => $uptime,
            'human' => $this->formatUptime($uptime),
            'started_at' => Carbon::createFromTimestamp($startTime)->toISOString(),
        ];
    }

    /**
     * Format uptime in human readable format.
     */
    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if ($seconds > 0 || empty($parts)) $parts[] = "{$seconds}s";
        
        return implode(' ', $parts);
    }

    /**
     * Parse memory limit string to bytes.
     */
    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    /**
     * Determine overall system status.
     */
    private function determineOverallStatus(array $checks): string
    {
        $criticalServices = ['database', 'cache'];
        $hasUnhealthyCore = false;
        $hasWarnings = false;
        
        foreach ($checks as $service => $check) {
            $status = $check['status'] ?? 'unknown';
            
            if (in_array($service, $criticalServices) && $status !== 'healthy') {
                $hasUnhealthyCore = true;
            }
            
            if (in_array($status, ['unhealthy', 'critical', 'warning'])) {
                $hasWarnings = true;
            }
        }
        
        if ($hasUnhealthyCore) {
            return 'unhealthy';
        }
        
        if ($hasWarnings) {
            return 'degraded';
        }
        
        return 'healthy';
    }

    /**
     * Readiness check for Kubernetes/Docker.
     */
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
        ];
        
        $isReady = true;
        foreach ($checks as $check) {
            if ($check['status'] !== 'healthy') {
                $isReady = false;
                break;
            }
        }
        
        return response()->json([
            'ready' => $isReady,
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
        ], $isReady ? 200 : 503);
    }

    /**
     * Liveness check for Kubernetes/Docker.
     */
    public function live(): JsonResponse
    {
        // Basic liveness check - if we can respond, we're alive
        return response()->json([
            'alive' => true,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
