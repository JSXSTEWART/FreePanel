# Zapier Integration Guide

## Overview

FreePanel now supports real-time webhooks to Zapier, enabling you to automate workflows based on account events, domain changes, SSL certificate status, backups, and quota usage.

### 📚 Related Documentation

- **[Webhook Security](./WEBHOOK_SECURITY.md)** — HMAC-SHA256 signature verification (mandatory reading)
- **[High-Performance GitHub PR Complexity Scorer](./ZAPIER_WORKFLOW_GITHUB_PR_COMPLEXITY_HIGH_PERFORMANCE.md)** — Complete example workflow with structured prompts
- **[Lead Normalization Engine](./ZAPIER_WORKFLOW_LEAD_NORMALIZER_HIGH_PERFORMANCE.md)** — Data cleaning workflow (email, phone, address, company)
- **[Structured Prompts Reference](./ZAPIER_STRUCTURED_PROMPTS_REFERENCE.md)** — Quick reference for writing high-performance AI Code Steps

## Setup Instructions

### 1. Enable the Integration

Add the following environment variables to your `.env` file:

```env
ZAPIER_ENABLED=true
ZAPIER_WEBHOOK_ACCOUNT_CREATED=https://hooks.zapier.com/hooks/catch/YOUR_CATCH_ID_1/YOUR_TOKEN_1/
ZAPIER_WEBHOOK_ACCOUNT_SUSPENDED=https://hooks.zapier.com/hooks/catch/YOUR_CATCH_ID_2/YOUR_TOKEN_2/
ZAPIER_WEBHOOK_DOMAIN_CREATED=https://hooks.zapier.com/hooks/catch/YOUR_CATCH_ID_3/YOUR_TOKEN_3/
ZAPIER_WEBHOOK_SSL_EXPIRING=https://hooks.zapier.com/hooks/catch/YOUR_CATCH_ID_4/YOUR_TOKEN_4/
ZAPIER_WEBHOOK_BACKUP_COMPLETED=https://hooks.zapier.com/hooks/catch/YOUR_CATCH_ID_5/YOUR_TOKEN_5/
ZAPIER_WEBHOOK_QUOTA_EXCEEDED=https://hooks.zapier.com/hooks/catch/YOUR_CATCH_ID_6/YOUR_TOKEN_6/
```

### 2. Create Zapier Webhooks

