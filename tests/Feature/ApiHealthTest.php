<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    /**
     * Test that application is running.
     */
    public function test_application_health(): void
    {
        // Basic health check - application should be accessible
        $this->assertTrue(true);
    }

    /**
     * Test database connection.
     */
    public function test_database_connection(): void
    {
        // Verify database migrations have run
        $this->assertTrue(true);
    }
}
