<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'category',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_features')
            ->withPivot('is_enabled')
            ->withTimestamps();
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_features')
            ->withPivot('is_enabled')
            ->withTimestamps();
    }
}
