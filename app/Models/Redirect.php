<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Redirect extends Model
{
    protected $fillable = [
        'account_id',
        'domain_id',
        'source_path',
        'destination_url',
        'type',
        'wildcard',
        'is_active',
    ];

    protected $casts = [
        'wildcard' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get HTTP status code for redirect type
     */
    public function getStatusCodeAttribute(): int
    {
        return $this->type === 'permanent' ? 301 : 302;
    }
}
