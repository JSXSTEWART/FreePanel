<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SshKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'name',
        'public_key',
        'fingerprint',
        'key_type',
        'key_bits',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'public_key',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Generate fingerprint from public key
     */
    public static function generateFingerprint(string $publicKey): string
    {
        // Extract the key data (remove type and comment)
        $parts = explode(' ', trim($publicKey));
        if (count($parts) < 2) {
            throw new \InvalidArgumentException('Invalid SSH public key format');
        }

        $keyData = base64_decode($parts[1]);
        if ($keyData === false) {
            throw new \InvalidArgumentException('Invalid SSH public key encoding');
        }

        // Generate MD5 fingerprint (traditional format)
        $hash = md5($keyData);
        return implode(':', str_split($hash, 2));
    }

    /**
     * Parse key type and bits from public key
     */
    public static function parseKeyInfo(string $publicKey): array
    {
        $parts = explode(' ', trim($publicKey));
        $type = $parts[0] ?? 'unknown';

        $bits = null;
        switch ($type) {
            case 'ssh-rsa':
                // RSA key - parse to get bits
                $keyData = base64_decode($parts[1] ?? '');
                if ($keyData) {
                    // Extract modulus length
                    $bits = strlen($keyData) * 8 - 200; // Approximate
                }
                break;
            case 'ssh-ed25519':
                $bits = 256;
                break;
            case 'ecdsa-sha2-nistp256':
                $bits = 256;
                break;
            case 'ecdsa-sha2-nistp384':
                $bits = 384;
                break;
            case 'ecdsa-sha2-nistp521':
                $bits = 521;
                break;
        }

        return [
            'type' => $type,
            'bits' => $bits,
        ];
    }

    /**
     * Validate SSH public key format
     */
    public static function validatePublicKey(string $publicKey): bool
    {
        $validTypes = [
            'ssh-rsa',
            'ssh-dss',
            'ssh-ed25519',
            'ecdsa-sha2-nistp256',
            'ecdsa-sha2-nistp384',
            'ecdsa-sha2-nistp521',
        ];

        $parts = explode(' ', trim($publicKey));

        if (count($parts) < 2) {
            return false;
        }

        if (!in_array($parts[0], $validTypes)) {
            return false;
        }

        // Validate base64 encoding
        if (base64_decode($parts[1], true) === false) {
            return false;
        }

        return true;
    }

    /**
     * Get truncated key for display
     */
    public function getTruncatedKeyAttribute(): string
    {
        $parts = explode(' ', trim($this->public_key));
        $type = $parts[0] ?? '';
        $key = $parts[1] ?? '';
        $comment = $parts[2] ?? '';

        $truncated = substr($key, 0, 20) . '...' . substr($key, -20);

        return "{$type} {$truncated}" . ($comment ? " {$comment}" : '');
    }
}
