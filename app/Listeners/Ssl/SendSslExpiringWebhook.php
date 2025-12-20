<?php

namespace App\Listeners\Ssl;

use App\Services\Zapier\ZapierWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendSslExpiringWebhook implements ShouldQueue
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
     * @param object $event The SSL expiring event
     */
    public function handle($event): void
    {
        if (!isset($event->certificate)) {
            return;
        }

        $daysRemaining = now()->diffInDays($event->certificate->expires_at);

        $payload = [
            'id' => $event->certificate->id,
            'domain_id' => $event->certificate->domain_id,
            'domain' => $event->certificate->domain->name ?? null,
            'expires_at' => $event->certificate->expires_at->toIso8601String(),
            'days_remaining' => $daysRemaining,
        ];

        $this->webhookService->send('ssl.expiring', $payload);
    }
}
