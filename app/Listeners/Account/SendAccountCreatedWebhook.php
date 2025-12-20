<?php

namespace App\Listeners\Account;

use App\Events\Account\AccountCreated;
use App\Services\Zapier\ZapierWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendAccountCreatedWebhook implements ShouldQueue
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
    public function handle(AccountCreated $event): void
    {
        $payload = [
            'id' => $event->account->id,
            'username' => $event->account->username,
            'email' => $event->account->email,
            'package_id' => $event->account->package_id,
            'created_at' => $event->account->created_at?->toIso8601String(),
        ];

        $this->webhookService->send('account.created', $payload);
    }
}
