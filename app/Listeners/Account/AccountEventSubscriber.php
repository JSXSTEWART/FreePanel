<?php

namespace App\Listeners\Account;

use App\Events\Account\AccountCreated;
use App\Events\Account\AccountSuspended;
use App\Services\System\UserManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class AccountEventSubscriber
{
    /**
     * Handle account created events.
     */
    public function handleAccountCreated(AccountCreated $event): void
    {
        $account = $event->account;

        Log::info("Account created: {$account->username}");

        // Create system user
        // Create home directory
        // Set up quotas
    }

    /**
     * Handle account suspended events.
     */
    public function handleAccountSuspended(AccountSuspended $event): void
    {
        $account = $event->account;

        Log::info("Account suspended: {$account->username}");

        // Disable system user
        // Stop running processes
        // Disable web access
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            AccountCreated::class => 'handleAccountCreated',
            AccountSuspended::class => 'handleAccountSuspended',
        ];
    }
}
