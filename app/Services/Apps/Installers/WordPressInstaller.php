<?php

namespace App\Services\Apps\Installers;

use App\Services\Apps\AppInstallerInterface;
use App\Services\Database\MysqlService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class WordPressInstaller implements AppInstallerInterface
{
    protected MysqlService $mysql;
    protected string $wpCliPath;

    public function __construct(MysqlService $mysql)
    {
        $this->mysql = $mysql;
        $this->wpCliPath = config('freepanel.wp_cli_path', '/usr/local/bin/wp');
    }

    public function install(array $options): array
    {
        $path = $options['path'];
        $url = $options['url'];
        $db = $options['database'];
        $admin = $options['admin'];
        $siteName = $options['site_name'];
        $account = $options['account'];

        // Create directory
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        // Create database
        $this->mysql->createDatabase($db['name']);
        $this->mysql->createUser($db['user'], $db['password']);
        $this->mysql->grantPrivileges($db['user'], $db['name'], ['ALL PRIVILEGES']);

        // Download WordPress
        $this->runWpCli("core download --path={$path}");

        // Create wp-config.php
        $this->runWpCli(sprintf(
            "config create --path=%s --dbname=%s --dbuser=%s --dbpass=%s --dbhost=%s",
            escapeshellarg($path),
            escapeshellarg($db['name']),
            escapeshellarg($db['user']),
            escapeshellarg($db['password']),
            escapeshellarg($db['host'])
        ));

        // Install WordPress
        $this->runWpCli(sprintf(
            "core install --path=%s --url=%s --title=%s --admin_user=%s --admin_password=%s --admin_email=%s",
            escapeshellarg($path),
            escapeshellarg($url),
            escapeshellarg($siteName),
            escapeshellarg($admin['username']),
            escapeshellarg($admin['password']),
            escapeshellarg($admin['email'])
        ));

        // Set proper ownership
        chown($path, $account->uid);
        chgrp($path, $account->gid);
        $this->chownRecursive($path, $account->uid, $account->gid);

        // Set secure permissions
        $this->setSecurePermissions($path);

        return [
            'admin_url' => rtrim($url, '/') . '/wp-admin/',
            'version' => $this->getInstalledVersion($path),
        ];
    }

    public function update(string $path, array $options): array
    {
        // Update WordPress core
        $this->runWpCli("core update --path={$path}");

        // Update plugins
        $this->runWpCli("plugin update --all --path={$path}");

        // Update themes
        $this->runWpCli("theme update --all --path={$path}");

        // Update database if needed
        $this->runWpCli("core update-db --path={$path}");

        return [
            'version' => $this->getInstalledVersion($path),
        ];
    }

    public function uninstall(string $path, array $options): void
    {
        // Drop database if requested
        if ($options['delete_database'] ?? false) {
            $dbName = $options['database_name'] ?? null;

            if ($dbName) {
                $this->mysql->dropDatabase($dbName);
            }
        }

        // Delete files if requested
        if ($options['delete_files'] ?? true) {
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            }
        }
    }

    public function backup(string $path, string $backupPath): void
    {
        if (!File::isDirectory($backupPath)) {
            File::makeDirectory($backupPath, 0700, true);
        }

        // Backup files
        File::copyDirectory($path, "{$backupPath}/files");

        // Export database
        $dbName = $this->getDbName($path);
        if ($dbName) {
            $this->mysql->exportDump($dbName, "{$backupPath}/database.sql");
        }
    }

    public function getInstalledVersion(string $path): ?string
    {
        $versionFile = "{$path}/wp-includes/version.php";

        if (!File::exists($versionFile)) {
            return null;
        }

        $content = File::get($versionFile);
        preg_match("/\\\$wp_version\s*=\s*'([^']+)'/", $content, $matches);

        return $matches[1] ?? null;
    }

    public function cloneToStaging(string $productionPath, string $stagingPath, array $options): array
    {
        $productionUrl = $options['production_url'];
        $stagingUrl = $options['staging_url'];

        // Copy files
        File::copyDirectory($productionPath, $stagingPath);

        // Clone database
        $prodDbName = $this->getDbName($productionPath);
        $stagingDbName = ($options['database_prefix'] ?? 'stg_') . $prodDbName;

        // Create staging database
        $this->mysql->createDatabase($stagingDbName);

        // Export and import
        $tempDump = "/tmp/staging_dump_{$stagingDbName}.sql";
        $this->mysql->exportDump($prodDbName, $tempDump);
        $this->mysql->importDump($stagingDbName, $tempDump);
        unlink($tempDump);

        // Update wp-config.php with new database
        $wpConfigPath = "{$stagingPath}/wp-config.php";
        $wpConfig = File::get($wpConfigPath);
        $wpConfig = preg_replace(
            "/define\s*\(\s*'DB_NAME'\s*,\s*'[^']+'\s*\)/",
            "define('DB_NAME', '{$stagingDbName}')",
            $wpConfig
        );
        File::put($wpConfigPath, $wpConfig);

        // Search and replace URLs
        $this->runWpCli(sprintf(
            "search-replace %s %s --path=%s --all-tables",
            escapeshellarg($productionUrl),
            escapeshellarg($stagingUrl),
            escapeshellarg($stagingPath)
        ));

        return [
            'staging_url' => $stagingUrl,
            'database' => $stagingDbName,
        ];
    }

    protected function runWpCli(string $command): string
    {
        $fullCommand = "{$this->wpCliPath} {$command} --allow-root 2>&1";
        $result = Process::run($fullCommand);

        if (!$result->successful()) {
            throw new \RuntimeException("WP-CLI command failed: " . $result->errorOutput());
        }

        return $result->output();
    }

    protected function getDbName(string $path): ?string
    {
        $wpConfigPath = "{$path}/wp-config.php";

        if (!File::exists($wpConfigPath)) {
            return null;
        }

        $content = File::get($wpConfigPath);
        preg_match("/define\s*\(\s*'DB_NAME'\s*,\s*'([^']+)'\s*\)/", $content, $matches);

        return $matches[1] ?? null;
    }

    protected function setSecurePermissions(string $path): void
    {
        // Set directory permissions
        Process::run("find {$path} -type d -exec chmod 755 {} \\;");

        // Set file permissions
        Process::run("find {$path} -type f -exec chmod 644 {} \\;");

        // Protect wp-config.php
        chmod("{$path}/wp-config.php", 0600);
    }

    protected function chownRecursive(string $path, int $uid, int $gid): void
    {
        Process::run("chown -R {$uid}:{$gid} " . escapeshellarg($path));
    }
}
