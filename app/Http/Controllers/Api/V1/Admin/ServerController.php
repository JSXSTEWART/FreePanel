<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class ServerController extends Controller
{
    public function info()
    {
        $info = Cache::remember('server_info', 300, function () {
            return [
                'hostname' => gethostname(),
                'os' => $this->getOsInfo(),
                'kernel' => php_uname('r'),
                'architecture' => php_uname('m'),
                'uptime' => $this->getUptime(),
                'php_version' => PHP_VERSION,
                'freepanel_version' => config('freepanel.version', '1.0.0'),
            ];
        });

        return $this->success($info);
    }

    public function load()
    {
        $load = [
            'cpu' => $this->getCpuInfo(),
            'memory' => $this->getMemoryInfo(),
            'swap' => $this->getSwapInfo(),
            'load_average' => sys_getloadavg(),
            'processes' => $this->getProcessCount(),
        ];

        return $this->success($load);
    }

    public function disk()
    {
        $disks = [];

        // Get mount points
        $output = shell_exec('df -BM 2>/dev/null');
        $lines = explode("\n", trim($output));
        array_shift($lines); // Remove header

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 6) {
                $mountPoint = $parts[5];

                // Only include relevant mount points
                if (!$this->isRelevantMount($mountPoint)) {
                    continue;
                }

                $disks[] = [
                    'filesystem' => $parts[0],
                    'mount' => $mountPoint,
                    'total' => (int) $parts[1],
                    'used' => (int) $parts[2],
                    'available' => (int) $parts[3],
                    'percent' => (int) rtrim($parts[4], '%'),
                ];
            }
        }

        return $this->success($disks);
    }

    public function network()
    {
        $interfaces = [];

        // Get network interfaces
        $output = shell_exec('ip -j addr 2>/dev/null');
        $data = json_decode($output, true) ?? [];

        foreach ($data as $iface) {
            if ($iface['ifname'] === 'lo') {
                continue;
            }

            $addresses = [];
            foreach ($iface['addr_info'] ?? [] as $addr) {
                $addresses[] = [
                    'family' => $addr['family'],
                    'address' => $addr['local'],
                    'prefix' => $addr['prefixlen'],
                ];
            }

            $interfaces[] = [
                'name' => $iface['ifname'],
                'state' => $iface['operstate'] ?? 'unknown',
                'mac' => $iface['address'] ?? null,
                'mtu' => $iface['mtu'] ?? null,
                'addresses' => $addresses,
            ];
        }

        return $this->success($interfaces);
    }

    public function processes()
    {
        $processes = [];

        $output = shell_exec('ps aux --sort=-%mem 2>/dev/null | head -20');
        $lines = explode("\n", trim($output));
        array_shift($lines); // Remove header

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', $line, 11);
            if (count($parts) >= 11) {
                $processes[] = [
                    'user' => $parts[0],
                    'pid' => (int) $parts[1],
                    'cpu' => (float) $parts[2],
                    'memory' => (float) $parts[3],
                    'vsz' => (int) $parts[4],
                    'rss' => (int) $parts[5],
                    'stat' => $parts[7],
                    'start' => $parts[8],
                    'time' => $parts[9],
                    'command' => $parts[10],
                ];
            }
        }

        return $this->success($processes);
    }

    public function phpInfo()
    {
        $info = [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'extensions' => get_loaded_extensions(),
            'ini_path' => php_ini_loaded_file(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'opcache' => [
                'enabled' => function_exists('opcache_get_status'),
                'status' => function_exists('opcache_get_status') ? opcache_get_status(false) : null,
            ],
        ];

        return $this->success($info);
    }

    public function mysqlInfo()
    {
        try {
            $pdo = \DB::connection()->getPdo();

            $info = [
                'version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
                'uptime' => $pdo->query("SHOW STATUS LIKE 'Uptime'")->fetch()['Value'] ?? 0,
                'connections' => $pdo->query("SHOW STATUS LIKE 'Threads_connected'")->fetch()['Value'] ?? 0,
                'max_connections' => $pdo->query("SHOW VARIABLES LIKE 'max_connections'")->fetch()['Value'] ?? 0,
                'queries' => $pdo->query("SHOW STATUS LIKE 'Queries'")->fetch()['Value'] ?? 0,
            ];

            return $this->success($info);
        } catch (\Exception $e) {
            return $this->error('Failed to get MySQL info: ' . $e->getMessage(), 500);
        }
    }

    protected function getOsInfo(): array
    {
        $release = [];

        if (file_exists('/etc/os-release')) {
            $content = file_get_contents('/etc/os-release');
            foreach (explode("\n", $content) as $line) {
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $release[strtolower($key)] = trim($value, '"');
                }
            }
        }

        return [
            'name' => $release['pretty_name'] ?? php_uname('s'),
            'id' => $release['id'] ?? 'unknown',
            'version' => $release['version_id'] ?? 'unknown',
        ];
    }

    protected function getUptime(): array
    {
        $uptimeSeconds = (int) file_get_contents('/proc/uptime');

        $days = floor($uptimeSeconds / 86400);
        $hours = floor(($uptimeSeconds % 86400) / 3600);
        $minutes = floor(($uptimeSeconds % 3600) / 60);

        return [
            'seconds' => $uptimeSeconds,
            'formatted' => "{$days}d {$hours}h {$minutes}m",
        ];
    }

    protected function getCpuInfo(): array
    {
        $cpuInfo = [];

        if (file_exists('/proc/cpuinfo')) {
            $content = file_get_contents('/proc/cpuinfo');
            preg_match('/model name\s*:\s*(.+)/i', $content, $matches);
            $cpuInfo['model'] = $matches[1] ?? 'Unknown';

            preg_match_all('/processor\s*:/i', $content, $matches);
            $cpuInfo['cores'] = count($matches[0]);
        }

        // Get CPU usage
        $stat1 = file('/proc/stat');
        usleep(100000);
        $stat2 = file('/proc/stat');

        $info1 = explode(' ', preg_replace('/\s+/', ' ', $stat1[0]));
        $info2 = explode(' ', preg_replace('/\s+/', ' ', $stat2[0]));

        $diff = [
            'user' => $info2[1] - $info1[1],
            'nice' => $info2[2] - $info1[2],
            'system' => $info2[3] - $info1[3],
            'idle' => $info2[4] - $info1[4],
        ];

        $total = array_sum($diff);
        $cpuInfo['usage'] = $total > 0 ? round(100 - ($diff['idle'] / $total * 100), 2) : 0;

        return $cpuInfo;
    }

    protected function getMemoryInfo(): array
    {
        $memInfo = [];

        if (file_exists('/proc/meminfo')) {
            $content = file_get_contents('/proc/meminfo');

            preg_match('/MemTotal:\s*(\d+)/i', $content, $matches);
            $memInfo['total'] = (int) ($matches[1] ?? 0) * 1024;

            preg_match('/MemFree:\s*(\d+)/i', $content, $matches);
            $free = (int) ($matches[1] ?? 0) * 1024;

            preg_match('/Buffers:\s*(\d+)/i', $content, $matches);
            $buffers = (int) ($matches[1] ?? 0) * 1024;

            preg_match('/Cached:\s*(\d+)/i', $content, $matches);
            $cached = (int) ($matches[1] ?? 0) * 1024;

            $memInfo['free'] = $free;
            $memInfo['available'] = $free + $buffers + $cached;
            $memInfo['used'] = $memInfo['total'] - $memInfo['available'];
            $memInfo['percent'] = $memInfo['total'] > 0
                ? round(($memInfo['used'] / $memInfo['total']) * 100, 2)
                : 0;
        }

        return $memInfo;
    }

    protected function getSwapInfo(): array
    {
        $swapInfo = ['total' => 0, 'used' => 0, 'free' => 0, 'percent' => 0];

        if (file_exists('/proc/meminfo')) {
            $content = file_get_contents('/proc/meminfo');

            preg_match('/SwapTotal:\s*(\d+)/i', $content, $matches);
            $swapInfo['total'] = (int) ($matches[1] ?? 0) * 1024;

            preg_match('/SwapFree:\s*(\d+)/i', $content, $matches);
            $swapInfo['free'] = (int) ($matches[1] ?? 0) * 1024;

            $swapInfo['used'] = $swapInfo['total'] - $swapInfo['free'];
            $swapInfo['percent'] = $swapInfo['total'] > 0
                ? round(($swapInfo['used'] / $swapInfo['total']) * 100, 2)
                : 0;
        }

        return $swapInfo;
    }

    protected function getProcessCount(): int
    {
        $output = shell_exec('ps aux 2>/dev/null | wc -l');
        return max(0, (int) $output - 1);
    }

    protected function isRelevantMount(string $mountPoint): bool
    {
        $relevant = ['/', '/home', '/var', '/tmp', '/usr', '/opt'];

        return in_array($mountPoint, $relevant) || str_starts_with($mountPoint, '/home/');
    }
}
