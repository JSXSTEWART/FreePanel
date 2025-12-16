<?php

namespace App\Services\Apps\Installers;

use App\Services\Apps\AppInstallerInterface;
use App\Services\Database\MysqlService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Drupal CMS Installer
 *
 * TODO: Complete implementation for Drupal installation
 *
 * Drupal Installation Overview:
 * 1. Download Drupal using Composer or direct download
 * 2. Create database and user
 * 3. Run Drush site-install command
 * 4. Set proper file permissions
 *
 * Drush Installation Command:
 * drush site:install standard \
 *   --db-url=mysql://USER:PASS@localhost/DB \
 *   --site-name="Site Name" \
 *   --account-name=admin --account-pass=password --account-mail=admin@example.com
 *
 * Composer-based Installation (Recommended):
 * composer create-project drupal/recommended-project mysite
 *
 * References:
 * - https://www.drupal.org/docs/installing-drupal
 * - https://www.drush.org/latest/install/
 */
class DrupalInstaller implements AppInstallerInterface
{
    protected MysqlService $mysql;
    protected string $drushPath;

    public function __construct(MysqlService $mysql)
    {
        $this->mysql = $mysql;
        $this->drushPath = config('freepanel.drush_path', '/usr/local/bin/drush');
    }

    public function install(array $options): array
    {
        $path = $options['path'];
        $url = $options['url'];
        $db = $options['database'];
        $admin = $options['admin'];
        $siteName = $options['site_name'];
        $account = $options['account'];

        // TODO: Implement Drupal installation
        //
        // Step 1: Create directory
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        // Step 2: Create database
        $this->mysql->createDatabase($db['name']);
        $this->mysql->createUser($db['user'], $db['password']);
        $this->mysql->grantPrivileges($db['user'], $db['name'], ['ALL PRIVILEGES']);

        // Step 3: Download Drupal using Composer (recommended method)
        // TODO: Implement Composer-based installation
        //
        // Option A: Composer create-project (creates in new directory)
        // Process::run("composer create-project drupal/recommended-project {$path} --no-interaction");
        //
        // Option B: Direct download
        // $version = config('freepanel.applications.drupal.version', '10.1.0');
        // $downloadUrl = "https://ftp.drupal.org/files/projects/drupal-{$version}.tar.gz";
        // $tempFile = "/tmp/drupal_{$version}.tar.gz";
        // Http::sink($tempFile)->get($downloadUrl);
        // Process::run("tar -xzf {$tempFile} -C " . dirname($path));
        // rename(dirname($path) . "/drupal-{$version}", $path);
        // unlink($tempFile);

        // Step 4: Run Drush site-install
        // TODO: Execute Drush installation
        // $dbUrl = sprintf('mysql://%s:%s@%s/%s',
        //     $db['user'],
        //     urlencode($db['password']),
        //     $db['host'],
        //     $db['name']
        // );
        //
        // Process::path($path)->run(sprintf(
        //     '%s site:install standard ' .
        //     '--db-url=%s ' .
        //     '--site-name=%s ' .
        //     '--account-name=%s ' .
        //     '--account-pass=%s ' .
        //     '--account-mail=%s ' .
        //     '-y',
        //     $this->drushPath,
        //     escapeshellarg($dbUrl),
        //     escapeshellarg($siteName),
        //     escapeshellarg($admin['username']),
        //     escapeshellarg($admin['password']),
        //     escapeshellarg($admin['email'])
        // ));

        // Step 5: Create sites/default/settings.php if not exists
        // Drupal installer should create this, but we may need to ensure directories exist
        // File::makeDirectory("{$path}/sites/default/files", 0755, true);

        // Step 6: Set ownership and permissions
        $this->setOwnership($path, $account->uid, $account->gid);
        $this->setSecurePermissions($path);

        throw new \RuntimeException('Drupal installer not yet implemented. See TODO comments in DrupalInstaller.php');

        return [
            'admin_url' => rtrim($url, '/') . '/user/login',
            'version' => $this->getInstalledVersion($path),
        ];
    }

    public function update(string $path, array $options): array
    {
        // TODO: Implement Drupal update
        //
        // Using Drush:
        // drush updatedb -y
        // drush cache:rebuild
        //
        // For core updates with Composer:
        // composer update drupal/core --with-dependencies
        // drush updatedb -y
        // drush cache:rebuild

        throw new \RuntimeException('Drupal updater not yet implemented');

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
        // Drupal stores version in core/lib/Drupal.php
        $versionFile = "{$path}/core/lib/Drupal.php";

        if (!File::exists($versionFile)) {
            // Try older Drupal location
            $versionFile = "{$path}/includes/bootstrap.inc";
        }

        if (File::exists($versionFile)) {
            $content = File::get($versionFile);
            if (preg_match("/const\s+VERSION\s*=\s*'([^']+)'/", $content, $matches)) {
                return $matches[1];
            }
            if (preg_match("/define\s*\(\s*'VERSION'\s*,\s*'([^']+)'\s*\)/", $content, $matches)) {
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

        // Update settings.php with new database
        $settingsPath = "{$stagingPath}/sites/default/settings.php";
        if (File::exists($settingsPath)) {
            $settings = File::get($settingsPath);
            // Drupal database config is more complex, using array format
            // TODO: Implement proper settings.php update for Drupal
        }

        // Clear Drupal cache via Drush
        // Process::path($stagingPath)->run("{$this->drushPath} cache:rebuild");

        return [
            'staging_url' => $stagingUrl,
            'database' => $stagingDbName,
        ];
    }

    protected function getDbName(string $path): ?string
    {
        $settingsPath = "{$path}/sites/default/settings.php";

        if (!File::exists($settingsPath)) {
            return null;
        }

        $content = File::get($settingsPath);
        // Drupal uses array format: $databases['default']['default']['database'] = 'dbname';
        if (preg_match("/\['database'\]\s*=\s*'([^']+)'/", $content, $matches)) {
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

        // Protect settings.php
        $settingsPath = "{$path}/sites/default/settings.php";
        if (File::exists($settingsPath)) {
            chmod($settingsPath, 0444);
        }

        // Make files directory writable
        $filesDir = "{$path}/sites/default/files";
        if (File::isDirectory($filesDir)) {
            Process::run("chmod -R 755 " . escapeshellarg($filesDir));
        }
    }
}
