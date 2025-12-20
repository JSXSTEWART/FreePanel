<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserZapierConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mcp_server_url',
        'is_active',
        'connected_at',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'connected_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function disconnect(): void
    {
        $this->update(['is_active' => false]);
    }
}
