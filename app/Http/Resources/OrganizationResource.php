<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
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
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'users' => UserResource::collection($this->whenLoaded('users')),
            'calendars' => CalendarResource::collection($this->whenLoaded('calendars')),
            'webhook_subscriptions' => WebhookSubscriptionResource::collection($this->whenLoaded('webhookSubscriptions')),
            
            // Computed fields
            'users_count' => $this->when(
                $this->relationLoaded('users'),
                fn() => $this->users->count()
            ),
            'calendars_count' => $this->when(
                $this->relationLoaded('calendars'),
                fn() => $this->calendars->count()
            ),
        ];
    }
}
