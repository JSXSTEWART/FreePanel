<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Account;
use App\Models\Database;
use App\Models\DatabaseUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DatabaseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_database_and_user(): void
    {
        $user = \App\Models\User::create(['username' => 'jdoe', 'email' => 'jdoe@example.com', 'password' => 'password']);
        $package = \App\Models\Package::create(['name' => 'default', 'is_active' => true]);
        $account = Account::create(['user_id' => $user->id, 'package_id' => $package->id, 'username' => 'jdoe', 'domain' => 'example.com', 'status' => 'active']);

        $db = Database::create([
            'account_id' => $account->id,
            'name' => 'jdoe_db',
        ]);

        $this->assertDatabaseHas('databases', ['id' => $db->id, 'name' => 'jdoe_db']);

        $dbUser = DatabaseUser::create([
            'account_id' => $account->id,
            'username' => 'jdoe_db_user',
            'host' => 'localhost',
            'password_hash' => bcrypt('secret'),
        ]);

        $this->assertDatabaseHas('database_users', ['id' => $dbUser->id, 'username' => 'jdoe_db_user']);
    }
}
