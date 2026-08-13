<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'endpoint_id',
        'payload',
        'status',
        'response_code',
        'response_body',
        'attempt_count',
        'duration_ms',
        'delivered_at',
        'next_retry_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReadyForRetry($query)
    {
        return $query->where('status', 'failed')
            ->where('next_retry_at', '<=', now());
    }

    /**
     * Deliveries left in `retrying` status past the configured threshold,
     * indicating the job that set that status never reached a terminal
     * success/failed update (e.g. the queue worker crashed or was restarted
     * mid-attempt). Invisible to every other status-based scope, so these
     * must be swept back into the normal retry flow explicitly.
     */
    public function scopeStuckRetrying($query, ?int $minutes = null)
    {
        $minutes ??= config('webhooks.stuck_retrying_minutes', 10);

        return $query->where('status', 'retrying')
            ->where('updated_at', '<=', now()->subMinutes($minutes));
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
