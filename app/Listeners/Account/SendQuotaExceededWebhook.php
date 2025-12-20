<?php

namespace App\Listeners\Account;

use App\Services\Zapier\ZapierWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendQuotaExceededWebhook implements ShouldQueue
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
     * @param object $event The quota exceeded event
     */
    public function handle($event): void
    {
        if (!isset($event->account) || !isset($event->resourceType)) {
            return;
        }

        $payload = [
            'account_id' => $event->account->id,
            'resource_type' => $event->resourceType,
            'limit' => $event->limit ?? 0,
            'usage' => $event->usage ?? 0,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->webhookService->send('quota.exceeded', $payload);
    }
}
