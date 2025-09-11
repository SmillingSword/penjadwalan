<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MonitoringService
{
    private array $metrics = [];
    private array $traces = [];
    private string $requestId;

    public function __construct()
    {
        $this->requestId = uniqid('req_', true);
    }

    /**
     * Record a custom metric.
     */
    public function recordMetric(string $name, float $value, array $tags = []): void
    {
        $metric = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true),
            'request_id' => $this->requestId,
        ];

        $this->metrics[] = $metric;

        // Store in cache for aggregation
        $cacheKey = "metrics:{$name}:" . date('Y-m-d-H');
        $existingMetrics = Cache::get($cacheKey, []);
        $existingMetrics[] = $metric;
        Cache::put($cacheKey, $existingMetrics, now()->addHours(25));

        // Log critical metrics
        if ($this->isCriticalMetric($name, $value, $tags)) {
            Log::warning('Critical metric recorded', $metric);
        }
    }

    /**
     * Record response time metric.
     */
    public function recordResponseTime(string $endpoint, float $responseTime, int $statusCode = 200): void
    {
        $this->recordMetric('http_request_duration_ms', $responseTime, [
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'method' => request()->method(),
        ]);
    }

    /**
     * Record database query metrics.
     */
    public function recordDatabaseQuery(string $query, float $executionTime, string $connection = 'default'): void
    {
        $this->recordMetric('database_query_duration_ms', $executionTime, [
            'connection' => $connection,
            'query_type' => $this->getQueryType($query),
        ]);

        // Record slow query
        if ($executionTime > 1000) { // Slower than 1 second
            $this->recordSlowQuery($query, $executionTime, $connection);
        }
    }

    /**
     * Record cache hit/miss metrics.
     */
    public function recordCacheMetric(string $operation, string $key, bool $hit = true): void
    {
        $this->recordMetric('cache_operations_total', 1, [
            'operation' => $operation,
            'result' => $hit ? 'hit' : 'miss',
            'key_prefix' => $this->getCacheKeyPrefix($key),
        ]);
    }

    /**
     * Record queue job metrics.
     */
    public function recordQueueJob(string $jobClass, float $executionTime, bool $success = true): void
    {
        $this->recordMetric('queue_job_duration_ms', $executionTime, [
            'job_class' => $jobClass,
            'status' => $success ? 'success' : 'failed',
        ]);

        if (!$success) {
            $this->recordMetric('queue_job_failures_total', 1, [
                'job_class' => $jobClass,
            ]);
        }
    }

    /**
     * Record memory usage metrics.
     */
    public function recordMemoryUsage(): void
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);

        $this->recordMetric('memory_usage_bytes', $memoryUsage);
        $this->recordMetric('memory_peak_bytes', $memoryPeak);

        // Alert on high memory usage
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        if ($memoryLimit > 0 && $memoryUsage > ($memoryLimit * 0.8)) {
            Log::warning('High memory usage detected', [
                'current_usage' => $memoryUsage,
                'peak_usage' => $memoryPeak,
                'limit' => $memoryLimit,
                'usage_percentage' => ($memoryUsage / $memoryLimit) * 100,
            ]);
        }
    }

    /**
     * Start a trace span.
     */
    public function startTrace(string $operationName, array $tags = []): string
    {
        $traceId = uniqid('trace_', true);
        
        $trace = [
            'trace_id' => $traceId,
            'operation_name' => $operationName,
            'start_time' => microtime(true),
            'tags' => $tags,
            'request_id' => $this->requestId,
        ];

        $this->traces[$traceId] = $trace;

        return $traceId;
    }

    /**
     * Finish a trace span.
     */
    public function finishTrace(string $traceId, array $additionalTags = []): void
    {
        if (!isset($this->traces[$traceId])) {
            return;
        }

        $trace = &$this->traces[$traceId];
        $trace['end_time'] = microtime(true);
        $trace['duration_ms'] = ($trace['end_time'] - $trace['start_time']) * 1000;
        $trace['tags'] = array_merge($trace['tags'], $additionalTags);

        // Record trace as metric
        $this->recordMetric('trace_duration_ms', $trace['duration_ms'], [
            'operation' => $trace['operation_name'],
        ]);

        // Log slow operations
        if ($trace['duration_ms'] > 5000) { // Slower than 5 seconds
            Log::warning('Slow operation detected', $trace);
        }
    }

    /**
     * Record business metrics.
     */
    public function recordBusinessMetric(string $event, array $data = []): void
    {
        $metric = [
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'request_id' => $this->requestId,
        ];

        // Store business metrics separately
        $cacheKey = "business_metrics:" . date('Y-m-d');
        $existingMetrics = Cache::get($cacheKey, []);
        $existingMetrics[] = $metric;
        Cache::put($cacheKey, $existingMetrics, now()->addDays(8));

        Log::info('Business metric recorded', $metric);
    }

    /**
     * Record error metrics.
     */
    public function recordError(\Throwable $exception, array $context = []): void
    {
        $errorData = [
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context,
            'request_id' => $this->requestId,
            'timestamp' => now()->toISOString(),
        ];

        $this->recordMetric('errors_total', 1, [
            'exception_class' => get_class($exception),
            'severity' => $this->getErrorSeverity($exception),
        ]);

        Log::error('Application error recorded', $errorData);

        // Store critical errors for alerting
        if ($this->isCriticalError($exception)) {
            Cache::put("critical_error:{$this->requestId}", $errorData, now()->addHours(24));
        }
    }

    /**
     * Get aggregated metrics for a time period.
     */
    public function getMetrics(Carbon $startTime, Carbon $endTime, array $metricNames = []): array
    {
        $aggregatedMetrics = [];
        
        $current = $startTime->copy()->startOfHour();
        while ($current <= $endTime) {
            $cacheKey = "metrics:*:" . $current->format('Y-m-d-H');
            
            // This is a simplified version - in production you'd use a proper time-series database
            $hourlyMetrics = Cache::get($cacheKey, []);
            
            foreach ($hourlyMetrics as $metric) {
                if (empty($metricNames) || in_array($metric['name'], $metricNames)) {
                    $key = $metric['name'];
                    
                    if (!isset($aggregatedMetrics[$key])) {
                        $aggregatedMetrics[$key] = [
                            'name' => $key,
                            'values' => [],
                            'count' => 0,
                            'sum' => 0,
                            'min' => PHP_FLOAT_MAX,
                            'max' => PHP_FLOAT_MIN,
                        ];
                    }
                    
                    $aggregatedMetrics[$key]['values'][] = $metric['value'];
                    $aggregatedMetrics[$key]['count']++;
                    $aggregatedMetrics[$key]['sum'] += $metric['value'];
                    $aggregatedMetrics[$key]['min'] = min($aggregatedMetrics[$key]['min'], $metric['value']);
                    $aggregatedMetrics[$key]['max'] = max($aggregatedMetrics[$key]['max'], $metric['value']);
                }
            }
            
            $current->addHour();
        }

        // Calculate averages
        foreach ($aggregatedMetrics as &$metric) {
            $metric['average'] = $metric['count'] > 0 ? $metric['sum'] / $metric['count'] : 0;
            $metric['p95'] = $this->calculatePercentile($metric['values'], 95);
            $metric['p99'] = $this->calculatePercentile($metric['values'], 99);
        }

        return array_values($aggregatedMetrics);
    }

    /**
     * Get system health metrics.
     */
    public function getHealthMetrics(): array
    {
        return [
            'memory' => [
                'current_usage_bytes' => memory_get_usage(true),
                'peak_usage_bytes' => memory_get_peak_usage(true),
                'limit_bytes' => $this->parseMemoryLimit(ini_get('memory_limit')),
            ],
            'database' => $this->getDatabaseHealthMetrics(),
            'cache' => $this->getCacheHealthMetrics(),
            'queue' => $this->getQueueHealthMetrics(),
            'errors' => $this->getErrorMetrics(),
        ];
    }

    /**
     * Get performance insights.
     */
    public function getPerformanceInsights(Carbon $startTime, Carbon $endTime): array
    {
        $metrics = $this->getMetrics($startTime, $endTime);
        $insights = [];

        foreach ($metrics as $metric) {
            if ($metric['name'] === 'http_request_duration_ms') {
                if ($metric['average'] > 1000) {
                    $insights[] = [
                        'type' => 'performance',
                        'severity' => 'warning',
                        'message' => 'Average response time is high',
                        'details' => [
                            'average_ms' => $metric['average'],
                            'p95_ms' => $metric['p95'],
                            'p99_ms' => $metric['p99'],
                        ],
                    ];
                }
            }

            if ($metric['name'] === 'database_query_duration_ms') {
                if ($metric['p95'] > 500) {
                    $insights[] = [
                        'type' => 'database',
                        'severity' => 'warning',
                        'message' => 'Database queries are slow',
                        'details' => [
                            'p95_ms' => $metric['p95'],
                            'p99_ms' => $metric['p99'],
                        ],
                    ];
                }
            }
        }

        return $insights;
    }

    /**
     * Export metrics in Prometheus format.
     */
    public function exportPrometheusMetrics(): string
    {
        $output = [];
        
        foreach ($this->metrics as $metric) {
            $metricName = str_replace('.', '_', $metric['name']);
            $labels = [];
            
            foreach ($metric['tags'] as $key => $value) {
                $labels[] = $key . '="' . addslashes($value) . '"';
            }
            
            $labelString = empty($labels) ? '' : '{' . implode(',', $labels) . '}';
            $output[] = $metricName . $labelString . ' ' . $metric['value'] . ' ' . (int)($metric['timestamp'] * 1000);
        }

        return implode("\n", $output);
    }

    /**
     * Check if a metric is critical.
     */
    private function isCriticalMetric(string $name, float $value, array $tags): bool
    {
        $criticalThresholds = [
            'http_request_duration_ms' => 5000,
            'database_query_duration_ms' => 2000,
            'memory_usage_bytes' => 500 * 1024 * 1024, // 500MB
            'errors_total' => 1,
        ];

        return isset($criticalThresholds[$name]) && $value >= $criticalThresholds[$name];
    }

    /**
     * Record slow query.
     */
    private function recordSlowQuery(string $query, float $executionTime, string $connection): void
    {
        $slowQuery = [
            'query' => $query,
            'execution_time_ms' => $executionTime,
            'connection' => $connection,
            'timestamp' => now()->toISOString(),
            'request_id' => $this->requestId,
        ];

        Log::warning('Slow query detected', $slowQuery);
        
        // Store for analysis
        $cacheKey = "slow_queries:" . date('Y-m-d');
        $existingQueries = Cache::get($cacheKey, []);
        $existingQueries[] = $slowQuery;
        Cache::put($cacheKey, $existingQueries, now()->addDays(7));
    }

    /**
     * Get query type from SQL.
     */
    private function getQueryType(string $query): string
    {
        $query = trim(strtoupper($query));
        
        if (strpos($query, 'SELECT') === 0) return 'SELECT';
        if (strpos($query, 'INSERT') === 0) return 'INSERT';
        if (strpos($query, 'UPDATE') === 0) return 'UPDATE';
        if (strpos($query, 'DELETE') === 0) return 'DELETE';
        if (strpos($query, 'CREATE') === 0) return 'CREATE';
        if (strpos($query, 'ALTER') === 0) return 'ALTER';
        if (strpos($query, 'DROP') === 0) return 'DROP';
        
        return 'OTHER';
    }

    /**
     * Get cache key prefix.
     */
    private function getCacheKeyPrefix(string $key): string
    {
        $parts = explode(':', $key);
        return $parts[0] ?? 'unknown';
    }

    /**
     * Parse memory limit string.
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
     * Calculate percentile.
     */
    private function calculatePercentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0;
        }
        
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        
        if (floor($index) == $index) {
            return $values[$index];
        }
        
        $lower = $values[floor($index)];
        $upper = $values[ceil($index)];
        
        return $lower + ($upper - $lower) * ($index - floor($index));
    }

    /**
     * Get database health metrics.
     */
    private function getDatabaseHealthMetrics(): array
    {
        try {
            $startTime = microtime(true);
            DB::select('SELECT 1');
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'active_connections' => DB::select('PRAGMA database_list')[0] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get cache health metrics.
     */
    private function getCacheHealthMetrics(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $startTime = microtime(true);
            
            Cache::put($testKey, 'test', 60);
            Cache::get($testKey);
            Cache::forget($testKey);
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get queue health metrics.
     */
    private function getQueueHealthMetrics(): array
    {
        try {
            return [
                'status' => 'healthy',
                'pending_jobs' => 0, // Simplified
                'failed_jobs' => DB::table('failed_jobs')->count(),
                'driver' => config('queue.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get error metrics.
     */
    private function getErrorMetrics(): array
    {
        $today = date('Y-m-d');
        $errorCount = count(Cache::get("business_metrics:{$today}", []));
        
        return [
            'errors_today' => $errorCount,
            'critical_errors' => $this->getCriticalErrorCount(),
        ];
    }

    /**
     * Get critical error count.
     */
    private function getCriticalErrorCount(): int
    {
        // This is simplified - in production you'd query your error tracking system
        return 0;
    }

    /**
     * Get error severity.
     */
    private function getErrorSeverity(\Throwable $exception): string
    {
        if ($exception instanceof \Error) {
            return 'critical';
        }
        
        if ($exception instanceof \RuntimeException) {
            return 'high';
        }
        
        return 'medium';
    }

    /**
     * Check if error is critical.
     */
    private function isCriticalError(\Throwable $exception): bool
    {
        return $exception instanceof \Error || 
               $exception instanceof \ParseError ||
               strpos($exception->getMessage(), 'database') !== false;
    }
}
