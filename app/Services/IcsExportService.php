<?php

namespace App\Services;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Eluceo\iCal\Domain\Entity\Calendar as ICalCalendar;
use Eluceo\iCal\Domain\Entity\Event as ICalEvent;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Illuminate\Support\Collection;

class IcsExportService
{
    /**
     * Export a calendar to ICS format.
     */
    public function exportCalendar(Calendar $calendar, array $options = []): string
    {
        $options = array_merge([
            'include_private' => false,
            'date_range_start' => null,
            'date_range_end' => null,
            'timezone' => 'UTC',
            'expand_recurring' => false,
            'max_events' => 1000,
        ], $options);

        // Get events from the calendar
        $events = $this->getCalendarEvents($calendar, $options);

        // Create iCal calendar
        $iCalCalendar = new ICalCalendar();
        $iCalCalendar->setProductIdentifier('-//Laravel Calendar App//Calendar Export//EN');
        
        // Add calendar properties
        if ($calendar->name) {
            $iCalCalendar->setName($calendar->name);
        }
        
        if ($calendar->description) {
            $iCalCalendar->setDescription($calendar->description);
        }

        // Convert events to iCal events
        foreach ($events as $event) {
            $iCalEvent = $this->convertEventToICal($event, $options);
            if ($iCalEvent) {
                $iCalCalendar->addEvent($iCalEvent);
            }
        }

        // Generate ICS content
        $calendarFactory = new CalendarFactory();
        return $calendarFactory->createCalendar($iCalCalendar);
    }

    /**
     * Export multiple calendars to ICS format.
     */
    public function exportMultipleCalendars(Collection $calendars, array $options = []): string
    {
        $options = array_merge([
            'include_private' => false,
            'date_range_start' => null,
            'date_range_end' => null,
            'timezone' => 'UTC',
            'expand_recurring' => false,
            'max_events' => 1000,
            'calendar_name' => 'Combined Calendar Export',
        ], $options);

        // Create combined iCal calendar
        $iCalCalendar = new ICalCalendar();
        $iCalCalendar->setProductIdentifier('-//Laravel Calendar App//Combined Calendar Export//EN');
        $iCalCalendar->setName($options['calendar_name']);

        $totalEvents = 0;

        foreach ($calendars as $calendar) {
            if ($totalEvents >= $options['max_events']) {
                break;
            }

            $events = $this->getCalendarEvents($calendar, $options);
            $remainingSlots = $options['max_events'] - $totalEvents;
            
            if ($remainingSlots < count($events)) {
                $events = $events->take($remainingSlots);
            }

            foreach ($events as $event) {
                $iCalEvent = $this->convertEventToICal($event, $options);
                if ($iCalEvent) {
                    $iCalCalendar->addEvent($iCalEvent);
                    $totalEvents++;
                }
            }
        }

        // Generate ICS content
        $calendarFactory = new CalendarFactory();
        return $calendarFactory->createCalendar($iCalCalendar);
    }

    /**
     * Export a single event to ICS format.
     */
    public function exportEvent(Event $event, array $options = []): string
    {
        $options = array_merge([
            'timezone' => 'UTC',
            'include_private' => true,
        ], $options);

        // Create iCal calendar with single event
        $iCalCalendar = new ICalCalendar();
        $iCalCalendar->setProductIdentifier('-//Laravel Calendar App//Event Export//EN');
        $iCalCalendar->setName($event->title);

        $iCalEvent = $this->convertEventToICal($event, $options);
        if ($iCalEvent) {
            $iCalCalendar->addEvent($iCalEvent);
        }

        // Generate ICS content
        $calendarFactory = new CalendarFactory();
        return $calendarFactory->createCalendar($iCalCalendar);
    }

    /**
     * Generate a public ICS feed URL for a calendar.
     */
    public function generatePublicFeedUrl(Calendar $calendar, array $options = []): string
    {
        // Generate a secure token for the calendar
        $token = $this->generateCalendarToken($calendar);
        
        $queryParams = array_merge([
            'token' => $token,
            'calendar_id' => $calendar->id,
        ], $options);

        return url('/api/public/calendar/feed.ics?' . http_build_query($queryParams));
    }

