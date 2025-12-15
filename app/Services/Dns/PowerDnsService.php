<?php

namespace App\Services\Dns;

use App\Models\Domain;
use App\Models\DnsZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PowerDnsService implements DnsInterface
{
    protected string $pdnsDb = 'powerdns';

    public function createZone(Domain $domain): DnsZone
    {
        $zone = DnsZone::create([
            'domain_id' => $domain->id,
            'name' => $domain->name,
            'serial' => $this->generateSerial(),
            'refresh' => 86400,
            'retry' => 7200,
            'expire' => 3600000,
            'minimum' => 172800,
        ]);

        // Insert into PowerDNS domains table
        DB::connection($this->pdnsDb)->table('domains')->insert([
            'name' => $domain->name,
            'type' => 'NATIVE',
            'account' => $domain->account->username ?? '',
        ]);

        $this->createDefaultRecords($zone);

        Log::info("PowerDNS zone created for {$domain->name}");

        return $zone;
    }

    public function removeZone(Domain $domain): void
    {
        // Remove from PowerDNS
        $pdnsDomain = DB::connection($this->pdnsDb)
            ->table('domains')
            ->where('name', $domain->name)
            ->first();

        if ($pdnsDomain) {
            DB::connection($this->pdnsDb)
                ->table('records')
                ->where('domain_id', $pdnsDomain->id)
                ->delete();

            DB::connection($this->pdnsDb)
                ->table('domains')
                ->where('id', $pdnsDomain->id)
                ->delete();
        }

        Log::info("PowerDNS zone removed for {$domain->name}");
    }

    public function resetZone(DnsZone $zone): void
    {
        $pdnsDomain = DB::connection($this->pdnsDb)
            ->table('domains')
            ->where('name', $zone->name)
            ->first();

        if ($pdnsDomain) {
            DB::connection($this->pdnsDb)
                ->table('records')
                ->where('domain_id', $pdnsDomain->id)
                ->delete();
        }

        $zone->records()->delete();
        $this->createDefaultRecords($zone);
        $this->incrementSerial($zone);
    }

    public function addRecord(DnsZone $zone, array $record): void
    {
        $pdnsDomain = DB::connection($this->pdnsDb)
            ->table('domains')
            ->where('name', $zone->name)
            ->first();

        if ($pdnsDomain) {
            DB::connection($this->pdnsDb)->table('records')->insert([
                'domain_id' => $pdnsDomain->id,
                'name' => $record['name'],
                'type' => $record['type'],
                'content' => $record['content'],
                'ttl' => $record['ttl'] ?? 3600,
                'prio' => $record['priority'] ?? 0,
            ]);
        }

        $this->incrementSerial($zone);
    }

    public function updateRecord(DnsZone $zone, array $oldRecord, array $newRecord): void
    {
        $pdnsDomain = DB::connection($this->pdnsDb)
            ->table('domains')
            ->where('name', $zone->name)
            ->first();

        if ($pdnsDomain) {
            DB::connection($this->pdnsDb)->table('records')
                ->where('domain_id', $pdnsDomain->id)
                ->where('name', $oldRecord['name'])
                ->where('type', $oldRecord['type'])
                ->update([
                    'name' => $newRecord['name'],
                    'type' => $newRecord['type'],
                    'content' => $newRecord['content'],
                    'ttl' => $newRecord['ttl'] ?? 3600,
                    'prio' => $newRecord['priority'] ?? 0,
                ]);
        }

        $this->incrementSerial($zone);
    }

    public function removeRecord(DnsZone $zone, string $name, string $type): void
    {
        $pdnsDomain = DB::connection($this->pdnsDb)
            ->table('domains')
            ->where('name', $zone->name)
            ->first();

        if ($pdnsDomain) {
            DB::connection($this->pdnsDb)->table('records')
                ->where('domain_id', $pdnsDomain->id)
                ->where('name', $name)
                ->where('type', $type)
                ->delete();
        }

        $this->incrementSerial($zone);
    }

    public function reload(): void
    {
        // PowerDNS reads from database directly, no reload needed
        // But we can signal pdns_control if needed
        // Process::run('pdns_control reload');
    }

    public function checkZone(string $zoneName, string $zoneFile): bool
    {
        // PowerDNS doesn't use zone files, validation is done at DB level
        return true;
    }

    protected function createDefaultRecords(DnsZone $zone): void
    {
        $serverIp = config('freepanel.server_ip', '127.0.0.1');
        $hostname = config('freepanel.hostname', 'ns1.freepanel.local');

        $defaultRecords = [
            ['name' => $zone->name, 'type' => 'SOA', 'content' => "{$hostname} admin.{$zone->name} {$zone->serial} 86400 7200 3600000 172800"],
            ['name' => $zone->name, 'type' => 'NS', 'content' => $hostname],
            ['name' => $zone->name, 'type' => 'A', 'content' => $serverIp],
            ['name' => "www.{$zone->name}", 'type' => 'A', 'content' => $serverIp],
            ['name' => "mail.{$zone->name}", 'type' => 'A', 'content' => $serverIp],
            ['name' => $zone->name, 'type' => 'MX', 'content' => "mail.{$zone->name}", 'priority' => 10],
        ];

        foreach ($defaultRecords as $record) {
            $this->addRecord($zone, $record);
        }
    }

    protected function generateSerial(): int
    {
        return (int) date('Ymd') . '01';
    }

    protected function incrementSerial(DnsZone $zone): void
    {
        $currentDate = (int) date('Ymd');
        $serialDate = (int) substr($zone->serial, 0, 8);

        if ($serialDate < $currentDate) {
            $zone->serial = $currentDate . '01';
        } else {
            $zone->serial = $zone->serial + 1;
        }

        $zone->save();
    }
}
