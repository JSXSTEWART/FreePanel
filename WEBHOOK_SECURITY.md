# Zapier Webhook Security Guide

## Overview

FreePanel implements **HMAC-SHA256 signature verification** for all incoming webhooks from Zapier. This ensures that:

1. **Authentication**: Requests genuinely originate from Zapier
2. **Integrity**: Webhook payloads haven't been tampered with
3. **Replay Protection**: Timestamps prevent old webhooks from being replayed

## Signature Verification

### How It Works

Each webhook request includes two required headers:

- **`X-Zapier-Signature`**: HMAC-SHA256 signature of the payload
- **`X-Zapier-Timestamp`**: ISO 8601 or Unix timestamp of when the webhook was created

The signature is computed as:

```
signature = HMAC-SHA256(payload.timestamp, webhook_secret)
```

Where:
- `payload` = raw request body (exact bytes sent)
- `timestamp` = value from `X-Zapier-Timestamp` header
- `webhook_secret` = shared secret key (stored in `ZAPIER_WEBHOOK_SECRET` env var)

### Example Request

```http
POST /api/v1/webhooks/zapier/receive HTTP/1.1
Content-Type: application/json
X-Zapier-Signature: a7f3c2d8e1b9f4c6d3e2a1f8b7c6d5e4f3a2b1c0d9e8f7a6b5c4d3e2f1a0
X-Zapier-Timestamp: 2025-12-20T12:00:00Z

{
  "event": "account.created",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": {
    "id": 123,
    "username": "newuser"
  }
}
```

### Timestamp Validation

Timestamps must be:
- **Recent**: Within 5 minutes of server time (configurable via `ZAPIER_WEBHOOK_TIMESTAMP_TOLERANCE`)
- **Parseable**: Either ISO 8601 format or Unix timestamp (seconds since epoch)

This prevents **replay attacks** where an old webhook is resent.

### Signature Validation

The signature is validated using **timing-safe comparison** (`hash_equals`), which prevents **timing attacks**.

---

## Setup Instructions

### 1. Generate Webhook Secret

Generate a cryptographically secure secret:

```bash
php artisan zapier:generate-secret
```

This will:
- Create a 64-character hex string
- Automatically save to `.env` file as `ZAPIER_WEBHOOK_SECRET`
- Display the secret for sharing with Zapier

Output:
```
Webhook secret generated and saved to .env
Secret: a1b2c3d4e5f6...
⚠️ Keep this secret safe! Do not commit it to version control.
Share this secret with Zapier only.
```

### 2. Configure Environment Variables

Verify `.env` contains:

```env
ZAPIER_WEBHOOK_SECRET=a1b2c3d4e5f6g7h8i9j0...
ZAPIER_WEBHOOK_SIGNATURE_ENABLED=true
ZAPIER_WEBHOOK_TIMESTAMP_TOLERANCE=300
```

### 3. Share Secret with Zapier

1. Copy the generated secret from the command output
2. Add it to your Zapier MCP server configuration
3. Zapier will use this secret to sign all webhook requests

### 4. Test Webhook

Use the health endpoint to verify signature settings:

```bash
curl https://freepanel.com/api/v1/webhooks/zapier/health
```

Response:
```json
{
  "status": "healthy",
  "timestamp": "2025-12-20T12:00:00Z",
  "signature_verification": {
    "enabled": true,
    "algorithm": "sha256",
    "required_headers": [
      "X-Zapier-Signature",
      "X-Zapier-Timestamp"
    ],
    "signing_method": "HMAC-SHA256(payload.timestamp, secret)",
    "timestamp_tolerance_seconds": 300
  }
}
```

---

## Implementation Details

### WebhookSignatureService

Located in `app/Services/Zapier/WebhookSignatureService.php`

**Key Methods:**

```php
// Verify signature and timestamp
$service = app(\App\Services\Zapier\WebhookSignatureService::class);
$isValid = $service->verify(
    $payload,           // raw request body
    $signature,         // from X-Zapier-Signature header
    $timestamp,         // from X-Zapier-Timestamp header
    $secret             // from config/zapier.php
);

// Generate new secret
$secret = \App\Services\Zapier\WebhookSignatureService::generateSecret();

// Compute signature (for testing)
$signature = $service->computeSignature($payload, $timestamp, $secret);
```

