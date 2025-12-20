# FreePanel Zapier Webhook Integration — Implementation Summary

## Overview
Comprehensive Zapier webhook integration for FreePanel, enabling bidirectional event flows between FreePanel and Zapier automation platform.

## What Was Implemented

### 1. **Enhanced MCP Configuration** (`mcp.json`)
- Registered Zapier as an "other" type MCP server
- Documented 11 supported FreePanel events with payload schemas
- Defined webhook serialization formats (JSON, form-encoded, XML)
- Specified webhook endpoints and authentication requirements
- Added payload format examples for integration reference

**Supported Events:**
- Account: created, suspended, updated, deleted
- Domain: created, deleted
- SSL: expiring, renewed
- Backup: completed, failed
- Quota: exceeded

---

### 2. **Enhanced Webhook Service** (`app/Services/Zapier/ZapierWebhookService.php`)
- **Payload Formatting**: Support for JSON, form-encoded, and XML formats
- **Flexible Transmission**: Set format per webhook or batch operation
- **Header Management**: Custom headers with event metadata
- **Batch Operations**: Send multiple webhooks in one operation
- **Error Handling**: Comprehensive logging and failure tracking
- **Retry Logic**: Automatic retry with timeout management

**Key Methods:**
- `send()` — Send single event webhook
- `sendBatch()` — Send multiple webhooks
- `setFormat()` — Change serialization format
- `buildPayload()` — Format payload based on type

---

### 3. **Webhook Controller** (`app/Http/Controllers/Api/V1/WebhookController.php`)
- **Receive Webhooks**: Public endpoint at `/api/v1/webhooks/zapier/receive`
- **Multi-Format Support**: Parse JSON, form-encoded, and XML payloads
- **Content-Type Detection**: Automatic format detection from headers
- **Health Check**: Public `/api/v1/webhooks/zapier/health` endpoint
- **Send Webhooks**: Authenticated endpoint to dispatch webhooks programmatically
- **Event Dispatch**: Routes incoming webhooks to Laravel event system

---

### 4. **API Routes** (`routes/api.php`)
```php
// Public routes
POST /api/v1/webhooks/zapier/receive    // Receive webhooks from Zapier
GET  /api/v1/webhooks/zapier/health     // Health check

// Authenticated routes
POST /api/v1/webhooks/send               // Send webhook (auth required)
```

---

### 5. **Event Listeners** (8 New Listeners)

#### Account Listeners
- `SendAccountCreatedWebhook` — Dispatches when account created
- `SendAccountSuspendedWebhook` — Dispatches when account suspended
- `SendQuotaExceededWebhook` — Dispatches when quota exceeded

#### Domain Listeners
- `SendDomainCreatedWebhook` — Dispatches when domain created
- `SendDomainDeletedWebhook` — Dispatches when domain deleted

#### SSL Listeners
- `SendSslExpiringWebhook` — Dispatches when certificate expiring
- `SendSslRenewedWebhook` — Dispatches when certificate renewed

#### Backup Listeners
- `SendBackupCompletedWebhook` — Dispatches when backup completes
- `SendBackupFailedWebhook` — Dispatches when backup fails

**Features:**
- All listeners use `ShouldQueue` for async dispatch
- Automatic payload transformation from model data
- ISO 8601 timestamp formatting
- Graceful handling of missing relationships

---

### 6. **Webhook Management Model** (`app/Models/ZapierWebhook.php`)
- Store per-account webhook registrations
- Track webhook health (failures, last trigger)
- Support multiple events per account
- Auto-disable after 10 consecutive failures
- Helper methods:
  - `recordSuccess()` — Update success timestamp
  - `recordFailure()` — Log failure and disable if threshold reached
  - `scopeActive()` — Query active webhooks
  - `scopeForEvent()` — Query by event type

---

