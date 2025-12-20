#!/bin/bash
#
# FreePanel Installation Script
# Optimized for AlmaLinux 8/9, Rocky Linux 8/9, and RHEL-based distributions
# Also supports Ubuntu 22.04+ and Debian 12+
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
FREEPANEL_DIR="/opt/freepanel"
FREEPANEL_USER="freepanel"
FREEPANEL_REPO="https://github.com/JSXSTEWART/FreePanel.git"
FREEPANEL_BRANCH="main"
PHP_VERSION="8.2"
NODE_VERSION="20"
INSTALL_LOG="/var/log/freepanel-install.log"

# Ensure log file exists
mkdir -p /var/log
touch "$INSTALL_LOG"

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
    echo "[INFO] $(date '+%Y-%m-%d %H:%M:%S') $1" >> "$INSTALL_LOG"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
    echo "[SUCCESS] $(date '+%Y-%m-%d %H:%M:%S') $1" >> "$INSTALL_LOG"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
    echo "[WARNING] $(date '+%Y-%m-%d %H:%M:%S') $1" >> "$INSTALL_LOG"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
    echo "[ERROR] $(date '+%Y-%m-%d %H:%M:%S') $1" >> "$INSTALL_LOG"
    exit 1
}

log_step() {
    echo ""
    echo -e "${CYAN}========================================${NC}"
    echo -e "${CYAN}  $1${NC}"
    echo -e "${CYAN}========================================${NC}"
    echo ""
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root. Use: sudo bash install.sh"
    fi
}

check_memory() {
    local total_mem=$(free -m | awk '/^Mem:/{print $2}')
    if [ "$total_mem" -lt 1024 ]; then
        log_warning "System has less than 1GB RAM. FreePanel requires minimum 1GB, recommended 2GB+"
        read -p "Continue anyway? (y/N) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
}

detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$ID
        VERSION=$VERSION_ID
        VERSION_MAJOR=$(echo $VERSION_ID | cut -d. -f1)
    else
        log_error "Cannot detect operating system"
    fi

    case $OS in
        ubuntu)
            if [ "$VERSION_MAJOR" -lt 22 ]; then
                log_error "Ubuntu 22.04 or higher is required"
            fi
            PKG_MANAGER="apt-get"
            PKG_UPDATE="apt-get update -y"
            PKG_INSTALL="apt-get install -y"
            SERVICE_APACHE="apache2"
            SERVICE_PHP_FPM="php${PHP_VERSION}-fpm"
            ;;
        debian)
            if [ "$VERSION_MAJOR" -lt 12 ]; then
                log_error "Debian 12 or higher is required"
            fi
            PKG_MANAGER="apt-get"
            PKG_UPDATE="apt-get update -y"
            PKG_INSTALL="apt-get install -y"
            SERVICE_APACHE="apache2"
            SERVICE_PHP_FPM="php${PHP_VERSION}-fpm"
            ;;
        almalinux|rocky|rhel|centos)
            if [ "$VERSION_MAJOR" -lt 8 ]; then
                log_error "AlmaLinux/Rocky Linux/RHEL 8 or higher is required"
            fi
            PKG_MANAGER="dnf"
            PKG_UPDATE="dnf check-update || true"
            PKG_INSTALL="dnf install -y"
            SERVICE_APACHE="httpd"
            SERVICE_PHP_FPM="php-fpm"
            ;;
        *)
            log_error "Unsupported operating system: $OS. Supported: AlmaLinux, Rocky Linux, RHEL, Ubuntu, Debian"
            ;;
    esac

    log_info "Detected OS: $OS $VERSION"
}

setup_rhel_repos() {
    log_info "Setting up repositories for RHEL-based system..."

    # Enable PowerTools/CRB repository (required for many packages)
    if [ "$VERSION_MAJOR" -eq 8 ]; then
        dnf config-manager --set-enabled powertools 2>/dev/null || \
        dnf config-manager --set-enabled PowerTools 2>/dev/null || true
    else
        dnf config-manager --set-enabled crb 2>/dev/null || true
    fi

    # Install EPEL repository
    if ! rpm -q epel-release &>/dev/null; then
        log_info "Installing EPEL repository..."
        $PKG_INSTALL epel-release
    fi

    # Install Remi repository for PHP
    if ! rpm -q remi-release &>/dev/null; then
        log_info "Installing Remi repository for PHP..."
        $PKG_INSTALL "https://rpms.remirepo.net/enterprise/remi-release-${VERSION_MAJOR}.rpm"
    fi

    log_success "Repositories configured"
}

