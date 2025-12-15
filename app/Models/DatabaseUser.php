<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DatabaseUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'username',
        'password_hash',
        'host',
        'type',
    ];

    protected $hidden = [
        'password_hash',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function databases(): BelongsToMany
    {
        return $this->belongsToMany(Database::class, 'database_user_privileges')
            ->withPivot('privileges')
            ->withTimestamps();
    }
}
