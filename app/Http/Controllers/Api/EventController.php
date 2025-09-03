<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Calendar;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EventController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Event::class);

        $organizationId = $request->get('current_organization_id');
        
        $validated = $request->validate([
            'calendar_id' => 'nullable|uuid|exists:calendars,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'include_private' => 'boolean',
        ]);

        $query = Event::whereHas('calendar', function ($query) use ($organizationId) {
            $query->where('organization_id', $organizationId);
        });

        if (isset($validated['calendar_id'])) {
            $query->where('calendar_id', $validated['calendar_id']);
        }

        if (isset($validated['start_date']) && isset($validated['end_date'])) {
            $query->whereBetween('start_at', [$validated['start_date'], $validated['end_date']]);
        }

        if (!($validated['include_private'] ?? false)) {
            $query->where('is_private', false);
        }

        $events = $query->with(['calendar', 'participants', 'reminders'])
            ->paginate(15);

        return response()->json($events);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Event::class);

        $validated = $request->validate([
            'calendar_id' => 'required|uuid|exists:calendars,id',
            'title' => 'required|string|max:255',
            'description_md' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url|max:255',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after:start_at',
            'all_day' => 'boolean',
            'timezone' => 'nullable|string|max:64',
            'rrule' => 'nullable|string',
            'exdates' => 'nullable|array',
            'exdates.*' => 'date',
            'is_private' => 'boolean',
            'participants' => 'nullable|array',
            'participants.*.email' => 'required_with:participants|email',
            'participants.*.name' => 'nullable|string|max:255',
            'participants.*.role' => 'nullable|in:required,optional,resource',
            'reminders' => 'nullable|array',
            'reminders.*.method' => 'required_with:reminders|in:email,popup,sms',
            'reminders.*.minutes_before' => 'required_with:reminders|integer|min:0',
        ]);

        // Verify calendar belongs to current organization
        $calendar = Calendar::where('id', $validated['calendar_id'])
            ->where('organization_id', $request->get('current_organization_id'))
            ->firstOrFail();

        // Set timezone if not provided
        if (!isset($validated['timezone'])) {
            $validated['timezone'] = Auth::user()->timezone ?? 'Asia/Jakarta';
        }

        // Handle all-day events
        if ($validated['all_day'] ?? false) {
            $validated['start_at'] = Carbon::parse($validated['start_at'])->startOfDay();
            $validated['end_at'] = $validated['end_at'] 
                ? Carbon::parse($validated['end_at'])->endOfDay()
                : Carbon::parse($validated['start_at'])->endOfDay();
        }

        $event = Event::create($validated);

        // Add participants if provided
        if (isset($validated['participants'])) {
            foreach ($validated['participants'] as $participant) {
                $event->participants()->create([
                    'email' => $participant['email'],
                    'name' => $participant['name'] ?? null,
                    'role' => $participant['role'] ?? 'required',
                    'status' => 'pending',
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

        return response()->json($event, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event): JsonResponse
    {
        if ($event->is_private) {
            $this->authorize('viewPrivate', $event);
        } else {
            $this->authorize('view', $event);
        }

        $event->load(['calendar', 'participants', 'reminders']);

        return response()->json($event);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description_md' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url|max:255',
            'start_at' => 'sometimes|required|date',
            'end_at' => 'nullable|date|after:start_at',
            'all_day' => 'boolean',
            'timezone' => 'nullable|string|max:64',
            'rrule' => 'nullable|string',
            'exdates' => 'nullable|array',
            'exdates.*' => 'date',
            'is_private' => 'boolean',
        ]);

        // Handle all-day events
        if (isset($validated['all_day']) && $validated['all_day']) {
            if (isset($validated['start_at'])) {
                $validated['start_at'] = Carbon::parse($validated['start_at'])->startOfDay();
            }
            if (isset($validated['end_at'])) {
                $validated['end_at'] = Carbon::parse($validated['end_at'])->endOfDay();
            }
        }

        $event->update($validated);
        $event->load(['calendar', 'participants', 'reminders']);

        return response()->json($event);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }

    /**
     * Manage event participants.
     */
    public function updateParticipants(Request $request, Event $event): JsonResponse
    {
        $this->authorize('manageParticipants', $event);

        $validated = $request->validate([
            'participants' => 'required|array',
            'participants.*.email' => 'required|email',
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
                'status' => $participant['status'] ?? 'pending',
            ]);
        }

        $event->load(['participants']);

        return response()->json($event);
    }
}
