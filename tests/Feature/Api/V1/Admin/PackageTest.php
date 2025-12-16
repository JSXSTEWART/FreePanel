<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $reseller;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->reseller = User::factory()->reseller()->create();
        $this->user = User::factory()->create();
    }

    public function test_admin_can_list_all_packages(): void
    {
        Package::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/packages');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'disk_quota',
                        'bandwidth_quota',
                        'max_domains',
                    ],
                ],
            ]);
    }

    public function test_reseller_cannot_manage_packages(): void
    {
        $response = $this->actingAs($this->reseller)
            ->getJson('/api/v1/admin/packages');

        $response->assertForbidden();
    }

    public function test_regular_user_cannot_access_packages(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/admin/packages');

        $response->assertForbidden();
    }

    public function test_admin_can_create_package(): void
    {
        $packageData = [
            'name' => 'Pro Plan',
            'description' => 'Professional hosting package',
            'disk_quota' => 50 * 1024 * 1024 * 1024, // 50 GB
            'bandwidth_quota' => 500 * 1024 * 1024 * 1024, // 500 GB
            'max_domains' => 25,
            'max_subdomains' => 100,
            'max_email_accounts' => 500,
            'max_databases' => 25,
            'max_ftp_accounts' => 25,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/packages', $packageData);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('packages', [
            'name' => 'Pro Plan',
        ]);
    }

    public function test_admin_can_view_single_package(): void
    {
        $package = Package::factory()->create();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/packages/{$package->id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $package->id,
                    'name' => $package->name,
                ],
            ]);
    }

    public function test_admin_can_update_package(): void
    {
        $package = Package::factory()->create();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/packages/{$package->id}", [
                'name' => 'Updated Package Name',
                'max_domains' => 50,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'name' => 'Updated Package Name',
            'max_domains' => 50,
        ]);
    }

    public function test_admin_can_delete_package(): void
    {
        $package = Package::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/packages/{$package->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('packages', [
            'id' => $package->id,
        ]);
    }

    public function test_create_package_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/packages', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_package_validates_unique_name(): void
    {
        Package::factory()->create(['name' => 'Existing Plan']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/packages', [
                'name' => 'Existing Plan',
                'disk_quota' => 10 * 1024 * 1024 * 1024,
                'bandwidth_quota' => 100 * 1024 * 1024 * 1024,
                'max_domains' => 10,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_package_quotas_can_be_unlimited(): void
    {
        $packageData = [
            'name' => 'Unlimited Plan',
            'disk_quota' => 0, // Unlimited
            'bandwidth_quota' => 0, // Unlimited
            'max_domains' => 0, // Unlimited
            'max_subdomains' => 0,
            'max_email_accounts' => 0,
            'max_databases' => 0,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/packages', $packageData);

        $response->assertCreated();

        $package = Package::where('name', 'Unlimited Plan')->first();
        $this->assertTrue($package->isUnlimited('disk_quota'));
        $this->assertTrue($package->isUnlimited('max_domains'));
    }
}
