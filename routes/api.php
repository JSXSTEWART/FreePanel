<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;

use App\Http\Controllers\Api\V1\User\DomainController;
use App\Http\Controllers\Api\V1\User\DnsController;
use App\Http\Controllers\Api\V1\User\EmailController;
use App\Http\Controllers\Api\V1\User\DatabaseController;
use App\Http\Controllers\Api\V1\User\FileController;
use App\Http\Controllers\Api\V1\User\FtpController;
use App\Http\Controllers\Api\V1\User\SslController;
use App\Http\Controllers\Api\V1\User\AppInstallerController;
use App\Http\Controllers\Api\V1\User\BackupController;
use App\Http\Controllers\Api\V1\User\StatsController;
use App\Http\Controllers\Api\V1\User\CronController;
use App\Http\Controllers\Api\V1\User\SecurityController;
use App\Http\Controllers\Api\V1\User\ErrorPageController;
use App\Http\Controllers\Api\V1\User\RedirectController;
use App\Http\Controllers\Api\V1\User\PhpConfigController;
use App\Http\Controllers\Api\V1\User\EmailFilterController;
use App\Http\Controllers\Api\V1\User\GitController;
use App\Http\Controllers\Api\V1\User\ApplicationController;
use App\Http\Controllers\Api\V1\User\MimeTypeController;
use App\Http\Controllers\Api\V1\User\TerminalController;
use App\Http\Controllers\Api\V1\User\RemoteMysqlController;
use App\Http\Controllers\Api\V1\User\DiskUsageController;
use App\Http\Controllers\Api\V1\User\AccessLogController;

use App\Http\Controllers\Api\V1\Admin\AccountController;
use App\Http\Controllers\Api\V1\Admin\PackageController;
use App\Http\Controllers\Api\V1\Admin\ResellerController;
use App\Http\Controllers\Api\V1\Admin\ServiceController;
use App\Http\Controllers\Api\V1\Admin\ServerController;
use App\Http\Controllers\Api\V1\Admin\FirewallController;
use App\Http\Controllers\Api\V1\Admin\BackupScheduleController;
use App\Http\Controllers\Api\V1\Admin\SshController;
use App\Http\Controllers\Api\V1\Admin\MailQueueController;
use App\Http\Controllers\Api\V1\Admin\ModSecurityController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Authentication Routes (Public)
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:auth');
    Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/refresh', [LoginController::class, 'refresh'])->middleware('auth:sanctum');
    Route::get('/me', [LoginController::class, 'me'])->middleware('auth:sanctum');

    // Two-Factor Authentication
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->middleware('auth:sanctum');
    Route::post('/2fa/enable', [TwoFactorController::class, 'enable'])->middleware('auth:sanctum');
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->middleware('auth:sanctum');
    Route::get('/2fa/qrcode', [TwoFactorController::class, 'qrcode'])->middleware('auth:sanctum');

    // Password Reset
    Route::post('/password/forgot', [LoginController::class, 'forgotPassword'])->middleware('throttle:auth');
    Route::post('/password/reset', [LoginController::class, 'resetPassword'])->middleware('throttle:auth');
});

