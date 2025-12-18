<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use App\Models\HotlinkProtection;
use App\Models\ProtectedDirectory;
use App\Models\ProtectedDirectoryUser;
use App\Services\WebServer\WebServerInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SecurityController extends Controller
{
    protected WebServerInterface $webServer;

    public function __construct(WebServerInterface $webServer)
    {
        $this->webServer = $webServer;
    }

    // ========== IP BLOCKER ==========

    /**
     * List all blocked IPs
     */
    public function listBlockedIps(Request $request)
    {
        $account = $request->user()->account;

        $blockedIps = $account->blockedIps()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($ip) => [
                'id' => $ip->id,
                'ip_address' => $ip->ip_address,
                'reason' => $ip->reason,
                'expires_at' => $ip->expires_at?->toIso8601String(),
                'is_active' => $ip->isActive(),
                'created_at' => $ip->created_at->toIso8601String(),
            ]);

        return $this->success(['blocked_ips' => $blockedIps]);
    }

    /**
     * Block an IP address
     */
    public function blockIp(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'ip_address' => 'required|string|max:45',
            'reason' => 'nullable|string|max:500',
            'duration' => 'nullable|integer|min:0', // Hours, 0 = permanent
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        if (!BlockedIp::isValidIp($request->ip_address)) {
            return $this->error('Invalid IP address format', 422);
        }

        // Check if already blocked
        if ($account->blockedIps()->where('ip_address', $request->ip_address)->exists()) {
            return $this->error('This IP is already blocked', 422);
        }

        $expiresAt = null;
        if ($request->duration && $request->duration > 0) {
            $expiresAt = now()->addHours($request->duration);
        }

        $blockedIp = $account->blockedIps()->create([
            'ip_address' => $request->ip_address,
            'reason' => $request->reason,
            'expires_at' => $expiresAt,
        ]);

        // Update .htaccess
        $this->syncIpBlockList($account);

        return $this->success([
            'id' => $blockedIp->id,
        ], 'IP address blocked successfully', 201);
    }

    /**
     * Unblock an IP address
     */
    public function unblockIp(Request $request, int $id)
    {
        $account = $request->user()->account;
        $blockedIp = $account->blockedIps()->find($id);

        if (!$blockedIp) {
            return $this->error('Blocked IP not found', 404);
        }

        $blockedIp->delete();

        // Update .htaccess
        $this->syncIpBlockList($account);

        return $this->success(null, 'IP address unblocked successfully');
    }

    // ========== HOTLINK PROTECTION ==========

    /**
     * Get hotlink protection settings
     */
    public function getHotlinkProtection(Request $request)
    {
        $account = $request->user()->account;

        $protection = $account->hotlinkProtection ?? new HotlinkProtection([
            'is_enabled' => false,
            'allowed_urls' => [],
            'protected_extensions' => HotlinkProtection::getDefaultExtensions(),
            'allow_direct_requests' => true,
        ]);

        return $this->success([
            'is_enabled' => $protection->is_enabled,
            'allowed_urls' => $protection->allowed_urls ?? [],
            'protected_extensions' => $protection->protected_extensions ?? HotlinkProtection::getDefaultExtensions(),
            'allow_direct_requests' => $protection->allow_direct_requests,
            'redirect_url' => $protection->redirect_url,
            'default_extensions' => HotlinkProtection::getDefaultExtensions(),
        ]);
    }

    /**
     * Update hotlink protection settings
     */
    public function updateHotlinkProtection(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'is_enabled' => 'required|boolean',
            'allowed_urls' => 'nullable|array',
            'allowed_urls.*' => 'string|max:255',
            'protected_extensions' => 'nullable|array',
            'protected_extensions.*' => 'string|max:20',
            'allow_direct_requests' => 'required|boolean',
            'redirect_url' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $protection = $account->hotlinkProtection()->updateOrCreate(
            ['account_id' => $account->id],
            [
                'is_enabled' => $request->is_enabled,
                'allowed_urls' => $request->allowed_urls ?? [],
                'protected_extensions' => $request->protected_extensions ?? HotlinkProtection::getDefaultExtensions(),
                'allow_direct_requests' => $request->allow_direct_requests,
                'redirect_url' => $request->redirect_url,
            ]
        );

        // Update .htaccess
        $this->syncHotlinkProtection($account);

        return $this->success(null, 'Hotlink protection updated successfully');
    }

    // ========== PASSWORD PROTECTED DIRECTORIES ==========

    /**
     * List protected directories
     */
    public function listProtectedDirectories(Request $request)
    {
        $account = $request->user()->account;

        $directories = $account->protectedDirectories()
            ->with('users:id,protected_directory_id,username')
            ->get()
            ->map(fn($dir) => [
                'id' => $dir->id,
                'path' => $dir->path,
                'name' => $dir->name,
                'users' => $dir->users->map(fn($u) => [
                    'id' => $u->id,
                    'username' => $u->username,
                ]),
                'created_at' => $dir->created_at->toIso8601String(),
            ]);

        return $this->success(['directories' => $directories]);
    }

    /**
     * Protect a directory
     */
    public function protectDirectory(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'path' => 'required|string|max:500',
            'name' => 'required|string|max:100',
            'username' => 'required|string|max:50',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Validate path is within user's home directory
        $homeDir = "/home/{$account->system_username}";
        $fullPath = realpath($homeDir . '/' . ltrim($request->path, '/'));

        if (!$fullPath || !str_starts_with($fullPath, $homeDir)) {
            return $this->error('Invalid directory path', 422);
        }

        if (!is_dir($fullPath)) {
            return $this->error('Directory does not exist', 422);
        }

        // Check if already protected
        if ($account->protectedDirectories()->where('path', $request->path)->exists()) {
            return $this->error('This directory is already protected', 422);
        }

        $directory = $account->protectedDirectories()->create([
            'path' => $request->path,
            'name' => $request->name,
        ]);

        $directory->users()->create([
            'username' => $request->username,
            'password' => ProtectedDirectoryUser::hashPassword($request->password),
        ]);

        // Create .htaccess and .htpasswd files
        $this->syncDirectoryProtection($account, $directory);

        return $this->success([
            'id' => $directory->id,
        ], 'Directory protected successfully', 201);
    }

    /**
     * Add user to protected directory
     */
    public function addDirectoryUser(Request $request, int $directoryId)
    {
        $account = $request->user()->account;
        $directory = $account->protectedDirectories()->find($directoryId);

        if (!$directory) {
            return $this->error('Protected directory not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        if ($directory->users()->where('username', $request->username)->exists()) {
            return $this->error('Username already exists for this directory', 422);
        }

        $user = $directory->users()->create([
            'username' => $request->username,
            'password' => ProtectedDirectoryUser::hashPassword($request->password),
        ]);

        // Update .htpasswd
        $this->syncDirectoryProtection($account, $directory);

        return $this->success([
            'id' => $user->id,
        ], 'User added successfully', 201);
    }

    /**
     * Remove user from protected directory
     */
    public function removeDirectoryUser(Request $request, int $directoryId, int $userId)
    {
        $account = $request->user()->account;
        $directory = $account->protectedDirectories()->find($directoryId);

        if (!$directory) {
            return $this->error('Protected directory not found', 404);
        }

        $user = $directory->users()->find($userId);
        if (!$user) {
            return $this->error('User not found', 404);
        }

        $user->delete();

        // Update .htpasswd
        $this->syncDirectoryProtection($account, $directory);

        return $this->success(null, 'User removed successfully');
    }

    /**
     * Unprotect a directory
     */
    public function unprotectDirectory(Request $request, int $id)
    {
        $account = $request->user()->account;
        $directory = $account->protectedDirectories()->find($id);

        if (!$directory) {
            return $this->error('Protected directory not found', 404);
        }

        // Remove .htaccess and .htpasswd files
        $this->removeDirectoryProtection($account, $directory);

        $directory->delete();

        return $this->success(null, 'Directory unprotected successfully');
    }

    // ========== HELPER METHODS ==========

    protected function syncIpBlockList($account): void
    {
        $homeDir = "/home/{$account->system_username}";
        $htaccessPath = "{$homeDir}/public_html/.htaccess";

        $blockedIps = $account->blockedIps()->active()->pluck('ip_address')->toArray();

        // Generate deny rules
        $rules = "# BEGIN FreePanel IP Blocker\n";
        $rules .= "<IfModule mod_authz_core.c>\n";
        foreach ($blockedIps as $ip) {
            $rules .= "    Require not ip {$ip}\n";
        }
        $rules .= "</IfModule>\n";
        $rules .= "<IfModule !mod_authz_core.c>\n";
        $rules .= "    Order Allow,Deny\n";
        $rules .= "    Allow from all\n";
        foreach ($blockedIps as $ip) {
            $rules .= "    Deny from {$ip}\n";
        }
        $rules .= "</IfModule>\n";
        $rules .= "# END FreePanel IP Blocker\n";

        $this->updateHtaccessSection($htaccessPath, 'FreePanel IP Blocker', $rules);
    }

    protected function syncHotlinkProtection($account): void
    {
        $homeDir = "/home/{$account->system_username}";
        $htaccessPath = "{$homeDir}/public_html/.htaccess";

        $protection = $account->hotlinkProtection;

        if (!$protection || !$protection->is_enabled) {
            $this->updateHtaccessSection($htaccessPath, 'FreePanel Hotlink Protection', '');
            return;
        }

        $extensions = implode('|', $protection->protected_extensions ?? []);
        $allowedUrls = array_merge(
            [''],
            $protection->allowed_urls ?? []
        );

        $rules = "# BEGIN FreePanel Hotlink Protection\n";
        $rules .= "RewriteEngine On\n";

        foreach ($allowedUrls as $url) {
            if ($url) {
                $rules .= "RewriteCond %{HTTP_REFERER} !^https?://(www\\.)?{$url} [NC]\n";
            }
        }

        if ($protection->allow_direct_requests) {
            $rules .= "RewriteCond %{HTTP_REFERER} !^$\n";
        }

        $rules .= "RewriteCond %{HTTP_REFERER} !^https?://(www\\.)?" . preg_quote($_SERVER['HTTP_HOST'] ?? '', '/') . " [NC]\n";
        $rules .= "RewriteRule \\.({$extensions})$ ";

        if ($protection->redirect_url) {
            $rules .= $protection->redirect_url;
        } else {
            $rules .= "- [F,NC]";
        }

        $rules .= "\n# END FreePanel Hotlink Protection\n";

        $this->updateHtaccessSection($htaccessPath, 'FreePanel Hotlink Protection', $rules);
    }

    protected function syncDirectoryProtection($account, $directory): void
    {
        $homeDir = "/home/{$account->system_username}";
        $fullPath = "{$homeDir}/" . ltrim($directory->path, '/');
        $htaccessPath = "{$fullPath}/.htaccess";
        $htpasswdPath = "{$fullPath}/.htpasswd";

        // Create .htpasswd
        $htpasswd = '';
        foreach ($directory->users as $user) {
            $htpasswd .= "{$user->username}:{$user->password}\n";
        }
        file_put_contents($htpasswdPath, $htpasswd);
        chmod($htpasswdPath, 0644);

        // Create .htaccess
        $htaccess = "AuthType Basic\n";
        $htaccess .= "AuthName \"{$directory->name}\"\n";
        $htaccess .= "AuthUserFile {$htpasswdPath}\n";
        $htaccess .= "Require valid-user\n";
        file_put_contents($htaccessPath, $htaccess);
        chmod($htaccessPath, 0644);
    }

    protected function removeDirectoryProtection($account, $directory): void
    {
        $homeDir = "/home/{$account->system_username}";
        $fullPath = "{$homeDir}/" . ltrim($directory->path, '/');

        @unlink("{$fullPath}/.htaccess");
        @unlink("{$fullPath}/.htpasswd");
    }

    protected function updateHtaccessSection(string $path, string $section, string $content): void
    {
        $existing = file_exists($path) ? file_get_contents($path) : '';

        $startMarker = "# BEGIN {$section}";
        $endMarker = "# END {$section}";

        // Remove existing section
        $pattern = "/\n?{$startMarker}.*?{$endMarker}\n?/s";
        $existing = preg_replace($pattern, '', $existing);

        // Add new content if not empty
        if ($content) {
            $existing = trim($existing) . "\n\n" . $content;
        }

        file_put_contents($path, trim($existing) . "\n");
    }
}
