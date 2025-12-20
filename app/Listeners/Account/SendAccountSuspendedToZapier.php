<?php

namespace App\Listeners\Account;

use App\Events\Account\AccountSuspended;
use App\Services\Zapier\ZapierWebhookService;

class SendAccountSuspendedToZapier
{
    public function __construct(
        private ZapierWebhookService $zapierService
    ) {}

    public function handle(AccountSuspended $event): void
    {
        if (!config('zapier.enabled')) {
            return;
        }

        $data = [
            'id' => $event->account->id,
            'username' => $event->account->username,
            'email' => $event->account->email,
            'status' => $event->account->status,
            'suspended_at' => now()->toIso8601String(),
        ];

        $this->zapierService->send('account.suspended', $data);
    }
}
