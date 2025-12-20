# FreePanel Zapier Webhook Integration Guide

## ⚠️ Security Notice

All incoming webhooks are **cryptographically signed** using HMAC-SHA256. See [WEBHOOK_SECURITY.md](WEBHOOK_SECURITY.md) for detailed security implementation and setup.

## Overview

FreePanel integrates with Zapier through **secure webhooks**, enabling automated workflows and multi-app integrations. This integration includes:

- **Inbound Webhooks**: Receive signed payloads from Zapier to trigger FreePanel actions
- **Outbound Webhooks**: Send FreePanel events to Zapier for automation
- **Event Listeners**: Automatic webhook dispatch when FreePanel events occur
- **Signature Verification**: HMAC-SHA256 validation to ensure authenticity

## Architecture

### Components

1. **MCP Configuration** (`mcp.json`)
   - Defines webhook capabilities and payload schemas
   - Registers supported events and serialization formats
   - Documents signature verification requirements

2. **Webhook Service** (`app/Services/Zapier/ZapierWebhookService.php`)
   - Handles webhook payload formatting (JSON, form-encoded, XML)
   - Manages webhook transmission with retry logic
   - Supports batch webhook operations

3. **Webhook Controller** (`app/Http/Controllers/Api/V1/WebhookController.php`)
   - Receives inbound webhooks from Zapier
   - Parses multiple payload formats
   - Routes events to application listeners

4. **Webhook Signature Service** (`app/Services/Zapier/WebhookSignatureService.php`)
   - Computes HMAC-SHA256 signatures
   - Verifies incoming webhook signatures
   - Validates timestamps to prevent replay attacks

5. **Signature Verification Middleware** (`app/Http/Middleware/VerifyZapierWebhookSignature.php`)
   - Authenticates webhook requests
   - Rejects unauthenticated/forged payloads
   - Logs all verification attempts

6. **Event Listeners** (`app/Listeners/*`)
   - Dispatch webhooks for FreePanel events
   - Support queueable operations for async processing
   - Handle payload transformation

7. **Webhook Model** (`app/Models/ZapierWebhook.php`)
   - Store webhook registrations per account
   - Track webhook health (failures, last triggered)
   - Auto-disable after 10 consecutive failures

## Supported Events

### Account Events

#### `account.created`
Triggered when a new hosting account is created.

**Payload Schema:**
```json
{
  "event": "account.created",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": {
    "id": 123,
    "username": "username",
    "email": "user@example.com",
    "package_id": 5,
    "created_at": "2025-12-20T12:00:00Z"
  }
}
```

**Listener:** `SendAccountCreatedWebhook`

---

#### `account.suspended`
Triggered when an account is suspended.

**Payload Schema:**
```json
{
  "event": "account.suspended",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": {
    "id": 123,
    "username": "username",
    "reason": "Payment overdue"
  }
}
```

**Listener:** `SendAccountSuspendedWebhook`

---

#### `account.updated`
Triggered when account details are updated.

**Payload Schema:**
```json
{
  "event": "account.updated",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": {
    "id": 123,
    "username": "username",
    "changed_fields": ["email", "package_id"]
  }
}
```

---

#### `account.deleted`
Triggered when an account is permanently deleted.

**Payload Schema:**
```json
{
  "event": "account.deleted",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": {
    "id": 123,
    "username": "username",
    "deleted_at": "2025-12-20T12:00:00Z"
  }
}
```

---

#### `quota.exceeded`
Triggered when an account exceeds its resource quota.

**Payload Schema:**
```json
{
  "event": "quota.exceeded",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": {
    "account_id": 123,
    "resource_type": "disk_space",
    "limit": 1099511627776,
    "usage": 1099511627776,
    "timestamp": "2025-12-20T12:00:00Z"
  }
}
```

**Listener:** `SendQuotaExceededWebhook`

---

### Domain Events

#### `domain.created`
Triggered when a domain is added to an account.

**Payload Schema:**
```json
{
  "event": "domain.created",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": {
    "id": 456,
    "account_id": 123,
    "name": "example.com",
    "created_at": "2025-12-20T12:00:00Z"
  }
}
```

**Listener:** `SendDomainCreatedWebhook`

---

#### `domain.deleted`
Triggered when a domain is removed from an account.

**Payload Schema:**
```json
{
  "event": "domain.deleted",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": {
    "id": 456,
    "account_id": 123,
    "name": "example.com",
    "deleted_at": "2025-12-20T12:00:00Z"
  }
}
```

