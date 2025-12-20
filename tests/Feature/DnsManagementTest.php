<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Account;
use App\Models\Domain;
use App\Models\DnsZone;
use App\Models\DnsRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DnsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_zone_and_record(): void
    {
        $user = \App\Models\User::create(['username' => 'jdoe', 'email' => 'jdoe@example.com', 'password' => 'password']);
        $package = \App\Models\Package::create(['name' => 'default', 'is_active' => true]);
        $account = Account::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'username' => 'jdoe',
            'domain' => 'example.com',
            'status' => 'active',
        ]);

        $domain = Domain::create([
            'account_id' => $account->id,
            'name' => 'example.com',
            'type' => 'main',
            'document_root' => '/home/jdoe/public_html',
        ]);

        $zone = DnsZone::create([
            'domain_id' => $domain->id,
            'serial' => 2025121901,
            'primary_ns' => 'ns1.example.com',
            'admin_email' => 'hostmaster@example.com',
        ]);

        $this->assertDatabaseHas('dns_zones', ['id' => $zone->id, 'domain_id' => $domain->id]);

        $record = DnsRecord::create([
            'zone_id' => $zone->id,
            'name' => '@',
            'type' => 'A',
            'content' => '203.0.113.10',
            'ttl' => 3600,
        ]);

        $this->assertDatabaseHas('dns_records', ['id' => $record->id, 'type' => 'A']);
        $this->assertStringContainsString('203.0.113.10', $record->toZoneFormat());
    }
}
