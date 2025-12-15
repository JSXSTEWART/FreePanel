<?php

namespace App\Services\Dns;

use App\Models\Domain;
use App\Models\DnsZone;
use App\Models\DnsRecord;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\View;

class BindService implements DnsInterface
{
    protected string $namedConfDir;
    protected string $zonesDir;
    protected string $serviceName;

    public function __construct()
    {
        // Detect distribution
        if (file_exists('/etc/bind')) {
            // Debian/Ubuntu
            $this->namedConfDir = '/etc/bind';
            $this->zonesDir = '/var/lib/bind';
            $this->serviceName = 'bind9';
        } else {
            // RHEL/CentOS
            $this->namedConfDir = '/etc/named';
            $this->zonesDir = '/var/named';
            $this->serviceName = 'named';
        }
    }

    public function createZone(Domain $domain): DnsZone
    {
        $serverIp = config('freepanel.server_ip');
        $ns1 = config('freepanel.nameservers.ns1', "ns1.{$domain->name}");
        $ns2 = config('freepanel.nameservers.ns2', "ns2.{$domain->name}");
        $adminEmail = config('freepanel.admin_email', "admin.{$domain->name}");

        // Create zone record in database
        $zone = DnsZone::create([
            'domain_id' => $domain->id,
            'serial' => $this->generateSerial(),
            'refresh' => 28800,
            'retry' => 7200,
            'expire' => 1209600,
            'minimum' => 86400,
            'ns1' => $ns1,
            'ns2' => $ns2,
        ]);

        // Create default records
        $defaultRecords = [
            ['type' => 'SOA', 'name' => '@', 'content' => "{$ns1} {$adminEmail} {$zone->serial} {$zone->refresh} {$zone->retry} {$zone->expire} {$zone->minimum}", 'ttl' => 86400],
            ['type' => 'NS', 'name' => '@', 'content' => $ns1, 'ttl' => 86400],
            ['type' => 'NS', 'name' => '@', 'content' => $ns2, 'ttl' => 86400],
            ['type' => 'A', 'name' => '@', 'content' => $serverIp, 'ttl' => 3600],
            ['type' => 'A', 'name' => 'www', 'content' => $serverIp, 'ttl' => 3600],
            ['type' => 'A', 'name' => 'mail', 'content' => $serverIp, 'ttl' => 3600],
            ['type' => 'MX', 'name' => '@', 'content' => "mail.{$domain->name}", 'ttl' => 3600, 'priority' => 10],
            ['type' => 'TXT', 'name' => '@', 'content' => 'v=spf1 a mx ~all', 'ttl' => 3600],
        ];

        foreach ($defaultRecords as $record) {
            DnsRecord::create([
                'dns_zone_id' => $zone->id,
                'type' => $record['type'],
                'name' => $record['name'],
                'content' => $record['content'],
                'ttl' => $record['ttl'],
                'priority' => $record['priority'] ?? null,
            ]);
        }

        // Write zone file
        $this->writeZoneFile($zone);

        // Add zone to named.conf
        $this->addZoneToConfig($domain->name);

        $this->reload();

        return $zone;
    }

    public function removeZone(Domain $domain): void
    {
        $zoneFile = "{$this->zonesDir}/{$domain->name}.db";

        // Remove zone file
        if (File::exists($zoneFile)) {
            File::delete($zoneFile);
        }

        // Remove from named.conf
        $this->removeZoneFromConfig($domain->name);

        // Delete zone from database (cascade deletes records)
        if ($domain->dnsZone) {
            $domain->dnsZone->delete();
        }

        $this->reload();
    }

    public function resetZone(DnsZone $zone): void
    {
        $domain = $zone->domain;
        $serverIp = config('freepanel.server_ip');

        // Delete all non-essential records
        DnsRecord::where('dns_zone_id', $zone->id)
            ->where('type', '!=', 'SOA')
            ->whereNot(fn($q) => $q->where('type', 'NS')->where('name', '@'))
            ->delete();

        // Recreate default records
        $defaultRecords = [
            ['type' => 'A', 'name' => '@', 'content' => $serverIp, 'ttl' => 3600],
            ['type' => 'A', 'name' => 'www', 'content' => $serverIp, 'ttl' => 3600],
            ['type' => 'A', 'name' => 'mail', 'content' => $serverIp, 'ttl' => 3600],
            ['type' => 'MX', 'name' => '@', 'content' => "mail.{$domain->name}", 'ttl' => 3600, 'priority' => 10],
            ['type' => 'TXT', 'name' => '@', 'content' => 'v=spf1 a mx ~all', 'ttl' => 3600],
        ];

        foreach ($defaultRecords as $record) {
            DnsRecord::create([
                'dns_zone_id' => $zone->id,
                'type' => $record['type'],
                'name' => $record['name'],
                'content' => $record['content'],
                'ttl' => $record['ttl'],
                'priority' => $record['priority'] ?? null,
            ]);
        }

        $this->incrementSerial($zone);
        $this->writeZoneFile($zone);
        $this->reload();
    }

