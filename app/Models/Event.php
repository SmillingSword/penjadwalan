<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use App\Services\RecurrenceService;

class Event extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'calendar_id',
        'title',
        'description_md',
        'location',
        'meeting_link',
        'start_at',
        'end_at',
        'all_day',
        'timezone',
        'rrule',
        'exdates',
        'is_private',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'all_day' => 'boolean',
        'is_private' => 'boolean',
        'exdates' => 'array',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /**
     * Scope a query to only include events within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($query) use ($startDate, $endDate) {
            $query->whereBetween('start_at', [$startDate, $endDate])
                  ->orWhereBetween('end_at', [$startDate, $endDate])
                  ->orWhere(function ($query) use ($startDate, $endDate) {
                      $query->where('start_at', '<=', $startDate)
                            ->where('end_at', '>=', $endDate);
                  });
        });
    }

    /**
     * Scope a query to only include public events.
     */
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    /**
     * Scope a query to only include private events.
     */
    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    /**
     * Scope a query to only include recurring events.
     */
    public function scopeRecurring($query)
    {
        return $query->whereNotNull('rrule');
    }

    /**
     * Scope a query to only include non-recurring events.
     */
    public function scopeNonRecurring($query)
    {
        return $query->whereNull('rrule');
    }

    /**
     * Check if the event is recurring.
     */
    public function isRecurring(): bool
    {
        return !empty($this->rrule);
    }

    /**
     * Get the duration of the event in minutes.
     */
    public function getDurationInMinutes(): int
    {
        if (!$this->end_at) {
            return $this->all_day ? 1440 : 60; // 24 hours for all-day, 1 hour default
        }

        return $this->start_at->diffInMinutes($this->end_at);
    }

    /**
     * Check if the event is all day.
     */
    public function isAllDay(): bool
    {
        return $this->all_day;
    }

    /**
     * Get formatted start date.
     */
    public function getFormattedStartAttribute(): string
    {
        if ($this->all_day) {
            return $this->start_at->format('Y-m-d');
        }

        return $this->start_at->setTimezone($this->timezone ?? 'UTC')->format('Y-m-d H:i:s T');
    }

    /**
     * Get formatted end date.
     */
    public function getFormattedEndAttribute(): ?string
    {
        if (!$this->end_at) {
            return null;
        }

        if ($this->all_day) {
            return $this->end_at->format('Y-m-d');
        }

        return $this->end_at->setTimezone($this->timezone ?? 'UTC')->format('Y-m-d H:i:s T');
    }

    /**
     * Expand recurring event instances for a date range.
     */
    public function expandRecurrence(Carbon $rangeStart, Carbon $rangeEnd): \Illuminate\Support\Collection
    {
        if (!$this->isRecurring()) {
            return collect();
        }

        $recurrenceService = app(RecurrenceService::class);
        
        return $recurrenceService->expandRecurrence(
            $this->rrule,
            $this->start_at,
            $this->end_at,
            $rangeStart,
            $rangeEnd,
            $this->exdates ?? [],
            $this->timezone ?? 'UTC'
        );
    }

    /**
     * Get human-readable recurrence description.
     */
    public function getRecurrenceDescription(): ?string
    {
        if (!$this->isRecurring()) {
            return null;
        }

        $recurrenceService = app(RecurrenceService::class);
        return $recurrenceService->getRRuleDescription($this->rrule, $this->timezone ?? 'UTC');
    }

    /**
     * Add an exception date to exclude from recurrence.
     */
    public function addExceptionDate(Carbon $date): void
    {
        $exdates = $this->exdates ?? [];
        $dateString = $date->format('Y-m-d');
        
        if (!in_array($dateString, $exdates)) {
            $exdates[] = $dateString;
            $this->update(['exdates' => $exdates]);
        }
    }

    /**
     * Remove an exception date.
     */
    public function removeExceptionDate(Carbon $date): void
    {
        $exdates = $this->exdates ?? [];
        $dateString = $date->format('Y-m-d');
        
        $exdates = array_filter($exdates, function ($exdate) use ($dateString) {
            return $exdate !== $dateString;
        });
        
        $this->update(['exdates' => array_values($exdates)]);
    }

    /**
     * Check if a date is excluded from recurrence.
     */
    public function isDateExcluded(Carbon $date): bool
    {
        if (!$this->exdates) {
            return false;
        }

        $dateString = $date->format('Y-m-d');
        return in_array($dateString, $this->exdates);
    }

    /**
     * Validate RRULE before saving.
     */
    public function validateRRule(): bool
    {
        if (!$this->rrule) {
            return true;
        }

        $recurrenceService = app(RecurrenceService::class);
        return $recurrenceService->validateRRule($this->rrule);
    }

    /**
     * Boot method to add model event listeners.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($event) {
            // Validate RRULE before saving
            if ($event->rrule && !$event->validateRRule()) {
                throw new \InvalidArgumentException('Invalid RRULE format');
            }
        });
    }
}
