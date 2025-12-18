<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpamSettings extends Model
{
    use HasFactory;

    protected $table = 'spam_settings';

    protected $fillable = [
        'account_id',
        'spam_filter_enabled',
        'spam_threshold',
        'auto_delete_spam',
        'auto_delete_score',
        'spam_box_enabled',
        'whitelist',
        'blacklist',
    ];

    protected $casts = [
        'spam_filter_enabled' => 'boolean',
        'auto_delete_spam' => 'boolean',
        'spam_box_enabled' => 'boolean',
        'whitelist' => 'array',
        'blacklist' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get spam threshold levels
     */
    public static function thresholdLevels(): array
    {
        return [
            1 => 'Extremely Aggressive (1) - May catch legitimate mail',
            2 => 'Very Aggressive (2)',
            3 => 'Aggressive (3)',
            4 => 'Moderately Aggressive (4)',
            5 => 'Default (5) - Recommended',
            6 => 'Moderately Permissive (6)',
            7 => 'Permissive (7)',
            8 => 'Very Permissive (8)',
            10 => 'Extremely Permissive (10) - May miss spam',
        ];
    }

    /**
     * Add email to whitelist
     */
    public function addToWhitelist(string $email): void
    {
        $whitelist = $this->whitelist ?? [];
        if (!in_array($email, $whitelist)) {
            $whitelist[] = $email;
            $this->update(['whitelist' => $whitelist]);
        }
    }

    /**
     * Remove email from whitelist
     */
    public function removeFromWhitelist(string $email): void
    {
        $whitelist = $this->whitelist ?? [];
        $whitelist = array_filter($whitelist, fn($e) => $e !== $email);
        $this->update(['whitelist' => array_values($whitelist)]);
    }

    /**
     * Add email to blacklist
     */
    public function addToBlacklist(string $email): void
    {
        $blacklist = $this->blacklist ?? [];
        if (!in_array($email, $blacklist)) {
            $blacklist[] = $email;
            $this->update(['blacklist' => $blacklist]);
        }
    }

    /**
     * Remove email from blacklist
     */
    public function removeFromBlacklist(string $email): void
    {
        $blacklist = $this->blacklist ?? [];
        $blacklist = array_filter($blacklist, fn($e) => $e !== $email);
        $this->update(['blacklist' => array_values($blacklist)]);
    }

    /**
     * Check if email is whitelisted
     */
    public function isWhitelisted(string $email): bool
    {
        return in_array($email, $this->whitelist ?? []);
    }

    /**
     * Check if email is blacklisted
     */
    public function isBlacklisted(string $email): bool
    {
        return in_array($email, $this->blacklist ?? []);
    }

    /**
     * Generate SpamAssassin user preferences
     */
    public function toSpamAssassinConfig(): string
    {
        $config = "# SpamAssassin user preferences\n";
        $config .= "required_score {$this->spam_threshold}\n";

        if ($this->whitelist) {
            foreach ($this->whitelist as $email) {
                $config .= "whitelist_from {$email}\n";
            }
        }

        if ($this->blacklist) {
            foreach ($this->blacklist as $email) {
                $config .= "blacklist_from {$email}\n";
            }
        }

        return $config;
    }
}
