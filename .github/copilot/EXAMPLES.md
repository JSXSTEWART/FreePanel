# Example MCP Configuration Usage

This file demonstrates how to use the FreePanel MCP configuration with GitHub Copilot.

## Environment Setup

First, set up your environment variables:

```bash
# Required: FreePanel API credentials
export FREEPANEL_API_URL="https://panel.example.com"
export FREEPANEL_API_TOKEN="your_bearer_token_here"

# Optional: For webhook signature verification
export ZAPIER_WEBHOOK_SECRET="your_webhook_secret_here"
```

## Example 1: Create a Complete Hosting Setup

Ask GitHub Copilot:
> "Using the FreePanel API, create a new hosting account for client@example.com with username 'client123', add the domain client.com as a primary domain, and setup a Let's Encrypt SSL certificate"

**Expected Copilot Actions:**
1. Uses `create_account` tool to create the account
2. Uses `create_domain` tool to add the domain
3. Uses `create_ssl_certificate` tool to setup SSL

**Generated Code Example:**
```typescript
async function setupNewClient() {
  const account = await createAccount({
    username: 'client123',
    email: 'client@example.com',
    password: generatePassword(),
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
  
  return { account, domain, ssl };
}
```

## Example 2: Monitor System Health

Ask GitHub Copilot:
> "Check all SSL certificates expiring in the next 30 days and list accounts that have exceeded 90% of their disk quota"

**Expected Copilot Actions:**
1. Uses `list_ssl_certificates` with `expiring_soon: true`
2. Uses `list_accounts` to get all accounts
3. Uses `check_quotas` for each account to check disk usage

**Generated Code Example:**
```typescript
async function checkSystemHealth() {
  // Get expiring SSL certificates
  const expiringSsl = await listSslCertificates({
    expiring_soon: true
  });
  
  // Check quota usage
  const accounts = await listAccounts();
  const highUsageAccounts = [];
  
  for (const account of accounts) {
    const quotas = await checkQuotas({ account_id: account.id });
    if (quotas.disk_usage / quotas.disk_limit > 0.9) {
      highUsageAccounts.push({
        account_id: account.id,
        username: account.username,
        usage_percent: (quotas.disk_usage / quotas.disk_limit * 100).toFixed(1)
      });
    }
  }
  
  return {
    expiring_certificates: expiringSsl,
    high_usage_accounts: highUsageAccounts
  };
}
```

## Example 3: Automated Backup Creation

Ask GitHub Copilot:
> "Create full backups for all active accounts and log the results"

**Expected Copilot Actions:**
1. Uses `list_accounts` with status filter
2. Uses `create_backup` for each active account

**Generated Code Example:**
```typescript
async function backupAllAccounts() {
  const accounts = await listAccounts({ status: 'active' });
  const backupResults = [];
  
  for (const account of accounts) {
    try {
      const backup = await createBackup({
        account_id: account.id,
        type: 'full'
      });
      backupResults.push({
        account_id: account.id,
        status: 'success',
        backup_id: backup.id
      });
    } catch (error) {
      backupResults.push({
        account_id: account.id,
        status: 'failed',
        error: error.message
      });
    }
  }
  
  return backupResults;
}
```

## Example 4: Webhook Event Handler

Ask GitHub Copilot:
> "Create a webhook handler that listens for quota.exceeded events and automatically sends an email notification"

**Expected Copilot Actions:**
1. Recognizes `quota.exceeded` event schema from MCP config
2. Creates handler with proper payload validation
3. Implements email notification logic

**Generated Code Example:**
```typescript
async function handleQuotaExceeded(webhook: WebhookPayload) {
  // Validate event type
  if (webhook.event !== 'quota.exceeded') {
    throw new Error('Invalid event type');
  }
  
  const { account_id, resource_type, limit, usage } = webhook.data;
  
  // Get account details
  const account = await getAccount({ id: account_id });
  
  // Send notification
  await sendEmail({
    to: account.email,
    subject: `Quota Exceeded: ${resource_type}`,
    body: `
      Your ${resource_type} usage has exceeded the limit.
      Limit: ${formatBytes(limit)}
      Current Usage: ${formatBytes(usage)}
      Please upgrade your package or reduce usage.
    `
  });
  
  console.log(`Notification sent for account ${account_id}`);
}
```

## Example 5: Bulk Domain Management

Ask GitHub Copilot:
> "Add SSL certificates to all domains that don't have one yet"

