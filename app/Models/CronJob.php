<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronJob extends Model
{
    protected $fillable = [
        'account_id',
        'minute',
        'hour',
        'day',
        'month',
        'weekday',
        'command',
        'email',
        'is_active',
        'last_run',
        'last_status',
        'last_output',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the cron schedule expression
     */
    public function getScheduleAttribute(): string
    {
        return "{$this->minute} {$this->hour} {$this->day} {$this->month} {$this->weekday}";
    }

    /**
     * Get human-readable schedule description
     */
    public function getScheduleDescriptionAttribute(): string
    {
        $parts = [];

        // Minute
        if ($this->minute === '*') {
            $parts[] = 'every minute';
        } elseif (str_starts_with($this->minute, '*/')) {
            $parts[] = 'every ' . substr($this->minute, 2) . ' minutes';
        } else {
            $parts[] = 'at minute ' . $this->minute;
        }

        // Hour
        if ($this->hour !== '*') {
            if (str_starts_with($this->hour, '*/')) {
                $parts[] = 'every ' . substr($this->hour, 2) . ' hours';
            } else {
                $parts[] = 'at ' . $this->hour . ':00';
            }
        }

        // Day of month
        if ($this->day !== '*') {
            $parts[] = 'on day ' . $this->day;
        }

        // Month
        if ($this->month !== '*') {
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $monthNum = (int) $this->month;
            if ($monthNum >= 1 && $monthNum <= 12) {
                $parts[] = 'in ' . $months[$monthNum - 1];
            }
        }

        // Day of week
        if ($this->weekday !== '*') {
            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $dayNum = (int) $this->weekday;
            if ($dayNum >= 0 && $dayNum <= 6) {
                $parts[] = 'on ' . $days[$dayNum];
            }
        }

        return ucfirst(implode(', ', $parts));
    }

    /**
     * Common schedule presets
     */
    public static function getPresets(): array
    {
        return [
            'every_minute' => ['*', '*', '*', '*', '*', 'Every Minute'],
            'every_5_minutes' => ['*/5', '*', '*', '*', '*', 'Every 5 Minutes'],
            'every_15_minutes' => ['*/15', '*', '*', '*', '*', 'Every 15 Minutes'],
            'every_30_minutes' => ['*/30', '*', '*', '*', '*', 'Every 30 Minutes'],
            'hourly' => ['0', '*', '*', '*', '*', 'Once Per Hour'],
            'twice_daily' => ['0', '0,12', '*', '*', '*', 'Twice Per Day'],
            'daily' => ['0', '0', '*', '*', '*', 'Once Per Day'],
            'weekly' => ['0', '0', '*', '*', '0', 'Once Per Week'],
            'monthly' => ['0', '0', '1', '*', '*', 'Once Per Month'],
        ];
    }
}
