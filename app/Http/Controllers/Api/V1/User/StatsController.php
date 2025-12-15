<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class StatsController extends Controller
{
    public function bandwidth(Request $request)
    {
        $account = $request->user()->account;
        $period = $request->get('period', 'month'); // day, week, month, year

        $bandwidth = $this->getBandwidthStats($account, $period);

        return $this->success([
            'current_usage' => $account->bandwidth_used,
            'limit' => $account->package->bandwidth ?? -1,
            'usage_percent' => $account->bandwidth_usage_percent,
            'history' => $bandwidth['history'],
            'by_domain' => $bandwidth['by_domain'],
            'period' => $period,
        ]);
    }

    public function visitors(Request $request)
    {
        $account = $request->user()->account;
        $period = $request->get('period', 'month');
        $domain = $request->get('domain');

        $visitors = $this->getVisitorStats($account, $period, $domain);

        return $this->success([
            'total_visits' => $visitors['total'],
            'unique_visitors' => $visitors['unique'],
            'page_views' => $visitors['page_views'],
            'history' => $visitors['history'],
            'top_pages' => $visitors['top_pages'],
            'top_referrers' => $visitors['top_referrers'],
            'browsers' => $visitors['browsers'],
            'countries' => $visitors['countries'],
            'period' => $period,
        ]);
    }

    public function errors(Request $request)
    {
        $account = $request->user()->account;
        $period = $request->get('period', 'week');
        $domain = $request->get('domain');
        $limit = min($request->get('limit', 100), 500);

        $errors = $this->getErrorStats($account, $period, $domain, $limit);

        return $this->success([
            'total_errors' => $errors['total'],
            'by_status_code' => $errors['by_status'],
            'recent_errors' => $errors['recent'],
            'top_error_urls' => $errors['top_urls'],
            'period' => $period,
        ]);
    }

    public function resourceUsage(Request $request)
    {
        $account = $request->user()->account;

        $usage = $this->getResourceUsage($account);

        return $this->success([
            'disk' => [
                'used' => $account->disk_used,
                'limit' => $account->package->disk_quota ?? -1,
                'percent' => $account->disk_usage_percent,
                'breakdown' => $usage['disk_breakdown'],
            ],
            'bandwidth' => [
                'used' => $account->bandwidth_used,
                'limit' => $account->package->bandwidth ?? -1,
                'percent' => $account->bandwidth_usage_percent,
            ],
            'inodes' => $usage['inodes'],
            'quotas' => [
                'email_accounts' => [
                    'used' => $account->emailAccountsCount(),
                    'limit' => $account->package->max_email_accounts ?? -1,
                ],
                'databases' => [
                    'used' => $account->databases()->count(),
                    'limit' => $account->package->max_databases ?? -1,
                ],
                'domains' => [
                    'used' => $account->domains()->where('is_main', false)->count(),
                    'limit' => $account->package->max_addon_domains ?? -1,
                ],
                'subdomains' => [
                    'used' => $this->getSubdomainCount($account),
                    'limit' => $account->package->max_subdomains ?? -1,
                ],
                'ftp_accounts' => [
                    'used' => $account->ftpAccounts()->count(),
                    'limit' => $account->package->max_ftp_accounts ?? -1,
                ],
            ],
        ]);
    }

    protected function getBandwidthStats(Account $account, string $period): array
    {
        $cacheKey = "bandwidth_stats_{$account->id}_{$period}";

        return Cache::remember($cacheKey, 300, function () use ($account, $period) {
            $history = [];
            $byDomain = [];

            // Get domains for this account
            $domains = $account->domains;

            foreach ($domains as $domain) {
                $domainBandwidth = $this->parseDomainBandwidth($account, $domain, $period);
                $byDomain[$domain->name] = $domainBandwidth['total'];

                // Aggregate history
                foreach ($domainBandwidth['history'] as $date => $bytes) {
                    $history[$date] = ($history[$date] ?? 0) + $bytes;
                }
            }

            ksort($history);

            return [
                'history' => $history,
                'by_domain' => $byDomain,
            ];
        });
    }

    protected function parseDomainBandwidth(Account $account, Domain $domain, string $period): array
    {
        $logPath = "/var/log/apache2/domlogs/{$domain->name}-bytes_log";

        if (!File::exists($logPath)) {
            $logPath = "/var/log/httpd/domlogs/{$domain->name}-bytes_log";
        }

        $history = [];
        $total = 0;

        if (File::exists($logPath)) {
            $startDate = $this->getPeriodStartDate($period);

            $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                foreach ($lines as $line) {
                    // Format: timestamp bytes
                    $parts = preg_split('/\s+/', trim($line));
                    if (count($parts) >= 2) {
                        $timestamp = (int) $parts[0];
                        $bytes = (int) $parts[1];

                        if ($timestamp >= $startDate->timestamp) {
                            $date = date('Y-m-d', $timestamp);
                            $history[$date] = ($history[$date] ?? 0) + $bytes;
                            $total += $bytes;
                        }
                    }
                }
            }
        }

        return [
            'total' => $total,
            'history' => $history,
        ];
    }

    protected function getVisitorStats(Account $account, string $period, ?string $domainFilter): array
    {
        $cacheKey = "visitor_stats_{$account->id}_{$period}_{$domainFilter}";

        return Cache::remember($cacheKey, 300, function () use ($account, $period, $domainFilter) {
            $stats = [
                'total' => 0,
                'unique' => 0,
                'page_views' => 0,
                'history' => [],
                'top_pages' => [],
                'top_referrers' => [],
                'browsers' => [],
                'countries' => [],
            ];

            $domains = $domainFilter
                ? $account->domains()->where('name', $domainFilter)->get()
                : $account->domains;

            $visitors = [];
            $pages = [];
            $referrers = [];
            $browsers = [];

            foreach ($domains as $domain) {
                $logPath = $this->getAccessLogPath($domain);

                if (File::exists($logPath)) {
                    $this->parseAccessLog(
                        $logPath,
                        $this->getPeriodStartDate($period),
                        $stats,
                        $visitors,
                        $pages,
                        $referrers,
                        $browsers
                    );
                }
            }

            $stats['unique'] = count($visitors);

            // Sort and limit top items
            arsort($pages);
            arsort($referrers);
            arsort($browsers);

            $stats['top_pages'] = array_slice($pages, 0, 10, true);
            $stats['top_referrers'] = array_slice($referrers, 0, 10, true);
            $stats['browsers'] = array_slice($browsers, 0, 5, true);

            ksort($stats['history']);

            return $stats;
        });
    }

    protected function parseAccessLog(
        string $logPath,
        \DateTime $startDate,
        array &$stats,
        array &$visitors,
        array &$pages,
        array &$referrers,
        array &$browsers
    ): void {
        $handle = @fopen($logPath, 'r');
        if (!$handle) {
            return;
        }

        // Read last 100MB max
        $maxBytes = 100 * 1024 * 1024;
        $fileSize = filesize($logPath);
        if ($fileSize > $maxBytes) {
            fseek($handle, $fileSize - $maxBytes);
            fgets($handle); // Skip partial line
        }

        while (($line = fgets($handle)) !== false) {
            // Combined log format parsing
            if (preg_match('/^(\S+).*\[([^\]]+)\].*"(?:GET|POST|HEAD)\s+([^\s"]+).*"\s+(\d+)\s+(\d+)\s+"([^"]*)"\s+"([^"]*)"/', $line, $matches)) {
                $ip = $matches[1];
                $dateStr = $matches[2];
                $url = $matches[3];
                $status = (int) $matches[4];
                $bytes = (int) $matches[5];
                $referrer = $matches[6];
                $userAgent = $matches[7];

                // Parse date
                $logDate = \DateTime::createFromFormat('d/M/Y:H:i:s O', $dateStr);
                if (!$logDate || $logDate < $startDate) {
                    continue;
                }

                $dateKey = $logDate->format('Y-m-d');

                // Count visit
                $stats['total']++;
                $stats['page_views']++;
                $stats['history'][$dateKey] = ($stats['history'][$dateKey] ?? 0) + 1;

                // Track unique visitors
                $visitors[$ip] = true;

                // Track pages (exclude static assets)
                if (!preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/i', $url)) {
                    $pages[$url] = ($pages[$url] ?? 0) + 1;
                }

                // Track referrers (exclude self and empty)
                if ($referrer !== '-' && !empty($referrer)) {
                    $referrerHost = parse_url($referrer, PHP_URL_HOST);
                    if ($referrerHost) {
                        $referrers[$referrerHost] = ($referrers[$referrerHost] ?? 0) + 1;
                    }
                }

                // Track browsers
                $browser = $this->detectBrowser($userAgent);
                $browsers[$browser] = ($browsers[$browser] ?? 0) + 1;
            }
        }

        fclose($handle);
    }

    protected function getErrorStats(Account $account, string $period, ?string $domainFilter, int $limit): array
    {
        $cacheKey = "error_stats_{$account->id}_{$period}_{$domainFilter}";

        return Cache::remember($cacheKey, 300, function () use ($account, $period, $domainFilter, $limit) {
            $stats = [
                'total' => 0,
                'by_status' => [],
                'recent' => [],
                'top_urls' => [],
            ];

            $domains = $domainFilter
                ? $account->domains()->where('name', $domainFilter)->get()
                : $account->domains;

            $errorUrls = [];
            $errors = [];

            foreach ($domains as $domain) {
                $logPath = $this->getErrorLogPath($domain);

                if (File::exists($logPath)) {
                    $this->parseErrorLog(
                        $logPath,
                        $this->getPeriodStartDate($period),
                        $stats,
                        $errors,
                        $errorUrls,
                        $domain->name
                    );
                }
            }

            // Sort by timestamp descending
            usort($errors, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

            $stats['recent'] = array_slice($errors, 0, $limit);

            // Top error URLs
            arsort($errorUrls);
            $stats['top_urls'] = array_slice($errorUrls, 0, 10, true);

            return $stats;
        });
    }

    protected function parseErrorLog(
        string $logPath,
        \DateTime $startDate,
        array &$stats,
        array &$errors,
        array &$errorUrls,
        string $domainName
    ): void {
        $handle = @fopen($logPath, 'r');
        if (!$handle) {
            return;
        }

        // Read last 50MB max
        $maxBytes = 50 * 1024 * 1024;
        $fileSize = filesize($logPath);
        if ($fileSize > $maxBytes) {
            fseek($handle, $fileSize - $maxBytes);
            fgets($handle);
        }

        while (($line = fgets($handle)) !== false) {
            // Apache error log format
            if (preg_match('/^\[([^\]]+)\]\s+\[([^\]]+)\]\s+\[client\s+([^\]]+)\]\s+(.+)$/', $line, $matches)) {
                $dateStr = $matches[1];
                $level = $matches[2];
                $client = $matches[3];
                $message = $matches[4];

                $logDate = \DateTime::createFromFormat('D M d H:i:s.u Y', $dateStr) ?:
                           \DateTime::createFromFormat('D M d H:i:s Y', $dateStr);

                if (!$logDate || $logDate < $startDate) {
                    continue;
                }

                $stats['total']++;
                $stats['by_status'][$level] = ($stats['by_status'][$level] ?? 0) + 1;

                // Extract URL if present
                if (preg_match('/(?:File does not exist|script not found|client denied):\s+(.+)/', $message, $urlMatch)) {
                    $errorUrls[$urlMatch[1]] = ($errorUrls[$urlMatch[1]] ?? 0) + 1;
                }

                $errors[] = [
                    'timestamp' => $logDate->getTimestamp(),
                    'datetime' => $logDate->format('Y-m-d H:i:s'),
                    'level' => $level,
                    'client' => $client,
                    'message' => substr($message, 0, 500),
                    'domain' => $domainName,
                ];
            }
        }

        fclose($handle);
    }

    protected function getResourceUsage(Account $account): array
    {
        $homeDir = $account->home_directory;

        // Disk breakdown by directory
        $breakdown = [];
        $directories = ['public_html', 'mail', 'logs', 'tmp', 'backups'];

        foreach ($directories as $dir) {
            $path = "{$homeDir}/{$dir}";
            if (File::isDirectory($path)) {
                $breakdown[$dir] = $this->getDirectorySize($path);
            } else {
                $breakdown[$dir] = 0;
            }
        }

        // Calculate "other"
        $totalKnown = array_sum($breakdown);
        $breakdown['other'] = max(0, $account->disk_used - $totalKnown);

        // Inode usage
        $inodes = $this->getInodeUsage($homeDir);

        return [
            'disk_breakdown' => $breakdown,
            'inodes' => $inodes,
        ];
    }

    protected function getDirectorySize(string $path): int
    {
        $size = 0;

        $output = @shell_exec("du -sb " . escapeshellarg($path) . " 2>/dev/null");
        if ($output && preg_match('/^(\d+)/', $output, $matches)) {
            $size = (int) $matches[1];
        }

        return $size;
    }

    protected function getInodeUsage(string $path): array
    {
        $used = 0;
        $limit = 0;

        // Count files
        $output = @shell_exec("find " . escapeshellarg($path) . " -type f 2>/dev/null | wc -l");
        if ($output) {
            $used = (int) trim($output);
        }

        // Get quota if set
        $quotaOutput = @shell_exec("quota -u " . escapeshellarg(basename($path)) . " 2>/dev/null");
        if ($quotaOutput && preg_match('/\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $quotaOutput, $matches)) {
            $limit = (int) $matches[5]; // soft limit for inodes
        }

        return [
            'used' => $used,
            'limit' => $limit > 0 ? $limit : -1,
            'percent' => $limit > 0 ? round(($used / $limit) * 100, 2) : 0,
        ];
    }

    protected function getSubdomainCount(Account $account): int
    {
        $count = 0;
        foreach ($account->domains as $domain) {
            $count += $domain->subdomains()->count();
        }
        return $count;
    }

    protected function getAccessLogPath(Domain $domain): string
    {
        $paths = [
            "/var/log/apache2/domlogs/{$domain->name}-access_log",
            "/var/log/httpd/domlogs/{$domain->name}-access_log",
            "/var/log/apache2/{$domain->name}-access.log",
            "/var/log/nginx/{$domain->name}.access.log",
        ];

        foreach ($paths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return $paths[0];
    }

    protected function getErrorLogPath(Domain $domain): string
    {
        $paths = [
            "/var/log/apache2/domlogs/{$domain->name}-error_log",
            "/var/log/httpd/domlogs/{$domain->name}-error_log",
            "/var/log/apache2/{$domain->name}-error.log",
            "/var/log/nginx/{$domain->name}.error.log",
        ];

        foreach ($paths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return $paths[0];
    }

    protected function getPeriodStartDate(string $period): \DateTime
    {
        $date = new \DateTime();

        switch ($period) {
            case 'day':
                $date->modify('-1 day');
                break;
            case 'week':
                $date->modify('-1 week');
                break;
            case 'year':
                $date->modify('-1 year');
                break;
            case 'month':
            default:
                $date->modify('-1 month');
                break;
        }

        return $date;
    }

    protected function detectBrowser(string $userAgent): string
    {
        $browsers = [
            'Chrome' => '/Chrome\/[\d.]+/',
            'Firefox' => '/Firefox\/[\d.]+/',
            'Safari' => '/Safari\/[\d.]+/',
            'Edge' => '/Edg\/[\d.]+/',
            'Opera' => '/OPR\/[\d.]+/',
            'IE' => '/MSIE|Trident/',
        ];

        foreach ($browsers as $name => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                // Don't match Chrome for Safari
                if ($name === 'Safari' && preg_match('/Chrome/', $userAgent)) {
                    continue;
                }
                return $name;
            }
        }

        if (preg_match('/bot|crawler|spider|crawling/i', $userAgent)) {
            return 'Bot';
        }

        return 'Other';
    }
}