**Listener:** `SendDomainDeletedWebhook`

---

### SSL Certificate Events

#### `ssl.expiring`
Triggered when an SSL certificate is about to expire (30, 14, 7 days).

**Payload Schema:**
```json
{
  "event": "ssl.expiring",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": {
    "id": 789,
    "domain_id": 456,
    "domain": "example.com",
    "expires_at": "2026-01-19T12:00:00Z",
    "days_remaining": 30
  }
}
```

**Listener:** `SendSslExpiringWebhook`

---

#### `ssl.renewed`
Triggered when an SSL certificate is renewed or newly installed.

**Payload Schema:**
```json
{
  "event": "ssl.renewed",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": {
    "id": 789,
    "domain_id": 456,
    "domain": "example.com",
    "installed_at": "2025-12-20T12:00:00Z",
    "expires_at": "2026-12-20T12:00:00Z"
  }
}
```

**Listener:** `SendSslRenewedWebhook`

---

### Backup Events

#### `backup.completed`
Triggered when a backup completes successfully.

**Payload Schema:**
```json
{
  "event": "backup.completed",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": {
    "id": 321,
    "account_id": 123,
    "size_bytes": 5368709120,
    "completed_at": "2025-12-20T12:00:00Z"
  }
}
```

**Listener:** `SendBackupCompletedWebhook`

---

#### `backup.failed`
Triggered when a backup operation fails.

**Payload Schema:**
```json
{
  "event": "backup.failed",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": {
    "id": 321,
    "account_id": 123,
    "error": "Insufficient disk space",
    "failed_at": "2025-12-20T12:00:00Z"
  }
}
```

**Listener:** `SendBackupFailedWebhook`

---

## API Endpoints

### Receive Webhook (Public)

```http
POST /api/v1/webhooks/zapier/receive
Content-Type: application/json

{
  "event": "account.created",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": { ... }
}
```

**Request Headers (Required):**
```
X-Zapier-Signature: a7f3c2d8e1b9f4c6d3e2a1f8b7c6d5e4f3a2b1c0d9e8f7a6b5c4d3e2f1a0
X-Zapier-Timestamp: 2025-12-20T12:00:00Z
Content-Type: application/json
```

**Signature Verification:**
- Signature is HMAC-SHA256 of `payload.timestamp` using shared secret
- Timestamp must be within 5 minutes (configurable)
- See [WEBHOOK_SECURITY.md](WEBHOOK_SECURITY.md) for detailed security implementation

**Response:**
```json
{
  "success": true
}
```

**Supported Content-Types:**
- `application/json`
- `application/x-www-form-urlencoded`
- `application/xml`

**Error Responses:**

Missing headers (401):
```json
{
  "error": "Missing webhook authentication headers",
  "required_headers": ["X-Zapier-Signature", "X-Zapier-Timestamp"]
}
```

Invalid signature (403):
```json
{
  "error": "Invalid webhook signature"
}
```

---

### Webhook Health Check (Public)

```http
GET /api/v1/webhooks/zapier/health
```

**Response:**
```json
{
  "status": "healthy",
  "timestamp": "2025-12-20T12:00:00Z",
  "version": "1.0",
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

### Send Webhook (Authenticated)

```http
POST /api/v1/webhooks/send
Authorization: Bearer {token}
Content-Type: application/json

{
  "event": "account.created",
  "url": "https://hooks.zapier.com/hooks/catch/...",
  "data": { ... },
  "format": "json"
}
```

**Response:**
```json
{
  "success": true,
  "event": "account.created"
}
```

**Parameters:**
- `event` (string, required): Event type identifier
- `url` (string, required): Target webhook URL
- `data` (object, required): Payload data
- `format` (string, optional): `json`, `form-encoded`, or `xml` (default: `json`)

---

## Payload Formats

### JSON (Default)

```json
{
  "event": "account.created",
  "timestamp": "2025-12-20T12:00:00Z",
  "data": {
    "id": 123,
    "username": "user"
  }
}
```

**Content-Type:** `application/json`

---

### Form-Encoded

```
event=account.created&timestamp=2025-12-20T12%3A00%3A00Z&data[id]=123&data[username]=user
```

**Content-Type:** `application/x-www-form-urlencoded`

---

### XML

```xml
<?xml version="1.0"?>
<root>
  <event>account.created</event>
  <timestamp>2025-12-20T12:00:00Z</timestamp>
  <data>
    <id>123</id>
    <username>user</username>
  </data>
