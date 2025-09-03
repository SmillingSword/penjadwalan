<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderResource extends JsonResource
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
            'event_id' => $this->event_id,
            'method' => $this->method,
            'minutes_before' => $this->minutes_before,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'event' => new EventResource($this->whenLoaded('event')),
            
            // Computed fields
            'method_label' => match($this->method) {
                'email' => 'Email',
                'popup' => 'Popup',
                'sms' => 'SMS',
                default => 'Unknown'
            },
            'time_label' => $this->getTimeLabel(),
        ];
    }

    /**
     * Get human-readable time label for the reminder.
     */
    private function getTimeLabel(): string
    {
        $minutes = $this->minutes_before;
        
        if ($minutes === 0) {
            return 'At event time';
        }
        
        if ($minutes < 60) {
            return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' before';
        }
        
        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($hours < 24) {
            $label = $hours . ' hour' . ($hours === 1 ? '' : 's');
            if ($remainingMinutes > 0) {
                $label .= ' and ' . $remainingMinutes . ' minute' . ($remainingMinutes === 1 ? '' : 's');
            }
            return $label . ' before';
        }
        
        $days = intval($hours / 24);
        $remainingHours = $hours % 24;
        
        $label = $days . ' day' . ($days === 1 ? '' : 's');
        if ($remainingHours > 0) {
            $label .= ' and ' . $remainingHours . ' hour' . ($remainingHours === 1 ? '' : 's');
        }
        
        return $label . ' before';
    }
}
