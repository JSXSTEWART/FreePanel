<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Account extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'package_id',
        'username',
        'domain',
        'home_directory',
        'shell',
        'uid',
        'gid',
        'disk_used',
        'bandwidth_used',
        'suspend_reason',
        'suspended_at',
        'status',
        'ip_address',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'suspended_at' => 'datetime',
        'disk_used' => 'integer',
        'bandwidth_used' => 'integer',
        'uid' => 'integer',
        'gid' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($account) {
            if (empty($account->uuid)) {
                $account->uuid = (string) Str::uuid();
            }
            if (empty($account->home_directory)) {
                $account->home_directory = config('freepanel.paths.home_base') . '/' . $account->username;
            }
        });
    }

    /**
     * Get the user that owns the account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the package for this account.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get all domains for this account.
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Get the primary domain.
     */
    public function primaryDomain(): HasMany
    {
        return $this->hasMany(Domain::class)->where('type', 'main');
    }

    /**
     * Get all databases for this account.
     */
    public function databases(): HasMany
    {
        return $this->hasMany(Database::class);
    }

    /**
     * Get all database users for this account.
     */
    public function databaseUsers(): HasMany
    {
        return $this->hasMany(DatabaseUser::class);
    }

    /**
     * Get all FTP accounts.
     */
    public function ftpAccounts(): HasMany
    {
        return $this->hasMany(FtpAccount::class);
    }

    /**
     * Get all backups.
     */
    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    /**
     * Get enabled features for this account.
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'account_features')
            ->withPivot('enabled');
    }

    /**
     * Check if account has a specific feature enabled.
     */
    public function hasFeature(string $featureName): bool
    {
        $feature = $this->features()->where('name', $featureName)->first();

        if (!$feature) {
            // Check if feature is enabled by default
            return Feature::where('name', $featureName)->where('is_default', true)->exists();
        }

        return (bool) $feature->pivot->enabled;
    }

    /**
     * Enable a feature for this account.
     */
    public function enableFeature(string $featureName): void
    {
        $feature = Feature::where('name', $featureName)->first();
        if ($feature) {
            $this->features()->syncWithoutDetaching([
                $feature->id => ['enabled' => true]
            ]);
        }
    }

    /**
     * Disable a feature for this account.
     */
    public function disableFeature(string $featureName): void
    {
        $feature = Feature::where('name', $featureName)->first();
        if ($feature) {
            $this->features()->syncWithoutDetaching([
                $feature->id => ['enabled' => false]
            ]);
        }
    }

    /**
     * Get all available features with their status for this account.
     */
    public function getAllFeatures(): array
    {
        $allFeatures = Feature::all();
        $accountFeatures = $this->features()->get()->keyBy('id');

        return $allFeatures->map(function ($feature) use ($accountFeatures) {
            $accountFeature = $accountFeatures->get($feature->id);
            return [
                'id' => $feature->id,
                'name' => $feature->name,
                'display_name' => $feature->display_name,
                'description' => $feature->description,
                'category' => $feature->category,
                'is_enabled' => $accountFeature
                    ? (bool) $accountFeature->pivot->enabled
                    : $feature->is_default,
                'is_default' => $feature->is_default,
            ];
        })->toArray();
    }

    /**
     * Check if account is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Check if account is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get disk usage percentage.
     */
    public function getDiskUsagePercentAttribute(): float
    {
        $quota = $this->package->disk_quota;
        if ($quota <= 0) {
            return 0;
        }
        return round(($this->disk_used / $quota) * 100, 2);
    }

    /**
     * Get bandwidth usage percentage.
     */
    public function getBandwidthUsagePercentAttribute(): float
    {
        $quota = $this->package->bandwidth_quota;
        if ($quota <= 0) {
            return 0;
        }
        return round(($this->bandwidth_used / $quota) * 100, 2);
    }

    /**
     * Get email accounts count.
     */
    public function emailAccountsCount(): int
    {
        return $this->domains()
            ->withCount('emailAccounts')
            ->get()
            ->sum('email_accounts_count');
    }
}
