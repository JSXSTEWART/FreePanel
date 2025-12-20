<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Zapier\WebhookSignatureService;
use Symfony\Component\HttpFoundation\Response;

class VerifyZapierWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get signature and timestamp from headers
        $signature = $request->header(WebhookSignatureService::signatureHeader());
        $timestamp = $request->header(WebhookSignatureService::timestampHeader());

        // Verify headers are present
        if (!$signature || !$timestamp) {
            Log::warning('Webhook signature verification failed: missing headers', [
                'has_signature' => !empty($signature),
                'has_timestamp' => !empty($timestamp),
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'Missing webhook authentication headers',
                'required_headers' => [
                    WebhookSignatureService::signatureHeader(),
                    WebhookSignatureService::timestampHeader(),
                ],
            ], 401);
        }

        // Get webhook secret from config
        $secret = config('zapier.webhook_secret');

        if (!$secret) {
            Log::error('Webhook secret not configured', [
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'Server configuration error',
            ], 500);
        }

        // Verify signature
        $signatureService = app(WebhookSignatureService::class);
        $payload = $request->getContent();

        if (!$signatureService->verify($payload, $signature, $timestamp, $secret)) {
            Log::warning('Webhook signature verification failed: invalid signature', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return response()->json([
                'error' => 'Invalid webhook signature',
            ], 403);
        }

        // Attach verified flag to request
        $request->attributes->set('webhook_verified', true);

        return $next($request);
    }
}
