<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserZapierConnection;
use App\Services\Zapier\ZapierMcpClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ZapierConnectionController extends Controller
{
    public function __construct(
        private ZapierMcpClient $mcpClient
    ) {}

    /**
     * Store a new Zapier MCP connection for the authenticated user
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mcp_server_url' => 'required|url|unique:user_zapier_connections,mcp_server_url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Test the connection before storing
        if (!$this->mcpClient->testConnection($request->mcp_server_url)) {
            return response()->json([
                'message' => 'Failed to connect to Zapier MCP server',
            ], 400);
        }

        $connection = UserZapierConnection::create([
            'user_id' => $request->user()->id,
            'mcp_server_url' => $request->mcp_server_url,
            'is_active' => true,
            'connected_at' => now(),
        ]);

        return response()->json([
            'message' => 'Zapier connection established successfully',
            'connection' => $connection,
        ], 201);
    }

    /**
     * Get the authenticated user's Zapier connection
     */
    public function show(Request $request): JsonResponse
    {
        $connection = UserZapierConnection::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            return response()->json([
                'message' => 'No active Zapier connection found',
            ], 404);
        }

        return response()->json([
            'connection' => $connection,
        ]);
    }

    /**
     * List available Zapier tools for the authenticated user
     */
    public function listTools(Request $request): JsonResponse
    {
        $connection = UserZapierConnection::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            return response()->json([
                'message' => 'No active Zapier connection found',
            ], 404);
        }

        $tools = $this->mcpClient->listTools($connection);

        return response()->json([
            'tools' => $tools,
        ]);
    }

    /**
     * Execute a Zapier tool (action)
     */
    public function executeTool(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tool_name' => 'required|string',
            'params' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $connection = UserZapierConnection::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            return response()->json([
                'message' => 'No active Zapier connection found',
            ], 404);
        }

        $result = $this->mcpClient->executeTool(
            $connection,
            $request->tool_name,
            $request->params
        );

        if (!$result) {
            return response()->json([
                'message' => 'Failed to execute Zapier tool',
            ], 500);
        }

        return response()->json([
            'result' => $result,
        ]);
    }

    /**
     * Disconnect the user's Zapier connection
     */
    public function destroy(Request $request): JsonResponse
    {
        $connection = UserZapierConnection::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            return response()->json([
                'message' => 'No active Zapier connection found',
            ], 404);
        }

        $connection->disconnect();

        return response()->json([
            'message' => 'Zapier connection disconnected successfully',
        ]);
    }
}