// Protected API Routes
Route::prefix('v1')->middleware(['auth:sanctum', 'audit'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | User API Routes (UAPI equivalent)
    |--------------------------------------------------------------------------
    */

    // Domains
    Route::apiResource('domains', DomainController::class);
    Route::post('domains/{domain}/redirects', [DomainController::class, 'addRedirect']);
    Route::delete('domains/{domain}/redirects/{redirect}', [DomainController::class, 'removeRedirect']);

    // Subdomains
    Route::apiResource('subdomains', DomainController::class . '@subdomains');

    // DNS
    Route::prefix('dns/zones/{domain}')->group(function () {
        Route::get('/', [DnsController::class, 'show']);
        Route::get('/records', [DnsController::class, 'records']);
        Route::post('/records', [DnsController::class, 'addRecord'])->middleware('quota:dns_records');
        Route::put('/records/{record}', [DnsController::class, 'updateRecord']);
        Route::delete('/records/{record}', [DnsController::class, 'deleteRecord']);
        Route::post('/reset', [DnsController::class, 'resetZone']);
        Route::get('/export', [DnsController::class, 'exportZone']);
    });

    // Email
    Route::prefix('email')->group(function () {
        Route::apiResource('accounts', EmailController::class)->middleware('quota:email_accounts');
        Route::put('accounts/{account}/password', [EmailController::class, 'changePassword']);
        Route::put('accounts/{account}/quota', [EmailController::class, 'updateQuota']);
        Route::get('accounts/{account}/usage', [EmailController::class, 'usage']);

        Route::apiResource('forwarders', EmailController::class . '@forwarders');
        Route::apiResource('autoresponders', EmailController::class . '@autoresponders');
    });

    // Databases
    Route::prefix('databases')->group(function () {
        Route::apiResource('/', DatabaseController::class)->middleware('quota:databases');
        Route::get('/{database}/size', [DatabaseController::class, 'size']);
        Route::post('/{database}/grant', [DatabaseController::class, 'grant']);
        Route::post('/{database}/revoke', [DatabaseController::class, 'revoke']);

        Route::apiResource('users', DatabaseController::class . '@users');
        Route::put('users/{user}/password', [DatabaseController::class, 'changeUserPassword']);
    });

    // File Manager
    Route::prefix('files')->group(function () {
        Route::get('/list', [FileController::class, 'list']);
        Route::post('/upload', [FileController::class, 'upload']);
        Route::get('/download', [FileController::class, 'download']);
        Route::post('/mkdir', [FileController::class, 'mkdir']);
        Route::post('/copy', [FileController::class, 'copy']);
        Route::post('/move', [FileController::class, 'move']);
        Route::delete('/delete', [FileController::class, 'delete']);
        Route::get('/read', [FileController::class, 'read']);
        Route::put('/write', [FileController::class, 'write']);
        Route::post('/permissions', [FileController::class, 'permissions']);
        Route::post('/compress', [FileController::class, 'compress']);
        Route::post('/extract', [FileController::class, 'extract']);
        Route::get('/search', [FileController::class, 'search']);
        Route::get('/quota', [FileController::class, 'quota']);
    });

    // FTP
    Route::apiResource('ftp/accounts', FtpController::class)->middleware('quota:ftp_accounts');
    Route::put('ftp/accounts/{account}/password', [FtpController::class, 'changePassword']);

    // SSL
    Route::prefix('ssl')->group(function () {
        Route::apiResource('certificates', SslController::class);
        Route::post('/generate-csr', [SslController::class, 'generateCsr']);
        Route::post('/lets-encrypt', [SslController::class, 'letsEncrypt']);
        Route::get('/auto-ssl/status', [SslController::class, 'autoSslStatus']);
    });

    // Application Installer
    Route::prefix('apps')->group(function () {
        Route::get('/available', [AppInstallerController::class, 'available']);
        Route::get('/installed', [AppInstallerController::class, 'installed']);
        Route::post('/install', [AppInstallerController::class, 'install']);
        Route::delete('/{app}', [AppInstallerController::class, 'uninstall']);
        Route::post('/{app}/update', [AppInstallerController::class, 'update']);
        Route::post('/{app}/staging', [AppInstallerController::class, 'createStaging']);
    });

    // Backups
    Route::prefix('backups')->group(function () {
        Route::get('/', [BackupController::class, 'index']);
        Route::post('/', [BackupController::class, 'create']);
        Route::get('/{backup}', [BackupController::class, 'show']);
        Route::get('/{backup}/download', [BackupController::class, 'download']);
        Route::post('/{backup}/restore', [BackupController::class, 'restore']);
        Route::delete('/{backup}', [BackupController::class, 'destroy']);
    });

    // Statistics
    Route::prefix('stats')->group(function () {
        Route::get('/bandwidth', [StatsController::class, 'bandwidth']);
        Route::get('/visitors', [StatsController::class, 'visitors']);
        Route::get('/errors', [StatsController::class, 'errors']);
        Route::get('/resource-usage', [StatsController::class, 'resourceUsage']);
    });

    // Cron Jobs
    Route::prefix('cron')->group(function () {
        Route::get('/', [CronController::class, 'index']);
        Route::post('/', [CronController::class, 'store']);
        Route::get('/presets', [CronController::class, 'presets']);
        Route::get('/crontab', [CronController::class, 'crontab']);
        Route::get('/{cronJob}', [CronController::class, 'show']);
        Route::put('/{cronJob}', [CronController::class, 'update']);
        Route::delete('/{cronJob}', [CronController::class, 'destroy']);
        Route::post('/{cronJob}/toggle', [CronController::class, 'toggle']);
    });

    // Security - IP Blocker
    Route::prefix('security')->group(function () {
        Route::get('/blocked-ips', [SecurityController::class, 'blockedIps']);
        Route::post('/blocked-ips', [SecurityController::class, 'blockIp']);
        Route::delete('/blocked-ips/{blockedIp}', [SecurityController::class, 'unblockIp']);

        // Hotlink Protection
        Route::get('/hotlink-protection', [SecurityController::class, 'getHotlinkProtection']);
        Route::post('/hotlink-protection', [SecurityController::class, 'updateHotlinkProtection']);

        // Directory Protection
        Route::get('/protected-directories', [SecurityController::class, 'getProtectedDirectories']);
        Route::post('/protected-directories', [SecurityController::class, 'protectDirectory']);
        Route::delete('/protected-directories/{protectedDirectory}', [SecurityController::class, 'unprotectDirectory']);
        Route::post('/protected-directories/{protectedDirectory}/users', [SecurityController::class, 'addDirectoryUser']);
        Route::delete('/protected-directories/{protectedDirectory}/users/{user}', [SecurityController::class, 'removeDirectoryUser']);
    });

    // Custom Error Pages
    Route::prefix('error-pages')->group(function () {
        Route::get('/', [ErrorPageController::class, 'index']);
        Route::get('/codes', [ErrorPageController::class, 'codes']);
        Route::get('/{errorPage}', [ErrorPageController::class, 'show']);
        Route::post('/', [ErrorPageController::class, 'store']);
        Route::put('/{errorPage}', [ErrorPageController::class, 'update']);
        Route::delete('/{errorPage}', [ErrorPageController::class, 'destroy']);
        Route::post('/{errorPage}/toggle', [ErrorPageController::class, 'toggle']);
    });

    // Redirects
    Route::prefix('redirects')->group(function () {
        Route::get('/', [RedirectController::class, 'index']);
        Route::post('/', [RedirectController::class, 'store']);
        Route::get('/{redirect}', [RedirectController::class, 'show']);
        Route::put('/{redirect}', [RedirectController::class, 'update']);
        Route::delete('/{redirect}', [RedirectController::class, 'destroy']);
        Route::post('/{redirect}/toggle', [RedirectController::class, 'toggle']);
    });

    // PHP Configuration
    Route::prefix('php')->group(function () {
        Route::get('/config', [PhpConfigController::class, 'index']);
        Route::put('/config', [PhpConfigController::class, 'update']);
        Route::get('/versions', [PhpConfigController::class, 'versions']);
        Route::put('/version', [PhpConfigController::class, 'setVersion']);
        Route::get('/info', [PhpConfigController::class, 'info']);
        Route::get('/extensions', [PhpConfigController::class, 'extensions']);
    });

    // Email Filters
    Route::prefix('email-filters')->group(function () {
        Route::get('/', [EmailFilterController::class, 'index']);
        Route::post('/', [EmailFilterController::class, 'store']);
        Route::get('/options', [EmailFilterController::class, 'options']);
        Route::get('/{emailFilter}', [EmailFilterController::class, 'show']);
        Route::put('/{emailFilter}', [EmailFilterController::class, 'update']);
        Route::delete('/{emailFilter}', [EmailFilterController::class, 'destroy']);
        Route::post('/{emailFilter}/toggle', [EmailFilterController::class, 'toggle']);
        Route::post('/reorder', [EmailFilterController::class, 'reorder']);

        // Spam Settings
        Route::get('/spam/settings', [EmailFilterController::class, 'getSpamSettings']);
        Route::put('/spam/settings', [EmailFilterController::class, 'updateSpamSettings']);
        Route::get('/spam/whitelist', [EmailFilterController::class, 'getWhitelist']);
        Route::post('/spam/whitelist', [EmailFilterController::class, 'addToWhitelist']);
        Route::delete('/spam/whitelist/{email}', [EmailFilterController::class, 'removeFromWhitelist']);
        Route::get('/spam/blacklist', [EmailFilterController::class, 'getBlacklist']);
        Route::post('/spam/blacklist', [EmailFilterController::class, 'addToBlacklist']);
        Route::delete('/spam/blacklist/{email}', [EmailFilterController::class, 'removeFromBlacklist']);
    });

    // Git Repositories
    Route::prefix('git')->group(function () {
        Route::get('/', [GitController::class, 'index']);
        Route::post('/', [GitController::class, 'store']);
        Route::post('/clone', [GitController::class, 'cloneRepo']);
        Route::get('/{gitRepository}', [GitController::class, 'show']);
        Route::put('/{gitRepository}', [GitController::class, 'update']);
        Route::delete('/{gitRepository}', [GitController::class, 'destroy']);
        Route::post('/{gitRepository}/pull', [GitController::class, 'pull']);
        Route::post('/{gitRepository}/deploy', [GitController::class, 'deploy']);
        Route::get('/{gitRepository}/deploy-logs', [GitController::class, 'deployLogs']);
        Route::get('/{gitRepository}/files', [GitController::class, 'files']);
        Route::get('/{gitRepository}/file', [GitController::class, 'fileContent']);
    });

    // Node.js/Python Applications
    Route::prefix('applications')->group(function () {
        Route::get('/', [ApplicationController::class, 'index']);
        Route::get('/runtimes', [ApplicationController::class, 'runtimes']);
        Route::post('/', [ApplicationController::class, 'store']);
        Route::get('/{application}', [ApplicationController::class, 'show']);
        Route::put('/{application}', [ApplicationController::class, 'update']);
        Route::delete('/{application}', [ApplicationController::class, 'destroy']);
        Route::post('/{application}/start', [ApplicationController::class, 'start']);
        Route::post('/{application}/stop', [ApplicationController::class, 'stop']);
        Route::post('/{application}/restart', [ApplicationController::class, 'restart']);
        Route::get('/{application}/logs', [ApplicationController::class, 'logs']);
        Route::get('/{application}/metrics', [ApplicationController::class, 'metrics']);
        Route::put('/{application}/env', [ApplicationController::class, 'updateEnv']);
    });

    // MIME Types & Apache Handlers
    Route::prefix('mime-types')->group(function () {
        Route::get('/', [MimeTypeController::class, 'index']);
        Route::post('/', [MimeTypeController::class, 'store']);
        Route::put('/{mimeType}', [MimeTypeController::class, 'update']);
        Route::delete('/{mimeType}', [MimeTypeController::class, 'destroy']);
        Route::get('/handlers', [MimeTypeController::class, 'handlers']);
        Route::post('/handlers', [MimeTypeController::class, 'addHandler']);
        Route::delete('/handlers', [MimeTypeController::class, 'removeHandler']);
        Route::get('/indexes', [MimeTypeController::class, 'indexes']);
        Route::put('/indexes', [MimeTypeController::class, 'updateIndexes']);
    });

    // Web Terminal
    Route::prefix('terminal')->group(function () {
        Route::post('/session', [TerminalController::class, 'createSession']);
        Route::post('/execute', [TerminalController::class, 'execute']);
        Route::post('/cd', [TerminalController::class, 'cd']);
        Route::get('/history', [TerminalController::class, 'history']);
        Route::delete('/session', [TerminalController::class, 'closeSession']);
        Route::post('/complete', [TerminalController::class, 'complete']);
    });

    // Remote MySQL
    Route::prefix('remote-mysql')->group(function () {
        Route::get('/', [RemoteMysqlController::class, 'index']);
        Route::post('/', [RemoteMysqlController::class, 'store']);
        Route::delete('/', [RemoteMysqlController::class, 'destroy']);
        Route::post('/test', [RemoteMysqlController::class, 'test']);
    });

    // Disk Usage
    Route::prefix('disk-usage')->group(function () {
        Route::get('/', [DiskUsageController::class, 'index']);
        Route::get('/directory', [DiskUsageController::class, 'directory']);
        Route::get('/databases', [DiskUsageController::class, 'databases']);
        Route::get('/emails', [DiskUsageController::class, 'emails']);
        Route::get('/largest-files', [DiskUsageController::class, 'largestFiles']);
        Route::get('/by-type', [DiskUsageController::class, 'byType']);
    });

    // Access Logs
    Route::prefix('access-logs')->group(function () {
        Route::get('/', [AccessLogController::class, 'index']);
        Route::get('/view', [AccessLogController::class, 'view']);
        Route::get('/download', [AccessLogController::class, 'download']);
        Route::get('/search', [AccessLogController::class, 'search']);
        Route::get('/stats', [AccessLogController::class, 'stats']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin API Routes (WHM equivalent)
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->middleware('role:admin,reseller')->group(function () {

        // Accounts
        Route::apiResource('accounts', AccountController::class);
        Route::post('accounts/{account}/suspend', [AccountController::class, 'suspend']);
        Route::post('accounts/{account}/unsuspend', [AccountController::class, 'unsuspend']);
        Route::post('accounts/{account}/change-package', [AccountController::class, 'changePackage']);
        Route::get('accounts/{account}/usage', [AccountController::class, 'usage']);

        // Packages
        Route::apiResource('packages', PackageController::class)->middleware('role:admin');

        // Resellers (Admin only)
        Route::middleware('role:admin')->group(function () {
            Route::apiResource('resellers', ResellerController::class);
            Route::get('resellers/{reseller}/accounts', [ResellerController::class, 'accounts']);
        });

        // Services (Admin only)
        Route::prefix('services')->middleware('role:admin')->group(function () {
            Route::get('/', [ServiceController::class, 'index']);
            Route::get('/{service}/status', [ServiceController::class, 'status']);
            Route::post('/{service}/start', [ServiceController::class, 'start']);
            Route::post('/{service}/stop', [ServiceController::class, 'stop']);
            Route::post('/{service}/restart', [ServiceController::class, 'restart']);
        });

        // Server Info (Admin only)
        Route::prefix('server')->middleware('role:admin')->group(function () {
            Route::get('/info', [ServerController::class, 'info']);
            Route::get('/load', [ServerController::class, 'load']);
            Route::get('/disk', [ServerController::class, 'disk']);
            Route::get('/processes', [ServerController::class, 'processes']);
            Route::get('/ips', [ServerController::class, 'ips']);
        });

        // DNS (Admin)
        Route::prefix('dns')->middleware('role:admin')->group(function () {
            Route::get('/zones', [DnsController::class, 'allZones']);
            Route::post('/zones/{domain}/sync', [DnsController::class, 'syncZone']);
        });

        // Firewall (Admin only)
        Route::prefix('firewall')->middleware('role:admin')->group(function () {
            Route::get('/', [FirewallController::class, 'index']);
            Route::post('/enable', [FirewallController::class, 'enable']);
            Route::post('/disable', [FirewallController::class, 'disable']);
            Route::post('/rules', [FirewallController::class, 'addRule']);
            Route::delete('/rules/{ruleNumber}', [FirewallController::class, 'deleteRule']);
            Route::post('/services/allow', [FirewallController::class, 'allowService']);
            Route::get('/blocked-ips', [FirewallController::class, 'getBlockedIps']);
            Route::post('/unban', [FirewallController::class, 'unbanIp']);
        });

        // Backup Schedules (Admin only)
        Route::prefix('backup-schedules')->middleware('role:admin')->group(function () {
            Route::get('/', [BackupScheduleController::class, 'index']);
            Route::post('/', [BackupScheduleController::class, 'store']);
            Route::get('/statistics', [BackupScheduleController::class, 'statistics']);
            Route::get('/backups', [BackupScheduleController::class, 'listBackups']);
            Route::get('/{backupSchedule}', [BackupScheduleController::class, 'show']);
            Route::put('/{backupSchedule}', [BackupScheduleController::class, 'update']);
            Route::delete('/{backupSchedule}', [BackupScheduleController::class, 'destroy']);
            Route::post('/{backupSchedule}/toggle', [BackupScheduleController::class, 'toggle']);
            Route::post('/{backupSchedule}/run', [BackupScheduleController::class, 'runNow']);
            Route::post('/restore', [BackupScheduleController::class, 'restore']);
            Route::delete('/backups', [BackupScheduleController::class, 'deleteBackup']);
        });

        // SSH Access Manager (Admin only)
        Route::prefix('ssh')->middleware('role:admin')->group(function () {
            Route::get('/keys', [SshController::class, 'index']);
            Route::get('/settings', [SshController::class, 'settings']);
            Route::put('/settings', [SshController::class, 'updateSettings']);
            Route::post('/accounts/{account}/enable', [SshController::class, 'enableSshAccess']);
            Route::post('/accounts/{account}/disable', [SshController::class, 'disableSshAccess']);
            Route::post('/accounts/{account}/keys', [SshController::class, 'addKey']);
            Route::delete('/keys/{sshKey}', [SshController::class, 'removeKey']);
            Route::post('/keys/{sshKey}/toggle', [SshController::class, 'toggleKey']);
            Route::get('/logs', [SshController::class, 'logs']);
            Route::get('/sessions', [SshController::class, 'sessions']);
            Route::post('/sessions/terminate', [SshController::class, 'terminateSession']);
        });

        // Mail Queue Manager (Admin only)
        Route::prefix('mail-queue')->middleware('role:admin')->group(function () {
            Route::get('/', [MailQueueController::class, 'index']);
            Route::get('/search', [MailQueueController::class, 'search']);
            Route::get('/by-sender', [MailQueueController::class, 'bySender']);
            Route::get('/logs', [MailQueueController::class, 'logs']);
            Route::get('/{queueId}', [MailQueueController::class, 'show']);
            Route::delete('/{queueId}', [MailQueueController::class, 'destroy']);
            Route::post('/bulk-delete', [MailQueueController::class, 'bulkDelete']);
            Route::post('/flush', [MailQueueController::class, 'flush']);
            Route::post('/purge', [MailQueueController::class, 'purge']);
            Route::post('/{queueId}/hold', [MailQueueController::class, 'hold']);
            Route::post('/{queueId}/release', [MailQueueController::class, 'release']);
            Route::post('/{queueId}/requeue', [MailQueueController::class, 'requeue']);
        });

        // ModSecurity/WAF (Admin only)
        Route::prefix('modsecurity')->middleware('role:admin')->group(function () {
            Route::get('/', [ModSecurityController::class, 'index']);
            Route::post('/enable', [ModSecurityController::class, 'enable']);
            Route::post('/disable', [ModSecurityController::class, 'disable']);
            Route::post('/detection-only', [ModSecurityController::class, 'detectionOnly']);
            Route::put('/config', [ModSecurityController::class, 'updateConfiguration']);
            Route::get('/audit-log', [ModSecurityController::class, 'auditLog']);
            Route::get('/blocked-requests', [ModSecurityController::class, 'blockedRequests']);
            Route::get('/exclusions', [ModSecurityController::class, 'getExclusions']);
            Route::post('/exclusions', [ModSecurityController::class, 'addExclusion']);
            Route::delete('/exclusions', [ModSecurityController::class, 'removeExclusion']);
            Route::post('/install-owasp-crs', [ModSecurityController::class, 'installOwaspCrs']);
        });
    });
});
