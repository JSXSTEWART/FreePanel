<?php

namespace App\Services\Apps\Installers;

use App\Services\Apps\AppInstallerInterface;
use App\Services\Database\MysqlService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * phpBB Forum Installer
 *
 * TODO: Complete implementation for phpBB installation
 *
 * phpBB Installation Overview:
 * 1. Download phpBB from https://www.phpbb.com/
 * 2. Extract to target directory
 * 3. Create database and user
 * 4. Run phpBB CLI installer
 * 5. Remove install directory
 * 6. Set proper file permissions
 *
 * phpBB CLI Installation (3.3+):
 * php install/phpbbcli.php install config.yml
 *
 * References:
 * - https://www.phpbb.com/support/docs/en/3.3/ug/quickstart/
 * - https://github.com/phpbb/phpbb
 */
class PhpBBInstaller implements AppInstallerInterface
{
    protected MysqlService $mysql;

    public function __construct(MysqlService $mysql)
    {
        $this->mysql = $mysql;
    }

    public function install(array $options): array
    {
        $path = $options['path'];
        $url = $options['url'];
        $db = $options['database'];
        $admin = $options['admin'];
        $siteName = $options['site_name'];
        $account = $options['account'];

        // TODO: Implement phpBB installation
        //
        // Step 1: Create directory
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        // Step 2: Create database
        $this->mysql->createDatabase($db['name']);
        $this->mysql->createUser($db['user'], $db['password']);
        $this->mysql->grantPrivileges($db['user'], $db['name'], ['ALL PRIVILEGES']);

        // Step 3: Download phpBB
        // TODO: Implement download logic
        // $version = config('freepanel.applications.phpbb.version', '3.3.10');
        // $downloadUrl = "https://download.phpbb.com/pub/release/3.3/{$version}/phpBB-{$version}.zip";
        // $tempFile = "/tmp/phpbb_{$version}.zip";
        // Http::sink($tempFile)->get($downloadUrl);
        // Process::run("unzip -q {$tempFile} -d /tmp/phpbb_extract");
        // // phpBB extracts to phpBB3 directory
        // Process::run("mv /tmp/phpbb_extract/phpBB3/* {$path}/");
        // Process::run("mv /tmp/phpbb_extract/phpBB3/.[!.]* {$path}/ 2>/dev/null || true");
        // File::deleteDirectory('/tmp/phpbb_extract');
        // unlink($tempFile);

        // Step 4: Create install config YAML for CLI installation
        // TODO: Generate config.yml for phpBB CLI installer
        // $configYaml = $this->generateInstallConfig($db, $admin, $siteName, $url);
        // File::put("{$path}/install/config.yml", $configYaml);

        // Step 5: Run CLI installer
        // TODO: Execute CLI installation
        // Process::run("php {$path}/install/phpbbcli.php install {$path}/install/config.yml");

        // Step 6: Remove install directory (SECURITY CRITICAL)
        // File::deleteDirectory("{$path}/install");

        // Step 7: Set ownership and permissions
        $this->setOwnership($path, $account->uid, $account->gid);
        $this->setSecurePermissions($path);

        throw new \RuntimeException('phpBB installer not yet implemented. See TODO comments in PhpBBInstaller.php');

        return [
            'admin_url' => rtrim($url, '/') . '/adm/',
            'version' => $this->getInstalledVersion($path),
        ];
    }

    public function update(string $path, array $options): array
    {
        // TODO: Implement phpBB update
        // phpBB updates can be done via:
        // 1. Automatic update through ACP
        // 2. Manual update by downloading update package
        //
        // CLI update:
        // php bin/phpbbcli.php update

        throw new \RuntimeException('phpBB updater not yet implemented');

        return [
            'version' => $this->getInstalledVersion($path),
        ];
    }

    public function uninstall(string $path, array $options): void
    {
        if ($options['delete_database'] ?? false) {
            $dbName = $options['database_name'] ?? null;
            if ($dbName) {
                $this->mysql->dropDatabase($dbName);
            }
        }

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

        File::copyDirectory($path, "{$backupPath}/files");

        $dbName = $this->getDbName($path);
        if ($dbName) {
            $this->mysql->exportDump($dbName, "{$backupPath}/database.sql");
        }
    }

    public function getInstalledVersion(string $path): ?string
    {
        // phpBB stores version in includes/constants.php
        $versionFile = "{$path}/includes/constants.php";

        if (File::exists($versionFile)) {
            $content = File::get($versionFile);
            if (preg_match("/define\s*\(\s*'PHPBB_VERSION'\s*,\s*'([^']+)'\s*\)/", $content, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public function cloneToStaging(string $productionPath, string $stagingPath, array $options): array
    {
        $productionUrl = $options['production_url'];
        $stagingUrl = $options['staging_url'];

        File::copyDirectory($productionPath, $stagingPath);

        $prodDbName = $this->getDbName($productionPath);
        $stagingDbName = ($options['database_prefix'] ?? 'stg_') . $prodDbName;

        $this->mysql->createDatabase($stagingDbName);

        $tempDump = "/tmp/staging_dump_{$stagingDbName}.sql";
        $this->mysql->exportDump($prodDbName, $tempDump);
        $this->mysql->importDump($stagingDbName, $tempDump);
        unlink($tempDump);

        // Update config.php with new database
        $configPath = "{$stagingPath}/config.php";
        if (File::exists($configPath)) {
            $config = File::get($configPath);
            $config = preg_replace(
                "/\\\$dbname\s*=\s*'[^']+'/",
                "\$dbname = '{$stagingDbName}'",
                $config
            );
            File::put($configPath, $config);
        }

        // Update board URL in database
        // TODO: Run SQL to update phpbb_config table
        // UPDATE phpbb_config SET config_value = 'staging_url' WHERE config_name = 'server_name';
        // UPDATE phpbb_config SET config_value = 'staging_url' WHERE config_name = 'script_path';

        return [
            'staging_url' => $stagingUrl,
            'database' => $stagingDbName,
        ];
    }

    protected function getDbName(string $path): ?string
    {
        $configPath = "{$path}/config.php";

        if (!File::exists($configPath)) {
            return null;
        }

        $content = File::get($configPath);
        if (preg_match("/\\\$dbname\s*=\s*'([^']+)'/", $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function setOwnership(string $path, int $uid, int $gid): void
    {
        Process::run("chown -R {$uid}:{$gid} " . escapeshellarg($path));
    }

    protected function setSecurePermissions(string $path): void
    {
        Process::run("find {$path} -type d -exec chmod 755 {} \\;");
        Process::run("find {$path} -type f -exec chmod 644 {} \\;");

        // Protect config.php
        if (File::exists("{$path}/config.php")) {
            chmod("{$path}/config.php", 0600);
        }

        // Make cache, files, store, images directories writable
        foreach (['cache', 'files', 'store', 'images/avatars/upload'] as $dir) {
            $dirPath = "{$path}/{$dir}";
            if (File::isDirectory($dirPath)) {
                Process::run("chmod -R 755 " . escapeshellarg($dirPath));
            }
        }
    }

    /**
     * Generate phpBB CLI installer config YAML
     *
     * TODO: Implement config generation
     */
    protected function generateInstallConfig(array $db, array $admin, string $siteName, string $url): string
    {
        // TODO: Generate proper phpBB install config
        // See: https://area51.phpbb.com/docs/dev/master/cli/install.html
        return '';
    }
}