    /**
     * Export user's free/busy information to ICS format.
     */
    public function exportFreeBusy(User $user, Carbon $startDate, Carbon $endDate, array $options = []): string
    {
        $options = array_merge([
            'timezone' => 'UTC',
            'organizer_email' => $user->email,
            'organizer_name' => $user->name,
        ], $options);

        // Get user's busy periods
        $freeBusyService = app(FreeBusyService::class);
        $freeBusyData = $freeBusyService->getFreeBusyForUser($user, $startDate, $endDate, $options['timezone']);

        // Create iCal calendar for free/busy
        $iCalCalendar = new ICalCalendar();
        $iCalCalendar->setProductIdentifier('-//Laravel Calendar App//Free Busy Export//EN');
        $iCalCalendar->setName($user->name . ' - Free/Busy');

        // Add busy periods as events
        foreach ($freeBusyData['busy_periods'] as $busyPeriod) {
            $iCalEvent = new ICalEvent();
            $iCalEvent->setSummary($busyPeriod['is_private'] ? 'Busy' : $busyPeriod['title']);
            $iCalEvent->setDescription('Free/Busy Information');
            
            $startDateTime = new DateTime(Carbon::parse($busyPeriod['start']), true);
            $endDateTime = new DateTime(Carbon::parse($busyPeriod['end']), true);
            $iCalEvent->setOccurrence(new TimeSpan($startDateTime, $endDateTime));
            
            // Mark as busy/opaque
            $iCalEvent->setTransparency('OPAQUE');
            
            $iCalCalendar->addEvent($iCalEvent);
        }

        // Generate ICS content
        $calendarFactory = new CalendarFactory();
        return $calendarFactory->createCalendar($iCalCalendar);
    }

    /**
     * Create an ICS invitation for an event.
     */
    public function createEventInvitation(Event $event, string $attendeeEmail, array $options = []): string
    {
        $options = array_merge([
            'method' => 'REQUEST',
            'organizer_email' => $event->calendar->owner->email ?? 'noreply@example.com',
            'organizer_name' => $event->calendar->owner->name ?? 'Calendar System',
            'timezone' => $event->timezone ?? 'UTC',
        ], $options);

        // Create iCal calendar
        $iCalCalendar = new ICalCalendar();
        $iCalCalendar->setProductIdentifier('-//Laravel Calendar App//Event Invitation//EN');
        $iCalCalendar->setMethod($options['method']);

        // Convert event to iCal event with invitation details
        $iCalEvent = $this->convertEventToICal($event, $options);
        
        if ($iCalEvent) {
            // Add organizer
            $iCalEvent->setOrganizer(
                new \Eluceo\iCal\Domain\ValueObject\EmailAddress($options['organizer_email']),
                $options['organizer_name']
            );

            // Add attendee
            $attendee = new \Eluceo\iCal\Domain\Entity\Attendee(
                new \Eluceo\iCal\Domain\ValueObject\EmailAddress($attendeeEmail)
            );
            $attendee->setDisplayName($attendeeEmail);
            $attendee->setParticipationStatus('NEEDS-ACTION');
            $iCalEvent->addAttendee($attendee);

            $iCalCalendar->addEvent($iCalEvent);
        }

        // Generate ICS content
        $calendarFactory = new CalendarFactory();
        return $calendarFactory->createCalendar($iCalCalendar);
    }

    /**
     * Get events from a calendar based on options.
     */
    private function getCalendarEvents(Calendar $calendar, array $options): Collection
    {
        $query = Event::where('calendar_id', $calendar->id);

        // Filter by privacy
        if (!$options['include_private']) {
            $query->where('is_private', false);
        }

        // Filter by date range
        if ($options['date_range_start']) {
            $startDate = Carbon::parse($options['date_range_start']);
            $query->where('start_at', '>=', $startDate);
        }

        if ($options['date_range_end']) {
            $endDate = Carbon::parse($options['date_range_end']);
            $query->where('start_at', '<=', $endDate);
        }

        // Limit results
        $query->limit($options['max_events']);

        return $query->with(['participants', 'reminders'])->get();
    }

