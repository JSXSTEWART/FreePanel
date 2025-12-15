<?php

namespace App\Services\WebServer;

use App\Models\Domain;
use App\Models\Subdomain;
use App\Models\SslCertificate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\View;

class ApacheService implements WebServerInterface
{
    protected string $sitesAvailable;
    protected string $sitesEnabled;
    protected string $sslDir;
    protected string $serviceName;

    public function __construct()
    {
        // Detect distribution and set paths
        if (file_exists('/etc/apache2')) {
            // Debian/Ubuntu
            $this->sitesAvailable = '/etc/apache2/sites-available';
            $this->sitesEnabled = '/etc/apache2/sites-enabled';
            $this->sslDir = '/etc/apache2/ssl';
            $this->serviceName = 'apache2';
        } else {
            // RHEL/CentOS
            $this->sitesAvailable = '/etc/httpd/conf.d';
            $this->sitesEnabled = '/etc/httpd/conf.d';
            $this->sslDir = '/etc/httpd/ssl';
            $this->serviceName = 'httpd';
        }
    }

    public function createVirtualHost(Domain $domain): void
    {
        $account = $domain->account;

        // Create document root if it doesn't exist
        if (!File::isDirectory($domain->document_root)) {
            File::makeDirectory($domain->document_root, 0755, true);
            $this->setOwnership($domain->document_root, $account->uid, $account->gid);
        }

        // Create log directory
        $logDir = "/home/{$account->username}/logs";
        if (!File::isDirectory($logDir)) {
            File::makeDirectory($logDir, 0755, true);
            $this->setOwnership($logDir, $account->uid, $account->gid);
        }

        // Generate virtual host configuration
        $config = $this->generateVhostConfig($domain);

        // Write configuration file
        $configPath = "{$this->sitesAvailable}/{$domain->name}.conf";
        File::put($configPath, $config);

        // Enable site (Debian/Ubuntu)
        if ($this->sitesAvailable !== $this->sitesEnabled) {
            $enabledPath = "{$this->sitesEnabled}/{$domain->name}.conf";
            if (!File::exists($enabledPath)) {
                File::link($configPath, $enabledPath);
            }
        }

        // Create default index page
        $indexPath = "{$domain->document_root}/index.html";
        if (!File::exists($indexPath)) {
            $defaultPage = View::make('system.templates.default-index', [
                'domain' => $domain->name,
            ])->render();
            File::put($indexPath, $defaultPage);
            $this->setOwnership($indexPath, $account->uid, $account->gid);
        }

        $this->testAndReload();
    }

    public function updateVirtualHost(Domain $domain): void
    {
        $config = $this->generateVhostConfig($domain);
        $configPath = "{$this->sitesAvailable}/{$domain->name}.conf";

        File::put($configPath, $config);
        $this->testAndReload();
    }

    public function removeVirtualHost(Domain $domain): void
    {
        $configPath = "{$this->sitesAvailable}/{$domain->name}.conf";
        $enabledPath = "{$this->sitesEnabled}/{$domain->name}.conf";
        $sslConfigPath = "{$this->sitesAvailable}/{$domain->name}-ssl.conf";
        $sslEnabledPath = "{$this->sitesEnabled}/{$domain->name}-ssl.conf";

        // Remove enabled symlinks
        if (File::exists($enabledPath)) {
            File::delete($enabledPath);
        }
        if (File::exists($sslEnabledPath)) {
            File::delete($sslEnabledPath);
        }

        // Remove config files
        if (File::exists($configPath)) {
            File::delete($configPath);
        }
        if (File::exists($sslConfigPath)) {
            File::delete($sslConfigPath);
        }

        $this->testAndReload();
    }

    public function enableVirtualHost(Domain $domain): void
    {
        if ($this->sitesAvailable !== $this->sitesEnabled) {
            $configPath = "{$this->sitesAvailable}/{$domain->name}.conf";
            $enabledPath = "{$this->sitesEnabled}/{$domain->name}.conf";

            if (File::exists($configPath) && !File::exists($enabledPath)) {
                File::link($configPath, $enabledPath);
            }

            // Also enable SSL if exists
            $sslConfigPath = "{$this->sitesAvailable}/{$domain->name}-ssl.conf";
            $sslEnabledPath = "{$this->sitesEnabled}/{$domain->name}-ssl.conf";
            if (File::exists($sslConfigPath) && !File::exists($sslEnabledPath)) {
                File::link($sslConfigPath, $sslEnabledPath);
            }
        }

        $this->testAndReload();
    }

    public function disableVirtualHost(Domain $domain): void
    {
        if ($this->sitesAvailable !== $this->sitesEnabled) {
            $enabledPath = "{$this->sitesEnabled}/{$domain->name}.conf";
            $sslEnabledPath = "{$this->sitesEnabled}/{$domain->name}-ssl.conf";

            if (File::exists($enabledPath)) {
                File::delete($enabledPath);
            }
            if (File::exists($sslEnabledPath)) {
                File::delete($sslEnabledPath);
            }
        }

        $this->testAndReload();
    }

    public function createSubdomainVirtualHost(Subdomain $subdomain): void
    {
        $domain = $subdomain->domain;
        $account = $domain->account;
        $fullName = "{$subdomain->name}.{$domain->name}";

        // Create document root
        if (!File::isDirectory($subdomain->document_root)) {
            File::makeDirectory($subdomain->document_root, 0755, true);
            $this->setOwnership($subdomain->document_root, $account->uid, $account->gid);
        }

        // Generate configuration
        $config = $this->generateSubdomainConfig($subdomain);

        $configPath = "{$this->sitesAvailable}/{$fullName}.conf";
        File::put($configPath, $config);

        if ($this->sitesAvailable !== $this->sitesEnabled) {
            $enabledPath = "{$this->sitesEnabled}/{$fullName}.conf";
            if (!File::exists($enabledPath)) {
                File::link($configPath, $enabledPath);
            }
        }

        $this->testAndReload();
    }

