<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedIp extends Model
{
    protected $fillable = [
        'account_id',
        'ip_address',
        'reason',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Check if the block is still active
     */
    public function isActive(): bool
    {
        if ($this->expires_at === null) {
            return true; // Permanent block
        }

        return $this->expires_at->isFuture();
    }

    /**
     * Scope to get only active blocks
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Validate IP address format (IPv4 or IPv6)
     */
    public static function isValidIp(string $ip): bool
    {
        // Check for CIDR notation
        if (str_contains($ip, '/')) {
            [$address, $prefix] = explode('/', $ip);
            $prefixInt = (int) $prefix;

            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $prefixInt >= 0 && $prefixInt <= 32;
            }

            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $prefixInt >= 0 && $prefixInt <= 128;
            }

            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}
