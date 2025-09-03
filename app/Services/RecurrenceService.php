<?php

namespace App\Services;

use RRule\RRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class RecurrenceService
{
    /**
     * Validate an RRULE string.
     */
    public function validateRRule(string $rrule): bool
    {
        try {
            new RRule($rrule);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Expand recurring events for a given date range.
     * 
     * @param string $rrule The RRULE string
     * @param Carbon $startDate The event start date
     * @param Carbon $endDate The event end date (duration)
     * @param Carbon $rangeStart The range start for expansion
     * @param Carbon $rangeEnd The range end for expansion
     * @param array $exdates Array of exception dates to exclude
     * @param string $timezone The timezone for the event
     * @return Collection Collection of event instances
     */
    public function expandRecurrence(
        string $rrule,
        Carbon $startDate,
        ?Carbon $endDate,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        array $exdates = [],
        string $timezone = 'UTC'
    ): Collection {
        try {
            // Create RRule instance
            $rule = new RRule($rrule, $startDate->setTimezone($timezone)->toDateTimeString());
            
            // Calculate event duration
            $duration = $endDate ? $startDate->diffInMinutes($endDate) : 60;
            
            // Convert exdates to Carbon instances for comparison
            $excludeDates = collect($exdates)->map(function ($date) use ($timezone) {
                return Carbon::parse($date, $timezone)->startOfDay();
            });
            
            // Generate occurrences within the range
            $occurrences = collect();
            
            foreach ($rule as $occurrence) {
                $occurrenceStart = Carbon::instance($occurrence)->setTimezone($timezone);
                
                // Skip if this date is in exdates
                if ($excludeDates->contains(function ($exdate) use ($occurrenceStart) {
                    return $exdate->isSameDay($occurrenceStart);
                })) {
                    continue;
                }
                
                // Check if occurrence is within the requested range
                if ($occurrenceStart->gte($rangeStart) && $occurrenceStart->lte($rangeEnd)) {
                    // Calculate end time
                    $occurrenceEnd = $occurrenceStart->copy()->addMinutes($duration);
                    
                    $occurrences->push([
                        'start_at' => $occurrenceStart->utc(),
                        'end_at' => $occurrenceEnd->utc(),
                        'original_start' => $occurrenceStart->copy(),
                        'is_recurring_instance' => true,
                    ]);
                }
                
                // Stop if we're past the range (optimization)
                if ($occurrenceStart->gt($rangeEnd)) {
                    break;
                }
            }
            
            return $occurrences;
            
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Invalid RRULE: " . $e->getMessage());
        }
    }

    /**
     * Get human-readable description of an RRULE.
     */
    public function getRRuleDescription(string $rrule, string $timezone = 'UTC'): string
    {
        try {
            $rule = new RRule($rrule);
            return $rule->humanReadable([
                'locale' => 'en',
                'date_formatter' => function ($date) use ($timezone) {
                    return Carbon::instance($date)->setTimezone($timezone)->format('M j, Y');
                }
            ]);
        } catch (\Exception $e) {
            return 'Invalid recurrence rule';
        }
    }

    /**
     * Create common RRULE patterns.
     */
    public function createDailyRule(int $interval = 1, ?Carbon $until = null, ?int $count = null): string
    {
        $parts = ["FREQ=DAILY"];
        
        if ($interval > 1) {
            $parts[] = "INTERVAL={$interval}";
        }
        
        if ($until) {
            $parts[] = "UNTIL=" . $until->utc()->format('Ymd\THis\Z');
        } elseif ($count) {
            $parts[] = "COUNT={$count}";
        }
        
        return implode(';', $parts);
    }

    /**
     * Create weekly RRULE.
     */
    public function createWeeklyRule(
        array $byDay = [],
        int $interval = 1,
        ?Carbon $until = null,
        ?int $count = null
    ): string {
        $parts = ["FREQ=WEEKLY"];
        
        if ($interval > 1) {
            $parts[] = "INTERVAL={$interval}";
        }
        
        if (!empty($byDay)) {
            $parts[] = "BYDAY=" . implode(',', $byDay);
        }
        
        if ($until) {
            $parts[] = "UNTIL=" . $until->utc()->format('Ymd\THis\Z');
        } elseif ($count) {
            $parts[] = "COUNT={$count}";
        }
        
        return implode(';', $parts);
    }

    /**
     * Create monthly RRULE.
     */
    public function createMonthlyRule(
        ?int $byMonthDay = null,
        ?string $byDay = null,
        int $interval = 1,
        ?Carbon $until = null,
        ?int $count = null
    ): string {
        $parts = ["FREQ=MONTHLY"];
        
        if ($interval > 1) {
            $parts[] = "INTERVAL={$interval}";
        }
        
        if ($byMonthDay) {
            $parts[] = "BYMONTHDAY={$byMonthDay}";
        } elseif ($byDay) {
            $parts[] = "BYDAY={$byDay}";
        }
        
        if ($until) {
            $parts[] = "UNTIL=" . $until->utc()->format('Ymd\THis\Z');
        } elseif ($count) {
            $parts[] = "COUNT={$count}";
        }
        
        return implode(';', $parts);
    }

    /**
     * Create yearly RRULE.
     */
    public function createYearlyRule(
        ?int $byMonth = null,
        ?int $byMonthDay = null,
        int $interval = 1,
        ?Carbon $until = null,
        ?int $count = null
    ): string {
        $parts = ["FREQ=YEARLY"];
        
        if ($interval > 1) {
            $parts[] = "INTERVAL={$interval}";
        }
        
        if ($byMonth) {
            $parts[] = "BYMONTH={$byMonth}";
        }
        
        if ($byMonthDay) {
            $parts[] = "BYMONTHDAY={$byMonthDay}";
        }
        
        if ($until) {
            $parts[] = "UNTIL=" . $until->utc()->format('Ymd\THis\Z');
        } elseif ($count) {
            $parts[] = "COUNT={$count}";
        }
        
        return implode(';', $parts);
    }

    /**
     * Parse RRULE components.
     */
    public function parseRRule(string $rrule): array
    {
        $components = [];
        $parts = explode(';', $rrule);
        
        foreach ($parts as $part) {
            if (strpos($part, '=') !== false) {
                [$key, $value] = explode('=', $part, 2);
                $components[strtoupper($key)] = $value;
            }
        }
        
        return $components;
    }

    /**
     * Check if a date should be excluded based on exdates.
     */
    public function isExcludedDate(Carbon $date, array $exdates, string $timezone = 'UTC'): bool
    {
        $checkDate = $date->copy()->setTimezone($timezone)->startOfDay();
        
        foreach ($exdates as $exdate) {
            $excludeDate = Carbon::parse($exdate, $timezone)->startOfDay();
            if ($checkDate->equalTo($excludeDate)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if a specific date is excluded from the recurring event.
     */
    public function isExcluded($event, string $date): bool
    {
        if (!$event->exdates || !is_array($event->exdates)) {
            return false;
        }

        return $this->isExcludedDate(
            Carbon::parse($date),
            $event->exdates,
            $event->timezone ?? 'UTC'
        );
    }
}
