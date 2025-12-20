<?php

namespace App\Listeners\Domain;

use App\Events\Domain\DomainDeleted;
use App\Services\Zapier\ZapierWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendDomainDeletedWebhook implements ShouldQueue
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
    public function handle(DomainDeleted $event): void
    {
        $payload = [
            'id' => $event->domain->id,
            'account_id' => $event->domain->account_id,
            'name' => $event->domain->name,
            'deleted_at' => now()->toIso8601String(),
        ];

        $this->webhookService->send('domain.deleted', $payload);
    }
}
