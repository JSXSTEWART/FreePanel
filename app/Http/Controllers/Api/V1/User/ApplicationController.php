<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    /**
     * List applications for the authenticated user
     */
    public function index(Request $request)
    {
        $account = $request->user()->account;

        $applications = Application::where('account_id', $account->id)
            ->with('domain:id,name')
            ->orderBy('name')
            ->get();

        return $this->success($applications);
    }

    /**
     * Get available runtimes
     */
    public function runtimes()
    {
        return $this->success([
            'runtimes' => Application::availableRuntimes(),
            'process_managers' => [
                'nodejs' => ['pm2', 'forever', 'nodemon'],
                'python' => ['gunicorn', 'uwsgi', 'supervisor'],
                'ruby' => ['passenger', 'puma', 'unicorn'],
                'php' => ['php-fpm'],
            ],
        ]);
    }

    /**
     * Create a new application
     */
    public function store(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|regex:/^[a-zA-Z0-9_-]+$/',
            'domain_id' => 'nullable|exists:domains,id',
            'type' => 'required|in:nodejs,python,ruby,php',
            'runtime_version' => 'required|string',
            'path' => 'required|string|max:255',
            'entry_point' => 'string|max:255',
            'environment_variables' => 'nullable|array',
            'instances' => 'integer|min:1|max:10',
            'max_memory_mb' => 'nullable|integer|min:128|max:4096',
            'auto_restart' => 'boolean',
            'watch_files' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if name is unique for this account
        if (Application::where('account_id', $account->id)->where('name', $request->name)->exists()) {
            return $this->error('Application with this name already exists', 422);
        }

        // Assign a port
        $port = $this->getAvailablePort($account->id);

        $application = Application::create([
            'account_id' => $account->id,
            'domain_id' => $request->domain_id,
            'name' => $request->name,
            'type' => $request->type,
            'runtime_version' => $request->runtime_version,
            'path' => $request->path,
            'entry_point' => $request->input('entry_point', Application::getDefaultEntryPoint($request->type)),
            'port' => $port,
            'environment_variables' => $request->environment_variables,
            'status' => 'stopped',
            'process_manager' => Application::getDefaultProcessManager($request->type),
            'instances' => $request->input('instances', 1),
            'max_memory_mb' => $request->max_memory_mb,
            'auto_restart' => $request->boolean('auto_restart', true),
            'watch_files' => $request->boolean('watch_files', false),
            'log_path' => "/home/{$account->username}/logs/{$request->name}.log",
            'error_log_path' => "/home/{$account->username}/logs/{$request->name}.error.log",
        ]);

        // Create log directory
        Process::run("sudo -u {$account->username} mkdir -p /home/{$account->username}/logs");

        return $this->success($application, 'Application created');
    }

    /**
     * Show a specific application
     */
    public function show(Request $request, Application $application)
    {
        $account = $request->user()->account;

        if ($application->account_id !== $account->id) {
            return $this->error('Application not found', 404);
        }

        // Get real-time status
        $status = $this->getApplicationStatus($application);

        return $this->success([
            'application' => $application,
            'status' => $status,
            'uptime' => $application->uptime,
        ]);
    }

    /**
     * Update an application
     */
    public function update(Request $request, Application $application)
    {
        $account = $request->user()->account;

        if ($application->account_id !== $account->id) {
            return $this->error('Application not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'domain_id' => 'nullable|exists:domains,id',
            'runtime_version' => 'string',
            'entry_point' => 'string|max:255',
            'startup_file' => 'nullable|string|max:255',
            'environment_variables' => 'nullable|array',
            'instances' => 'integer|min:1|max:10',
            'max_memory_mb' => 'nullable|integer|min:128|max:4096',
            'auto_restart' => 'boolean',
            'watch_files' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $wasRunning = $application->status === 'running';

        $application->update($request->only([
            'domain_id',
            'runtime_version',
            'entry_point',
            'startup_file',
            'environment_variables',
            'instances',
            'max_memory_mb',
            'auto_restart',
            'watch_files',
        ]));

        // Restart if was running
        if ($wasRunning) {
            $this->restartApplication($application);
        }

        return $this->success($application, 'Application updated');
    }

    /**
     * Delete an application
     */
    public function destroy(Request $request, Application $application)
    {
        $account = $request->user()->account;

        if ($application->account_id !== $account->id) {
            return $this->error('Application not found', 404);
        }

        // Stop the application first
        $this->stopApplication($application);

        $application->delete();

        return $this->success(null, 'Application deleted');
    }

    /**
     * Start an application
     */
    public function start(Request $request, Application $application)
    {
        $account = $request->user()->account;

        if ($application->account_id !== $account->id) {
            return $this->error('Application not found', 404);
        }

        try {
            $this->startApplication($application);
            return $this->success($application->fresh(), 'Application started');
        } catch (\Exception $e) {
            return $this->error('Failed to start application: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Stop an application
     */
    public function stop(Request $request, Application $application)
    {
        $account = $request->user()->account;

        if ($application->account_id !== $account->id) {
            return $this->error('Application not found', 404);
        }

        try {
            $this->stopApplication($application);
            return $this->success($application->fresh(), 'Application stopped');
        } catch (\Exception $e) {
            return $this->error('Failed to stop application: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Restart an application
     */
    public function restart(Request $request, Application $application)
    {
        $account = $request->user()->account;

        if ($application->account_id !== $account->id) {
            return $this->error('Application not found', 404);
        }

        try {
            $this->restartApplication($application);
            return $this->success($application->fresh(), 'Application restarted');
        } catch (\Exception $e) {
            return $this->error('Failed to restart application: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get application logs
     */
    public function logs(Request $request, Application $application)
    {
        $account = $request->user()->account;

        if ($application->account_id !== $account->id) {
            return $this->error('Application not found', 404);
        }

        $type = $request->input('type', 'stdout'); // stdout or stderr
        $lines = $request->input('lines', 100);

        $logPath = $type === 'stderr' ? $application->error_log_path : $application->log_path;
        $result = Process::run("tail -{$lines} {$logPath} 2>/dev/null");

        return $this->success([
            'logs' => $result->output() ?: 'No logs available',
            'type' => $type,
        ]);
    }

    /**
     * Get application metrics
     */
    public function metrics(Request $request, Application $application)
    {
        $account = $request->user()->account;

        if ($application->account_id !== $account->id) {
            return $this->error('Application not found', 404);
        }

        $metrics = $this->getApplicationMetrics($application);

        return $this->success($metrics);
    }

    /**
     * Update environment variables
     */
    public function updateEnv(Request $request, Application $application)
    {
        $account = $request->user()->account;

        if ($application->account_id !== $account->id) {
            return $this->error('Application not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'environment_variables' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $application->update([
            'environment_variables' => $request->environment_variables,
        ]);

        // Restart if running
        if ($application->status === 'running') {
            $this->restartApplication($application);
        }

        return $this->success($application, 'Environment variables updated');
    }

    /**
     * Start the application
     */
    protected function startApplication(Application $application): void
    {
        $account = $application->account;
        $fullPath = $application->full_path;

        $application->update(['status' => 'starting']);

        switch ($application->type) {
            case 'nodejs':
                $this->startNodeApp($application);
                break;
            case 'python':
                $this->startPythonApp($application);
                break;
            case 'ruby':
                $this->startRubyApp($application);
                break;
        }

        $application->update([
            'status' => 'running',
            'started_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Stop the application
     */
    protected function stopApplication(Application $application): void
    {
        $account = $application->account;

        switch ($application->type) {
            case 'nodejs':
                Process::run("sudo -u {$account->username} pm2 delete {$application->name} 2>/dev/null");
                break;
            case 'python':
                // Kill gunicorn process
                $pidFile = "/tmp/{$application->name}.pid";
                if (file_exists($pidFile)) {
                    $pid = trim(file_get_contents($pidFile));
                    Process::run("kill {$pid} 2>/dev/null");
                }
                break;
        }

        $application->update([
            'status' => 'stopped',
            'started_at' => null,
        ]);
    }

    /**
     * Restart the application
     */
    protected function restartApplication(Application $application): void
    {
        $this->stopApplication($application);
        sleep(1);
        $this->startApplication($application);
    }

    /**
     * Start Node.js application with PM2
     */
    protected function startNodeApp(Application $application): void
    {
        $account = $application->account;
        $fullPath = $application->full_path;

        // Generate PM2 ecosystem config
        $pm2Config = $application->toPm2Config();
        $configPath = "{$fullPath}/ecosystem.config.js";

        $configContent = "module.exports = { apps: [" . json_encode($pm2Config) . "] };";
        file_put_contents("/tmp/pm2_config.js", $configContent);
        Process::run("sudo mv /tmp/pm2_config.js {$configPath}");
        Process::run("sudo chown {$account->username}:{$account->username} {$configPath}");

        // Set up environment
        $envStr = '';
        if ($application->environment_variables) {
            foreach ($application->environment_variables as $key => $value) {
                $envStr .= "{$key}={$value} ";
            }
        }
        $envStr .= "PORT={$application->port}";

        // Start with PM2
        $result = Process::run("cd {$fullPath} && sudo -u {$account->username} {$envStr} pm2 start ecosystem.config.js");

        if (!$result->successful()) {
            throw new \Exception($result->errorOutput());
        }
    }

    /**
     * Start Python application with Gunicorn
     */
    protected function startPythonApp(Application $application): void
    {
        $account = $application->account;
        $fullPath = $application->full_path;

        // Build environment string
        $envStr = '';
        if ($application->environment_variables) {
            foreach ($application->environment_variables as $key => $value) {
                $envStr .= "{$key}={$value} ";
            }
        }

        $pidFile = "/tmp/{$application->name}.pid";
        $entryPoint = $application->entry_point;

        $cmd = "cd {$fullPath} && {$envStr} gunicorn " .
               "--bind 127.0.0.1:{$application->port} " .
               "--workers {$application->instances} " .
               "--pid {$pidFile} " .
               "--daemon " .
               "--access-logfile {$application->log_path} " .
               "--error-logfile {$application->error_log_path} " .
               "{$entryPoint}";

        $result = Process::run("sudo -u {$account->username} bash -c '{$cmd}'");

        if (!$result->successful()) {
            throw new \Exception($result->errorOutput());
        }
    }

    /**
     * Start Ruby application
     */
    protected function startRubyApp(Application $application): void
    {
        $account = $application->account;
        $fullPath = $application->full_path;

        // Use Passenger or Puma
        $envStr = '';
        if ($application->environment_variables) {
            foreach ($application->environment_variables as $key => $value) {
                $envStr .= "{$key}={$value} ";
            }
        }

        $cmd = "cd {$fullPath} && {$envStr} bundle exec puma " .
               "-b tcp://127.0.0.1:{$application->port} " .
               "-w {$application->instances} " .
               "-d " .
               "--pidfile /tmp/{$application->name}.pid";

        Process::run("sudo -u {$account->username} bash -c '{$cmd}'");
    }

    /**
     * Get real-time application status
     */
    protected function getApplicationStatus(Application $application): array
    {
        $account = $application->account;

        switch ($application->type) {
            case 'nodejs':
                $result = Process::run("sudo -u {$account->username} pm2 jlist 2>/dev/null");
                $processes = json_decode($result->output(), true) ?? [];

                foreach ($processes as $proc) {
                    if ($proc['name'] === $application->name) {
                        return [
                            'running' => $proc['pm2_env']['status'] === 'online',
                            'cpu' => $proc['monit']['cpu'] ?? 0,
                            'memory' => $proc['monit']['memory'] ?? 0,
                            'restarts' => $proc['pm2_env']['restart_time'] ?? 0,
                            'uptime' => $proc['pm2_env']['pm_uptime'] ?? null,
                        ];
                    }
                }
                break;

            case 'python':
                $pidFile = "/tmp/{$application->name}.pid";
                if (file_exists($pidFile)) {
                    $pid = trim(file_get_contents($pidFile));
                    $result = Process::run("ps -p {$pid} -o %cpu,%mem --no-headers 2>/dev/null");
                    if ($result->successful() && trim($result->output())) {
                        [$cpu, $mem] = preg_split('/\s+/', trim($result->output()));
                        return [
                            'running' => true,
                            'cpu' => (float) $cpu,
                            'memory_percent' => (float) $mem,
                            'pid' => $pid,
                        ];
                    }
                }
                break;
        }

        return [
            'running' => false,
            'cpu' => 0,
            'memory' => 0,
        ];
    }

    /**
     * Get application metrics
     */
    protected function getApplicationMetrics(Application $application): array
    {
        $status = $this->getApplicationStatus($application);

        return [
            'current' => $status,
            'port' => $application->port,
            'instances' => $application->instances,
        ];
    }

    /**
     * Get an available port for the account
     */
    protected function getAvailablePort(int $accountId): int
    {
        // Port range: 3000-4999 for user applications
        $usedPorts = Application::where('account_id', $accountId)->pluck('port')->toArray();

        for ($port = 3000; $port < 5000; $port++) {
            if (!in_array($port, $usedPorts)) {
                return $port;
            }
        }

        throw new \Exception('No available ports');
    }
}
