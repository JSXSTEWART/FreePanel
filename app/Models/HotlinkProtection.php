<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotlinkProtection extends Model
{
    protected $table = 'hotlink_protection';

    protected $fillable = [
        'account_id',
        'is_enabled',
        'allowed_urls',
        'protected_extensions',
        'allow_direct_requests',
        'redirect_url',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'allowed_urls' => 'array',
        'protected_extensions' => 'array',
        'allow_direct_requests' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get default protected extensions
     */
    public static function getDefaultExtensions(): array
    {
        return [
            'jpg', 'jpeg', 'gif', 'png', 'bmp', 'webp', 'svg',
            'mp3', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm',
            'pdf', 'doc', 'docx', 'zip', 'rar', '7z',
        ];
    }
}
