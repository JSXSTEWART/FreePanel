<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'disk_quota',
        'bandwidth_quota',
        'max_domains',
        'max_subdomains',
        'max_email_accounts',
        'max_email_forwarders',
        'max_databases',
        'max_ftp_accounts',
        'max_parked_domains',
        'features',
        'is_active',
        'is_reseller_package',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'disk_quota' => 'integer',
        'bandwidth_quota' => 'integer',
        'max_domains' => 'integer',
        'max_subdomains' => 'integer',
        'max_email_accounts' => 'integer',
        'max_email_forwarders' => 'integer',
        'max_databases' => 'integer',
        'max_ftp_accounts' => 'integer',
        'max_parked_domains' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_reseller_package' => 'boolean',
    ];

    /**
     * Get accounts using this package.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Get disk quota in human-readable format.
     */
    public function getDiskQuotaHumanAttribute(): string
    {
        return $this->formatBytes($this->disk_quota);
    }

    /**
     * Get bandwidth quota in human-readable format.
     */
    public function getBandwidthQuotaHumanAttribute(): string
    {
        return $this->formatBytes($this->bandwidth_quota);
    }

    /**
     * Check if a limit is unlimited (0).
     */
    public function isUnlimited(string $field): bool
    {
        return $this->{$field} === 0;
    }

    /**
     * Format bytes to human-readable.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return 'Unlimited';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
