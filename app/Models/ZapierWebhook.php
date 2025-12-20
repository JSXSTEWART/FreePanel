<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZapierWebhook extends Model
{
    use HasFactory;

    protected $table = 'zapier_webhooks';

    protected $fillable = [
        'account_id',
        'event_type',
        'webhook_url',
        'format',
        'is_active',
        'last_triggered_at',
        'failure_count',
        'last_error',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    /**
     * Get the account that owns this webhook.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Scope: Get only active webhooks
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Get webhooks for a specific event
     */
    public function scopeForEvent($query, string $event)
    {
        return $query->where('event_type', $event);
    }

    /**
     * Mark this webhook as triggered
     */
    public function recordSuccess(): void
    {
        $this->update([
            'last_triggered_at' => now(),
            'failure_count' => 0,
            'last_error' => null,
        ]);
    }

    /**
     * Record a failure for this webhook
     */
    public function recordFailure(string $error): void
    {
        $this->increment('failure_count');
        $this->update([
            'last_error' => $error,
        ]);

        // Auto-disable after 10 consecutive failures
        if ($this->failure_count >= 10) {
            $this->update(['is_active' => false]);
        }
    }
}
