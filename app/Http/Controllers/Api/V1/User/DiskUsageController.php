<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class DiskUsageController extends Controller
{
    protected function assertValidUsername(string $username): void
    {
        if (! preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $username)) {
            abort(404);
        }
    }

    protected function assertValidDomain(string $domain): void
    {
        if (! preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]{0,253})[A-Za-z0-9]$/', $domain)) {
            abort(404);
        }
    }

    /**
     * MySQL schema names in FreePanel are validated at creation time via
     * MysqlService::validateName. Re-enforce here so any drift in that
     * upstream validation can't yield SQL or shell injection when we
     * lookup disk usage.
     */
    protected function assertValidDatabaseName(string $name): void
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $name)) {
            abort(422, 'Invalid database name');
        }
    }

    /**
     * Get disk usage summary
     */
    public function index(Request $request)
    {
        $account = $request->user()->account;
        $this->assertValidUsername($account->username);
        $homeDir = "/home/{$account->username}";

        // Total usage
        $result = Process::run(['du', '-sb', $homeDir]);
        $totalBytes = (int) strtok(trim($result->output()), "\t");

        // Quota info
        $quota = $account->package->disk_quota ?? null;
        $quotaBytes = $quota ? $quota * 1024 * 1024 : null;

        // Per-top-level-dir breakdown via glob + filesize walk in PHP so
        // nothing is passed to a shell.
        $breakdown = [];
        foreach (glob($homeDir.'/*') ?: [] as $entry) {
            $size = is_dir($entry) ? $this->directorySize($entry) : (filesize($entry) ?: 0);
            $breakdown[] = [
                'path' => basename($entry),
                'size' => $size,
                'size_human' => $this->formatBytes($size),
                'percentage' => $totalBytes > 0 ? round(($size / $totalBytes) * 100, 2) : 0,
            ];
        }
        usort($breakdown, fn ($a, $b) => $b['size'] <=> $a['size']);

        return $this->success([
            'total_usage' => $totalBytes,
            'total_usage_human' => $this->formatBytes($totalBytes),
            'quota' => $quotaBytes,
            'quota_human' => $quotaBytes ? $this->formatBytes($quotaBytes) : 'Unlimited',
            'percentage_used' => $quotaBytes ? round(($totalBytes / $quotaBytes) * 100, 2) : null,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Get detailed usage for a specific directory
     */
    public function directory(Request $request)
    {
        $account = $request->user()->account;
        $this->assertValidUsername($account->username);
        $homeDir = "/home/{$account->username}";
        $path = (string) $request->input('path', '');

        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            return $this->error('Invalid path', 400);
        }

        $fullPath = realpath($path === '' ? $homeDir : "{$homeDir}/{$path}");
        if (! $fullPath || ! (str_starts_with($fullPath, $homeDir.'/') || $fullPath === $homeDir)) {
            return $this->error('Invalid path', 400);
        }

        $result = Process::run(['du', '-sb', '--max-depth=1', $fullPath]);
        $items = [];
        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) {
                continue;
            }
            [$size, $itemPath] = preg_split('/\t/', $line, 2) + [null, null];
            if ($itemPath === null || $itemPath === $fullPath) {
                continue;
            }
            $items[] = [
                'name' => basename($itemPath),
                'path' => str_replace($homeDir.'/', '', $itemPath),
                'size' => (int) $size,
                'size_human' => $this->formatBytes((int) $size),
                'is_directory' => is_dir($itemPath),
            ];
        }

        $result = Process::run(['du', '-sb', $fullPath]);
        $totalBytes = (int) strtok(trim($result->output()), "\t");

        return $this->success([
            'path' => str_replace($homeDir.'/', '', $fullPath),
            'total_size' => $totalBytes,
            'total_size_human' => $this->formatBytes($totalBytes),
            'items' => $items,
        ]);
    }

    /**
     * Get database disk usage
     */
    public function databases(Request $request)
    {
        $account = $request->user()->account;
        $databases = $account->databases;
        $usage = [];

        foreach ($databases as $database) {
            $name = (string) $database->name;
            $this->assertValidDatabaseName($name);

            // Parameterized query against information_schema — never
            // interpolate the db name into a MySQL CLI command.
            $row = DB::connection('mysql_admin')
                ->selectOne(
                    'SELECT COALESCE(SUM(data_length + index_length), 0) AS size
                     FROM information_schema.tables
                     WHERE table_schema = ?',
                    [$name]
                );
            $size = (int) ($row->size ?? 0);

            $usage[] = [
                'name' => $name,
                'size' => $size,
                'size_human' => $this->formatBytes($size),
            ];
        }

        usort($usage, fn ($a, $b) => $b['size'] - $a['size']);

        $totalSize = array_sum(array_column($usage, 'size'));

        return $this->success([
            'databases' => $usage,
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
        ]);
    }

    /**
     * Get email disk usage
     */
    public function emails(Request $request)
    {
        $account = $request->user()->account;
        $this->assertValidDomain($account->domain);
        $mailDir = "/var/mail/vhosts/{$account->domain}";
        $usage = [];

        if (is_dir($mailDir)) {
            foreach (glob($mailDir.'/*') ?: [] as $entry) {
                $size = is_dir($entry) ? $this->directorySize($entry) : (filesize($entry) ?: 0);
                $usage[] = [
                    'account' => basename($entry).'@'.$account->domain,
                    'size' => $size,
                    'size_human' => $this->formatBytes($size),
                ];
            }
        }

        $totalSize = array_sum(array_column($usage, 'size'));

        return $this->success([
            'email_accounts' => $usage,
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
        ]);
    }

    /**
     * Get largest files
     */
    public function largestFiles(Request $request)
    {
        $account = $request->user()->account;
        $this->assertValidUsername($account->username);
        $homeDir = "/home/{$account->username}";
        $limit = max(1, min((int) $request->input('limit', 50), 500));

        // `find` drives discovery; we cap with `head` via an
        // intermediate array rather than a shell pipeline.
        $result = Process::run(['find', $homeDir, '-type', 'f', '-printf', '%s %p\n']);

        $files = [];
        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) {
                continue;
            }
            [$size, $path] = preg_split('/\s+/', $line, 2);
            $files[] = [
                'size' => (int) $size,
                'path' => str_replace($homeDir.'/', '', $path),
                'raw_path' => $path,
            ];
        }

        usort($files, fn ($a, $b) => $b['size'] <=> $a['size']);
        $files = array_slice($files, 0, $limit);

        $files = array_map(fn ($f) => [
            'path' => $f['path'],
            'size' => $f['size'],
            'size_human' => $this->formatBytes($f['size']),
            'extension' => pathinfo($f['raw_path'], PATHINFO_EXTENSION),
        ], $files);

        return $this->success(['files' => $files]);
    }

    /**
     * Get usage by file type
     */
    public function byType(Request $request)
    {
        $account = $request->user()->account;
        $this->assertValidUsername($account->username);
        $homeDir = "/home/{$account->username}";

        $result = Process::run(['find', $homeDir, '-type', 'f', '-printf', '%s %f\n']);

        $byExtension = [];
        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) {
                continue;
            }
            [$size, $filename] = preg_split('/\s+/', $line, 2);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'no extension';

            if (! isset($byExtension[$ext])) {
                $byExtension[$ext] = ['count' => 0, 'size' => 0];
            }
            $byExtension[$ext]['count']++;
            $byExtension[$ext]['size'] += (int) $size;
        }

        $types = [];
        foreach ($byExtension as $ext => $data) {
            $types[] = [
                'extension' => $ext,
                'count' => $data['count'],
                'size' => $data['size'],
                'size_human' => $this->formatBytes($data['size']),
            ];
        }

        usort($types, fn ($a, $b) => $b['size'] - $a['size']);

        return $this->success([
            'by_type' => array_slice($types, 0, 30),
        ]);
    }

    /**
     * Sum total bytes of a directory tree via `du -sb`. Used where a
     * precise breakdown isn't needed — just a single size.
     */
    protected function directorySize(string $path): int
    {
        $result = Process::run(['du', '-sb', $path]);

        return (int) strtok(trim($result->output()), "\t");
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

        return round($bytes, 2).' '.$units[$i];
    }
}