</root>
```

**Content-Type:** `application/xml`

---

## Configuration

### Generate Webhook Secret

Before you begin, generate a secure webhook secret:

```bash
php artisan zapier:generate-secret
```

This creates a 64-character cryptographically random secret and saves it to `.env` as `ZAPIER_WEBHOOK_SECRET`.

### Environment Variables

Add to `.env`:

```env
# Webhook signature verification (REQUIRED for security)
ZAPIER_WEBHOOK_SECRET=a1b2c3d4e5f6g7h8i9j0...
ZAPIER_WEBHOOK_SIGNATURE_ENABLED=true
ZAPIER_WEBHOOK_TIMESTAMP_TOLERANCE=300

# Webhook formatting
ZAPIER_WEBHOOK_FORMAT=json
ZAPIER_WEBHOOK_TIMEOUT=10
ZAPIER_WEBHOOK_RETRY_COUNT=3

# Outbound webhook URLs
ZAPIER_WEBHOOK_ACCOUNT_CREATED=https://hooks.zapier.com/hooks/catch/...
ZAPIER_WEBHOOK_ACCOUNT_SUSPENDED=https://hooks.zapier.com/hooks/catch/...
ZAPIER_WEBHOOK_DOMAIN_CREATED=https://hooks.zapier.com/hooks/catch/...
# ... other webhook URLs
```

### Zapier Configuration

In `config/zapier.php`:

```php
return [
    'webhooks' => [
        'account.created' => env('ZAPIER_WEBHOOK_ACCOUNT_CREATED'),
        'account.suspended' => env('ZAPIER_WEBHOOK_ACCOUNT_SUSPENDED'),
        'domain.created' => env('ZAPIER_WEBHOOK_DOMAIN_CREATED'),
        // ... other webhooks
    ],
    
    'signature' => [
        'enabled' => env('ZAPIER_WEBHOOK_SIGNATURE_ENABLED', true),
        'secret' => env('ZAPIER_WEBHOOK_SECRET'),
        'timestamp_tolerance' => env('ZAPIER_WEBHOOK_TIMESTAMP_TOLERANCE', 300),
        'algorithm' => 'sha256',
    ],
    
    'format' => env('ZAPIER_WEBHOOK_FORMAT', 'json'),
    'timeout' => env('ZAPIER_WEBHOOK_TIMEOUT', 10),
];
```

---

## Usage Examples

### Dispatch Event and Webhook

When an account is created in FreePanel, the `AccountCreated` event is dispatched. The `SendAccountCreatedWebhook` listener automatically sends the webhook:

```php
// In your controller or service
event(new \App\Events\Account\AccountCreated($account));

// The listener automatically sends:
// POST https://hooks.zapier.com/hooks/catch/...
// {
//   "event": "account.created",
//   "timestamp": "2025-12-20T12:00:00Z",
//   "data": { "id": 123, "username": "user", ... }
// }
```

---

### Programmatically Send Webhook

```php
use App\Services\Zapier\ZapierWebhookService;

$webhookService = app(ZapierWebhookService::class);

// Send JSON webhook
$webhookService->send('account.created', [
    'id' => 123,
    'username' => 'user',
]);

// Send form-encoded webhook
$webhookService
    ->setFormat('form-encoded')
    ->send('account.created', $data);

// Send batch webhooks
$webhookService->sendBatch([
    ['event' => 'account.created', 'data' => [...], 'headers' => []],
    ['event' => 'domain.created', 'data' => [...], 'headers' => []],
]);
```

---

### Receive and Process Webhook

Zapier sends a POST request to `/api/v1/webhooks/zapier/receive`. FreePanel:

1. Parses the payload based on `Content-Type`
2. Validates the event type
3. Dispatches Laravel events
4. Triggers registered listeners
5. Returns success response

```php
// Example Zapier webhook trigger
curl -X POST https://freepanel.com/api/v1/webhooks/zapier/receive \
  -H "Content-Type: application/json" \
  -d '{
    "event": "account.created",
    "timestamp": "2025-12-20T12:00:00Z",
    "data": {
      "id": 123,
      "username": "newuser",
      "email": "user@example.com"
    }
  }'
