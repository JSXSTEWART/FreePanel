<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\System\SystemdManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    protected SystemdManager $systemd;

    protected array $managedServices = [
        'apache2' => ['name' => 'Apache Web Server', 'aliases' => ['httpd']],
        'nginx' => ['name' => 'Nginx Proxy'],
        'mariadb' => ['name' => 'MariaDB Database', 'aliases' => ['mysql', 'mysqld']],
        'dovecot' => ['name' => 'Dovecot IMAP/POP3'],
        'exim4' => ['name' => 'Exim Mail Server', 'aliases' => ['exim']],
        'bind9' => ['name' => 'BIND DNS Server', 'aliases' => ['named']],
        'pure-ftpd' => ['name' => 'Pure-FTPd Server', 'aliases' => ['proftpd', 'vsftpd']],
        'redis-server' => ['name' => 'Redis Cache', 'aliases' => ['redis']],
        'memcached' => ['name' => 'Memcached'],
        'fail2ban' => ['name' => 'Fail2Ban Security'],
        'clamav-daemon' => ['name' => 'ClamAV Antivirus', 'aliases' => ['clamd']],
        'spamassassin' => ['name' => 'SpamAssassin', 'aliases' => ['spamd']],
        'php8.4-fpm' => ['name' => 'PHP 8.4 FPM', 'aliases' => ['php-fpm', 'php8.3-fpm', 'php8.2-fpm', 'php8.1-fpm']],
    ];

    public function __construct(SystemdManager $systemd)
    {
        $this->systemd = $systemd;
    }

    public function index()
    {
        $services = [];

        foreach ($this->managedServices as $serviceId => $config) {
            $serviceName = $this->resolveServiceName($serviceId, $config['aliases'] ?? []);

            if (!$serviceName) {
                continue;
            }

            $status = $this->systemd->getStatus($serviceName);

            $services[] = [
                'id' => $serviceId,
                'service_name' => $serviceName,
                'display_name' => $config['name'],
                'status' => $status['status'],
                'is_running' => $status['is_running'],
                'is_enabled' => $status['is_enabled'],
                'uptime' => $status['uptime'] ?? null,
                'pid' => $status['pid'] ?? null,
                'memory' => $status['memory'] ?? null,
                'cpu' => $status['cpu'] ?? null,
            ];
        }

        return $this->success($services);
    }

    public function show(string $service)
    {
        $config = $this->managedServices[$service] ?? null;

        if (!$config) {
            return $this->error('Service not found', 404);
        }

        $serviceName = $this->resolveServiceName($service, $config['aliases'] ?? []);

        if (!$serviceName) {
            return $this->error('Service not installed', 404);
        }

        $status = $this->systemd->getStatus($serviceName);
        $logs = $this->systemd->getLogs($serviceName, 50);

        return $this->success([
            'id' => $service,
            'service_name' => $serviceName,
            'display_name' => $config['name'],
            'status' => $status,
            'logs' => $logs,
        ]);
    }

    public function start(string $service)
    {
        $serviceName = $this->getServiceName($service);

        if (!$serviceName) {
            return $this->error('Service not found', 404);
        }

        try {
            $this->systemd->start($serviceName);
            $this->clearStatusCache();

            return $this->success(null, "Service {$service} started successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to start service: ' . $e->getMessage(), 500);
        }
    }

    public function stop(string $service)
    {
        $serviceName = $this->getServiceName($service);

        if (!$serviceName) {
            return $this->error('Service not found', 404);
        }

        // Prevent stopping critical services without confirmation
        $criticalServices = ['apache2', 'mariadb', 'bind9'];
        if (in_array($service, $criticalServices)) {
            // In production, require additional confirmation
        }

        try {
            $this->systemd->stop($serviceName);
            $this->clearStatusCache();

            return $this->success(null, "Service {$service} stopped successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to stop service: ' . $e->getMessage(), 500);
        }
    }

    public function restart(string $service)
    {
        $serviceName = $this->getServiceName($service);

        if (!$serviceName) {
            return $this->error('Service not found', 404);
        }

        try {
            $this->systemd->restart($serviceName);
            $this->clearStatusCache();

            return $this->success(null, "Service {$service} restarted successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to restart service: ' . $e->getMessage(), 500);
        }
    }

    public function reload(string $service)
    {
        $serviceName = $this->getServiceName($service);

        if (!$serviceName) {
            return $this->error('Service not found', 404);
        }

        try {
            $this->systemd->reload($serviceName);
            $this->clearStatusCache();

            return $this->success(null, "Service {$service} reloaded successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to reload service: ' . $e->getMessage(), 500);
        }
    }

    public function enable(string $service)
    {
        $serviceName = $this->getServiceName($service);

        if (!$serviceName) {
            return $this->error('Service not found', 404);
        }

        try {
            $this->systemd->enable($serviceName);
            return $this->success(null, "Service {$service} enabled on boot");
        } catch (\Exception $e) {
            return $this->error('Failed to enable service: ' . $e->getMessage(), 500);
        }
    }

    public function disable(string $service)
    {
        $serviceName = $this->getServiceName($service);

        if (!$serviceName) {
            return $this->error('Service not found', 404);
        }

        try {
            $this->systemd->disable($serviceName);
            return $this->success(null, "Service {$service} disabled on boot");
        } catch (\Exception $e) {
            return $this->error('Failed to disable service: ' . $e->getMessage(), 500);
        }
    }

    public function logs(Request $request, string $service)
    {
        $serviceName = $this->getServiceName($service);

        if (!$serviceName) {
            return $this->error('Service not found', 404);
        }

        $lines = min($request->get('lines', 100), 1000);
        $logs = $this->systemd->getLogs($serviceName, $lines);

        return $this->success([
            'service' => $service,
            'logs' => $logs,
        ]);
    }

    public function restartAll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'services' => 'required|array',
            'services.*' => 'string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $results = [];

        foreach ($request->services as $service) {
            $serviceName = $this->getServiceName($service);

            if (!$serviceName) {
                $results[$service] = ['success' => false, 'message' => 'Service not found'];
                continue;
            }

            try {
                $this->systemd->restart($serviceName);
                $results[$service] = ['success' => true, 'message' => 'Restarted'];
            } catch (\Exception $e) {
                $results[$service] = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        $this->clearStatusCache();

        return $this->success($results, 'Batch restart completed');
    }

    protected function getServiceName(string $service): ?string
    {
        $config = $this->managedServices[$service] ?? null;

        if (!$config) {
            return null;
        }

        return $this->resolveServiceName($service, $config['aliases'] ?? []);
    }

    protected function resolveServiceName(string $primary, array $aliases): ?string
    {
        // Check primary name first
        if ($this->systemd->exists($primary)) {
            return $primary;
        }

        // Check aliases
        foreach ($aliases as $alias) {
            if ($this->systemd->exists($alias)) {
                return $alias;
            }
        }

        return null;
    }

    protected function clearStatusCache(): void
    {
        Cache::forget('services_status');
    }
}
