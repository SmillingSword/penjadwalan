<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuditLogService
{
    /**
     * Log an audit event.
     */
    public function log(
        string $action,
        string $resource_type,
        ?string $resource_id = null,
        array $metadata = [],
        ?User $actor = null,
        ?Request $request = null
    ): AuditLog {
        $actor = $actor ?: Auth::user();
        $request = $request ?: request();
        
        $auditData = [
            'actor_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'actor_name' => $actor?->name,
            'action' => $action,
            'resource_type' => $resource_type,
            'resource_id' => $resource_id,
            'organization_id' => $request?->get('current_organization_id'),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => array_merge($metadata, [
                'timestamp' => now()->toISOString(),
                'request_id' => $request?->header('X-Request-ID') ?: uniqid(),
                'session_id' => $request?->session()?->getId(),
            ]),
            'severity' => $this->determineSeverity($action, $resource_type),
            'created_at' => now(),
        ];

        // Add request details for sensitive operations
        if ($this->isSensitiveOperation($action, $resource_type)) {
            $auditData['metadata']['request_method'] = $request?->method();
            $auditData['metadata']['request_url'] = $request?->fullUrl();
            $auditData['metadata']['request_headers'] = $this->sanitizeHeaders($request?->headers->all() ?? []);
        }

        $auditLog = AuditLog::create($auditData);

        // Log to application logs for critical events
        if ($auditData['severity'] === 'critical') {
            Log::critical('Critical audit event', $auditData);
        } elseif ($auditData['severity'] === 'high') {
            Log::warning('High severity audit event', $auditData);
        }

        return $auditLog;
    }

    /**
     * Log calendar-related actions.
     */
    public function logCalendarAction(string $action, $calendar, array $metadata = []): AuditLog
    {
        return $this->log(
            $action,
            'calendar',
            $calendar?->id,
            array_merge($metadata, [
                'calendar_name' => $calendar?->name,
                'calendar_owner' => $calendar?->owner?->email,
            ])
        );
    }

    /**
     * Log event-related actions.
     */
    public function logEventAction(string $action, $event, array $metadata = []): AuditLog
    {
        return $this->log(
            $action,
            'event',
            $event?->id,
            array_merge($metadata, [
                'event_title' => $event?->title,
                'event_start' => $event?->start_at?->toISOString(),
                'calendar_id' => $event?->calendar_id,
                'is_recurring' => !empty($event?->rrule),
                'participant_count' => $event?->participants?->count() ?? 0,
            ])
        );
    }

    /**
     * Log user authentication actions.
     */
    public function logAuthAction(string $action, ?User $user = null, array $metadata = []): AuditLog
    {
        return $this->log(
            $action,
            'authentication',
            $user?->id,
            array_merge($metadata, [
                'user_email' => $user?->email,
                'login_method' => $metadata['method'] ?? 'email',
            ])
        );
    }

    /**
     * Log organization-related actions.
     */
    public function logOrganizationAction(string $action, $organization, array $metadata = []): AuditLog
    {
        return $this->log(
            $action,
            'organization',
            $organization?->id,
            array_merge($metadata, [
                'organization_name' => $organization?->name,
                'member_count' => $organization?->users?->count() ?? 0,
            ])
        );
    }

    /**
     * Log invitation-related actions.
     */
    public function logInvitationAction(string $action, $event, array $metadata = []): AuditLog
    {
        return $this->log(
            $action,
            'invitation',
            $event?->id,
            array_merge($metadata, [
                'event_title' => $event?->title,
                'participant_emails' => $metadata['participant_emails'] ?? [],
                'invitation_method' => $metadata['method'] ?? 'email',
            ])
        );
    }

    /**
     * Log ICS import/export actions.
     */
    public function logIcsAction(string $action, $calendar, array $metadata = []): AuditLog
    {
        return $this->log(
            $action,
            'ics_integration',
            $calendar?->id,
            array_merge($metadata, [
                'calendar_name' => $calendar?->name,
                'event_count' => $metadata['event_count'] ?? 0,
                'file_size' => $metadata['file_size'] ?? null,
                'source_url' => $metadata['source_url'] ?? null,
            ])
        );
    }

    /**
     * Log scheduling-related actions.
     */
    public function logSchedulingAction(string $action, array $metadata = []): AuditLog
    {
        return $this->log(
            $action,
            'scheduling',
            null,
            array_merge($metadata, [
                'participants' => $metadata['participants'] ?? [],
                'duration_minutes' => $metadata['duration_minutes'] ?? null,
                'suggested_times_count' => $metadata['suggested_times_count'] ?? null,
            ])
        );
    }

    /**
     * Log security-related events.
     */
    public function logSecurityEvent(string $action, array $metadata = []): AuditLog
    {
        return $this->log(
            $action,
            'security',
            null,
            array_merge($metadata, [
                'threat_level' => $metadata['threat_level'] ?? 'medium',
                'blocked' => $metadata['blocked'] ?? false,
            ])
        );
    }

    /**
     * Get audit logs for a specific resource.
     */
    public function getResourceAuditLogs(string $resourceType, string $resourceId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit logs for a specific user.
     */
    public function getUserAuditLogs(User $user, int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where('actor_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit logs for an organization.
     */
    public function getOrganizationAuditLogs(string $organizationId, int $limit = 200): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where('organization_id', $organizationId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Search audit logs with filters.
     */
    public function searchAuditLogs(array $filters = [], int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        $query = AuditLog::query();

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['resource_type'])) {
            $query->where('resource_type', $filters['resource_type']);
        }

        if (!empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date']));
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date']));
        }

        if (!empty($filters['ip_address'])) {
            $query->where('ip_address', $filters['ip_address']);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit statistics.
     */
    public function getAuditStatistics(string $organizationId, Carbon $startDate, Carbon $endDate): array
    {
        $baseQuery = AuditLog::where('organization_id', $organizationId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        return [
            'total_events' => $baseQuery->count(),
            'by_action' => $baseQuery->groupBy('action')
                ->selectRaw('action, count(*) as count')
                ->pluck('count', 'action')
                ->toArray(),
            'by_resource_type' => $baseQuery->groupBy('resource_type')
                ->selectRaw('resource_type, count(*) as count')
                ->pluck('count', 'resource_type')
                ->toArray(),
            'by_severity' => $baseQuery->groupBy('severity')
                ->selectRaw('severity, count(*) as count')
                ->pluck('count', 'severity')
                ->toArray(),
            'unique_actors' => $baseQuery->distinct('actor_id')->count('actor_id'),
            'unique_ips' => $baseQuery->distinct('ip_address')->count('ip_address'),
        ];
    }

    /**
     * Determine the severity of an audit event.
     */
    private function determineSeverity(string $action, string $resourceType): string
    {
        // Critical events
        $criticalActions = [
            'delete_organization',
            'delete_user',
            'security_breach',
            'unauthorized_access',
            'data_export',
            'admin_login',
        ];

        // High severity events
        $highActions = [
            'create_organization',
            'update_organization',
            'delete_calendar',
            'bulk_delete',
            'permission_change',
            'failed_login',
            'rate_limit_exceeded',
        ];

        // Medium severity events
        $mediumActions = [
            'create_calendar',
            'update_calendar',
            'delete_event',
            'send_invitation',
            'ics_import',
            'ics_export',
        ];

        if (in_array($action, $criticalActions)) {
            return 'critical';
        }

        if (in_array($action, $highActions)) {
            return 'high';
        }

        if (in_array($action, $mediumActions)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Check if an operation is sensitive and requires detailed logging.
     */
    private function isSensitiveOperation(string $action, string $resourceType): bool
    {
        $sensitiveActions = [
            'delete_organization',
            'delete_calendar',
            'bulk_delete',
            'data_export',
            'ics_import',
            'permission_change',
            'security_breach',
            'unauthorized_access',
        ];

        $sensitiveResources = [
            'organization',
            'security',
            'authentication',
        ];

        return in_array($action, $sensitiveActions) || in_array($resourceType, $sensitiveResources);
    }

    /**
     * Sanitize request headers for logging.
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'cookie',
            'x-api-key',
            'x-auth-token',
        ];

        $sanitized = [];
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveHeaders)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = is_array($value) ? implode(', ', $value) : $value;
            }
        }

        return $sanitized;
    }

    /**
     * Clean up old audit logs (for maintenance).
     */
    public function cleanupOldLogs(int $daysToKeep = 365): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        return AuditLog::where('created_at', '<', $cutoffDate)
            ->where('severity', '!=', 'critical') // Keep critical logs longer
            ->delete();
    }
}
