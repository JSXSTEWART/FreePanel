<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reseller extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'max_accounts',
        'disk_limit',
        'bandwidth_limit',
        'nameservers',
        'branding',
        'allowed_packages',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'max_accounts' => 'integer',
        'disk_limit' => 'integer',
        'bandwidth_limit' => 'integer',
        'nameservers' => 'array',
        'branding' => 'array',
        'allowed_packages' => 'array',
    ];

    /**
     * Get the user that owns the reseller configuration.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all accounts managed by this reseller.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'user_id', 'user_id')
            ->whereHas('user', function ($query) {
                $query->where('parent_id', $this->user_id);
            });
    }

    /**
     * Get all child users (customers) of this reseller.
     */
    public function customers(): HasMany
    {
        return $this->user->children();
    }

    /**
     * Get total disk usage across all managed accounts.
     */
    public function getTotalDiskUsedAttribute(): int
    {
        return Account::whereHas('user', function ($query) {
            $query->where('parent_id', $this->user_id);
        })->sum('disk_used');
    }

    /**
     * Get total bandwidth usage across all managed accounts.
     */
    public function getTotalBandwidthUsedAttribute(): int
    {
        return Account::whereHas('user', function ($query) {
            $query->where('parent_id', $this->user_id);
        })->sum('bandwidth_used');
    }

    /**
     * Get current account count for this reseller.
     */
    public function getAccountCountAttribute(): int
    {
        return User::where('parent_id', $this->user_id)->count();
    }

    /**
     * Check if reseller can create more accounts.
     */
    public function canCreateAccount(): bool
    {
        if ($this->max_accounts === 0) {
            return true; // Unlimited
        }

        return $this->account_count < $this->max_accounts;
    }

    /**
     * Check if reseller can assign a specific package.
     */
    public function canAssignPackage(int $packageId): bool
    {
        if (empty($this->allowed_packages)) {
            return true; // All packages allowed
        }

        return in_array($packageId, $this->allowed_packages);
    }

    /**
     * Get disk usage percentage.
     */
    public function getDiskUsagePercentAttribute(): float
    {
        if ($this->disk_limit <= 0) {
            return 0;
        }
        return round(($this->total_disk_used / $this->disk_limit) * 100, 2);
    }

    /**
     * Get bandwidth usage percentage.
     */
    public function getBandwidthUsagePercentAttribute(): float
    {
        if ($this->bandwidth_limit <= 0) {
            return 0;
        }
        return round(($this->total_bandwidth_used / $this->bandwidth_limit) * 100, 2);
    }
}
