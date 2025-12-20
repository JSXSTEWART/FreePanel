#!/bin/bash
#
# FreePanel Uninstall Script
# Removes FreePanel and optionally its data
#

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

FREEPANEL_DIR="/opt/freepanel"
FREEPANEL_USER="freepanel"

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
        log_error "This script must be run as root. Use: sudo bash uninstall.sh"
    fi
}

confirm_uninstall() {
    echo ""
    echo -e "${RED}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║                       WARNING                                  ║${NC}"
    echo -e "${RED}║  This will remove FreePanel from your system!                 ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo "This script will:"
    echo "  - Stop and disable FreePanel services"
    echo "  - Remove FreePanel application files"
    echo "  - Optionally remove the database"
    echo "  - Optionally remove user home directories"
    echo ""
    echo -e "${YELLOW}Note: This will NOT remove Apache, PHP, MariaDB, or other${NC}"
    echo -e "${YELLOW}system services that were installed during setup.${NC}"
    echo ""

    read -p "Are you sure you want to continue? (yes/no): " CONFIRM
    if [ "$CONFIRM" != "yes" ]; then
        echo "Uninstall cancelled."
        exit 0
    fi
}

stop_services() {
    log_info "Stopping FreePanel services..."

    systemctl stop freepanel-worker 2>/dev/null || true
    systemctl stop freepanel 2>/dev/null || true
    systemctl disable freepanel-worker 2>/dev/null || true
    systemctl disable freepanel 2>/dev/null || true

    rm -f /etc/systemd/system/freepanel.service
    rm -f /etc/systemd/system/freepanel-worker.service
    systemctl daemon-reload

    log_success "Services stopped and disabled"
}

remove_database() {
    echo ""
    read -p "Remove FreePanel database? This will delete ALL hosting data! (yes/no): " REMOVE_DB
    if [ "$REMOVE_DB" = "yes" ]; then
        log_info "Removing database..."
        mysql -e "DROP DATABASE IF EXISTS freepanel;" 2>/dev/null || true
        mysql -e "DROP USER IF EXISTS 'freepanel'@'localhost';" 2>/dev/null || true
        mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true
        log_success "Database removed"
    else
        log_info "Database preserved"
    fi
}

remove_user_data() {
    echo ""
    echo -e "${RED}WARNING: This will delete all user websites and data!${NC}"
    read -p "Remove all user home directories (/home/*)? (yes/no): " REMOVE_HOMES
    if [ "$REMOVE_HOMES" = "yes" ]; then
        log_warning "Removing user home directories..."
        # Only remove directories created by FreePanel (have public_html)
        for dir in /home/*/public_html; do
            if [ -d "$dir" ]; then
                user_home=$(dirname "$dir")
                username=$(basename "$user_home")
                if [ "$username" != "freepanel" ]; then
                    log_info "Removing $user_home..."
                    rm -rf "$user_home"
                    userdel "$username" 2>/dev/null || true
                fi
            fi
        done
        log_success "User directories removed"
    else
        log_info "User directories preserved"
    fi
}

remove_freepanel() {
    log_info "Removing FreePanel application..."

    # Backup .env first
    if [ -f "$FREEPANEL_DIR/.env" ]; then
        cp "$FREEPANEL_DIR/.env" /root/.freepanel_env_backup
        log_info "Environment file backed up to /root/.freepanel_env_backup"
    fi

    rm -rf "$FREEPANEL_DIR"
    rm -rf /var/log/freepanel
    rm -f /root/.freepanel_credentials

    log_success "FreePanel application removed"
}

remove_freepanel_user() {
    log_info "Removing FreePanel system user..."

    userdel -r $FREEPANEL_USER 2>/dev/null || true

    log_success "FreePanel user removed"
}

print_summary() {
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                                ║${NC}"
    echo -e "${GREEN}║              FreePanel Uninstall Complete                      ║${NC}"
    echo -e "${GREEN}║                                                                ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo "FreePanel has been removed from your system."
    echo ""
    echo "The following services are still installed and running:"
    echo "  - Apache/Nginx web server"
    echo "  - PHP and PHP-FPM"
    echo "  - MariaDB/MySQL"
    echo "  - Dovecot, Exim/Postfix"
    echo "  - BIND DNS"
    echo "  - Pure-FTPd"
    echo ""
    echo "To remove these services, use your package manager:"
    echo "  dnf remove httpd php mariadb-server  # AlmaLinux/Rocky"
    echo "  apt remove apache2 php mariadb-server  # Ubuntu/Debian"
    echo ""
}

# Main
main() {
    echo ""
    echo -e "${CYAN}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                FreePanel Uninstall Script                      ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════════════════════════════╝${NC}"
    echo ""

    check_root
    confirm_uninstall
    stop_services
    remove_database
    remove_user_data
    remove_freepanel
    remove_freepanel_user
    print_summary
}

main "$@"
