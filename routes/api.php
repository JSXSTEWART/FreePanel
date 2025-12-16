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

use App\Http\Controllers\Api\V1\Admin\AccountController;
use App\Http\Controllers\Api\V1\Admin\PackageController;
use App\Http\Controllers\Api\V1\Admin\ResellerController;
use App\Http\Controllers\Api\V1\Admin\ServiceController;
use App\Http\Controllers\Api\V1\Admin\ServerController;

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
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api', 'audit'])->group(function () {

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
    });
});
