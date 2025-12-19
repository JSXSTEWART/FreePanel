<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Account;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\EmailForwarder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_email_account_and_forwarder(): void
    {
        $user = \App\Models\User::create(['username' => 'jdoe', 'email' => 'jdoe@example.com', 'password' => 'password']);
        $package = \App\Models\Package::create(['name' => 'default', 'is_active' => true]);
        $account = Account::create(['user_id' => $user->id, 'package_id' => $package->id, 'username' => 'jdoe', 'domain' => 'example.com', 'status' => 'active']);

        $domain = Domain::create(['account_id' => $account->id, 'name' => 'example.com', 'type' => 'main', 'document_root' => '/home/jdoe/public_html']);

        $email = EmailAccount::create([
            'domain_id' => $domain->id,
            'local_part' => 'info',
            'email' => 'info@example.com',
            'password_hash' => bcrypt('secret'),
            'maildir_path' => "/var/mail/vhosts/{$domain->name}/info",
        ]);

        $this->assertDatabaseHas('email_accounts', ['id' => $email->id, 'local_part' => 'info']);

        $forwarder = EmailForwarder::create([
            'domain_id' => $domain->id,
            'source' => 'sales@example.com',
            'destination' => 'owner@example.com',
        ]);

        $this->assertDatabaseHas('email_forwarders', ['id' => $forwarder->id, 'source' => 'sales@example.com']);
    }
}
