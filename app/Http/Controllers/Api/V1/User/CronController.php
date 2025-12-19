<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\CronJob;
use App\Services\System\CronService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CronController extends Controller
{
    protected CronService $cronService;

    public function __construct(CronService $cronService)
    {
        $this->cronService = $cronService;
    }

    /**
     * List all cron jobs for the account
     */
    public function index(Request $request)
    {
        $account = $request->user()->account;

        $cronJobs = $account->cronJobs()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'schedule' => $job->schedule,
                    'schedule_description' => $job->schedule_description,
                    'minute' => $job->minute,
                    'hour' => $job->hour,
                    'day' => $job->day,
                    'month' => $job->month,
                    'weekday' => $job->weekday,
                    'command' => $job->command,
                    'email' => $job->email,
                    'is_active' => $job->is_active,
                    'last_run' => $job->last_run?->toIso8601String(),
                    'last_status' => $job->last_status,
                    'created_at' => $job->created_at->toIso8601String(),
                ];
            });

        return $this->success([
            'cron_jobs' => $cronJobs,
            'presets' => CronJob::getPresets(),
        ]);
    }

    /**
     * Create a new cron job
     */
    public function store(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'minute' => 'required|string|max:20',
            'hour' => 'required|string|max:20',
            'day' => 'required|string|max:20',
            'month' => 'required|string|max:20',
            'weekday' => 'required|string|max:20',
            'command' => 'required|string|max:1000',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Validate cron expression
        if (!$this->cronService->validateExpression(
            $request->minute,
            $request->hour,
            $request->day,
            $request->month,
            $request->weekday
        )) {
            return $this->error('Invalid cron expression', 422);
        }

        // Validate command for security
        if (!$this->cronService->validateCommand($request->command, $account)) {
            return $this->error('Command contains forbidden operations or paths', 422);
        }

        $cronJob = $account->cronJobs()->create([
            'minute' => $request->minute,
            'hour' => $request->hour,
            'day' => $request->day,
            'month' => $request->month,
            'weekday' => $request->weekday,
            'command' => $request->command,
            'email' => $request->email,
            'is_active' => true,
        ]);

        // Sync to system crontab
        try {
            $this->cronService->syncCrontab($account);
        } catch (\Exception $e) {
            $cronJob->delete();
            return $this->error('Failed to install cron job: ' . $e->getMessage(), 500);
        }

        return $this->success([
            'id' => $cronJob->id,
            'schedule' => $cronJob->schedule,
        ], 'Cron job created successfully', 201);
    }

    /**
     * Update a cron job
     */
    public function update(Request $request, int $id)
    {
        $account = $request->user()->account;
        $cronJob = $account->cronJobs()->find($id);

        if (!$cronJob) {
            return $this->error('Cron job not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'minute' => 'sometimes|string|max:20',
            'hour' => 'sometimes|string|max:20',
            'day' => 'sometimes|string|max:20',
            'month' => 'sometimes|string|max:20',
            'weekday' => 'sometimes|string|max:20',
            'command' => 'sometimes|string|max:1000',
            'email' => 'nullable|email|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // If schedule is being updated, validate it
        $minute = $request->minute ?? $cronJob->minute;
        $hour = $request->hour ?? $cronJob->hour;
        $day = $request->day ?? $cronJob->day;
        $month = $request->month ?? $cronJob->month;
        $weekday = $request->weekday ?? $cronJob->weekday;

        if (!$this->cronService->validateExpression($minute, $hour, $day, $month, $weekday)) {
            return $this->error('Invalid cron expression', 422);
        }

        // Validate command if being updated
        if ($request->has('command') && !$this->cronService->validateCommand($request->command, $account)) {
            return $this->error('Command contains forbidden operations or paths', 422);
        }

        $cronJob->update($request->only([
            'minute', 'hour', 'day', 'month', 'weekday',
            'command', 'email', 'is_active'
        ]));

        // Sync to system crontab
        try {
            $this->cronService->syncCrontab($account);
        } catch (\Exception $e) {
            return $this->error('Failed to update cron job: ' . $e->getMessage(), 500);
        }

        return $this->success(null, 'Cron job updated successfully');
    }

    /**
     * Delete a cron job
     */
    public function destroy(Request $request, int $id)
    {
        $account = $request->user()->account;
        $cronJob = $account->cronJobs()->find($id);

        if (!$cronJob) {
            return $this->error('Cron job not found', 404);
        }

        $cronJob->delete();

        // Sync to system crontab
        try {
            $this->cronService->syncCrontab($account);
        } catch (\Exception $e) {
            return $this->error('Failed to remove cron job from system: ' . $e->getMessage(), 500);
        }

        return $this->success(null, 'Cron job deleted successfully');
    }

    /**
     * Toggle cron job active status
     */
    public function toggle(Request $request, int $id)
    {
        $account = $request->user()->account;
        $cronJob = $account->cronJobs()->find($id);

        if (!$cronJob) {
            return $this->error('Cron job not found', 404);
        }

        $cronJob->update(['is_active' => !$cronJob->is_active]);

        // Sync to system crontab
        try {
            $this->cronService->syncCrontab($account);
        } catch (\Exception $e) {
            return $this->error('Failed to update cron job: ' . $e->getMessage(), 500);
        }

        return $this->success([
            'is_active' => $cronJob->is_active,
        ], $cronJob->is_active ? 'Cron job enabled' : 'Cron job disabled');
    }

    /**
     * Get the raw crontab for the account
     */
    public function raw(Request $request)
    {
        $account = $request->user()->account;

        try {
            $crontab = $this->cronService->getCrontab($account);
            return $this->success(['crontab' => $crontab]);
        } catch (\Exception $e) {
            return $this->error('Failed to get crontab: ' . $e->getMessage(), 500);
        }
    }
}
