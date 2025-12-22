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

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "Error: Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Check if .env file exists
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
    
    # Generate APP_KEY
    echo "Generating application key..."
    docker-compose run --rm app php artisan key:generate --ansi
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
chmod -R 775 storage bootstrap/cache

# Build Docker images
echo ""
echo "Building Docker images (this may take a few minutes)..."
docker-compose build

# Start services
echo ""
echo "Starting Docker containers..."
docker-compose up -d

# Wait for database to be ready
echo ""
echo "Waiting for database to be ready..."
sleep 10

# Run migrations
echo ""
echo "Running database migrations..."
docker-compose exec -T app php artisan migrate --force

# Seed database (optional)
read -p "Do you want to seed the database? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    docker-compose exec -T app php artisan db:seed
fi

# Create admin user
echo ""
echo "Creating admin user..."
docker-compose exec app php artisan freepanel:create-admin

# Clear and cache configuration
echo ""
echo "Optimizing application..."
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache
docker-compose exec -T app php artisan view:cache

echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "FreePanel is now running at: http://localhost:8080"
echo "Mailpit dashboard at: http://localhost:8025"
echo ""
echo "Useful commands:"
echo "  Start:   docker-compose up -d"
echo "  Stop:    docker-compose down"
echo "  Logs:    docker-compose logs -f"
echo "  Shell:   docker-compose exec app bash"
echo ""
