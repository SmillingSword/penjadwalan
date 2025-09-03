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
        return [
            'id' => $this->id,
            'calendar_id' => $this->calendar_id,
            'title' => $this->title,
            'description_md' => $this->description_md,
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
            'start_at' => $this->start_at?->toISOString(),
            'end_at' => $this->end_at?->toISOString(),
            'all_day' => $this->all_day,
            'timezone' => $this->timezone,
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
            
            // Formatted dates for display
            'formatted_start' => $this->when($this->start_at, function () {
                return [
                    'date' => $this->start_at->format('Y-m-d'),
                    'time' => $this->all_day ? null : $this->start_at->format('H:i'),
                    'datetime' => $this->start_at->format('Y-m-d H:i:s'),
                    'human' => $this->start_at->diffForHumans(),
                ];
            }),
            'formatted_end' => $this->when($this->end_at, function () {
                return [
                    'date' => $this->end_at->format('Y-m-d'),
                    'time' => $this->all_day ? null : $this->end_at->format('H:i'),
                    'datetime' => $this->end_at->format('Y-m-d H:i:s'),
                    'human' => $this->end_at->diffForHumans(),
                ];
            }),
        ];
    }
}
