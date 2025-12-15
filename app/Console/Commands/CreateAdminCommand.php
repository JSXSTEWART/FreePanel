<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminCommand extends Command
{
    protected $signature = 'freepanel:create-admin
                            {--email= : Admin email address}
                            {--password= : Admin password}
                            {--username= : Admin username}';

    protected $description = 'Create the initial admin user for FreePanel';

    public function handle(): int
    {
        $this->info('Creating FreePanel admin user...');

        // Check if admin already exists
        if (User::where('role', 'admin')->exists()) {
            if (!$this->confirm('An admin user already exists. Create another?')) {
                return 0;
            }
        }

        // Get credentials
        $email = $this->option('email') ?? $this->ask('Enter admin email', 'admin@localhost');
        $username = $this->option('username') ?? $this->ask('Enter admin username', 'admin');
        $password = $this->option('password') ?? $this->secret('Enter admin password');

        if (!$password) {
            $password = Str::random(16);
            $this->warn("Generated password: {$password}");
        }

        // Validate
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address');
            return 1;
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters');
            return 1;
        }

        // Create user
        $user = User::create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->info('Admin user created successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['Username', $username],
                ['Email', $email],
                ['Role', 'admin'],
            ]
        );

        return 0;
    }
}
