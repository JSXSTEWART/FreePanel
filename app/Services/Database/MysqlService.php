<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use PDO;

class MysqlService implements DatabaseInterface
{
    protected PDO $pdo;

    public function __construct()
    {
        // Use a separate connection for administrative tasks
        $this->pdo = DB::connection('mysql_admin')->getPdo();
    }

    public function createDatabase(string $name): void
    {
        $this->validateName($name);

        $quotedName = $this->quoteIdentifier($name);
        $this->pdo->exec("CREATE DATABASE IF NOT EXISTS {$quotedName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function dropDatabase(string $name): void
    {
        $this->validateName($name);

        $quotedName = $this->quoteIdentifier($name);
        $this->pdo->exec("DROP DATABASE IF EXISTS {$quotedName}");
    }

    public function databaseExists(string $name): bool
    {
        $this->validateName($name);

        $stmt = $this->pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$name]);

        return $stmt->fetch() !== false;
    }

    public function getDatabaseSize(string $name): int
    {
        $this->validateName($name);

        $stmt = $this->pdo->prepare("
            SELECT SUM(data_length + index_length) as size
            FROM information_schema.TABLES
            WHERE table_schema = ?
        ");
        $stmt->execute([$name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['size'] ?? 0);
    }

    public function getTableCount(string $name): int
    {
        $this->validateName($name);

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM information_schema.TABLES
            WHERE table_schema = ?
        ");
        $stmt->execute([$name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['count'] ?? 0);
    }

    public function createUser(string $username, string $password): void
    {
        $this->validateName($username);

        // Create user with localhost access
        $stmt = $this->pdo->prepare("CREATE USER IF NOT EXISTS ?@'localhost' IDENTIFIED BY ?");
        $stmt->execute([$username, $password]);

        // Also create for 127.0.0.1 access
        $stmt = $this->pdo->prepare("CREATE USER IF NOT EXISTS ?@'127.0.0.1' IDENTIFIED BY ?");
        $stmt->execute([$username, $password]);
    }

    public function dropUser(string $username): void
    {
        $this->validateName($username);

        $quotedUser = $this->quoteUsername($username);
        $this->pdo->exec("DROP USER IF EXISTS {$quotedUser}@'localhost'");
        $this->pdo->exec("DROP USER IF EXISTS {$quotedUser}@'127.0.0.1'");
    }

    public function changePassword(string $username, string $password): void
    {
        $this->validateName($username);

        $stmt = $this->pdo->prepare("ALTER USER ?@'localhost' IDENTIFIED BY ?");
        $stmt->execute([$username, $password]);

        $stmt = $this->pdo->prepare("ALTER USER ?@'127.0.0.1' IDENTIFIED BY ?");
        $stmt->execute([$username, $password]);

        $this->pdo->exec("FLUSH PRIVILEGES");
    }

    public function grantPrivileges(string $username, string $database, array $privileges): void
    {
        $this->validateName($username);
        $this->validateName($database);

        // Map common privilege names
        $validPrivileges = [
            'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP',
            'INDEX', 'ALTER', 'CREATE TEMPORARY TABLES', 'LOCK TABLES',
            'EXECUTE', 'CREATE VIEW', 'SHOW VIEW', 'CREATE ROUTINE',
            'ALTER ROUTINE', 'EVENT', 'TRIGGER', 'ALL PRIVILEGES'
        ];

        $grantPrivileges = [];
        foreach ($privileges as $priv) {
            $priv = strtoupper($priv);
            if (in_array($priv, $validPrivileges)) {
                $grantPrivileges[] = $priv;
            }
        }

        if (empty($grantPrivileges)) {
            throw new \InvalidArgumentException('No valid privileges specified');
        }

        $privilegeStr = implode(', ', $grantPrivileges);

        $quotedDb = $this->quoteIdentifier($database);
        $quotedUser = $this->quoteUsername($username);
        $this->pdo->exec("GRANT {$privilegeStr} ON {$quotedDb}.* TO {$quotedUser}@'localhost'");
        $this->pdo->exec("GRANT {$privilegeStr} ON {$quotedDb}.* TO {$quotedUser}@'127.0.0.1'");
        $this->pdo->exec("FLUSH PRIVILEGES");
    }

    public function revokePrivileges(string $username, string $database): void
    {
        $this->validateName($username);
        $this->validateName($database);

        $quotedDb = $this->quoteIdentifier($database);
        $quotedUser = $this->quoteUsername($username);
        $this->pdo->exec("REVOKE ALL PRIVILEGES ON {$quotedDb}.* FROM {$quotedUser}@'localhost'");
        $this->pdo->exec("REVOKE ALL PRIVILEGES ON {$quotedDb}.* FROM {$quotedUser}@'127.0.0.1'");
        $this->pdo->exec("FLUSH PRIVILEGES");
    }

    public function getPrivileges(string $username, string $database): array
    {
        $this->validateName($username);
        $this->validateName($database);

        $stmt = $this->pdo->prepare("SHOW GRANTS FOR ?@'localhost'");
        $stmt->execute([$username]);

        $privileges = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $grant = $row[0];
            if (strpos($grant, "`{$database}`") !== false || strpos($grant, '*.*') !== false) {
                // Parse privileges from GRANT statement
                preg_match('/GRANT (.+) ON/', $grant, $matches);
                if (isset($matches[1])) {
                    $privs = array_map('trim', explode(',', $matches[1]));
                    $privileges = array_merge($privileges, $privs);
                }
            }
        }

        return array_unique($privileges);
    }

    public function importDump(string $database, string $dumpFile): void
    {
        $this->validateName($database);

        if (!file_exists($dumpFile)) {
            throw new \InvalidArgumentException('Dump file does not exist');
        }

        $config = config('database.connections.mysql_admin');

        $command = sprintf(
            'mysql -h %s -u %s -p%s %s < %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($database),
            escapeshellarg($dumpFile)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to import database dump');
        }
    }

    public function exportDump(string $database, string $dumpFile): void
    {
        $this->validateName($database);

        $config = config('database.connections.mysql_admin');

        $command = sprintf(
            'mysqldump -h %s -u %s -p%s %s > %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($database),
            escapeshellarg($dumpFile)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to export database dump');
        }
    }

    protected function validateName(string $name): void
    {
        // Prevent SQL injection by validating name format
        if (!preg_match('/^[a-z][a-z0-9_]*$/i', $name)) {
            throw new \InvalidArgumentException('Invalid database/user name format');
        }

        if (strlen($name) > 64) {
            throw new \InvalidArgumentException('Name exceeds maximum length');
        }
    }

    /**
     * Quote a MySQL identifier (database name, table name) with backticks.
     * Escapes any backticks within the identifier to prevent SQL injection.
     */
    protected function quoteIdentifier(string $identifier): string
    {
        // Escape backticks by doubling them (MySQL standard)
        $escaped = str_replace('`', '``', $identifier);
        return "`{$escaped}`";
    }

    /**
     * Quote a MySQL username with single quotes.
     * Escapes any single quotes within the username to prevent SQL injection.
     */
    protected function quoteUsername(string $username): string
    {
        // Escape single quotes by doubling them (MySQL standard)
        $escaped = str_replace("'", "''", $username);
        return "'{$escaped}'";
    }
}
