<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Calendar;
use App\Models\Organization;
use App\Models\User;
use App\Models\Conversation;
use App\Services\WeatherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected $weatherService;

    public function __construct(WeatherService $weatherService)
    {
        $this->weatherService = $weatherService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        // Get user's calendars and events using correct field name
        $userCalendars = Calendar::where('owner_user_id', $user->id)->pluck('id');
        $userEvents = Event::whereIn('calendar_id', $userCalendars)
            ->with(['calendar', 'participants'])
            ->get();

        // Calculate statistics
        $todayEvents = $userEvents->filter(function ($event) use ($today) {
            return Carbon::parse($event->start_at)->isSameDay($today);
        })->count();

        $weekEvents = $userEvents->filter(function ($event) use ($weekStart, $weekEnd) {
            $eventDate = Carbon::parse($event->start_at);
            return $eventDate->between($weekStart, $weekEnd);
        })->count();

        $monthEvents = $userEvents->filter(function ($event) use ($monthStart, $monthEnd) {
            $eventDate = Carbon::parse($event->start_at);
            return $eventDate->between($monthStart, $monthEnd);
        })->count();

        $upcomingEvents = $userEvents->filter(function ($event) use ($today) {
            return Carbon::parse($event->start_at)->isAfter($today);
        })->count();

        $completedEvents = $userEvents->filter(function ($event) use ($today) {
            return Carbon::parse($event->end_at)->isBefore($today);
        })->count();

        // Calculate productivity (percentage of completed events)
        $totalEvents = $userEvents->count();
        $productivity = $totalEvents > 0 ? round(($completedEvents / $totalEvents) * 100) : 0;

        // Get user timezone, default to Asia/Jakarta if not set
        $userTimezone = $user->timezone ?? 'Asia/Jakarta';

        // Get upcoming events for sidebar
        $upcomingEventsList = $userEvents
            ->filter(function ($event) use ($today) {
                return Carbon::parse($event->start_at)->isAfter($today);
            })
            ->sortBy('start_at')
            ->take(5)
            ->map(function ($event) use ($userTimezone) {
                // Convert UTC time to user's local timezone
                $localStartTime = Carbon::parse($event->start_at)->setTimezone($userTimezone);
                
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'time' => $localStartTime->format('h:i A'),
                    'date' => $localStartTime->format('M d'),
                    'location' => $event->location ?? 'No location',
                    'color' => $event->calendar->color ?? '#3B82F6'
                ];
            })
            ->values();

        // Get weather data based on user location
        $weatherData = null;
        if ($user->location) {
            $weatherData = $this->weatherService->getWeatherByLocation($user->location);
        } else {
            // Default to Jakarta if no location set
            $weatherData = $this->weatherService->getWeatherByLocation('Jakarta, Indonesia');
        }

        // Get user's calendars count using correct field name
        $calendarsCount = Calendar::where('owner_user_id', $user->id)->count();

        // Get chat data for ChatManager - simplified approach
        $conversations = collect(); // Empty for now, will be loaded via API
        
        // Get all users for chat (online and offline) - using only existing columns
        $allUsers = User::where('id', '!=', $user->id)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get()
            ->map(function($chatUser) {
                return [
                    'id' => $chatUser->id,
                    'name' => $chatUser->name,
                    'email' => $chatUser->email,
                    'avatar' => '/default-avatar.png',
                    'online_status' => 'offline',
                    'status_message' => null,
                    'last_seen_at' => null,
                    'is_online' => false,
                ];
            });

        return Inertia::render('DashboardWithRealTimeChat', [
            'stats' => [
                'todayEvents' => $todayEvents,
                'weekEvents' => $weekEvents,
                'monthEvents' => $monthEvents,
                'upcomingEvents' => $upcomingEvents,
                'completedEvents' => $completedEvents,
                'productivity' => $productivity,
                'calendarsCount' => $calendarsCount
            ],
            'upcomingEvents' => $upcomingEventsList,
            'weather' => $weatherData,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'location' => $user->location ?? 'Jakarta, Indonesia'
            ],
            'initialConversations' => $conversations,
            'initialOnlineUsers' => $allUsers
        ]);
    }

    public function getEvents(Request $request)
    {
        $user = Auth::user();
        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);

        // Get user timezone, default to Asia/Jakarta if not set
        $userTimezone = $user->timezone ?? 'Asia/Jakarta';

        // Use correct field name for calendar filtering
        $userCalendars = Calendar::where('owner_user_id', $user->id)->pluck('id');
        $events = Event::whereIn('calendar_id', $userCalendars)
            ->whereYear('start_at', $year)
            ->whereMonth('start_at', $month)
            ->with(['calendar'])
            ->get()
            ->map(function ($event) use ($userTimezone) {
                // Convert UTC time to user's local timezone
                $localStartTime = Carbon::parse($event->start_at)->setTimezone($userTimezone);
                $localEndTime = Carbon::parse($event->end_at)->setTimezone($userTimezone);
                
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description_md,
                    'start_date' => $localStartTime->toISOString(),
                    'end_date' => $localEndTime->toISOString(),
                    'date' => $localStartTime->format('Y-m-d'),
                    'time' => $localStartTime->format('H:i'),
                    'color' => $event->calendar->color ?? '#3B82F6',
                    'location' => $event->location,
                    'calendar_name' => $event->calendar->name ?? 'Default'
                ];
            });

        return response()->json($events);
    }

    /**
     * Create a simple event from dashboard
     */
    public function createEvent(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $user = Auth::user();
                
                // Get or create default calendar for user with proper field names
                $calendar = Calendar::where('owner_user_id', $user->id)->first();
                
                if (!$calendar) {
                    // Get or create default organization for user
                    $organization = Organization::whereHas('users', function($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })->first();
                    
                    if (!$organization) {
                        $organization = Organization::create([
                            'name' => $user->name . "'s Organization",
                        ]);
                        
                        // Attach user to organization using DB insert
                        DB::table('organization_user')->insert([
                            'organization_id' => $organization->id,
                            'user_id' => $user->id,
                            'role' => 'owner',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    
                    // Create calendar with correct field names
                    $calendar = Calendar::create([
                        'organization_id' => $organization->id,
                        'owner_user_id' => $user->id,
                        'name' => 'Personal',
                        'description' => 'Personal calendar',
                        'color' => '#3B82F6',
                        'is_default' => true,
                        'is_public' => false,
                    ]);
                }

                // Get user timezone, default to Asia/Jakarta (WIB)
                $userTimezone = $user->timezone ?? 'Asia/Jakarta';
                
                // Parse dates as local time in user's timezone, then convert to UTC for storage
                $startDateTime = Carbon::parse($validated['start_date'], $userTimezone)->utc();
                $endDateTime = Carbon::parse($validated['end_date'], $userTimezone)->utc();

                // Create the event
                $event = Event::create([
                    'calendar_id' => $calendar->id,
                    'title' => $validated['title'],
                    'description_md' => $validated['description'],
                    'start_at' => $startDateTime,
                    'end_at' => $endDateTime,
                    'location' => null,
                    'all_day' => false,
                    'timezone' => $userTimezone,
                    'is_private' => false,
                ]);

                Log::info('Event created successfully', [
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'calendar_id' => $calendar->id,
                    'title' => $event->title
                ]);

                return response()->json([
                    'message' => 'Event created successfully',
                    'event' => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'start_at' => $event->start_at,
                        'end_at' => $event->end_at,
                        'calendar_id' => $calendar->id,
                        'calendar_name' => $calendar->name,
                    ]
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error creating event from dashboard: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $validated,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to create event. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update an event
     */
    public function updateEvent(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            return DB::transaction(function () use ($validated, $id) {
                $user = Auth::user();
                
                // Find event and verify ownership
                $event = Event::whereHas('calendar', function($query) use ($user) {
                    $query->where('owner_user_id', $user->id);
                })->findOrFail($id);

                // Get user timezone, default to Asia/Jakarta (WIB)
                $userTimezone = $user->timezone ?? 'Asia/Jakarta';
                
                // Parse dates as local time in user's timezone, then convert to UTC for storage
                $startDateTime = Carbon::parse($validated['start_date'], $userTimezone)->utc();
                $endDateTime = Carbon::parse($validated['end_date'], $userTimezone)->utc();

                // Update the event
                $event->update([
                    'title' => $validated['title'],
                    'description_md' => $validated['description'],
                    'start_at' => $startDateTime,
                    'end_at' => $endDateTime,
                ]);

                Log::info('Event updated successfully', [
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'title' => $event->title
                ]);

                return response()->json([
                    'message' => 'Event updated successfully',
                    'event' => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'start_at' => $event->start_at,
                        'end_at' => $event->end_at,
                        'calendar_id' => $event->calendar_id,
                        'calendar_name' => $event->calendar->name,
                    ]
                ]);
            });

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Event not found or you do not have permission to edit this event.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating event: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'event_id' => $id,
                'request_data' => $validated,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to update event. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete an event
     */
    public function deleteEvent($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $user = Auth::user();
                
                // Find event and verify ownership
                $event = Event::whereHas('calendar', function($query) use ($user) {
                    $query->where('owner_user_id', $user->id);
                })->findOrFail($id);

                $eventTitle = $event->title;
                
                // Delete the event
                $event->delete();

                Log::info('Event deleted successfully', [
                    'event_id' => $id,
                    'user_id' => $user->id,
                    'title' => $eventTitle
                ]);

                return response()->json([
                    'message' => 'Event deleted successfully',
                    'deleted_event' => [
                        'id' => $id,
                        'title' => $eventTitle,
                    ]
                ]);
            });

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Event not found or you do not have permission to delete this event.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting event: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'event_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to delete event. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
