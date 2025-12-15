<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DnsZone extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_id',
        'serial',
        'refresh',
        'retry',
        'expire',
        'minimum',
        'ttl',
        'primary_ns',
        'admin_email',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'serial' => 'integer',
        'refresh' => 'integer',
        'retry' => 'integer',
        'expire' => 'integer',
        'minimum' => 'integer',
        'ttl' => 'integer',
    ];

    /**
     * Get the domain this zone belongs to.
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get all DNS records for this zone.
     */
    public function records(): HasMany
    {
        return $this->hasMany(DnsRecord::class, 'zone_id');
    }

    /**
     * Increment the serial number.
     */
    public function incrementSerial(): void
    {
        $today = (int) date('Ymd');
        $serialDate = (int) substr((string) $this->serial, 0, 8);
        $sequence = (int) substr((string) $this->serial, 8, 2);

        if ($serialDate === $today) {
            $sequence++;
        } else {
            $serialDate = $today;
            $sequence = 1;
        }

        $this->serial = (int) sprintf('%d%02d', $serialDate, $sequence);
        $this->save();
    }
}
