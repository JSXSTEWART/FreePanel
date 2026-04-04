# FreePanel MCP Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                       GitHub Copilot                             │
│                    (AI Coding Assistant)                         │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ Uses MCP Protocol
                         │
        ┌────────────────▼────────────────┐
        │  .github/copilot/mcp.json       │
        │  (MCP Configuration)            │
        │                                 │
        │  • 14 API Tools                 │
        │  • 11 Webhook Events            │
        │  • Authentication Schemas       │
        │  • System Capabilities          │
        └────────┬───────────┬────────────┘
                 │           │
       ┌─────────▼───┐   ┌───▼──────────┐
       │ API Server  │   │   Webhooks   │
       │             │   │              │
       │ freepanel-  │   │ freepanel-   │
       │    api      │   │  webhooks    │
       └──────┬──────┘   └──────┬───────┘
              │                 │
              │                 │
┌─────────────▼─────────────────▼──────────────────┐
│              FreePanel Application                │
│                                                   │
│  ┌─────────────────────────────────────────┐    │
│  │         REST API (Laravel)              │    │
│  │  /api/v1/accounts                       │    │
│  │  /api/v1/domains                        │    │
│  │  /api/v1/ssl/certificates               │    │
│  │  /api/v1/databases                      │    │
│  │  /api/v1/email/accounts                 │    │
│  │  /api/v1/backups                        │    │
│  │  /api/v1/quotas                         │    │
│  └─────────────────────────────────────────┘    │
│                                                   │
│  ┌─────────────────────────────────────────┐    │
│  │      Webhook Endpoints                  │    │
│  │  /api/v1/webhooks/zapier/receive        │    │
│  │  /api/v1/webhooks/zapier/health         │    │
│  └─────────────────────────────────────────┘    │
│                                                   │
│  ┌─────────────────────────────────────────┐    │
│  │         Event System                    │    │
│  │  • account.created                      │    │
│  │  • account.suspended                    │    │
│  │  • domain.created                       │    │
│  │  • ssl.expiring                         │    │
│  │  • backup.completed                     │    │
│  │  • quota.exceeded                       │    │
│  │  ... and 5 more events                  │    │
│  └─────────────────────────────────────────┘    │
│                                                   │
└───────────────────────────────────────────────────┘
              │                    │
              │                    │
    ┌─────────▼────────┐  ┌────────▼──────────┐
    │  System Services │  │   External Apps   │
    │                  │  │                   │
    │  • Apache/Nginx  │  │  • Zapier         │
    │  • MariaDB       │  │  • Email Clients  │
    │  • Dovecot/Exim  │  │  • Monitoring     │
    │  • BIND DNS      │  │  • Backup Systems │
    │  • Pure-FTPd     │  │                   │
    └──────────────────┘  └───────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                    Authentication Flow                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  API Authentication:                                             │
│  ┌────────┐                                                      │
│  │ Copilot│──► Authorization: Bearer ${FREEPANEL_API_TOKEN}     │
│  └────────┘                                                      │
│                                                                  │
│  Webhook Authentication:                                         │
│  ┌────────┐                                                      │
│  │ Zapier │──► X-Zapier-Signature: <hmac_sha256>                │
│  └────────┘──► X-Zapier-Timestamp: <iso8601>                    │
│                                                                  │
│  Signature = HMAC-SHA256(payload.timestamp, secret)             │
│  Tolerance = 300 seconds (5 minutes)                             │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                      File Structure                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  .github/copilot/                                                │
│  ├── mcp.json                    (Main configuration)           │
│  ├── README.md                   (Usage guide)                  │
│  ├── EXAMPLES.md                 (Code examples)                │
│  ├── QUICK_REFERENCE.md          (Quick lookup)                 │
│  ├── IMPLEMENTATION_SUMMARY.md   (Summary doc)                  │
│  └── validate-mcp-config.sh      (Validation script)            │
│                                                                  │
│  .vscode/                                                        │
│  └── settings.json               (VS Code MCP config)           │
│                                                                  │
│  .mcp/                          (Existing YAML configs)          │
│  ├── servers.yaml                                                │
│  └── servers.local.yaml.example                                 │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                    Usage Example Flow                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. User asks Copilot:                                           │
│     "Create a hosting account for john@example.com"             │
│                                                                  │
│  2. Copilot reads mcp.json:                                      │
│     → Finds 'create_account' tool                               │
│     → Reads input schema                                         │
│     → Prepares API call                                          │
│                                                                  │
│  3. Copilot calls FreePanel API:                                 │
│     POST /api/v1/accounts                                        │
│     Authorization: Bearer ${TOKEN}                               │
│     Body: { username, email, password, package_id }             │
│                                                                  │
│  4. FreePanel creates account:                                   │
│     → Validates input                                            │
│     → Creates hosting account                                    │
│     → Configures system services                                 │
│     → Returns account details                                    │
│                                                                  │
│  5. FreePanel dispatches event:                                  │
│     → 'account.created' event triggered                          │
│     → Webhook sent to configured endpoints                       │
│     → External systems notified                                  │
│                                                                  │
│  6. Copilot presents results:                                    │
│     → Shows account details                                      │
│     → Suggests next steps                                        │
│     → Offers to continue workflow                                │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Component Descriptions

### MCP Configuration (mcp.json)
Central configuration file that defines:
- Available API tools and their schemas
- Webhook events and payload formats
- Authentication requirements
- System capabilities
- Documentation resources

### API Tools (14 Total)
Direct access to FreePanel operations:
- **Account Management**: Create, list, get, suspend accounts
- **Domain Management**: Add and list domains
- **SSL Management**: Issue and track certificates
- **Database Management**: Create databases
- **Email Management**: Create email accounts
- **Backup Management**: Create, list, restore backups
- **Quota Management**: Check resource usage

### Webhook Events (11 Total)
Automated notifications for system events:
- **Account Events**: created, suspended, updated, deleted
- **Domain Events**: created, deleted
- **SSL Events**: expiring, renewed
- **Backup Events**: completed, failed
- **Quota Events**: exceeded

### Authentication
- **API**: Bearer token authentication
- **Webhooks**: HMAC-SHA256 signature verification with timestamp validation

### Integration Points
- GitHub Copilot (primary)
- VS Code MCP extension
- Zapier (existing integration)
- External monitoring and automation tools

## Data Flow

```
User Request → Copilot → MCP Config → API Call → FreePanel
                                                      ↓
                                              System Services
                                                      ↓
                                              Event Dispatch
                                                      ↓
                                              Webhooks
                                                      ↓
                                            External Systems
```

## Benefits

1. **Natural Language Interface**: Ask Copilot in plain English
2. **Type Safety**: JSON schemas ensure correct parameters
3. **Documentation**: Built-in tool descriptions
4. **Automation**: Webhook events enable workflows
5. **Security**: Token + signature authentication
6. **Extensibility**: Easy to add new tools/events

---

Last Updated: 2025-12-20
