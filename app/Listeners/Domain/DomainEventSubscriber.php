<?php

namespace App\Listeners\Domain;

use App\Events\Domain\DomainCreated;
use App\Events\Domain\DomainDeleted;
use App\Services\WebServer\WebServerInterface;
use App\Services\Dns\DnsInterface;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class DomainEventSubscriber
{
    /**
     * Handle domain created events.
     */
    public function handleDomainCreated(DomainCreated $event): void
    {
        $domain = $event->domain;

        Log::info("Domain created: {$domain->name}");

        // Create web server virtual host
        // Create DNS zone
        // Create home directory structure
    }

    /**
     * Handle domain deleted events.
     */
    public function handleDomainDeleted(DomainDeleted $event): void
    {
        $domain = $event->domain;

        Log::info("Domain deleted: {$domain->name}");

        // Remove web server virtual host
        // Remove DNS zone
        // Optionally archive home directory
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            DomainCreated::class => 'handleDomainCreated',
            DomainDeleted::class => 'handleDomainDeleted',
        ];
    }
}
