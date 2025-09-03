<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CalendarController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Calendar::class);

        $organizationId = $request->get('current_organization_id');
        
        $calendars = Calendar::where('organization_id', $organizationId)
            ->with(['owner', 'organization'])
            ->paginate(15);

        return response()->json($calendars);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Calendar::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_default' => 'boolean',
            'is_public' => 'boolean',
        ]);

        $calendar = Calendar::create([
            'organization_id' => $request->get('current_organization_id'),
            'owner_user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#3B82F6',
            'is_default' => $validated['is_default'] ?? false,
            'is_public' => $validated['is_public'] ?? false,
        ]);

        $calendar->load(['owner', 'organization']);

        return response()->json($calendar, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Calendar $calendar): JsonResponse
    {
        $this->authorize('view', $calendar);

        $calendar->load(['owner', 'organization', 'events' => function ($query) {
            $query->with(['participants', 'reminders']);
        }]);

        return response()->json($calendar);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Calendar $calendar): JsonResponse
    {
        $this->authorize('update', $calendar);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_default' => 'boolean',
            'is_public' => 'boolean',
        ]);

        $calendar->update($validated);
        $calendar->load(['owner', 'organization']);

        return response()->json($calendar);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Calendar $calendar): JsonResponse
    {
        $this->authorize('delete', $calendar);

        $calendar->delete();

        return response()->json(['message' => 'Calendar deleted successfully']);
    }

    /**
     * Get events for a specific calendar within a date range.
     */
    public function events(Request $request, Calendar $calendar): JsonResponse
    {
        $this->authorize('view', $calendar);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $events = $calendar->events()
            ->whereBetween('start_at', [$validated['start_date'], $validated['end_date']])
            ->with(['participants', 'reminders'])
            ->get();

        return response()->json($events);
    }
}
