#!/bin/bash
#
# FreePanel Installation Script
# This script installs and configures FreePanel on a fresh server
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
FREEPANEL_DIR="/opt/freepanel"
FREEPANEL_USER="freepanel"
FREEPANEL_REPO="https://github.com/JSXSTEWART/FreePanel.git"
FREEPANEL_BRANCH="main"
PHP_VERSION="8.2"
NODE_VERSION="20"

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root"
    fi
}

detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$ID
        VERSION=$VERSION_ID
    else
        log_error "Cannot detect operating system"
    fi

    case $OS in
        ubuntu|debian)
            PKG_MANAGER="apt-get"
            PKG_UPDATE="apt-get update"
            PKG_INSTALL="apt-get install -y"
            ;;
        centos|rhel|rocky|almalinux)
            PKG_MANAGER="dnf"
            PKG_UPDATE="dnf check-update || true"
            PKG_INSTALL="dnf install -y"
            ;;
        *)
            log_error "Unsupported operating system: $OS"
            ;;
    esac

    log_info "Detected OS: $OS $VERSION"
}

install_dependencies() {
    log_info "Installing system dependencies..."

    $PKG_UPDATE

    # Common packages
    $PKG_INSTALL curl wget git unzip tar gzip bzip2 \
        vim nano htop \
        openssl ca-certificates \
        cron logrotate

    log_success "System dependencies installed"
}

install_webserver() {
    log_info "Installing Apache web server..."

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL apache2 libapache2-mod-fcgid
            a2enmod rewrite headers ssl proxy_fcgi setenvif
            systemctl enable apache2
            ;;
        centos|rhel|rocky|almalinux)
            $PKG_INSTALL httpd mod_ssl mod_fcgid
            systemctl enable httpd
            ;;
    esac

    log_success "Apache installed and enabled"
}

install_php() {
    log_info "Installing PHP $PHP_VERSION..."

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL software-properties-common
            add-apt-repository -y ppa:ondrej/php
            $PKG_UPDATE
            $PKG_INSTALL php${PHP_VERSION} php${PHP_VERSION}-fpm \
                php${PHP_VERSION}-mysql php${PHP_VERSION}-curl \
                php${PHP_VERSION}-gd php${PHP_VERSION}-mbstring \
                php${PHP_VERSION}-xml php${PHP_VERSION}-zip \
                php${PHP_VERSION}-bcmath php${PHP_VERSION}-intl \
                php${PHP_VERSION}-redis php${PHP_VERSION}-imagick
            systemctl enable php${PHP_VERSION}-fpm
            ;;
        centos|rhel|rocky|almalinux)
            $PKG_INSTALL https://rpms.remirepo.net/enterprise/remi-release-$(rpm -E %{rhel}).rpm
            dnf module reset php -y
            dnf module enable php:remi-${PHP_VERSION} -y
            $PKG_INSTALL php php-fpm php-mysqlnd php-curl php-gd \
                php-mbstring php-xml php-zip php-bcmath php-intl \
                php-redis php-imagick
            systemctl enable php-fpm
            ;;
    esac

    log_success "PHP $PHP_VERSION installed"
}

install_mysql() {
    log_info "Installing MariaDB..."

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL mariadb-server mariadb-client
            systemctl enable mariadb
            ;;
        centos|rhel|rocky|almalinux)
            $PKG_INSTALL mariadb-server mariadb
            systemctl enable mariadb
            ;;
    esac

    systemctl start mariadb

    log_success "MariaDB installed and started"
}

install_mail() {
    log_info "Installing mail services..."

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL dovecot-core dovecot-imapd dovecot-pop3d \
                dovecot-lmtpd dovecot-mysql \
                exim4 exim4-daemon-heavy
            systemctl enable dovecot exim4
            ;;
        centos|rhel|rocky|almalinux)
            $PKG_INSTALL dovecot dovecot-mysql \
                exim exim-mysql
            systemctl enable dovecot exim
            ;;
    esac

    log_success "Mail services installed"
}

