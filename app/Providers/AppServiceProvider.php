<?php

namespace App\Providers;

use App\Services\Database\DatabaseInterface;
use App\Services\Database\MysqlService;
use App\Services\Dns\BindService;
use App\Services\Dns\DnsInterface;
use App\Services\Dns\PowerDnsService;
use App\Services\Email\DovecotService;
use App\Services\Email\EmailInterface;
use App\Services\Email\EximService;
use App\Services\Ftp\FtpInterface;
use App\Services\Ftp\PureFtpdService;
use App\Services\WebServer\ApacheService;
use App\Services\WebServer\NginxService;
use App\Services\WebServer\WebServerInterface;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
                'nginx' => new NginxService,
                default => new ApacheService,
            };
        });

        // Bind DNS provider based on config
        $this->app->bind(DnsInterface::class, function ($app) {
            return match (config('freepanel.dns_server')) {
                'powerdns' => new PowerDnsService,
                default => new BindService,
            };
        });

        // Bind Email provider based on config
        $this->app->bind(EmailInterface::class, function ($app) {
            return match (config('freepanel.mail_server')) {
                'dovecot' => new DovecotService,
                default => new EximService,
            };
        });

        // Bind FTP provider
        $this->app->bind(FtpInterface::class, function ($app) {
            return new PureFtpdService;
        });

        // Bind Database provider
        $this->app->bind(DatabaseInterface::class, function ($app) {
            return new MysqlService;
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

        $this->assertRedisPasswordInProduction();
    }

    /**
     * In production, refuse to boot with an unauthenticated Redis
     * connection. Session, cache, and queue data would otherwise be
     * exposed to anyone on the network. The literal string "null" is
     * also rejected because `.env` files that set `REDIS_PASSWORD=null`
     * are interpreted as the string "null", not as an empty password.
     */
    protected function assertRedisPasswordInProduction(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        // Only enforce when Redis is actually in use by a driver.
        $usesRedis = in_array('redis', [
            (string) config('cache.default'),
            (string) config('session.driver'),
            (string) config('queue.default'),
        ], true);

        if (! $usesRedis) {
            return;
        }

        $password = config('database.redis.default.password');

        if ($password === null || $password === '' || strtolower((string) $password) === 'null') {
            throw new \RuntimeException(
                'Refusing to boot: REDIS_PASSWORD is not set but a Redis driver is in use. '
                .'Set a strong password on the Redis server and mirror it in .env.'
            );
        }
    }
}
