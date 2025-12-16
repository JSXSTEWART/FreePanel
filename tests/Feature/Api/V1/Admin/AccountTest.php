<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Account;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $reseller;
    protected User $user;
    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->reseller = User::factory()->reseller()->create();
        $this->user = User::factory()->create();
        $this->package = Package::factory()->create();
    }

    public function test_admin_can_list_all_accounts(): void
    {
        Account::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/accounts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'uuid',
                        'username',
                        'domain',
                        'status',
                    ],
                ],
            ]);
    }

    public function test_reseller_can_list_accounts(): void
    {
        $response = $this->actingAs($this->reseller)
            ->getJson('/api/v1/admin/accounts');

        $response->assertOk();
    }

    public function test_regular_user_cannot_access_admin_accounts(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/admin/accounts');

        $response->assertForbidden();
    }

    public function test_admin_can_create_account(): void
    {
        $accountData = [
            'username' => 'newaccount',
            'domain' => 'newdomain.com',
            'email' => 'admin@newdomain.com',
            'password' => 'SecureP@ssw0rd!',
            'package_id' => $this->package->id,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/accounts', $accountData);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'uuid',
                    'username',
                    'domain',
                ],
            ]);

        $this->assertDatabaseHas('accounts', [
            'username' => 'newaccount',
            'domain' => 'newdomain.com',
        ]);
    }

    public function test_admin_can_view_single_account(): void
    {
        $account = Account::factory()->create();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/accounts/{$account->id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $account->id,
                    'username' => $account->username,
                ],
            ]);
    }

    public function test_admin_can_update_account(): void
    {
        $account = Account::factory()->create();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/accounts/{$account->id}", [
                'domain' => 'updated-domain.com',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'domain' => 'updated-domain.com',
        ]);
    }

    public function test_admin_can_delete_account(): void
    {
        $account = Account::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/accounts/{$account->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('accounts', [
            'id' => $account->id,
        ]);
    }

    public function test_admin_can_suspend_account(): void
    {
        $account = Account::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/accounts/{$account->id}/suspend", [
                'reason' => 'Violation of terms',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'suspended',
            'suspend_reason' => 'Violation of terms',
        ]);
    }

    public function test_admin_can_unsuspend_account(): void
    {
        $account = Account::factory()->suspended()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/accounts/{$account->id}/unsuspend");

        $response->assertOk();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_change_account_package(): void
    {
        $account = Account::factory()->create(['package_id' => $this->package->id]);
        $newPackage = Package::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/accounts/{$account->id}/change-package", [
                'package_id' => $newPackage->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'package_id' => $newPackage->id,
        ]);
    }

    public function test_create_account_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/accounts', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'domain', 'package_id']);
    }

    public function test_create_account_validates_unique_username(): void
    {
        Account::factory()->create(['username' => 'existing']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/accounts', [
                'username' => 'existing',
                'domain' => 'newdomain.com',
                'email' => 'admin@newdomain.com',
                'password' => 'SecureP@ssw0rd!',
                'package_id' => $this->package->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }
}
