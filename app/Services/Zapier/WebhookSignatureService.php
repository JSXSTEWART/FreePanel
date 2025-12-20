<?php

namespace App\Services\Zapier;

use Illuminate\Support\Facades\Log;

class WebhookSignatureService
{
    /**
     * Algorithm for HMAC signature
     */
    private const SIGNATURE_ALGORITHM = 'sha256';

    /**
     * Header name for signature
     */
    private const SIGNATURE_HEADER = 'X-Zapier-Signature';

    /**
     * Header name for timestamp (for replay attack prevention)
     */
    private const TIMESTAMP_HEADER = 'X-Zapier-Timestamp';

    /**
     * Maximum age of timestamp in seconds (5 minutes)
     */
    private const TIMESTAMP_TOLERANCE = 300;

    /**
     * Verify webhook signature and timestamp
     *
     * @param string $payload The raw request body
     * @param string $signature The signature from the request header
     * @param string $timestamp The timestamp from the request header
     * @param string $secret The webhook secret
     * @return bool Whether the signature is valid
     */
    public function verify(
        string $payload,
        string $signature,
        string $timestamp,
        string $secret
    ): bool {
        // Verify timestamp to prevent replay attacks
        if (!$this->verifyTimestamp($timestamp)) {
            Log::warning('Webhook signature verification failed: timestamp too old', [
                'timestamp' => $timestamp,
                'current_time' => now()->timestamp,
                'difference' => now()->timestamp - (int)$timestamp,
            ]);
            return false;
        }

        // Compute expected signature
        $expectedSignature = $this->computeSignature($payload, $timestamp, $secret);

        // Use timing-safe comparison to prevent timing attacks
        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            Log::warning('Webhook signature verification failed: signature mismatch', [
                'provided' => substr($signature, 0, 16) . '...',
                'expected' => substr($expectedSignature, 0, 16) . '...',
            ]);
        } else {
            Log::debug('Webhook signature verified successfully');
        }

        return $isValid;
    }

    /**
     * Compute the HMAC signature for a webhook
     *
     * @param string $payload The raw request body
     * @param string $timestamp The timestamp (ISO 8601 or Unix timestamp)
     * @param string $secret The webhook secret
     * @return string The computed signature
     */
    public function computeSignature(
        string $payload,
        string $timestamp,
        string $secret
    ): string {
        // Normalize timestamp to Unix timestamp if needed
        $normalizedTimestamp = $this->normalizeTimestamp($timestamp);

        // Create signing string: "payload.timestamp"
        $signingString = "{$payload}.{$normalizedTimestamp}";

        // Compute HMAC-SHA256
        return hash_hmac(
            self::SIGNATURE_ALGORITHM,
            $signingString,
            $secret,
            false // Return as hex, not raw binary
        );
    }

    /**
     * Verify that the timestamp is within acceptable tolerance
     *
     * @param string $timestamp The timestamp to verify
     * @return bool Whether the timestamp is acceptable
     */
    protected function verifyTimestamp(string $timestamp): bool
    {
        try {
            $normalizedTimestamp = $this->normalizeTimestamp($timestamp);
            $currentTime = now()->timestamp;
            $difference = abs($currentTime - $normalizedTimestamp);

            return $difference <= self::TIMESTAMP_TOLERANCE;
        } catch (\Exception $e) {
            Log::warning('Error verifying webhook timestamp', [
                'timestamp' => $timestamp,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Normalize timestamp to Unix timestamp (seconds since epoch)
     *
     * @param string $timestamp Timestamp in ISO 8601 or Unix format
     * @return int Unix timestamp
     */
    protected function normalizeTimestamp(string $timestamp): int
    {
        // Check if it's already a Unix timestamp (all digits)
        if (ctype_digit($timestamp)) {
            return (int)$timestamp;
        }

        // Try to parse as ISO 8601
        try {
            return (int)\Carbon\Carbon::parse($timestamp)->timestamp;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid timestamp format: {$timestamp}");
        }
    }

    /**
     * Get the signature header name
     */
    public static function signatureHeader(): string
    {
        return self::SIGNATURE_HEADER;
    }

    /**
     * Get the timestamp header name
     */
    public static function timestampHeader(): string
    {
        return self::TIMESTAMP_HEADER;
    }

    /**
     * Generate a webhook secret (for creating webhooks)
     *
     * @return string A cryptographically random secret
     */
    public static function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }
}
