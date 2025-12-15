<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Process;

class SystemdManager
{
    /**
     * Start a service
     */
    public function start(string $service): void
    {
        $result = Process::run("systemctl start {$service}");

        if (!$result->successful()) {
            throw new \RuntimeException("Failed to start {$service}: " . $result->errorOutput());
        }
    }

    /**
     * Stop a service
     */
    public function stop(string $service): void
    {
        $result = Process::run("systemctl stop {$service}");

        if (!$result->successful()) {
            throw new \RuntimeException("Failed to stop {$service}: " . $result->errorOutput());
        }
    }

    /**
     * Restart a service
     */
    public function restart(string $service): void
    {
        $result = Process::run("systemctl restart {$service}");

        if (!$result->successful()) {
            throw new \RuntimeException("Failed to restart {$service}: " . $result->errorOutput());
        }
    }

    /**
     * Reload a service configuration
     */
    public function reload(string $service): void
    {
        $result = Process::run("systemctl reload {$service}");

        if (!$result->successful()) {
            // Fall back to restart if reload fails
            $this->restart($service);
        }
    }

    /**
     * Enable a service to start on boot
     */
    public function enable(string $service): void
    {
        $result = Process::run("systemctl enable {$service}");

        if (!$result->successful()) {
            throw new \RuntimeException("Failed to enable {$service}: " . $result->errorOutput());
        }
    }

    /**
     * Disable a service from starting on boot
     */
    public function disable(string $service): void
    {
        $result = Process::run("systemctl disable {$service}");

        if (!$result->successful()) {
            throw new \RuntimeException("Failed to disable {$service}: " . $result->errorOutput());
        }
    }

    /**
     * Check if a service exists
     */
    public function exists(string $service): bool
    {
        $result = Process::run("systemctl list-unit-files {$service}.service 2>/dev/null | grep -q {$service}");
        return $result->successful();
    }

    /**
     * Get service status
     */
    public function getStatus(string $service): array
    {
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
        $activeResult = Process::run("systemctl is-active {$service} 2>/dev/null");
        $status['status'] = trim($activeResult->output());
        $status['is_running'] = $status['status'] === 'active';

        // Check if enabled
        $enabledResult = Process::run("systemctl is-enabled {$service} 2>/dev/null");
        $status['is_enabled'] = trim($enabledResult->output()) === 'enabled';

        if ($status['is_running']) {
            // Get PID
            $pidResult = Process::run("systemctl show {$service} --property=MainPID --value 2>/dev/null");
            $pid = (int) trim($pidResult->output());
            $status['pid'] = $pid > 0 ? $pid : null;

            // Get uptime
            $uptimeResult = Process::run("systemctl show {$service} --property=ActiveEnterTimestamp --value 2>/dev/null");
            $timestamp = trim($uptimeResult->output());
            if ($timestamp) {
                $startTime = strtotime($timestamp);
                if ($startTime) {
                    $status['uptime'] = $this->formatUptime(time() - $startTime);
                }
            }

            // Get memory usage
            $memResult = Process::run("systemctl show {$service} --property=MemoryCurrent --value 2>/dev/null");
            $memBytes = trim($memResult->output());
            if ($memBytes && $memBytes !== '[not set]') {
                $status['memory'] = $this->formatBytes((int) $memBytes);
            }

            // Get CPU usage (from top for main PID)
            if ($status['pid']) {
                $cpuResult = Process::run("ps -p {$status['pid']} -o %cpu --no-headers 2>/dev/null");
                $cpu = trim($cpuResult->output());
                if ($cpu !== '') {
                    $status['cpu'] = $cpu . '%';
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
        $result = Process::run("journalctl -u {$service} -n {$lines} --no-pager 2>/dev/null");

        if (!$result->successful()) {
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
        Process::run('systemctl daemon-reload');
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

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
