<?php

namespace App\Services\Database;

interface DatabaseInterface
{
    /**
     * Create a new database
     */
    public function createDatabase(string $name): void;

    /**
     * Drop a database
     */
    public function dropDatabase(string $name): void;

    /**
     * Check if database exists
     */
    public function databaseExists(string $name): bool;

    /**
     * Get database size in bytes
     */
    public function getDatabaseSize(string $name): int;

    /**
     * Get table count in database
     */
    public function getTableCount(string $name): int;

    /**
     * Create a database user
     */
    public function createUser(string $username, string $password): void;

    /**
     * Drop a database user
     */
    public function dropUser(string $username): void;

    /**
     * Change user password
     */
    public function changePassword(string $username, string $password): void;

    /**
     * Grant privileges to user on database
     */
    public function grantPrivileges(string $username, string $database, array $privileges): void;

    /**
     * Revoke all privileges from user on database
     */
    public function revokePrivileges(string $username, string $database): void;

    /**
     * Get user privileges on a database
     */
    public function getPrivileges(string $username, string $database): array;
}