install_dns() {
    log_info "Installing BIND DNS server..."

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL bind9 bind9utils
            systemctl enable named
            ;;
        centos|rhel|rocky|almalinux)
            $PKG_INSTALL bind bind-utils
            systemctl enable named
            ;;
    esac

    log_success "BIND DNS installed"
}

install_ftp() {
    log_info "Installing Pure-FTPd..."

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL pure-ftpd pure-ftpd-mysql
            systemctl enable pure-ftpd
            ;;
        centos|rhel|rocky|almalinux)
            $PKG_INSTALL pure-ftpd
            systemctl enable pure-ftpd
            ;;
    esac

    log_success "Pure-FTPd installed"
}

install_nodejs() {
    log_info "Installing Node.js $NODE_VERSION..."

    curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash -
    $PKG_INSTALL nodejs

    npm install -g npm@latest

    log_success "Node.js installed"
}

install_composer() {
    log_info "Installing Composer..."

    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

    log_success "Composer installed"
}

install_certbot() {
    log_info "Installing Certbot for Let's Encrypt..."

    case $OS in
        ubuntu|debian)
            $PKG_INSTALL certbot python3-certbot-apache
            ;;
        centos|rhel|rocky|almalinux)
            $PKG_INSTALL certbot python3-certbot-apache
            ;;
    esac

    log_success "Certbot installed"
}

clone_freepanel() {
    log_info "Cloning FreePanel from GitHub..."

    # Remove existing directory if present
    if [ -d "$FREEPANEL_DIR" ]; then
        log_warning "Removing existing FreePanel installation..."
        rm -rf $FREEPANEL_DIR
    fi

    # Clone repository
    git clone --branch $FREEPANEL_BRANCH $FREEPANEL_REPO $FREEPANEL_DIR

    if [ $? -ne 0 ]; then
        log_error "Failed to clone FreePanel repository"
    fi

    log_success "FreePanel cloned successfully"
}

