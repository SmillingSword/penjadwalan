<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventParticipantResource;
use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SearchController extends Controller
{
    use AuthorizesRequests;

    /**
     * Search across events and participants.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2|max:255',
            'type' => 'nullable|in:events,participants,all',
            'limit' => 'nullable|integer|min:1|max:100',
            'calendar_id' => 'nullable|uuid|exists:calendars,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $query = $validated['query'];
        $type = $validated['type'] ?? 'all';
        $limit = $validated['limit'] ?? 20;
        $organizationId = $request->get('current_organization_id');

        $results = [];

        // Search events
        if ($type === 'events' || $type === 'all') {
            $eventQuery = Event::search($query)
                ->where('calendar.organization_id', $organizationId);

            // Apply filters
            if (isset($validated['calendar_id'])) {
                $eventQuery->where('calendar_id', $validated['calendar_id']);
            }

            if (isset($validated['start_date']) && isset($validated['end_date'])) {
                $eventQuery->where('start_at', '>=', strtotime($validated['start_date']))
                          ->where('end_at', '<=', strtotime($validated['end_date']));
            }

            $events = $eventQuery->take($limit)->get();
            
            // Filter events based on organization access
            $events = $events->filter(function ($event) use ($organizationId) {
                return $event->calendar && $event->calendar->organization_id === $organizationId;
            });

            $results['events'] = EventResource::collection($events);
        }

        // Search participants
        if ($type === 'participants' || $type === 'all') {
            $participantQuery = EventParticipant::search($query);

            $participants = $participantQuery->take($limit)->get();
            
            // Filter participants based on organization access
            $participants = $participants->filter(function ($participant) use ($organizationId) {
                return $participant->event && 
                       $participant->event->calendar && 
                       $participant->event->calendar->organization_id === $organizationId;
            });

            $results['participants'] = EventParticipantResource::collection($participants);
        }

        return response()->json([
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'total' => collect($results)->flatten()->count(),
        ]);
    }

    /**
     * Search events only.
     */
    public function searchEvents(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Event::class);

        $validated = $request->validate([
            'query' => 'required|string|min:2|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
            'calendar_id' => 'nullable|uuid|exists:calendars,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'include_private' => 'boolean',
        ]);

        $query = $validated['query'];
        $limit = $validated['limit'] ?? 20;
        $organizationId = $request->get('current_organization_id');

        $eventQuery = Event::search($query);

        // Apply filters
        if (isset($validated['calendar_id'])) {
            $eventQuery->where('calendar_id', $validated['calendar_id']);
        }

        if (!($validated['include_private'] ?? false)) {
            $eventQuery->where('is_private', false);
        }

        if (isset($validated['start_date']) && isset($validated['end_date'])) {
            $eventQuery->where('start_at', '>=', strtotime($validated['start_date']))
                      ->where('end_at', '<=', strtotime($validated['end_date']));
        }

        $events = $eventQuery->take($limit)->get();
        
        // Filter events based on organization access
        $events = $events->filter(function ($event) use ($organizationId) {
            return $event->calendar && $event->calendar->organization_id === $organizationId;
        });

        return EventResource::collection($events);
    }

    /**
     * Search participants only.
     */
    public function searchParticipants(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
            'event_id' => 'nullable|uuid|exists:events,id',
            'status' => 'nullable|in:invited,accepted,declined,tentative',
            'role' => 'nullable|in:required,optional',
        ]);

        $query = $validated['query'];
        $limit = $validated['limit'] ?? 20;
        $organizationId = $request->get('current_organization_id');

        $participantQuery = EventParticipant::search($query);

        // Apply filters
        if (isset($validated['event_id'])) {
            $participantQuery->where('event_id', $validated['event_id']);
        }

        if (isset($validated['status'])) {
            $participantQuery->where('status', $validated['status']);
        }

        if (isset($validated['role'])) {
            $participantQuery->where('role', $validated['role']);
        }

        $participants = $participantQuery->take($limit)->get();
        
        // Filter participants based on organization access
        $participants = $participants->filter(function ($participant) use ($organizationId) {
            return $participant->event && 
                   $participant->event->calendar && 
                   $participant->event->calendar->organization_id === $organizationId;
        });

        return EventParticipantResource::collection($participants);
    }

    /**
     * Get search suggestions.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:1|max:255',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $query = $validated['query'];
        $limit = $validated['limit'] ?? 10;
        $organizationId = $request->get('current_organization_id');

        // Get event title suggestions
        $eventSuggestions = Event::search($query)
            ->take($limit)
            ->get()
            ->filter(function ($event) use ($organizationId) {
                return $event->calendar && $event->calendar->organization_id === $organizationId;
            })
            ->pluck('title')
            ->unique()
            ->take($limit);

        // Get location suggestions
        $locationSuggestions = Event::search($query)
            ->take($limit)
            ->get()
            ->filter(function ($event) use ($organizationId) {
                return $event->calendar && 
                       $event->calendar->organization_id === $organizationId &&
                       !empty($event->location);
            })
            ->pluck('location')
            ->unique()
            ->take($limit);

        return response()->json([
            'query' => $query,
            'suggestions' => [
                'titles' => $eventSuggestions->values(),
                'locations' => $locationSuggestions->values(),
            ],
        ]);
    }
}
