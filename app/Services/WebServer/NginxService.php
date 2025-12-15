<?php

namespace App\Services\WebServer;

use App\Models\Domain;
use App\Models\Subdomain;
use App\Models\SslCertificate;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class NginxService implements WebServerInterface
{
    protected string $sitesAvailable = '/etc/nginx/sites-available';
    protected string $sitesEnabled = '/etc/nginx/sites-enabled';

    public function createVirtualHost(Domain $domain): void
    {
        $config = $this->generateVhostConfig($domain);
        $configPath = "{$this->sitesAvailable}/{$domain->name}.conf";

        file_put_contents($configPath, $config);

        $this->enableVirtualHost($domain);
        $this->reload();

        Log::info("Nginx vhost created for {$domain->name}");
    }

    public function updateVirtualHost(Domain $domain): void
    {
        $this->createVirtualHost($domain);
    }

    public function removeVirtualHost(Domain $domain): void
    {
        $this->disableVirtualHost($domain);

        $configPath = "{$this->sitesAvailable}/{$domain->name}.conf";
        if (file_exists($configPath)) {
            unlink($configPath);
        }

        $this->reload();

        Log::info("Nginx vhost removed for {$domain->name}");
    }

    public function enableVirtualHost(Domain $domain): void
    {
        $available = "{$this->sitesAvailable}/{$domain->name}.conf";
        $enabled = "{$this->sitesEnabled}/{$domain->name}.conf";

        if (file_exists($available) && !file_exists($enabled)) {
            symlink($available, $enabled);
        }
    }

    public function disableVirtualHost(Domain $domain): void
    {
        $enabled = "{$this->sitesEnabled}/{$domain->name}.conf";

        if (file_exists($enabled)) {
            unlink($enabled);
        }
    }

    public function createSubdomainVirtualHost(Subdomain $subdomain): void
    {
        $fullName = $subdomain->name . '.' . $subdomain->domain->name;
        $config = $this->generateSubdomainConfig($subdomain);
        $configPath = "{$this->sitesAvailable}/{$fullName}.conf";

        file_put_contents($configPath, $config);
        symlink($configPath, "{$this->sitesEnabled}/{$fullName}.conf");

        $this->reload();

        Log::info("Nginx subdomain vhost created for {$fullName}");
    }

    public function removeSubdomainVirtualHost(Subdomain $subdomain): void
    {
        $fullName = $subdomain->name . '.' . $subdomain->domain->name;

        $enabled = "{$this->sitesEnabled}/{$fullName}.conf";
        $available = "{$this->sitesAvailable}/{$fullName}.conf";

        if (file_exists($enabled)) {
            unlink($enabled);
        }
        if (file_exists($available)) {
            unlink($available);
        }

        $this->reload();
    }

    public function enableSsl(Domain $domain, SslCertificate $certificate): void
    {
        $config = $this->generateSslVhostConfig($domain, $certificate);
        $configPath = "{$this->sitesAvailable}/{$domain->name}.conf";

        file_put_contents($configPath, $config);
        $this->reload();

        Log::info("SSL enabled for {$domain->name}");
    }

    public function disableSsl(Domain $domain): void
    {
        $this->createVirtualHost($domain);
    }

    public function testConfig(): bool
    {
        $result = Process::run('nginx -t');
        return $result->successful();
    }

    public function reload(): void
    {
        if ($this->testConfig()) {
            Process::run('systemctl reload nginx');
        }
    }

    public function getVersion(): string
    {
        $result = Process::run('nginx -v 2>&1');
        return trim($result->output());
    }

    protected function generateVhostConfig(Domain $domain): string
    {
        $documentRoot = $domain->document_root ?? "/home/{$domain->account->username}/public_html";

        return <<<NGINX
server {
    listen 80;
    server_name {$domain->name} www.{$domain->name};
    root {$documentRoot};
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    access_log /var/log/nginx/{$domain->name}-access.log;
    error_log /var/log/nginx/{$domain->name}-error.log;
}
NGINX;
    }

    protected function generateSslVhostConfig(Domain $domain, SslCertificate $certificate): string
    {
        $documentRoot = $domain->document_root ?? "/home/{$domain->account->username}/public_html";

        return <<<NGINX
server {
    listen 80;
    server_name {$domain->name} www.{$domain->name};
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name {$domain->name} www.{$domain->name};
    root {$documentRoot};
    index index.php index.html;

    ssl_certificate {$certificate->certificate_path};
    ssl_certificate_key {$certificate->private_key_path};

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    access_log /var/log/nginx/{$domain->name}-access.log;
    error_log /var/log/nginx/{$domain->name}-error.log;
}
NGINX;
    }

    protected function generateSubdomainConfig(Subdomain $subdomain): string
    {
        $fullName = $subdomain->name . '.' . $subdomain->domain->name;

        return <<<NGINX
server {
    listen 80;
    server_name {$fullName};
    root {$subdomain->document_root};
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    access_log /var/log/nginx/{$fullName}-access.log;
    error_log /var/log/nginx/{$fullName}-error.log;
}
NGINX;
    }
}
