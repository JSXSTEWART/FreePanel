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
        'httpd' => ['name' => 'Apache Web Server', 'aliases' => ['apache2']],
        'nginx' => ['name' => 'Nginx Proxy'],
        'mariadb' => ['name' => 'MariaDB Database', 'aliases' => ['mysql', 'mysqld']],
        'dovecot' => ['name' => 'Dovecot IMAP/POP3'],
        'exim' => ['name' => 'Exim Mail Server', 'aliases' => ['exim4']],
        'named' => ['name' => 'BIND DNS Server', 'aliases' => ['bind9']],
        'pure-ftpd' => ['name' => 'Pure-FTPd Server', 'aliases' => ['proftpd', 'vsftpd']],
        'redis' => ['name' => 'Redis Cache', 'aliases' => ['redis-server']],
        'memcached' => ['name' => 'Memcached'],
        'fail2ban' => ['name' => 'Fail2Ban Security'],
        'clamav-daemon' => ['name' => 'ClamAV Antivirus', 'aliases' => ['clamd']],
        'spamd' => ['name' => 'SpamAssassin', 'aliases' => ['spamassassin']],
        'php-fpm' => ['name' => 'PHP-FPM', 'aliases' => ['php8.2-fpm', 'php8.1-fpm']],
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

    public function stop(Request $request, string $service)
    {
        $serviceName = $this->getServiceName($service);

        if (!$serviceName) {
            return $this->error('Service not found', 404);
        }

        // Prevent stopping critical services without confirmation
        // TODO: Implement critical service protection
        // Critical services should require additional confirmation to prevent accidental outages:
        //
        // 1. Add 'confirm' parameter to request validation:
        //    'confirm' => 'required_if:service,httpd,mariadb,named|boolean'
        //
        // 2. Check for confirmation:
        //    if (!$request->boolean('confirm')) {
        //        return $this->error(
        //            "Stopping {$service} will affect all hosted websites/databases. " .
        //            "Pass 'confirm: true' to proceed.",
        //            422,
        //            ['requires_confirmation' => true, 'service' => $service]
        //        );
        //    }
        //
        // 3. Log the action to audit trail:
        //    AuditLog::create([
        //        'user_id' => auth()->id(),
        //        'action' => 'service.stop',
        //        'target_type' => 'service',
        //        'target_id' => $service,
        //        'details' => ['confirmed' => true, 'ip' => request()->ip()],
        //    ]);
        //
        // 4. Optionally notify other admins:
        //    Notification::send(User::admins()->get(), new CriticalServiceStopped($service));
        //
        // 5. Consider adding a grace period or maintenance mode:
        //    - Set maintenance mode before stopping web server
        //    - Queue service stop with delay to allow active requests to complete
        $criticalServices = ['httpd', 'mariadb', 'named'];
        if (in_array($service, $criticalServices)) {
            if (!$request->boolean('confirm')) {
                return $this->error(
                    "Warning: Stopping '{$service}' is a critical operation that may affect " .
                    "all hosted websites and services. Please confirm this action.",
                    422,
                    ['requires_confirmation' => true, 'critical_service' => $service]
                );
            }
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
