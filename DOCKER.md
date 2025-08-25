# Docker Setup Guide

This document provides comprehensive instructions for running the Webhook Management Platform using Docker.

## Prerequisites

- Docker 20.10+
- Docker Compose 2.0+

## Quick Start

1. **Clone the repository**
```bash
git clone <repository-url>
cd webhook-management-platform
```

2. **Run the setup script**
```bash
./docker/setup.sh
```

3. **Access the application**
- Web Interface: http://localhost
- Development Server: http://localhost:8000 (development mode)
- MailHog: http://localhost:8025 (email testing)

## Docker Services

### Core Services

- **app**: Laravel application (PHP 8.2-FPM)
- **nginx**: Web server and reverse proxy
- **db**: PostgreSQL 15 database
- **redis**: Redis for caching and queues
- **queue**: Background job processor
- **scheduler**: Laravel task scheduler

### Development Services

- **mailhog**: Email testing interface (development only)

## Environment Configurations

### Development Environment

Uses `docker-compose.yml` + `docker-compose.override.yml`:

- Hot reloading enabled
- Debug mode active
- MailHog for email testing
- Direct access to application server

```bash
# Start development environment
make dev
# or
docker-compose up -d
```

### Production Environment

Uses `docker-compose.yml` + `docker-compose.prod.yml`:

- Optimized for performance
- Resource limits set
- Multiple replicas for scaling
- Security hardened

```bash
# Start production environment
make prod
# or
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

## Makefile Commands

The project includes a comprehensive Makefile for easy management:

### Docker Management
```bash
make help          # Show all available commands
make build         # Build Docker containers
make up            # Start all services
make down          # Stop all services
make restart       # Restart all services
make logs          # View service logs
make shell         # Access application shell
```

### Database Management
```bash
make migrate       # Run database migrations
make fresh         # Fresh database with migrations
make seed          # Seed the database
make backup-db     # Backup database
```

### Application Management
```bash
make optimize      # Cache config, routes, views
make clear         # Clear all caches
make key           # Generate application key
make test          # Run tests
```

### Queue Management
```bash
make queue-work    # Start queue worker manually
make queue-restart # Restart queue workers
make queue-failed  # List failed jobs
make process-retries # Process webhook retries
```

### Maintenance
```bash
make maintenance-on  # Enable maintenance mode
make maintenance-off # Disable maintenance mode
make fix-permissions # Fix storage permissions
make clean          # Remove all containers and images
```

## Manual Docker Commands

If you prefer using Docker Compose directly:

### Basic Operations
```bash
# Build containers
docker-compose build --no-cache

# Start services
docker-compose up -d

# Stop services
docker-compose down

# View logs
docker-compose logs -f

# Access application shell
docker-compose exec app sh
```

### Database Operations
```bash
# Run migrations
docker-compose exec app php artisan migrate

# Access database
docker-compose exec db psql -U webhook_user -d webhook_management

# Backup database
docker-compose exec db pg_dump -U webhook_user webhook_management > backup.sql
```

### Application Operations
```bash
# Clear caches
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear

# Generate application key
docker-compose exec app php artisan key:generate

# Run tests
docker-compose exec app php artisan test
```

## Configuration Files

### Docker Environment
- `docker/.env.docker`: Docker-specific environment variables
- `docker/.env.production`: Production environment variables

### Service Configuration
- `docker/nginx.conf`: Nginx configuration for Laravel
- `docker/supervisord.conf`: Supervisor configuration for PHP-FPM
- `docker/crontab`: Cron jobs for Laravel scheduler

### Docker Files
- `Dockerfile`: Multi-stage build for different environments
- `docker-compose.yml`: Base service definitions
- `docker-compose.override.yml`: Development overrides
- `docker-compose.prod.yml`: Production overrides

## Volumes and Data Persistence

### Named Volumes
- `postgres_data`: PostgreSQL database files
- `redis_data`: Redis persistence files
- `storage_data`: Laravel storage directory

### Bind Mounts (Development)
- `.:/var/www/html`: Application source code
- `./docker/.env.docker:/var/www/html/.env`: Environment file

## Networking

All services communicate through the `webhook-network` bridge network:

- Services can communicate using service names (e.g., `app`, `db`, `redis`)
- External access is provided through Nginx on port 80/443
- Database and Redis are accessible on standard ports for debugging

## Resource Management

### Development Resources
- No resource limits (uses available system resources)
- Single replica per service

### Production Resources
- Memory limits set for each service
- Multiple replicas for app and queue workers
- Resource reservations for guaranteed availability

## Security Considerations

### Network Security
- Services communicate over internal network
- Only Nginx exposes public ports
- Database and Redis are not directly accessible

### File Permissions
- Application runs as `www-data` user
- Storage directories have appropriate permissions
- Sensitive files are excluded via `.dockerignore`

### Environment Variables
- Secrets should be managed through environment files
- Database passwords should be changed in production
- SSL certificates should be mounted for HTTPS

## Troubleshooting

### Common Issues

**Services won't start**
```bash
# Check service status
docker-compose ps

# View service logs
docker-compose logs [service_name]

# Rebuild containers
make build
```

**Database connection errors**
```bash
# Wait for database to be ready
sleep 10

# Test database connection
docker-compose exec app php artisan migrate:status
```

**Permission errors**
```bash
# Fix storage permissions
make fix-permissions
```

**Queue jobs not processing**
```bash
# Check queue worker status
docker-compose logs queue

# Restart queue workers
make queue-restart
```

### Performance Tuning

**For Development**
- Increase resource limits in Docker Desktop
- Use volume mounts for faster file access
- Enable BuildKit for faster builds

**For Production**
- Use multi-stage builds to reduce image size
- Set appropriate resource limits
- Use external Redis/PostgreSQL for scaling
- Enable HTTP/2 and Gzip compression

## Monitoring and Logging

### Viewing Logs
```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f app

# Laravel logs
docker-compose exec app tail -f storage/logs/laravel.log
```

### Health Checks
- Nginx: curl http://localhost/health (if implemented)
- Database: `docker-compose exec db pg_isready`
- Redis: `docker-compose exec redis redis-cli ping`

## Scaling

### Horizontal Scaling
```bash
# Scale queue workers
docker-compose up -d --scale queue=3

# Scale app instances (requires load balancer)
docker-compose up -d --scale app=2
```

### Vertical Scaling
Update resource limits in `docker-compose.prod.yml` and restart services.

## Backup and Recovery

### Database Backup
```bash
# Create backup
make backup-db

# Restore backup
docker-compose exec -T db psql -U webhook_user -d webhook_management < backup.sql
```

### Volume Backup
```bash
# Backup volumes
docker run --rm -v webhook_postgres_data:/data -v $(pwd):/backup alpine tar czf /backup/postgres_backup.tar.gz /data
```

## Development Workflow

1. **Start development environment**
```bash
make dev
```

2. **Make changes to code** (auto-reloaded)

3. **Run tests**
```bash
make test
```

4. **View logs**
```bash
make logs
```

5. **Access application shell if needed**
```bash
make shell
```

## Production Deployment

1. **Prepare environment**
```bash
cp docker/.env.docker docker/.env.production
# Edit production environment variables
```

2. **Build production images**
```bash
docker-compose -f docker-compose.yml -f docker-compose.prod.yml build
```

3. **Start production services**
```bash
make prod
```

4. **Run initial setup**
```bash
docker-compose exec app php artisan migrate --force
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache
```

This completes the Docker setup for the Webhook Management Platform. The containerized setup ensures consistent environments across development and production while simplifying deployment and scaling.
