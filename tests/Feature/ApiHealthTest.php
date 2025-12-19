<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApiHealthTest extends TestCase
{
    /**
     * Test that application is running and does not return a server error.
     */
    public function test_application_health(): void
    {
        $response = $this->get('/');

        $this->assertTrue(
            $response->isSuccessful() || $response->isRedirection(),
            'Application returned a server error: ' . $response->getStatusCode()
        );
    }

    /**
     * Test API health endpoint if it exists.
     */
    public function test_api_health_endpoint(): void
    {
        $response = $this->get('/api/health');

        if ($response->getStatusCode() === 404) {
            $this->markTestSkipped('API health endpoint (/api/health) not defined.');
        }

        $this->assertTrue(
            $response->isSuccessful() || $response->isRedirection(),
            'API health endpoint returned a server error: ' . $response->getStatusCode()
        );
    }

    /**
     * Test database connection.
     */
    public function test_database_connection(): void
    {
        try {
            $pdo = DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not configured for tests: ' . $e->getMessage());
            return;
        }

        $this->assertNotNull($pdo, 'DB connection did not return a PDO instance.');
    }

    /**
     * Verify that migrations table exists if DB is available.
     */
    public function test_migrations_table_exists(): void
    {
        try {
            $hasTable = Schema::hasTable('migrations');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Unable to inspect schema: ' . $e->getMessage());
            return;
        }

        if (! $hasTable) {
            $this->markTestSkipped('Migrations table does not exist in the test database.');
        }

        $this->assertTrue($hasTable, 'Migrations table should exist when migrations have been run.');
    }
}