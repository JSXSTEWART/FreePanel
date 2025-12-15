<VirtualHost *:443>
    ServerName {{ $serverName }}
    ServerAlias {{ $serverAlias }}
    DocumentRoot {{ $documentRoot }}

    ServerAdmin webmaster@{{ $serverName }}

    <Directory {{ $documentRoot }}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Logging
    ErrorLog {{ $logDir }}/{{ $serverName }}-ssl-error.log
    CustomLog {{ $logDir }}/{{ $serverName }}-ssl-access.log combined

    # PHP-FPM Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php{{ $phpVersion }}-fpm-{{ $account->username }}.sock|fcgi://localhost"
    </FilesMatch>

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile {{ $sslCertFile }}
    SSLCertificateKeyFile {{ $sslKeyFile }}
@if($sslChainFile)
    SSLCertificateChainFile {{ $sslChainFile }}
@endif

    # Modern SSL Configuration
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384
    SSLHonorCipherOrder off
    SSLSessionTickets off

    # HSTS Header
    Header always set Strict-Transport-Security "max-age=63072000"

    # Security Headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # OCSP Stapling
    SSLUseStapling on
    SSLStaplingResponderTimeout 5
    SSLStaplingReturnResponderErrors off
</VirtualHost>

# OCSP Stapling Cache (if not defined globally)
SSLStaplingCache shmcb:/var/run/ocsp(128000)
