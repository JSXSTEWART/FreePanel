<?php

namespace App\Services\Zapier;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZapierWebhookService
{
    /**
     * Supported payload formats
     */
    public const FORMAT_JSON = 'json';
    public const FORMAT_FORM_ENCODED = 'form-encoded';
    public const FORMAT_XML = 'xml';

    protected string $format = self::FORMAT_JSON;

    /**
     * Set the payload format for webhook transmission
     */
    public function setFormat(string $format): self
    {
        if (!in_array($format, [self::FORMAT_JSON, self::FORMAT_FORM_ENCODED, self::FORMAT_XML])) {
            throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
        $this->format = $format;
        return $this;
    }

    /**
     * Send a webhook payload to Zapier
     *
     * @param string $event The event type (e.g., 'account.created', 'ssl.expiring')
     * @param array $data The payload to send
     * @param string|null $webhookUrl Optional custom webhook URL
     * @param array $headers Optional custom headers
     * @return bool Whether the webhook was sent successfully
     */
    public function send(string $event, array $data, ?string $webhookUrl = null, array $headers = []): bool
    {
        $webhookUrl = $webhookUrl ?? config('zapier.webhooks.' . $event);

        if (!$webhookUrl) {
            Log::warning("No Zapier webhook configured for event: {$event}");
            return false;
        }

        try {
            $payload = $this->buildPayload($event, $data);
            $contentType = $this->getContentType();

            $defaultHeaders = [
                'Content-Type' => $contentType,
                'User-Agent' => 'FreePanel/1.0',
                'X-Event' => $event,
                'X-Timestamp' => now()->toIso8601String(),
            ];

            $allHeaders = array_merge($defaultHeaders, $headers);

            $response = Http::timeout(10)
                ->withHeaders($allHeaders)
                ->post($webhookUrl, $payload);

            if ($response->failed()) {
                Log::error("Zapier webhook failed for event {$event}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $webhookUrl,
                ]);
                return false;
            }

            Log::info("Zapier webhook sent successfully for event: {$event}", [
                'status' => $response->status(),
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("Error sending Zapier webhook for event {$event}", [
                'message' => $e->getMessage(),
                'exception' => class_basename($e),
            ]);
            return false;
        }
    }

    /**
     * Send a batch of events to Zapier
     *
     * @param array $events Array of ['event' => string, 'data' => array] pairs
     * @param string|null $format Optional format override
     * @return array Results keyed by event type
     */
    public function sendBatch(array $events, ?string $format = null): array
    {
        if ($format) {
            $this->setFormat($format);
        }

        $results = [];
        foreach ($events as $item) {
            $results[$item['event']] = $this->send(
                $item['event'],
                $item['data'],
                null,
                $item['headers'] ?? []
            );
        }
        return $results;
    }

    /**
     * Build the webhook payload in the configured format
     */
    protected function buildPayload(string $event, array $data): array|string
    {
        $basePayload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];

        return match ($this->format) {
            self::FORMAT_JSON => $basePayload,
            self::FORMAT_FORM_ENCODED => $this->flattenArray($basePayload),
            self::FORMAT_XML => $this->arrayToXml($basePayload),
            default => $basePayload,
        };
    }

    /**
     * Get the appropriate Content-Type header
     */
    protected function getContentType(): string
    {
        return match ($this->format) {
            self::FORMAT_JSON => 'application/json',
            self::FORMAT_FORM_ENCODED => 'application/x-www-form-urlencoded',
            self::FORMAT_XML => 'application/xml',
            default => 'application/json',
        };
    }

    /**
     * Flatten nested array for form-encoded format
     */
    protected function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Convert array to XML string
     */
    protected function arrayToXml(array $array, string $rootElement = 'root'): string
    {
        $xml = new \SimpleXMLElement("<?xml version=\"1.0\"?><{$rootElement}></{$rootElement}>");
        $this->arrayToXmlRecursive($array, $xml);
        return $xml->asXML();
    }

    /**
     * Recursively add array elements to XML
     */
    protected function arrayToXmlRecursive(array $array, \SimpleXMLElement $parent): void
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $child = $parent->addChild($key);
                $this->arrayToXmlRecursive($value, $child);
            } else {
                $parent->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }
}

