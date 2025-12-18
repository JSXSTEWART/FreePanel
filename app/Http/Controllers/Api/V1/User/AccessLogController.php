<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessLogController extends Controller
{
    /**
     * Get access log overview
     */
    public function index(Request $request)
    {
        $account = $request->user()->account;
        $logDir = "/var/log/apache2/domlogs";
        $domain = $account->domain;

        $accessLog = "{$logDir}/{$domain}-access.log";
        $errorLog = "{$logDir}/{$domain}-error.log";
        $sslAccessLog = "{$logDir}/{$domain}-ssl_access.log";

        $logs = [];

        // Get log file info
        foreach ([$accessLog, $errorLog, $sslAccessLog] as $log) {
            if (file_exists($log)) {
                $result = Process::run("ls -la {$log}");
                preg_match('/(\d+)\s+(\w+\s+\d+\s+[\d:]+)/', $result->output(), $matches);

                $logs[] = [
                    'name' => basename($log),
                    'path' => $log,
                    'size' => (int) ($matches[1] ?? 0),
                    'size_human' => $this->formatBytes((int) ($matches[1] ?? 0)),
                    'modified' => $matches[2] ?? null,
                    'type' => str_contains($log, 'error') ? 'error' : 'access',
                ];
            }
        }

        // Get archived logs
        $result = Process::run("ls -la {$logDir}/{$domain}*.gz 2>/dev/null");
        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;
            preg_match('/(\d+)\s+(\w+\s+\d+\s+[\d:]+)\s+(.+\.gz)$/', $line, $matches);
            if ($matches) {
                $logs[] = [
                    'name' => basename($matches[3]),
                    'path' => $matches[3],
                    'size' => (int) $matches[1],
                    'size_human' => $this->formatBytes((int) $matches[1]),
                    'modified' => $matches[2],
                    'type' => 'archived',
                ];
            }
        }

        return $this->success([
            'logs' => $logs,
            'domain' => $domain,
        ]);
    }

    /**
     * View recent log entries
     */
    public function view(Request $request)
    {
        $account = $request->user()->account;
        $logDir = "/var/log/apache2/domlogs";
        $domain = $account->domain;

        $type = $request->input('type', 'access'); // access, error, ssl_access
        $lines = min($request->input('lines', 100), 1000);

        $logFile = match ($type) {
            'error' => "{$logDir}/{$domain}-error.log",
            'ssl_access' => "{$logDir}/{$domain}-ssl_access.log",
            default => "{$logDir}/{$domain}-access.log",
        };

        if (!file_exists($logFile)) {
            return $this->error('Log file not found', 404);
        }

        $result = Process::run("sudo tail -{$lines} {$logFile}");

        $entries = [];
        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;

            if ($type === 'error') {
                $entries[] = $this->parseErrorLogLine($line);
            } else {
                $entries[] = $this->parseAccessLogLine($line);
            }
        }

        return $this->success([
            'type' => $type,
            'entries' => array_reverse($entries),
            'count' => count($entries),
        ]);
    }

    /**
     * Download raw log file
     */
    public function download(Request $request): StreamedResponse
    {
        $account = $request->user()->account;
        $logDir = "/var/log/apache2/domlogs";
        $domain = $account->domain;

        $type = $request->input('type', 'access');
        $date = $request->input('date'); // For archived logs

        if ($date) {
            $logFile = "{$logDir}/{$domain}-{$type}.log.{$date}.gz";
        } else {
            $logFile = match ($type) {
                'error' => "{$logDir}/{$domain}-error.log",
                'ssl_access' => "{$logDir}/{$domain}-ssl_access.log",
                default => "{$logDir}/{$domain}-access.log",
            };
        }

        // Verify path is within allowed directory
        $realPath = realpath($logFile);
        if (!$realPath || !str_starts_with($realPath, $logDir)) {
            abort(404, 'Log file not found');
        }

        $filename = basename($logFile);

        return response()->streamDownload(function () use ($logFile) {
            $result = Process::run("sudo cat {$logFile}");
            echo $result->output();
        }, $filename, [
            'Content-Type' => str_ends_with($logFile, '.gz') ? 'application/gzip' : 'text/plain',
        ]);
    }

    /**
     * Search logs
     */
    public function search(Request $request)
    {
        $account = $request->user()->account;
        $logDir = "/var/log/apache2/domlogs";
        $domain = $account->domain;

        $query = $request->input('query');
        $type = $request->input('type', 'access');
        $lines = min($request->input('lines', 500), 2000);

        if (!$query || strlen($query) < 3) {
            return $this->error('Query must be at least 3 characters', 422);
        }

        $logFile = match ($type) {
            'error' => "{$logDir}/{$domain}-error.log",
            default => "{$logDir}/{$domain}-access.log",
        };

        if (!file_exists($logFile)) {
            return $this->error('Log file not found', 404);
        }

        $escapedQuery = escapeshellarg($query);
        $result = Process::run("sudo grep -i {$escapedQuery} {$logFile} | tail -{$lines}");

        $entries = [];
        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;

            if ($type === 'error') {
                $entries[] = $this->parseErrorLogLine($line);
            } else {
                $entries[] = $this->parseAccessLogLine($line);
            }
        }

        return $this->success([
            'query' => $query,
            'entries' => array_reverse($entries),
            'count' => count($entries),
        ]);
    }

    /**
     * Get log statistics
     */
    public function stats(Request $request)
    {
        $account = $request->user()->account;
        $logDir = "/var/log/apache2/domlogs";
        $domain = $account->domain;

        $logFile = "{$logDir}/{$domain}-access.log";
        $hours = $request->input('hours', 24);

        if (!file_exists($logFile)) {
            return $this->success([
                'total_requests' => 0,
                'unique_ips' => 0,
                'top_urls' => [],
                'top_ips' => [],
                'status_codes' => [],
            ]);
        }

        // Get recent entries
        $result = Process::run("sudo tail -10000 {$logFile}");
        $lines = explode("\n", $result->output());

        $urls = [];
        $ips = [];
        $statusCodes = [];
        $totalRequests = 0;

        foreach ($lines as $line) {
            $parsed = $this->parseAccessLogLine($line);
            if (!$parsed['ip']) continue;

            $totalRequests++;

            // Count URLs
            $url = $parsed['url'] ?? '/';
            $urls[$url] = ($urls[$url] ?? 0) + 1;

            // Count IPs
            $ips[$parsed['ip']] = ($ips[$parsed['ip']] ?? 0) + 1;

            // Count status codes
            $status = $parsed['status'] ?? '0';
            $statusCodes[$status] = ($statusCodes[$status] ?? 0) + 1;
        }

        // Sort and limit
        arsort($urls);
        arsort($ips);
        arsort($statusCodes);

        $topUrls = array_slice(array_map(fn($url, $count) => ['url' => $url, 'count' => $count], array_keys($urls), $urls), 0, 20, true);
        $topIps = array_slice(array_map(fn($ip, $count) => ['ip' => $ip, 'count' => $count], array_keys($ips), $ips), 0, 20, true);

        return $this->success([
            'total_requests' => $totalRequests,
            'unique_ips' => count($ips),
            'top_urls' => array_values($topUrls),
            'top_ips' => array_values($topIps),
            'status_codes' => $statusCodes,
        ]);
    }

    /**
     * Parse access log line (Combined Log Format)
     */
    protected function parseAccessLogLine(string $line): array
    {
        $pattern = '/^(\S+) \S+ \S+ \[([^\]]+)\] "(\S+) ([^"]*) HTTP\/[^"]*" (\d+) (\d+|-) "([^"]*)" "([^"]*)"/';

        if (!preg_match($pattern, $line, $matches)) {
            return [
                'raw' => $line,
                'ip' => null,
            ];
        }

        return [
            'ip' => $matches[1],
            'timestamp' => $matches[2],
            'method' => $matches[3],
            'url' => $matches[4],
            'status' => $matches[5],
            'size' => $matches[6] === '-' ? 0 : (int) $matches[6],
            'referer' => $matches[7] === '-' ? null : $matches[7],
            'user_agent' => $matches[8],
        ];
    }

    /**
     * Parse error log line
     */
    protected function parseErrorLogLine(string $line): array
    {
        $pattern = '/^\[([^\]]+)\] \[([^\]]+)\] \[pid (\d+)\](?:\[client ([^\]]+)\])? (.+)$/';

        if (!preg_match($pattern, $line, $matches)) {
            return [
                'raw' => $line,
                'timestamp' => null,
            ];
        }

        return [
            'timestamp' => $matches[1],
            'level' => $matches[2],
            'pid' => $matches[3],
            'client' => $matches[4] ?? null,
            'message' => $matches[5],
        ];
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
