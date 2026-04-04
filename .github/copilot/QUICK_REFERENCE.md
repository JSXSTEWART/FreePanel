# MCP Quick Reference

Quick reference for using FreePanel's MCP configuration with GitHub Copilot.

## Setup (One-Time)

```bash
# Set environment variables
export FREEPANEL_API_URL="https://panel.example.com"
export FREEPANEL_API_TOKEN="your_bearer_token"
export ZAPIER_WEBHOOK_SECRET="your_webhook_secret"  # Optional
```

## Available Tools

### Account Management
| Tool | Description | Required Parameters |
|------|-------------|---------------------|
| `create_account` | Create new hosting account | username, email, password, package_id |
| `list_accounts` | List all accounts | page (optional), per_page (optional), status (optional) |
| `get_account` | Get account details | id |
| `suspend_account` | Suspend an account | id, reason (optional) |

### Domain Management
| Tool | Description | Required Parameters |
|------|-------------|---------------------|
| `create_domain` | Add domain to account | account_id, name, type |
| `list_domains` | List domains | account_id (optional) |

### SSL Management
| Tool | Description | Required Parameters |
|------|-------------|---------------------|
| `create_ssl_certificate` | Issue/renew SSL certificate | domain_id, provider (optional) |
| `list_ssl_certificates` | List SSL certificates | expiring_soon (optional) |

### Database Management
| Tool | Description | Required Parameters |
|------|-------------|---------------------|
| `create_database` | Create MySQL/MariaDB database | account_id, name |

### Email Management
| Tool | Description | Required Parameters |
|------|-------------|---------------------|
| `create_email_account` | Create email account | domain_id, email, password, quota (optional) |

### Backup Management
| Tool | Description | Required Parameters |
|------|-------------|---------------------|
| `create_backup` | Create backup | account_id, type |
| `list_backups` | List available backups | account_id |
| `restore_backup` | Restore from backup | id |

### Quota Management
| Tool | Description | Required Parameters |
|------|-------------|---------------------|
| `check_quotas` | Check resource usage | account_id |

## Webhook Events

### Account Events
- `account.created` - New account created
- `account.suspended` - Account suspended
- `account.updated` - Account details updated
- `account.deleted` - Account deleted

### Domain Events
- `domain.created` - Domain added
- `domain.deleted` - Domain removed

### SSL Events
- `ssl.expiring` - Certificate expiring soon
- `ssl.renewed` - Certificate renewed

### Backup Events
- `backup.completed` - Backup successful
- `backup.failed` - Backup failed

### Quota Events
- `quota.exceeded` - Resource quota exceeded

## Common Copilot Prompts

### Account Operations
```
Create a hosting account for user@example.com with username "user123"
List all suspended accounts
Get details for account ID 456
Suspend account ID 789 for non-payment
```

### Domain & SSL
```
Add domain example.com to account ID 123
List all domains for account ID 123
Create SSL certificate for domain ID 456
Show all SSL certificates expiring in the next 30 days
```

### Backups
```
Create a full backup for account ID 123
List all backups for account ID 456
Restore backup ID 789
```

### System Monitoring
```
Check quota usage for account ID 123
Find all accounts using more than 90% disk space
List accounts with expiring SSL certificates
```

### Bulk Operations
```
Create backups for all active accounts
Add SSL certificates to all domains without one
List all accounts created in the last 7 days
```

## Example Code Snippets

### Create Account with Domain and SSL
```typescript
const account = await createAccount({
  username: 'client',
  email: 'client@example.com',
  password: 'SecurePass123!',
  package_id: 1
});

const domain = await createDomain({
  account_id: account.id,
  name: 'client.com',
  type: 'primary'
});

const ssl = await createSslCertificate({
  domain_id: domain.id,
  provider: 'letsencrypt'
});
```

### Monitor System Health
```typescript
const expiringSsl = await listSslCertificates({
  expiring_soon: true
});

const accounts = await listAccounts();
for (const account of accounts) {
  const quotas = await checkQuotas({
    account_id: account.id
  });
  if (quotas.disk_usage / quotas.disk_limit > 0.9) {
    console.log(`⚠️  Account ${account.username} at ${(quotas.disk_usage / quotas.disk_limit * 100).toFixed(1)}% disk usage`);
  }
}
```

### Handle Webhook Event
```typescript
async function handleWebhook(payload) {
  if (payload.event === 'quota.exceeded') {
    const account = await getAccount({
      id: payload.data.account_id
    });
    
    // Send notification
    await sendEmail({
      to: account.email,
      subject: 'Quota Exceeded',
      body: `Your ${payload.data.resource_type} quota has been exceeded.`
    });
  }
}
```

## Authentication

### API Authentication
```
Header: Authorization: Bearer YOUR_TOKEN
```

### Webhook Authentication
```
X-Zapier-Signature: <hmac_sha256_signature>
X-Zapier-Timestamp: <iso8601_timestamp>
```

**Signature Verification:**
```typescript
const signature = hmac_sha256(
  `payload.${timestamp}`,
  ZAPIER_WEBHOOK_SECRET
);
```

## API Endpoints

### Base URL
```
${FREEPANEL_API_URL}/api/v1
```

### Accounts
- `POST /accounts` - Create account
- `GET /accounts` - List accounts
- `GET /accounts/{id}` - Get account
- `POST /accounts/{id}/suspend` - Suspend account

### Domains
- `POST /domains` - Create domain
- `GET /domains` - List domains

### SSL
- `POST /ssl/certificates` - Create certificate
- `GET /ssl/certificates` - List certificates

### Databases
- `POST /databases` - Create database

### Email
- `POST /email/accounts` - Create email account

### Backups
- `POST /backups` - Create backup
- `GET /backups` - List backups
- `POST /backups/{id}/restore` - Restore backup

### Quotas
- `GET /quotas/{account_id}` - Check quotas

### Webhooks
- `POST /webhooks/zapier/receive` - Receive webhook (public)
- `GET /webhooks/zapier/health` - Health check (public)

## Troubleshooting

### Common Issues

**"API authentication failed"**
- Verify `FREEPANEL_API_TOKEN` is set correctly
- Check token hasn't expired
- Ensure token has required permissions

**"Webhook signature verification failed"**
- Check `ZAPIER_WEBHOOK_SECRET` matches server
- Verify timestamp is within 5-minute tolerance
- Ensure payload hasn't been modified

**"Copilot doesn't recognize tools"**
- Verify `.github/copilot/mcp.json` exists
- Check JSON syntax is valid
- Restart IDE
- Ensure Copilot is in "Agent" mode

## Documentation Links

- [Complete MCP Documentation](.github/copilot/README.md)
- [Usage Examples](.github/copilot/EXAMPLES.md)
- [API Documentation](README.md)
- [Webhook Security](WEBHOOK_SECURITY.md)

## Support

For issues or questions:
- GitHub Issues: https://github.com/JSXSTEWART/FreePanel/issues
- Documentation: See links above
- Security: Report via GitHub Security Advisories

---

Last Updated: 2025-12-20