install_dependencies() {
    log_step "Installing System Dependencies"

    $PKG_UPDATE

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL curl wget git unzip tar gzip bzip2 \
                vim nano htop lsof net-tools \
                openssl ca-certificates gnupg \
                cron logrotate acl rsync \
                software-properties-common apt-transport-https
            ;;
        almalinux|rocky|rhel|centos)
            $PKG_INSTALL curl wget git unzip tar gzip bzip2 \
                vim nano htop lsof net-tools \
                openssl ca-certificates \
                cronie logrotate acl rsync \
                dnf-utils policycoreutils-python-utils
            ;;
    esac

    log_success "System dependencies installed"
}

install_webserver() {
    log_step "Installing Apache Web Server"

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL apache2 libapache2-mod-fcgid
            a2enmod rewrite headers ssl proxy_fcgi setenvif expires deflate
            systemctl enable apache2
            ;;
        almalinux|rocky|rhel|centos)
            $PKG_INSTALL httpd mod_ssl mod_fcgid
            systemctl enable httpd
            ;;
    esac

    log_success "Apache installed and enabled"
}

install_php() {
    log_step "Installing PHP $PHP_VERSION"

    case $OS in
        ubuntu|debian)
            # Add Ondrej PHP repository
            if [ ! -f /etc/apt/sources.list.d/ondrej-*.list ]; then
                add-apt-repository -y ppa:ondrej/php
                $PKG_UPDATE
            fi
            $PKG_INSTALL php${PHP_VERSION} php${PHP_VERSION}-fpm \
                php${PHP_VERSION}-mysql php${PHP_VERSION}-curl \
                php${PHP_VERSION}-gd php${PHP_VERSION}-mbstring \
                php${PHP_VERSION}-xml php${PHP_VERSION}-zip \
                php${PHP_VERSION}-bcmath php${PHP_VERSION}-intl \
                php${PHP_VERSION}-redis php${PHP_VERSION}-imagick \
                php${PHP_VERSION}-cli php${PHP_VERSION}-common \
                php${PHP_VERSION}-opcache php${PHP_VERSION}-readline \
                php${PHP_VERSION}-soap php${PHP_VERSION}-tokenizer
            systemctl enable php${PHP_VERSION}-fpm
            ;;
        almalinux|rocky|rhel|centos)
            # Reset and enable PHP from Remi
            dnf module reset php -y
            dnf module enable php:remi-${PHP_VERSION} -y
            $PKG_INSTALL php php-fpm php-mysqlnd php-curl php-gd \
                php-mbstring php-xml php-zip php-bcmath php-intl \
                php-redis php-imagick php-cli php-common \
                php-opcache php-soap php-process php-pecl-zip

            # Configure PHP-FPM for Apache
            sed -i 's/user = apache/user = freepanel/' /etc/php-fpm.d/www.conf 2>/dev/null || true
            sed -i 's/group = apache/group = freepanel/' /etc/php-fpm.d/www.conf 2>/dev/null || true

            systemctl enable php-fpm
            ;;
    esac

    # Optimize PHP settings
    configure_php

    log_success "PHP $PHP_VERSION installed"
}

configure_php() {
    log_info "Optimizing PHP configuration..."

    local php_ini
    case $OS in
        ubuntu|debian)
            php_ini="/etc/php/${PHP_VERSION}/fpm/php.ini"
            ;;
        almalinux|rocky|rhel|centos)
            php_ini="/etc/php.ini"
            ;;
    esac

    if [ -f "$php_ini" ]; then
        # Backup original
        cp "$php_ini" "${php_ini}.backup"

        # Apply optimizations
        sed -i 's/^upload_max_filesize.*/upload_max_filesize = 256M/' "$php_ini"
        sed -i 's/^post_max_size.*/post_max_size = 256M/' "$php_ini"
        sed -i 's/^memory_limit.*/memory_limit = 512M/' "$php_ini"
        sed -i 's/^max_execution_time.*/max_execution_time = 300/' "$php_ini"
        sed -i 's/^max_input_time.*/max_input_time = 300/' "$php_ini"
        sed -i 's/^max_input_vars.*/max_input_vars = 5000/' "$php_ini"
        sed -i 's/^;date.timezone.*/date.timezone = UTC/' "$php_ini"
    fi
}

