<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Domain;
use App\Models\Package;
use App\Models\User;
use App\Services\System\UserManager;
use App\Services\WebServer\WebServerInterface;
use App\Services\Dns\DnsInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    protected UserManager $userManager;
    protected WebServerInterface $webServer;
    protected DnsInterface $dns;

    public function __construct(
        UserManager $userManager,
        WebServerInterface $webServer,
        DnsInterface $dns
    ) {
        $this->userManager = $userManager;
        $this->webServer = $webServer;
        $this->dns = $dns;
    }

    public function index(Request $request)
    {
        $query = Account::with(['user', 'package', 'reseller']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        if ($request->has('reseller_id')) {
            $query->where('reseller_id', $request->reseller_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%")
                    ->orWhereHas('user', fn($q) => $q->where('email', 'like', "%{$search}%"));
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 25), 100);
        $accounts = $query->paginate($perPage);

        return $this->success($accounts);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|min:3|max:16|regex:/^[a-z][a-z0-9]*$/|unique:accounts,username',
            'password' => 'required|string|min:8',
            'email' => 'required|email|unique:users,email',
            'domain' => 'required|string|max:255|unique:accounts,domain|unique:domains,name',
            'package_id' => 'required|exists:packages,id',
            'reseller_id' => 'nullable|exists:accounts,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            // Create user
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'user',
            ]);

            // Create system user
            $systemUser = $this->userManager->createUser($request->username, $request->password);

            // Create account
            $account = Account::create([
                'user_id' => $user->id,
                'package_id' => $request->package_id,
                'reseller_id' => $request->reseller_id,
                'username' => $request->username,
                'domain' => strtolower($request->domain),
                'uid' => $systemUser['uid'],
                'gid' => $systemUser['gid'],
                'home_directory' => "/home/{$request->username}",
                'status' => 'active',
            ]);

            // Create home directory structure
            $this->userManager->createHomeDirectory($account);

            // Create main domain
            $domain = Domain::create([
                'account_id' => $account->id,
                'name' => strtolower($request->domain),
                'document_root' => "/home/{$request->username}/public_html",
                'is_main' => true,
                'status' => 'active',
            ]);

            // Create virtual host
            $this->webServer->createVirtualHost($domain);

            // Create DNS zone
            $this->dns->createZone($domain);

            DB::commit();

            return $this->success($account->load(['user', 'package']), 'Account created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Cleanup if needed
            if (isset($systemUser)) {
                $this->userManager->deleteUser($request->username);
            }

            return $this->error('Failed to create account: ' . $e->getMessage(), 500);
        }
    }

    public function show(int $id)
    {
        $account = Account::with([
            'user',
            'package',
            'reseller',
            'domains',
            'features',
        ])->findOrFail($id);

        // Get resource usage
        $account->disk_used = $this->userManager->getDiskUsage($account->username);
        $account->bandwidth_used = $this->getBandwidthUsage($account);

        return $this->success($account);
    }

    public function update(Request $request, int $id)
    {
        $account = Account::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email|unique:users,email,' . $account->user_id,
            'package_id' => 'nullable|exists:packages,id',
            'reseller_id' => 'nullable|exists:accounts,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            if ($request->has('email')) {
                $account->user->update(['email' => $request->email]);
            }

            $account->update($request->only(['package_id', 'reseller_id']));

            DB::commit();
            return $this->success($account->fresh()->load(['user', 'package']), 'Account updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update account: ' . $e->getMessage(), 500);
        }
    }

    public function changePassword(Request $request, int $id)
    {
        $account = Account::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            // Update database password
            $account->user->update(['password' => Hash::make($request->password)]);

            // Update system user password
            $this->userManager->changePassword($account->username, $request->password);

            DB::commit();
            return $this->success(null, 'Password changed successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to change password: ' . $e->getMessage(), 500);
        }
    }

    public function suspend(Request $request, int $id)
    {
        $account = Account::findOrFail($id);

        if ($account->status === 'suspended') {
            return $this->error('Account is already suspended', 422);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            // Suspend system user
            $this->userManager->suspendUser($account->username);

            // Disable web server configs
            foreach ($account->domains as $domain) {
                $this->webServer->disableVirtualHost($domain);
            }

            $account->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'suspension_reason' => $request->reason,
            ]);

            $account->user->update(['is_active' => false]);

            DB::commit();
            return $this->success($account, 'Account suspended successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to suspend account: ' . $e->getMessage(), 500);
        }
    }

    public function unsuspend(int $id)
    {
        $account = Account::findOrFail($id);

        if ($account->status !== 'suspended') {
            return $this->error('Account is not suspended', 422);
        }

        DB::beginTransaction();
        try {
            // Unsuspend system user
            $this->userManager->unsuspendUser($account->username);

            // Enable web server configs
            foreach ($account->domains as $domain) {
                $this->webServer->enableVirtualHost($domain);
            }

            $account->update([
                'status' => 'active',
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);

            $account->user->update(['is_active' => true]);

            DB::commit();
            return $this->success($account, 'Account unsuspended successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to unsuspend account: ' . $e->getMessage(), 500);
        }
    }

    public function terminate(Request $request, int $id)
    {
        $account = Account::findOrFail($id);

        // TODO: Full account termination verification implementation
        // This is a destructive operation that should include multiple safety checks:
        //
        // 1. Require explicit confirmation with account username:
        //    $validator = Validator::make($request->all(), [
        //        'confirm_username' => 'required|string|in:' . $account->username,
        //        'backup_data' => 'nullable|boolean',
        //        'keep_dns' => 'nullable|boolean',
        //        'reason' => 'nullable|string|max:500',
        //    ]);
        //
        // 2. Create final backup before deletion (if requested):
        //    if ($request->boolean('backup_data', true)) {
        //        $backupService = app(BackupService::class);
        //        $backupPath = $backupService->createFullBackup($account);
        //        // Optionally email backup download link to admin or account email
        //    }
        //
        // 3. Send notification email to account owner:
        //    Mail::to($account->user->email)->send(new AccountTerminationNotice($account, $request->reason));
        //
        // 4. Optionally keep DNS records for grace period:
        //    if (!$request->boolean('keep_dns', false)) {
        //        $this->dns->removeZone($domain);
        //    }
        //
        // 5. Archive account data for compliance/audit trail:
        //    TerminatedAccount::create([
        //        'original_id' => $account->id,
        //        'username' => $account->username,
        //        'email' => $account->user->email,
        //        'domain' => $account->domain,
        //        'terminated_by' => auth()->id(),
        //        'reason' => $request->reason,
        //        'backup_path' => $backupPath ?? null,
        //        'account_data' => $account->toArray(),
        //    ]);
        //
        // 6. Add delay/cooling-off period:
        //    $account->update(['status' => 'pending_termination', 'terminate_at' => now()->addDays(7)]);
        //    // Use scheduled job to actually delete after cooling-off period

        // Validate confirmation
        $validator = Validator::make($request->all(), [
            'confirm' => 'required|boolean|accepted',
            'confirm_username' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Verify username matches
        if ($request->confirm_username !== $account->username) {
            return $this->error(
                'Username confirmation does not match. Please type the exact username to confirm termination.',
                422
            );
        }

        DB::beginTransaction();
        try {
            // Log the termination for audit trail
            \Log::warning("Account termination initiated", [
                'account_id' => $account->id,
                'username' => $account->username,
                'terminated_by' => auth()->id(),
                'ip' => request()->ip(),
            ]);

            // Remove all domains and their configs
            foreach ($account->domains as $domain) {
                $this->webServer->removeVirtualHost($domain);
                $this->dns->removeZone($domain);
            }

            // Remove system user and home directory
            $this->userManager->deleteUser($account->username);

            // Delete account (cascades to domains, emails, databases, etc.)
            $account->user->delete();
            $account->delete();

            DB::commit();
            return $this->success(null, 'Account terminated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to terminate account: ' . $e->getMessage(), 500);
        }
    }

    public function loginAs(int $id)
    {
        $account = Account::with('user')->findOrFail($id);

        // Generate temporary token for user
        $token = $account->user->createToken('admin-login-as', ['*'], now()->addHour());

        return $this->success([
            'token' => $token->plainTextToken,
            'user' => $account->user,
            'expires_at' => now()->addHour(),
        ], 'Login token generated');
    }

    public function stats()
    {
        $stats = [
            'total' => Account::count(),
            'active' => Account::where('status', 'active')->count(),
            'suspended' => Account::where('status', 'suspended')->count(),
            'created_today' => Account::whereDate('created_at', today())->count(),
            'created_this_month' => Account::whereMonth('created_at', now()->month)->count(),
            'by_package' => Package::withCount('accounts')->get()->pluck('accounts_count', 'name'),
        ];

        return $this->success($stats);
    }

    protected function getBandwidthUsage(Account $account): int
    {
        $totalBandwidth = 0;

        // Get all domains for this account
        foreach ($account->domains as $domain) {
            $totalBandwidth += $this->getDomainBandwidth($domain);
        }

        // Update the account's bandwidth_used field
        if ($totalBandwidth !== $account->bandwidth_used) {
            $account->update(['bandwidth_used' => $totalBandwidth]);
        }

        return $totalBandwidth;
    }

    protected function getDomainBandwidth(Domain $domain): int
    {
        $bandwidth = 0;
        $currentMonth = now()->format('Y-m');

        // Try different log file locations
        $logPaths = [
            "/var/log/apache2/domlogs/{$domain->name}-bytes_log",
            "/var/log/httpd/domlogs/{$domain->name}-bytes_log",
            "/var/log/apache2/{$domain->name}-bytes.log",
        ];

        $logPath = null;
        foreach ($logPaths as $path) {
            if (file_exists($path)) {
                $logPath = $path;
                break;
            }
        }

        if ($logPath && file_exists($logPath)) {
            $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                foreach ($lines as $line) {
                    // Format: timestamp bytes
                    $parts = preg_split('/\s+/', trim($line));
                    if (count($parts) >= 2) {
                        $timestamp = (int) $parts[0];
                        $bytes = (int) $parts[1];

                        // Only count current month
                        if (date('Y-m', $timestamp) === $currentMonth) {
                            $bandwidth += $bytes;
                        }
                    }
                }
            }
        } else {
            // Fallback: Parse access log for bandwidth
            $bandwidth = $this->parseAccessLogBandwidth($domain);
        }

        return $bandwidth;
    }

    protected function parseAccessLogBandwidth(Domain $domain): int
    {
        $bandwidth = 0;
        $currentMonth = now()->format('Y-m');

        $logPaths = [
            "/var/log/apache2/domlogs/{$domain->name}-access_log",
            "/var/log/httpd/domlogs/{$domain->name}-access_log",
            "/var/log/apache2/{$domain->name}-access.log",
            "/var/log/nginx/{$domain->name}.access.log",
        ];

        $logPath = null;
        foreach ($logPaths as $path) {
            if (file_exists($path)) {
                $logPath = $path;
                break;
            }
        }

        if (!$logPath || !file_exists($logPath)) {
            return 0;
        }

        $handle = @fopen($logPath, 'r');
        if (!$handle) {
            return 0;
        }

        // Read last 100MB max for performance
        $maxBytes = 100 * 1024 * 1024;
        $fileSize = filesize($logPath);
        if ($fileSize > $maxBytes) {
            fseek($handle, $fileSize - $maxBytes);
            fgets($handle); // Skip partial line
        }

        while (($line = fgets($handle)) !== false) {
            // Combined log format: IP - - [date] "request" status bytes
            if (preg_match('/\[(\d{2})\/([A-Za-z]{3})\/(\d{4}):.*\]\s+"[^"]+"\s+\d+\s+(\d+)/', $line, $matches)) {
                $day = $matches[1];
                $month = $this->monthToNumber($matches[2]);
                $year = $matches[3];
                $bytes = (int) $matches[4];

                $logMonth = "{$year}-{$month}";
                if ($logMonth === $currentMonth && $bytes > 0) {
                    $bandwidth += $bytes;
                }
            }
        }

        fclose($handle);

        return $bandwidth;
    }

    protected function monthToNumber(string $month): string
    {
        $months = [
            'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04',
            'May' => '05', 'Jun' => '06', 'Jul' => '07', 'Aug' => '08',
            'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dec' => '12',
        ];

        return $months[$month] ?? '01';
    }

    public function usage(int $id)
    {
        $account = Account::with(['package', 'domains'])->findOrFail($id);

        // Get current resource usage
        $diskUsed = $this->userManager->getDiskUsage($account->username);
        $bandwidthUsed = $this->getBandwidthUsage($account);

        // Update account with latest usage
        $account->update([
            'disk_used' => $diskUsed,
            'bandwidth_used' => $bandwidthUsed,
        ]);

        return $this->success([
            'disk' => [
                'used' => $diskUsed,
                'limit' => $account->package->disk_quota ?? -1,
                'percent' => $account->package->disk_quota > 0
                    ? round(($diskUsed / $account->package->disk_quota) * 100, 2)
                    : 0,
            ],
            'bandwidth' => [
                'used' => $bandwidthUsed,
                'limit' => $account->package->bandwidth ?? -1,
                'percent' => $account->package->bandwidth > 0
                    ? round(($bandwidthUsed / $account->package->bandwidth) * 100, 2)
                    : 0,
            ],
            'quotas' => [
                'domains' => [
                    'used' => $account->domains()->where('is_main', false)->count(),
                    'limit' => $account->package->max_addon_domains ?? -1,
                ],
                'databases' => [
                    'used' => $account->databases()->count(),
                    'limit' => $account->package->max_databases ?? -1,
                ],
                'email_accounts' => [
                    'used' => $account->emailAccountsCount(),
                    'limit' => $account->package->max_email_accounts ?? -1,
                ],
                'ftp_accounts' => [
                    'used' => $account->ftpAccounts()->count(),
                    'limit' => $account->package->max_ftp_accounts ?? -1,
                ],
            ],
        ]);
    }
}
