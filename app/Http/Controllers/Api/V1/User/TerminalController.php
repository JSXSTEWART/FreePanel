<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TerminalController extends Controller
{
    /**
     * Create a new terminal session
     */
    public function createSession(Request $request)
    {
        $account = $request->user()->account;

        // Generate unique session ID
        $sessionId = Str::uuid()->toString();

        // Create session data
        $session = [
            'id' => $sessionId,
            'account_id' => $account->id,
            'username' => $account->username,
            'cwd' => "/home/{$account->username}",
            'created_at' => now()->toIso8601String(),
            'last_activity' => now()->toIso8601String(),
            'history' => [],
        ];

        // Store session (expires in 30 minutes of inactivity)
        Cache::put("terminal_session:{$sessionId}", $session, now()->addMinutes(30));

        return $this->success([
            'session_id' => $sessionId,
            'cwd' => $session['cwd'],
            'username' => $account->username,
            'hostname' => gethostname(),
        ]);
    }

    /**
     * Execute command in terminal session
     */
    public function execute(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|uuid',
            'command' => 'required|string|max:10000',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $account = $request->user()->account;
        $sessionId = $request->session_id;

        // Get session
        $session = Cache::get("terminal_session:{$sessionId}");

        if (!$session || $session['account_id'] !== $account->id) {
            return $this->error('Invalid or expired session', 401);
        }

        $command = trim($request->command);

        // Validate command for security
        $validationResult = $this->validateCommand($command, $account);
        if ($validationResult !== true) {
            return $this->error($validationResult, 403);
        }

        // Handle built-in commands
        if ($this->isBuiltInCommand($command)) {
            $result = $this->handleBuiltInCommand($command, $session, $account);

            if ($result['update_session']) {
                $session = array_merge($session, $result['session_update']);
                $session['last_activity'] = now()->toIso8601String();
                Cache::put("terminal_session:{$sessionId}", $session, now()->addMinutes(30));
            }

            return $this->success([
                'output' => $result['output'],
                'exit_code' => $result['exit_code'],
                'cwd' => $session['cwd'],
            ]);
        }

        // Execute command as the account user
        $cwd = $session['cwd'];
        $fullCommand = "cd {$cwd} && {$command}";

        $result = Process::timeout(30)->run(
            "sudo -u {$account->username} bash -c " . escapeshellarg($fullCommand) . " 2>&1"
        );

        // Add to history
        $session['history'][] = [
            'command' => $command,
            'timestamp' => now()->toIso8601String(),
        ];

        // Keep only last 100 commands
        if (count($session['history']) > 100) {
            $session['history'] = array_slice($session['history'], -100);
        }

        $session['last_activity'] = now()->toIso8601String();
        Cache::put("terminal_session:{$sessionId}", $session, now()->addMinutes(30));

        return $this->success([
            'output' => $result->output(),
            'exit_code' => $result->exitCode(),
            'cwd' => $session['cwd'],
        ]);
    }

    /**
     * Change directory
     */
    public function cd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|uuid',
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $account = $request->user()->account;
        $sessionId = $request->session_id;

        $session = Cache::get("terminal_session:{$sessionId}");

        if (!$session || $session['account_id'] !== $account->id) {
            return $this->error('Invalid or expired session', 401);
        }

        $newPath = $request->path;
        $currentCwd = $session['cwd'];
        $homeDir = "/home/{$account->username}";

        // Handle relative paths
        if (!str_starts_with($newPath, '/')) {
            if ($newPath === '~' || $newPath === '') {
                $newPath = $homeDir;
            } elseif (str_starts_with($newPath, '~/')) {
                $newPath = $homeDir . substr($newPath, 1);
            } else {
                $newPath = $currentCwd . '/' . $newPath;
            }
        }

        // Resolve the path
        $result = Process::run("sudo -u {$account->username} bash -c 'cd " . escapeshellarg($newPath) . " && pwd' 2>&1");

        if (!$result->successful()) {
            return $this->error(trim($result->output()) ?: 'Directory not found', 400);
        }

        $resolvedPath = trim($result->output());

        // Security: ensure path is within user's allowed directories
        if (!str_starts_with($resolvedPath, $homeDir) && !str_starts_with($resolvedPath, '/tmp')) {
            return $this->error('Access denied: outside home directory', 403);
        }

        $session['cwd'] = $resolvedPath;
        $session['last_activity'] = now()->toIso8601String();
        Cache::put("terminal_session:{$sessionId}", $session, now()->addMinutes(30));

        return $this->success([
            'cwd' => $resolvedPath,
        ]);
    }

    /**
     * Get command history
     */
    public function history(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|uuid',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $account = $request->user()->account;
        $sessionId = $request->session_id;

        $session = Cache::get("terminal_session:{$sessionId}");

        if (!$session || $session['account_id'] !== $account->id) {
            return $this->error('Invalid or expired session', 401);
        }

        return $this->success([
            'history' => $session['history'],
        ]);
    }

    /**
     * Close terminal session
     */
    public function closeSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|uuid',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $account = $request->user()->account;
        $sessionId = $request->session_id;

        $session = Cache::get("terminal_session:{$sessionId}");

        if ($session && $session['account_id'] === $account->id) {
            Cache::forget("terminal_session:{$sessionId}");
        }

        return $this->success(null, 'Session closed');
    }

    /**
     * Tab completion
     */
    public function complete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|uuid',
            'partial' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $account = $request->user()->account;
        $sessionId = $request->session_id;

        $session = Cache::get("terminal_session:{$sessionId}");

        if (!$session || $session['account_id'] !== $account->id) {
            return $this->error('Invalid or expired session', 401);
        }

        $partial = $request->partial;
        $cwd = $session['cwd'];

        // Get completions
        $completions = [];

        // File/directory completion
        if (str_contains($partial, '/') || str_contains($partial, '.')) {
            $result = Process::run(
                "sudo -u {$account->username} bash -c 'cd {$cwd} && compgen -f " . escapeshellarg($partial) . "' 2>/dev/null"
            );
            $completions = array_filter(explode("\n", $result->output()));
        } else {
            // Command completion
            $result = Process::run(
                "sudo -u {$account->username} bash -c 'compgen -c " . escapeshellarg($partial) . "' 2>/dev/null | head -20"
            );
            $completions = array_filter(explode("\n", $result->output()));
        }

        return $this->success([
            'completions' => array_values(array_unique($completions)),
        ]);
    }

    /**
     * Validate command for security
     */
    protected function validateCommand(string $command, $account): bool|string
    {
        // Dangerous commands that should never be run
        $blockedCommands = [
            'rm -rf /',
            'mkfs',
            'dd if=',
            ':(){ :|:& };:',  // Fork bomb
            '> /dev/sd',
            'chmod -R 777 /',
            'chown -R',
            'shutdown',
            'reboot',
            'init ',
            'systemctl',
            'service ',
            '/etc/passwd',
            '/etc/shadow',
        ];

        foreach ($blockedCommands as $blocked) {
            if (str_contains(strtolower($command), strtolower($blocked))) {
                return "Command blocked for security reasons";
            }
        }

        // Block sudo unless explicitly allowed
        if (preg_match('/\bsudo\b/', $command)) {
            return "sudo is not allowed in web terminal";
        }

        // Block su
        if (preg_match('/\bsu\b/', $command)) {
            return "su is not allowed in web terminal";
        }

        return true;
    }

    /**
     * Check if command is built-in
     */
    protected function isBuiltInCommand(string $command): bool
    {
        $builtIns = ['cd', 'pwd', 'clear', 'exit', 'history'];
        $parts = preg_split('/\s+/', $command);
        return in_array($parts[0], $builtIns);
    }

    /**
     * Handle built-in commands
     */
    protected function handleBuiltInCommand(string $command, array $session, $account): array
    {
        $parts = preg_split('/\s+/', $command);
        $cmd = $parts[0];

        switch ($cmd) {
            case 'cd':
                $path = $parts[1] ?? '~';
                // CD is handled separately via the cd endpoint
                return [
                    'output' => '',
                    'exit_code' => 0,
                    'update_session' => false,
                    'session_update' => [],
                ];

            case 'pwd':
                return [
                    'output' => $session['cwd'] . "\n",
                    'exit_code' => 0,
                    'update_session' => false,
                    'session_update' => [],
                ];

            case 'clear':
                return [
                    'output' => "\033[2J\033[H",  // ANSI clear screen
                    'exit_code' => 0,
                    'update_session' => false,
                    'session_update' => [],
                ];

            case 'exit':
                return [
                    'output' => "Session terminated.\n",
                    'exit_code' => 0,
                    'update_session' => false,
                    'session_update' => [],
                ];

            case 'history':
                $output = '';
                foreach ($session['history'] as $i => $entry) {
                    $output .= sprintf("%5d  %s\n", $i + 1, $entry['command']);
                }
                return [
                    'output' => $output,
                    'exit_code' => 0,
                    'update_session' => false,
                    'session_update' => [],
                ];

            default:
                return [
                    'output' => "Unknown built-in command\n",
                    'exit_code' => 1,
                    'update_session' => false,
                    'session_update' => [],
                ];
        }
    }
}