install_mysql() {
    log_step "Installing MariaDB Database Server"

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL mariadb-server mariadb-client
            systemctl enable mariadb
            ;;
        almalinux|rocky|rhel|centos)
            $PKG_INSTALL mariadb-server mariadb
            systemctl enable mariadb
            ;;
    esac

    systemctl start mariadb

    # Secure MariaDB installation
    log_info "Securing MariaDB installation..."
    mysql -e "DELETE FROM mysql.user WHERE User='';"
    mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
    mysql -e "DROP DATABASE IF EXISTS test;"
    mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
    mysql -e "FLUSH PRIVILEGES;"

    log_success "MariaDB installed and secured"
}

install_redis() {
    log_step "Installing Redis Cache Server"

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL redis-server
            systemctl enable redis-server
            systemctl start redis-server
            ;;
        almalinux|rocky|rhel|centos)
            $PKG_INSTALL redis
            systemctl enable redis
            systemctl start redis
            ;;
    esac

    log_success "Redis installed and started"
}

install_mail() {
    log_step "Installing Mail Services"

    case $OS in
        ubuntu|debian)
            # Pre-configure exim4 to avoid interactive prompts
            debconf-set-selections <<< "exim4-config exim4/dc_eximconfig_configtype select internet site; mail is sent and received directly using SMTP"
            debconf-set-selections <<< "exim4-config exim4/mailname string $(hostname -f)"

            $PKG_INSTALL dovecot-core dovecot-imapd dovecot-pop3d \
                dovecot-lmtpd dovecot-mysql \
                exim4 exim4-daemon-heavy
            systemctl enable dovecot exim4
            ;;
        almalinux|rocky|rhel|centos)
            $PKG_INSTALL dovecot dovecot-mysql dovecot-pigeonhole
            # Postfix as alternative to exim on RHEL (exim may not be in repos)
            if dnf list exim &>/dev/null; then
                $PKG_INSTALL exim
                systemctl enable exim
            else
                log_warning "Exim not available, installing Postfix as mail server"
                $PKG_INSTALL postfix postfix-mysql
                systemctl enable postfix
            fi
            systemctl enable dovecot
            ;;
    esac

    log_success "Mail services installed"
}

install_dns() {
    log_step "Installing BIND DNS Server"

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL bind9 bind9utils bind9-dnsutils
            systemctl enable named
            ;;
        almalinux|rocky|rhel|centos)
            $PKG_INSTALL bind bind-utils bind-chroot
            systemctl enable named
            ;;
    esac

    log_success "BIND DNS installed"
}

install_ftp() {
    log_step "Installing Pure-FTPd Server"

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL pure-ftpd pure-ftpd-mysql
            systemctl enable pure-ftpd
            ;;
        almalinux|rocky|rhel|centos)
            # Pure-FTPd is in EPEL
            $PKG_INSTALL pure-ftpd
            systemctl enable pure-ftpd
            ;;
    esac

    log_success "Pure-FTPd installed"
}

install_nodejs() {
    log_step "Installing Node.js $NODE_VERSION"

    case $OS in
        ubuntu|debian)
            # NodeSource repository for Debian/Ubuntu
            curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash -
            $PKG_INSTALL nodejs
            ;;
        almalinux|rocky|rhel|centos)
            # NodeSource repository for RHEL-based
            curl -fsSL https://rpm.nodesource.com/setup_${NODE_VERSION}.x | bash -
            $PKG_INSTALL nodejs
            ;;
    esac

    # Update npm to latest
    npm install -g npm@latest

    log_success "Node.js $(node -v) installed"
}

