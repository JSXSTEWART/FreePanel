<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

use App\Events\Domain\DomainCreated;
use App\Events\Domain\DomainDeleted;
use App\Events\Account\AccountCreated;
use App\Events\Account\AccountSuspended;
use App\Listeners\Domain\DomainEventSubscriber;
use App\Listeners\Account\AccountEventSubscriber;
use App\Listeners\Account\SendAccountCreatedToZapier;
use App\Listeners\Account\SendAccountSuspendedToZapier;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        AccountCreated::class => [
            SendAccountCreatedToZapier::class,
        ],
        AccountSuspended::class => [
            SendAccountSuspendedToZapier::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [
        DomainEventSubscriber::class,
        AccountEventSubscriber::class,
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
