<?php

namespace App\Services\Backup;

use App\Models\Account;
use App\Models\Backup;
use App\Services\Database\MysqlService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupService
{
    protected MysqlService $mysql;
    protected string $backupDir;

    public function __construct(MysqlService $mysql)
    {
        $this->mysql = $mysql;
        $this->backupDir = config('freepanel.backup_dir', '/var/backups/freepanel');
    }

    /**
     * Create a backup for an account
     */
    public function create(Account $account, array $options = []): array
    {
        $type = $options['type'] ?? 'full';
        $timestamp = date('Y-m-d_His');
        $backupName = "{$account->username}_{$type}_{$timestamp}";
        $tempDir = "/tmp/backup_{$backupName}";
        $finalPath = "{$this->backupDir}/{$account->username}/{$backupName}.tar.gz";

        // Ensure backup directory exists
        $accountBackupDir = dirname($finalPath);
        if (!File::isDirectory($accountBackupDir)) {
            File::makeDirectory($accountBackupDir, 0700, true);
        }

        // Create temp directory
        File::makeDirectory($tempDir, 0700, true);

        try {
            $manifest = [
                'account' => $account->username,
                'domain' => $account->domain,
                'type' => $type,
                'created_at' => now()->toIso8601String(),
                'components' => [],
            ];

            // Backup files
            if (in_array($type, ['full', 'files'])) {
                $this->backupFiles($account, $tempDir);
                $manifest['components'][] = 'files';
            }

            // Backup databases
            if (in_array($type, ['full', 'databases'])) {
                $this->backupDatabases($account, $tempDir);
                $manifest['components'][] = 'databases';
            }

            // Backup emails
            if (in_array($type, ['full', 'emails'])) {
                $this->backupEmails($account, $tempDir);
                $manifest['components'][] = 'emails';
            }

            // Write manifest
            File::put("{$tempDir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));

            // Create tar.gz archive
            $result = Process::run(
                "tar -czf " . escapeshellarg($finalPath) . " -C " . escapeshellarg(dirname($tempDir)) . " " . escapeshellarg(basename($tempDir))
            );

            if (!$result->successful()) {
                throw new \RuntimeException("Failed to create backup archive: " . $result->errorOutput());
            }

            $size = filesize($finalPath);

            return [
                'path' => $finalPath,
                'size' => $size,
                'manifest' => $manifest,
            ];
        } finally {
            // Cleanup temp directory
            if (File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Restore a backup
     */
    public function restore(Account $account, Backup $backup, array $options = []): void
    {
        $components = $options['components'] ?? ['files', 'databases', 'emails'];
        $tempDir = "/tmp/restore_{$account->username}_" . time();

        // Extract backup
        File::makeDirectory($tempDir, 0700, true);

        try {
            $result = Process::run(
                "tar -xzf " . escapeshellarg($backup->path) . " -C " . escapeshellarg($tempDir) . " --strip-components=1"
            );

            if (!$result->successful()) {
                throw new \RuntimeException("Failed to extract backup: " . $result->errorOutput());
            }

            // Read manifest
            $manifestPath = "{$tempDir}/manifest.json";
            if (!File::exists($manifestPath)) {
                throw new \RuntimeException("Backup manifest not found");
            }

            $manifest = json_decode(File::get($manifestPath), true);

            // Restore files
            if (in_array('files', $components) && in_array('files', $manifest['components'])) {
                $this->restoreFiles($account, $tempDir);
            }

            // Restore databases
            if (in_array('databases', $components) && in_array('databases', $manifest['components'])) {
                $this->restoreDatabases($account, $tempDir);
            }

            // Restore emails
            if (in_array('emails', $components) && in_array('emails', $manifest['components'])) {
                $this->restoreEmails($account, $tempDir);
            }
        } finally {
            // Cleanup
            if (File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Get backup contents preview
     */
    public function getContents(string $backupPath): array
    {
        $result = Process::run("tar -tzf " . escapeshellarg($backupPath) . " 2>/dev/null | head -100");

        return array_filter(explode("\n", $result->output()));
    }

    /**
     * Delete old backups based on retention policy
     */
    public function cleanupOldBackups(Account $account, int $retentionDays = 30): int
    {
        $backupDir = "{$this->backupDir}/{$account->username}";

        if (!File::isDirectory($backupDir)) {
            return 0;
        }

        $deleted = 0;
        $cutoffTime = time() - ($retentionDays * 86400);

        foreach (File::files($backupDir) as $file) {
            if ($file->getMTime() < $cutoffTime) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        // Also delete from database
        Backup::where('account_id', $account->id)
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();

        return $deleted;
    }

    protected function backupFiles(Account $account, string $tempDir): void
    {
        $homeDir = "/home/{$account->username}";
        $destDir = "{$tempDir}/files";

        if (!File::isDirectory($homeDir)) {
            return;
        }

        File::makeDirectory($destDir, 0700, true);

        // Backup public_html and other user directories
        $dirs = ['public_html', 'mail', 'logs', '.ssh'];

        foreach ($dirs as $dir) {
            $sourcePath = "{$homeDir}/{$dir}";
            if (File::isDirectory($sourcePath)) {
                File::copyDirectory($sourcePath, "{$destDir}/{$dir}");
            }
        }
    }

    protected function backupDatabases(Account $account, string $tempDir): void
    {
        $destDir = "{$tempDir}/databases";
        File::makeDirectory($destDir, 0700, true);

        // Get all databases for this account
        $databases = $account->databases;

        foreach ($databases as $database) {
            $dumpFile = "{$destDir}/{$database->name}.sql";
            $this->mysql->exportDump($database->name, $dumpFile);
        }
    }

    protected function backupEmails(Account $account, string $tempDir): void
    {
        $mailDir = config('freepanel.mail_dir', '/var/mail/vhosts');
        $destDir = "{$tempDir}/emails";

        File::makeDirectory($destDir, 0700, true);

        // Backup email data for each domain
        foreach ($account->domains as $domain) {
            $domainMailDir = "{$mailDir}/{$domain->name}";

            if (File::isDirectory($domainMailDir)) {
                File::copyDirectory($domainMailDir, "{$destDir}/{$domain->name}");
            }
        }
    }

    protected function restoreFiles(Account $account, string $tempDir): void
    {
        $sourceDir = "{$tempDir}/files";
        $homeDir = "/home/{$account->username}";

        if (!File::isDirectory($sourceDir)) {
            return;
        }

        // Restore each directory
        foreach (File::directories($sourceDir) as $dir) {
            $dirName = basename($dir);
            $destPath = "{$homeDir}/{$dirName}";

            // Backup existing before restore
            if (File::isDirectory($destPath)) {
                File::deleteDirectory($destPath);
            }

            File::copyDirectory($dir, $destPath);

            // Set ownership
            $this->chownRecursive($destPath, $account->uid, $account->gid);
        }
    }

    protected function restoreDatabases(Account $account, string $tempDir): void
    {
        $sourceDir = "{$tempDir}/databases";

        if (!File::isDirectory($sourceDir)) {
            return;
        }

        foreach (File::files($sourceDir) as $file) {
            if ($file->getExtension() !== 'sql') {
                continue;
            }

            $dbName = $file->getFilenameWithoutExtension();

            // Verify database belongs to this account
            $database = $account->databases()->where('name', $dbName)->first();

            if ($database) {
                // Import the dump
                $this->mysql->importDump($dbName, $file->getPathname());
            }
        }
    }

    protected function restoreEmails(Account $account, string $tempDir): void
    {
        $sourceDir = "{$tempDir}/emails";
        $mailDir = config('freepanel.mail_dir', '/var/mail/vhosts');

        if (!File::isDirectory($sourceDir)) {
            return;
        }

        foreach (File::directories($sourceDir) as $dir) {
            $domainName = basename($dir);
            $destPath = "{$mailDir}/{$domainName}";

            // Verify domain belongs to this account
            $domain = $account->domains()->where('name', $domainName)->first();

            if ($domain) {
                if (File::isDirectory($destPath)) {
                    File::deleteDirectory($destPath);
                }

                File::copyDirectory($dir, $destPath);

                // Set ownership (vmail user)
                $this->chownRecursive($destPath, 5000, 5000);
            }
        }
    }

    protected function chownRecursive(string $path, int $uid, int $gid): void
    {
        Process::run("chown -R {$uid}:{$gid} " . escapeshellarg($path));
    }
}
