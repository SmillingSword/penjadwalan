<?php

namespace App\Services;

use App\Models\User;
use App\Models\Event;
use App\Models\Calendar;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SchedulingAssistantService
{
    protected FreeBusyService $freeBusyService;

    public function __construct(FreeBusyService $freeBusyService)
    {
        $this->freeBusyService = $freeBusyService;
    }

    /**
     * Suggest optimal meeting times for multiple participants.
     */
    public function suggestMeetingTimes(array $params): array
    {
        $participants = $this->resolveParticipants($params['participants']);
        $durationMinutes = $params['duration_minutes'];
        $bufferMinutes = $params['buffer_minutes'] ?? 15;
        $timezone = $params['timezone'] ?? 'UTC';
        $workingHours = $params['working_hours'] ?? ['09:00', '17:00'];
        $preferredDays = $params['preferred_days'] ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $maxSuggestions = $params['max_suggestions'] ?? 10;
        
        // Default to next 14 days if no date range specified
        $startDate = isset($params['start_date']) 
            ? Carbon::parse($params['start_date'], $timezone)
            : Carbon::now($timezone)->startOfDay();
        $endDate = isset($params['end_date'])
            ? Carbon::parse($params['end_date'], $timezone)
            : $startDate->clone()->addDays(14)->endOfDay();

        // Find available slots
        $availableSlots = $this->freeBusyService->findAvailableSlots(
            $participants,
            $startDate,
            $endDate,
            $durationMinutes,
            $bufferMinutes,
            $timezone,
            $workingHours
        );

        // Filter by preferred days
        $filteredSlots = $this->filterByPreferredDays($availableSlots, $preferredDays, $timezone);

        // Enhance slots with additional metadata
        $enhancedSlots = $this->enhanceSlots($filteredSlots, $participants, $timezone);

        // Group by date for better presentation
        $groupedSlots = $this->groupSlotsByDate($enhancedSlots, $timezone);

        return [
            'suggestions' => array_slice($enhancedSlots, 0, $maxSuggestions),
            'grouped_by_date' => $groupedSlots,
            'search_criteria' => [
                'participants_count' => $participants->count(),
                'duration_minutes' => $durationMinutes,
                'buffer_minutes' => $bufferMinutes,
                'timezone' => $timezone,
                'working_hours' => $workingHours,
                'preferred_days' => $preferredDays,
                'date_range' => [
                    'start' => $startDate->toISOString(),
                    'end' => $endDate->toISOString(),
                ],
            ],
            'total_slots_found' => count($enhancedSlots),
            'participants' => $participants->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'timezone' => $user->timezone ?? $timezone,
            ]),
        ];
    }

    /**
     * Find the next available meeting slot.
     */
    public function findNextAvailableSlot(array $params): ?array
    {
        $suggestions = $this->suggestMeetingTimes(array_merge($params, ['max_suggestions' => 1]));
        
        return $suggestions['suggestions'][0] ?? null;
    }

    /**
     * Check if a specific meeting time works for all participants.
     */
    public function validateMeetingTime(array $params): array
    {
        $participants = $this->resolveParticipants($params['participants']);
        $startTime = Carbon::parse($params['start_time'], $params['timezone'] ?? 'UTC');
        $endTime = Carbon::parse($params['end_time'], $params['timezone'] ?? 'UTC');
        $bufferMinutes = $params['buffer_minutes'] ?? 15;

        $availability = $this->freeBusyService->isSlotAvailable(
            $participants,
            $startTime,
            $endTime,
            $bufferMinutes
        );

        // Add recommendations if there are conflicts
        $recommendations = [];
        if (!$availability['available']) {
            $recommendations = $this->generateConflictRecommendations(
                $participants,
                $startTime,
                $endTime,
                $params
            );
        }

        return [
            'available' => $availability['available'],
            'conflicts' => $availability['conflicts'],
            'requested_slot' => $availability['requested_slot'],
            'recommendations' => $recommendations,
            'participants' => $participants->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'has_conflict' => collect($availability['conflicts'])->contains('user_id', $user->id),
            ]),
        ];
    }

    /**
     * Create a meeting with optimal scheduling.
     */
    public function createOptimalMeeting(array $params): array
    {
        // First, try to validate the requested time if provided
        if (isset($params['preferred_start_time'])) {
            $validation = $this->validateMeetingTime([
                'participants' => $params['participants'],
                'start_time' => $params['preferred_start_time'],
                'end_time' => Carbon::parse($params['preferred_start_time'])
                    ->addMinutes($params['duration_minutes'])
                    ->toISOString(),
                'timezone' => $params['timezone'] ?? 'UTC',
                'buffer_minutes' => $params['buffer_minutes'] ?? 15,
            ]);

            if ($validation['available']) {
                return $this->createMeetingAtTime($params, $params['preferred_start_time']);
            }
        }

        // Find the best available slot
        $nextSlot = $this->findNextAvailableSlot($params);
        
        if (!$nextSlot) {
            throw new \Exception('No available time slots found for the specified criteria.');
        }

        return $this->createMeetingAtTime($params, $nextSlot['start']);
    }

    /**
     * Analyze meeting patterns and suggest improvements.
     */
    public function analyzeMeetingPatterns(User $user, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        // Get user's events
        $events = Event::whereHas('participants', function ($query) use ($user) {
            $query->where('email', $user->email);
        })
        ->whereBetween('start_at', [$startDate, $endDate])
        ->with(['participants', 'calendar'])
        ->get();

        $analysis = [
            'total_meetings' => $events->count(),
            'total_meeting_hours' => $events->sum(fn($event) => $event->start_at->diffInHours($event->end_at)),
            'average_meeting_duration' => $events->avg(fn($event) => $event->start_at->diffInMinutes($event->end_at)),
            'meetings_by_day_of_week' => [],
            'meetings_by_hour' => [],
            'busiest_days' => [],
            'meeting_frequency_trend' => [],
            'recommendations' => [],
        ];

        // Analyze by day of week
        $dayGroups = $events->groupBy(fn($event) => $event->start_at->format('l'));
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            $analysis['meetings_by_day_of_week'][$day] = $dayGroups->get($day, collect())->count();
        }

        // Analyze by hour
        $hourGroups = $events->groupBy(fn($event) => $event->start_at->format('H'));
        for ($hour = 0; $hour < 24; $hour++) {
            $analysis['meetings_by_hour'][$hour] = $hourGroups->get(sprintf('%02d', $hour), collect())->count();
        }

        // Find busiest days
        $dailyGroups = $events->groupBy(fn($event) => $event->start_at->format('Y-m-d'));
        $analysis['busiest_days'] = $dailyGroups
            ->map(fn($dayEvents) => $dayEvents->count())
            ->sortDesc()
            ->take(5)
            ->toArray();

        // Generate recommendations
        $analysis['recommendations'] = $this->generatePatternRecommendations($analysis, $user);

        return $analysis;
    }

    /**
     * Resolve participants from various input formats.
     */
    private function resolveParticipants(array $participants): Collection
    {
        $users = collect();

        foreach ($participants as $participant) {
            if (is_string($participant)) {
                // Email address
                $user = User::where('email', $participant)->first();
                if ($user) {
                    $users->push($user);
                }
            } elseif (is_array($participant) && isset($participant['email'])) {
                // Array with email
                $user = User::where('email', $participant['email'])->first();
                if ($user) {
                    $users->push($user);
                }
            } elseif (is_numeric($participant)) {
                // User ID
                $user = User::find($participant);
                if ($user) {
                    $users->push($user);
                }
            }
        }

        return $users->unique('id');
    }

    /**
     * Filter slots by preferred days of the week.
     */
    private function filterByPreferredDays(array $slots, array $preferredDays, string $timezone): array
    {
        $dayMap = [
            'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
            'thursday' => 4, 'friday' => 5, 'saturday' => 6
        ];

        $preferredDayNumbers = array_map(fn($day) => $dayMap[strtolower($day)] ?? null, $preferredDays);
        $preferredDayNumbers = array_filter($preferredDayNumbers, fn($day) => $day !== null);

        return array_filter($slots, function ($slot) use ($preferredDayNumbers, $timezone) {
            $slotDay = Carbon::parse($slot['start'], $timezone)->dayOfWeek;
            return in_array($slotDay, $preferredDayNumbers);
        });
    }

    /**
     * Enhance slots with additional metadata.
     */
    private function enhanceSlots(array $slots, Collection $participants, string $timezone): array
    {
        return array_map(function ($slot) use ($participants, $timezone) {
            $startTime = Carbon::parse($slot['start'], $timezone);
            
            return array_merge($slot, [
                'formatted_start' => $startTime->format('Y-m-d H:i'),
                'formatted_end' => Carbon::parse($slot['end'], $timezone)->format('Y-m-d H:i'),
                'day_of_week' => $startTime->format('l'),
                'date' => $startTime->format('Y-m-d'),
                'time' => $startTime->format('H:i'),
                'is_weekend' => $startTime->isWeekend(),
                'timezone' => $timezone,
                'quality_score' => $this->calculateQualityScore($slot, $startTime),
                'recommendation_reason' => $this->getRecommendationReason($slot, $startTime),
            ]);
        }, $slots);
    }

    /**
     * Group slots by date.
     */
    private function groupSlotsByDate(array $slots, string $timezone): array
    {
        $grouped = [];
        
        foreach ($slots as $slot) {
            $date = Carbon::parse($slot['start'], $timezone)->format('Y-m-d');
            if (!isset($grouped[$date])) {
                $grouped[$date] = [
                    'date' => $date,
                    'formatted_date' => Carbon::parse($slot['start'], $timezone)->format('l, F j, Y'),
                    'slots' => [],
                ];
            }
            $grouped[$date]['slots'][] = $slot;
        }

        return array_values($grouped);
    }

    /**
     * Generate recommendations when there are conflicts.
     */
    private function generateConflictRecommendations(
        Collection $participants,
        Carbon $startTime,
        Carbon $endTime,
        array $params
    ): array {
        $recommendations = [];

        // Suggest nearby available slots
        $nearbySlots = $this->freeBusyService->findAvailableSlots(
            $participants,
            $startTime->clone()->subHours(2),
            $startTime->clone()->addHours(4),
            $startTime->diffInMinutes($endTime),
            $params['buffer_minutes'] ?? 15,
            $params['timezone'] ?? 'UTC'
        );

        if (!empty($nearbySlots)) {
            $recommendations[] = [
                'type' => 'alternative_times',
                'message' => 'Here are some nearby available times:',
                'slots' => array_slice($nearbySlots, 0, 3),
            ];
        }

        // Suggest different day
        $nextDaySlots = $this->freeBusyService->findAvailableSlots(
            $participants,
            $startTime->clone()->addDay()->startOfDay(),
            $startTime->clone()->addDay()->endOfDay(),
            $startTime->diffInMinutes($endTime),
            $params['buffer_minutes'] ?? 15,
            $params['timezone'] ?? 'UTC'
        );

        if (!empty($nextDaySlots)) {
            $recommendations[] = [
                'type' => 'next_day',
                'message' => 'Consider scheduling for the next day:',
                'slots' => array_slice($nextDaySlots, 0, 2),
            ];
        }

        return $recommendations;
    }

    /**
     * Create a meeting at a specific time.
     */
    private function createMeetingAtTime(array $params, string $startTime): array
    {
        $participants = $this->resolveParticipants($params['participants']);
        $start = Carbon::parse($startTime, $params['timezone'] ?? 'UTC');
        $end = $start->clone()->addMinutes($params['duration_minutes']);

        // Find appropriate calendar (use organizer's default calendar)
        $organizer = $participants->first(); // Assume first participant is organizer
        $calendar = Calendar::where('owner_user_id', $organizer->id)->first();

        if (!$calendar) {
            throw new \Exception('No calendar found for the organizer.');
        }

        // Create the event
        $event = Event::create([
            'calendar_id' => $calendar->id,
            'title' => $params['title'] ?? 'Meeting',
            'description_md' => $params['description'] ?? '',
            'location' => $params['location'] ?? '',
            'meeting_link' => $params['meeting_link'] ?? '',
            'start_at' => $start->utc(),
            'end_at' => $end->utc(),
            'timezone' => $params['timezone'] ?? 'UTC',
            'all_day' => false,
            'is_private' => $params['is_private'] ?? false,
        ]);

        // Add participants
        foreach ($participants as $participant) {
            $event->participants()->create([
                'email' => $participant->email,
                'name' => $participant->name,
                'role' => $participant->id === $organizer->id ? 'required' : 'required',
                'status' => 'invited',
            ]);
        }

        // Add reminders if specified
        if (isset($params['reminders'])) {
            foreach ($params['reminders'] as $reminder) {
                $event->reminders()->create($reminder);
            }
        }

        return [
            'event' => $event->load(['participants', 'reminders', 'calendar']),
            'scheduled_time' => [
                'start' => $start->toISOString(),
                'end' => $end->toISOString(),
                'timezone' => $params['timezone'] ?? 'UTC',
                'duration_minutes' => $params['duration_minutes'],
            ],
            'participants' => $participants->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]),
        ];
    }

    /**
     * Calculate quality score for a time slot.
     */
    private function calculateQualityScore(array $slot, Carbon $startTime): string
    {
        $score = $slot['score'] ?? 0;

        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 60) return 'fair';
        return 'poor';
    }

    /**
     * Get recommendation reason for a time slot.
     */
    private function getRecommendationReason(array $slot, Carbon $startTime): string
    {
        $reasons = [];

        if (!$startTime->isWeekend()) {
            $reasons[] = 'weekday';
        }

        $hour = $startTime->hour;
        if ($hour >= 10 && $hour <= 14) {
            $reasons[] = 'optimal time';
        }

        if ($slot['participants_count'] > 2) {
            $reasons[] = 'good for group meetings';
        }

        return implode(', ', $reasons) ?: 'available slot';
    }

    /**
     * Generate pattern-based recommendations.
     */
    private function generatePatternRecommendations(array $analysis, User $user): array
    {
        $recommendations = [];

        // Check for meeting overload
        if ($analysis['total_meeting_hours'] > 20) {
            $recommendations[] = [
                'type' => 'meeting_overload',
                'message' => 'You have a high number of meeting hours. Consider consolidating or declining non-essential meetings.',
                'priority' => 'high',
            ];
        }

        // Check for long meetings
        if ($analysis['average_meeting_duration'] > 60) {
            $recommendations[] = [
                'type' => 'long_meetings',
                'message' => 'Your average meeting duration is quite long. Consider shorter, more focused meetings.',
                'priority' => 'medium',
            ];
        }

        // Check for meeting distribution
        $maxMeetingsPerDay = max($analysis['meetings_by_day_of_week']);
        $minMeetingsPerDay = min(array_filter($analysis['meetings_by_day_of_week']));
        
        if ($maxMeetingsPerDay > $minMeetingsPerDay * 3) {
            $recommendations[] = [
                'type' => 'uneven_distribution',
                'message' => 'Your meetings are unevenly distributed across the week. Consider spreading them more evenly.',
                'priority' => 'low',
            ];
        }

        return $recommendations;
    }
}