### 7. **Database Migration** (`database/migrations/2025_12_20_000000_create_zapier_webhooks_table.php`)
```sql
CREATE TABLE zapier_webhooks (
  id PRIMARY KEY
  account_id FOREIGN KEY (cascadeOnDelete)
  event_type VARCHAR (e.g., 'account.created')
  webhook_url TEXT (Zapier webhook URL)
  format ENUM ('json', 'form-encoded', 'xml')
  is_active BOOLEAN
  last_triggered_at TIMESTAMP
  failure_count INTEGER
  last_error TEXT
  timestamps (created_at, updated_at)
  
  indexes:
  - (account_id, event_type)
  - (is_active, event_type)
  - (created_at)
)
```

---

### 8. **Comprehensive Documentation** (`ZAPIER_WEBHOOK_INTEGRATION.md`)
- Event catalog with full payload schemas
- API endpoint reference and examples
- Configuration instructions
- Payload format specifications
- Usage examples and code snippets
- Reliability and monitoring guidance
- Security best practices
- Testing and troubleshooting guide

---

## Architecture Diagram

```
FreePanel Event System
        │
        ├─ Event Dispatched (e.g., AccountCreated)
        │
        └─ EventServiceProvider
             │
             └─ SendAccountCreatedWebhook Listener
                  │
                  └─ ZapierWebhookService
                       │
                       ├─ Format Payload
                       │  ├─ JSON
                       │  ├─ Form-Encoded
                       │  └─ XML
                       │
                       ├─ Add Headers
                       │  ├─ Content-Type
                       │  ├─ User-Agent
                       │  ├─ X-Event
                       │  └─ X-Timestamp
                       │
                       └─ HTTP POST to Zapier Webhook URL
                            │
                            └─ Log Success/Failure
                                 │
                                 └─ Update ZapierWebhook Model

Zapier Incoming Webhooks
        │
        └─ POST /api/v1/webhooks/zapier/receive
             │
             ├─ Parse Payload (JSON/Form/XML)
             │
             ├─ Validate Event
             │
             └─ Dispatch Laravel Event
                  │
                  └─ Route to Registered Listeners
```

---

## Integration Workflow

### 1. Outbound: FreePanel → Zapier

```
Account Created in FreePanel
        │
        └─ Dispatch AccountCreated Event
             │
             └─ SendAccountCreatedWebhook Listener
                  │
                  └─ ZapierWebhookService::send()
                       │
                       ├─ Build Payload:
                       │  {
                       │    "event": "account.created",
                       │    "timestamp": "2025-12-20T12:00:00Z",
                       │    "data": {
                       │      "id": 123,
                       │      "username": "user",
                       │      "email": "user@example.com"
                       │    }
                       │  }
                       │
                       ├─ POST to Zapier Webhook
                       │
                       └─ Log & Track Result
```

### 2. Inbound: Zapier → FreePanel

```
Zapier Triggers FreePanel Webhook
        │
        └─ POST /api/v1/webhooks/zapier/receive
             │
             ├─ Parse Payload
             │
             ├─ Validate Format
             │
             └─ Dispatch webhook.received Event
                  │
                  └─ Route to Listeners
                       │
                       └─ Custom Handler
                            │
                            └─ Perform Action
```

---

## Usage Examples

### Register Webhook Listener

In `app/Providers/EventServiceProvider.php`:

```php
protected $listen = [
    AccountCreated::class => [
        SendAccountCreatedWebhook::class,
    ],
];
```

### Dispatch Event (Automatic Webhook)

```php
// In any controller/service
event(new \App\Events\Account\AccountCreated($account));

// Automatically sends webhook to Zapier!
```

### Send Webhook Programmatically

```php
$service = app(\App\Services\Zapier\ZapierWebhookService::class);

$service->send('account.created', [
    'id' => 123,
    'username' => 'john_doe',
    'email' => 'john@example.com',
]);
```

### Batch Webhooks

```php
$service->sendBatch([
    ['event' => 'account.created', 'data' => [...], 'headers' => []],
    ['event' => 'domain.created', 'data' => [...], 'headers' => []],
]);
```

