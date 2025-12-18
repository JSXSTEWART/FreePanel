<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetTestingEnvironmentFile();
    }

    public function test_initialize_persists_server_settings_for_future_requests(): void
    {
        $payload = [
            'admin_username' => 'adminuser',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
            'server_hostname' => 'panel.example.com',
            'server_ip' => '203.0.113.10',
            'nameservers' => ['ns1.example.com', 'ns2.example.com'],
        ];

        $response = $this->postJson('/api/v1/setup/initialize', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->refreshApplication();

        $this->assertSame('panel.example.com', config('freepanel.hostname'));
        $this->assertSame('203.0.113.10', config('freepanel.server_ip'));
        $this->assertSame([
            'ns1' => 'ns1.example.com',
            'ns2' => 'ns2.example.com',
        ], config('freepanel.nameservers'));

        $this->assertSame('panel.example.com', config('app.hostname'));
        $this->assertSame('203.0.113.10', config('app.server_ip'));
        $this->assertSame([
            'ns1' => 'ns1.example.com',
            'ns2' => 'ns2.example.com',
        ], config('app.nameservers'));

        $envContent = file_get_contents(dirname(__DIR__, 2) . '/.env.testing');
        $this->assertStringContainsString('APP_HOSTNAME=panel.example.com', $envContent);
        $this->assertStringContainsString('FREEPANEL_SERVER_IP=203.0.113.10', $envContent);
        $this->assertStringContainsString('FREEPANEL_NAMESERVERS={"ns1":"ns1.example.com","ns2":"ns2.example.com"}', $envContent);
    }

    public function test_nameservers_are_trimmed_and_empty_entries_removed(): void
    {
        $payload = [
            'admin_username' => 'anotheradmin',
            'admin_email' => 'another@example.com',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
            'nameservers' => [' ns1.example.com ', null, '', 'ns2.example.com', '   '],
        ];

        $response = $this->postJson('/api/v1/setup/initialize', $payload);

        $response->assertStatus(200);

        $this->refreshApplication();

        $this->assertSame([
            'ns1' => 'ns1.example.com',
            'ns2' => 'ns2.example.com',
        ], config('freepanel.nameservers'));

        $envContent = file_get_contents(dirname(__DIR__, 2) . '/.env.testing');
        $this->assertStringContainsString('FREEPANEL_NAMESERVERS={"ns1":"ns1.example.com","ns2":"ns2.example.com"}', $envContent);
        $this->assertStringNotContainsString('APP_NAMESERVERS', $envContent);
    }

    public function test_server_identity_values_are_trimmed_before_persisting(): void
    {
        $payload = [
            'admin_username' => 'trimadmin',
            'admin_email' => 'trim@example.com',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
            'server_hostname' => '  trimmed.example.com  ',
            'server_ip' => ' 198.51.100.7 ',
            'nameservers' => ['  ns1.trimmed.com', 'ns2.trimmed.com  '],
        ];

        $response = $this->postJson('/api/v1/setup/initialize', $payload);

        $response->assertStatus(200);

        $this->refreshApplication();

        $this->assertSame('trimmed.example.com', config('app.hostname'));
        $this->assertSame('trimmed.example.com', config('freepanel.hostname'));
        $this->assertSame('198.51.100.7', config('app.server_ip'));
        $this->assertSame('198.51.100.7', config('freepanel.server_ip'));
        $this->assertSame([
            'ns1' => 'ns1.trimmed.com',
            'ns2' => 'ns2.trimmed.com',
        ], config('freepanel.nameservers'));

        $envContent = file_get_contents(dirname(__DIR__, 2) . '/.env.testing');
        $this->assertStringContainsString('APP_HOSTNAME=trimmed.example.com', $envContent);
        $this->assertStringContainsString('FREEPANEL_SERVER_IP=198.51.100.7', $envContent);
        $this->assertStringContainsString('FREEPANEL_NAMESERVERS={"ns1":"ns1.trimmed.com","ns2":"ns2.trimmed.com"}', $envContent);
    }
}
