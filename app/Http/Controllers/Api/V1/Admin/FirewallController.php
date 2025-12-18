<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;

class FirewallController extends Controller
{
    /**
     * Get firewall status and rules
     */
    public function index()
    {
        $status = $this->getFirewallStatus();
        $rules = $this->getFirewallRules();

        return $this->success([
            'status' => $status,
            'rules' => $rules,
        ]);
    }

    /**
     * Enable firewall
     */
    public function enable()
    {
        $result = Process::run('sudo ufw --force enable');

        if (!$result->successful()) {
            return $this->error('Failed to enable firewall: ' . $result->errorOutput(), 500);
        }

        return $this->success(null, 'Firewall enabled');
    }

    /**
     * Disable firewall
     */
    public function disable()
    {
        $result = Process::run('sudo ufw --force disable');

        if (!$result->successful()) {
            return $this->error('Failed to disable firewall: ' . $result->errorOutput(), 500);
        }

        return $this->success(null, 'Firewall disabled');
    }

    /**
     * Add a firewall rule
     */
    public function addRule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:allow,deny',
            'direction' => 'required|in:in,out',
            'port' => 'required_without:ip|integer|min:1|max:65535',
            'protocol' => 'nullable|in:tcp,udp,any',
            'ip' => 'required_without:port|ip',
            'comment' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $cmd = 'sudo ufw ';
        $cmd .= $request->action . ' ';
        $cmd .= $request->direction . ' ';

        if ($request->ip) {
            $cmd .= 'from ' . $request->ip . ' ';
        }

        if ($request->port) {
            $cmd .= 'to any port ' . $request->port;
            if ($request->protocol && $request->protocol !== 'any') {
                $cmd .= ' proto ' . $request->protocol;
            }
        }

        if ($request->comment) {
            $cmd .= ' comment "' . addslashes($request->comment) . '"';
        }

        $result = Process::run($cmd);

        if (!$result->successful()) {
            return $this->error('Failed to add rule: ' . $result->errorOutput(), 500);
        }

        return $this->success(null, 'Firewall rule added');
    }

    /**
     * Delete a firewall rule
     */
    public function deleteRule(Request $request, int $ruleNumber)
    {
        $result = Process::run("sudo ufw --force delete {$ruleNumber}");

        if (!$result->successful()) {
            return $this->error('Failed to delete rule: ' . $result->errorOutput(), 500);
        }

        return $this->success(null, 'Firewall rule deleted');
    }

    /**
     * Allow common services
     */
    public function allowService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service' => 'required|in:ssh,http,https,ftp,smtp,pop3,imap,mysql,dns',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $services = [
            'ssh' => '22/tcp',
            'http' => '80/tcp',
            'https' => '443/tcp',
            'ftp' => '21/tcp',
            'smtp' => '25/tcp',
            'pop3' => '110/tcp',
            'imap' => '143/tcp',
            'mysql' => '3306/tcp',
            'dns' => '53',
        ];

        $port = $services[$request->service];
        $result = Process::run("sudo ufw allow {$port}");

        if (!$result->successful()) {
            return $this->error('Failed to allow service: ' . $result->errorOutput(), 500);
        }

        return $this->success(null, "Service {$request->service} allowed");
    }

    /**
     * Get blocked IPs (from fail2ban)
     */
    public function getBlockedIps()
    {
        $blockedIps = [];

        // Check fail2ban if installed
        $result = Process::run('sudo fail2ban-client status 2>/dev/null');

        if ($result->successful()) {
            // Get list of jails
            preg_match('/Jail list:\s*(.+)/', $result->output(), $matches);
            if (!empty($matches[1])) {
                $jails = array_map('trim', explode(',', $matches[1]));

                foreach ($jails as $jail) {
                    $jailResult = Process::run("sudo fail2ban-client status {$jail} 2>/dev/null");
                    if ($jailResult->successful()) {
                        preg_match('/Banned IP list:\s*(.*)/', $jailResult->output(), $ipMatches);
                        if (!empty($ipMatches[1])) {
                            $ips = array_filter(array_map('trim', explode(' ', $ipMatches[1])));
                            foreach ($ips as $ip) {
                                $blockedIps[] = [
                                    'ip' => $ip,
                                    'jail' => $jail,
                                    'source' => 'fail2ban',
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $this->success(['blocked_ips' => $blockedIps]);
    }

    /**
     * Unban an IP from fail2ban
     */
    public function unbanIp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ip' => 'required|ip',
            'jail' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $result = Process::run("sudo fail2ban-client set {$request->jail} unbanip {$request->ip}");

        if (!$result->successful()) {
            return $this->error('Failed to unban IP: ' . $result->errorOutput(), 500);
        }

        return $this->success(null, "IP {$request->ip} unbanned from {$request->jail}");
    }

    /**
     * Get firewall status
     */
    protected function getFirewallStatus(): array
    {
        $result = Process::run('sudo ufw status verbose');

        $output = $result->output();
        $isActive = str_contains($output, 'Status: active');

        preg_match('/Default: (\w+) \(incoming\), (\w+) \(outgoing\), (\w+) \(routed\)/', $output, $defaults);

        return [
            'is_active' => $isActive,
            'default_incoming' => $defaults[1] ?? 'unknown',
            'default_outgoing' => $defaults[2] ?? 'unknown',
            'default_routed' => $defaults[3] ?? 'unknown',
        ];
    }

    /**
     * Get firewall rules
     */
    protected function getFirewallRules(): array
    {
        $result = Process::run('sudo ufw status numbered');

        $rules = [];
        $lines = explode("\n", $result->output());

        foreach ($lines as $line) {
            if (preg_match('/\[\s*(\d+)\]\s+(.+?)\s+(ALLOW|DENY)\s+(IN|OUT)?\s*(.*)/', $line, $matches)) {
                $rules[] = [
                    'number' => (int) $matches[1],
                    'to' => trim($matches[2]),
                    'action' => strtolower($matches[3]),
                    'direction' => strtolower($matches[4] ?? 'in'),
                    'from' => trim($matches[5]),
                ];
            }
        }

        return $rules;
    }
}