    public function removeSubdomainVirtualHost(Subdomain $subdomain): void
    {
        $fullName = "{$subdomain->name}.{$subdomain->domain->name}";

        $configPath = "{$this->sitesAvailable}/{$fullName}.conf";
        $enabledPath = "{$this->sitesEnabled}/{$fullName}.conf";

        if (File::exists($enabledPath)) {
            File::delete($enabledPath);
        }
        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        $this->testAndReload();
    }

    public function enableSsl(Domain $domain, SslCertificate $certificate): void
    {
        $account = $domain->account;

        // Create SSL directory for domain
        $sslDomainDir = "{$this->sslDir}/{$domain->name}";
        if (!File::isDirectory($sslDomainDir)) {
            File::makeDirectory($sslDomainDir, 0700, true);
        }

        // Write certificate files
        File::put("{$sslDomainDir}/cert.pem", $certificate->certificate);
        File::put("{$sslDomainDir}/key.pem", decrypt($certificate->private_key));
        chmod("{$sslDomainDir}/key.pem", 0600);

        if ($certificate->ca_bundle) {
            File::put("{$sslDomainDir}/chain.pem", $certificate->ca_bundle);
        }

        // Generate SSL virtual host
        $config = $this->generateSslVhostConfig($domain, $sslDomainDir);

        $configPath = "{$this->sitesAvailable}/{$domain->name}-ssl.conf";
        File::put($configPath, $config);

        if ($this->sitesAvailable !== $this->sitesEnabled) {
            $enabledPath = "{$this->sitesEnabled}/{$domain->name}-ssl.conf";
            if (!File::exists($enabledPath)) {
                File::link($configPath, $enabledPath);
            }
        }

        // Update HTTP vhost to redirect to HTTPS
        $this->updateVirtualHost($domain);

        $this->testAndReload();
    }

    public function disableSsl(Domain $domain): void
    {
        $sslConfigPath = "{$this->sitesAvailable}/{$domain->name}-ssl.conf";
        $sslEnabledPath = "{$this->sitesEnabled}/{$domain->name}-ssl.conf";
        $sslDomainDir = "{$this->sslDir}/{$domain->name}";

        if (File::exists($sslEnabledPath)) {
            File::delete($sslEnabledPath);
        }
        if (File::exists($sslConfigPath)) {
            File::delete($sslConfigPath);
        }
        if (File::isDirectory($sslDomainDir)) {
            File::deleteDirectory($sslDomainDir);
        }

        // Update HTTP vhost to remove redirect
        $this->updateVirtualHost($domain);

        $this->testAndReload();
    }

    public function testConfig(): bool
    {
        $result = Process::run("{$this->serviceName} -t");
        return $result->successful();
    }

    public function reload(): void
    {
        Process::run("systemctl reload {$this->serviceName}");
    }

    public function getVersion(): string
    {
        $result = Process::run("{$this->serviceName} -v");
        preg_match('/Apache\/([0-9.]+)/', $result->output(), $matches);
        return $matches[1] ?? 'unknown';
    }

    protected function testAndReload(): void
    {
        if ($this->testConfig()) {
            $this->reload();
        } else {
            throw new \RuntimeException('Apache configuration test failed');
        }
    }

    protected function generateVhostConfig(Domain $domain): string
    {
        $account = $domain->account;
        $hasSsl = $domain->sslCertificate !== null;

        return View::make('system.templates.apache.vhost', [
            'domain' => $domain,
            'account' => $account,
            'serverName' => $domain->name,
            'serverAlias' => "www.{$domain->name}",
            'documentRoot' => $domain->document_root,
            'logDir' => "/home/{$account->username}/logs",
            'phpVersion' => $domain->php_version ?? config('freepanel.default_php_version', '8.2'),
            'redirectToHttps' => $hasSsl,
        ])->render();
    }

    protected function generateSslVhostConfig(Domain $domain, string $sslDir): string
    {
        $account = $domain->account;

        return View::make('system.templates.apache.vhost-ssl', [
            'domain' => $domain,
            'account' => $account,
            'serverName' => $domain->name,
            'serverAlias' => "www.{$domain->name}",
            'documentRoot' => $domain->document_root,
            'logDir' => "/home/{$account->username}/logs",
            'phpVersion' => $domain->php_version ?? config('freepanel.default_php_version', '8.2'),
            'sslCertFile' => "{$sslDir}/cert.pem",
            'sslKeyFile' => "{$sslDir}/key.pem",
            'sslChainFile' => file_exists("{$sslDir}/chain.pem") ? "{$sslDir}/chain.pem" : null,
        ])->render();
    }

    protected function generateSubdomainConfig(Subdomain $subdomain): string
    {
        $domain = $subdomain->domain;
        $account = $domain->account;
        $fullName = "{$subdomain->name}.{$domain->name}";

        return View::make('system.templates.apache.subdomain', [
            'subdomain' => $subdomain,
            'domain' => $domain,
            'account' => $account,
            'serverName' => $fullName,
            'documentRoot' => $subdomain->document_root,
            'logDir' => "/home/{$account->username}/logs",
            'phpVersion' => $domain->php_version ?? config('freepanel.default_php_version', '8.2'),
        ])->render();
    }

    protected function setOwnership(string $path, int $uid, int $gid): void
    {
        chown($path, $uid);
        chgrp($path, $gid);
    }
}