### Middleware

Located in `app/Http/Middleware/VerifyZapierWebhookSignature.php`

Automatically applied to webhook routes:

```php
Route::post('zapier/receive', [WebhookController::class, 'receive'])
    ->middleware(\App\Http\Middleware\VerifyZapierWebhookSignature::class);
```

**Verification Flow:**

1. Check required headers are present
2. Get secret from config
3. Verify timestamp is recent
4. Compute expected signature
5. Use timing-safe comparison
6. Log all failures with context

---

## Error Responses

### Missing Headers

```http
HTTP/1.1 401 Unauthorized
Content-Type: application/json

{
  "error": "Missing webhook authentication headers",
  "required_headers": [
    "X-Zapier-Signature",
    "X-Zapier-Timestamp"
  ]
}
```

### Invalid Signature

```http
HTTP/1.1 403 Forbidden
Content-Type: application/json

{
  "error": "Invalid webhook signature"
}
```

### Timestamp Too Old

```http
HTTP/1.1 403 Forbidden
Content-Type: application/json

{
  "error": "Invalid webhook signature"
}
```

(Actual error message is generic to prevent timing attacks)

### Server Configuration Error

```http
HTTP/1.1 500 Internal Server Error
Content-Type: application/json

{
  "error": "Server configuration error"
}
```

---

## Testing Locally

### Using curl

Generate a test signature:

```php
php artisan tinker

$payload = json_encode(["event" => "test", "data" => []]);
$timestamp = now()->toIso8601String();
$secret = env('ZAPIER_WEBHOOK_SECRET');

$service = app(\App\Services\Zapier\WebhookSignatureService::class);
$signature = $service->computeSignature($payload, $timestamp, $secret);

echo "Payload: " . $payload . "\n";
echo "Timestamp: " . $timestamp . "\n";
echo "Signature: " . $signature . "\n";
```

Then send the request:

```bash
curl -X POST http://localhost:8000/api/v1/webhooks/zapier/receive \
  -H "Content-Type: application/json" \
  -H "X-Zapier-Signature: <signature>" \
  -H "X-Zapier-Timestamp: <timestamp>" \
  -d '<payload>'
```

### Using Postman

1. Create a new POST request to `/api/v1/webhooks/zapier/receive`
2. Set headers:
   - `Content-Type: application/json`
   - `X-Zapier-Signature: <computed signature>`
   - `X-Zapier-Timestamp: <current timestamp>`
3. Set body to test payload
4. Send request

### Using ngrok for Testing

```bash
# Start local server
php artisan serve

# In another terminal, expose to internet
ngrok http 8000

# Use ngrok URL in Zapier configuration
# https://abc123.ngrok.io/api/v1/webhooks/zapier/receive
```

---

## Monitoring & Logging

All webhook events are logged to `storage/logs/laravel.log`

### Successful Verification

```
[2025-12-20 12:00:00] local.DEBUG: Webhook signature verified successfully
```

### Failed Verification

```
[2025-12-20 12:00:00] local.WARNING: Webhook signature verification failed: invalid signature [] []
[2025-12-20 12:00:00] local.WARNING: Webhook signature verification failed: signature mismatch {
  "provided": "a7f3c2d8e1b9f4c...",
  "expected": "b8g4d3e9f2c5a1d..."
}
```

### Missing Headers

```
[2025-12-20 12:00:00] local.WARNING: Webhook signature verification failed: missing headers {
  "has_signature": false,
  "has_timestamp": true,
  "path": "api/v1/webhooks/zapier/receive"
}
```

### Suspicious Activity

```
[2025-12-20 12:00:00] local.WARNING: Webhook signature verification failed: invalid signature {
  "path": "api/v1/webhooks/zapier/receive",
  "ip": "192.168.1.1",
  "user_agent": "Mozilla/5.0..."
}
```

---

## Security Best Practices

### 1. Secret Management

✅ **DO:**
- Keep `ZAPIER_WEBHOOK_SECRET` in `.env` file (not committed to git)
- Use strong, randomly generated secrets
- Rotate secrets if compromised
- Store in secure secret management system (e.g., HashiCorp Vault)

❌ **DON'T:**
- Commit secrets to version control
- Use weak or predictable secrets
- Share secrets in logs or error messages
- Reuse secrets across environments

