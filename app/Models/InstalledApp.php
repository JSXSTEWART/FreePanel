<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstalledApp extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'domain_id',
        'app_type',
        'app_name',
        'version',
        'path',
        'install_path',
        'admin_url',
        'admin_username',
        'database_name',
        'settings',
        'installed_at',
        'auto_update',
    ];

    protected $casts = [
        'settings' => 'array',
        'installed_at' => 'datetime',
        'auto_update' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function getFullUrlAttribute(): string
    {
        $path = ltrim($this->install_path, '/');
        return 'https://' . $this->domain->name . '/' . $path;
    }

    public function getAdminFullUrlAttribute(): string
    {
        return $this->full_url . '/' . ltrim($this->admin_url, '/');
    }
}
