<?php

namespace App\Console\Commands;

use App\Models\SslCertificate;
use App\Services\Ssl\LetsEncryptService;
use App\Services\Ssl\SslInstaller;
use App\Services\WebServer\WebServerInterface;
use Illuminate\Console\Command;

class RenewSslCommand extends Command
{
    protected $signature = 'freepanel:renew-ssl
                            {--days=30 : Renew certificates expiring within this many days}
                            {--dry-run : Show what would be renewed without actually renewing}';

    protected $description = 'Renew SSL certificates that are about to expire';

    public function handle(
        LetsEncryptService $letsEncrypt,
        SslInstaller $sslInstaller,
        WebServerInterface $webServer
    ): int {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Checking for SSL certificates expiring within {$days} days...");

        $certificates = SslCertificate::where('type', 'lets_encrypt')
            ->where('auto_renew', true)
            ->where('expires_at', '<=', now()->addDays($days))
            ->with('domain.account')
            ->get();

        if ($certificates->isEmpty()) {
            $this->info('No certificates need renewal.');
            return 0;
        }

        $this->info("Found {$certificates->count()} certificate(s) to renew.");

        foreach ($certificates as $certificate) {
            $domain = $certificate->domain;
            $account = $domain->account;
            $daysUntilExpiry = now()->diffInDays($certificate->expires_at, false);

            $this->line("Processing: {$domain->name} (expires in {$daysUntilExpiry} days)");

            if ($dryRun) {
                $this->comment("  [DRY RUN] Would renew certificate for {$domain->name}");
                continue;
            }

            try {
                $result = $letsEncrypt->renewCertificate(
                    $domain->name,
                    "/home/{$account->username}/public_html/{$domain->name}"
                );

                $certificate->update([
                    'certificate' => $result['certificate'],
                    'private_key' => encrypt($result['private_key']),
                    'ca_bundle' => $result['ca_bundle'],
                    'issued_at' => now(),
                    'expires_at' => $result['expires_at'],
                ]);

                $sslInstaller->install($domain, $certificate);
                $webServer->enableSsl($domain, $certificate);

                $this->info("  ✓ Renewed successfully, expires: {$result['expires_at']}");
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to renew: {$e->getMessage()}");
            }
        }

        return 0;
    }
}
