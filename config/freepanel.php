<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FreePanel Version
    |--------------------------------------------------------------------------
    */

    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Admin & User Ports
    |--------------------------------------------------------------------------
    */

    'admin_port' => env('FREEPANEL_ADMIN_PORT', 2087),
    'user_port' => env('FREEPANEL_USER_PORT', 2083),

    /*
    |--------------------------------------------------------------------------
    | Service Configuration
    |--------------------------------------------------------------------------
    */

    'webserver' => env('FREEPANEL_WEBSERVER', 'apache'),
    'dns_server' => env('FREEPANEL_DNS_SERVER', 'bind'),
    'mail_server' => env('FREEPANEL_MAIL_SERVER', 'exim'),

    /*
    |--------------------------------------------------------------------------
    | System Paths
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'vhosts' => env('FREEPANEL_VHOSTS_PATH', '/etc/apache2/sites-available'),
        'vhosts_enabled' => env('FREEPANEL_VHOSTS_ENABLED', '/etc/apache2/sites-enabled'),
        'dns_zones' => env('FREEPANEL_DNS_ZONES_PATH', '/var/named'),
        'mail' => env('FREEPANEL_MAIL_PATH', '/var/mail/vhosts'),
        'home_base' => env('FREEPANEL_HOME_BASE', '/home'),
        'ssl' => env('FREEPANEL_SSL_PATH', '/etc/ssl/freepanel'),
        'backups' => env('FREEPANEL_BACKUPS_PATH', '/var/backups/freepanel'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Mappings (Service Name -> systemd unit)
    |--------------------------------------------------------------------------
    */

    'services' => [
        'apache' => 'httpd',
        'nginx' => 'nginx',
        'mysql' => 'mariadb',
        'postgresql' => 'postgresql',
        'dovecot' => 'dovecot',
        'exim' => 'exim',
        'postfix' => 'postfix',
        'proftpd' => 'proftpd',
        'pureftpd' => 'pure-ftpd',
        'named' => 'named',
        'powerdns' => 'pdns',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Package Settings
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'disk_quota' => 10 * 1024 * 1024 * 1024, // 10 GB in bytes
        'bandwidth' => 100 * 1024 * 1024 * 1024, // 100 GB in bytes
        'max_domains' => 10,
        'max_subdomains' => 25,
        'max_email_accounts' => 100,
        'max_databases' => 10,
        'max_ftp_accounts' => 10,
        'php_version' => '8.2',
        'shell' => '/bin/bash',
    ],

    /*
    |--------------------------------------------------------------------------
    | Let's Encrypt Configuration
    |--------------------------------------------------------------------------
    */

    'letsencrypt' => [
        'email' => env('LETSENCRYPT_EMAIL', ''),
        'staging' => env('LETSENCRYPT_STAGING', true),
        'renewal_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */

    'security' => [
        'min_password_strength' => 65,
        'session_timeout' => 24 * 60, // 24 hours in minutes
        'max_login_attempts' => 5,
        'lockout_duration' => 15, // minutes
        'two_factor_required' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    */

    'backups' => [
        'retention_days' => 30,
        'max_backups' => 10,
        'compression' => 'gzip',
        'include_databases' => true,
        'include_emails' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | PHP Versions Available
    |--------------------------------------------------------------------------
    */

    'php_versions' => [
        '7.4',
        '8.0',
        '8.1',
        '8.2',
        '8.3',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Applications for One-Click Install
    |--------------------------------------------------------------------------
    */

    'applications' => [
        'wordpress' => [
            'name' => 'WordPress',
            'version' => '6.4',
            'url' => 'https://wordpress.org/latest.tar.gz',
            'icon' => 'wordpress.svg',
        ],
        'joomla' => [
            'name' => 'Joomla',
            'version' => '5.0',
            'url' => 'https://downloads.joomla.org/latest',
            'icon' => 'joomla.svg',
        ],
        'drupal' => [
            'name' => 'Drupal',
            'version' => '10.2',
            'url' => 'https://www.drupal.org/download-latest/tar.gz',
            'icon' => 'drupal.svg',
        ],
        'prestashop' => [
            'name' => 'PrestaShop',
            'version' => '8.1',
            'url' => 'https://github.com/PrestaShop/PrestaShop/releases/latest',
            'icon' => 'prestashop.svg',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | DNS Record Types
    |--------------------------------------------------------------------------
    */

    'dns_record_types' => [
        'A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'PTR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Privileges
    |--------------------------------------------------------------------------
    */

    'database_privileges' => [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE',
        'CREATE', 'DROP', 'INDEX', 'ALTER',
        'CREATE TEMPORARY TABLES', 'LOCK TABLES',
        'EXECUTE', 'CREATE VIEW', 'SHOW VIEW',
        'CREATE ROUTINE', 'ALTER ROUTINE',
        'EVENT', 'TRIGGER', 'REFERENCES',
    ],

];