### 2. HTTPS Only

✅ Always use HTTPS for webhook endpoints:

```env
ZAPIER_WEBHOOK_ENDPOINT=https://freepanel.com/api/v1/webhooks/zapier/receive
```

❌ Never use HTTP (unencrypted)

### 3. Timestamp Validation

The default 5-minute tolerance is suitable for most use cases. Adjust only if needed:

```env
ZAPIER_WEBHOOK_TIMESTAMP_TOLERANCE=300  # seconds
```

For stricter validation (e.g., 1 minute):
```env
ZAPIER_WEBHOOK_TIMESTAMP_TOLERANCE=60
```

### 4. Replay Attack Prevention

- Webhook signatures include timestamps
- Timestamps are validated to be recent
- Attackers cannot replay old webhooks (even if they have the signature)

### 5. Monitoring

- Enable logging for all webhook activity
- Alert on repeated signature failures (potential attack)
- Monitor from unexpected IP addresses
- Set up alerts for configuration errors

### 6. Graceful Degradation

If signature verification is temporarily disabled:

```env
ZAPIER_WEBHOOK_SIGNATURE_ENABLED=false
```

⚠️ **Only use during debugging/testing. Enable in production!**

### 7. Handling Compromised Secrets

If you suspect a secret is compromised:

```bash
# Generate new secret
php artisan zapier:generate-secret

# Update Zapier configuration with new secret
# (Zapier will need to be reconfigured)

# Revoke old secret by rotating
```

---

## API Reference

### Webhook Signature Service

```php
namespace App\Services\Zapier;

class WebhookSignatureService
{
    // Verify signature and timestamp
    public function verify(
        string $payload,
        string $signature,
        string $timestamp,
        string $secret
    ): bool

    // Compute signature for testing
    public function computeSignature(
        string $payload,
        string $timestamp,
        string $secret
    ): string

    // Generate new random secret
    public static function generateSecret(): string

    // Get header names
    public static function signatureHeader(): string      // X-Zapier-Signature
    public static function timestampHeader(): string      // X-Zapier-Timestamp
}
```

### Configuration

```php
// config/zapier.php
return [
    'signature' => [
        'enabled' => env('ZAPIER_WEBHOOK_SIGNATURE_ENABLED', true),
        'secret' => env('ZAPIER_WEBHOOK_SECRET'),
        'timestamp_tolerance' => env('ZAPIER_WEBHOOK_TIMESTAMP_TOLERANCE', 300),
        'algorithm' => 'sha256',
    ],
];
```

---

## Troubleshooting

### "Invalid webhook signature"

**Causes:**
1. Wrong secret
2. Secret not configured
3. Payload modified in transit
4. Wrong signing method

**Solution:**
- Verify `ZAPIER_WEBHOOK_SECRET` is set correctly
- Check logs for signature mismatch details
- Regenerate secret if suspected compromise
- Ensure Zapier is using same secret

### "Missing webhook authentication headers"

**Causes:**
1. Zapier not sending headers
2. Headers lost by intermediate proxy
3. Middleware misconfiguration

**Solution:**
- Check Zapier settings send both headers
- Verify HTTP proxy configuration
- Check middleware is applied to route

### "Timestamp too old"

**Causes:**
1. Server clock out of sync
2. Zapier server clock out of sync
3. Tolerance too strict

**Solution:**
- Sync server clocks (NTP)
- Check Zapier server time
- Increase tolerance if needed
- Check logs for exact time difference

---

## FAQ

**Q: Why use timestamps in the signature?**
A: Prevents replay attacks. An attacker who captures a webhook cannot resend it hours later.

**Q: Can I disable signature verification?**
A: Only for testing. It's a critical security feature. Always enable in production.

**Q: What if the secret is leaked?**
A: Generate a new one with `php artisan zapier:generate-secret` and update Zapier configuration.

**Q: Can I rotate secrets without downtime?**
A: Not directly. You'd need to support multiple secrets temporarily, then switch.

**Q: How often should I rotate secrets?**
A: Rotate if compromised. Consider periodic rotation (annually) as best practice.

**Q: Does signature verification slow down webhooks?**
A: Negligibly. HMAC-SHA256 is very fast (< 1ms).

