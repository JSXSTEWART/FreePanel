<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RedirectController extends Controller
{
    /**
     * List all redirects
     */
    public function index(Request $request)
    {
        $account = $request->user()->account;

        $redirects = $account->redirects()
            ->with('domain:id,domain')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($redirect) => [
                'id' => $redirect->id,
                'domain' => $redirect->domain?->domain ?? 'All Domains',
                'domain_id' => $redirect->domain_id,
                'source_path' => $redirect->source_path,
                'destination_url' => $redirect->destination_url,
                'type' => $redirect->type,
                'status_code' => $redirect->status_code,
                'wildcard' => $redirect->wildcard,
                'is_active' => $redirect->is_active,
                'created_at' => $redirect->created_at->toIso8601String(),
            ]);

        return $this->success(['redirects' => $redirects]);
    }

    /**
     * Create a new redirect
     */
    public function store(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'domain_id' => 'nullable|integer|exists:domains,id',
            'source_path' => 'required|string|max:500',
            'destination_url' => 'required|url|max:1000',
            'type' => 'required|in:permanent,temporary',
            'wildcard' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Validate domain belongs to account
        if ($request->domain_id) {
            $domain = $account->domains()->find($request->domain_id);
            if (!$domain) {
                return $this->error('Domain not found', 404);
            }
        }

        // Check for duplicate
        $existing = $account->redirects()
            ->where('domain_id', $request->domain_id)
            ->where('source_path', $request->source_path)
            ->exists();

        if ($existing) {
            return $this->error('A redirect for this path already exists', 422);
        }

        $redirect = $account->redirects()->create([
            'domain_id' => $request->domain_id,
            'source_path' => $request->source_path,
            'destination_url' => $request->destination_url,
            'type' => $request->type,
            'wildcard' => $request->wildcard ?? false,
            'is_active' => true,
        ]);

        // Update .htaccess
        $this->syncRedirects($account, $request->domain_id);

        return $this->success([
            'id' => $redirect->id,
        ], 'Redirect created successfully', 201);
    }

    /**
     * Update a redirect
     */
    public function update(Request $request, int $id)
    {
        $account = $request->user()->account;
        $redirect = $account->redirects()->find($id);

        if (!$redirect) {
            return $this->error('Redirect not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'destination_url' => 'sometimes|url|max:1000',
            'type' => 'sometimes|in:permanent,temporary',
            'wildcard' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $redirect->update($request->only([
            'destination_url', 'type', 'wildcard', 'is_active'
        ]));

        // Update .htaccess
        $this->syncRedirects($account, $redirect->domain_id);

        return $this->success(null, 'Redirect updated successfully');
    }

    /**
     * Delete a redirect
     */
    public function destroy(Request $request, int $id)
    {
        $account = $request->user()->account;
        $redirect = $account->redirects()->find($id);

        if (!$redirect) {
            return $this->error('Redirect not found', 404);
        }

        $domainId = $redirect->domain_id;
        $redirect->delete();

        // Update .htaccess
        $this->syncRedirects($account, $domainId);

        return $this->success(null, 'Redirect deleted successfully');
    }

    /**
     * Toggle redirect active status
     */
    public function toggle(Request $request, int $id)
    {
        $account = $request->user()->account;
        $redirect = $account->redirects()->find($id);

        if (!$redirect) {
            return $this->error('Redirect not found', 404);
        }

        $redirect->update(['is_active' => !$redirect->is_active]);

        // Update .htaccess
        $this->syncRedirects($account, $redirect->domain_id);

        return $this->success([
            'is_active' => $redirect->is_active,
        ], $redirect->is_active ? 'Redirect enabled' : 'Redirect disabled');
    }

    /**
     * Sync redirects to .htaccess
     */
    protected function syncRedirects($account, ?int $domainId = null): void
    {
        $homeDir = "/home/{$account->system_username}";

        if ($domainId) {
            $domain = $account->domains()->find($domainId);
            $docRoot = "{$homeDir}/{$domain->document_root}";
            $redirects = $account->redirects()
                ->where('domain_id', $domainId)
                ->where('is_active', true)
                ->get();
        } else {
            $docRoot = "{$homeDir}/public_html";
            $redirects = $account->redirects()
                ->whereNull('domain_id')
                ->where('is_active', true)
                ->get();
        }

        $htaccessPath = "{$docRoot}/.htaccess";
        $existing = file_exists($htaccessPath) ? file_get_contents($htaccessPath) : '';

        // Remove existing redirects section
        $startMarker = "# BEGIN FreePanel Redirects";
        $endMarker = "# END FreePanel Redirects";
        $pattern = "/\n?{$startMarker}.*?{$endMarker}\n?/s";
        $existing = preg_replace($pattern, '', $existing);

        // Build new directives
        $rules = "# BEGIN FreePanel Redirects\n";
        $rules .= "RewriteEngine On\n";

        foreach ($redirects as $redirect) {
            $statusFlag = $redirect->type === 'permanent' ? 'R=301' : 'R=302';
            $source = preg_quote($redirect->source_path, '/');

            if ($redirect->wildcard) {
                $rules .= "RewriteRule ^{$source}(.*)\$ {$redirect->destination_url}\$1 [{$statusFlag},L]\n";
            } else {
                $rules .= "RewriteRule ^{$source}\$ {$redirect->destination_url} [{$statusFlag},L]\n";
            }
        }

        $rules .= "# END FreePanel Redirects\n";

        file_put_contents($htaccessPath, trim($existing) . "\n\n" . $rules);
    }
}