    /**
     * Convert a Laravel Event to iCal Event.
     */
    private function convertEventToICal(Event $event, array $options): ?ICalEvent
    {
        try {
            $iCalEvent = new ICalEvent();
            
            // Basic properties
            $iCalEvent->setSummary($event->title);
            
            if ($event->description_md) {
                $iCalEvent->setDescription(strip_tags($event->description_md));
            }
            
            if ($event->location) {
                $iCalEvent->setLocation($event->location);
            }

            // Set unique identifier
            $uid = $event->external_id ?: $event->id . '@' . config('app.url');
            $iCalEvent->setUniqueIdentifier($uid);

            // Set timestamps
            $timezone = $options['timezone'] ?? $event->timezone ?? 'UTC';
            
            if ($event->all_day) {
                // All-day event
                $startDate = $event->start_at->setTimezone($timezone)->startOfDay();
                $endDate = $event->end_at ? 
                    $event->end_at->setTimezone($timezone)->startOfDay() : 
                    $startDate->clone()->addDay();
                
                $iCalEvent->setOccurrence(new TimeSpan(
                    new DateTime($startDate, false), // false = no time
                    new DateTime($endDate, false)
                ));
            } else {
                // Timed event
                $startDateTime = new DateTime($event->start_at->setTimezone($timezone), true);
                $endDateTime = new DateTime(
                    $event->end_at ? $event->end_at->setTimezone($timezone) : $event->start_at->clone()->addHour()->setTimezone($timezone),
                    true
                );
                
                $iCalEvent->setOccurrence(new TimeSpan($startDateTime, $endDateTime));
            }

            // Set recurrence rule if present
            if ($event->rrule) {
                $iCalEvent->addRecurrenceRule($event->rrule);
            }

            // Set status
            $status = 'CONFIRMED';
            if ($event->status === 'cancelled') {
                $status = 'CANCELLED';
            } elseif ($event->status === 'tentative') {
                $status = 'TENTATIVE';
            }
            $iCalEvent->setStatus($status);

            // Set privacy
            if ($event->is_private) {
                $iCalEvent->setClassification('PRIVATE');
            }

            // Add URL if meeting link exists
            if ($event->meeting_link) {
                $iCalEvent->setUrl(new Uri($event->meeting_link));
            }

            // Set created and modified timestamps
            $iCalEvent->setCreatedAt(new DateTime($event->created_at, true));
            $iCalEvent->setModifiedAt(new DateTime($event->updated_at, true));

            return $iCalEvent;
        } catch (\Exception $e) {
            // Log error and skip this event
            \Log::error('Failed to convert event to iCal', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate a secure token for calendar access.
     */
    private function generateCalendarToken(Calendar $calendar): string
    {
        $data = [
            'calendar_id' => $calendar->id,
            'created_at' => $calendar->created_at->timestamp,
            'secret' => config('app.key'),
        ];
        
        return hash('sha256', implode('|', $data));
    }

    /**
     * Validate a calendar token.
     */
    public function validateCalendarToken(Calendar $calendar, string $token): bool
    {
        $expectedToken = $this->generateCalendarToken($calendar);
        return hash_equals($expectedToken, $token);
    }

    /**
     * Get export statistics for a calendar.
     */
    public function getExportStatistics(Calendar $calendar): array
    {
        $events = Event::where('calendar_id', $calendar->id)->get();
        
        return [
            'total_events' => $events->count(),
            'private_events' => $events->where('is_private', true)->count(),
            'recurring_events' => $events->whereNotNull('rrule')->count(),
            'all_day_events' => $events->where('all_day', true)->count(),
            'events_with_location' => $events->whereNotNull('location')->where('location', '!=', '')->count(),
            'events_with_description' => $events->whereNotNull('description_md')->where('description_md', '!=', '')->count(),
            'date_range' => [
                'earliest_event' => $events->min('start_at'),
                'latest_event' => $events->max('start_at'),
            ],
        ];
    }

    /**
     * Create a calendar subscription response.
     */
    public function createSubscriptionResponse(string $icsContent, string $filename = 'calendar.ics'): \Illuminate\Http\Response
    {
        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
        ]);
    }
}
