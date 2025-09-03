<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'organizations' => OrganizationResource::collection($this->whenLoaded('organizations')),
            'calendars' => CalendarResource::collection($this->whenLoaded('calendars')),
            
            // Computed fields
            'organizations_count' => $this->when(
                $this->relationLoaded('organizations'),
                fn() => $this->organizations->count()
            ),
            'calendars_count' => $this->when(
                $this->relationLoaded('calendars'),
                fn() => $this->calendars->count()
            ),
        ];
    }
}