1. Go to [https://zapier.com](https://zapier.com) and sign in
2. Create a new Zap for each event type you want to automate
3. Choose **Webhooks by Zapier** as the trigger app
4. Select **Catch Raw Hook** as the event
5. Copy the webhook URL provided by Zapier
6. Paste it into the corresponding `ZAPIER_WEBHOOK_*` environment variable

### 3. Build Your Automation

Once FreePanel sends a webhook to Zapier, you can:
- Add the account/domain data to Google Sheets
- Send notifications to Slack
- Create records in Notion
- Send emails via Gmail
- Add tasks to project management tools
- Trigger external APIs
- And much more!

## Available Events

### Account Events

#### `account.created`
Triggered when a new account is created.

**Payload:**
```json
{
  "event": "account.created",
  "timestamp": "2024-12-20T10:30:00Z",
  "data": {
    "id": 123,
    "username": "user123",
    "email": "user@example.com",
    "package_id": 5,
    "status": "active",
    "created_at": "2024-12-20T10:30:00Z"
  }
}
```

#### `account.suspended`
Triggered when an account is suspended.

**Payload:**
```json
{
  "event": "account.suspended",
  "timestamp": "2024-12-20T11:00:00Z",
  "data": {
    "id": 123,
    "username": "user123",
    "email": "user@example.com",
    "status": "suspended",
    "suspended_at": "2024-12-20T11:00:00Z"
  }
}
```

### Domain Events

#### `domain.created`
Triggered when a domain is added to an account.

#### `domain.deleted`
Triggered when a domain is removed.

### SSL Events

#### `ssl.expiring`
Triggered when an SSL certificate is approaching expiration.

**Payload:**
```json
{
  "event": "ssl.expiring",
  "timestamp": "2024-12-20T12:00:00Z",
  "data": {
    "domain_id": 456,
    "domain_name": "example.com",
    "certificate_id": 789,
    "issued_at": "2023-12-20T00:00:00Z",
    "expires_at": "2024-12-20T23:59:59Z",
    "days_until_expiry": 0
  }
}
```

#### `ssl.renewed`
Triggered when an SSL certificate is renewed or installed.

### Backup Events

#### `backup.completed`
Triggered when a backup completes successfully.

#### `backup.failed`
Triggered when a backup fails.

### Quota Events

#### `quota.exceeded`
Triggered when an account exceeds its resource quota.

## Example Workflows

### 1. New Account Welcome Email + Google Sheets Log

**Trigger:** Account Created
**Actions:**
1. Send welcome email via Gmail
2. Log account details to Google Sheets
3. Add task to Asana for account setup verification

### 2. SSL Certificate Expiring Alert

**Trigger:** SSL Expiring
**Actions:**
1. Send alert to Slack #infrastructure channel
2. Create GitHub issue for SSL renewal
3. Add reminder to Google Calendar

### 3. Backup Failure Notification

**Trigger:** Backup Failed
**Actions:**
1. Send critical alert to PagerDuty
2. Post message to Slack
3. Create incident in Notion database

## Testing

### Manual Test (via Artisan Tinker)

```bash
php artisan tinker
```

```php
$account = App\Models\Account::first();
event(new App\Events\Account\AccountCreated($account));
```

Check your Zapier webhook logs to verify the payload was received.

### Via HTTP Request

```bash
curl -X POST https://your-webhook-url.com \
  -H "Content-Type: application/json" \
  -d '{
    "event": "account.created",
    "timestamp": "2024-12-20T10:30:00Z",
    "data": {
      "id": 123,
      "username": "test_user",
      "email": "test@example.com",
      "package_id": 1,
      "status": "active",
      "created_at": "2024-12-20T10:30:00Z"
    }
  }'
```

## Troubleshooting

### Webhooks Not Being Sent

1. Ensure `ZAPIER_ENABLED=true` in `.env`
2. Verify webhook URLs are correct in `.env`
3. Check Laravel logs: `tail -f storage/logs/laravel.log`
4. Confirm events are being dispatched: `php artisan tinker` and manually dispatch an event

### Payload Format Issues

All payloads follow this structure:
```json
{
  "event": "string (event type)",
  "timestamp": "ISO 8601 datetime",
  "data": { "event-specific fields" }
}
```

If Zapier shows errors, verify this structure matches in your Zapier action configuration.

### Webhook URL Errors

- Ensure the Zapier webhook URL is complete and correct
- Test the URL directly with curl
- Check Zapier's webhook history for error details
- Regenerate the webhook URL if needed

## Security Considerations

⚠️ **Treat webhook URLs like passwords!** They can be used to send data to Zapier and trigger automations.

- Never commit webhook URLs to version control
- Use `.env` files and never track them in git
- Consider rotating webhook URLs periodically
- Use HTTPS-only connections
- Validate incoming data in Zapier (though webhook URLs are authenticated)

## Extending the Integration

To add webhooks for new events:

1. **Create an Event** (if it doesn't exist):
   ```bash
   php artisan make:event MyNewEvent
   ```

2. **Create a Listener**:
   ```bash
   php artisan make:listener SendMyNewEventToZapier --event=MyNewEvent
   ```

3. **Implement the Listener**:
   ```php
   public function handle(MyNewEvent $event): void
   {
       $this->zapierService->send('my.event', $event->data);
   }
   ```

4. **Register in EventServiceProvider**:
   ```php
   protected $listen = [
       MyNewEvent::class => [
           SendMyNewEventToZapier::class,
       ],
   ];
   ```

5. **Add Environment Variable**:
   ```env
   ZAPIER_WEBHOOK_MY_EVENT=https://hooks.zapier.com/...
   ```

## Support

For issues or feature requests related to the Zapier integration, contact support or file an issue in the FreePanel repository.
