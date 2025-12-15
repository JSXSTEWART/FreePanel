<?php

namespace App\Services\Dns;

use App\Models\Domain;
use App\Models\DnsZone;

interface DnsInterface
{
    /**
     * Create a DNS zone for a domain
     */
    public function createZone(Domain $domain): DnsZone;

    /**
     * Remove a DNS zone
     */
    public function removeZone(Domain $domain): void;

    /**
     * Reset a zone to default records
     */
    public function resetZone(DnsZone $zone): void;

    /**
     * Add a DNS record
     */
    public function addRecord(DnsZone $zone, array $record): void;

    /**
     * Update a DNS record
     */
    public function updateRecord(DnsZone $zone, array $oldRecord, array $newRecord): void;

    /**
     * Remove a DNS record
     */
    public function removeRecord(DnsZone $zone, string $name, string $type): void;

    /**
     * Reload DNS server
     */
    public function reload(): void;

    /**
     * Check zone syntax
     */
    public function checkZone(string $zoneName, string $zoneFile): bool;
}
