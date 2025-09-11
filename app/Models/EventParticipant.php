<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class EventParticipant extends Model
{
    use HasUuids, Searchable;

    protected $fillable = [
        'event_id',
        'email',
        'name',
        'status',
        'role',
    ];

    protected $casts = [
        'status' => 'string',
        'role' => 'string',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'email' => $this->email,
            'name' => $this->name,
            'status' => $this->status,
            'role' => $this->role,
            'created_at' => $this->created_at?->timestamp,
        ];
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        // Only index participants for events that are accessible to the current organization
        return $this->event && 
               $this->event->calendar && 
               $this->event->calendar->organization_id === request()->get('current_organization_id');
    }

    /**
     * Get the Scout index name for the model.
     */
    public function searchableAs(): string
    {
        return 'event_participants';
    }
}
