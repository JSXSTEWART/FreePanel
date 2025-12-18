<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\ErrorPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ErrorPageController extends Controller
{
    /**
     * List all custom error pages
     */
    public function index(Request $request)
    {
        $account = $request->user()->account;

        $errorPages = $account->errorPages()
            ->with('domain:id,domain')
            ->get()
            ->map(fn($page) => [
                'id' => $page->id,
                'error_code' => $page->error_code,
                'error_name' => ErrorPage::getSupportedCodes()[$page->error_code] ?? 'Unknown',
                'domain' => $page->domain?->domain ?? 'All Domains',
                'domain_id' => $page->domain_id,
                'is_active' => $page->is_active,
                'created_at' => $page->created_at->toIso8601String(),
            ]);

        return $this->success([
            'error_pages' => $errorPages,
            'supported_codes' => ErrorPage::getSupportedCodes(),
        ]);
    }

    /**
     * Get a specific error page content
     */
    public function show(Request $request, int $id)
    {
        $account = $request->user()->account;
        $errorPage = $account->errorPages()->find($id);

        if (!$errorPage) {
            return $this->error('Error page not found', 404);
        }

        return $this->success([
            'id' => $errorPage->id,
            'error_code' => $errorPage->error_code,
            'content' => $errorPage->content,
            'domain_id' => $errorPage->domain_id,
            'is_active' => $errorPage->is_active,
        ]);
    }

    /**
     * Create or update a custom error page
     */
    public function store(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'error_code' => 'required|integer|in:' . implode(',', array_keys(ErrorPage::getSupportedCodes())),
            'content' => 'required|string|max:50000',
            'domain_id' => 'nullable|integer|exists:domains,id',
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

        $errorPage = $account->errorPages()->updateOrCreate(
            [
                'error_code' => $request->error_code,
                'domain_id' => $request->domain_id,
            ],
            [
                'content' => $request->content,
                'is_active' => true,
            ]
        );

        // Deploy error page
        $this->deployErrorPage($account, $errorPage);

        return $this->success([
            'id' => $errorPage->id,
        ], 'Error page saved successfully', 201);
    }

    /**
     * Toggle error page active status
     */
    public function toggle(Request $request, int $id)
    {
        $account = $request->user()->account;
        $errorPage = $account->errorPages()->find($id);

        if (!$errorPage) {
            return $this->error('Error page not found', 404);
        }

        $errorPage->update(['is_active' => !$errorPage->is_active]);

        // Re-deploy or remove
        if ($errorPage->is_active) {
            $this->deployErrorPage($account, $errorPage);
        } else {
            $this->removeErrorPage($account, $errorPage);
        }

        return $this->success([
            'is_active' => $errorPage->is_active,
        ], $errorPage->is_active ? 'Error page enabled' : 'Error page disabled');
    }

    /**
     * Delete a custom error page
     */
    public function destroy(Request $request, int $id)
    {
        $account = $request->user()->account;
        $errorPage = $account->errorPages()->find($id);

        if (!$errorPage) {
            return $this->error('Error page not found', 404);
        }

        $this->removeErrorPage($account, $errorPage);
        $errorPage->delete();

        return $this->success(null, 'Error page deleted successfully');
    }

    /**
     * Get default content for an error code
     */
    public function getDefault(Request $request, int $code)
    {
        if (!array_key_exists($code, ErrorPage::getSupportedCodes())) {
            return $this->error('Invalid error code', 422);
        }

        return $this->success([
            'content' => ErrorPage::getDefaultContent($code),
        ]);
    }

    /**
     * Deploy error page to the filesystem
     */
    protected function deployErrorPage($account, ErrorPage $errorPage): void
    {
        $homeDir = "/home/{$account->system_username}";

        if ($errorPage->domain_id) {
            $domain = $errorPage->domain;
            $docRoot = "{$homeDir}/{$domain->document_root}";
        } else {
            $docRoot = "{$homeDir}/public_html";
        }

        $errorDir = "{$docRoot}/error_documents";
        if (!is_dir($errorDir)) {
            mkdir($errorDir, 0755, true);
        }

        $filename = "{$errorDir}/{$errorPage->error_code}.html";
        file_put_contents($filename, $errorPage->content);
        chmod($filename, 0644);

        // Update .htaccess
        $this->updateErrorDocuments($docRoot, $account);
    }

    /**
     * Remove error page from filesystem
     */
    protected function removeErrorPage($account, ErrorPage $errorPage): void
    {
        $homeDir = "/home/{$account->system_username}";

        if ($errorPage->domain_id) {
            $domain = $errorPage->domain;
            $docRoot = "{$homeDir}/{$domain->document_root}";
        } else {
            $docRoot = "{$homeDir}/public_html";
        }

        $filename = "{$docRoot}/error_documents/{$errorPage->error_code}.html";
        @unlink($filename);

        // Update .htaccess
        $this->updateErrorDocuments($docRoot, $account);
    }

    /**
     * Update ErrorDocument directives in .htaccess
     */
    protected function updateErrorDocuments(string $docRoot, $account): void
    {
        $htaccessPath = "{$docRoot}/.htaccess";
        $errorDir = "{$docRoot}/error_documents";

        $existing = file_exists($htaccessPath) ? file_get_contents($htaccessPath) : '';

        // Remove existing error document section
        $startMarker = "# BEGIN FreePanel Error Documents";
        $endMarker = "# END FreePanel Error Documents";
        $pattern = "/\n?{$startMarker}.*?{$endMarker}\n?/s";
        $existing = preg_replace($pattern, '', $existing);

        // Build new directives
        $directives = "# BEGIN FreePanel Error Documents\n";

        if (is_dir($errorDir)) {
            foreach (glob("{$errorDir}/*.html") as $file) {
                $code = basename($file, '.html');
                if (is_numeric($code)) {
                    $directives .= "ErrorDocument {$code} /error_documents/{$code}.html\n";
                }
            }
        }

        $directives .= "# END FreePanel Error Documents\n";

        file_put_contents($htaccessPath, trim($existing) . "\n\n" . $directives);
    }
}
