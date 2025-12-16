<?php

namespace App\Services\Apps\Installers;

use App\Services\Apps\AppInstallerInterface;
use App\Services\Database\MysqlService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * PrestaShop E-commerce Installer
 *
 * TODO: Complete implementation for PrestaShop installation
 *
 * PrestaShop Installation Overview:
 * 1. Download PrestaShop from https://www.prestashop.com/
 * 2. Extract to target directory
 * 3. Create database and user
 * 4. Run PrestaShop CLI installer
 * 5. Remove install directory
 * 6. Set proper file permissions
 *
 * PrestaShop CLI Installation:
 * php install/index_cli.php \
 *   --domain=example.com --db_server=localhost --db_name=DB \
 *   --db_user=USER --db_password=PASS \
 *   --firstname=John --lastname=Doe --email=admin@example.com \
 *   --password=AdminPass123 --name="My Shop"
 *
 * References:
 * - https://devdocs.prestashop-project.org/8/basics/installation/
 * - https://github.com/PrestaShop/PrestaShop
 */
class PrestaShopInstaller implements AppInstallerInterface
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

        // TODO: Implement PrestaShop installation
        //
        // Step 1: Create directory
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        // Step 2: Create database
        $this->mysql->createDatabase($db['name']);
        $this->mysql->createUser($db['user'], $db['password']);
        $this->mysql->grantPrivileges($db['user'], $db['name'], ['ALL PRIVILEGES']);

        // Step 3: Download PrestaShop
        // TODO: Implement download logic
        // $version = config('freepanel.applications.prestashop.version', '8.1.0');
        // $downloadUrl = "https://github.com/PrestaShop/PrestaShop/releases/download/{$version}/prestashop_{$version}.zip";
        // $tempFile = "/tmp/prestashop_{$version}.zip";
        // Http::sink($tempFile)->get($downloadUrl);
        //
        // // PrestaShop zip contains another zip inside
        // Process::run("unzip -q {$tempFile} -d /tmp/prestashop_extract");
        // Process::run("unzip -q /tmp/prestashop_extract/prestashop.zip -d {$path}");
        // File::deleteDirectory('/tmp/prestashop_extract');
        // unlink($tempFile);

        // Step 4: Run CLI installer
        // TODO: Execute CLI installation
        // Parse domain from URL
        // $parsedUrl = parse_url($url);
        // $domain = $parsedUrl['host'];
        //
        // Process::run(sprintf(
        //     'php %s/install/index_cli.php ' .
        //     '--domain=%s --db_server=%s --db_name=%s ' .
        //     '--db_user=%s --db_password=%s ' .
        //     '--prefix=ps_ --email=%s --password=%s ' .
        //     '--name=%s --language=en --country=US',
        //     escapeshellarg($path),
        //     escapeshellarg($domain),
        //     escapeshellarg($db['host']),
        //     escapeshellarg($db['name']),
        //     escapeshellarg($db['user']),
        //     escapeshellarg($db['password']),
        //     escapeshellarg($admin['email']),
        //     escapeshellarg($admin['password']),
        //     escapeshellarg($siteName)
        // ));

        // Step 5: Remove install directory (SECURITY CRITICAL)
        // File::deleteDirectory("{$path}/install");

        // Step 6: Rename admin directory for security
        // $adminNewName = 'admin' . substr(md5(uniqid()), 0, 8);
        // rename("{$path}/admin", "{$path}/{$adminNewName}");

        // Step 7: Set ownership and permissions
        $this->setOwnership($path, $account->uid, $account->gid);
        $this->setSecurePermissions($path);

        throw new \RuntimeException('PrestaShop installer not yet implemented. See TODO comments in PrestaShopInstaller.php');

        return [
            'admin_url' => rtrim($url, '/') . '/admin/', // Note: should use renamed admin dir
            'version' => $this->getInstalledVersion($path),
        ];
    }

    public function update(string $path, array $options): array
    {
        // TODO: Implement PrestaShop update
        // PrestaShop updates are typically done through:
        // 1. 1-Click Upgrade module in back office
        // 2. Manual update by replacing files
        //
        // CLI update (PrestaShop 8+):
        // php bin/console prestashop:update

        throw new \RuntimeException('PrestaShop updater not yet implemented');

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
        // PrestaShop stores version in app/AppKernel.php or config/defines.inc.php
        $versionFiles = [
            "{$path}/app/AppKernel.php",
            "{$path}/config/defines.inc.php",
        ];

        foreach ($versionFiles as $versionFile) {
            if (File::exists($versionFile)) {
                $content = File::get($versionFile);
                if (preg_match("/const\s+VERSION\s*=\s*'([^']+)'/", $content, $matches)) {
                    return $matches[1];
                }
                if (preg_match("/define\s*\(\s*'_PS_VERSION_'\s*,\s*'([^']+)'\s*\)/", $content, $matches)) {
                    return $matches[1];
                }
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

        // Update config/settings.inc.php with new database and URL
        $settingsPath = "{$stagingPath}/config/settings.inc.php";
        if (File::exists($settingsPath)) {
            $settings = File::get($settingsPath);
            $settings = preg_replace(
                "/define\s*\(\s*'_DB_NAME_'\s*,\s*'[^']+'\s*\)/",
                "define('_DB_NAME_', '{$stagingDbName}')",
                $settings
            );
            File::put($settingsPath, $settings);
        }

        // Update shop URLs in database
        // TODO: Run SQL to update ps_shop_url and ps_configuration tables

        return [
            'staging_url' => $stagingUrl,
            'database' => $stagingDbName,
        ];
    }

    protected function getDbName(string $path): ?string
    {
        $settingsPath = "{$path}/config/settings.inc.php";

        if (!File::exists($settingsPath)) {
            return null;
        }

        $content = File::get($settingsPath);
        if (preg_match("/define\s*\(\s*'_DB_NAME_'\s*,\s*'([^']+)'\s*\)/", $content, $matches)) {
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

        // Protect config files
        if (File::exists("{$path}/config/settings.inc.php")) {
            chmod("{$path}/config/settings.inc.php", 0600);
        }

        // Make cache and log directories writable
        foreach (['var/cache', 'var/logs', 'img', 'upload', 'download'] as $dir) {
            $dirPath = "{$path}/{$dir}";
            if (File::isDirectory($dirPath)) {
                Process::run("chmod -R 755 " . escapeshellarg($dirPath));
            }
        }
    }
}
