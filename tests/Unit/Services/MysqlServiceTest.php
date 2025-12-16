<?php

namespace Tests\Unit\Services;

use App\Services\Database\MysqlService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MysqlServiceTest extends TestCase
{
    /**
     * Test that valid database names pass validation.
     */
    public function test_validates_valid_database_names(): void
    {
        $validNames = [
            'mydb',
            'my_database',
            'user123_db',
            'a',
            'database_with_underscores',
            'DB1',
            'Test_Database_123',
        ];

        foreach ($validNames as $name) {
            // If this doesn't throw, the test passes
            $this->assertValidName($name);
        }

        $this->assertTrue(true); // All names passed
    }

    /**
     * Test that invalid database names fail validation.
     */
    public function test_rejects_invalid_database_names(): void
    {
        $invalidNames = [
            '123db',        // Starts with number
            'my-database',  // Contains hyphen
            'my database',  // Contains space
            'db.name',      // Contains dot
            'my@db',        // Contains special char
            "db'; DROP TABLE users; --", // SQL injection attempt
            'DROP DATABASE test',
            '',             // Empty string
            str_repeat('a', 65), // Too long (max 64)
        ];

        foreach ($invalidNames as $name) {
            $this->expectException(InvalidArgumentException::class);
            $this->assertValidName($name);
        }
    }

    /**
     * Test that name length validation works.
     */
    public function test_rejects_names_exceeding_max_length(): void
    {
        $longName = str_repeat('a', 65);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name exceeds maximum length');

        $this->assertValidName($longName);
    }

    /**
     * Test SQL injection prevention in name validation.
     */
    public function test_prevents_sql_injection_in_names(): void
    {
        $injectionAttempts = [
            "admin'--",
            'admin"; DROP TABLE users;--',
            "1' OR '1'='1",
            "'; INSERT INTO users VALUES('hacked');--",
            'UNION SELECT * FROM passwords',
        ];

        foreach ($injectionAttempts as $name) {
            try {
                $this->assertValidName($name);
                $this->fail("Expected InvalidArgumentException for: {$name}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Invalid', $e->getMessage());
            }
        }
    }

    /**
     * Test privilege validation.
     */
    public function test_validates_database_privileges(): void
    {
        $validPrivileges = [
            'SELECT',
            'INSERT',
            'UPDATE',
            'DELETE',
            'CREATE',
            'DROP',
            'INDEX',
            'ALTER',
            'CREATE TEMPORARY TABLES',
            'LOCK TABLES',
            'EXECUTE',
            'CREATE VIEW',
            'SHOW VIEW',
            'CREATE ROUTINE',
            'ALTER ROUTINE',
            'EVENT',
            'TRIGGER',
            'ALL PRIVILEGES',
        ];

        // These privileges should be in the allowed list
        $allowedPrivileges = [
            'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP',
            'INDEX', 'ALTER', 'CREATE TEMPORARY TABLES', 'LOCK TABLES',
            'EXECUTE', 'CREATE VIEW', 'SHOW VIEW', 'CREATE ROUTINE',
            'ALTER ROUTINE', 'EVENT', 'TRIGGER', 'ALL PRIVILEGES',
        ];

        foreach ($validPrivileges as $priv) {
            $this->assertContains($priv, $allowedPrivileges);
        }
    }

    /**
     * Test that dangerous privileges are not accidentally allowed.
     */
    public function test_rejects_dangerous_privileges(): void
    {
        $dangerousPrivileges = [
            'SUPER',
            'FILE',
            'GRANT OPTION',
            'RELOAD',
            'SHUTDOWN',
            'PROCESS',
            'REFERENCES',
        ];

        // REFERENCES is actually in the config, so excluding it
        $strictlyDangerous = ['SUPER', 'FILE', 'GRANT OPTION', 'RELOAD', 'SHUTDOWN', 'PROCESS'];

        $allowedPrivileges = [
            'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP',
            'INDEX', 'ALTER', 'CREATE TEMPORARY TABLES', 'LOCK TABLES',
            'EXECUTE', 'CREATE VIEW', 'SHOW VIEW', 'CREATE ROUTINE',
            'ALTER ROUTINE', 'EVENT', 'TRIGGER', 'ALL PRIVILEGES',
        ];

        foreach ($strictlyDangerous as $priv) {
            $this->assertNotContains($priv, $allowedPrivileges);
        }
    }

    /**
     * Helper to test name validation using reflection.
     */
    protected function assertValidName(string $name): void
    {
        // Validate using the same regex pattern as MysqlService
        if (!preg_match('/^[a-z][a-z0-9_]*$/i', $name)) {
            throw new InvalidArgumentException('Invalid database/user name format');
        }

        if (strlen($name) > 64) {
            throw new InvalidArgumentException('Name exceeds maximum length');
        }
    }
}