**Expected Copilot Actions:**
1. Uses `list_domains` to get all domains
2. Uses `list_ssl_certificates` to check existing certificates
3. Uses `create_ssl_certificate` for domains without SSL

**Generated Code Example:**
```typescript
async function addMissingSSL() {
  const domains = await listDomains();
  const certificates = await listSslCertificates();
  
  // Create a set of domain IDs that have certificates
  const domainsWithSsl = new Set(
    certificates.map(cert => cert.domain_id)
  );
  
  // Find domains without SSL
  const domainsNeedingSsl = domains.filter(
    domain => !domainsWithSsl.has(domain.id)
  );
  
  const results = [];
  for (const domain of domainsNeedingSsl) {
    try {
      const cert = await createSslCertificate({
        domain_id: domain.id,
        provider: 'letsencrypt'
      });
      results.push({
        domain: domain.name,
        status: 'success',
        certificate_id: cert.id
      });
    } catch (error) {
      results.push({
        domain: domain.name,
        status: 'failed',
        error: error.message
      });
    }
  }
  
  return results;
}
```

## Example 6: Using Pre-configured Prompts

The MCP configuration includes optimized prompts that Copilot can use directly:

### Create Hosting Account
```
@copilot create_hosting_account username=newuser email=new@example.com package=premium
```

### Setup Domain with SSL
```
@copilot setup_domain_with_ssl account_id=123 domain=example.com
```

### Check System Health
```
@copilot check_system_health
```

## Testing Your MCP Configuration

### 1. Validate API Access
```bash
curl -H "Authorization: Bearer $FREEPANEL_API_TOKEN" \
     "$FREEPANEL_API_URL/api/v1/accounts"
```

### 2. Test Webhook Endpoint
```bash
curl "$FREEPANEL_API_URL/api/v1/webhooks/zapier/health"
```

Expected response:
```json
{
  "status": "healthy",
  "signature_verification": {
    "enabled": true,
    "algorithm": "sha256"
  }
}
```

### 3. Verify MCP Configuration
```bash
python3 -m json.tool .github/copilot/mcp.json
```

## Best Practices

### 1. Error Handling
Always wrap API calls in try-catch blocks:
```typescript
try {
  const result = await apiCall();
  // Handle success
} catch (error) {
  console.error('API call failed:', error);
  // Handle error
}
```

### 2. Rate Limiting
Be mindful of API rate limits when making bulk operations:
```typescript
// Add delay between requests
await sleep(100); // 100ms delay
```

### 3. Authentication
Always validate that environment variables are set:
```typescript
if (!process.env.FREEPANEL_API_TOKEN) {
  throw new Error('FREEPANEL_API_TOKEN not set');
}
```

### 4. Webhook Signature Verification
Always verify webhook signatures:
```typescript
const signature = request.headers['x-zapier-signature'];
const timestamp = request.headers['x-zapier-timestamp'];

if (!verifySignature(payload, signature, timestamp)) {
  throw new Error('Invalid webhook signature');
}
```

## Common Use Cases

### Daily Operations
- Create hosting accounts
- Add domains and SSL certificates
- Manage email accounts
- Check quota usage
- Create backups

### Monitoring & Alerts
- Check SSL certificate expiration
- Monitor disk usage
- Track backup status
- Detect quota violations

### Automation
- Auto-renewal of SSL certificates
- Scheduled backups
- Quota notifications
- Account suspension workflows

### Bulk Operations
- Mass account creation
- Bulk SSL setup
- Domain migrations
- Backup restoration

## Troubleshooting

### Issue: Copilot doesn't recognize MCP tools

**Solution:**
1. Verify `.github/copilot/mcp.json` exists
2. Check JSON syntax is valid
3. Restart your IDE
4. Ensure Copilot is in "Agent" mode

### Issue: API authentication fails

**Solution:**
1. Verify `FREEPANEL_API_TOKEN` is set correctly
2. Check token hasn't expired
3. Ensure token has required permissions

### Issue: Webhook signature verification fails

**Solution:**
1. Check `ZAPIER_WEBHOOK_SECRET` matches server config
2. Verify timestamp is within 5-minute tolerance
3. Ensure payload hasn't been modified

## Additional Resources

- [MCP Configuration README](.github/copilot/README.md)
- [FreePanel API Documentation](../../README.md)
- [Webhook Integration Guide](../../ZAPIER_WEBHOOK_INTEGRATION.md)
- [Security Best Practices](../../WEBHOOK_SECURITY.md)

---

Last Updated: 2025-12-20
