<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'name',
        'event_type',
        'payload',
        'description',
    ];
    
    protected $casts = [
        'payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function endpoints(): BelongsToMany
    {
        return $this->belongsToMany(Endpoint::class, 'event_endpoint')->withTimestamps();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function activeEndpoints(): BelongsToMany
    {
        return $this->endpoints()->where('is_active', true);
    }
}
