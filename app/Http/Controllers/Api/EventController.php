<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Calendar;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EventController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Event::class);

        $organizationId = $request->get('current_organization_id');
        
        $validated = $request->validate([
            'calendar_id' => 'nullable|uuid|exists:calendars,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'include_private' => 'boolean',
            'timezone' => 'nullable|string|in:' . implode(',', timezone_identifiers_list()),
            'expand' => 'boolean', // New parameter for expanding recurring events
        ]);

        $query = Event::whereHas('calendar', function ($query) use ($organizationId) {
            $query->where('organization_id', $organizationId);
        });

        if (isset($validated['calendar_id'])) {
            $query->where('calendar_id', $validated['calendar_id']);
        }

        if (!($validated['include_private'] ?? false)) {
            $query->where('is_private', false);
        }

        // Handle date range and expansion
        if (isset($validated['start_date']) && isset($validated['end_date'])) {
            $timezone = $validated['timezone'] ?? $request->user()->timezone ?? 'Asia/Jakarta';
            $startDate = Carbon::parse($validated['start_date'], $timezone)->utc();
            $endDate = Carbon::parse($validated['end_date'], $timezone)->utc();
            
            if ($validated['expand'] ?? false) {
                // Get all events (recurring and non-recurring) and expand recurring ones
                $events = $this->getExpandedEvents($query, $startDate, $endDate, $timezone);
                return EventResource::collection($events);
            } else {
                // Regular query without expansion
                $query->whereBetween('start_at', [$startDate, $endDate]);
            }
        }

        $events = $query->with(['calendar', 'participants', 'reminders'])
            ->paginate(15);

        return EventResource::collection($events);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEventRequest $request): EventResource
    {
        $this->authorize('create', Event::class);

        $validated = $request->validated();

        // Verify calendar belongs to current organization
        $calendar = Calendar::where('id', $validated['calendar_id'])
            ->where('organization_id', $request->get('current_organization_id'))
            ->firstOrFail();

        // Convert datetime to UTC for storage
        $timezone = $validated['timezone'];
        
        if ($validated['all_day'] ?? false) {
            // For all-day events, store as start/end of day in the specified timezone, then convert to UTC
            $validated['start_at'] = Carbon::parse($validated['start_at'], $timezone)->startOfDay()->utc();
            $validated['end_at'] = $validated['end_at'] 
                ? Carbon::parse($validated['end_at'], $timezone)->endOfDay()->utc()
                : Carbon::parse($validated['start_at'], $timezone)->endOfDay()->utc();
        } else {
            // Convert to UTC for storage
            $validated['start_at'] = Carbon::parse($validated['start_at'], $timezone)->utc();
            if ($validated['end_at']) {
                $validated['end_at'] = Carbon::parse($validated['end_at'], $timezone)->utc();
            }
        }

        $event = Event::create($validated);

        // Add participants if provided
        if (isset($validated['participants'])) {
            foreach ($validated['participants'] as $participant) {
                $event->participants()->create([
                    'email' => $participant['email'],
                    'name' => $participant['name'] ?? null,
                    'role' => $participant['role'] ?? 'required',
                    'status' => 'invited',
                ]);
            }
        }

        // Add reminders if provided
        if (isset($validated['reminders'])) {
            foreach ($validated['reminders'] as $reminder) {
                $event->reminders()->create($reminder);
            }
        }

        $event->load(['calendar', 'participants', 'reminders']);

        return new EventResource($event);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event): EventResource
    {
        if ($event->is_private) {
            $this->authorize('viewPrivate', $event);
        } else {
            $this->authorize('view', $event);
        }

        $event->load(['calendar', 'participants', 'reminders']);

        return new EventResource($event);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        $this->authorize('update', $event);

        $validated = $request->validated();

        // Handle timezone conversion for datetime fields
        if (isset($validated['timezone'])) {
            $timezone = $validated['timezone'];
            
            if (isset($validated['all_day']) && $validated['all_day']) {
                if (isset($validated['start_at'])) {
                    $validated['start_at'] = Carbon::parse($validated['start_at'], $timezone)->startOfDay()->utc();
                }
                if (isset($validated['end_at'])) {
                    $validated['end_at'] = Carbon::parse($validated['end_at'], $timezone)->endOfDay()->utc();
                }
            } else {
                if (isset($validated['start_at'])) {
                    $validated['start_at'] = Carbon::parse($validated['start_at'], $timezone)->utc();
                }
                if (isset($validated['end_at'])) {
                    $validated['end_at'] = Carbon::parse($validated['end_at'], $timezone)->utc();
                }
            }
        }

        $event->update($validated);
        $event->load(['calendar', 'participants', 'reminders']);

        return new EventResource($event);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully'
        ]);
    }

    /**
     * Manage event participants.
     */
    public function updateParticipants(Request $request, Event $event): EventResource
    {
        $this->authorize('manageParticipants', $event);

        $validated = $request->validate([
            'participants' => 'required|array|max:100',
            'participants.*.email' => 'required|email|max:255',
            'participants.*.name' => 'nullable|string|max:255',
            'participants.*.role' => 'nullable|in:required,optional,resource',
            'participants.*.status' => 'nullable|in:pending,accepted,declined,tentative',
        ]);

        // Remove existing participants
        $event->participants()->delete();

        // Add new participants
        foreach ($validated['participants'] as $participant) {
            $event->participants()->create([
                'email' => $participant['email'],
                'name' => $participant['name'] ?? null,
                'role' => $participant['role'] ?? 'required',
                'status' => $participant['status'] ?? 'invited',
            ]);
        }

        $event->load(['calendar', 'participants', 'reminders']);

        return new EventResource($event);
    }

    /**
     * Get expanded events including recurring event instances.
     */
    private function getExpandedEvents($query, Carbon $startDate, Carbon $endDate, string $timezone): \Illuminate\Support\Collection
    {
        // Get all events that might have instances in the date range
        $events = $query->with(['calendar', 'participants', 'reminders'])->get();
        
        $expandedEvents = collect();
        
        foreach ($events as $event) {
            if ($event->isRecurring()) {
                // Expand recurring event instances
                $instances = $event->expandRecurrence($startDate, $endDate);
                
                foreach ($instances as $instance) {
                    // Create a virtual event instance
                    $virtualEvent = $event->replicate();
                    $virtualEvent->start_at = $instance['start_at'];
                    $virtualEvent->end_at = $instance['end_at'];
                    $virtualEvent->setAttribute('is_recurring_instance', true);
                    $virtualEvent->setAttribute('original_event_id', $event->id);
                    $virtualEvent->setAttribute('instance_date', $instance['original_start']->format('Y-m-d'));
                    
                    // Set relationships
                    $virtualEvent->setRelation('calendar', $event->calendar);
                    $virtualEvent->setRelation('participants', $event->participants);
                    $virtualEvent->setRelation('reminders', $event->reminders);
                    
                    $expandedEvents->push($virtualEvent);
                }
            } else {
                // Check if non-recurring event falls within the date range
                if ($event->start_at->between($startDate, $endDate) || 
                    ($event->end_at && $event->end_at->between($startDate, $endDate))) {
                    $expandedEvents->push($event);
                }
            }
        }
        
        // Sort by start date
        return $expandedEvents->sortBy('start_at');
    }
}
