<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Subdomain;
use App\Services\WebServer\WebServerInterface;
use App\Services\Dns\DnsInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DomainController extends Controller
{
    protected WebServerInterface $webServer;
    protected DnsInterface $dns;

    public function __construct(WebServerInterface $webServer, DnsInterface $dns)
    {
        $this->webServer = $webServer;
        $this->dns = $dns;
    }

    public function index(Request $request)
    {
        $account = $request->user()->account;

        $domains = Domain::where('account_id', $account->id)
            ->with(['subdomains', 'sslCertificate'])
            ->orderBy('is_main', 'desc')
            ->orderBy('name')
            ->get();

        return $this->success($domains);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:domains,name',
            'document_root' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $account = $request->user()->account;

        // Check domain quota
        $currentDomains = Domain::where('account_id', $account->id)->count();
        if ($account->package->max_addon_domains != -1 && $currentDomains >= $account->package->max_addon_domains + 1) {
            return $this->error('Domain quota exceeded', 403);
        }

        DB::beginTransaction();
        try {
            $domain = Domain::create([
                'account_id' => $account->id,
                'name' => strtolower($request->name),
                'document_root' => $request->document_root ?? "/home/{$account->username}/public_html/{$request->name}",
                'is_main' => false,
                'status' => 'pending',
            ]);

            // Create virtual host
            $this->webServer->createVirtualHost($domain);

            // Create DNS zone
            $this->dns->createZone($domain);

            $domain->update(['status' => 'active']);

            DB::commit();
            return $this->success($domain, 'Domain created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create domain: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, int $id)
    {
        $account = $request->user()->account;

        $domain = Domain::where('account_id', $account->id)
            ->with(['subdomains', 'sslCertificate', 'dnsZone.records'])
            ->findOrFail($id);

        return $this->success($domain);
    }

    public function update(Request $request, int $id)
    {
        $account = $request->user()->account;

        $domain = Domain::where('account_id', $account->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'document_root' => 'nullable|string|max:500',
            'php_version' => 'nullable|string|in:7.4,8.0,8.1,8.2,8.3',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            $domain->update($request->only(['document_root', 'php_version']));

            // Update virtual host
            $this->webServer->updateVirtualHost($domain);

            DB::commit();
            return $this->success($domain, 'Domain updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update domain: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $account = $request->user()->account;

        $domain = Domain::where('account_id', $account->id)->findOrFail($id);

        if ($domain->is_main) {
            return $this->error('Cannot delete main domain', 403);
        }

        DB::beginTransaction();
        try {
            // Remove virtual host
            $this->webServer->removeVirtualHost($domain);

            // Remove DNS zone
            $this->dns->removeZone($domain);

            $domain->delete();

            DB::commit();
            return $this->success(null, 'Domain deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete domain: ' . $e->getMessage(), 500);
        }
    }

    // Subdomain methods
    public function subdomains(Request $request, int $domainId)
    {
        $account = $request->user()->account;
        $domain = Domain::where('account_id', $account->id)->findOrFail($domainId);

        return $this->success($domain->subdomains);
    }

    public function storeSubdomain(Request $request, int $domainId)
    {
        $account = $request->user()->account;
        $domain = Domain::where('account_id', $account->id)->findOrFail($domainId);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:63|regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
            'document_root' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check subdomain quota
        $currentSubdomains = Subdomain::whereHas('domain', fn($q) => $q->where('account_id', $account->id))->count();
        if ($account->package->max_subdomains != -1 && $currentSubdomains >= $account->package->max_subdomains) {
            return $this->error('Subdomain quota exceeded', 403);
        }

        // Check uniqueness
        $fullName = strtolower($request->name) . '.' . $domain->name;
        if (Subdomain::where('domain_id', $domain->id)->where('name', $request->name)->exists()) {
            return $this->error('Subdomain already exists', 422);
        }

        DB::beginTransaction();
        try {
            $subdomain = Subdomain::create([
                'domain_id' => $domain->id,
                'name' => strtolower($request->name),
                'document_root' => $request->document_root ?? "/home/{$account->username}/public_html/{$fullName}",
            ]);

            // Create virtual host for subdomain
            $this->webServer->createSubdomainVirtualHost($subdomain);

            // Add DNS A record
            $this->dns->addRecord($domain->dnsZone, [
                'type' => 'A',
                'name' => $request->name,
                'content' => config('freepanel.server_ip'),
                'ttl' => 3600,
            ]);

            DB::commit();
            return $this->success($subdomain, 'Subdomain created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create subdomain: ' . $e->getMessage(), 500);
        }
    }

    public function destroySubdomain(Request $request, int $domainId, int $subdomainId)
    {
        $account = $request->user()->account;
        $domain = Domain::where('account_id', $account->id)->findOrFail($domainId);
        $subdomain = Subdomain::where('domain_id', $domain->id)->findOrFail($subdomainId);

        DB::beginTransaction();
        try {
            $this->webServer->removeSubdomainVirtualHost($subdomain);
            $this->dns->removeRecord($domain->dnsZone, $subdomain->name, 'A');

            $subdomain->delete();

            DB::commit();
            return $this->success(null, 'Subdomain deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete subdomain: ' . $e->getMessage(), 500);
        }
    }
}
