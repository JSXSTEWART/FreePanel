<?php

namespace App\Services\Apps\Installers;

use App\Services\Apps\AppInstallerInterface;
use App\Services\Database\MysqlService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Nextcloud File Sharing Platform Installer
 *
 * TODO: Complete implementation for Nextcloud installation
 *
 * Nextcloud Installation Overview:
 * 1. Download Nextcloud from https://nextcloud.com/install/
 * 2. Extract to target directory
 * 3. Create database and user
 * 4. Run Nextcloud OCC installer
 * 5. Configure recommended settings
 * 6. Set proper file permissions
 *
 * Nextcloud OCC Installation:
 * php occ maintenance:install \
 *   --database="mysql" --database-name="DB" \
 *   --database-user="USER" --database-pass="PASS" \
 *   --admin-user="admin" --admin-pass="password" \
 *   --data-dir="/path/to/data"
 *
 * References:
 * - https://docs.nextcloud.com/server/latest/admin_manual/installation/
 * - https://github.com/nextcloud/server
 */
class NextcloudInstaller implements AppInstallerInterface
{
    protected MysqlService $mysql;
    protected string $occCommand = 'php occ';

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

        // TODO: Implement Nextcloud installation
        //
        // Step 1: Create directory
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        // Step 2: Create database
        $this->mysql->createDatabase($db['name']);
        $this->mysql->createUser($db['user'], $db['password']);
        $this->mysql->grantPrivileges($db['user'], $db['name'], ['ALL PRIVILEGES']);

        // Step 3: Download Nextcloud
        // TODO: Implement download logic
        // $version = config('freepanel.applications.nextcloud.version', '27.1.0');
        // $downloadUrl = "https://download.nextcloud.com/server/releases/nextcloud-{$version}.zip";
        // $tempFile = "/tmp/nextcloud_{$version}.zip";
        // Http::sink($tempFile)->get($downloadUrl);
        // Process::run("unzip -q {$tempFile} -d /tmp/nextcloud_extract");
        // Process::run("mv /tmp/nextcloud_extract/nextcloud/* {$path}/");
        // Process::run("mv /tmp/nextcloud_extract/nextcloud/.[!.]* {$path}/ 2>/dev/null || true");
        // File::deleteDirectory('/tmp/nextcloud_extract');
        // unlink($tempFile);

        // Step 4: Create data directory outside webroot (security best practice)
        $dataDir = "/home/{$account->username}/nextcloud_data";
        // if (!File::isDirectory($dataDir)) {
        //     File::makeDirectory($dataDir, 0750, true);
        //     Process::run("chown {$account->uid}:{$account->gid} " . escapeshellarg($dataDir));
        // }

        // Step 5: Run OCC installer
        // TODO: Execute OCC installation
        // Process::path($path)->run(sprintf(
        //     'sudo -u %s php occ maintenance:install ' .
        //     '--database="mysql" ' .
        //     '--database-name=%s ' .
        //     '--database-host=%s ' .
        //     '--database-user=%s ' .
        //     '--database-pass=%s ' .
        //     '--admin-user=%s ' .
        //     '--admin-pass=%s ' .
        //     '--data-dir=%s',
        //     escapeshellarg($account->username),
        //     escapeshellarg($db['name']),
        //     escapeshellarg($db['host']),
        //     escapeshellarg($db['user']),
        //     escapeshellarg($db['password']),
        //     escapeshellarg($admin['username']),
        //     escapeshellarg($admin['password']),
        //     escapeshellarg($dataDir)
        // ));

        // Step 6: Configure trusted domains
        // Process::path($path)->run(sprintf(
        //     'php occ config:system:set trusted_domains 0 --value=%s',
        //     escapeshellarg(parse_url($url, PHP_URL_HOST))
        // ));

        // Step 7: Set recommended PHP settings in config
        // Process::path($path)->run('php occ config:system:set memcache.local --value="\\OC\\Memcache\\APCu"');
        // Process::path($path)->run('php occ config:system:set default_phone_region --value="US"');

        // Step 8: Set ownership and permissions
        $this->setOwnership($path, $account->uid, $account->gid);
        $this->setSecurePermissions($path);

        throw new \RuntimeException('Nextcloud installer not yet implemented. See TODO comments in NextcloudInstaller.php');

