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
- **Cache/Queue**: Redis
- **Authentication**: Laravel Sanctum (JWT) + OAuth 2.0 / OpenID Connect

## Requirements

### Supported Operating Systems

| OS | Version | Status |
|----|---------|--------|
| **AlmaLinux** | 8, 9 | ✅ Fully Tested |
| **Rocky Linux** | 8, 9 | ✅ Fully Tested |
| **RHEL** | 8, 9 | ✅ Supported |
| **Ubuntu** | 22.04+ | ✅ Supported |
| **Debian** | 12+ | ✅ Supported |

### Hardware Requirements

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| RAM | 1 GB | 2+ GB |
| Disk | 20 GB | 50+ GB |
| CPU | 1 core | 2+ cores |

- Fresh server installation required
- Root/sudo access required

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

### Quick Install on AlmaLinux/Rocky Linux (Recommended)

Run this single command on a fresh AlmaLinux 8/9 or Rocky Linux 8/9 server:

```bash
# As root or with sudo
curl -sSL https://raw.githubusercontent.com/JSXSTEWART/FreePanel/main/system/scripts/install.sh | sudo bash
```

**What the installer does:**

1. **Repository Setup** - Configures EPEL, Remi (for PHP), and NodeSource repositories
2. **System Packages** - Installs Apache, PHP 8.2, MariaDB, Redis, Node.js 20, and more
3. **Service Configuration** - Sets up Dovecot, Exim/Postfix, BIND, Pure-FTPd
4. **FreePanel Setup** - Clones repo, installs dependencies, builds frontend
5. **Database** - Creates database, runs migrations
6. **Systemd Services** - Creates and enables `freepanel` and `freepanel-worker` services
7. **SELinux** - Configures proper SELinux policies (if enabled)
8. **Firewall** - Opens required ports via firewalld

**Installation time:** ~10-15 minutes depending on server speed

### Quick Install on Ubuntu/Debian

```bash
curl -sSL https://raw.githubusercontent.com/JSXSTEWART/FreePanel/main/system/scripts/install.sh | sudo bash
```

The installer automatically detects your OS and uses the appropriate package manager.

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

### OAuth Authentication (Optional)

FreePanel supports OAuth 2.0 / OpenID Connect authentication, allowing users to sign in with their existing accounts from supported providers.

**Supported Providers:**
- Google
- GitHub
- Microsoft
- Generic OpenID Connect (OIDC)

**Configuration:**

1. **Enable OAuth providers** in `.env`:
```bash
# Comma-separated list of providers to enable
OAUTH_PROVIDERS=google,github
```

2. **Configure Google OAuth** (if using):
```bash
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:1455/auth/callback
```

3. **Configure GitHub OAuth** (if using):
```bash
GITHUB_CLIENT_ID=your-github-client-id
GITHUB_CLIENT_SECRET=your-github-client-secret
GITHUB_REDIRECT_URI=http://localhost:1455/auth/callback
```

4. **Configure Microsoft OAuth** (if using):
```bash
MICROSOFT_CLIENT_ID=your-microsoft-client-id
MICROSOFT_CLIENT_SECRET=your-microsoft-client-secret
MICROSOFT_REDIRECT_URI=http://localhost:1455/auth/callback
```

5. **Configure Generic OIDC** (if using a custom provider):
```bash
OIDC_CLIENT_ID=your-oidc-client-id
OIDC_CLIENT_SECRET=your-oidc-client-secret
OIDC_REDIRECT_URI=http://localhost:1455/auth/callback
OIDC_AUTHORIZE_URL=https://your-provider.com/oauth/authorize
OIDC_TOKEN_URL=https://your-provider.com/oauth/token
OIDC_USERINFO_URL=https://your-provider.com/oauth/userinfo
```

**Database Migration:**

After configuring OAuth, run the migration to add OAuth fields to the users table:

```bash
php artisan migrate
```

**How it works:**
- Users can click "Sign in with Google" or "Sign in with GitHub" on the login page
- On first sign-in, a new user account is automatically created
- On subsequent sign-ins, the existing account is used
- If a user signs in with OAuth using an email that already exists, the OAuth account is linked to that user
- OAuth users don't need a password (password field is optional when OAuth is used)


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

## Integrations

### Zapier MCP (Model Context Protocol)

FreePanel supports Zapier MCP integration, allowing AI assistants like GitHub Copilot to access Zapier automation tools.

**Visual Studio Code Setup:**

