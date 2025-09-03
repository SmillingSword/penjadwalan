<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Calendar extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'owner_user_id',
        'name',
        'color',
        'is_default',
        'is_public',
        'description',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