install_composer() {
    log_step "Installing Composer"

    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        rm composer-setup.php
        log_error "Composer installer checksum verification failed"
    fi

    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm composer-setup.php

    log_success "Composer $(composer --version --no-ansi | cut -d' ' -f3) installed"
}

install_certbot() {
    log_step "Installing Certbot for Let's Encrypt"

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL certbot python3-certbot-apache
            ;;
        almalinux|rocky|rhel|centos)
            $PKG_INSTALL certbot python3-certbot-apache
            ;;
    esac

    log_success "Certbot installed"
}

create_freepanel_user() {
    log_info "Creating FreePanel system user..."

    if ! id "$FREEPANEL_USER" &>/dev/null; then
        useradd -r -m -d /home/$FREEPANEL_USER -s /bin/bash $FREEPANEL_USER
        log_success "User '$FREEPANEL_USER' created"
    else
        log_info "User '$FREEPANEL_USER' already exists"
    fi

    # Add freepanel user to appropriate groups
    case $OS in
        ubuntu|debian)
            usermod -aG www-data $FREEPANEL_USER 2>/dev/null || true
            ;;
        almalinux|rocky|rhel|centos)
            usermod -aG apache $FREEPANEL_USER 2>/dev/null || true
            ;;
    esac
}

clone_freepanel() {
    log_step "Downloading FreePanel"

    # Remove existing directory if present
    if [ -d "$FREEPANEL_DIR" ]; then
        log_warning "Removing existing FreePanel installation..."
        rm -rf $FREEPANEL_DIR
    fi

    # Clone repository
    git clone --branch $FREEPANEL_BRANCH --depth 1 $FREEPANEL_REPO $FREEPANEL_DIR

    if [ $? -ne 0 ]; then
        log_error "Failed to clone FreePanel repository"
    fi

    # Set ownership
    chown -R $FREEPANEL_USER:$FREEPANEL_USER $FREEPANEL_DIR

    log_success "FreePanel downloaded to $FREEPANEL_DIR"
}

create_env_file() {
    log_info "Creating environment configuration..."

    local server_ip=$(hostname -I | awk '{print $1}')
    local hostname=$(hostname -f 2>/dev/null || hostname)
    local app_key=$(openssl rand -base64 32)

    cat > $FREEPANEL_DIR/.env << EOF
# FreePanel Environment Configuration
# Generated by installer on $(date)

APP_NAME=FreePanel
APP_ENV=production
APP_KEY=base64:${app_key}
APP_DEBUG=false
APP_URL=http://${server_ip}:8080
APP_HOSTNAME=${hostname}

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=warning

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=freepanel
DB_USERNAME=freepanel
DB_PASSWORD=PLACEHOLDER_DB_PASS

# Redis Cache & Queue
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# FreePanel Specific Settings
FREEPANEL_HOSTNAME=${hostname}
FREEPANEL_SERVER_IP=${server_ip}
FREEPANEL_ADMIN_PORT=2087
FREEPANEL_USER_PORT=2083
FREEPANEL_WEBSERVER=apache
FREEPANEL_DNS_SERVER=bind
FREEPANEL_MAIL_SERVER=exim

# System Paths (RHEL/AlmaLinux defaults)
FREEPANEL_VHOSTS_PATH=/etc/httpd/conf.d
FREEPANEL_VHOSTS_ENABLED=/etc/httpd/conf.d
FREEPANEL_DNS_ZONES_PATH=/var/named
FREEPANEL_MAIL_PATH=/var/mail/vhosts
FREEPANEL_HOME_BASE=/home
FREEPANEL_SSL_PATH=/etc/ssl/freepanel
FREEPANEL_BACKUPS_PATH=/var/backups/freepanel

# Let's Encrypt
LETSENCRYPT_EMAIL=
LETSENCRYPT_STAGING=false

# Session Configuration
SESSION_LIFETIME=1440
SANCTUM_STATEFUL_DOMAINS=${server_ip}:8080,localhost:8080,127.0.0.1:8080
EOF

    chown $FREEPANEL_USER:$FREEPANEL_USER $FREEPANEL_DIR/.env
    chmod 640 $FREEPANEL_DIR/.env

    log_success "Environment file created"
}

