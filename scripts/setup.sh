#!/bin/bash
# FreePanel Setup Script
# Usage: ./scripts/setup.sh [--test] [--build] [--all]

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Parse arguments
RUN_TESTS=false
RUN_BUILD=false
RUN_ALL=false

for arg in "$@"; do
    case $arg in
        --test) RUN_TESTS=true ;;
        --build) RUN_BUILD=true ;;
        --all) RUN_ALL=true ;;
        --help|-h)
            echo "Usage: ./scripts/setup.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --test   Run tests after setup"
            echo "  --build  Build frontend after setup"
            echo "  --all    Run full setup with tests and build"
            echo "  --help   Show this help message"
            exit 0
            ;;
    esac
done

if [ "$RUN_ALL" = true ]; then
    RUN_TESTS=true
    RUN_BUILD=true
fi

cd "$PROJECT_DIR"

# 1. Create required directories
log_info "Creating required directories..."
mkdir -p bootstrap/cache
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
chmod -R 775 storage bootstrap/cache

# 2. Setup environment file
if [ ! -f .env ]; then
    log_info "Creating .env from .env.example..."
    cp .env.example .env
else
    log_info ".env already exists, skipping..."
fi

# 3. Install PHP dependencies
if [ -f composer.json ]; then
    log_info "Installing PHP dependencies..."
    if command -v composer &> /dev/null; then
        composer install --no-interaction --prefer-dist
    else
        log_warn "Composer not found, skipping PHP dependencies"
    fi
fi

# 4. Generate application key
if command -v php &> /dev/null && [ -f artisan ]; then
    if grep -q "APP_KEY=$" .env || grep -q "APP_KEY=\"\"" .env; then
        log_info "Generating application key..."
        php artisan key:generate --no-interaction
    else
        log_info "Application key already set, skipping..."
    fi
fi

# 5. Install frontend dependencies
if [ -f frontend/package.json ]; then
    log_info "Installing frontend dependencies..."
    cd frontend
    if command -v npm &> /dev/null; then
        npm install
    else
        log_warn "npm not found, skipping frontend dependencies"
    fi
    cd "$PROJECT_DIR"
fi

# 6. Build frontend (optional)
if [ "$RUN_BUILD" = true ] && [ -f frontend/package.json ]; then
    log_info "Building frontend..."
    cd frontend
    npm run build
    cd "$PROJECT_DIR"
fi

# 7. Run tests (optional)
if [ "$RUN_TESTS" = true ]; then
    log_info "Running tests..."

    # PHP tests
    if [ -f vendor/bin/phpunit ]; then
        log_info "Running PHPUnit tests..."
        ./vendor/bin/phpunit || log_warn "Some PHP tests failed"
    fi

    # Frontend tests
    if [ -f frontend/package.json ]; then
        cd frontend
        if npm run test --if-present 2>/dev/null; then
            log_info "Frontend tests passed"
        fi
        cd "$PROJECT_DIR"
    fi
fi

# 8. Security audit
log_info "Running security audits..."
if [ -f frontend/package.json ]; then
    cd frontend
    npm audit --audit-level=moderate || log_warn "Some npm vulnerabilities found"
    cd "$PROJECT_DIR"
fi

if command -v composer &> /dev/null; then
    composer audit 2>/dev/null || log_warn "Composer audit not available"
fi

log_info "Setup complete!"
echo ""
echo "Next steps:"
echo "  1. Configure .env with your database settings"
echo "  2. Run: php artisan migrate"
echo "  3. Run: php artisan serve"
echo "  4. Frontend dev: cd frontend && npm run dev"
