#!/bin/bash
#
# FreePanel Status Check Script
# Shows the status of all FreePanel services and system resources
#

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

FREEPANEL_DIR="/opt/freepanel"

check_service() {
    local service=$1
    local display_name=$2

    if systemctl is-active --quiet "$service" 2>/dev/null; then
        echo -e "  ${GREEN}●${NC} $display_name: ${GREEN}Running${NC}"
        return 0
    elif systemctl is-enabled --quiet "$service" 2>/dev/null; then
        echo -e "  ${RED}●${NC} $display_name: ${RED}Stopped${NC} (enabled)"
        return 1
    else
        echo -e "  ${YELLOW}○${NC} $display_name: ${YELLOW}Not installed${NC}"
        return 2
    fi
}

print_header() {
    echo ""
    echo -e "${CYAN}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                  FreePanel System Status                       ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

print_section() {
    echo ""
    echo -e "${BLUE}═══ $1 ═══${NC}"
    echo ""
}

# Main
print_header

# Server Info
print_section "Server Information"
echo -e "  Hostname:     $(hostname -f 2>/dev/null || hostname)"
echo -e "  IP Address:   $(hostname -I | awk '{print $1}')"
echo -e "  OS:           $(cat /etc/os-release | grep PRETTY_NAME | cut -d'"' -f2)"
echo -e "  Kernel:       $(uname -r)"
echo -e "  Uptime:       $(uptime -p)"

# FreePanel Version
if [ -f "$FREEPANEL_DIR/config/freepanel.php" ]; then
    VERSION=$(grep "'version'" "$FREEPANEL_DIR/config/freepanel.php" | grep -oP "'\K[0-9]+\.[0-9]+\.[0-9]+")
    echo -e "  FreePanel:    v${VERSION:-unknown}"
fi

# Resource Usage
print_section "Resource Usage"
# CPU
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
echo -e "  CPU Usage:    ${CPU_USAGE}%"

# Memory
MEM_TOTAL=$(free -h | awk '/^Mem:/{print $2}')
MEM_USED=$(free -h | awk '/^Mem:/{print $3}')
MEM_PERCENT=$(free | awk '/^Mem:/{printf "%.1f", $3/$2*100}')
echo -e "  Memory:       ${MEM_USED} / ${MEM_TOTAL} (${MEM_PERCENT}%)"

# Disk
DISK_USAGE=$(df -h / | awk 'NR==2{print $3 " / " $2 " (" $5 ")"}')
echo -e "  Disk (/):     ${DISK_USAGE}"

# Load Average
LOAD=$(uptime | awk -F'load average:' '{print $2}' | xargs)
echo -e "  Load Avg:     ${LOAD}"

# FreePanel Services
print_section "FreePanel Services"
check_service "freepanel" "FreePanel App"
check_service "freepanel-worker" "Queue Worker"

# Web Services
print_section "Web Services"
check_service "httpd" "Apache (httpd)" || check_service "apache2" "Apache (apache2)"
check_service "nginx" "Nginx"
check_service "php-fpm" "PHP-FPM" || check_service "php8.2-fpm" "PHP 8.2 FPM"

# Database Services
print_section "Database Services"
check_service "mariadb" "MariaDB"
check_service "redis" "Redis" || check_service "redis-server" "Redis"

# Mail Services
print_section "Mail Services"
check_service "dovecot" "Dovecot (IMAP/POP3)"
check_service "exim" "Exim (SMTP)" || check_service "exim4" "Exim4 (SMTP)"
check_service "postfix" "Postfix (SMTP)"

# Other Services
print_section "Other Services"
check_service "named" "BIND DNS"
check_service "pure-ftpd" "Pure-FTPd"

# Active Connections
print_section "Network Status"
if command -v ss &>/dev/null; then
    HTTP_CONN=$(ss -tun | grep -c ":80 " 2>/dev/null || echo "0")
    HTTPS_CONN=$(ss -tun | grep -c ":443 " 2>/dev/null || echo "0")
    FP_CONN=$(ss -tun | grep -c ":8080 " 2>/dev/null || echo "0")
    echo -e "  HTTP (:80):   ${HTTP_CONN} connections"
    echo -e "  HTTPS (:443): ${HTTPS_CONN} connections"
    echo -e "  Panel (:8080): ${FP_CONN} connections"
fi

# Quick Links
print_section "Access URLs"
SERVER_IP=$(hostname -I | awk '{print $1}')
echo -e "  FreePanel:    ${BLUE}http://${SERVER_IP}:8080${NC}"
echo -e "  Admin Creds:  /root/.freepanel_credentials"

echo ""
echo -e "${CYAN}════════════════════════════════════════════════════════════════${NC}"
echo ""
