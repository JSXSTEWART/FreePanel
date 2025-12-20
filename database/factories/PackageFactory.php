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
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
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
            'name' => fake()->unique()->words(2, true) . ' Package',
            'description' => fake()->sentence(),
            'disk_quota' => fake()->randomElement([1024, 5120, 10240, 0]) * 1024 * 1024, // MB to bytes
            'bandwidth_quota' => fake()->randomElement([10, 50, 100, 0]) * 1024 * 1024 * 1024, // GB to bytes
            'max_domains' => fake()->randomElement([1, 5, 10, 0]),
            'max_subdomains' => fake()->randomElement([5, 10, 25, 0]),
            'max_email_accounts' => fake()->randomElement([10, 50, 100, 0]),
            'max_email_forwarders' => fake()->randomElement([10, 50, 100, 0]),
            'max_databases' => fake()->randomElement([1, 5, 10, 0]),
            'max_ftp_accounts' => fake()->randomElement([1, 5, 10, 0]),
            'max_parked_domains' => fake()->randomElement([0, 5, 10]),
            'features' => [],
            'is_active' => true,
            'is_reseller_package' => false,
        ];
    }

    /**
     * Indicate that the package is for resellers.
     */
    public function reseller(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reseller_package' => true,
            'name' => 'Reseller ' . fake()->words(2, true),
        ]);
    }

    /**
     * Indicate that the package is unlimited.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'disk_quota' => 0,
            'bandwidth_quota' => 0,
            'max_domains' => 0,
            'max_subdomains' => 0,
            'max_email_accounts' => 0,
            'max_email_forwarders' => 0,
            'max_databases' => 0,
            'max_ftp_accounts' => 0,
            'max_parked_domains' => 0,
        ]);
    }

    /**
     * Indicate that the package is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
