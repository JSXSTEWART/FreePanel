<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtectedDirectoryUser extends Model
{
    protected $fillable = [
        'protected_directory_id',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    public function protectedDirectory(): BelongsTo
    {
        return $this->belongsTo(ProtectedDirectory::class);
    }

    /**
     * Hash password for .htpasswd
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}
