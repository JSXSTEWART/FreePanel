<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Account;
use App\Models\FtpAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FtpManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_ftp_account(): void
    {
        $user = \App\Models\User::create(['username' => 'jdoe', 'email' => 'jdoe@example.com', 'password' => 'password']);
        $package = \App\Models\Package::create(['name' => 'default', 'is_active' => true]);
        $account = Account::create(['user_id' => $user->id, 'package_id' => $package->id, 'username' => 'jdoe', 'domain' => 'example.com', 'status' => 'active']);

        $ftp = new FtpAccount();
        $ftp->account_id = $account->id;
        $ftp->username = 'ftp_jdoe';
        $ftp->password_hash = bcrypt('secret');
        $ftp->home_directory = '/home/jdoe';
        $ftp->save();

        $this->assertDatabaseHas('ftp_accounts', ['id' => $ftp->id, 'username' => 'ftp_jdoe']);
    }
}
