<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Account;
use App\Models\Domain;
use App\Models\SslCertificate;
use App\Services\Ssl\LetsEncryptService;
use App\Services\Ssl\SslInstaller;
use App\Services\WebServer\WebServerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SslRenewalTest extends TestCase
{
    use RefreshDatabase;

    public function test_renew_ssl_command_uses_lets_encrypt_and_installs(): void
    {
        $user = \App\Models\User::create(['username' => 'jdoe', 'email' => 'jdoe@example.com', 'password' => 'password']);
        $package = \App\Models\Package::create(['name' => 'default', 'is_active' => true]);
        $account = Account::create(['user_id' => $user->id, 'package_id' => $package->id, 'username' => 'jdoe', 'domain' => 'example.com', 'status' => 'active']);

        $domain = Domain::create(['account_id' => $account->id, 'name' => 'example.com', 'type' => 'main', 'document_root' => '/home/jdoe/public_html']);

        $cert = SslCertificate::create([
            'domain_id' => $domain->id,
            'type' => 'lets_encrypt',
            'auto_renew' => true,
            'expires_at' => now()->addDays(5),
            'certificate' => 'OLDCERT',
            'private_key' => 'OLDKEY',
        ]);

        $this->mock(LetsEncryptService::class, function ($mock) use ($domain) {
            $mock->shouldReceive('renewCertificate')
                ->once()
                ->with($domain->name, \Mockery::type('string'))
                ->andReturn([
                    'certificate' => '---CERT---',
                    'private_key' => '---KEY---',
                    'ca_bundle' => '---CA---',
                    'expires_at' => now()->addDays(90)->toDateTimeString(),
                ]);
        });

        $this->mock(SslInstaller::class, function ($mock) use ($domain) {
            $mock->shouldReceive('install')->once();
        });

        $this->mock(WebServerInterface::class, function ($mock) use ($domain) {
            $mock->shouldReceive('enableSsl')->once();
        });

        $this->artisan('freepanel:renew-ssl', ['--days' => 30])->assertExitCode(0);

        $cert->refresh();

        $this->assertNotNull($cert->certificate);
        $this->assertNotNull($cert->private_key);
    }
}