See [VSCODE_MCP_SETUP.md](VSCODE_MCP_SETUP.md) for detailed instructions on configuring Zapier MCP in VS Code.

Quick setup:
1. Open VS Code Command Palette (`⇧+⌘+P` on Mac, `Ctrl+Shift+P` on Windows)
2. Run `MCP: Add Server...`
3. Choose `HTTP (HTTP or Server-Sent Events)`
4. Obtain your personal server URL from [https://mcp.zapier.com](https://mcp.zapier.com) (see [VSCODE_MCP_SETUP.md](VSCODE_MCP_SETUP.md) for details)
5. Configure the server with your URL
6. Set GitHub Copilot to "Agent" mode

**Additional Zapier Documentation:**
- [ZAPIER_MCP_EMBED.md](ZAPIER_MCP_EMBED.md) - Embedding Zapier MCP in FreePanel UI
- [ZAPIER_INTEGRATION.md](ZAPIER_INTEGRATION.md) - Complete integration guide
- [.mcp/servers.yaml](.mcp/servers.yaml) - MCP server configuration

## Management Scripts

FreePanel includes helper scripts for common operations:

### Check Status

```bash
sudo /opt/freepanel/system/scripts/status.sh
```

Shows:
- Server information and resource usage
- Status of all FreePanel services
- Network connections

### Update FreePanel

```bash
sudo /opt/freepanel/system/scripts/update.sh
```

This script:
- Creates a backup before updating
- Pulls latest code from GitHub
- Updates PHP and Node dependencies
- Runs database migrations
- Clears and rebuilds caches
- Restarts services

### Uninstall FreePanel

```bash
sudo /opt/freepanel/system/scripts/uninstall.sh
```

Interactively removes FreePanel with options to preserve or delete data.

### Manual Update (Alternative)

```bash
cd /opt/freepanel
git pull origin main
composer install --no-dev --optimize-autoloader
cd frontend && npm ci && npm run build && cd ..
php artisan migrate --force
php artisan cache:clear
php artisan config:cache
php artisan route:cache
systemctl restart freepanel freepanel-worker
```

## Troubleshooting

### Common Issues

**Permission denied errors:**

```bash
# For AlmaLinux/Rocky Linux/RHEL:
chown -R freepanel:freepanel /opt/freepanel
chmod -R 755 /opt/freepanel
chmod -R 775 /opt/freepanel/storage /opt/freepanel/bootstrap/cache

# For Ubuntu/Debian:
chown -R www-data:www-data /opt/freepanel
chmod -R 775 storage bootstrap/cache
```

**SELinux blocking access (AlmaLinux/Rocky Linux):**

```bash
# Check if SELinux is causing issues
sudo ausearch -m avc -ts recent

# Apply FreePanel SELinux policies
sudo setsebool -P httpd_can_network_connect 1
sudo setsebool -P httpd_can_network_connect_db 1
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/opt/freepanel/storage(/.*)?"
sudo restorecon -Rv /opt/freepanel
```

**Database connection failed:**

```bash
# Verify MariaDB is running
systemctl status mariadb

# Test connection
mysql -u freepanel -p freepanel -e "SELECT 1"

# Check credentials in .env
cat /opt/freepanel/.env | grep DB_
```

**Frontend not loading:**

```bash
cd /opt/freepanel/frontend
npm ci
npm run build
cd ..
php artisan cache:clear
php artisan view:clear
systemctl restart freepanel
```

**Services not starting:**

```bash
# Check service status
systemctl status freepanel freepanel-worker

# View detailed logs
journalctl -u freepanel -n 50 --no-pager
journalctl -u freepanel-worker -n 50 --no-pager

# Verify PHP path
which php
```

### Log Locations

| Log | Path |
|-----|------|
| Application | `/opt/freepanel/storage/logs/laravel.log` |
| FreePanel Service | `journalctl -u freepanel` |
| Queue Worker | `journalctl -u freepanel-worker` |
| Apache (RHEL) | `/var/log/httpd/` |
| Apache (Debian) | `/var/log/apache2/` |
| Installation | `/var/log/freepanel-install.log` |

### Useful Commands

```bash
# Check all service status
sudo /opt/freepanel/system/scripts/status.sh

# Restart all FreePanel services
sudo systemctl restart freepanel freepanel-worker

# View live logs
sudo journalctl -u freepanel -f

# Clear all caches
cd /opt/freepanel
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

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