### Change Payload Format

```php
$service
    ->setFormat('form-encoded')
    ->send('account.created', $data);
```

---

## Configuration

### Environment Variables

```env
# .env
ZAPIER_ACCOUNT_CREATED_WEBHOOK=https://hooks.zapier.com/hooks/catch/...
ZAPIER_ACCOUNT_SUSPENDED_WEBHOOK=https://hooks.zapier.com/hooks/catch/...
ZAPIER_DOMAIN_CREATED_WEBHOOK=https://hooks.zapier.com/hooks/catch/...
# ... etc
```

### Register Listeners

Update `EventServiceProvider`:

```php
protected $listen = [
    \App\Events\Account\AccountCreated::class => [
        \App\Listeners\Account\SendAccountCreatedWebhook::class,
    ],
    \App\Events\Account\AccountSuspended::class => [
        \App\Listeners\Account\SendAccountSuspendedWebhook::class,
    ],
    \App\Events\Domain\DomainCreated::class => [
        \App\Listeners\Domain\SendDomainCreatedWebhook::class,
    ],
    \App\Events\Domain\DomainDeleted::class => [
        \App\Listeners\Domain\SendDomainDeletedWebhook::class,
    ],
];
```

---

## Next Steps

1. **Run Migration**
   ```bash
   php artisan migrate
   ```

2. **Register Listeners** (update EventServiceProvider if not auto-discovered)

3. **Set Environment Variables** with actual Zapier webhook URLs

4. **Test Webhook**
   ```bash
   curl https://freepanel.com/api/v1/webhooks/zapier/health
   ```

5. **Monitor Logs**
   ```bash
   tail -f storage/logs/laravel.log | grep webhook
   ```

6. **Create Zapier Zap** using FreePanel as trigger

---

## Files Created/Modified

### New Files
- `app/Http/Controllers/Api/V1/WebhookController.php`
- `app/Services/Zapier/ZapierWebhookService.php` (enhanced)
- `app/Models/ZapierWebhook.php`
- `app/Listeners/Account/SendAccountCreatedWebhook.php`
- `app/Listeners/Account/SendAccountSuspendedWebhook.php`
- `app/Listeners/Account/SendQuotaExceededWebhook.php`
- `app/Listeners/Domain/SendDomainCreatedWebhook.php`
- `app/Listeners/Domain/SendDomainDeletedWebhook.php`
- `app/Listeners/Ssl/SendSslExpiringWebhook.php`
- `app/Listeners/Ssl/SendSslRenewedWebhook.php`
- `app/Listeners/Backup/SendBackupCompletedWebhook.php`
- `app/Listeners/Backup/SendBackupFailedWebhook.php`
- `database/migrations/2025_12_20_000000_create_zapier_webhooks_table.php`
- `ZAPIER_WEBHOOK_INTEGRATION.md`

### Modified Files
- `routes/api.php` (added webhook routes and controller import)
- `vscode-userdata:/User/mcp.json` (enhanced MCP configuration)

---

## Testing Checklist

- [ ] Health check endpoint returns 200
- [ ] Webhook listener is registered in EventServiceProvider
- [ ] Event is dispatched when account created
- [ ] Webhook sent to Zapier (check logs)
- [ ] Payload format is correct (JSON/form/XML)
- [ ] Incoming webhook endpoint works (POST /webhooks/zapier/receive)
- [ ] Incoming webhook parses multiple formats
- [ ] Headers include event metadata
- [ ] Failures are logged properly
- [ ] ZapierWebhook model records success/failure

---

## Support & Troubleshooting

See [ZAPIER_WEBHOOK_INTEGRATION.md](ZAPIER_WEBHOOK_INTEGRATION.md) for:
- Complete API reference
- Event payload specifications
- Configuration instructions
- Testing procedures
- Troubleshooting guide
- Security best practices