setup_freepanel() {
    log_info "Setting up FreePanel..."

    # Create FreePanel user
    useradd -r -m -d /home/$FREEPANEL_USER -s /bin/bash $FREEPANEL_USER 2>/dev/null || true

    # Set ownership
    chown -R $FREEPANEL_USER:$FREEPANEL_USER $FREEPANEL_DIR

    # Setup environment file
    cd $FREEPANEL_DIR
    if [ ! -f .env ]; then
        cp .env.example .env
        log_info "Created .env file from .env.example"
    fi

    # Install PHP dependencies
    log_info "Installing PHP dependencies..."
    sudo -u $FREEPANEL_USER composer install --no-dev --optimize-autoloader

    # Install Node dependencies and build frontend
    log_info "Building frontend..."
    cd $FREEPANEL_DIR/frontend
    sudo -u $FREEPANEL_USER npm install
    sudo -u $FREEPANEL_USER npm run build

    # Copy frontend build to public directory
    if [ -d "$FREEPANEL_DIR/frontend/dist" ]; then
        cp -r $FREEPANEL_DIR/frontend/dist/* $FREEPANEL_DIR/public/
        log_info "Frontend assets copied to public directory"
    fi

    # Generate application key
    cd $FREEPANEL_DIR
    sudo -u $FREEPANEL_USER php artisan key:generate --force

    # Set storage permissions
    chmod -R 775 $FREEPANEL_DIR/storage
    chmod -R 775 $FREEPANEL_DIR/bootstrap/cache

    log_success "FreePanel setup complete"
}

setup_database() {
    log_info "Setting up database..."

    # Generate random password
    DB_PASS=$(openssl rand -base64 12)

    # Create database and user
    mysql -e "CREATE DATABASE IF NOT EXISTS freepanel;"
    mysql -e "CREATE USER IF NOT EXISTS 'freepanel'@'localhost' IDENTIFIED BY '$DB_PASS';"
    mysql -e "GRANT ALL PRIVILEGES ON freepanel.* TO 'freepanel'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"

    # Update .env with database credentials
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" $FREEPANEL_DIR/.env

    # Run migrations
    cd $FREEPANEL_DIR
    sudo -u $FREEPANEL_USER php artisan migrate --force --seed

    log_success "Database configured"
}

create_admin() {
    log_info "Creating admin user..."

    cd $FREEPANEL_DIR
    sudo -u $FREEPANEL_USER php artisan freepanel:create-admin

    log_success "Admin user created"
}

create_systemd_service() {
    log_info "Creating systemd service..."

    cat > /etc/systemd/system/freepanel.service << EOF
[Unit]
Description=FreePanel Web Hosting Control Panel
After=network.target mariadb.service

[Service]
User=$FREEPANEL_USER
Group=$FREEPANEL_USER
WorkingDirectory=$FREEPANEL_DIR
ExecStart=/usr/bin/php artisan serve --host=0.0.0.0 --port=8080
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable freepanel
    systemctl start freepanel

    log_success "FreePanel service created and started"
}

configure_firewall() {
    log_info "Configuring firewall..."

    case $OS in
        ubuntu|debian)
            ufw allow 22/tcp
            ufw allow 80/tcp
            ufw allow 443/tcp
            ufw allow 8080/tcp
            ufw allow 21/tcp
            ufw allow 25/tcp
            ufw allow 465/tcp
            ufw allow 587/tcp
            ufw allow 110/tcp
            ufw allow 143/tcp
            ufw allow 993/tcp
            ufw allow 995/tcp
            ufw allow 53/tcp
            ufw allow 53/udp
            ufw --force enable
            ;;
        centos|rhel|rocky|almalinux)
            firewall-cmd --permanent --add-service=http
            firewall-cmd --permanent --add-service=https
            firewall-cmd --permanent --add-service=ftp
            firewall-cmd --permanent --add-service=smtp
            firewall-cmd --permanent --add-service=smtps
            firewall-cmd --permanent --add-service=pop3
            firewall-cmd --permanent --add-service=pop3s
            firewall-cmd --permanent --add-service=imap
            firewall-cmd --permanent --add-service=imaps
            firewall-cmd --permanent --add-service=dns
            firewall-cmd --permanent --add-port=8080/tcp
            firewall-cmd --reload
            ;;
    esac

    log_success "Firewall configured"
}

print_summary() {
    SERVER_IP=$(hostname -I | awk '{print $1}')

    echo ""
    echo "========================================"
    echo -e "${GREEN}FreePanel Installation Complete!${NC}"
    echo "========================================"
    echo ""
    echo "Access FreePanel at:"
    echo -e "  ${BLUE}http://${SERVER_IP}:8080${NC}"
    echo ""
    echo "Default admin credentials have been created."
    echo "Please check the installation logs for details."
    echo ""
    echo "Important directories:"
    echo "  FreePanel:  $FREEPANEL_DIR"
    echo "  Web Root:   /home/*/public_html"
    echo "  Logs:       /home/*/logs"
    echo ""
    echo "Services installed:"
    echo "  - Apache Web Server"
    echo "  - PHP $PHP_VERSION with FPM"
    echo "  - MariaDB Database"
    echo "  - Dovecot IMAP/POP3"
    echo "  - Exim Mail Server"
    echo "  - BIND DNS Server"
    echo "  - Pure-FTPd"
    echo "  - Let's Encrypt (Certbot)"
    echo ""
    echo "========================================"
}

# Main installation
main() {
    echo ""
    echo "========================================"
    echo "  FreePanel Installation Script"
    echo "========================================"
    echo ""

    check_root
    detect_os
    install_dependencies
    install_webserver
    install_php
    install_mysql
    install_mail
    install_dns
    install_ftp
    install_nodejs
    install_composer
    install_certbot
    clone_freepanel
    setup_freepanel
    setup_database
    create_admin
    create_systemd_service
    configure_firewall
    print_summary
}

main "$@"
