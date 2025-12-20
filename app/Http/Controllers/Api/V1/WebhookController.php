<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class WebhookController extends Controller
{
    /**
     * Receive and process incoming webhooks from external services (e.g., Zapier)
     *
     * @param Request $request
     * @return Response
     */
    public function receive(Request $request): Response
    {
        // Log the incoming webhook for debugging
        Log::info('Incoming webhook received', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'headers' => $request->headers->all(),
        ]);

        // Validate that request has payload
        if ($request->getContent() === '' || $request->getContent() === null) {
            Log::warning('Webhook received with empty payload');
            return response()->json(['error' => 'Empty payload'], 400);
        }

        // Parse the incoming webhook data
        $payload = $this->parsePayload($request);

        if (!$payload) {
            return response()->json(['error' => 'Unable to parse payload'], 400);
        }

        Log::info('Webhook payload parsed', [
            'event' => $payload['event'] ?? 'unknown',
            'timestamp' => $payload['timestamp'] ?? 'unknown',
        ]);

        // Process the webhook event
        if (isset($payload['event'])) {
            $this->handleWebhookEvent($payload);
        }

        // Zapier expects a successful response
        return response()->json(['success' => true], 200);
    }

    /**
     * Health check endpoint for Zapier webhook
     *
     * @return Response
     */
    public function health(): Response
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0'),
            'signature_verification' => [
                'enabled' => config('zapier.signature.enabled', true),
                'algorithm' => config('zapier.signature.algorithm', 'sha256'),
                'required_headers' => [
                    'X-Zapier-Signature',
                    'X-Zapier-Timestamp',
                ],
                'signing_method' => 'HMAC-SHA256(payload.timestamp, secret)',
                'timestamp_tolerance_seconds' => config('zapier.signature.timestamp_tolerance', 300),
            ],
        ], 200);
    }

    /**
     * Send webhook to external service
     *
     * @param Request $request
     * @return Response
     */
    public function send(Request $request): Response
    {
        $validated = $request->validate([
            'event' => 'required|string',
            'url' => 'required|url',
            'data' => 'required|array',
            'format' => 'nullable|in:json,form-encoded,xml',
        ]);

        try {
            $service = app(\App\Services\Zapier\ZapierWebhookService::class);

            if (isset($validated['format'])) {
                $service->setFormat($validated['format']);
            }

            $success = $service->send(
                $validated['event'],
                $validated['data'],
                $validated['url']
            );

            return response()->json([
                'success' => $success,
                'event' => $validated['event'],
            ], $success ? 200 : 500);
        } catch (\Exception $e) {
            Log::error('Error sending webhook', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse incoming webhook payload based on Content-Type
     *
     * @param Request $request
     * @return array|null
     */
    protected function parsePayload(Request $request): ?array
    {
        $contentType = $request->header('Content-Type', 'application/json');

        try {
            if (str_contains($contentType, 'application/json')) {
                return json_decode($request->getContent(), true) ?? $request->all();
            }

            if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                return $request->all();
            }

            if (str_contains($contentType, 'application/xml')) {
                $xml = simplexml_load_string($request->getContent());
                if ($xml === false) {
                    return null;
                }
                return json_decode(json_encode($xml), true);
            }

            // Default to trying JSON first, then form data
            $json = json_decode($request->getContent(), true);
            return $json ?? $request->all();
        } catch (\Exception $e) {
            Log::error('Error parsing webhook payload', [
                'content_type' => $contentType,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Handle webhook event and dispatch to appropriate listeners
     *
     * @param array $payload
     * @return void
     */
    protected function handleWebhookEvent(array $payload): void
    {
        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];

        if (!$event) {
            Log::warning('Webhook event missing event key');
            return;
        }

        // Dispatch event to Laravel event system
        // This allows listeners to handle events consistently
        event('webhook.received', [$event, $data]);

        Log::info("Webhook event handled: {$event}");
    }
}
