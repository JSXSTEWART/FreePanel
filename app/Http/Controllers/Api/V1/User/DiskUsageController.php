<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class DiskUsageController extends Controller
{
    /**
     * Get disk usage summary
     */
    public function index(Request $request)
    {
        $account = $request->user()->account;
        $homeDir = "/home/{$account->username}";

        // Get total usage
        $result = Process::run("du -sb {$homeDir} 2>/dev/null | cut -f1");
        $totalBytes = (int) trim($result->output());

        // Get quota info
        $quota = $account->package->disk_quota ?? null;
        $quotaBytes = $quota ? $quota * 1024 * 1024 : null; // Convert MB to bytes

        // Get breakdown by top-level directories
        $result = Process::run("du -sb {$homeDir}/* 2>/dev/null | sort -rn");
        $breakdown = [];

        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;
            [$size, $path] = preg_split('/\s+/', $line, 2);
            $breakdown[] = [
                'path' => basename($path),
                'size' => (int) $size,
                'size_human' => $this->formatBytes((int) $size),
                'percentage' => $totalBytes > 0 ? round(((int) $size / $totalBytes) * 100, 2) : 0,
            ];
        }

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
        $homeDir = "/home/{$account->username}";
        $path = $request->input('path', '');

        // Security check
        $fullPath = realpath("{$homeDir}/{$path}");
        if (!$fullPath || !str_starts_with($fullPath, $homeDir)) {
            return $this->error('Invalid path', 400);
        }

        // Get directory contents with sizes
        $result = Process::run("du -sb {$fullPath}/* 2>/dev/null | sort -rn");
        $items = [];

        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;
            [$size, $itemPath] = preg_split('/\s+/', $line, 2);
            $items[] = [
                'name' => basename($itemPath),
                'path' => str_replace($homeDir . '/', '', $itemPath),
                'size' => (int) $size,
                'size_human' => $this->formatBytes((int) $size),
                'is_directory' => is_dir($itemPath),
            ];
        }

        // Get total for this directory
        $result = Process::run("du -sb {$fullPath} 2>/dev/null | cut -f1");
        $totalBytes = (int) trim($result->output());

        return $this->success([
            'path' => str_replace($homeDir . '/', '', $fullPath),
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
            $result = Process::run(
                "sudo /usr/bin/mysql -N -e \"SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = '{$database->name}'\""
            );
            $size = (int) trim($result->output());

            $usage[] = [
                'name' => $database->name,
                'size' => $size,
                'size_human' => $this->formatBytes($size),
            ];
        }

        // Sort by size descending
        usort($usage, fn($a, $b) => $b['size'] - $a['size']);

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
        $mailDir = "/var/mail/vhosts/{$account->domain}";
        $usage = [];

        if (is_dir($mailDir)) {
            $result = Process::run("du -sb {$mailDir}/* 2>/dev/null | sort -rn");

            foreach (explode("\n", $result->output()) as $line) {
                if (empty(trim($line))) continue;
                [$size, $path] = preg_split('/\s+/', $line, 2);
                $usage[] = [
                    'account' => basename($path) . '@' . $account->domain,
                    'size' => (int) $size,
                    'size_human' => $this->formatBytes((int) $size),
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
        $homeDir = "/home/{$account->username}";
        $limit = $request->input('limit', 50);

        $result = Process::run(
            "find {$homeDir} -type f -printf '%s %p\n' 2>/dev/null | sort -rn | head -{$limit}"
        );

        $files = [];
        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;
            [$size, $path] = preg_split('/\s+/', $line, 2);
            $files[] = [
                'path' => str_replace($homeDir . '/', '', $path),
                'size' => (int) $size,
                'size_human' => $this->formatBytes((int) $size),
                'extension' => pathinfo($path, PATHINFO_EXTENSION),
            ];
        }

        return $this->success([
            'files' => $files,
        ]);
    }

    /**
     * Get usage by file type
     */
    public function byType(Request $request)
    {
        $account = $request->user()->account;
        $homeDir = "/home/{$account->username}";

        // Get usage by extension
        $result = Process::run(
            "find {$homeDir} -type f -printf '%s %f\n' 2>/dev/null"
        );

        $byExtension = [];
        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;
            [$size, $filename] = preg_split('/\s+/', $line, 2);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'no extension';

            if (!isset($byExtension[$ext])) {
                $byExtension[$ext] = ['count' => 0, 'size' => 0];
            }
            $byExtension[$ext]['count']++;
            $byExtension[$ext]['size'] += (int) $size;
        }

        // Convert to array and sort
        $types = [];
        foreach ($byExtension as $ext => $data) {
            $types[] = [
                'extension' => $ext,
                'count' => $data['count'],
                'size' => $data['size'],
                'size_human' => $this->formatBytes($data['size']),
            ];
        }

        usort($types, fn($a, $b) => $b['size'] - $a['size']);

        return $this->success([
            'by_type' => array_slice($types, 0, 30),
        ]);
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

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
