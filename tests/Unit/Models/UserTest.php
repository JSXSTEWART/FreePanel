<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_uuid_generated_on_creation(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $user->uuid
        );
    }

    public function test_user_password_is_hashed(): void
    {
        $user = User::factory()->create([
            'password' => 'plaintext_password',
        ]);

        $this->assertNotEquals('plaintext_password', $user->password);
        $this->assertTrue(password_verify('plaintext_password', $user->password));
    }

    public function test_user_password_is_hidden_in_array(): void
    {
        $user = User::factory()->create();
        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertArrayNotHasKey('two_factor_secret', $array);
    }

    public function test_is_admin_returns_true_for_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isReseller());
    }

    public function test_is_reseller_returns_true_for_reseller_role(): void
    {
        $reseller = User::factory()->reseller()->create();

        $this->assertTrue($reseller->isReseller());
        $this->assertFalse($reseller->isAdmin());
    }

    public function test_regular_user_is_neither_admin_nor_reseller(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isReseller());
    }

    public function test_user_can_have_parent_relationship(): void
    {
        $reseller = User::factory()->reseller()->create();
        $user = User::factory()->create(['parent_id' => $reseller->id]);

        $this->assertEquals($reseller->id, $user->parent->id);
    }

    public function test_reseller_can_have_child_users(): void
    {
        $reseller = User::factory()->reseller()->create();
        $users = User::factory()->count(3)->create(['parent_id' => $reseller->id]);

        $this->assertCount(3, $reseller->children);
    }

    public function test_two_factor_enabled_is_cast_to_boolean(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => 1]);

        $this->assertIsBool($user->two_factor_enabled);
        $this->assertTrue($user->two_factor_enabled);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $user = User::factory()->create(['is_active' => 1]);

        $this->assertIsBool($user->is_active);
        $this->assertTrue($user->is_active);
    }

    public function test_last_login_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create([
            'last_login_at' => '2024-01-15 10:30:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->last_login_at);
    }

    public function test_user_factory_creates_active_users_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->is_active);
    }

    public function test_user_factory_inactive_state(): void
    {
        $user = User::factory()->inactive()->create();

        $this->assertFalse($user->is_active);
    }

    public function test_user_factory_with_two_factor_state(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->assertTrue($user->two_factor_enabled);
        $this->assertNotNull($user->two_factor_secret);
    }
}
