<?php

namespace App\Services\WebServer;

use App\Models\Domain;
use App\Models\Subdomain;
use App\Models\SslCertificate;

interface WebServerInterface
{
    /**
     * Create a virtual host for a domain
     */
    public function createVirtualHost(Domain $domain): void;

    /**
     * Update an existing virtual host
     */
    public function updateVirtualHost(Domain $domain): void;

    /**
     * Remove a virtual host
     */
    public function removeVirtualHost(Domain $domain): void;

    /**
     * Enable a virtual host
     */
    public function enableVirtualHost(Domain $domain): void;

    /**
     * Disable a virtual host
     */
    public function disableVirtualHost(Domain $domain): void;

    /**
     * Create a virtual host for a subdomain
     */
    public function createSubdomainVirtualHost(Subdomain $subdomain): void;

    /**
     * Remove a subdomain virtual host
     */
    public function removeSubdomainVirtualHost(Subdomain $subdomain): void;

    /**
     * Enable SSL for a domain
     */
    public function enableSsl(Domain $domain, SslCertificate $certificate): void;

    /**
     * Disable SSL for a domain
     */
    public function disableSsl(Domain $domain): void;

    /**
     * Test configuration syntax
     */
    public function testConfig(): bool;

    /**
     * Reload the web server
     */
    public function reload(): void;

    /**
     * Get web server version
     */
    public function getVersion(): string;
}
