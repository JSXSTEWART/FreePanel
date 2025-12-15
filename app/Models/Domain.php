<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Domain extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'name',
        'type',
        'document_root',
        'is_active',
        'ssl_enabled',
        'php_version',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'ssl_enabled' => 'boolean',
    ];

    /**
     * Get the account that owns the domain.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get subdomains for this domain.
     */
    public function subdomains(): HasMany
    {
        return $this->hasMany(Subdomain::class);
    }

    /**
     * Get the DNS zone for this domain.
     */
    public function dnsZone(): HasOne
    {
        return $this->hasOne(DnsZone::class);
    }

    /**
     * Get email accounts for this domain.
     */
    public function emailAccounts(): HasMany
    {
        return $this->hasMany(EmailAccount::class);
    }

    /**
     * Get email forwarders for this domain.
     */
    public function emailForwarders(): HasMany
    {
        return $this->hasMany(EmailForwarder::class);
    }

    /**
     * Get SSL certificates for this domain.
     */
    public function sslCertificates(): HasMany
    {
        return $this->hasMany(SslCertificate::class);
    }

    /**
     * Get the active SSL certificate.
     */
    public function activeSslCertificate(): HasOne
    {
        return $this->hasOne(SslCertificate::class)->where('is_active', true);
    }

    /**
     * Get installed applications for this domain.
     */
    public function installedApps(): HasMany
    {
        return $this->hasMany(InstalledApp::class);
    }

    /**
     * Get the full URL for this domain.
     */
    public function getUrlAttribute(): string
    {
        $protocol = $this->ssl_enabled ? 'https' : 'http';
        return "{$protocol}://{$this->name}";
    }

    /**
     * Check if this is the primary domain.
     */
    public function isPrimary(): bool
    {
        return $this->type === 'main';
    }
}
