<?php

namespace App\Services\Ssl;

use App\Models\Domain;
use App\Models\SslCertificate;
use Illuminate\Support\Facades\File;

class SslInstaller
{
    protected string $sslBaseDir;

    public function __construct()
    {
        $this->sslBaseDir = config('freepanel.ssl_dir', '/etc/ssl/freepanel');
    }

    /**
     * Install SSL certificate files
     */
    public function install(Domain $domain, SslCertificate $certificate): void
    {
        $certDir = "{$this->sslBaseDir}/{$domain->name}";

        // Create directory if it doesn't exist
        if (!File::isDirectory($certDir)) {
            File::makeDirectory($certDir, 0700, true);
        }

        // Write certificate
        $certPath = "{$certDir}/cert.pem";
        File::put($certPath, $certificate->certificate);
        chmod($certPath, 0644);

        // Write private key (decrypted)
        $keyPath = "{$certDir}/key.pem";
        File::put($keyPath, decrypt($certificate->private_key));
        chmod($keyPath, 0600);

        // Write CA bundle if present
        if ($certificate->ca_bundle) {
            $chainPath = "{$certDir}/chain.pem";
            File::put($chainPath, $certificate->ca_bundle);
            chmod($chainPath, 0644);

            // Create fullchain (cert + chain)
            $fullchainPath = "{$certDir}/fullchain.pem";
            File::put($fullchainPath, $certificate->certificate . "\n" . $certificate->ca_bundle);
            chmod($fullchainPath, 0644);
        }
    }

    /**
     * Uninstall SSL certificate files
     */
    public function uninstall(Domain $domain): void
    {
        $certDir = "{$this->sslBaseDir}/{$domain->name}";

        if (File::isDirectory($certDir)) {
            File::deleteDirectory($certDir);
        }
    }

    /**
     * Get certificate paths
     */
    public function getPaths(Domain $domain): array
    {
        $certDir = "{$this->sslBaseDir}/{$domain->name}";

        return [
            'certificate' => "{$certDir}/cert.pem",
            'private_key' => "{$certDir}/key.pem",
            'ca_bundle' => "{$certDir}/chain.pem",
            'fullchain' => "{$certDir}/fullchain.pem",
        ];
    }

    /**
     * Verify certificate and key match
     */
    public function verify(string $certificate, string $privateKey): bool
    {
        $certResource = openssl_x509_read($certificate);
        $keyResource = openssl_pkey_get_private($privateKey);

        if (!$certResource || !$keyResource) {
            return false;
        }

        return openssl_x509_check_private_key($certResource, $keyResource);
    }

    /**
     * Get certificate details
     */
    public function getCertificateInfo(string $certificate): array
    {
        $certInfo = openssl_x509_parse($certificate);

        if (!$certInfo) {
            throw new \RuntimeException("Failed to parse certificate");
        }

        return [
            'subject' => $certInfo['subject']['CN'] ?? 'Unknown',
            'issuer' => $certInfo['issuer']['CN'] ?? 'Unknown',
            'valid_from' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t']),
            'valid_to' => date('Y-m-d H:i:s', $certInfo['validTo_time_t']),
            'serial' => $certInfo['serialNumberHex'] ?? null,
            'signature_algorithm' => $certInfo['signatureTypeSN'] ?? null,
            'san' => $certInfo['extensions']['subjectAltName'] ?? null,
        ];
    }
}
