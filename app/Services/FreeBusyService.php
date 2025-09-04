<?php

namespace App\Services;

use App\Models\User;
use App\Models\Event;
use App\Models\FreeBusyBlock;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class FreeBusyService
{
    /**
     * Calculate free/busy information for a user within a date range.
     */
    public function getFreeBusyForUser(User $user, Carbon $startDate, Carbon $endDate, string $timezone = 'UTC'): array
    {
        // Convert dates to UTC for database queries
        $startUtc = $startDate->clone()->utc();
        $endUtc = $endDate->clone()->utc();

        // Get all events for the user in the date range
        $events = $this->getUserEvents($user, $startUtc, $endUtc);
        
        // Get manual free/busy blocks
        $freeBusyBlocks = $this->getUserFreeBusyBlocks($user, $startUtc, $endUtc);
        
        // Combine and process all busy periods
        $busyPeriods = $this->processBusyPeriods($events, $freeBusyBlocks, $timezone);
        
        // Generate free periods
        $freePeriods = $this->generateFreePeriods($startDate, $endDate, $busyPeriods, $timezone);
        
        return [
            'user_id' => $user->id,
            'start_date' => $startDate->toISOString(),
            'end_date' => $endDate->toISOString(),
            'timezone' => $timezone,
            'busy_periods' => $busyPeriods,
            'free_periods' => $freePeriods,
            'summary' => [
                'total_busy_minutes' => $this->calculateTotalMinutes($busyPeriods),
                'total_free_minutes' => $this->calculateTotalMinutes($freePeriods),
                'busy_periods_count' => count($busyPeriods),
                'free_periods_count' => count($freePeriods),
            ]
        ];
    }

    /**
     * Calculate free/busy information for multiple users.
     */
    public function getFreeBusyForUsers(Collection $users, Carbon $startDate, Carbon $endDate, string $timezone = 'UTC'): array
    {
        $results = [];
        
        foreach ($users as $user) {
            $results[] = $this->getFreeBusyForUser($user, $startDate, $endDate, $timezone);
        }
        
        return [
            'users' => $results,
            'combined_availability' => $this->findCommonAvailability($results, $startDate, $endDate, $timezone)
        ];
    }

    /**
     * Find available time slots for multiple users.
     */
    public function findAvailableSlots(
        Collection $users, 
        Carbon $startDate, 
        Carbon $endDate, 
        int $durationMinutes, 
        int $bufferMinutes = 0,
        string $timezone = 'UTC',
        array $workingHours = ['09:00', '17:00']
    ): array {
        $freeBusyData = $this->getFreeBusyForUsers($users, $startDate, $endDate, $timezone);
        $commonAvailability = $freeBusyData['combined_availability'];
        
        $availableSlots = [];
        
        foreach ($commonAvailability as $freeSlot) {
            $slotStart = Carbon::parse($freeSlot['start'], $timezone);
            $slotEnd = Carbon::parse($freeSlot['end'], $timezone);
            
            // Apply working hours filter
            $slotStart = $this->applyWorkingHours($slotStart, $workingHours, true);
            $slotEnd = $this->applyWorkingHours($slotEnd, $workingHours, false);
            
            if ($slotStart->gte($slotEnd)) {
                continue; // Skip if no working hours overlap
            }
            
            // Find all possible slots within this free period
            $currentTime = $slotStart->clone();
            
            while ($currentTime->clone()->addMinutes($durationMinutes + $bufferMinutes)->lte($slotEnd)) {
                $slotEndTime = $currentTime->clone()->addMinutes($durationMinutes);
                
                $availableSlots[] = [
                    'start' => $currentTime->toISOString(),
                    'end' => $slotEndTime->toISOString(),
                    'duration_minutes' => $durationMinutes,
                    'buffer_minutes' => $bufferMinutes,
                    'score' => $this->calculateSlotScore($currentTime, $workingHours, $users->count()),
                    'participants_count' => $users->count(),
                ];
                
                // Move to next possible slot (15-minute intervals)
                $currentTime->addMinutes(15);
            }
        }
        
        // Sort by score (best slots first)
        usort($availableSlots, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return array_slice($availableSlots, 0, 20); // Return top 20 slots
    }

    /**
     * Check if a specific time slot is available for all users.
     */
    public function isSlotAvailable(
        Collection $users, 
        Carbon $startTime, 
        Carbon $endTime, 
        int $bufferMinutes = 0
    ): array {
        $conflicts = [];
        $bufferedStart = $startTime->clone()->subMinutes($bufferMinutes);
        $bufferedEnd = $endTime->clone()->addMinutes($bufferMinutes);
        
        foreach ($users as $user) {
            $userConflicts = $this->findUserConflicts($user, $bufferedStart, $bufferedEnd);
            
            if (!empty($userConflicts)) {
                $conflicts[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'conflicts' => $userConflicts
                ];
            }
        }
        
        return [
            'available' => empty($conflicts),
            'conflicts' => $conflicts,
            'requested_slot' => [
                'start' => $startTime->toISOString(),
                'end' => $endTime->toISOString(),
                'duration_minutes' => $startTime->diffInMinutes($endTime),
                'buffer_minutes' => $bufferMinutes,
            ]
        ];
    }

    /**
     * Get all events for a user in the specified date range.
     */
    private function getUserEvents(User $user, Carbon $startUtc, Carbon $endUtc): Collection
    {
        return Event::whereHas('calendar', function ($query) use ($user) {
            $query->whereHas('organization.users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        })
        ->whereHas('participants', function ($query) use ($user) {
            $query->where('email', $user->email)
                  ->whereIn('status', ['accepted', 'tentative']);
        })
        ->where(function ($query) use ($startUtc, $endUtc) {
            $query->whereBetween('start_at', [$startUtc, $endUtc])
                  ->orWhereBetween('end_at', [$startUtc, $endUtc])
                  ->orWhere(function ($q) use ($startUtc, $endUtc) {
                      $q->where('start_at', '<=', $startUtc)
                        ->where('end_at', '>=', $endUtc);
                  });
        })
        ->with(['calendar', 'participants'])
        ->get();
    }

    /**
     * Get manual free/busy blocks for a user.
     */
    private function getUserFreeBusyBlocks(User $user, Carbon $startUtc, Carbon $endUtc): Collection
    {
        return FreeBusyBlock::where('user_id', $user->id)
            ->where('status', 'busy')
            ->where(function ($query) use ($startUtc, $endUtc) {
                $query->whereBetween('start_at', [$startUtc, $endUtc])
                      ->orWhereBetween('end_at', [$startUtc, $endUtc])
                      ->orWhere(function ($q) use ($startUtc, $endUtc) {
                          $q->where('start_at', '<=', $startUtc)
                            ->where('end_at', '>=', $endUtc);
                      });
            })
            ->get();
    }

    /**
     * Process busy periods from events and free/busy blocks.
     */
    private function processBusyPeriods(Collection $events, Collection $freeBusyBlocks, string $timezone): array
    {
        $busyPeriods = [];
        
        // Add events as busy periods
        foreach ($events as $event) {
            $busyPeriods[] = [
                'start' => $event->start_at->setTimezone($timezone)->toISOString(),
                'end' => $event->end_at->setTimezone($timezone)->toISOString(),
                'type' => 'event',
                'title' => $event->title,
                'event_id' => $event->id,
                'is_private' => $event->is_private,
            ];
        }
        
        // Add manual free/busy blocks
        foreach ($freeBusyBlocks as $block) {
            $busyPeriods[] = [
                'start' => $block->start_at->setTimezone($timezone)->toISOString(),
                'end' => $block->end_at->setTimezone($timezone)->toISOString(),
                'type' => 'busy_block',
                'title' => $block->title ?? 'Busy',
                'block_id' => $block->id,
                'is_private' => true,
            ];
        }
        
        // Sort by start time and merge overlapping periods
        usort($busyPeriods, fn($a, $b) => $a['start'] <=> $b['start']);
        
        return $this->mergeOverlappingPeriods($busyPeriods);
    }

    /**
     * Generate free periods from busy periods.
     */
    private function generateFreePeriods(Carbon $startDate, Carbon $endDate, array $busyPeriods, string $timezone): array
    {
        $freePeriods = [];
        $currentTime = $startDate->clone()->setTimezone($timezone);
        $endTime = $endDate->clone()->setTimezone($timezone);
        
        foreach ($busyPeriods as $busyPeriod) {
            $busyStart = Carbon::parse($busyPeriod['start'], $timezone);
            
            if ($currentTime->lt($busyStart)) {
                $freePeriods[] = [
                    'start' => $currentTime->toISOString(),
                    'end' => $busyStart->toISOString(),
                    'duration_minutes' => $currentTime->diffInMinutes($busyStart),
                ];
            }
            
            $currentTime = Carbon::parse($busyPeriod['end'], $timezone);
        }
        
        // Add final free period if there's time left
        if ($currentTime->lt($endTime)) {
            $freePeriods[] = [
                'start' => $currentTime->toISOString(),
                'end' => $endTime->toISOString(),
                'duration_minutes' => $currentTime->diffInMinutes($endTime),
            ];
        }
        
        return $freePeriods;
    }

    /**
     * Find common availability across multiple users.
     */
    private function findCommonAvailability(array $userFreeBusyData, Carbon $startDate, Carbon $endDate, string $timezone): array
    {
        if (empty($userFreeBusyData)) {
            return [];
        }
        
        // Start with the first user's free periods
        $commonFree = $userFreeBusyData[0]['free_periods'];
        
        // Intersect with each subsequent user's free periods
        for ($i = 1; $i < count($userFreeBusyData); $i++) {
            $commonFree = $this->intersectFreePeriods($commonFree, $userFreeBusyData[$i]['free_periods']);
        }
        
        return $commonFree;
    }

    /**
     * Intersect two sets of free periods.
     */
    private function intersectFreePeriods(array $periods1, array $periods2): array
    {
        $intersections = [];
        
        foreach ($periods1 as $period1) {
            foreach ($periods2 as $period2) {
                $start1 = Carbon::parse($period1['start']);
                $end1 = Carbon::parse($period1['end']);
                $start2 = Carbon::parse($period2['start']);
                $end2 = Carbon::parse($period2['end']);
                
                $intersectionStart = $start1->max($start2);
                $intersectionEnd = $end1->min($end2);
                
                if ($intersectionStart->lt($intersectionEnd)) {
                    $intersections[] = [
                        'start' => $intersectionStart->toISOString(),
                        'end' => $intersectionEnd->toISOString(),
                        'duration_minutes' => $intersectionStart->diffInMinutes($intersectionEnd),
                    ];
                }
            }
        }
        
        return $intersections;
    }

    /**
     * Merge overlapping busy periods.
     */
    private function mergeOverlappingPeriods(array $periods): array
    {
        if (empty($periods)) {
            return [];
        }
        
        $merged = [$periods[0]];
        
        for ($i = 1; $i < count($periods); $i++) {
            $current = $periods[$i];
            $lastMerged = &$merged[count($merged) - 1];
            
            $lastEnd = Carbon::parse($lastMerged['end']);
            $currentStart = Carbon::parse($current['start']);
            
            if ($currentStart->lte($lastEnd)) {
                // Overlapping periods - merge them
                $currentEnd = Carbon::parse($current['end']);
                if ($currentEnd->gt($lastEnd)) {
                    $lastMerged['end'] = $current['end'];
                }
                
                // Combine titles if different
                if ($lastMerged['title'] !== $current['title']) {
                    $lastMerged['title'] = $lastMerged['title'] . ', ' . $current['title'];
                }
            } else {
                // Non-overlapping - add as new period
                $merged[] = $current;
            }
        }
        
        return $merged;
    }

    /**
     * Apply working hours constraints to a time.
     */
    private function applyWorkingHours(Carbon $time, array $workingHours, bool $isStart): Carbon
    {
        $workStart = Carbon::parse($workingHours[0], $time->timezone)->setDateFrom($time);
        $workEnd = Carbon::parse($workingHours[1], $time->timezone)->setDateFrom($time);
        
        if ($isStart) {
            return $time->max($workStart);
        } else {
            return $time->min($workEnd);
        }
    }

    /**
     * Calculate a score for a time slot (higher is better).
     */
    private function calculateSlotScore(Carbon $slotTime, array $workingHours, int $participantCount): int
    {
        $score = 100;
        
        // Prefer times closer to the middle of working hours
        $workStart = Carbon::parse($workingHours[0], $slotTime->timezone)->setDateFrom($slotTime);
        $workEnd = Carbon::parse($workingHours[1], $slotTime->timezone)->setDateFrom($slotTime);
        $workMiddle = $workStart->clone()->addMinutes($workStart->diffInMinutes($workEnd) / 2);
        
        $distanceFromMiddle = abs($slotTime->diffInMinutes($workMiddle));
        $score -= $distanceFromMiddle / 10; // Reduce score based on distance from middle
        
        // Prefer weekdays over weekends
        if ($slotTime->isWeekend()) {
            $score -= 20;
        }
        
        // Prefer certain hours (10 AM - 4 PM gets bonus)
        $hour = $slotTime->hour;
        if ($hour >= 10 && $hour <= 16) {
            $score += 10;
        }
        
        // Bonus for more participants (shows importance)
        $score += min($participantCount * 2, 10);
        
        return max(0, (int) $score);
    }

    /**
     * Find conflicts for a specific user in a time range.
     */
    private function findUserConflicts(User $user, Carbon $startTime, Carbon $endTime): array
    {
        $conflicts = [];
        
        // Check events
        $events = $this->getUserEvents($user, $startTime->clone()->utc(), $endTime->clone()->utc());
        foreach ($events as $event) {
            $conflicts[] = [
                'type' => 'event',
                'title' => $event->is_private ? 'Private Event' : $event->title,
                'start' => $event->start_at->toISOString(),
                'end' => $event->end_at->toISOString(),
                'event_id' => $event->id,
            ];
        }
        
        // Check free/busy blocks
        $blocks = $this->getUserFreeBusyBlocks($user, $startTime->clone()->utc(), $endTime->clone()->utc());
        foreach ($blocks as $block) {
            $conflicts[] = [
                'type' => 'busy_block',
                'title' => $block->title ?? 'Busy',
                'start' => $block->start_at->toISOString(),
                'end' => $block->end_at->toISOString(),
                'block_id' => $block->id,
            ];
        }
        
        return $conflicts;
    }

    /**
     * Calculate total minutes for a set of periods.
     */
    private function calculateTotalMinutes(array $periods): int
    {
        $total = 0;
        foreach ($periods as $period) {
            if (isset($period['duration_minutes'])) {
                $total += $period['duration_minutes'];
            } else {
                $start = Carbon::parse($period['start']);
                $end = Carbon::parse($period['end']);
                $total += $start->diffInMinutes($end);
            }
        }
        return $total;
    }
}
