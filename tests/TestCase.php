<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * PHPUnit setUp hook.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the minimal testing environment file exists
        $this->resetTestingEnvironmentFile();

        // Clear any compiled views or cached config that could interfere with tests
        if (method_exists($this, 'artisan')) {
            try {
                $this->artisan('config:clear');
                $this->artisan('view:clear');

                // Ensure migrations are run in the test environment so DB-backed features are available
                $this->artisan('migrate', ['--force' => true]);
            } catch (\Exception $e) {
                // Non-fatal during some environments
            }
        }
    }

    /**
     * Reset or create a minimal testing environment file used by tests.
     */
    protected function resetTestingEnvironmentFile(): void
    {
        $root = dirname(__DIR__);
        $path = $root.'/.env.testing';
        $content = <<<'ENV'
APP_ENV=testing
APP_KEY=
APP_DEBUG=true
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
ENV;
        file_put_contents($path, $content);

        // Ensure storage directories exist for cache, sessions, and views
        $storage = $root.'/storage/framework';
        @mkdir($storage.'/cache', 0777, true);
        @mkdir($storage.'/sessions', 0777, true);
        @mkdir($storage.'/views', 0777, true);

        // Make sure no stale setup-lock or install-token files from a prior
        // test run leak into this one. Tests that exercise /setup/initialize
        // call seedInstallToken() to write a fresh token.
        @unlink($root.'/storage/app/setup.lock');
        @unlink($root.'/storage/app/install.token');
    }

    /**
     * Write a fresh install token for tests that exercise the setup flow
     * and return its value for use in the request body.
     */
    protected function seedInstallToken(): string
    {
        $root = dirname(__DIR__);
        $dir = $root.'/storage/app';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $token = bin2hex(random_bytes(24));
        file_put_contents($dir.'/install.token', $token);
        @chmod($dir.'/install.token', 0600);

        return $token;
    }
}
