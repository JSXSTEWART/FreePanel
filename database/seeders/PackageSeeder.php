<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Seed default hosting packages.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Starter',
                'disk_quota' => 1024, // 1 GB
                'bandwidth' => 10240, // 10 GB
                'max_addon_domains' => 0,
                'max_subdomains' => 3,
                'max_email_accounts' => 5,
                'max_databases' => 1,
                'max_ftp_accounts' => 1,
                'max_parked_domains' => 0,
                'is_default' => true,
                'features' => [
                    'ssl' => true,
                    'cron' => false,
                    'shell_access' => false,
                    'backup' => true,
                    'softaculous' => false,
                ],
            ],
            [
                'name' => 'Basic',
                'disk_quota' => 5120, // 5 GB
                'bandwidth' => 51200, // 50 GB
                'max_addon_domains' => 1,
                'max_subdomains' => 10,
                'max_email_accounts' => 25,
                'max_databases' => 5,
                'max_ftp_accounts' => 5,
                'max_parked_domains' => 2,
                'is_default' => false,
                'features' => [
                    'ssl' => true,
                    'cron' => true,
                    'shell_access' => false,
                    'backup' => true,
                    'softaculous' => true,
                ],
            ],
            [
                'name' => 'Professional',
                'disk_quota' => 20480, // 20 GB
                'bandwidth' => 204800, // 200 GB
                'max_addon_domains' => 5,
                'max_subdomains' => 50,
                'max_email_accounts' => 100,
                'max_databases' => 20,
                'max_ftp_accounts' => 20,
                'max_parked_domains' => 10,
                'is_default' => false,
                'features' => [
                    'ssl' => true,
                    'cron' => true,
                    'shell_access' => true,
                    'backup' => true,
                    'softaculous' => true,
                    'dedicated_ip' => false,
                ],
            ],
            [
                'name' => 'Business',
                'disk_quota' => 51200, // 50 GB
                'bandwidth' => 512000, // 500 GB
                'max_addon_domains' => 20,
                'max_subdomains' => -1, // Unlimited
                'max_email_accounts' => -1,
                'max_databases' => 50,
                'max_ftp_accounts' => 50,
                'max_parked_domains' => -1,
                'is_default' => false,
                'features' => [
                    'ssl' => true,
                    'cron' => true,
                    'shell_access' => true,
                    'backup' => true,
                    'softaculous' => true,
                    'dedicated_ip' => true,
                    'priority_support' => true,
                ],
            ],
            [
                'name' => 'Enterprise',
                'disk_quota' => -1, // Unlimited
                'bandwidth' => -1, // Unlimited
                'max_addon_domains' => -1,
                'max_subdomains' => -1,
                'max_email_accounts' => -1,
                'max_databases' => -1,
                'max_ftp_accounts' => -1,
                'max_parked_domains' => -1,
                'is_default' => false,
                'features' => [
                    'ssl' => true,
                    'cron' => true,
                    'shell_access' => true,
                    'backup' => true,
                    'softaculous' => true,
                    'dedicated_ip' => true,
                    'priority_support' => true,
                    'custom_php' => true,
                ],
            ],
        ];

        foreach ($packages as $package) {
            Package::updateOrCreate(
                ['name' => $package['name']],
                $package
            );
        }

        $this->command->info('Created ' . count($packages) . ' hosting packages.');
    }
}
