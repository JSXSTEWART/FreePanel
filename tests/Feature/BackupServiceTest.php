<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Account;
use App\Models\Backup;
use App\Services\Backup\BackupService;
use Illuminate\Support\Facades\File;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BackupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_old_backups_deletes_old_files_and_db_records(): void
    {
        $user = \App\Models\User::create(['username' => 'jdoe', 'email' => 'jdoe@example.com', 'password' => 'password']);
        $package = \App\Models\Package::create(['name' => 'default', 'is_active' => true]);
        $account = Account::create(['user_id' => $user->id, 'package_id' => $package->id, 'username' => 'jdoe', 'domain' => 'example.com', 'status' => 'active']);

        $tmpBackupDir = storage_path('app/test_backups/' . $account->username);
        File::ensureDirectoryExists($tmpBackupDir, 0700, true);

        // create old backup file
        $oldPath = $tmpBackupDir . '/old_backup.tar.gz';
        File::put($oldPath, 'old');
        // set mtime to past
        touch($oldPath, time() - (40 * 86400));

        // record in DB
        Backup::create([
            'account_id' => $account->id,
            'filename' => basename($oldPath),
            'storage_path' => $tmpBackupDir,
            'size' => filesize($oldPath),
            'status' => 'completed',
            'created_at' => now()->subDays(40),
        ]);

        // set config for backup dir
        config(['freepanel.backup_dir' => storage_path('app/test_backups')]);

        // Mock MysqlService so resolving BackupService doesn't attempt DB admin connection
        $this->mock(\App\Services\Database\MysqlService::class, function ($mock) {
            $mock->shouldReceive('exportDump')->andReturnNull();
        });

        $service = app(BackupService::class);

        $deleted = $service->cleanupOldBackups($account, 30);

        $this->assertEquals(1, $deleted);
        $this->assertFileDoesNotExist($oldPath);
        $this->assertDatabaseMissing('backups', ['path' => $oldPath]);
    }
}
