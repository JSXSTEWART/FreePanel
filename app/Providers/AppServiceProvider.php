<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Services\WebServer\Contracts\WebServerInterface;
use App\Services\WebServer\ApacheService;
use App\Services\WebServer\NginxService;
use App\Services\Dns\Contracts\DnsProviderInterface;
use App\Services\Dns\BindService;
use App\Services\Email\Contracts\SmtpProviderInterface;
use App\Services\Email\EximService;
use App\Services\Email\Contracts\ImapProviderInterface;
use App\Services\Email\DovecotService;

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
        $this->app->bind(DnsProviderInterface::class, function ($app) {
            return match (config('freepanel.dns_server')) {
                'powerdns' => new PowerDnsService(),
                default => new BindService(),
            };
        });

        // Bind Email providers
        $this->app->bind(SmtpProviderInterface::class, function ($app) {
            return match (config('freepanel.mail_server')) {
                'postfix' => new PostfixService(),
                default => new EximService(),
            };
        });

        $this->app->bind(ImapProviderInterface::class, function ($app) {
            return new DovecotService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
