<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeatureSeeder extends Seeder
{
    /**
     * Seed available features.
     */
    public function run(): void
    {
        $features = [
            [
                'name' => 'ssl',
                'display_name' => 'SSL Certificates',
                'description' => 'Issue and manage SSL certificates including Let\'s Encrypt',
                'category' => 'security',
            ],
            [
                'name' => 'cron',
                'display_name' => 'Cron Jobs',
                'description' => 'Schedule automated tasks',
                'category' => 'tools',
            ],
            [
                'name' => 'shell_access',
                'display_name' => 'SSH Access',
                'description' => 'Command line access to the server',
                'category' => 'access',
            ],
            [
                'name' => 'backup',
                'display_name' => 'Backups',
                'description' => 'Create and restore account backups',
                'category' => 'tools',
            ],
            [
                'name' => 'softaculous',
                'display_name' => 'App Installer',
                'description' => 'One-click application installer',
                'category' => 'tools',
            ],
            [
                'name' => 'dedicated_ip',
                'display_name' => 'Dedicated IP',
                'description' => 'Dedicated IP address for the account',
                'category' => 'network',
            ],
            [
                'name' => 'priority_support',
                'display_name' => 'Priority Support',
                'description' => 'Priority customer support',
                'category' => 'support',
            ],
            [
                'name' => 'custom_php',
                'display_name' => 'Custom PHP',
                'description' => 'Custom PHP version and configuration',
                'category' => 'development',
            ],
            [
                'name' => 'nodejs',
                'display_name' => 'Node.js',
                'description' => 'Node.js application hosting',
                'category' => 'development',
            ],
            [
                'name' => 'python',
                'display_name' => 'Python',
                'description' => 'Python application hosting',
                'category' => 'development',
            ],
            [
                'name' => 'git',
                'display_name' => 'Git Repositories',
                'description' => 'Git version control access',
                'category' => 'development',
            ],
            [
                'name' => 'staging',
                'display_name' => 'Staging Environment',
                'description' => 'Create staging copies of websites',
                'category' => 'development',
            ],
        ];

        foreach ($features as $feature) {
            DB::table('features')->updateOrInsert(
                ['name' => $feature['name']],
                array_merge($feature, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Created ' . count($features) . ' features.');
    }
}
