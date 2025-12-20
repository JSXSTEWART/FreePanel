<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Zapier\WebhookSignatureService;

class GenerateWebhookSecret extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zapier:generate-secret {--show : Display the secret without saving to .env}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new Zapier webhook secret for HMAC signature verification';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Generate the secret
        $secret = WebhookSignatureService::generateSecret();

        if ($this->option('show')) {
            $this->line($secret);
            return 0;
        }

        // Attempt to update .env file
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->error('.env file not found');
            return 1;
        }

        $envContent = file_get_contents($envPath);

        // Check if ZAPIER_WEBHOOK_SECRET already exists
        if (str_contains($envContent, 'ZAPIER_WEBHOOK_SECRET=')) {
            $this->warn('ZAPIER_WEBHOOK_SECRET already exists in .env file');
            $this->info('To overwrite, manually update or use --show option and update manually');
            return 0;
        }

        // Append the new secret to .env
        file_put_contents($envPath, "\nZAPIER_WEBHOOK_SECRET={$secret}\n", FILE_APPEND);

        $this->info('Webhook secret generated and saved to .env');
        $this->info("Secret: {$secret}");
        $this->line('');
        $this->info('⚠️  Keep this secret safe! Do not commit it to version control.');
        $this->info('Share this secret with Zapier only.');

        return 0;
    }
}
