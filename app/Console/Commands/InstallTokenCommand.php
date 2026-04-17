<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallTokenCommand extends Command
{
    protected $signature = 'freepanel:install-token {--force : Overwrite an existing token}';

    protected $description = 'Generate a one-time install token that gates initial setup';

    public function handle(): int
    {
        $tokenPath = storage_path('app/install.token');
        $lockPath = storage_path('app/setup.lock');

        if (file_exists($lockPath)) {
            $this->error('Setup has already completed on this host.');
            $this->line('If you truly want to re-run setup, remove '.$lockPath.' and re-run this command.');

            return self::FAILURE;
        }

        if (file_exists($tokenPath) && ! $this->option('force')) {
            $this->error('Install token already exists at '.$tokenPath);
            $this->line('Pass --force to overwrite.');

            return self::FAILURE;
        }

        $token = Str::random(48);

        $dir = dirname($tokenPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($tokenPath, $token, LOCK_EX) === false) {
            $this->error('Failed to write token file.');

            return self::FAILURE;
        }

        @chmod($tokenPath, 0600);

        $this->info('Install token generated.');
        $this->line('Path:  '.$tokenPath);
        $this->line('Token: '.$token);
        $this->newLine();
        $this->line('Submit this token with your initial POST to /api/v1/setup/initialize');
        $this->line('as the `install_token` field. The token is deleted after successful setup.');

        return self::SUCCESS;
    }
}
