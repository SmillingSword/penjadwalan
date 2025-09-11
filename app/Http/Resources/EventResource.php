<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get user's timezone, fallback to event's timezone, then to Asia/Jakarta
        $userTimezone = $request->user()?->timezone ?? $this->timezone ?? 'Asia/Jakarta';
        
        // Convert UTC times to user's timezone for display
        $startAtInUserTz = $this->start_at ? $this->start_at->setTimezone($userTimezone) : null;
        $endAtInUserTz = $this->end_at ? $this->end_at->setTimezone($userTimezone) : null;
        
        return [
            'id' => $this->id,
            'calendar_id' => $this->calendar_id,
            'title' => $this->title,
            'description_md' => $this->description_md,
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
            
            // Return times in user's timezone for frontend display
            'start_at' => $startAtInUserTz?->toISOString(),
            'end_at' => $endAtInUserTz?->toISOString(),
            
            // Also provide formatted date and time for easy frontend use
            'date' => $startAtInUserTz?->format('Y-m-d'), // Date in user timezone
            'time' => $this->all_day ? null : $startAtInUserTz?->format('H:i'), // Time in user timezone
            'end_time' => $this->all_day ? null : $endAtInUserTz?->format('H:i'), // End time in user timezone
            
            // Keep UTC versions for reference
            'start_at_utc' => $this->start_at?->toISOString(),
            'end_at_utc' => $this->end_at?->toISOString(),
            
            'all_day' => $this->all_day,
            'timezone' => $this->timezone,
            'user_timezone' => $userTimezone,
            'rrule' => $this->rrule,
            'exdates' => $this->exdates,
            'is_private' => $this->is_private,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'calendar' => new CalendarResource($this->whenLoaded('calendar')),
            'participants' => EventParticipantResource::collection($this->whenLoaded('participants')),
            'reminders' => ReminderResource::collection($this->whenLoaded('reminders')),
            
            // Computed fields
            'duration_minutes' => $this->when(
                $this->start_at && $this->end_at,
                fn() => $this->start_at->diffInMinutes($this->end_at)
            ),
            'is_recurring' => !empty($this->rrule),
            'has_participants' => $this->participants_count > 0 || $this->relationLoaded('participants') && $this->participants->count() > 0,
            'has_reminders' => $this->reminders_count > 0 || $this->relationLoaded('reminders') && $this->reminders->count() > 0,
            
            // Formatted dates for display (in user's timezone)
            'formatted_start' => $this->when($startAtInUserTz, function () use ($startAtInUserTz) {
                return [
                    'date' => $startAtInUserTz->format('Y-m-d'),
                    'time' => $this->all_day ? null : $startAtInUserTz->format('H:i'),
                    'datetime' => $startAtInUserTz->format('Y-m-d H:i:s'),
                    'human' => $startAtInUserTz->diffForHumans(),
                    'full_date' => $startAtInUserTz->format('l, F j, Y'),
                    'time_12h' => $this->all_day ? null : $startAtInUserTz->format('g:i A'),
                ];
            }),
            'formatted_end' => $this->when($endAtInUserTz, function () use ($endAtInUserTz) {
                return [
                    'date' => $endAtInUserTz->format('Y-m-d'),
                    'time' => $this->all_day ? null : $endAtInUserTz->format('H:i'),
                    'datetime' => $endAtInUserTz->format('Y-m-d H:i:s'),
                    'human' => $endAtInUserTz->diffForHumans(),
                    'full_date' => $endAtInUserTz->format('l, F j, Y'),
                    'time_12h' => $this->all_day ? null : $endAtInUserTz->format('g:i A'),
                ];
            }),
            
            // Add color for frontend display
            'color' => $this->getEventColor(),
        ];
    }
    
    /**
     * Get event color based on calendar or other criteria
     */
    private function getEventColor()
    {
        // You can customize this logic based on your needs
        // For now, return a default color or based on calendar
        $colors = [
            '#3B82F6', // Blue
            '#10B981', // Green  
            '#F59E0B', // Yellow
            '#EF4444', // Red
            '#8B5CF6', // Purple
            '#06B6D4', // Cyan
            '#F97316', // Orange
            '#84CC16', // Lime
        ];
        
        // Use calendar_id to determine color consistently
        $index = crc32($this->calendar_id) % count($colors);
        return $colors[$index];
    }
}
