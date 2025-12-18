<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Services\WebServer\WebServerInterface;
use App\Services\WebServer\ApacheService;
use App\Services\WebServer\NginxService;
use App\Services\Dns\DnsInterface;
use App\Services\Dns\BindService;
use App\Services\Dns\PowerDnsService;
use App\Services\Email\EmailInterface;
use App\Services\Email\EximService;
use App\Services\Email\DovecotService;
use App\Services\Ftp\FtpInterface;
use App\Services\Ftp\PureFtpdService;
use App\Services\Database\DatabaseInterface;
use App\Services\Database\MysqlService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind WebServer implementation based on config
        $this->app->bind(WebServerInterface::class, function ($app) {
            return match (config('freepanel.webserver')) {
                'nginx' => new NginxService(),
                default => new ApacheService(),
            };
        });

        // Bind DNS provider based on config
        $this->app->bind(DnsInterface::class, function ($app) {
            return match (config('freepanel.dns_server')) {
                'powerdns' => new PowerDnsService(),
                default => new BindService(),
            };
        });

        // Bind Email provider based on config
        $this->app->bind(EmailInterface::class, function ($app) {
            return match (config('freepanel.mail_server')) {
                'dovecot' => new DovecotService(),
                default => new EximService(),
            };
        });

        // Bind FTP provider
        $this->app->bind(FtpInterface::class, function ($app) {
            return new PureFtpdService();
        });

        // Bind Database provider
        $this->app->bind(DatabaseInterface::class, function ($app) {
            return new MysqlService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS only if FORCE_HTTPS env is set
        if (config('app.force_https', false)) {
            URL::forceScheme('https');
        }
    }
}
