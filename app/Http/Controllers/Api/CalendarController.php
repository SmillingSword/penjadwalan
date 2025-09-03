<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCalendarRequest;
use App\Http\Requests\UpdateCalendarRequest;
use App\Http\Resources\CalendarResource;
use App\Http\Resources\EventResource;
use App\Models\Calendar;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Carbon\Carbon;

class CalendarController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Calendar::class);

        $organizationId = $request->get('current_organization_id');
        
        $calendars = Calendar::where('organization_id', $organizationId)
            ->with(['owner', 'organization'])
            ->paginate(15);

        return CalendarResource::collection($calendars);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCalendarRequest $request): CalendarResource
    {
        $this->authorize('create', Calendar::class);

        $calendar = Calendar::create($request->getValidatedData());
        $calendar->load(['owner', 'organization']);

        return new CalendarResource($calendar);
    }

    /**
     * Display the specified resource.
     */
    public function show(Calendar $calendar): CalendarResource
    {
        $this->authorize('view', $calendar);

        $calendar->load(['owner', 'organization', 'events' => function ($query) {
            $query->with(['participants', 'reminders']);
        }]);

        return new CalendarResource($calendar);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCalendarRequest $request, Calendar $calendar): CalendarResource
    {
        $this->authorize('update', $calendar);

        $calendar->update($request->validated());
        $calendar->load(['owner', 'organization']);

        return new CalendarResource($calendar);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Calendar $calendar): JsonResponse
    {
        $this->authorize('delete', $calendar);

        $calendar->delete();

        return response()->json([
            'message' => 'Calendar deleted successfully'
        ]);
    }

    /**
     * Get events for a specific calendar within a date range.
     */
    public function events(Request $request, Calendar $calendar): AnonymousResourceCollection
    {
        $this->authorize('view', $calendar);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'timezone' => 'nullable|string|in:' . implode(',', timezone_identifiers_list()),
        ]);

        // Convert dates to UTC for database query
        $timezone = $validated['timezone'] ?? $request->user()->timezone ?? 'Asia/Jakarta';
        $startDate = Carbon::parse($validated['start_date'], $timezone)->utc();
        $endDate = Carbon::parse($validated['end_date'], $timezone)->utc();

        $events = $calendar->events()
            ->whereBetween('start_at', [$startDate, $endDate])
            ->with(['participants', 'reminders', 'calendar'])
            ->get();

        return EventResource::collection($events);
    }
}
