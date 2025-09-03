<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventParticipantResource extends JsonResource
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
            'email' => $this->email,
            'name' => $this->name,
            'status' => $this->status,
            'role' => $this->role,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'event' => new EventResource($this->whenLoaded('event')),
            
            // Computed fields
            'is_required' => $this->role === 'required',
            'is_optional' => $this->role === 'optional',
            'is_resource' => $this->role === 'resource',
            'has_responded' => in_array($this->status, ['accepted', 'declined']),
            'status_label' => match($this->status) {
                'pending' => 'Pending',
                'accepted' => 'Accepted',
                'declined' => 'Declined',
                'tentative' => 'Tentative',
                default => 'Unknown'
            },
            'role_label' => match($this->role) {
                'required' => 'Required',
                'optional' => 'Optional',
                'resource' => 'Resource',
                default => 'Required'
            },
        ];
    }
}
