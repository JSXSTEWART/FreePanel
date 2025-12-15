<VirtualHost *:80>
    ServerName {{ $serverName }}
    DocumentRoot {{ $documentRoot }}

    ServerAdmin webmaster@{{ $domain->name }}

    <Directory {{ $documentRoot }}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Logging
    ErrorLog {{ $logDir }}/{{ $serverName }}-error.log
    CustomLog {{ $logDir }}/{{ $serverName }}-access.log combined

    # PHP-FPM Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php{{ $phpVersion }}-fpm-{{ $account->username }}.sock|fcgi://localhost"
    </FilesMatch>

    # Security Headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
