<?php

namespace App\Listeners\Domain;

use App\Services\Zapier\ZapierWebhookService;

class SendSslExpiringToZapier
{
    public function __construct(
        private ZapierWebhookService $zapierService
    ) {}

    /**
     * Handle SSL certificate expiration events
     * This listener should be called by a scheduled command that checks certificate expiry
     */
    public function handleExpiring($domain): void
    {
        if (!config('zapier.enabled')) {
            return;
        }

        $sslCert = $domain->sslCertificate;

        if (!$sslCert) {
            return;
        }

        $data = [
            'domain_id' => $domain->id,
            'domain_name' => $domain->domain,
            'certificate_id' => $sslCert->id,
            'issued_at' => $sslCert->issued_at?->toIso8601String(),
            'expires_at' => $sslCert->expires_at?->toIso8601String(),
            'days_until_expiry' => $sslCert->expires_at?->diffInDays(now()),
        ];

        $this->zapierService->send('ssl.expiring', $data);
    }
}
