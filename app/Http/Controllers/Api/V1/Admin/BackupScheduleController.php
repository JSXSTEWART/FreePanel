<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BackupSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class BackupScheduleController extends Controller
{
    /**
     * List all backup schedules
     */
    public function index(Request $request)
    {
        $query = BackupSchedule::with('account:id,username,domain');

        if ($request->has('is_system')) {
            $query->where('is_system', $request->boolean('is_system'));
        }

        if ($request->has('is_enabled')) {
            $query->where('is_enabled', $request->boolean('is_enabled'));
        }

        $schedules = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->success($schedules);
    }

    /**
     * Create a new backup schedule
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'nullable|exists:accounts,id',
            'is_system' => 'boolean',
            'type' => 'required|in:full,files,databases,emails',
            'frequency' => 'required|in:daily,weekly,monthly',
            'day_of_week' => 'required_if:frequency,weekly|nullable|integer|min:0|max:6',
            'day_of_month' => 'required_if:frequency,monthly|nullable|integer|min:1|max:28',
            'time' => 'required|date_format:H:i',
            'retention_days' => 'integer|min:1|max:365',
            'destination' => 'required|in:local,remote,s3',
            'destination_config' => 'nullable|array',
            'is_enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Must have either account_id or is_system=true
        if (!$request->account_id && !$request->boolean('is_system')) {
            return $this->error('Either account_id or is_system must be specified', 422);
        }

        $schedule = BackupSchedule::create($request->all());

        return $this->success($schedule, 'Backup schedule created');
    }

    /**
     * Show a backup schedule
     */
    public function show(BackupSchedule $backupSchedule)
    {
        $backupSchedule->load('account:id,username,domain');

        return $this->success([
            'schedule' => $backupSchedule,
            'next_run' => $backupSchedule->next_run,
            'frequency_description' => $backupSchedule->frequency_description,
        ]);
    }

    /**
     * Update a backup schedule
     */
    public function update(Request $request, BackupSchedule $backupSchedule)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'in:full,files,databases,emails',
            'frequency' => 'in:daily,weekly,monthly',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:28',
            'time' => 'date_format:H:i',
            'retention_days' => 'integer|min:1|max:365',
            'destination' => 'in:local,remote,s3',
            'destination_config' => 'nullable|array',
            'is_enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $backupSchedule->update($request->all());

        return $this->success($backupSchedule, 'Backup schedule updated');
    }

    /**
     * Delete a backup schedule
     */
    public function destroy(BackupSchedule $backupSchedule)
    {
        $backupSchedule->delete();

        return $this->success(null, 'Backup schedule deleted');
    }

    /**
     * Toggle backup schedule enabled state
     */
    public function toggle(BackupSchedule $backupSchedule)
    {
        $backupSchedule->update(['is_enabled' => !$backupSchedule->is_enabled]);

        $status = $backupSchedule->is_enabled ? 'enabled' : 'disabled';

        return $this->success($backupSchedule, "Backup schedule {$status}");
    }

    /**
     * Run a backup immediately
     */
    public function runNow(BackupSchedule $backupSchedule)
    {
        $account = $backupSchedule->account;

        try {
            $backupPath = $this->performBackup($backupSchedule, $account);

            $backupSchedule->update([
                'last_run' => now(),
                'last_status' => 'success',
            ]);

            return $this->success([
                'backup_path' => $backupPath,
            ], 'Backup completed successfully');
        } catch (\Exception $e) {
            $backupSchedule->update([
                'last_run' => now(),
                'last_status' => 'failed: ' . $e->getMessage(),
            ]);

            return $this->error('Backup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List available backups
     */
    public function listBackups(Request $request)
    {
        $backupDir = storage_path('backups');
        $backups = [];

        if (!is_dir($backupDir)) {
            return $this->success(['backups' => []]);
        }

        $files = glob($backupDir . '/*/*.tar.gz');

        foreach ($files as $file) {
            $filename = basename($file);
            $dir = basename(dirname($file));

            preg_match('/^(.+?)_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})_(.+)\.tar\.gz$/', $filename, $matches);

            $backups[] = [
                'path' => $file,
                'filename' => $filename,
                'account' => $matches[1] ?? $dir,
                'date' => str_replace('_', ' ', $matches[2] ?? ''),
                'type' => $matches[3] ?? 'unknown',
                'size' => filesize($file),
                'size_human' => $this->formatBytes(filesize($file)),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        // Sort by date descending
        usort($backups, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

        // Filter by account if requested
        if ($request->has('account')) {
            $backups = array_filter($backups, fn($b) => $b['account'] === $request->account);
        }

        return $this->success(['backups' => array_values($backups)]);
    }

    /**
     * Restore a backup
     */
    public function restore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'backup_path' => 'required|string',
            'account_id' => 'required|exists:accounts,id',
            'restore_files' => 'boolean',
            'restore_databases' => 'boolean',
            'restore_emails' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $backupPath = $request->backup_path;

        if (!file_exists($backupPath)) {
            return $this->error('Backup file not found', 404);
        }

        $account = Account::findOrFail($request->account_id);

        try {
            $this->performRestore($backupPath, $account, $request);

            return $this->success(null, 'Backup restored successfully');
        } catch (\Exception $e) {
            return $this->error('Restore failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a backup file
     */
    public function deleteBackup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'backup_path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $backupPath = $request->backup_path;

        // Security: ensure path is within backups directory
        $realPath = realpath($backupPath);
        $backupDir = realpath(storage_path('backups'));

        if (!$realPath || !str_starts_with($realPath, $backupDir)) {
            return $this->error('Invalid backup path', 400);
        }

        if (!file_exists($backupPath)) {
            return $this->error('Backup file not found', 404);
        }

        unlink($backupPath);

        return $this->success(null, 'Backup deleted');
    }

    /**
     * Get backup statistics
     */
    public function statistics()
    {
        $backupDir = storage_path('backups');
        $totalSize = 0;
        $backupCount = 0;

        if (is_dir($backupDir)) {
            $files = glob($backupDir . '/*/*.tar.gz');
            $backupCount = count($files);
            foreach ($files as $file) {
                $totalSize += filesize($file);
            }
        }

        $scheduleStats = BackupSchedule::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN is_enabled = 1 THEN 1 ELSE 0 END) as enabled,
            SUM(CASE WHEN is_system = 1 THEN 1 ELSE 0 END) as system_schedules
        ')->first();

        $recentBackups = BackupSchedule::whereNotNull('last_run')
            ->where('last_status', 'like', 'success%')
            ->orderBy('last_run', 'desc')
            ->limit(5)
            ->get(['id', 'type', 'last_run', 'last_status']);

        $failedBackups = BackupSchedule::whereNotNull('last_run')
            ->where('last_status', 'like', 'failed%')
            ->orderBy('last_run', 'desc')
            ->limit(5)
            ->get(['id', 'type', 'last_run', 'last_status']);

        return $this->success([
            'total_backups' => $backupCount,
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
            'schedules' => [
                'total' => $scheduleStats->total ?? 0,
                'enabled' => $scheduleStats->enabled ?? 0,
                'system' => $scheduleStats->system_schedules ?? 0,
            ],
            'recent_backups' => $recentBackups,
            'failed_backups' => $failedBackups,
        ]);
    }

    /**
     * Perform the actual backup
     */
    protected function performBackup(BackupSchedule $schedule, ?Account $account): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');

        if ($schedule->is_system) {
            $backupName = "system_{$timestamp}_{$schedule->type}";
            $backupDir = storage_path('backups/system');
        } else {
            $backupName = "{$account->username}_{$timestamp}_{$schedule->type}";
            $backupDir = storage_path("backups/{$account->username}");
        }

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backupPath = "{$backupDir}/{$backupName}.tar.gz";
        $tempDir = sys_get_temp_dir() . "/backup_{$backupName}";
        mkdir($tempDir, 0755, true);

        try {
            switch ($schedule->type) {
                case 'full':
                    $this->backupFiles($account, $tempDir);
                    $this->backupDatabases($account, $tempDir);
                    break;
                case 'files':
                    $this->backupFiles($account, $tempDir);
                    break;
                case 'databases':
                    $this->backupDatabases($account, $tempDir);
                    break;
                case 'emails':
                    $this->backupEmails($account, $tempDir);
                    break;
            }

            // Create tar.gz archive
            $result = Process::run("tar -czf {$backupPath} -C " . dirname($tempDir) . " " . basename($tempDir));

            if (!$result->successful()) {
                throw new \Exception('Failed to create backup archive: ' . $result->errorOutput());
            }

            // Cleanup temp directory
            Process::run("rm -rf {$tempDir}");

            // Handle remote destinations
            if ($schedule->destination !== 'local') {
                $this->uploadToRemote($backupPath, $schedule);
            }

            // Cleanup old backups based on retention
            $this->cleanupOldBackups($schedule, $account);

            return $backupPath;
        } catch (\Exception $e) {
            // Cleanup on failure
            Process::run("rm -rf {$tempDir}");
            throw $e;
        }
    }

    /**
     * Backup files for an account
     */
    protected function backupFiles(?Account $account, string $destDir): void
    {
        if ($account) {
            $sourceDir = "/home/{$account->username}/public_html";
        } else {
            // System backup - backup key directories
            $sourceDir = '/etc';
        }

        if (!is_dir($sourceDir)) {
            return;
        }

        $filesDir = "{$destDir}/files";
        mkdir($filesDir, 0755, true);

        Process::run("cp -a {$sourceDir} {$filesDir}/");
    }

    /**
     * Backup databases for an account
     */
    protected function backupDatabases(?Account $account, string $destDir): void
    {
        $dbDir = "{$destDir}/databases";
        mkdir($dbDir, 0755, true);

        if ($account) {
            // Get account's databases
            $databases = $account->databases;

            foreach ($databases as $database) {
                $dumpFile = "{$dbDir}/{$database->name}.sql";
                $result = Process::run(
                    "mysqldump --single-transaction {$database->name} > {$dumpFile}"
                );
            }
        } else {
            // System backup - dump all databases
            $result = Process::run("mysql -N -e 'SHOW DATABASES'");
            $databases = array_filter(explode("\n", $result->output()), function ($db) {
                return !in_array($db, ['information_schema', 'performance_schema', 'mysql', 'sys', '']);
            });

            foreach ($databases as $database) {
                $dumpFile = "{$dbDir}/{$database}.sql";
                Process::run("mysqldump --single-transaction {$database} > {$dumpFile}");
            }
        }
    }

    /**
     * Backup emails for an account
     */
    protected function backupEmails(?Account $account, string $destDir): void
    {
        if (!$account) {
            return;
        }

        $mailDir = "/var/mail/vhosts/{$account->domain}";

        if (!is_dir($mailDir)) {
            return;
        }

        $emailsDir = "{$destDir}/emails";
        mkdir($emailsDir, 0755, true);

        Process::run("cp -a {$mailDir} {$emailsDir}/");
    }

    /**
     * Upload backup to remote destination
     */
    protected function uploadToRemote(string $backupPath, BackupSchedule $schedule): void
    {
        $config = $schedule->destination_config ?? [];

        switch ($schedule->destination) {
            case 's3':
                $bucket = $config['bucket'] ?? '';
                $region = $config['region'] ?? 'us-east-1';
                $key = basename($backupPath);

                Process::run("aws s3 cp {$backupPath} s3://{$bucket}/{$key} --region {$region}");
                break;

            case 'remote':
                $host = $config['host'] ?? '';
                $user = $config['user'] ?? 'root';
                $path = $config['path'] ?? '/backups';
                $port = $config['port'] ?? 22;

                Process::run("scp -P {$port} {$backupPath} {$user}@{$host}:{$path}/");
                break;
        }
    }

    /**
     * Cleanup old backups based on retention policy
     */
    protected function cleanupOldBackups(BackupSchedule $schedule, ?Account $account): void
    {
        if ($schedule->is_system) {
            $backupDir = storage_path('backups/system');
        } else {
            $backupDir = storage_path("backups/{$account->username}");
        }

        if (!is_dir($backupDir)) {
            return;
        }

        $cutoffTime = now()->subDays($schedule->retention_days)->timestamp;
        $files = glob($backupDir . '/*.tar.gz');

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }

    /**
     * Perform restore from backup
     */
    protected function performRestore(string $backupPath, Account $account, Request $request): void
    {
        $tempDir = sys_get_temp_dir() . '/restore_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            // Extract backup
            $result = Process::run("tar -xzf {$backupPath} -C {$tempDir}");

            if (!$result->successful()) {
                throw new \Exception('Failed to extract backup: ' . $result->errorOutput());
            }

            // Find the extracted directory
            $dirs = glob($tempDir . '/*', GLOB_ONLYDIR);
            $extractedDir = $dirs[0] ?? $tempDir;

            // Restore files
            if ($request->boolean('restore_files', true) && is_dir("{$extractedDir}/files")) {
                $targetDir = "/home/{$account->username}/public_html";
                Process::run("rm -rf {$targetDir}/*");
                Process::run("cp -a {$extractedDir}/files/* {$targetDir}/");
                Process::run("chown -R {$account->username}:{$account->username} {$targetDir}");
            }

            // Restore databases
            if ($request->boolean('restore_databases', true) && is_dir("{$extractedDir}/databases")) {
                $sqlFiles = glob("{$extractedDir}/databases/*.sql");
                foreach ($sqlFiles as $sqlFile) {
                    $dbName = pathinfo($sqlFile, PATHINFO_FILENAME);
                    Process::run("mysql {$dbName} < {$sqlFile}");
                }
            }

            // Restore emails
            if ($request->boolean('restore_emails', true) && is_dir("{$extractedDir}/emails")) {
                $mailDir = "/var/mail/vhosts/{$account->domain}";
                Process::run("rm -rf {$mailDir}");
                Process::run("cp -a {$extractedDir}/emails/* " . dirname($mailDir) . "/");
            }

            // Cleanup
            Process::run("rm -rf {$tempDir}");
        } catch (\Exception $e) {
            Process::run("rm -rf {$tempDir}");
            throw $e;
        }
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
