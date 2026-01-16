# FreePanel Docker Setup

This document describes how to run FreePanel using Docker containers for development and production.

## Prerequisites

- Docker 20.10 or higher
- Docker Compose 2.0 or higher
- 4GB RAM minimum (8GB recommended)
- 20GB disk space

## Quick Start (Development)

### 1. Clone and Setup

```bash
git clone https://github.com/JSXSTEWART/FreePanel.git
cd FreePanel
```

### 2. Run the Setup Script

```bash
chmod +x docker-setup.sh
./docker-setup.sh
```

This script will:
- Create `.env` file from example
- Generate application key
- Build Docker images
- Start all services
- Run database migrations
- Create admin user

### 3. Access the Application

- **Application**: http://localhost:8080
- **Mailpit (Email Testing)**: http://localhost:8025
- **MySQL**: localhost:3306
- **Redis**: localhost:6379

## Manual Setup

If you prefer to set up manually:

### 1. Create Environment File

```bash
cp .env.example .env
```

### 2. Update .env for Docker

```env
# Database Configuration
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=freepanel
DB_USERNAME=freepanel
DB_PASSWORD=secret

# Redis Configuration
REDIS_HOST=redis
REDIS_PORT=6379

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail Configuration (for testing)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
```

### 3. Build and Start Containers

```bash
docker-compose build
docker-compose up -d
```

### 4. Install Dependencies and Setup

```bash
# Generate application key
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate --seed

# Create admin user
docker-compose exec app php artisan freepanel:create-admin

# Optimize application
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache
```

## Docker Services

The development environment includes:

| Service | Container Name | Port | Description |
|---------|---------------|------|-------------|
| App | freepanel-app | 8080 | Laravel application with Nginx & PHP-FPM |
| MySQL | freepanel-mysql | 3306 | Database server |
| Redis | freepanel-redis | 6379 | Cache & queue storage |
| Mailpit | freepanel-mailpit | 8025, 1025 | Email testing tool |
| Queue | freepanel-queue | - | Queue worker process |

## Common Commands

### Container Management

```bash
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# Restart a specific service
docker-compose restart app

# View logs
docker-compose logs -f
docker-compose logs -f app

# Access container shell
docker-compose exec app bash
```

### Laravel Commands

```bash
# Run artisan commands
docker-compose exec app php artisan <command>

# Run migrations
docker-compose exec app php artisan migrate

# Clear cache
docker-compose exec app php artisan cache:clear

# Run tests
docker-compose exec app php artisan test

# Access tinker
docker-compose exec app php artisan tinker
```

### Database Management

```bash
# Access MySQL CLI
docker-compose exec mysql mysql -u freepanel -psecret freepanel

# Backup database
docker-compose exec mysql mysqldump -u freepanel -psecret freepanel > backup.sql

# Restore database
docker-compose exec -T mysql mysql -u freepanel -psecret freepanel < backup.sql
```

### Composer & NPM

```bash
# Install PHP dependencies
docker-compose exec app composer install

# Update PHP dependencies
docker-compose exec app composer update

# Install NPM dependencies (frontend)
docker-compose exec app sh -c "cd frontend && npm install"

# Build frontend
docker-compose exec app sh -c "cd frontend && npm run build"
```

## Production Deployment

### 1. Prepare Environment

Create a `.env.production` file with production settings:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Strong passwords
DB_PASSWORD=strong_database_password
DB_ROOT_PASSWORD=strong_root_password
REDIS_PASSWORD=strong_redis_password
```

### 2. Build Production Image

```bash
docker-compose -f docker-compose.prod.yml build
```

### 3. Start Production Services

```bash
docker-compose -f docker-compose.prod.yml up -d
```

### 4. Run Initial Setup

```bash
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker-compose -f docker-compose.prod.yml exec app php artisan freepanel:create-admin
```

## Development Workflow

### Hot Reload for Frontend

```bash
# In one terminal, start Vite dev server
docker-compose exec app sh -c "cd frontend && npm run dev"

# Access app with hot reload at http://localhost:5173
```

### Debugging with Xdebug

Xdebug is pre-configured in the development image. Configure your IDE:

**VS Code (launch.json)**:
```json
{
    "name": "Listen for Xdebug (Docker)",
    "type": "php",
    "request": "launch",
    "port": 9003,
    "pathMappings": {
        "/var/www/html": "${workspaceFolder}"
    }
}
```

### Running Tests

```bash
# Run all tests
docker-compose exec app php artisan test

# Run specific test
docker-compose exec app php artisan test --filter=UserTest

# With coverage
docker-compose exec app php artisan test --coverage
```

## Troubleshooting

### Permission Issues

```bash
# Fix storage permissions
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
docker-compose exec app chmod -R 775 storage bootstrap/cache
```

### Database Connection Issues

```bash
# Check MySQL is running
docker-compose ps mysql

# View MySQL logs
docker-compose logs mysql

# Verify connection from app container
docker-compose exec app php artisan db:show
```

### Container Doesn't Start

```bash
# View build logs
docker-compose build --no-cache

# Check container logs
docker-compose logs app

# Restart with clean state
docker-compose down -v
docker-compose up -d
```

### Clear All Data and Start Fresh

```bash
# WARNING: This will delete all data
docker-compose down -v
docker volume rm freepanel_mysql-data freepanel_redis-data
./docker-setup.sh
```

## Resource Management

### View Resource Usage

```bash
docker stats
```

### Limit Resources

Edit `docker-compose.yml` to add resource limits:

```yaml
services:
  app:
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 2G
```

## Advanced Configuration

### Custom PHP Settings

Edit `docker/php/php.ini` and rebuild:

```bash
docker-compose build app
docker-compose up -d
```

### Custom Nginx Configuration

Edit `docker/nginx/default.conf` and restart:

```bash
docker-compose restart app
```

### Multiple Environments

```bash
# Development
docker-compose up -d

# Staging
docker-compose -f docker-compose.staging.yml up -d

# Production
docker-compose -f docker-compose.prod.yml up -d
```

## Integration with CI/CD

### GitHub Actions Example

```yaml
name: Docker Build

on: [push]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Build Docker image
        run: docker-compose build
      - name: Run tests
        run: docker-compose run --rm app php artisan test
```

## Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Laravel Sail Documentation](https://laravel.com/docs/11.x/sail)

## Support

For issues related to Docker setup:
- Open an issue on GitHub
- Check existing issues for solutions
- Review logs: `docker-compose logs -f`
