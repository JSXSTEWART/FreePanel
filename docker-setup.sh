#!/bin/bash

# FreePanel Docker Setup Script
# This script helps initialize the FreePanel Docker environment

set -e

echo "=========================================="
echo "FreePanel Docker Environment Setup"
echo "=========================================="
echo ""

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "Error: Docker is not installed. Please install Docker first."
    exit 1
fi

# Check if Docker Compose is available
if ! docker compose version &> /dev/null && ! command -v docker-compose &> /dev/null; then
    echo "Error: Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Use 'docker compose' or 'docker-compose' based on what's available
if docker compose version &> /dev/null; then
    COMPOSE="docker compose"
else
    COMPOSE="docker-compose"
fi

echo "Using: $COMPOSE"
echo ""

# Check if .env file exists
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
    
    # Update .env for Docker
    sed -i 's/APP_ENV=production/APP_ENV=local/' .env
    sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' .env
    sed -i 's/DB_HOST=127.0.0.1/DB_HOST=mysql/' .env
    sed -i 's/REDIS_HOST=127.0.0.1/REDIS_HOST=redis/' .env
    sed -i 's/MAIL_HOST=127.0.0.1/MAIL_HOST=mailpit/' .env
    sed -i 's/MAIL_PORT=25/MAIL_PORT=1025/' .env
    
    # Generate APP_KEY
    echo "Generating application key..."
    $COMPOSE run --rm app php artisan key:generate --ansi
else
    echo ".env file already exists."
fi

# Create necessary directories
echo "Creating necessary directories..."
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Set permissions
echo "Setting permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Build Docker images
echo ""
echo "Building Docker images (this may take a few minutes)..."
$COMPOSE build

# Start services
echo ""
echo "Starting Docker containers..."
$COMPOSE up -d

# Wait for database to be ready
echo ""
echo "Waiting for database to be ready..."
sleep 15

# Run migrations
echo ""
echo "Running database migrations..."
$COMPOSE exec -T app php artisan migrate --force || {
    echo "Migration failed, but continuing..."
}

# Seed database (optional)
read -p "Do you want to seed the database? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    $COMPOSE exec -T app php artisan db:seed || {
        echo "Seeding failed, but continuing..."
    }
fi

# Create admin user
echo ""
echo "Creating admin user..."
$COMPOSE exec app php artisan freepanel:create-admin || {
    echo "Admin user creation failed, but continuing..."
}

# Clear and cache configuration
echo ""
echo "Optimizing application..."
$COMPOSE exec -T app php artisan config:clear
$COMPOSE exec -T app php artisan cache:clear

echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "FreePanel is now running at: http://localhost:8080"
echo "Mailpit dashboard at: http://localhost:8025"
echo ""
echo "Useful commands:"
echo "  Start:   $COMPOSE up -d"
echo "  Stop:    $COMPOSE down"
echo "  Logs:    $COMPOSE logs -f"
echo "  Shell:   $COMPOSE exec app bash"
echo ""
