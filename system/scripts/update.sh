#!/bin/bash
#
# FreePanel Update Script
# Updates FreePanel to the latest version
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

FREEPANEL_DIR="/opt/freepanel"
FREEPANEL_USER="freepanel"
BACKUP_DIR="/var/backups/freepanel/updates"

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
        log_error "This script must be run as root. Use: sudo bash update.sh"
    fi
}

create_backup() {
    log_info "Creating backup before update..."

    mkdir -p "$BACKUP_DIR"
    BACKUP_FILE="$BACKUP_DIR/freepanel-$(date +%Y%m%d-%H%M%S).tar.gz"

    # Backup important files
    tar -czf "$BACKUP_FILE" \
        -C "$FREEPANEL_DIR" \
        .env \
        storage/app \
        storage/logs \
        2>/dev/null || true

    log_success "Backup created: $BACKUP_FILE"
}

stop_services() {
    log_info "Stopping FreePanel services..."

    systemctl stop freepanel-worker 2>/dev/null || true
    systemctl stop freepanel 2>/dev/null || true

    log_success "Services stopped"
}

update_code() {
    log_info "Pulling latest code from repository..."

    cd "$FREEPANEL_DIR"

    # Stash any local changes
    sudo -u $FREEPANEL_USER git stash 2>/dev/null || true

    # Pull latest changes
    sudo -u $FREEPANEL_USER git fetch origin
    sudo -u $FREEPANEL_USER git pull origin main

    log_success "Code updated"
}

update_dependencies() {
    log_info "Updating PHP dependencies..."

    cd "$FREEPANEL_DIR"
    sudo -u $FREEPANEL_USER composer install --no-dev --optimize-autoloader --no-interaction

    log_info "Updating Node.js dependencies and rebuilding frontend..."

    cd "$FREEPANEL_DIR/frontend"
    sudo -u $FREEPANEL_USER npm ci --no-audit
    sudo -u $FREEPANEL_USER npm run build

    log_success "Dependencies updated"
}

run_migrations() {
    log_info "Running database migrations..."

    cd "$FREEPANEL_DIR"
    sudo -u $FREEPANEL_USER php artisan migrate --force

    log_success "Migrations complete"
}

clear_cache() {
    log_info "Clearing and rebuilding caches..."

    cd "$FREEPANEL_DIR"
    sudo -u $FREEPANEL_USER php artisan config:clear
    sudo -u $FREEPANEL_USER php artisan route:clear
    sudo -u $FREEPANEL_USER php artisan view:clear
    sudo -u $FREEPANEL_USER php artisan cache:clear

    sudo -u $FREEPANEL_USER php artisan config:cache
    sudo -u $FREEPANEL_USER php artisan route:cache
    sudo -u $FREEPANEL_USER php artisan view:cache

    log_success "Caches rebuilt"
}

fix_permissions() {
    log_info "Fixing file permissions..."

    chown -R $FREEPANEL_USER:$FREEPANEL_USER "$FREEPANEL_DIR"
    chmod -R 755 "$FREEPANEL_DIR"
    chmod -R 775 "$FREEPANEL_DIR/storage"
    chmod -R 775 "$FREEPANEL_DIR/bootstrap/cache"

    log_success "Permissions fixed"
}

start_services() {
    log_info "Starting FreePanel services..."

    systemctl start freepanel
    systemctl start freepanel-worker

    # Wait and verify
    sleep 3
    if systemctl is-active --quiet freepanel; then
        log_success "FreePanel is running"
    else
        log_error "FreePanel failed to start. Check: journalctl -u freepanel"
    fi
}

print_summary() {
    # Get new version
    VERSION=$(grep "'version'" "$FREEPANEL_DIR/config/freepanel.php" | grep -oP "'\K[0-9]+\.[0-9]+\.[0-9]+" || echo "unknown")

    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                                ║${NC}"
    echo -e "${GREEN}║              FreePanel Update Complete!                        ║${NC}"
    echo -e "${GREEN}║                                                                ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "  Version:      ${BLUE}v${VERSION}${NC}"
    echo -e "  Backup:       ${BACKUP_FILE}"
    echo ""
    echo -e "  Status:       $(systemctl is-active freepanel 2>/dev/null || echo 'unknown')"
    echo ""
}

# Main
main() {
    echo ""
    echo -e "${CYAN}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                  FreePanel Update Script                       ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════════════════════════════╝${NC}"
    echo ""

    check_root

    if [ ! -d "$FREEPANEL_DIR" ]; then
        log_error "FreePanel is not installed at $FREEPANEL_DIR"
    fi

    create_backup
    stop_services
    update_code
    update_dependencies
    run_migrations
    clear_cache
    fix_permissions
    start_services
    print_summary
}

main "$@"
