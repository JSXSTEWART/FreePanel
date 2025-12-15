<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmailAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'local_part',
        'password_hash',
        'quota',
        'quota_used',
        'maildir_path',
        'is_active',
    ];

    protected $casts = [
        'quota' => 'integer',
        'quota_used' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'password_hash',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function autoresponder(): HasOne
    {
        return $this->hasOne(EmailAutoresponder::class);
    }

    public function getEmailAttribute(): string
    {
        return "{$this->local_part}@{$this->domain->name}";
    }

    public function getQuotaUsagePercentAttribute(): float
    {
        if ($this->quota <= 0) return 0;
        return round(($this->quota_used / $this->quota) * 100, 2);
    }
}
