# FreePanel

A modern, open-source web hosting control panel built with Laravel and React.

## Features

- **User & Domain Management** - Create and manage hosting accounts with customizable packages
- **Web Server Management** - Apache/Nginx virtual host configuration with SSL support
- **Email System** - Full email hosting with Dovecot IMAP/POP3 and Exim SMTP
- **Database Management** - MySQL/MariaDB database and user management
- **File Manager** - Web-based file management with upload/download support
- **SSL/TLS Certificates** - Let's Encrypt integration for free SSL certificates
- **One-Click Installers** - WordPress and other popular applications
- **Backup System** - Automated and manual backup/restore functionality
- **DNS Management** - BIND DNS zone management

## Technology Stack

- **Backend**: PHP 8.2+ / Laravel 11
- **Frontend**: React 18 + TypeScript + Tailwind CSS
- **Database**: MySQL/MariaDB
- **Authentication**: Laravel Sanctum (JWT)
- **Queue**: Laravel Queue with Redis

## Requirements

- Fresh server installation (Ubuntu 20.04+, Debian 11+, Rocky Linux 8+, AlmaLinux 8+)
- Minimum 2GB RAM (4GB recommended)
- 20GB disk space
- Root access

### Environment Limitations

**FreePanel requires a dedicated server or VPS.** It cannot run in:

| Environment | Reason |
|-------------|--------|
| Google Colab | No persistent services, no systemd, ephemeral filesystem |
| Shared Hosting | No root access, cannot install system services |
| Docker (partial) | Requires multiple containers for full functionality |
| Serverless/Lambda | Not designed for stateless execution |

**Required System Services:**
- **Web Server** (Apache/Nginx) - Virtual host management
- **Database** (MariaDB/MySQL) - Persistent data storage
- **Mail Server** (Dovecot/Exim) - Email hosting
- **DNS Server** (BIND) - Zone management
- **FTP Server** (Pure-FTPd) - File transfer
- **Systemd** - Service management

These services require a full Linux server environment with persistent storage and root-level access to system configuration.

## Installation

### Quick Install (Recommended)

Run this single command on a fresh server as root:

```bash
curl -sSL https://raw.githubusercontent.com/JSXSTEWART/FreePanel/main/system/scripts/install.sh | sudo bash
```

This will automatically:
- Install all required dependencies (Apache, PHP 8.2, MariaDB, Node.js, etc.)
- Clone FreePanel from GitHub
- Configure the database
- Build the frontend
- Create a systemd service
- Configure the firewall

### Manual Installation

1. **Clone the repository:**
```bash
git clone https://github.com/JSXSTEWART/FreePanel.git /opt/freepanel
cd /opt/freepanel
```

2. **Install PHP dependencies:**
```bash
composer install --no-dev --optimize-autoloader
```

3. **Install Node dependencies and build frontend:**
```bash
cd frontend
npm install
npm run build
cd ..
```

4. **Configure environment:**
```bash
cp .env.example .env
php artisan key:generate
```

5. **Configure database in `.env`:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=freepanel
DB_USERNAME=freepanel
DB_PASSWORD=your_secure_password
```

6. **Run migrations and seed database:**
```bash
php artisan migrate --seed
```

7. **Create admin user:**
```bash
php artisan freepanel:create-admin
```

8. **Set permissions:**
```bash
chown -R www-data:www-data /opt/freepanel
chmod -R 775 storage bootstrap/cache
```

## Configuration

### Web Server

Configure Apache or Nginx to serve the `/opt/freepanel/public` directory.

**Apache Virtual Host:**
```apache
<VirtualHost *:80>
    ServerName panel.example.com
    DocumentRoot /opt/freepanel/public

    <Directory /opt/freepanel/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/freepanel-error.log
    CustomLog ${APACHE_LOG_DIR}/freepanel-access.log combined
</VirtualHost>
```

**Nginx Configuration:**
```nginx
server {
    listen 80;
    server_name panel.example.com;
    root /opt/freepanel/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Services Integration

FreePanel integrates with:
- **Apache/Nginx** - Web server
- **PHP-FPM** - PHP processing
- **MariaDB** - Database server
- **Dovecot** - IMAP/POP3 server
- **Exim** - SMTP server
- **BIND** - DNS server
- **Pure-FTPd** - FTP server
- **Certbot** - Let's Encrypt SSL

## Post-Installation

After installation, access FreePanel at:
- **URL**: `http://your-server-ip:8080`
- **Admin Port**: 2087 (HTTPS)
- **User Port**: 2083 (HTTPS)

### Securing Your Installation

1. **Configure SSL:**
```bash
certbot --apache -d panel.example.com
```

2. **Update firewall rules** as needed for your environment

3. **Change default credentials** immediately after first login

## API Documentation

FreePanel provides a RESTful API:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/auth/login` | POST | User authentication |
| `/api/v1/domains` | GET | List domains |
| `/api/v1/domains` | POST | Create domain |
| `/api/v1/email/accounts` | GET | List email accounts |
| `/api/v1/ssl/lets-encrypt` | POST | Issue SSL certificate |

Full API documentation: `/api/documentation`

## Development

### Backend Development
```bash
composer install
php artisan serve
```

### Frontend Development
```bash
cd frontend
npm install
npm run dev
```

### Running Tests
```bash
php artisan test
```

## Updating FreePanel

```bash
cd /opt/freepanel
git pull origin main
composer install --no-dev --optimize-autoloader
cd frontend && npm install && npm run build && cd ..
php artisan migrate --force
php artisan cache:clear
php artisan config:clear
systemctl restart freepanel
```

## Troubleshooting

### Common Issues

**Permission denied errors:**
```bash
chown -R www-data:www-data /opt/freepanel
chmod -R 775 storage bootstrap/cache
```

**Database connection failed:**
- Verify MariaDB is running: `systemctl status mariadb`
- Check credentials in `.env`

**Frontend not loading:**
- Rebuild: `cd frontend && npm run build`
- Clear cache: `php artisan cache:clear`

### Logs

- **Application logs**: `/opt/freepanel/storage/logs/laravel.log`
- **Apache logs**: `/var/log/apache2/` or `/var/log/httpd/`
- **System service**: `journalctl -u freepanel`

## Security

- All passwords hashed with bcrypt
- API authentication via JWT tokens
- Role-based access control (Admin, Reseller, User)
- Audit logging for all administrative actions
- Input validation and sanitization

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Commit changes: `git commit -am 'Add new feature'`
4. Push to branch: `git push origin feature/my-feature`
5. Submit a pull request

## License

FreePanel is open-source software licensed under the [MIT license](LICENSE).

## Support

- **GitHub Issues**: [JSXSTEWART/FreePanel/issues](https://github.com/JSXSTEWART/FreePanel/issues)
- **Documentation**: [docs.freepanel.io](https://docs.freepanel.io)
