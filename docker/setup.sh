#!/bin/bash

# Webhook Management Platform - Docker Setup Script

set -e

echo "🚀 Setting up Webhook Management Platform with Docker..."

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Generate application key if not exists
if [ ! -f "docker/.env.docker" ]; then
    echo "❌ Docker environment file not found. Please ensure docker/.env.docker exists."
    exit 1
fi

# Generate a new application key
APP_KEY=$(openssl rand -base64 32)
sed -i.bak "s/GENERATE_NEW_KEY_HERE/${APP_KEY}/" docker/.env.docker
rm docker/.env.docker.bak

echo "✅ Generated new application key"

# Build and start containers
echo "🔨 Building Docker containers..."
docker-compose build --no-cache

echo "🚀 Starting containers..."
docker-compose up -d

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
sleep 10

# Run migrations
echo "📦 Running database migrations..."
docker-compose exec app php artisan migrate --force

# Generate application key in container
echo "🔑 Setting up application key in container..."
docker-compose exec app php artisan key:generate --force

# Clear and optimize caches
echo "🧹 Optimizing application..."
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache

# Set proper permissions
echo "🔒 Setting proper permissions..."
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chown -R www-data:www-data /var/www/html/bootstrap/cache
docker-compose exec app chmod -R 775 /var/www/html/storage
docker-compose exec app chmod -R 775 /var/www/html/bootstrap/cache

echo ""
echo "🎉 Setup completed successfully!"
echo ""
echo "📍 Your application is now running at:"
echo "   Web Interface: http://localhost"
echo "   Development Server: http://localhost:8000 (if using override)"
echo "   MailHog (dev): http://localhost:8025"
echo ""
echo "📊 Database Connection:"
echo "   Host: localhost"
echo "   Port: 5432"
echo "   Database: webhook_management"
echo "   Username: webhook_user"
echo "   Password: webhook_password"
echo ""
echo "📝 Useful commands:"
echo "   View logs: docker-compose logs -f"
echo "   Stop services: docker-compose down"
echo "   Restart services: docker-compose restart"
echo "   Access app shell: docker-compose exec app sh"
echo ""
