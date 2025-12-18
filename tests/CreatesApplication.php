<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    public function createApplication()
    {
        $envPath = dirname(__DIR__) . '/.env.testing';

        if (!file_exists($envPath)) {
            file_put_contents($envPath, $this->defaultEnvironmentContent());
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->loadEnvironmentFrom('.env.testing');
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function resetTestingEnvironmentFile(): void
    {
        $envPath = dirname(__DIR__) . '/.env.testing';
        file_put_contents($envPath, $this->defaultEnvironmentContent());
    }

    protected function defaultEnvironmentContent(): string
    {
        return <<<ENV
APP_NAME=Laravel
APP_ENV=testing
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
APP_DEBUG=true
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=sqlite
DB_DATABASE=:memory:

BROADCAST_DRIVER=log
CACHE_DRIVER=array
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
SESSION_LIFETIME=120

SANCTUM_STATEFUL_DOMAINS=localhost
SANCTUM_TOKEN_EXPIRATION=1440
ENV;
    }
}
