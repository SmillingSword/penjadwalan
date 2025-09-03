<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookSubscriptionResource extends JsonResource
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
            'target_url' => $this->target_url,
            'events' => $this->events,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            
            // Computed fields
            'events_count' => is_array($this->events) ? count($this->events) : 0,
            'status_label' => $this->is_active ? 'Active' : 'Inactive',
            
            // Hide secret for security
            'has_secret' => !empty($this->secret),
        ];
    }
}
