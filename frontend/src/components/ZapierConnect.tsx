import React, { useEffect, useState } from 'react';
import axios from 'axios';

interface ZapierConnection {
  id: number;
  mcp_server_url: string;
  is_active: boolean;
  connected_at: string;
  last_used_at: string | null;
}

interface ZapierTool {
  name: string;
  description: string;
  inputSchema: Record<string, any>;
}

const ZapierConnect: React.FC = () => {
  const [connection, setConnection] = useState<ZapierConnection | null>(null);
  const [tools, setTools] = useState<ZapierTool[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadConnection();
    initializeZapierEmbed();
  }, []);

  const loadConnection = async () => {
    try {
      const response = await axios.get('/api/v1/zapier/connection');
      setConnection(response.data.connection);
      loadTools();
    } catch (err: any) {
      if (err.response?.status !== 404) {
        setError('Failed to load Zapier connection');
      }
    } finally {
      setLoading(false);
    }
  };

  const loadTools = async () => {
    try {
      const response = await axios.get('/api/v1/zapier/tools');
      setTools(response.data.tools);
    } catch (err) {
      console.error('Failed to load Zapier tools', err);
    }
  };

  const initializeZapierEmbed = () => {
    // Load Zapier MCP embed script
    const script = document.createElement('script');
    script.src = 'https://mcp.zapier.com/embed.js';
    script.async = true;
    document.body.appendChild(script);

    // Listen for the MCP server URL event
    window.addEventListener('message', async (event) => {
      if (event.data?.type === 'mcp-server-url') {
        await handleZapierConnect(event.data.url);
      }
    });
  };

  const handleZapierConnect = async (serverUrl: string) => {
    try {
      const response = await axios.post('/api/v1/zapier/connect', {
        mcp_server_url: serverUrl,
      });
      setConnection(response.data.connection);
      setError(null);
      await loadTools();
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to connect to Zapier');
    }
  };

  const handleDisconnect = async () => {
    if (!confirm('Are you sure you want to disconnect Zapier?')) {
      return;
    }

    try {
      await axios.delete('/api/v1/zapier/disconnect');
      setConnection(null);
      setTools([]);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to disconnect');
    }
  };

  const executeTool = async (toolName: string, params: Record<string, any>) => {
    try {
      const response = await axios.post('/api/v1/zapier/execute', {
        tool_name: toolName,
        params,
      });
      return response.data.result;
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to execute tool');
      throw err;
    }
  };

  if (loading) {
    return <div className="text-center py-8">Loading...</div>;
  }

  return (
    <div className="max-w-4xl mx-auto p-6">
      <h1 className="text-3xl font-bold mb-6">Zapier Integration</h1>

      {error && (
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          {error}
        </div>
      )}

      {!connection ? (
        <div className="bg-white shadow rounded-lg p-6">
          <h2 className="text-xl font-semibold mb-4">Connect Your Zapier Account</h2>
          <p className="text-gray-600 mb-6">
            Connect your Zapier account to automate workflows and integrate with thousands of apps.
          </p>
          
          {/* Zapier MCP Embed */}
          <div
            data-zapier-mcp-embed
            data-embed-id="5728245c-97fc-4927-a279-1c8dcc0c526d"
            className="mb-4"
          ></div>

          <p className="text-sm text-gray-500">
            After connecting, you'll be able to trigger Zapier workflows directly from FreePanel.
          </p>
        </div>
      ) : (
        <div className="space-y-6">
          <div className="bg-white shadow rounded-lg p-6">
            <div className="flex justify-between items-start mb-4">
              <div>
                <h2 className="text-xl font-semibold">Zapier Connected</h2>
                <p className="text-sm text-gray-500 mt-1">
                  Connected on {new Date(connection.connected_at).toLocaleDateString()}
                </p>
                {connection.last_used_at && (
                  <p className="text-sm text-gray-500">
                    Last used {new Date(connection.last_used_at).toLocaleDateString()}
                  </p>
                )}
              </div>
              <button
                onClick={handleDisconnect}
                className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
              >
                Disconnect
              </button>
            </div>
          </div>

          <div className="bg-white shadow rounded-lg p-6">
            <h3 className="text-lg font-semibold mb-4">Available Zapier Tools</h3>
            {tools.length === 0 ? (
              <p className="text-gray-500">No tools configured in Zapier yet.</p>
            ) : (
              <div className="space-y-3">
                {tools.map((tool) => (
                  <div key={tool.name} className="border rounded p-4">
                    <h4 className="font-semibold">{tool.name}</h4>
                    <p className="text-sm text-gray-600 mt-1">{tool.description}</p>
                  </div>
                ))}
              </div>
            )}
            <p className="text-sm text-gray-500 mt-4">
              Configure tools in your Zapier MCP server at{' '}
              <a
                href="https://mcp.zapier.com"
                target="_blank"
                rel="noopener noreferrer"
                className="text-blue-600 hover:underline"
              >
                mcp.zapier.com
              </a>
            </p>
          </div>
        </div>
      )}
    </div>
  );
};

export default ZapierConnect;
