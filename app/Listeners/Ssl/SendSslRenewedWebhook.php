<?php

namespace App\Listeners\Ssl;

use App\Services\Zapier\ZapierWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendSslRenewedWebhook implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        protected ZapierWebhookService $webhookService
    ) {}

    /**
     * Handle the event.
     *
     * @param object $event The SSL renewed event
     */
    public function handle($event): void
    {
        if (!isset($event->certificate)) {
            return;
        }

        $payload = [
            'id' => $event->certificate->id,
            'domain_id' => $event->certificate->domain_id,
            'domain' => $event->certificate->domain->name ?? null,
            'installed_at' => now()->toIso8601String(),
            'expires_at' => $event->certificate->expires_at->toIso8601String(),
        ];

        $this->webhookService->send('ssl.renewed', $payload);
    }
}
