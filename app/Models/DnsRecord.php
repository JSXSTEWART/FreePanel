<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DnsRecord extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'zone_id',
        'name',
        'type',
        'content',
        'ttl',
        'priority',
        'is_system',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ttl' => 'integer',
        'priority' => 'integer',
        'is_system' => 'boolean',
    ];

    /**
     * Get the zone this record belongs to.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(DnsZone::class, 'zone_id');
    }

    /**
     * Format record for zone file.
     */
    public function toZoneFormat(): string
    {
        $line = "{$this->name}\t{$this->ttl}\tIN\t{$this->type}";

        if ($this->type === 'MX' || $this->type === 'SRV') {
            $line .= "\t{$this->priority}";
        }

        $line .= "\t{$this->content}";

        return $line;
    }
}
