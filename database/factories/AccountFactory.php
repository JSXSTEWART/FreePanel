<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = fake()->unique()->userName();

        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'package_id' => Package::factory(),
            'username' => $username,
            'domain' => fake()->unique()->domainName(),
            'home_directory' => '/home/' . $username,
            'shell' => '/bin/bash',
            'uid' => fake()->numberBetween(1000, 65534),
            'gid' => fake()->numberBetween(1000, 65534),
            'disk_used' => fake()->numberBetween(0, 1024 * 1024 * 1024),
            'bandwidth_used' => fake()->numberBetween(0, 1024 * 1024 * 1024),
            'status' => 'active',
            'ip_address' => fake()->ipv4(),
        ];
    }

    /**
     * Indicate that the account is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
            'suspend_reason' => fake()->sentence(),
            'suspended_at' => now(),
        ]);
    }

    /**
     * Indicate that the account is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Set specific user for the account.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set specific package for the account.
     */
    public function withPackage(Package $package): static
    {
        return $this->state(fn (array $attributes) => [
            'package_id' => $package->id,
        ]);
    }
}
