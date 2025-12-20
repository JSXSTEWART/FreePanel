<?php

namespace App\Listeners\Account;

use App\Events\Account\AccountSuspended;
use App\Services\Zapier\ZapierWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendAccountSuspendedWebhook implements ShouldQueue
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
    public function handle(AccountSuspended $event): void
    {
        $payload = [
            'id' => $event->account->id,
            'username' => $event->account->username,
            'reason' => $event->reason ?? 'Account suspended by administrator',
        ];

        $this->webhookService->send('account.suspended', $payload);
    }
}