setup_freepanel() {
    log_step "Setting Up FreePanel Application"

    cd $FREEPANEL_DIR

    # Create required directories
    mkdir -p storage/framework/{cache,sessions,views}
    mkdir -p storage/logs
    mkdir -p bootstrap/cache
    mkdir -p /var/backups/freepanel
    mkdir -p /etc/ssl/freepanel

    # Install PHP dependencies
    log_info "Installing PHP dependencies (this may take a few minutes)..."
    sudo -u $FREEPANEL_USER composer install --no-dev --optimize-autoloader --no-interaction

    # Build frontend
    log_info "Building frontend assets..."
    cd $FREEPANEL_DIR/frontend
    sudo -u $FREEPANEL_USER npm ci --no-audit
    sudo -u $FREEPANEL_USER npm run build

    # Verify frontend build
    if [ -d "$FREEPANEL_DIR/public/build" ]; then
        log_success "Frontend assets built successfully"
    else
        log_warning "Frontend build directory not found - check build output"
    fi

    cd $FREEPANEL_DIR

    # Set permissions
    chown -R $FREEPANEL_USER:$FREEPANEL_USER $FREEPANEL_DIR
    chmod -R 755 $FREEPANEL_DIR
    chmod -R 775 $FREEPANEL_DIR/storage
    chmod -R 775 $FREEPANEL_DIR/bootstrap/cache

    log_success "FreePanel application setup complete"
}

setup_database() {
    log_step "Configuring Database"

    # Generate random password
    DB_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 20)

    # Create database and user
    mysql -e "CREATE DATABASE IF NOT EXISTS freepanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS 'freepanel'@'localhost' IDENTIFIED BY '$DB_PASS';"
    mysql -e "GRANT ALL PRIVILEGES ON freepanel.* TO 'freepanel'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"

    # Update .env with database credentials
    sed -i "s/DB_PASSWORD=PLACEHOLDER_DB_PASS/DB_PASSWORD=$DB_PASS/" $FREEPANEL_DIR/.env

    # Run migrations
    cd $FREEPANEL_DIR
    sudo -u $FREEPANEL_USER php artisan key:generate --force
    sudo -u $FREEPANEL_USER php artisan migrate --force
    sudo -u $FREEPANEL_USER php artisan config:cache
    sudo -u $FREEPANEL_USER php artisan route:cache
    sudo -u $FREEPANEL_USER php artisan view:cache

    log_success "Database configured and migrations complete"
}

create_admin() {
    log_step "Creating Admin User"

    cd $FREEPANEL_DIR

    # Generate admin password
    ADMIN_PASS=$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9' | head -c 16)

    # Check if create-admin command exists
    if php artisan list | grep -q "freepanel:create-admin"; then
        sudo -u $FREEPANEL_USER php artisan freepanel:create-admin --password="$ADMIN_PASS"
    else
        # Fallback: Create admin via tinker
        log_info "Creating admin user via database..."
        HASHED_PASS=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_BCRYPT);")
        mysql freepanel -e "INSERT INTO users (username, email, password, role, status, created_at, updated_at) VALUES ('admin', 'admin@localhost', '$HASHED_PASS', 'admin', 'active', NOW(), NOW()) ON DUPLICATE KEY UPDATE password='$HASHED_PASS';"
    fi

    # Save credentials
    cat > /root/.freepanel_credentials << EOF
========================================
FreePanel Admin Credentials
========================================
Username: admin
Password: $ADMIN_PASS

Please change this password after first login!
========================================
EOF
    chmod 600 /root/.freepanel_credentials

    log_success "Admin user created (credentials saved to /root/.freepanel_credentials)"
}