        return [
            'admin_url' => rtrim($url, '/') . '/index.php/login',
            'version' => $this->getInstalledVersion($path),
        ];
    }

    public function update(string $path, array $options): array
    {
        // TODO: Implement Nextcloud update
        //
        // Using OCC:
        // php occ upgrade
        //
        // Or with updater app:
        // php updater/updater.phar
        //
        // Steps:
        // 1. Enable maintenance mode: php occ maintenance:mode --on
        // 2. Backup database and files
        // 3. Download new version
        // 4. Extract over existing (preserve config/config.php and data/)
        // 5. Run upgrade: php occ upgrade
        // 6. Disable maintenance mode: php occ maintenance:mode --off

        throw new \RuntimeException('Nextcloud updater not yet implemented');

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
            // Also delete data directory if it exists
            $dataDir = $this->getDataDir($path);
            if ($dataDir && File::isDirectory($dataDir)) {
                File::deleteDirectory($dataDir);
            }

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

        // Enable maintenance mode during backup
        // Process::path($path)->run('php occ maintenance:mode --on');

        // Backup files
        File::copyDirectory($path, "{$backupPath}/files");

        // Backup data directory if separate
        $dataDir = $this->getDataDir($path);
        if ($dataDir && $dataDir !== "{$path}/data" && File::isDirectory($dataDir)) {
            File::copyDirectory($dataDir, "{$backupPath}/data");
        }

        // Export database
        $dbName = $this->getDbName($path);
        if ($dbName) {
            $this->mysql->exportDump($dbName, "{$backupPath}/database.sql");
        }

        // Disable maintenance mode
        // Process::path($path)->run('php occ maintenance:mode --off');
    }

    public function getInstalledVersion(string $path): ?string
    {
        // Nextcloud stores version in version.php
        $versionFile = "{$path}/version.php";

        if (File::exists($versionFile)) {
            $content = File::get($versionFile);
            if (preg_match("/\\\$OC_VersionString\s*=\s*'([^']+)'/", $content, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public function cloneToStaging(string $productionPath, string $stagingPath, array $options): array
    {
        $productionUrl = $options['production_url'];
        $stagingUrl = $options['staging_url'];

        // Enable maintenance mode
        // Process::path($productionPath)->run('php occ maintenance:mode --on');

        File::copyDirectory($productionPath, $stagingPath);

        $prodDbName = $this->getDbName($productionPath);
        $stagingDbName = ($options['database_prefix'] ?? 'stg_') . $prodDbName;

        $this->mysql->createDatabase($stagingDbName);

        $tempDump = "/tmp/staging_dump_{$stagingDbName}.sql";
        $this->mysql->exportDump($prodDbName, $tempDump);
        $this->mysql->importDump($stagingDbName, $tempDump);
        unlink($tempDump);

        // Update config.php with new database
        $configPath = "{$stagingPath}/config/config.php";
        if (File::exists($configPath)) {
            $config = File::get($configPath);
            $config = preg_replace(
                "/'dbname'\s*=>\s*'[^']+'/",
                "'dbname' => '{$stagingDbName}'",
                $config
            );
            // Update trusted domains
            $stagingHost = parse_url($stagingUrl, PHP_URL_HOST);
            // TODO: Properly update trusted_domains array
            File::put($configPath, $config);
        }

        // Update trusted domains via OCC
        // Process::path($stagingPath)->run(sprintf(
        //     'php occ config:system:set trusted_domains 0 --value=%s',
        //     escapeshellarg(parse_url($stagingUrl, PHP_URL_HOST))
        // ));

        // Disable maintenance mode
        // Process::path($productionPath)->run('php occ maintenance:mode --off');
        // Process::path($stagingPath)->run('php occ maintenance:mode --off');

        return [
            'staging_url' => $stagingUrl,
            'database' => $stagingDbName,
        ];
    }

    protected function getDbName(string $path): ?string
    {
        $configPath = "{$path}/config/config.php";

        if (!File::exists($configPath)) {
            return null;
        }

        $content = File::get($configPath);
        if (preg_match("/'dbname'\s*=>\s*'([^']+)'/", $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function getDataDir(string $path): ?string
    {
        $configPath = "{$path}/config/config.php";

        if (!File::exists($configPath)) {
            return null;
        }

        $content = File::get($configPath);
        if (preg_match("/'datadirectory'\s*=>\s*'([^']+)'/", $content, $matches)) {
            return $matches[1];
        }

        return "{$path}/data";
    }

    protected function setOwnership(string $path, int $uid, int $gid): void
    {
        Process::run("chown -R {$uid}:{$gid} " . escapeshellarg($path));
    }

    protected function setSecurePermissions(string $path): void
    {
        // Nextcloud has specific permission requirements
        // See: https://docs.nextcloud.com/server/latest/admin_manual/installation/installation_wizard.html#setting-strong-directory-permissions

        Process::run("find {$path} -type d -exec chmod 750 {} \\;");
        Process::run("find {$path} -type f -exec chmod 640 {} \\;");

        // config/ directory should be readable by web server
        if (File::isDirectory("{$path}/config")) {
            chmod("{$path}/config", 0750);
        }

        // Protect config.php
        if (File::exists("{$path}/config/config.php")) {
            chmod("{$path}/config/config.php", 0640);
        }

        // .htaccess and .user.ini need to be readable
        foreach (['.htaccess', '.user.ini'] as $file) {
            if (File::exists("{$path}/{$file}")) {
                chmod("{$path}/{$file}", 0644);
            }
        }

        // apps/, data/, updater/ need to be writable
        foreach (['apps', 'data', 'updater'] as $dir) {
            $dirPath = "{$path}/{$dir}";
            if (File::isDirectory($dirPath)) {
                Process::run("chmod -R 750 " . escapeshellarg($dirPath));
            }
        }
    }
}
