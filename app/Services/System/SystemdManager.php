<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Process;

class SystemdManager
{
    /**
     * Validate a systemd unit name. Allows letters, digits, and the
     * characters systemd permits in unit names (`-`, `_`, `.`, `@`, `:`).
     * Rejects anything that could be interpreted by a shell — although
     * callers should already be using array-form Process::run.
     */
    protected function assertValidServiceName(string $service): void
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9@._:-]{0,127}$/', $service)) {
            throw new \InvalidArgumentException("Invalid service name: {$service}");
        }
    }

    /**
     * Start a service
     */
    public function start(string $service): void
    {
        $this->assertValidServiceName($service);
        $result = Process::run(['sudo', '/usr/bin/systemctl', 'start', $service]);

        if (! $result->successful()) {
            throw new \RuntimeException("Failed to start {$service}: ".$result->errorOutput());
        }
    }

    /**
     * Stop a service
     */
    public function stop(string $service): void
    {
        $this->assertValidServiceName($service);
        $result = Process::run(['sudo', '/usr/bin/systemctl', 'stop', $service]);

        if (! $result->successful()) {
            throw new \RuntimeException("Failed to stop {$service}: ".$result->errorOutput());
        }
    }

    /**
     * Restart a service
     */
    public function restart(string $service): void
    {
        $this->assertValidServiceName($service);
        $result = Process::run(['sudo', '/usr/bin/systemctl', 'restart', $service]);

        if (! $result->successful()) {
            throw new \RuntimeException("Failed to restart {$service}: ".$result->errorOutput());
        }
    }

    /**
     * Reload a service configuration
     */
    public function reload(string $service): void
    {
        $this->assertValidServiceName($service);
        $result = Process::run(['sudo', '/usr/bin/systemctl', 'reload', $service]);

        if (! $result->successful()) {
            // Fall back to restart if reload fails
            $this->restart($service);
        }
    }

    /**
     * Enable a service to start on boot
     */
    public function enable(string $service): void
    {
        $this->assertValidServiceName($service);
        $result = Process::run(['sudo', '/usr/bin/systemctl', 'enable', $service]);

        if (! $result->successful()) {
            throw new \RuntimeException("Failed to enable {$service}: ".$result->errorOutput());
        }
    }

    /**
     * Disable a service from starting on boot
     */
    public function disable(string $service): void
    {
        $this->assertValidServiceName($service);
        $result = Process::run(['sudo', '/usr/bin/systemctl', 'disable', $service]);

        if (! $result->successful()) {
            throw new \RuntimeException("Failed to disable {$service}: ".$result->errorOutput());
        }
    }

    /**
     * Check if a service exists
     */
    public function exists(string $service): bool
    {
        $this->assertValidServiceName($service);

        $result = Process::run(['sudo', '/usr/bin/systemctl', 'list-unit-files', "{$service}.service"]);
        if (! $result->successful()) {
            return false;
        }

        foreach (explode("\n", $result->output()) as $line) {
            if (str_starts_with(trim($line), "{$service}.service")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get service status
     */
    public function getStatus(string $service): array
    {
        $this->assertValidServiceName($service);

        $status = [
            'service' => $service,
            'status' => 'unknown',
            'is_running' => false,
            'is_enabled' => false,
            'pid' => null,
            'uptime' => null,
            'memory' => null,
            'cpu' => null,
        ];

        // Check if running
        $activeResult = Process::run(['sudo', '/usr/bin/systemctl', 'is-active', $service]);
        $status['status'] = trim($activeResult->output());
        $status['is_running'] = $status['status'] === 'active';

        // Check if enabled
        $enabledResult = Process::run(['sudo', '/usr/bin/systemctl', 'is-enabled', $service]);
        $status['is_enabled'] = trim($enabledResult->output()) === 'enabled';

        if ($status['is_running']) {
            // Get PID
            $pidResult = Process::run(['sudo', '/usr/bin/systemctl', 'show', $service, '--property=MainPID', '--value']);
            $pid = (int) trim($pidResult->output());
            $status['pid'] = $pid > 0 ? $pid : null;

            // Get uptime
            $uptimeResult = Process::run(['sudo', '/usr/bin/systemctl', 'show', $service, '--property=ActiveEnterTimestamp', '--value']);
            $timestamp = trim($uptimeResult->output());
            if ($timestamp) {
                $startTime = strtotime($timestamp);
                if ($startTime) {
                    $status['uptime'] = $this->formatUptime(time() - $startTime);
                }
            }

            // Get memory usage
            $memResult = Process::run(['sudo', '/usr/bin/systemctl', 'show', $service, '--property=MemoryCurrent', '--value']);
            $memBytes = trim($memResult->output());
            if ($memBytes && $memBytes !== '[not set]') {
                $status['memory'] = $this->formatBytes((int) $memBytes);
            }

            // Get CPU usage (from top for main PID)
            if ($status['pid']) {
                $cpuResult = Process::run(['ps', '-p', (string) $status['pid'], '-o', '%cpu', '--no-headers']);
                $cpu = trim($cpuResult->output());
                if ($cpu !== '') {
                    $status['cpu'] = $cpu.'%';
                }
            }
        }

        return $status;
    }

    /**
     * Get service logs
     */
    public function getLogs(string $service, int $lines = 100): array
    {
        $this->assertValidServiceName($service);
        $lines = max(1, min($lines, 10000));

        $result = Process::run(['sudo', '/usr/bin/journalctl', '-u', $service, '-n', (string) $lines, '--no-pager']);

        if (! $result->successful()) {
            return [];
        }

        return array_filter(explode("\n", $result->output()));
    }

    /**
     * Get all managed services status
     */
    public function getAllStatus(array $services): array
    {
        $statuses = [];

        foreach ($services as $service) {
            $statuses[$service] = $this->getStatus($service);
        }

        return $statuses;
    }

    /**
     * Daemon reload (after changing unit files)
     */
    public function daemonReload(): void
    {
        Process::run(['sudo', '/usr/bin/systemctl', 'daemon-reload']);
    }

    protected function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
