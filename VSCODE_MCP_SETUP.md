# Configuring Zapier MCP in Visual Studio Code

This guide walks you through setting up the Zapier Model Context Protocol (MCP) server in Visual Studio Code to enable AI-powered automation tools from your Zapier account.

## Overview

The Zapier MCP integration allows GitHub Copilot in VS Code to access and execute automation tools from your connected Zapier account. This enables powerful workflows like sending emails, updating spreadsheets, posting to Slack, and more—all from within your development environment.

## Prerequisites

- Visual Studio Code installed
- GitHub Copilot extension installed and activated
- A Zapier account with configured automation tools

## Setup Instructions

### Step 1: Open the VS Code Command Palette

- **Mac**: Press `⇧+⌘+P` (Shift + Command + P)
- **Windows/Linux**: Press `Ctrl+Shift+P`

### Step 2: Add MCP Server

1. Type `MCP: Add Server...` in the command palette and press Enter
2. Choose `HTTP (HTTP or Server-Sent Events)` from the protocol options and press Enter

### Step 3: Configure Server URL

**Important**: You need to obtain your own Zapier MCP server URL from your Zapier account.

1. Visit [https://mcp.zapier.com](https://mcp.zapier.com) and log in to your Zapier account
2. Navigate to the MCP configuration section
3. Copy your personal MCP server URL (it will look like: `https://mcp.zapier.com/api/mcp/s/YOUR_SERVER_ID/mcp`)
4. Paste your server URL into the "Server URL" field in VS Code and press Enter

**Example URL format:**
```
https://mcp.zapier.com/api/mcp/s/YOUR_UNIQUE_SERVER_ID/mcp
```

⚠️ **Security Note**: Your MCP server URL contains authentication credentials. Keep it private and do not share it publicly or commit it to version control.

### Step 4: Name the Server

Give the server a meaningful name (e.g., `zapier-mcp` or `zapier-automation`) and press Enter.

### Step 5: Enable GitHub Copilot Agent Mode

1. Open GitHub Copilot settings
2. Ensure GitHub Copilot is set to **"Agent" mode**
3. This allows Copilot to use tools from your MCP server

### Step 6: Start Using Zapier Tools

You can now ask GitHub Copilot to use tools from your Zapier server! Examples:

- "Send an email via Gmail to notify the team"
- "Add a row to my Google Sheet with these values"
- "Post a message to our Slack channel"
- "Create a new task in Asana"

## Video Tutorial

For a visual walkthrough of the setup process, watch this video:

🎥 [VS Code MCP Setup Tutorial](https://mcp.zapier.com/videos/vscode-mcp-setup.mp4)

## Troubleshooting

### Server Connection Issues

If you can't connect to the MCP server:

1. Verify the server URL is correct and complete
2. Check your internet connection
3. Ensure you're logged into your Zapier account in a browser
4. Try restarting VS Code

### No Tools Available

If Copilot reports no tools are available:

1. Log into your Zapier account at [https://mcp.zapier.com](https://mcp.zapier.com)
2. Configure and enable automation tools for your MCP server
3. Reload VS Code window (`Cmd+R` or `Ctrl+R`)

### Copilot Not Using Tools

If Copilot doesn't recognize your MCP tools:

1. Verify Copilot is in "Agent" mode
2. Use explicit language like "use the Zapier tool to..."
3. Check that the MCP server shows as connected in VS Code settings

## Available Tools

The tools available depend on your Zapier configuration. Common tools include:

- **Gmail** - Send emails, read messages, manage labels
- **Google Sheets** - Create rows, update cells, read data
- **Slack** - Post messages, create channels, manage users
- **Asana** - Create tasks, update projects, add comments
- **Trello** - Create cards, move items, update boards
- **And many more!**

Visit [https://mcp.zapier.com](https://mcp.zapier.com) to configure your available tools.

## Security Considerations

### Obtaining Your Server URL

Your Zapier MCP server URL is unique to your account and contains authentication credentials:

1. Visit [https://mcp.zapier.com](https://mcp.zapier.com)
2. Log in with your Zapier account
3. Navigate to your MCP server settings or configuration page
4. Copy your personal MCP server URL
5. The URL format will be: `https://mcp.zapier.com/api/mcp/s/[YOUR_UNIQUE_ID]/mcp`

### Server URL Security

⚠️ **Important**: The server URL contains authentication tokens. 

- Do not share your server URL publicly
- Do not commit it to version control without encryption
- Treat it like a password or API key

### Permissions

The MCP server has access to actions you've configured in Zapier:

- Review and limit tool permissions in your Zapier account
- Only enable tools you need for development workflows
- Regularly audit connected applications

## Alternative Configuration Methods

### Using VS Code Settings File

You can also configure the MCP server directly in VS Code settings:

1. Open VS Code settings (JSON)
2. Add the following configuration (replace `YOUR_UNIQUE_SERVER_ID` with your actual server ID from Zapier):

```json
{
  "mcp.servers": [
    {
      "name": "zapier-mcp",
      "type": "http",
      "url": "https://mcp.zapier.com/api/mcp/s/YOUR_UNIQUE_SERVER_ID/mcp"
    }
  ]
}
```

⚠️ **Important**: Replace `YOUR_UNIQUE_SERVER_ID` with your actual server ID obtained from [https://mcp.zapier.com](https://mcp.zapier.com).

### Using MCP Configuration File

FreePanel includes an `.mcp/servers.yaml` file for MCP server configuration. 

**For local development with your personal credentials:**

1. Copy the example file:
   ```bash
   cp .mcp/servers.local.yaml.example .mcp/servers.local.yaml
   ```

2. Edit `.mcp/servers.local.yaml` and replace the placeholder URL with your actual server URL from Zapier

3. The `.mcp/servers.local.yaml` file is gitignored to prevent accidentally committing your credentials

See the main `.mcp/servers.yaml` file for additional server configurations.

## Integration with FreePanel

This MCP server can be used alongside FreePanel's built-in Zapier integration:

- **Frontend Integration**: See `ZAPIER_MCP_EMBED.md` for embedding Zapier MCP in the FreePanel UI
- **Backend Integration**: See `ZAPIER_INTEGRATION.md` for API-level integration
- **User Connections**: Each FreePanel user can connect their own Zapier account

## Next Steps

1. **Configure Tools**: Visit [https://mcp.zapier.com](https://mcp.zapier.com) to set up automation tools
2. **Test Integration**: Ask Copilot to list available tools
3. **Create Workflows**: Build custom automation workflows using Copilot + Zapier
4. **Explore Documentation**: Review Zapier MCP documentation for advanced features

## Related Documentation

- [ZAPIER_MCP_EMBED.md](ZAPIER_MCP_EMBED.md) - Embedding Zapier MCP in FreePanel
- [ZAPIER_INTEGRATION.md](ZAPIER_INTEGRATION.md) - Complete Zapier integration guide
- [.mcp/servers.yaml](.mcp/servers.yaml) - MCP server configuration file

## Support

For issues or questions:

- **VS Code MCP**: Check VS Code MCP extension documentation
- **Zapier MCP**: Visit [https://docs.zapier.com/mcp](https://docs.zapier.com/mcp)
- **FreePanel**: File an issue at [JSXSTEWART/FreePanel](https://github.com/JSXSTEWART/FreePanel/issues)

---

**Last Updated**: 2025-12-20