create_systemd_service() {
    log_step "Creating Systemd Services"

    # Main FreePanel service
    cat > /etc/systemd/system/freepanel.service << EOF
[Unit]
Description=FreePanel Web Hosting Control Panel
After=network.target mariadb.service redis.service
Requires=mariadb.service

[Service]
Type=simple
User=$FREEPANEL_USER
Group=$FREEPANEL_USER
WorkingDirectory=$FREEPANEL_DIR
ExecStart=/usr/bin/php artisan serve --host=0.0.0.0 --port=8080
Restart=always
RestartSec=5
StandardOutput=append:/var/log/freepanel/app.log
StandardError=append:/var/log/freepanel/error.log

[Install]
WantedBy=multi-user.target
EOF

    # Queue worker service
    cat > /etc/systemd/system/freepanel-worker.service << EOF
[Unit]
Description=FreePanel Queue Worker
After=network.target mariadb.service redis.service freepanel.service

[Service]
Type=simple
User=$FREEPANEL_USER
Group=$FREEPANEL_USER
WorkingDirectory=$FREEPANEL_DIR
ExecStart=/usr/bin/php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5
StandardOutput=append:/var/log/freepanel/worker.log
StandardError=append:/var/log/freepanel/worker-error.log

[Install]
WantedBy=multi-user.target
EOF

    # Create log directory
    mkdir -p /var/log/freepanel
    chown -R $FREEPANEL_USER:$FREEPANEL_USER /var/log/freepanel

    # Reload and enable services
    systemctl daemon-reload
    systemctl enable freepanel freepanel-worker
    systemctl start freepanel freepanel-worker

    log_success "Systemd services created and started"
}

configure_selinux() {
    log_step "Configuring SELinux"

    if command -v getenforce &>/dev/null; then
        SELINUX_STATUS=$(getenforce)
        if [ "$SELINUX_STATUS" = "Enforcing" ] || [ "$SELINUX_STATUS" = "Permissive" ]; then
            log_info "SELinux is $SELINUX_STATUS, applying policies..."

            # Allow httpd to connect to network
            setsebool -P httpd_can_network_connect 1
            setsebool -P httpd_can_network_connect_db 1
            setsebool -P httpd_can_sendmail 1
            setsebool -P httpd_unified 1

            # Set correct context for FreePanel directory
            semanage fcontext -a -t httpd_sys_rw_content_t "$FREEPANEL_DIR/storage(/.*)?" 2>/dev/null || true
            semanage fcontext -a -t httpd_sys_rw_content_t "$FREEPANEL_DIR/bootstrap/cache(/.*)?" 2>/dev/null || true
            restorecon -Rv $FREEPANEL_DIR 2>/dev/null || true

            # Allow custom port for FreePanel
            semanage port -a -t http_port_t -p tcp 8080 2>/dev/null || true

            log_success "SELinux policies applied"
        else
            log_info "SELinux is disabled, skipping configuration"
        fi
    else
        log_info "SELinux not installed, skipping configuration"
    fi
}

configure_firewall() {
    log_step "Configuring Firewall"

    case $OS in
        ubuntu|debian)
            if command -v ufw &>/dev/null; then
                ufw allow 22/tcp comment 'SSH'
                ufw allow 80/tcp comment 'HTTP'
                ufw allow 443/tcp comment 'HTTPS'
                ufw allow 8080/tcp comment 'FreePanel'
                ufw allow 2087/tcp comment 'FreePanel Admin'
                ufw allow 2083/tcp comment 'FreePanel User'
                ufw allow 21/tcp comment 'FTP'
                ufw allow 25/tcp comment 'SMTP'
                ufw allow 465/tcp comment 'SMTPS'
                ufw allow 587/tcp comment 'Submission'
                ufw allow 110/tcp comment 'POP3'
                ufw allow 143/tcp comment 'IMAP'
                ufw allow 993/tcp comment 'IMAPS'
                ufw allow 995/tcp comment 'POP3S'
                ufw allow 53/tcp comment 'DNS TCP'
                ufw allow 53/udp comment 'DNS UDP'
                ufw --force enable
            fi
            ;;
        almalinux|rocky|rhel|centos)
            if systemctl is-active --quiet firewalld; then
                # Basic services
                firewall-cmd --permanent --add-service=http
                firewall-cmd --permanent --add-service=https
                firewall-cmd --permanent --add-service=ssh
                firewall-cmd --permanent --add-service=ftp
                firewall-cmd --permanent --add-service=smtp
                firewall-cmd --permanent --add-service=smtps
                firewall-cmd --permanent --add-service=smtp-submission
                firewall-cmd --permanent --add-service=pop3
                firewall-cmd --permanent --add-service=pop3s
                firewall-cmd --permanent --add-service=imap
                firewall-cmd --permanent --add-service=imaps
                firewall-cmd --permanent --add-service=dns

                # Custom ports
                firewall-cmd --permanent --add-port=8080/tcp
                firewall-cmd --permanent --add-port=2087/tcp
                firewall-cmd --permanent --add-port=2083/tcp

                # FTP passive ports
                firewall-cmd --permanent --add-port=30000-31000/tcp

                firewall-cmd --reload
            else
                log_warning "Firewalld is not running, skipping firewall configuration"
            fi
            ;;
    esac

    log_success "Firewall configured"
}

