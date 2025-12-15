<?php

namespace App\Services\Ssl;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\File;

class LetsEncryptService
{
    protected string $certbotPath;
    protected string $webroot;
    protected string $email;

    public function __construct()
    {
        $this->certbotPath = config('freepanel.certbot_path', '/usr/bin/certbot');
        $this->email = config('freepanel.admin_email', 'admin@localhost');
    }

    /**
     * Request a new Let's Encrypt certificate
     */
    public function requestCertificate(array $domains, string $webroot): array
    {
        $primaryDomain = $domains[0];

        // Build domain arguments
        $domainArgs = implode(' ', array_map(fn($d) => "-d {$d}", $domains));

        // Run certbot
        $command = sprintf(
            '%s certonly --webroot -w %s %s --email %s --agree-tos --non-interactive --expand',
            $this->certbotPath,
            escapeshellarg($webroot),
            $domainArgs,
            escapeshellarg($this->email)
        );

        $result = Process::timeout(300)->run($command);

        if (!$result->successful()) {
            throw new \RuntimeException("Failed to obtain SSL certificate: " . $result->errorOutput());
        }

        // Read certificate files
        $certDir = "/etc/letsencrypt/live/{$primaryDomain}";

        if (!File::isDirectory($certDir)) {
            throw new \RuntimeException("Certificate directory not found: {$certDir}");
        }

        return [
            'certificate' => File::get("{$certDir}/cert.pem"),
            'private_key' => File::get("{$certDir}/privkey.pem"),
            'ca_bundle' => File::get("{$certDir}/chain.pem"),
            'fullchain' => File::get("{$certDir}/fullchain.pem"),
            'expires_at' => $this->getCertificateExpiry("{$certDir}/cert.pem"),
        ];
    }

    /**
     * Renew a Let's Encrypt certificate
     */
    public function renewCertificate(string $domain, string $webroot): array
    {
        $command = sprintf(
            '%s renew --cert-name %s --webroot -w %s --non-interactive',
            $this->certbotPath,
            escapeshellarg($domain),
            escapeshellarg($webroot)
        );

        $result = Process::timeout(300)->run($command);

        if (!$result->successful()) {
            throw new \RuntimeException("Failed to renew SSL certificate: " . $result->errorOutput());
        }

        $certDir = "/etc/letsencrypt/live/{$domain}";

        return [
            'certificate' => File::get("{$certDir}/cert.pem"),
            'private_key' => File::get("{$certDir}/privkey.pem"),
            'ca_bundle' => File::get("{$certDir}/chain.pem"),
            'fullchain' => File::get("{$certDir}/fullchain.pem"),
            'expires_at' => $this->getCertificateExpiry("{$certDir}/cert.pem"),
        ];
    }

    /**
     * Revoke a certificate
     */
    public function revokeCertificate(string $domain): void
    {
        $certPath = "/etc/letsencrypt/live/{$domain}/cert.pem";

        if (!File::exists($certPath)) {
            return;
        }

        $command = sprintf(
            '%s revoke --cert-path %s --non-interactive',
            $this->certbotPath,
            escapeshellarg($certPath)
        );

        Process::run($command);

        // Delete the certificate
        $command = sprintf(
            '%s delete --cert-name %s --non-interactive',
            $this->certbotPath,
            escapeshellarg($domain)
        );

        Process::run($command);
    }

    /**
     * Check if certificates need renewal
     */
    public function getCertificatesNeedingRenewal(int $daysThreshold = 30): array
    {
        $needsRenewal = [];

        $certDirs = glob('/etc/letsencrypt/live/*', GLOB_ONLYDIR);

        foreach ($certDirs as $certDir) {
            $domain = basename($certDir);
            $certPath = "{$certDir}/cert.pem";

            if (!File::exists($certPath)) {
                continue;
            }

            $expiry = $this->getCertificateExpiry($certPath);
            $daysUntilExpiry = now()->diffInDays($expiry, false);

            if ($daysUntilExpiry <= $daysThreshold) {
                $needsRenewal[] = [
                    'domain' => $domain,
                    'expires_at' => $expiry,
                    'days_until_expiry' => $daysUntilExpiry,
                ];
            }
        }

        return $needsRenewal;
    }

    /**
     * Get certificate expiry date
     */
    protected function getCertificateExpiry(string $certPath): \Carbon\Carbon
    {
        $cert = File::get($certPath);
        $certInfo = openssl_x509_parse($cert);

        if (!$certInfo || !isset($certInfo['validTo_time_t'])) {
            throw new \RuntimeException("Failed to parse certificate");
        }

        return \Carbon\Carbon::createFromTimestamp($certInfo['validTo_time_t']);
    }
}
