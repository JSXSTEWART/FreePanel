<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessLogController extends Controller
{
    protected string $logDir = '/var/log/apache2/domlogs';

    /**
     * Domains on an account can be lightly validated: the domain is
     * stored in the DB at account-creation time, but we still guard
     * against surprise values before we stitch it into a path and
     * shell-adjacent code.
     */
    protected function assertValidDomain(string $domain): void
    {
        if (! preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]{0,253})[A-Za-z0-9]$/', $domain)) {
            abort(404, 'Log file not found');
        }
    }

    /**
     * Canonicalize a log file path and confirm it lives under our
     * managed log directory. Returns null for missing/invalid files.
     */
    protected function resolveLogFile(string $candidate): ?string
    {
        $real = realpath($candidate);
        if ($real === false) {
            return null;
        }
        if (! str_starts_with($real, $this->logDir.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    /**
     * Get access log overview
     */
    public function index(Request $request)
    {
        $account = $request->user()->account;
        $domain = $account->domain;
        $this->assertValidDomain($domain);

        $logs = [];

        // Live logs
        foreach (['access', 'error', 'ssl_access'] as $kind) {
            $log = "{$this->logDir}/{$domain}-{$kind}.log";
            if (file_exists($log)) {
                $logs[] = [
                    'name' => basename($log),
                    'path' => $log,
                    'size' => filesize($log) ?: 0,
                    'size_human' => $this->formatBytes(filesize($log) ?: 0),
                    'modified' => date('M j H:i', filemtime($log) ?: 0),
                    'type' => $kind === 'error' ? 'error' : 'access',
                ];
            }
        }

        // Archived logs — enumerate via glob, not a shell `ls`.
        foreach (glob("{$this->logDir}/{$domain}*.gz") ?: [] as $archive) {
            $logs[] = [
                'name' => basename($archive),
                'path' => $archive,
                'size' => filesize($archive) ?: 0,
                'size_human' => $this->formatBytes(filesize($archive) ?: 0),
                'modified' => date('M j H:i', filemtime($archive) ?: 0),
                'type' => 'archived',
            ];
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
        $domain = $account->domain;
        $this->assertValidDomain($domain);

        $type = in_array($request->input('type'), ['access', 'error', 'ssl_access'], true)
            ? $request->input('type')
            : 'access';
        $lines = max(1, min((int) $request->input('lines', 100), 1000));

        $logFile = $this->resolveLogFile("{$this->logDir}/{$domain}-{$type}.log");
        if ($logFile === null) {
            return $this->error('Log file not found', 404);
        }

        $result = Process::run(['sudo', 'tail', '-n', (string) $lines, $logFile]);

        $entries = [];
        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $entries[] = $type === 'error'
                ? $this->parseErrorLogLine($line)
                : $this->parseAccessLogLine($line);
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
        $domain = $account->domain;
        $this->assertValidDomain($domain);

        $type = in_array($request->input('type'), ['access', 'error', 'ssl_access'], true)
            ? $request->input('type')
            : 'access';
        $date = $request->input('date'); // For archived logs — must match a safe pattern.

        if ($date !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(422, 'Invalid date');
        }

        $candidate = $date
            ? "{$this->logDir}/{$domain}-{$type}.log.{$date}.gz"
            : "{$this->logDir}/{$domain}-{$type}.log";

        $logFile = $this->resolveLogFile($candidate);
        if ($logFile === null) {
            abort(404, 'Log file not found');
        }

        $filename = basename($logFile);
        $contentType = str_ends_with($logFile, '.gz') ? 'application/gzip' : 'text/plain';

        return response()->streamDownload(function () use ($logFile) {
            $result = Process::run(['sudo', 'cat', $logFile]);
            echo $result->output();
        }, $filename, [
            'Content-Type' => $contentType,
        ]);
    }

    /**
     * Search logs
     */
    public function search(Request $request)
    {
        $account = $request->user()->account;
        $domain = $account->domain;
        $this->assertValidDomain($domain);

        $query = (string) $request->input('query', '');
        $type = $request->input('type') === 'error' ? 'error' : 'access';
        $lines = max(1, min((int) $request->input('lines', 500), 2000));

        if (strlen($query) < 3 || strlen($query) > 255) {
            return $this->error('Query must be between 3 and 255 characters', 422);
        }

        $logFile = $this->resolveLogFile("{$this->logDir}/{$domain}-{$type}.log");
        if ($logFile === null) {
            return $this->error('Log file not found', 404);
        }

        // grep with array args + --fixed-strings so the query is always
        // treated as a literal substring (never a regex that could ReDoS
        // the log reader).
        $grep = Process::run(['sudo', 'grep', '-iF', '--', $query, $logFile]);
        $matches = explode("\n", $grep->output());

        // Keep the last $lines matches — cheaper than piping through tail.
        if (count($matches) > $lines) {
            $matches = array_slice($matches, -$lines);
        }

        $entries = [];
        foreach ($matches as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $entries[] = $type === 'error'
                ? $this->parseErrorLogLine($line)
                : $this->parseAccessLogLine($line);
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
        $domain = $account->domain;
        $this->assertValidDomain($domain);

        $logFile = $this->resolveLogFile("{$this->logDir}/{$domain}-access.log");
        if ($logFile === null) {
            return $this->success([
                'total_requests' => 0,
                'unique_ips' => 0,
                'top_urls' => [],
                'top_ips' => [],
                'status_codes' => [],
            ]);
        }

        // Get recent entries (capped)
        $result = Process::run(['sudo', 'tail', '-n', '10000', $logFile]);
        $lines = explode("\n", $result->output());

        $urls = [];
        $ips = [];
        $statusCodes = [];
        $totalRequests = 0;

        foreach ($lines as $line) {
            $parsed = $this->parseAccessLogLine($line);
            if (! $parsed['ip']) {
                continue;
            }

            $totalRequests++;

            $url = $parsed['url'] ?? '/';
            $urls[$url] = ($urls[$url] ?? 0) + 1;

            $ips[$parsed['ip']] = ($ips[$parsed['ip']] ?? 0) + 1;

            $status = $parsed['status'] ?? '0';
            $statusCodes[$status] = ($statusCodes[$status] ?? 0) + 1;
        }

        arsort($urls);
        arsort($ips);
        arsort($statusCodes);

        $topUrls = array_slice(array_map(fn ($url, $count) => ['url' => $url, 'count' => $count], array_keys($urls), $urls), 0, 20, true);
        $topIps = array_slice(array_map(fn ($ip, $count) => ['ip' => $ip, 'count' => $count], array_keys($ips), $ips), 0, 20, true);

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

        if (! preg_match($pattern, $line, $matches)) {
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

        if (! preg_match($pattern, $line, $matches)) {
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

        return round($bytes, 2).' '.$units[$i];
    }
}
