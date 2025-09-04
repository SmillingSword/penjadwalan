<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FreeBusyService;
use App\Services\SchedulingAssistantService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;

class SchedulingController extends Controller
{
    use AuthorizesRequests;

    protected FreeBusyService $freeBusyService;
    protected SchedulingAssistantService $schedulingService;

    public function __construct(
        FreeBusyService $freeBusyService,
        SchedulingAssistantService $schedulingService
    ) {
        $this->freeBusyService = $freeBusyService;
        $this->schedulingService = $schedulingService;
    }

    /**
     * Get free/busy information for a user.
     */
    public function getFreeBusy(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'timezone' => 'nullable|string|in:' . implode(',', timezone_identifiers_list()),
        ]);

        $startDate = Carbon::parse($validated['start_date'], $validated['timezone'] ?? 'UTC');
        $endDate = Carbon::parse($validated['end_date'], $validated['timezone'] ?? 'UTC');
        $timezone = $validated['timezone'] ?? $request->user()->timezone ?? 'UTC';

        $freeBusyData = $this->freeBusyService->getFreeBusyForUser($user, $startDate, $endDate, $timezone);

        return response()->json([
            'success' => true,
            'data' => $freeBusyData,
        ]);
    }

    /**
     * Get free/busy information for multiple users.
     */
    public function getMultipleFreeBusy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:20',
            'user_ids.*' => 'exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'timezone' => 'nullable|string|in:' . implode(',', timezone_identifiers_list()),
        ]);

        $users = User::whereIn('id', $validated['user_ids'])->get();
        
        // Check authorization for each user
        foreach ($users as $user) {
            $this->authorize('view', $user);
        }

        $startDate = Carbon::parse($validated['start_date'], $validated['timezone'] ?? 'UTC');
        $endDate = Carbon::parse($validated['end_date'], $validated['timezone'] ?? 'UTC');
        $timezone = $validated['timezone'] ?? $request->user()->timezone ?? 'UTC';

        $freeBusyData = $this->freeBusyService->getFreeBusyForUsers($users, $startDate, $endDate, $timezone);

        return response()->json([
            'success' => true,
            'data' => $freeBusyData,
        ]);
    }

    /**
     * Find available meeting slots.
     */
    public function findAvailableSlots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'participants' => 'required|array|min:1|max:20',
            'participants.*' => 'email',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'buffer_minutes' => 'nullable|integer|min:0|max:60',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'timezone' => 'nullable|string|in:' . implode(',', timezone_identifiers_list()),
            'working_hours' => 'nullable|array|size:2',
            'working_hours.*' => 'date_format:H:i',
            'preferred_days' => 'nullable|array',
            'preferred_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'max_suggestions' => 'nullable|integer|min:1|max:50',
        ]);

        // Resolve participants to users
        $users = User::whereIn('email', $validated['participants'])->get();
        
        if ($users->count() !== count($validated['participants'])) {
            return response()->json([
                'success' => false,
                'message' => 'Some participants were not found in the system.',
                'missing_participants' => array_diff($validated['participants'], $users->pluck('email')->toArray()),
            ], 404);
        }

        // Check authorization for each user
        foreach ($users as $user) {
            $this->authorize('view', $user);
        }

        $timezone = $validated['timezone'] ?? $request->user()->timezone ?? 'UTC';
        
        $slots = $this->freeBusyService->findAvailableSlots(
            $users,
            isset($validated['start_date']) 
                ? Carbon::parse($validated['start_date'], $timezone)
                : Carbon::now($timezone)->startOfDay(),
            isset($validated['end_date'])
                ? Carbon::parse($validated['end_date'], $timezone)
                : Carbon::now($timezone)->addDays(14)->endOfDay(),
            $validated['duration_minutes'],
            $validated['buffer_minutes'] ?? 15,
            $timezone,
            $validated['working_hours'] ?? ['09:00', '17:00']
        );

        // Filter by preferred days if specified
        if (isset($validated['preferred_days'])) {
            $dayMap = [
                'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                'thursday' => 4, 'friday' => 5, 'saturday' => 6
            ];
            
            $preferredDayNumbers = array_map(fn($day) => $dayMap[strtolower($day)] ?? null, $validated['preferred_days']);
            $preferredDayNumbers = array_filter($preferredDayNumbers, fn($day) => $day !== null);
            
            $slots = array_filter($slots, function ($slot) use ($preferredDayNumbers, $timezone) {
                $slotDay = Carbon::parse($slot['start'], $timezone)->dayOfWeek;
                return in_array($slotDay, $preferredDayNumbers);
            });
        }

        $maxSuggestions = $validated['max_suggestions'] ?? 20;
        $slots = array_slice($slots, 0, $maxSuggestions);

        return response()->json([
            'success' => true,
            'data' => [
                'available_slots' => $slots,
                'search_criteria' => [
                    'participants' => $validated['participants'],
                    'duration_minutes' => $validated['duration_minutes'],
                    'buffer_minutes' => $validated['buffer_minutes'] ?? 15,
                    'timezone' => $timezone,
                    'working_hours' => $validated['working_hours'] ?? ['09:00', '17:00'],
                    'preferred_days' => $validated['preferred_days'] ?? [],
                ],
                'total_slots_found' => count($slots),
            ],
        ]);
    }

    /**
     * Suggest optimal meeting times using the scheduling assistant.
     */
    public function suggestMeetingTimes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'participants' => 'required|array|min:1|max:20',
            'participants.*' => 'email',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'buffer_minutes' => 'nullable|integer|min:0|max:60',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'timezone' => 'nullable|string|in:' . implode(',', timezone_identifiers_list()),
            'working_hours' => 'nullable|array|size:2',
            'working_hours.*' => 'date_format:H:i',
            'preferred_days' => 'nullable|array',
            'preferred_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'max_suggestions' => 'nullable|integer|min:1|max:50',
        ]);

        // Resolve participants to users
        $users = User::whereIn('email', $validated['participants'])->get();
        
        if ($users->count() !== count($validated['participants'])) {
            return response()->json([
                'success' => false,
                'message' => 'Some participants were not found in the system.',
                'missing_participants' => array_diff($validated['participants'], $users->pluck('email')->toArray()),
            ], 404);
        }

        // Check authorization for each user
        foreach ($users as $user) {
            $this->authorize('view', $user);
        }

        $validated['timezone'] = $validated['timezone'] ?? $request->user()->timezone ?? 'UTC';
        
        $suggestions = $this->schedulingService->suggestMeetingTimes($validated);

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }

    /**
     * Validate a specific meeting time.
     */
    public function validateMeetingTime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'participants' => 'required|array|min:1|max:20',
            'participants.*' => 'email',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'timezone' => 'nullable|string|in:' . implode(',', timezone_identifiers_list()),
            'buffer_minutes' => 'nullable|integer|min:0|max:60',
        ]);

        // Resolve participants to users
        $users = User::whereIn('email', $validated['participants'])->get();
        
        if ($users->count() !== count($validated['participants'])) {
            return response()->json([
                'success' => false,
                'message' => 'Some participants were not found in the system.',
                'missing_participants' => array_diff($validated['participants'], $users->pluck('email')->toArray()),
            ], 404);
        }

        // Check authorization for each user
        foreach ($users as $user) {
            $this->authorize('view', $user);
        }

        $validated['timezone'] = $validated['timezone'] ?? $request->user()->timezone ?? 'UTC';
        
        $validation = $this->schedulingService->validateMeetingTime($validated);

        return response()->json([
            'success' => true,
            'data' => $validation,
        ]);
    }

    /**
     * Create an optimal meeting.
     */
    public function createOptimalMeeting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'participants' => 'required|array|min:1|max:20',
            'participants.*' => 'email',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url|max:500',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'buffer_minutes' => 'nullable|integer|min:0|max:60',
            'preferred_start_time' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'timezone' => 'nullable|string|in:' . implode(',', timezone_identifiers_list()),
            'working_hours' => 'nullable|array|size:2',
            'working_hours.*' => 'date_format:H:i',
            'preferred_days' => 'nullable|array',
            'preferred_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'is_private' => 'nullable|boolean',
            'reminders' => 'nullable|array|max:10',
            'reminders.*.method' => 'required_with:reminders|in:email,popup,sms',
            'reminders.*.minutes_before' => 'required_with:reminders|integer|min:0|max:43200',
        ]);

        // Resolve participants to users
        $users = User::whereIn('email', $validated['participants'])->get();
        
        if ($users->count() !== count($validated['participants'])) {
            return response()->json([
                'success' => false,
                'message' => 'Some participants were not found in the system.',
                'missing_participants' => array_diff($validated['participants'], $users->pluck('email')->toArray()),
            ], 404);
        }

        // Check authorization - user must be able to create events
        $this->authorize('create', \App\Models\Event::class);

        $validated['timezone'] = $validated['timezone'] ?? $request->user()->timezone ?? 'UTC';
        
        try {
            $result = $this->schedulingService->createOptimalMeeting($validated);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Meeting created successfully at optimal time.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get meeting pattern analysis for a user.
     */
    public function analyzeMeetingPatterns(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $validated = $request->validate([
            'days' => 'nullable|integer|min:7|max:365',
        ]);

        $days = $validated['days'] ?? 30;
        $analysis = $this->schedulingService->analyzeMeetingPatterns($user, $days);

        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    /**
     * Check slot availability for a specific time.
     */
    public function checkSlotAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'participants' => 'required|array|min:1|max:20',
            'participants.*' => 'email',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'buffer_minutes' => 'nullable|integer|min:0|max:60',
        ]);

        // Resolve participants to users
        $users = User::whereIn('email', $validated['participants'])->get();
        
        if ($users->count() !== count($validated['participants'])) {
            return response()->json([
                'success' => false,
                'message' => 'Some participants were not found in the system.',
                'missing_participants' => array_diff($validated['participants'], $users->pluck('email')->toArray()),
            ], 404);
        }

        // Check authorization for each user
        foreach ($users as $user) {
            $this->authorize('view', $user);
        }

        $startTime = Carbon::parse($validated['start_time']);
        $endTime = Carbon::parse($validated['end_time']);
        $bufferMinutes = $validated['buffer_minutes'] ?? 0;

        $availability = $this->freeBusyService->isSlotAvailable($users, $startTime, $endTime, $bufferMinutes);

        return response()->json([
            'success' => true,
            'data' => $availability,
        ]);
    }

    /**
     * Get next available meeting slot.
     */
    public function getNextAvailableSlot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'participants' => 'required|array|min:1|max:20',
            'participants.*' => 'email',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'buffer_minutes' => 'nullable|integer|min:0|max:60',
            'timezone' => 'nullable|string|in:' . implode(',', timezone_identifiers_list()),
            'working_hours' => 'nullable|array|size:2',
            'working_hours.*' => 'date_format:H:i',
            'preferred_days' => 'nullable|array',
            'preferred_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        // Resolve participants to users
        $users = User::whereIn('email', $validated['participants'])->get();
        
        if ($users->count() !== count($validated['participants'])) {
            return response()->json([
                'success' => false,
                'message' => 'Some participants were not found in the system.',
                'missing_participants' => array_diff($validated['participants'], $users->pluck('email')->toArray()),
            ], 404);
        }

        // Check authorization for each user
        foreach ($users as $user) {
            $this->authorize('view', $user);
        }

        $validated['timezone'] = $validated['timezone'] ?? $request->user()->timezone ?? 'UTC';
        
        $nextSlot = $this->schedulingService->findNextAvailableSlot($validated);

        if (!$nextSlot) {
            return response()->json([
                'success' => false,
                'message' => 'No available slots found for the specified criteria.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'next_available_slot' => $nextSlot,
                'search_criteria' => [
                    'participants' => $validated['participants'],
                    'duration_minutes' => $validated['duration_minutes'],
                    'buffer_minutes' => $validated['buffer_minutes'] ?? 15,
                    'timezone' => $validated['timezone'],
                    'working_hours' => $validated['working_hours'] ?? ['09:00', '17:00'],
                    'preferred_days' => $validated['preferred_days'] ?? [],
                ],
            ],
        ]);
    }
}
