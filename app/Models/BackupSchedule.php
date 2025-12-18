<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupSchedule extends Model
{
    protected $fillable = [
        'account_id',
        'is_system',
        'type',
        'frequency',
        'day_of_week',
        'day_of_month',
        'time',
        'retention_days',
        'destination',
        'destination_config',
        'is_enabled',
        'last_run',
        'last_status',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_enabled' => 'boolean',
        'destination_config' => 'array',
        'last_run' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Check if backup should run now
     */
    public function shouldRunNow(): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        $now = now();
        $scheduledTime = explode(':', $this->time);
        $scheduledHour = (int) $scheduledTime[0];
        $scheduledMinute = (int) $scheduledTime[1];

        // Check if current time matches scheduled time (within 5-minute window)
        if ($now->hour !== $scheduledHour || abs($now->minute - $scheduledMinute) > 5) {
            return false;
        }

        switch ($this->frequency) {
            case 'daily':
                return true;

            case 'weekly':
                return $now->dayOfWeek === (int) $this->day_of_week;

            case 'monthly':
                return $now->day === $this->day_of_month;

            default:
                return false;
        }
    }

    /**
     * Get next run time
     */
    public function getNextRunAttribute(): ?\Carbon\Carbon
    {
        if (!$this->is_enabled) {
            return null;
        }

        $now = now();
        $time = explode(':', $this->time);
        $hour = (int) $time[0];
        $minute = (int) $time[1];

        switch ($this->frequency) {
            case 'daily':
                $next = $now->copy()->setTime($hour, $minute);
                if ($next->lte($now)) {
                    $next->addDay();
                }
                return $next;

            case 'weekly':
                $next = $now->copy()->next((int) $this->day_of_week)->setTime($hour, $minute);
                if ($next->lte($now)) {
                    $next->addWeek();
                }
                return $next;

            case 'monthly':
                $next = $now->copy()->setDay($this->day_of_month)->setTime($hour, $minute);
                if ($next->lte($now)) {
                    $next->addMonth();
                }
                return $next;

            default:
                return null;
        }
    }

    /**
     * Get frequency description
     */
    public function getFrequencyDescriptionAttribute(): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        switch ($this->frequency) {
            case 'daily':
                return "Daily at {$this->time}";
            case 'weekly':
                $day = $days[(int) $this->day_of_week] ?? 'Unknown';
                return "Every {$day} at {$this->time}";
            case 'monthly':
                return "Monthly on day {$this->day_of_month} at {$this->time}";
            default:
                return 'Unknown';
        }
    }
}
