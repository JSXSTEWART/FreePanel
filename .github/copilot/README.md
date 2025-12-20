# FreePanel MCP Configuration for GitHub Copilot

## Overview

This directory contains the Model Context Protocol (MCP) configuration for FreePanel, enabling GitHub Copilot and other AI coding agents to interact with FreePanel's APIs, webhooks, and system services.

## What is MCP?

The **Model Context Protocol (MCP)** is an open standard that defines how applications share context with Large Language Models (LLMs). It provides a standardized way to connect AI models to different data sources and tools, enabling them to work together more effectively.

## Configuration File

**File:** `.github/copilot/mcp.json`

This JSON configuration defines:

- **API Tools**: Available REST API endpoints for hosting management
- **Webhook Events**: Event schemas for automation and integrations
- **Resources**: Documentation and reference materials
- **Prompts**: Pre-configured commands for common tasks
- **Capabilities**: System features and supported operations

## Usage with GitHub Copilot

### Prerequisites

1. **GitHub Copilot** installed and activated in your IDE
2. **FreePanel API Access**:
   - API URL (e.g., `https://panel.example.com`)
   - API Bearer Token for authentication

### Environment Setup

Set the following environment variables:

```bash
export FREEPANEL_API_URL="https://panel.example.com"
export FREEPANEL_API_TOKEN="your_bearer_token_here"
export ZAPIER_WEBHOOK_SECRET="your_webhook_secret_here"  # Optional, for webhook signature verification
```

### Using MCP Tools in Copilot

Once configured, you can ask GitHub Copilot to perform FreePanel operations:

**Examples:**

- "Create a new hosting account for user john@example.com with the premium package"
- "List all domains for account ID 123"
- "Check SSL certificates that are expiring soon"
- "Create a backup for account ID 456"
- "Check quota usage for account ID 789"

### Available Tools

The MCP configuration provides access to these FreePanel operations:

#### Account Management
- `create_account` - Create new hosting accounts
- `list_accounts` - List and filter accounts
- `get_account` - Get account details
- `suspend_account` - Suspend accounts

#### Domain Management
- `create_domain` - Add domains to accounts
- `list_domains` - List domains with filtering

#### SSL Management
- `create_ssl_certificate` - Issue/renew SSL certificates
- `list_ssl_certificates` - View SSL certificate status

#### Database Management
- `create_database` - Create MySQL/MariaDB databases

#### Email Management
- `create_email_account` - Create email accounts

#### Backup Management
- `create_backup` - Create backups
- `list_backups` - View available backups
- `restore_backup` - Restore from backup

#### Quota Management
- `check_quotas` - Check resource usage

## Webhook Integration

The MCP configuration includes webhook event definitions for automation:

### Supported Events

- **Account Events**: `account.created`, `account.suspended`, `account.updated`, `account.deleted`
- **Domain Events**: `domain.created`, `domain.deleted`
- **SSL Events**: `ssl.expiring`, `ssl.renewed`
- **Backup Events**: `backup.completed`, `backup.failed`
- **Quota Events**: `quota.exceeded`

### Webhook Security

All webhooks use **HMAC-SHA256 signature verification**:

```
X-Zapier-Signature: <hmac_signature>
X-Zapier-Timestamp: <iso8601_timestamp>
```

**Algorithm**: `HMAC-SHA256(payload.timestamp, ZAPIER_WEBHOOK_SECRET)`

**Timestamp Tolerance**: 300 seconds (5 minutes)

See [WEBHOOK_SECURITY.md](../../WEBHOOK_SECURITY.md) for detailed security implementation.

## Pre-configured Prompts

The MCP configuration includes optimized prompts for common workflows:

### 1. Create Hosting Account
```
@copilot create_hosting_account username=john email=john@example.com package=premium
```

### 2. Setup Domain with SSL
```
@copilot setup_domain_with_ssl account_id=123 domain=example.com
```

### 3. Check System Health
```
@copilot check_system_health
```

## Configuration Structure

The `mcp.json` file follows this structure:

```json
{
  "mcpVersion": "1.0",
  "name": "FreePanel",
  "description": "...",
  "servers": [
    {
      "name": "freepanel-api",
      "type": "http",
      "tools": [...]
    },
    {
      "name": "freepanel-webhooks",
      "type": "other",
      "events": [...]
    }
  ],
  "resources": [...],
  "prompts": [...],
  "configuration": {...},
  "capabilities": {...},
  "metadata": {...}
}
```

### Key Sections

#### 1. Servers

Defines API and webhook endpoints:
- **freepanel-api**: HTTP REST API with bearer authentication
- **freepanel-webhooks**: Webhook event definitions with HMAC authentication

#### 2. Tools

Each tool defines:
- `name`: Tool identifier
- `description`: What the tool does
- `endpoint`: API endpoint path
- `method`: HTTP method (GET, POST, etc.)
- `inputSchema`: JSON Schema for parameters

#### 3. Events

Webhook events include:
- `name`: Event identifier
- `description`: When the event triggers
- `payloadSchema`: JSON Schema for event data

