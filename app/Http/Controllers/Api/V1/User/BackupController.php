<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\Backup\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    protected BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function index(Request $request)
    {
        $account = $request->user()->account;

        $backups = Backup::where('account_id', $account->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($backup) {
                $backup->size_formatted = $this->formatBytes($backup->size);
                $backup->can_restore = file_exists($backup->path);
                return $backup;
            });

        return $this->success($backups);
    }

    public function store(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:full,files,databases,emails',
            'note' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if there's a recent backup in progress
        $inProgress = Backup::where('account_id', $account->id)
            ->where('status', 'in_progress')
            ->where('created_at', '>', now()->subMinutes(30))
            ->exists();

        if ($inProgress) {
            return $this->error('A backup is already in progress', 422);
        }

        DB::beginTransaction();
        try {
            // Create backup record
            $backup = Backup::create([
                'account_id' => $account->id,
                'type' => $request->type,
                'status' => 'in_progress',
                'note' => $request->note,
            ]);

            // Dispatch backup job (in production, this would be queued)
            $result = $this->backupService->create($account, [
                'type' => $request->type,
                'backup_id' => $backup->id,
            ]);

            $backup->update([
                'path' => $result['path'],
                'size' => $result['size'],
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            DB::commit();

            $backup->size_formatted = $this->formatBytes($backup->size);
            return $this->success($backup, 'Backup created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Mark backup as failed if it exists
            if (isset($backup)) {
                $backup->update(['status' => 'failed']);
            }

            return $this->error('Failed to create backup: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, int $id)
    {
        $account = $request->user()->account;

        $backup = Backup::where('account_id', $account->id)->findOrFail($id);

        $backup->size_formatted = $this->formatBytes($backup->size);
        $backup->can_restore = file_exists($backup->path);

        // Get backup contents preview
        if ($backup->can_restore && $backup->status === 'completed') {
            $backup->contents = $this->backupService->getContents($backup->path);
        }

        return $this->success($backup);
    }

    public function download(Request $request, int $id): StreamedResponse
    {
        $account = $request->user()->account;

        $backup = Backup::where('account_id', $account->id)->findOrFail($id);

        if (!file_exists($backup->path)) {
            abort(404, 'Backup file not found');
        }

        return response()->streamDownload(function () use ($backup) {
            $stream = fopen($backup->path, 'rb');
            while (!feof($stream)) {
                echo fread($stream, 8192);
            }
            fclose($stream);
        }, basename($backup->path), [
            'Content-Type' => 'application/gzip',
        ]);
    }

    public function restore(Request $request, int $id)
    {
        $account = $request->user()->account;

        $backup = Backup::where('account_id', $account->id)->findOrFail($id);

        if ($backup->status !== 'completed') {
            return $this->error('Backup is not completed', 422);
        }

        if (!file_exists($backup->path)) {
            return $this->error('Backup file not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'components' => 'nullable|array',
            'components.*' => 'string|in:files,databases,emails,settings',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            $this->backupService->restore($account, $backup, [
                'components' => $request->components ?? ['files', 'databases', 'emails'],
            ]);

            $backup->update(['restored_at' => now()]);

            DB::commit();
            return $this->success(null, 'Backup restored successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to restore backup: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $account = $request->user()->account;

        $backup = Backup::where('account_id', $account->id)->findOrFail($id);

        try {
            // Delete backup file
            if (file_exists($backup->path)) {
                unlink($backup->path);
            }

            $backup->delete();

            return $this->success(null, 'Backup deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete backup: ' . $e->getMessage(), 500);
        }
    }

    public function schedule(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'frequency' => 'nullable|string|in:daily,weekly,monthly',
            'time' => 'nullable|string|date_format:H:i',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:28',
            'retention' => 'nullable|integer|min:1|max:30',
            'type' => 'nullable|string|in:full,files,databases',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $schedule = $account->backup_schedule ?? [];

            $schedule = array_merge($schedule, [
                'enabled' => $request->enabled,
                'frequency' => $request->frequency ?? 'weekly',
                'time' => $request->time ?? '02:00',
                'day_of_week' => $request->day_of_week ?? 0,
                'day_of_month' => $request->day_of_month ?? 1,
                'retention' => $request->retention ?? 7,
                'type' => $request->type ?? 'full',
            ]);

            $account->update(['backup_schedule' => $schedule]);

            return $this->success($schedule, 'Backup schedule updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update backup schedule: ' . $e->getMessage(), 500);
        }
    }

    public function getSchedule(Request $request)
    {
        $account = $request->user()->account;

        $schedule = $account->backup_schedule ?? [
            'enabled' => false,
            'frequency' => 'weekly',
            'time' => '02:00',
            'day_of_week' => 0,
            'day_of_month' => 1,
            'retention' => 7,
            'type' => 'full',
        ];

        return $this->success($schedule);
    }

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
