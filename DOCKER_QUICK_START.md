# FreePanel Docker Quick Reference

## Quick Start

```bash
# Initial setup (only once)
./docker-setup.sh

# Start services
docker compose up -d

# Stop services
docker compose down
```

## Access URLs

- **Application**: http://localhost:8080
- **Mailpit UI**: http://localhost:8025 (email testing)
- **MySQL**: localhost:3306
- **Redis**: localhost:6379

## Common Commands

### Service Management
```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# Restart specific service
docker compose restart app

# View logs
docker compose logs -f app

# View all logs
docker compose logs -f
```

### Application Commands
```bash
# Access container shell
docker compose exec app bash

# Run artisan commands
docker compose exec app php artisan <command>

# Run migrations
docker compose exec app php artisan migrate

# Clear cache
docker compose exec app php artisan cache:clear

# Run tests
docker compose exec app php artisan test
```

### Database Commands
```bash
# Access MySQL shell
docker compose exec mysql mysql -u freepanel -psecret freepanel

# Backup database
docker compose exec mysql mysqldump -u freepanel -psecret freepanel > backup.sql

# Restore database
cat backup.sql | docker compose exec -T mysql mysql -u freepanel -psecret freepanel
```

### Development Commands
```bash
# Install composer dependencies
docker compose exec app composer install

# Install frontend dependencies
docker compose exec app sh -c "cd frontend && npm install"

# Build frontend
docker compose exec app sh -c "cd frontend && npm run build"

# Run frontend dev server (with hot reload)
docker compose exec app sh -c "cd frontend && npm run dev"
```

## Troubleshooting

### Containers won't start
```bash
# Check container status
docker compose ps

# View logs
docker compose logs app

# Rebuild from scratch
docker compose down -v
docker compose build --no-cache
docker compose up -d
```

### Permission errors
```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app chmod -R 775 storage bootstrap/cache
```

### Database connection errors
```bash
# Verify MySQL is running
docker compose ps mysql

# Check database connection
docker compose exec app php artisan db:show
```

### Clear everything and start fresh
```bash
# WARNING: This deletes all data!
docker compose down -v
docker volume prune -f
./docker-setup.sh
```

## Environment Variables

Key variables in `.env`:
- `APP_PORT` - Application port (default: 8080)
- `FORWARD_DB_PORT` - MySQL port (default: 3306)
- `FORWARD_REDIS_PORT` - Redis port (default: 6379)
- `FORWARD_MAILPIT_PORT` - Mailpit SMTP port (default: 1025)
- `FORWARD_MAILPIT_DASHBOARD_PORT` - Mailpit UI port (default: 8025)

## Production Deployment

See [DOCKER.md](DOCKER.md#production-deployment) for detailed production setup instructions.

```bash
# Build production image
docker compose -f docker-compose.prod.yml build

# Start production services
docker compose -f docker-compose.prod.yml up -d
```
