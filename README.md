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

- PHP 8.2 or higher
- Node.js 18 or higher
- MySQL 8.0 / MariaDB 10.5 or higher
- Apache 2.4 or Nginx 1.18+
- Composer 2.x

## Installation

### Quick Install

```bash
# Download and run the installer
curl -sSL https://example.com/install.sh | sudo bash
```

### Manual Installation

1. Clone the repository:
```bash
git clone https://github.com/example/freepanel.git /opt/freepanel
cd /opt/freepanel
```

2. Install PHP dependencies:
```bash
composer install --no-dev --optimize-autoloader
```

3. Install Node dependencies and build frontend:
```bash
cd frontend
npm install
npm run build
cd ..
```

4. Configure environment:
```bash
cp .env.example .env
php artisan key:generate
```

5. Configure your database in `.env` and run migrations:
```bash
php artisan migrate --seed
```

6. Create admin user:
```bash
php artisan freepanel:create-admin
```

## Configuration

### Web Server

Configure Apache or Nginx to serve the `/opt/freepanel/public` directory.

Example Apache configuration:
```apache
<VirtualHost *:80>
    ServerName panel.example.com
    DocumentRoot /opt/freepanel/public

    <Directory /opt/freepanel/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Services

FreePanel integrates with:
- **Apache/Nginx** - Web server
- **PHP-FPM** - PHP processing
- **MariaDB** - Database server
- **Dovecot** - IMAP/POP3 server
- **Exim** - SMTP server
- **BIND** - DNS server
- **Pure-FTPd** - FTP server

## API Documentation

FreePanel provides a RESTful API for all operations:

- `POST /api/v1/auth/login` - User authentication
- `GET /api/v1/domains` - List domains
- `POST /api/v1/domains` - Create domain
- `GET /api/v1/email/accounts` - List email accounts
- `POST /api/v1/ssl/lets-encrypt` - Issue SSL certificate

Full API documentation is available at `/api/documentation`.

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

## Security

- All passwords are hashed using bcrypt
- API authentication via JWT tokens
- Role-based access control (Admin, Reseller, User)
- Feature flags for granular permissions
- Audit logging for all administrative actions
- Input validation and sanitization

## License

FreePanel is open-source software licensed under the MIT license.

## Support

- Documentation: https://docs.freepanel.io
- Issues: https://github.com/example/freepanel/issues
- Community: https://community.freepanel.io