    public function addRecord(DnsZone $zone, array $record): void
    {
        $this->incrementSerial($zone);
        $this->writeZoneFile($zone->fresh()->load('records'));
        $this->reload();
    }

    public function updateRecord(DnsZone $zone, array $oldRecord, array $newRecord): void
    {
        $this->incrementSerial($zone);
        $this->writeZoneFile($zone->fresh()->load('records'));
        $this->reload();
    }

    public function removeRecord(DnsZone $zone, string $name, string $type): void
    {
        $this->incrementSerial($zone);
        $this->writeZoneFile($zone->fresh()->load('records'));
        $this->reload();
    }

    public function reload(): void
    {
        Process::run("systemctl reload {$this->serviceName}");
    }

    public function checkZone(string $zoneName, string $zoneFile): bool
    {
        $result = Process::run("named-checkzone {$zoneName} {$zoneFile}");
        return $result->successful();
    }

    protected function writeZoneFile(DnsZone $zone): void
    {
        $domain = $zone->domain;
        $zoneFile = "{$this->zonesDir}/{$domain->name}.db";

        $content = View::make('system.templates.dns.zone', [
            'zone' => $zone,
            'domain' => $domain,
            'records' => $zone->records,
        ])->render();

        File::put($zoneFile, $content);
        chmod($zoneFile, 0644);

        // Validate zone file
        if (!$this->checkZone($domain->name, $zoneFile)) {
            throw new \RuntimeException("Invalid zone file for {$domain->name}");
        }
    }

    protected function addZoneToConfig(string $zoneName): void
    {
        $configFile = "{$this->namedConfDir}/named.conf.local";
        $zoneFile = "{$this->zonesDir}/{$zoneName}.db";

        $zoneConfig = <<<EOT

zone "{$zoneName}" {
    type master;
    file "{$zoneFile}";
    allow-transfer { none; };
};
EOT;

        // Check if zone already exists
        $content = File::get($configFile);
        if (strpos($content, "zone \"{$zoneName}\"") === false) {
            File::append($configFile, $zoneConfig);
        }
    }

    protected function removeZoneFromConfig(string $zoneName): void
    {
        $configFile = "{$this->namedConfDir}/named.conf.local";

        if (!File::exists($configFile)) {
            return;
        }

        $content = File::get($configFile);

        // Remove zone block
        $pattern = '/\n?zone\s+"' . preg_quote($zoneName, '/') . '"\s*\{[^}]+\};\n?/s';
        $content = preg_replace($pattern, '', $content);

        File::put($configFile, $content);
    }

    protected function incrementSerial(DnsZone $zone): void
    {
        $currentDate = date('Ymd');
        $currentSerial = $zone->serial;

        // Serial format: YYYYMMDDNN
        $serialDate = substr($currentSerial, 0, 8);
        $serialNum = (int) substr($currentSerial, 8, 2);

        if ($serialDate === $currentDate) {
            $newSerial = $currentDate . str_pad($serialNum + 1, 2, '0', STR_PAD_LEFT);
        } else {
            $newSerial = $currentDate . '01';
        }

        $zone->update(['serial' => $newSerial]);

        // Update SOA record
        $soaRecord = $zone->records()->where('type', 'SOA')->first();
        if ($soaRecord) {
            $soaContent = "{$zone->ns1} admin.{$zone->domain->name} {$newSerial} {$zone->refresh} {$zone->retry} {$zone->expire} {$zone->minimum}";
            $soaRecord->update(['content' => $soaContent]);
        }
    }

    protected function generateSerial(): string
    {
        return date('Ymd') . '01';
    }
}
