<?php

namespace App\Services\Zapier;

use App\Models\UserZapierConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZapierMcpClient
{
    private string $embedSecret;

    public function __construct()
    {
        $this->embedSecret = config('zapier.embed_secret');
    }

    /**
     * List available Zapier tools for a user
     */
    public function listTools(UserZapierConnection $connection): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->embedSecret}",
                'Content-Type' => 'application/json',
            ])->post($connection->mcp_server_url, [
                'jsonrpc' => '2.0',
                'id' => uniqid(),
                'method' => 'tools/list',
                'params' => [],
            ]);

            if ($response->failed()) {
                Log::error('Failed to list Zapier MCP tools', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $connection->markAsUsed();

            return $response->json('result.tools', []);
        } catch (\Exception $e) {
            Log::error('Error listing Zapier MCP tools', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Execute a Zapier tool (action) for a user
     */
    public function executeTool(UserZapierConnection $connection, string $toolName, array $params): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->embedSecret}",
                'Content-Type' => 'application/json',
            ])->post($connection->mcp_server_url, [
                'jsonrpc' => '2.0',
                'id' => uniqid(),
                'method' => 'tools/call',
                'params' => [
                    'name' => $toolName,
                    'arguments' => $params,
                ],
            ]);

            if ($response->failed()) {
                Log::error('Failed to execute Zapier MCP tool', [
                    'tool' => $toolName,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $connection->markAsUsed();

            return $response->json('result');
        } catch (\Exception $e) {
            Log::error('Error executing Zapier MCP tool', [
                'tool' => $toolName,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Test connection to user's Zapier MCP server
     */
    public function testConnection(string $serverUrl): bool
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->embedSecret}",
                    'Content-Type' => 'application/json',
                ])
                ->post($serverUrl, [
                    'jsonrpc' => '2.0',
                    'id' => uniqid(),
                    'method' => 'initialize',
                    'params' => [
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => [],
                        'clientInfo' => [
                            'name' => 'FreePanel',
                            'version' => '1.0.0',
                        ],
                    ],
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Error testing Zapier MCP connection', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
