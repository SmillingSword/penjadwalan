<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarResource extends JsonResource
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
            'organization_id' => $this->organization_id,
            'owner_user_id' => $this->owner_user_id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'is_default' => $this->is_default,
            'is_public' => $this->is_public,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'owner' => new UserResource($this->whenLoaded('owner')),
            'events' => EventResource::collection($this->whenLoaded('events')),
            
            // Computed fields
            'events_count' => $this->when(
                $this->relationLoaded('events'),
                fn() => $this->events->count()
            ),
        ];
    }
}
