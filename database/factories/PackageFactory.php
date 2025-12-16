<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Package>
 */
class PackageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Package::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'disk_quota' => 10 * 1024 * 1024 * 1024, // 10 GB
            'bandwidth_quota' => 100 * 1024 * 1024 * 1024, // 100 GB
            'max_domains' => 10,
            'max_subdomains' => 25,
            'max_email_accounts' => 100,
            'max_email_forwarders' => 50,
            'max_databases' => 10,
            'max_ftp_accounts' => 10,
            'max_parked_domains' => 5,
            'features' => ['ssl', 'cron', 'ssh'],
            'is_active' => true,
            'is_reseller_package' => false,
        ];
    }

    /**
     * Indicate that this is a reseller package.
     */
    public function reseller(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reseller_package' => true,
            'max_domains' => 100,
            'max_databases' => 100,
            'max_email_accounts' => 1000,
        ]);
    }

    /**
     * Indicate that this package is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a basic/starter package.
     */
    public function starter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Starter',
            'disk_quota' => 1 * 1024 * 1024 * 1024, // 1 GB
            'bandwidth_quota' => 10 * 1024 * 1024 * 1024, // 10 GB
            'max_domains' => 1,
            'max_subdomains' => 5,
            'max_email_accounts' => 10,
            'max_databases' => 1,
        ]);
    }

    /**
     * Create an unlimited package.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Unlimited',
            'disk_quota' => 0, // Unlimited
            'bandwidth_quota' => 0, // Unlimited
            'max_domains' => 0, // Unlimited
            'max_subdomains' => 0,
            'max_email_accounts' => 0,
            'max_databases' => 0,
        ]);
    }
}
