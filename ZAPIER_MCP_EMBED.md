# Zapier MCP Embed Integration

## Overview

FreePanel now supports **Zapier MCP embed**, allowing each user to connect their own Zapier account and execute automation tools directly from FreePanel.

## Architecture

### Multi-Tenant Model
- **Each FreePanel user** gets their own Zapier MCP connection
- Users connect via the Zapier MCP embed interface
- Server URLs are stored per-user in the database
- Backend authenticates using your **embed secret** to execute tools on behalf of users

### How It Works
1. User clicks "Connect Zapier" in FreePanel
2. Zapier MCP embed loads and user authenticates
3. Zapier dispatches an `mcp-server-url` event with user's unique server URL
4. FreePanel stores the URL associated with the user
5. Backend can now execute Zapier tools for that user

## Setup Instructions

### 1. Environment Variables

Add to `.env`:
```env
ZAPIER_EMBED_ID=5728245c-97fc-4927-a279-1c8dcc0c526d
ZAPIER_EMBED_SECRET=ZW7Iu7jUb78tRjCIzuC2O39rJG1HF-y1oYMQ6F_JPHE
```

### 2. Run Database Migration

```bash
php artisan migrate
```

This creates the `user_zapier_connections` table.

### 3. Add Frontend Route

In your frontend router, add:

```typescript
import ZapierConnect from './components/ZapierConnect';

// In your routes:
<Route path="/integrations/zapier" element={<ZapierConnect />} />
```

### 4. Test the Integration

1. Navigate to `/integrations/zapier` in FreePanel
2. Click "Connect Your Zapier Account"
3. Authenticate with Zapier
4. The connection should be established automatically

## API Endpoints

All endpoints require `auth:sanctum` middleware.

### Connect Zapier

```http
POST /api/v1/zapier/connect
Content-Type: application/json

{
  "mcp_server_url": "https://mcp.zapier.com/api/mcp/s/..."
}
```

**Response:**
```json
{
  "message": "Zapier connection established successfully",
  "connection": {
    "id": 1,
    "user_id": 123,
    "mcp_server_url": "https://...",
    "is_active": true,
    "connected_at": "2025-12-20T10:00:00Z"
  }
}
```

### Get Connection

```http
GET /api/v1/zapier/connection
```

**Response:**
```json
{
  "connection": {
    "id": 1,
    "user_id": 123,
    "mcp_server_url": "https://...",
    "is_active": true,
    "connected_at": "2025-12-20T10:00:00Z",
    "last_used_at": "2025-12-20T11:30:00Z"
  }
}
```

### List Available Tools

```http
GET /api/v1/zapier/tools
```

**Response:**
```json
{
  "tools": [
    {
      "name": "gmail__send_email",
      "description": "Send an email via Gmail",
      "inputSchema": {
        "type": "object",
        "properties": {
          "to": { "type": "string" },
          "subject": { "type": "string" },
          "body": { "type": "string" }
        }
      }
    }
  ]
}
```

### Execute a Tool

```http
POST /api/v1/zapier/execute
Content-Type: application/json

{
  "tool_name": "gmail__send_email",
  "params": {
    "to": "user@example.com",
    "subject": "Test Email",
    "body": "This is a test email from FreePanel"
  }
}
```

**Response:**
```json
{
  "result": {
    "success": true,
    "message_id": "abc123"
  }
}
```

### Disconnect

```http
DELETE /api/v1/zapier/disconnect
```

**Response:**
```json
{
  "message": "Zapier connection disconnected successfully"
}
```

## Usage Examples

### Send Email When Account Created

```php
use App\Models\UserZapierConnection;
use App\Services\Zapier\ZapierMcpClient;

// In your event listener or controller
$connection = UserZapierConnection::where('user_id', $user->id)
    ->where('is_active', true)
    ->first();

if ($connection) {
    $mcpClient = app(ZapierMcpClient::class);
    
    $result = $mcpClient->executeTool($connection, 'gmail__send_email', [
        'to' => $user->email,
        'subject' => 'Welcome to FreePanel',
        'body' => 'Your account has been created successfully!',
    ]);
}
```

### Log Data to Google Sheets

```php
$mcpClient->executeTool($connection, 'google_sheets__create_row', [
    'spreadsheet_id' => 'abc123',
    'range' => 'Sheet1!A1',
    'values' => [
        [$account->username, $account->email, $account->created_at],
    ],
]);
```

### Send Slack Notification

```php
$mcpClient->executeTool($connection, 'slack__post_message', [
    'channel' => '#alerts',
    'text' => "SSL certificate expiring for {$domain->name}",
]);
```

## Frontend Integration

The React component handles:
- Loading the Zapier MCP embed script
- Capturing the `mcp-server-url` event
- Storing the connection via API
- Displaying available tools
- Handling disconnection

### Customization

Edit [`frontend/src/components/ZapierConnect.tsx`](frontend/src/components/ZapierConnect.tsx) to:
- Add custom tool execution UI
- Display tool execution history
- Add tool favorites/shortcuts
- Implement automated workflows

## Security Considerations

### Embed Secret Protection
- ⚠️ **Never expose `ZAPIER_EMBED_SECRET` to the frontend**
- The secret is used server-side to authenticate with user MCP servers
- Stored in `.env` and accessed via `config('zapier.embed_secret')`

### User Data Isolation
- Each user's MCP server URL is unique and private
- Backend enforces user ownership (via `user_id` foreign key)
- Tools execute in the context of the authenticated user's Zapier account

### Rate Limiting
Consider adding rate limits to prevent abuse:

```php
Route::post('/execute', [ZapierConnectionController::class, 'executeTool'])
    ->middleware('throttle:zapier-execute');
```

In `app/Http/Kernel.php`:
```php
'zapier-execute' => 'throttle:10,1', // 10 requests per minute
```

## Troubleshooting

### Connection Fails
- Verify embed ID and secret are correct
- Check that Zapier MCP server URL is accessible
- Review Laravel logs: `tail -f storage/logs/laravel.log`

### Tools Not Appearing
- Ensure user has configured tools in their Zapier MCP server
- Visit https://mcp.zapier.com to add tools
- Refresh the connection: disconnect and reconnect

### Tool Execution Fails
- Check tool name is correct (`toolName` from API response)
- Verify all required parameters are provided
- Review Zapier's tool execution logs at https://mcp.zapier.com

## Database Schema

```sql
CREATE TABLE user_zapier_connections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    mcp_server_url VARCHAR(255) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Next Steps

1. **Add more automation triggers** — automatically execute Zapier tools when FreePanel events occur
2. **Build workflow templates** — pre-configured Zapier workflows for common tasks
3. **Add analytics** — track which tools are used most frequently
4. **Implement webhooks** — allow Zapier to trigger actions in FreePanel

## Support

For issues or questions:
- Check Laravel logs: `storage/logs/laravel.log`
- Review Zapier MCP documentation: https://docs.zapier.com/mcp
- Contact support or file an issue in the FreePanel repository