#### 4. Resources

Links to documentation:
- API documentation
- Webhook integration guide
- Security implementation
- Development setup

#### 5. Capabilities

System features and supported operations across all service categories.

## Integration Examples

### Example 1: Create Account with Domain and SSL

```typescript
// Copilot can generate code like this using MCP tools:

async function setupNewClient(username: string, email: string, domain: string) {
  // 1. Create account
  const account = await freepanel.createAccount({
    username,
    email,
    password: generateSecurePassword(),
    package_id: 2 // Premium package
  });

  // 2. Add domain
  const domainRecord = await freepanel.createDomain({
    account_id: account.id,
    name: domain,
    type: 'primary'
  });

  // 3. Setup SSL
  const ssl = await freepanel.createSslCertificate({
    domain_id: domainRecord.id,
    provider: 'letsencrypt'
  });

  return { account, domainRecord, ssl };
}
```

### Example 2: Monitor System Health

```typescript
async function checkSystemHealth() {
  // Check expiring SSL certificates
  const expiringSsl = await freepanel.listSslCertificates({
    expiring_soon: true
  });

  // Check accounts with quota issues
  const accounts = await freepanel.listAccounts();
  const quotaIssues = [];
  
  for (const account of accounts) {
    const quotas = await freepanel.checkQuotas({
      account_id: account.id
    });
    if (quotas.disk_usage > quotas.disk_limit * 0.9) {
      quotaIssues.push(account);
    }
  }

  return {
    expiringSslCount: expiringSsl.length,
    quotaIssuesCount: quotaIssues.length
  };
}
```

## Testing the Configuration

### 1. Validate JSON Syntax

```bash
python3 -m json.tool .github/copilot/mcp.json
```

### 2. Test API Connection

```bash
curl -H "Authorization: Bearer $FREEPANEL_API_TOKEN" \
     "$FREEPANEL_API_URL/api/v1/accounts"
```

### 3. Test Webhook Endpoint

```bash
curl "$FREEPANEL_API_URL/api/v1/webhooks/zapier/health"
```

## Related Documentation

- [README.md](../../README.md) - FreePanel overview and installation
- [VSCODE_MCP_SETUP.md](../../VSCODE_MCP_SETUP.md) - VS Code MCP setup guide
- [ZAPIER_MCP_EMBED.md](../../ZAPIER_MCP_EMBED.md) - Zapier MCP integration
- [ZAPIER_WEBHOOK_INTEGRATION.md](../../ZAPIER_WEBHOOK_INTEGRATION.md) - Webhook integration guide
- [WEBHOOK_SECURITY.md](../../WEBHOOK_SECURITY.md) - Security implementation details
- [DEVELOPMENT.md](../../DEVELOPMENT.md) - Development setup guide

## Extending the Configuration

To add new tools or events:

1. **Add API Tool**:
   ```json
   {
     "name": "your_tool_name",
     "description": "What it does",
     "endpoint": "/api/v1/your/endpoint",
     "method": "POST",
     "inputSchema": {
       "type": "object",
       "properties": {...},
       "required": [...]
     }
   }
   ```

2. **Add Webhook Event**:
   ```json
   {
     "name": "your.event",
     "description": "When it triggers",
     "payloadSchema": {
       "type": "object",
       "properties": {...}
     }
   }
   ```

3. **Test the changes**:
   ```bash
   python3 -m json.tool .github/copilot/mcp.json
   ```

## Troubleshooting

### Copilot Not Recognizing MCP Configuration

1. Ensure `.github/copilot/mcp.json` exists in repository root
2. Validate JSON syntax with `python3 -m json.tool`
3. Check environment variables are set correctly
4. Restart your IDE

### API Authentication Errors

1. Verify `FREEPANEL_API_TOKEN` is valid
2. Check token has required permissions
3. Ensure API URL is correct (no trailing slash)

### Webhook Signature Verification Fails

1. Verify `ZAPIER_WEBHOOK_SECRET` matches server configuration
2. Check timestamp is within tolerance (5 minutes)
3. Ensure payload hasn't been modified in transit

## Security Best Practices

1. **Never commit secrets** to version control
   - Use environment variables for `FREEPANEL_API_TOKEN`
   - Use environment variables for `ZAPIER_WEBHOOK_SECRET`

2. **Rotate credentials regularly**
   - API tokens should be rotated every 90 days
   - Webhook secrets should be rotated if compromised

3. **Use HTTPS** for all API and webhook endpoints

4. **Implement rate limiting** to prevent abuse

5. **Monitor webhook failures** and disable after threshold

## Support

For issues or questions:

- **GitHub Issues**: [JSXSTEWART/FreePanel/issues](https://github.com/JSXSTEWART/FreePanel/issues)
- **Documentation**: Check related documentation links above
- **Security Issues**: Report privately via GitHub Security Advisories

## License

This MCP configuration is part of FreePanel and is licensed under the MIT License.

---

**Last Updated**: 2025-12-20
