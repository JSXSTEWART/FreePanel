<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProtectedDirectory extends Model
{
    protected $fillable = [
        'account_id',
        'path',
        'name',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(ProtectedDirectoryUser::class);
    }
}
