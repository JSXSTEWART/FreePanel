#!/bin/bash
# FreePanel Deployment Test Script
# Validates the application can be deployed successfully

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[✓]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[!]${NC} $1"; }
log_error() { echo -e "${RED}[✗]${NC} $1"; }
log_test() { echo -e "    Testing: $1..."; }

ERRORS=0
WARNINGS=0

check_pass() { log_info "$1"; }
check_fail() { log_error "$1"; ((ERRORS++)); }
check_warn() { log_warn "$1"; ((WARNINGS++)); }

cd "$PROJECT_DIR"

echo "========================================"
echo "  FreePanel Deployment Test"
echo "========================================"
echo ""

# 1. Check PHP
echo "1. Checking PHP environment..."
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2)
    check_pass "PHP installed: $PHP_VERSION"

    # Check PHP version >= 8.2
    if php -r "exit(version_compare(PHP_VERSION, '8.2.0', '>=') ? 0 : 1);"; then
        check_pass "PHP version >= 8.2"
    else
        check_fail "PHP version must be >= 8.2"
    fi
else
    check_fail "PHP not installed"
fi

# 2. Check Composer
echo ""
echo "2. Checking Composer..."
if command -v composer &> /dev/null; then
    check_pass "Composer installed"
else
    check_fail "Composer not installed"
fi

# 3. Check Node.js/npm
echo ""
echo "3. Checking Node.js environment..."
if command -v node &> /dev/null; then
    NODE_VERSION=$(node -v)
    check_pass "Node.js installed: $NODE_VERSION"
else
    check_fail "Node.js not installed"
fi

if command -v npm &> /dev/null; then
    NPM_VERSION=$(npm -v)
    check_pass "npm installed: $NPM_VERSION"
else
    check_fail "npm not installed"
fi

# 4. Check required files
echo ""
echo "4. Checking required files..."
[ -f composer.json ] && check_pass "composer.json exists" || check_fail "composer.json missing"
[ -f .env ] && check_pass ".env exists" || check_warn ".env missing (run setup.sh first)"
[ -f artisan ] && check_pass "artisan exists" || check_fail "artisan missing"
[ -d vendor ] && check_pass "vendor/ exists" || check_warn "vendor/ missing (run composer install)"
[ -f frontend/package.json ] && check_pass "frontend/package.json exists" || check_fail "frontend/package.json missing"
[ -d frontend/node_modules ] && check_pass "frontend/node_modules exists" || check_warn "frontend/node_modules missing (run npm install)"

# 5. Check directories are writable
echo ""
echo "5. Checking directory permissions..."
[ -w storage ] && check_pass "storage/ is writable" || check_fail "storage/ not writable"
[ -w bootstrap/cache ] && check_pass "bootstrap/cache/ is writable" || check_fail "bootstrap/cache/ not writable"

# 6. Check Laravel configuration
echo ""
echo "6. Checking Laravel configuration..."
if [ -f .env ]; then
    if grep -q "APP_KEY=base64:" .env; then
        check_pass "APP_KEY is set"
    else
        check_warn "APP_KEY not set (run php artisan key:generate)"
    fi
fi

# 7. Test artisan commands
echo ""
echo "7. Testing Laravel artisan..."
if php artisan --version &> /dev/null; then
    LARAVEL_VERSION=$(php artisan --version)
    check_pass "Laravel artisan works: $LARAVEL_VERSION"
else
    check_fail "Laravel artisan failed"
fi

# 8. Frontend build test
echo ""
echo "8. Testing frontend build..."
if [ -d frontend/node_modules ]; then
    cd frontend
    if npm run build &> /dev/null; then
        check_pass "Frontend builds successfully"
    else
        check_fail "Frontend build failed"
    fi
    cd "$PROJECT_DIR"
else
    check_warn "Skipping frontend build (node_modules missing)"
fi

# 9. Check for built assets
echo ""
echo "9. Checking built assets..."
if [ -d public/build ] || [ -d frontend/dist ]; then
    check_pass "Built frontend assets exist"
else
    check_warn "No built frontend assets found"
fi

# 10. Security check
echo ""
echo "10. Running security checks..."
if [ -d frontend/node_modules ]; then
    cd frontend
    if npm audit --audit-level=high 2>/dev/null; then
        check_pass "No high severity npm vulnerabilities"
    else
        check_warn "npm audit found issues (run 'npm audit' for details)"
    fi
    cd "$PROJECT_DIR"
fi

# Summary
echo ""
echo "========================================"
echo "  Summary"
echo "========================================"
if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}All checks passed!${NC}"
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}Passed with $WARNINGS warning(s)${NC}"
    exit 0
else
    echo -e "${RED}Failed with $ERRORS error(s) and $WARNINGS warning(s)${NC}"
    exit 1
fi
