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
        $root = dirname(__DIR__, 2);
        $path = $root . '/.env.testing';
        $content = <<<'ENV'
APP_ENV=testing
APP_KEY=
APP_DEBUG=true
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
ENV;
        file_put_contents($path, $content);

        // Ensure storage directories exist for cache, sessions, and views
        $storage = $root . '/storage/framework';
        @mkdir($storage . '/cache', 0777, true);
        @mkdir($storage . '/sessions', 0777, true);
        @mkdir($storage . '/views', 0777, true);
    }
}
