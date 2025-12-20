<?php

namespace App\Listeners\Domain;

use App\Events\Domain\DomainCreated;
use App\Services\Zapier\ZapierWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendDomainCreatedWebhook implements ShouldQueue
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
     */
    public function handle(DomainCreated $event): void
    {
        $payload = [
            'id' => $event->domain->id,
            'account_id' => $event->domain->account_id,
            'name' => $event->domain->name,
            'created_at' => $event->domain->created_at?->toIso8601String(),
        ];

        $this->webhookService->send('domain.created', $payload);
    }
}