start_services() {
    log_step "Starting Services"

    case $OS in
        ubuntu|debian)
            systemctl restart apache2
            systemctl restart php${PHP_VERSION}-fpm
            systemctl restart mariadb
            systemctl restart redis-server
            ;;
        almalinux|rocky|rhel|centos)
            systemctl restart httpd
            systemctl restart php-fpm
            systemctl restart mariadb
            systemctl restart redis
            ;;
    esac

    systemctl restart freepanel
    systemctl restart freepanel-worker

    log_success "All services started"
}

print_summary() {
    SERVER_IP=$(hostname -I | awk '{print $1}')

    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                                ║${NC}"
    echo -e "${GREEN}║          FreePanel Installation Complete!                     ║${NC}"
    echo -e "${GREEN}║                                                                ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${CYAN}Access FreePanel:${NC}"
    echo -e "  Main Panel:    ${BLUE}http://${SERVER_IP}:8080${NC}"
    echo ""
    echo -e "${CYAN}Admin Credentials:${NC}"
    echo -e "  Saved to:      ${YELLOW}/root/.freepanel_credentials${NC}"
    echo ""
    echo -e "${CYAN}Important Directories:${NC}"
    echo "  FreePanel:     $FREEPANEL_DIR"
    echo "  User Homes:    /home/*"
    echo "  Backups:       /var/backups/freepanel"
    echo "  Logs:          /var/log/freepanel"
    echo ""
    echo -e "${CYAN}Services Installed:${NC}"
    echo "  - Apache Web Server (httpd)"
    echo "  - PHP $PHP_VERSION with FPM"
    echo "  - MariaDB Database"
    echo "  - Redis Cache"
    echo "  - Dovecot IMAP/POP3"
    echo "  - Mail Server (Exim/Postfix)"
    echo "  - BIND DNS Server"
    echo "  - Pure-FTPd"
    echo "  - Let's Encrypt (Certbot)"
    echo ""
    echo -e "${CYAN}Useful Commands:${NC}"
    echo "  systemctl status freepanel     - Check FreePanel status"
    echo "  systemctl restart freepanel    - Restart FreePanel"
    echo "  journalctl -u freepanel -f     - View live logs"
    echo ""
    echo -e "${CYAN}Installation Log:${NC}"
    echo "  $INSTALL_LOG"
    echo ""
    echo -e "${YELLOW}IMPORTANT: Please change the admin password after first login!${NC}"
    echo ""
}

# Main installation
main() {
    clear
    echo ""
    echo -e "${CYAN}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                                                                ║${NC}"
    echo -e "${CYAN}║          FreePanel Installation Script                        ║${NC}"
    echo -e "${CYAN}║          Optimized for AlmaLinux / Rocky Linux                ║${NC}"
    echo -e "${CYAN}║                                                                ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════════════════════════════╝${NC}"
    echo ""

    check_root
    check_memory
    detect_os

    # Setup RHEL-specific repos
    case $OS in
        almalinux|rocky|rhel|centos)
            setup_rhel_repos
            ;;
    esac

    install_dependencies
    create_freepanel_user
    install_webserver
    install_php
    install_mysql
    install_redis
    install_mail
    install_dns
    install_ftp
    install_nodejs
    install_composer
    install_certbot
    clone_freepanel
    create_env_file
    setup_freepanel
    setup_database
    create_admin
    create_systemd_service

    # RHEL-specific configurations
    case $OS in
        almalinux|rocky|rhel|centos)
            configure_selinux
            ;;
    esac

    configure_firewall
    start_services
    print_summary

    log_success "Installation completed successfully!"
}

# Run main function
main "$@"
