<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipant extends Model
{
    use HasUuids;

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
}
