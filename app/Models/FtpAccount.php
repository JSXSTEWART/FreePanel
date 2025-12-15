<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FtpAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'domain_id',
        'username',
        'password',
        'directory',
        'quota_mb',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'quota_mb' => 'integer',
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
}
