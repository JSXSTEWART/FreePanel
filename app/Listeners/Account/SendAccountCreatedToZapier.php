<?php

namespace App\Listeners\Account;

use App\Events\Account\AccountCreated;
use App\Services\Zapier\ZapierWebhookService;

class SendAccountCreatedToZapier
{
    public function __construct(
        private ZapierWebhookService $zapierService
    ) {}

    public function handle(AccountCreated $event): void
    {
        if (!config('zapier.enabled')) {
            return;
        }

        $data = [
            'id' => $event->account->id,
            'username' => $event->account->username,
            'email' => $event->account->email,
            'package_id' => $event->account->package_id,
            'status' => $event->account->status,
            'created_at' => $event->account->created_at?->toIso8601String(),
        ];

        $this->zapierService->send('account.created', $data);
    }
}