```

---

## Webhook Reliability

### Failure Handling

- **Automatic Retries**: Failed webhooks are retried up to 3 times
- **Logging**: All webhook activity is logged for debugging
- **Failure Tracking**: `ZapierWebhook` model tracks failures per webhook
- **Auto-Disable**: Webhooks disabled after 10 consecutive failures
- **Error Messages**: Last error stored in `last_error` field

### Monitoring

Check webhook health:

```php
$webhooks = \App\Models\ZapierWebhook::active()
    ->where('failure_count', '>', 0)
    ->get();

foreach ($webhooks as $webhook) {
    echo "Webhook {$webhook->id}: {$webhook->failure_count} failures";
    echo "Last error: {$webhook->last_error}";
}
```

---

## Security Considerations

### Webhook URL Protection

- Treat webhook URLs as **secrets** (like passwords)
- Do not commit URLs to version control
- Use environment variables for all webhook URLs
- Rotate URLs if compromised

### Empty Payloads

Zapier ignores completely empty webhook requests. FreePanel always includes:
- `event` field (required)
- `timestamp` field (required)
- `data` object (may be empty, but field exists)

### Payload Validation

- Verify `event` field matches expected value
- Validate `timestamp` is recent (< 5 minutes old)
- Schema-validate `data` payload
- Check request origin if using API keys

---

## Testing

### Health Check

```bash
curl https://freepanel.com/api/v1/webhooks/zapier/health
```

### Test Webhook Delivery

```bash
curl -X POST https://freepanel.com/api/v1/webhooks/zapier/receive \
  -H "Content-Type: application/json" \
  -d '{
    "event": "test.webhook",
    "timestamp": "2025-12-20T12:00:00Z",
    "data": {"test": true}
  }'
```

### Using Postman

1. Create new POST request to `https://freepanel.com/api/v1/webhooks/zapier/receive`
2. Set Content-Type header to `application/json`
3. Set body to webhook payload
4. Send and check response

---

## Troubleshooting

### Webhooks Not Being Triggered

1. **Check Configuration**: Verify webhook URLs in `.env`
2. **Check Event Dispatch**: Confirm events are being dispatched
3. **Check Listeners**: Verify listeners are registered in `EventServiceProvider`
4. **Check Queue**: If using queues, verify queue worker is running

### Webhooks Failing

1. **Check Logs**: `storage/logs/laravel.log`
2. **Check Network**: Test webhook URL accessibility
3. **Check Payload**: Validate payload format matches expectations
4. **Check Signature**: Verify signature headers are correct
5. **Check Timestamp**: Verify server clocks are synchronized
6. **Check Secret**: Verify `ZAPIER_WEBHOOK_SECRET` matches Zapier config

### Signature Verification Failures

See [WEBHOOK_SECURITY.md](WEBHOOK_SECURITY.md) **Troubleshooting** section for detailed guidance.

### Missing Events

1. **Verify Event Dispatch**: Add `\Log::info()` in event dispatcher
2. **Check Listener Registration**: Verify listener in `EventServiceProvider`
3. **Check Queue Processing**: Run `php artisan queue:work` locally
4. **Check Conditions**: Some events may have conditions (e.g., only active accounts)

---

## Migration & Setup

```bash
# 1. Generate webhook secret (required for security!)
php artisan zapier:generate-secret

# 2. Run migrations
php artisan migrate

# 3. Register listeners in EventServiceProvider
# See app/Providers/EventServiceProvider.php

# 4. Verify signature verification middleware is applied
# In routes/api.php - already configured

# 5. Test webhook health (should show signature settings)
curl https://freepanel.com/api/v1/webhooks/zapier/health

# 6. Create test webhook (authenticated)
curl -X POST https://freepanel.com/api/v1/webhooks/send \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "event": "test",
    "url": "https://hooks.zapier.com/hooks/catch/...",
    "data": {"test": true}
  }'
```

---

## Best Practices

1. **Use Environment Variables**: Never hardcode webhook URLs
2. **Handle Async**: Use queueable listeners for background processing
3. **Validate Payloads**: Check event type and required fields
4. **Log Everything**: Enable webhook logging for debugging
5. **Monitor Health**: Regularly check webhook failure counts
6. **Test Locally**: Use ngrok or Postman to test webhooks
7. **Document Events**: Keep event payload schemas up-to-date
8. **Handle Duplicates**: Webhooks may be sent multiple times
9. **Use Idempotent Keys**: Include unique IDs in payloads
10. **Rate Limit**: Be aware of Zapier webhook rate limits

