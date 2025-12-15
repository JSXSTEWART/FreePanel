<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\SslCertificate;
use App\Services\Ssl\LetsEncryptService;
use App\Services\Ssl\SslInstaller;
use App\Services\WebServer\WebServerInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SslController extends Controller
{
    protected LetsEncryptService $letsEncrypt;
    protected SslInstaller $sslInstaller;
    protected WebServerInterface $webServer;

    public function __construct(
        LetsEncryptService $letsEncrypt,
        SslInstaller $sslInstaller,
        WebServerInterface $webServer
    ) {
        $this->letsEncrypt = $letsEncrypt;
        $this->sslInstaller = $sslInstaller;
        $this->webServer = $webServer;
    }

    public function index(Request $request)
    {
        $account = $request->user()->account;

        $certificates = SslCertificate::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->with('domain:id,name')
            ->get()
            ->map(function ($cert) {
                $cert->days_until_expiry = now()->diffInDays($cert->expires_at, false);
                $cert->is_expiring_soon = $cert->days_until_expiry <= 30;
                return $cert;
            });

        return $this->success($certificates);
    }

    public function show(Request $request, int $id)
    {
        $account = $request->user()->account;

        $certificate = SslCertificate::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->with('domain:id,name')
            ->findOrFail($id);

        // Parse certificate for additional details
        $certInfo = openssl_x509_parse($certificate->certificate);
        if ($certInfo) {
            $certificate->issuer = $certInfo['issuer']['CN'] ?? 'Unknown';
            $certificate->subject = $certInfo['subject']['CN'] ?? 'Unknown';
            $certificate->serial = $certInfo['serialNumberHex'] ?? null;
            $certificate->signature_algorithm = $certInfo['signatureTypeSN'] ?? null;
        }

        return $this->success($certificate);
    }

    public function letsEncrypt(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|integer|exists:domains,id',
            'include_www' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $domain = Domain::where('account_id', $account->id)->findOrFail($request->domain_id);

        // Check if domain already has valid certificate
        $existing = SslCertificate::where('domain_id', $domain->id)
            ->where('expires_at', '>', now()->addDays(30))
            ->first();

        if ($existing) {
            return $this->error('Domain already has a valid SSL certificate', 422);
        }

        DB::beginTransaction();
        try {
            // Request certificate from Let's Encrypt
            $domains = [$domain->name];
            if ($request->include_www) {
                $domains[] = 'www.' . $domain->name;
            }

            $result = $this->letsEncrypt->requestCertificate(
                $domains,
                "/home/{$account->username}/public_html/{$domain->name}"
            );

            // Store certificate
            $certificate = SslCertificate::updateOrCreate(
                ['domain_id' => $domain->id],
                [
                    'type' => 'lets_encrypt',
                    'certificate' => $result['certificate'],
                    'private_key' => encrypt($result['private_key']),
                    'ca_bundle' => $result['ca_bundle'],
                    'issued_at' => now(),
                    'expires_at' => $result['expires_at'],
                    'auto_renew' => true,
                ]
            );

            // Install certificate on web server
            $this->sslInstaller->install($domain, $certificate);
            $this->webServer->enableSsl($domain, $certificate);

            DB::commit();
            return $this->success($certificate, 'SSL certificate issued successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to issue SSL certificate: ' . $e->getMessage(), 500);
        }
    }

    public function install(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|integer|exists:domains,id',
            'certificate' => 'required|string',
            'private_key' => 'required|string',
            'ca_bundle' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $domain = Domain::where('account_id', $account->id)->findOrFail($request->domain_id);

        // Validate certificate and key match
        $certResource = openssl_x509_read($request->certificate);
        $keyResource = openssl_pkey_get_private($request->private_key);

        if (!$certResource || !$keyResource) {
            return $this->error('Invalid certificate or private key format', 422);
        }

        if (!openssl_x509_check_private_key($certResource, $keyResource)) {
            return $this->error('Certificate and private key do not match', 422);
        }

        // Parse certificate for dates
        $certInfo = openssl_x509_parse($request->certificate);
        if (!$certInfo) {
            return $this->error('Failed to parse certificate', 422);
        }

        DB::beginTransaction();
        try {
            $certificate = SslCertificate::updateOrCreate(
                ['domain_id' => $domain->id],
                [
                    'type' => 'custom',
                    'certificate' => $request->certificate,
                    'private_key' => encrypt($request->private_key),
                    'ca_bundle' => $request->ca_bundle,
                    'issued_at' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t']),
                    'expires_at' => date('Y-m-d H:i:s', $certInfo['validTo_time_t']),
                    'auto_renew' => false,
                ]
            );

            // Install certificate
            $this->sslInstaller->install($domain, $certificate);
            $this->webServer->enableSsl($domain, $certificate);

            DB::commit();
            return $this->success($certificate, 'SSL certificate installed successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to install SSL certificate: ' . $e->getMessage(), 500);
        }
    }

    public function generateCsr(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|integer|exists:domains,id',
            'country' => 'required|string|size:2',
            'state' => 'required|string|max:64',
            'city' => 'required|string|max:64',
            'organization' => 'required|string|max:64',
            'organizational_unit' => 'nullable|string|max:64',
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $domain = Domain::where('account_id', $account->id)->findOrFail($request->domain_id);

        try {
            // Generate private key
            $privateKey = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            // Generate CSR
            $dn = [
                'countryName' => strtoupper($request->country),
                'stateOrProvinceName' => $request->state,
                'localityName' => $request->city,
                'organizationName' => $request->organization,
                'organizationalUnitName' => $request->organizational_unit ?? '',
                'commonName' => $domain->name,
                'emailAddress' => $request->email,
            ];

            $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => 'sha256']);

            // Export CSR and key
            openssl_csr_export($csr, $csrOut);
            openssl_pkey_export($privateKey, $keyOut);

            return $this->success([
                'csr' => $csrOut,
                'private_key' => $keyOut,
            ], 'CSR generated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to generate CSR: ' . $e->getMessage(), 500);
        }
    }

    public function renew(Request $request, int $id)
    {
        $account = $request->user()->account;

        $certificate = SslCertificate::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->findOrFail($id);

        if ($certificate->type !== 'lets_encrypt') {
            return $this->error('Only Let\'s Encrypt certificates can be renewed automatically', 422);
        }

        $domain = $certificate->domain;

        DB::beginTransaction();
        try {
            $result = $this->letsEncrypt->renewCertificate(
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

            // Reinstall certificate
            $this->sslInstaller->install($domain, $certificate);
            $this->webServer->enableSsl($domain, $certificate);

            DB::commit();
            return $this->success($certificate, 'SSL certificate renewed successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to renew SSL certificate: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $account = $request->user()->account;

        $certificate = SslCertificate::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->findOrFail($id);

        $domain = $certificate->domain;

        DB::beginTransaction();
        try {
            // Remove SSL from web server
            $this->webServer->disableSsl($domain);
            $this->sslInstaller->uninstall($domain);

            $certificate->delete();

            DB::commit();
            return $this->success(null, 'SSL certificate removed successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to remove SSL certificate: ' . $e->getMessage(), 500);
        }
    }
}
