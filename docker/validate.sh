#!/bin/bash

# Webhook Management Platform - Docker Validation Script

set -e

echo "🔍 Validating Docker setup for Webhook Management Platform..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✅ $2${NC}"
    else
        echo -e "${RED}❌ $2${NC}"
        exit 1
    fi
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Check if Docker is installed and running
echo "📋 Checking prerequisites..."

docker --version > /dev/null 2>&1
print_status $? "Docker is installed"

docker info > /dev/null 2>&1
print_status $? "Docker daemon is running"

docker-compose --version > /dev/null 2>&1
print_status $? "Docker Compose is installed"

# Check if required files exist
echo ""
echo "📁 Checking required files..."

[ -f "Dockerfile" ]
print_status $? "Dockerfile exists"

[ -f "docker-compose.yml" ]
print_status $? "docker-compose.yml exists"

[ -f "docker/.env.docker" ]
print_status $? "Docker environment file exists"

[ -f "docker/nginx.conf" ]
print_status $? "Nginx configuration exists"

[ -f "docker/setup.sh" ]
print_status $? "Setup script exists"

[ -f "Makefile" ]
print_status $? "Makefile exists"

# Check if services are running
echo ""
echo "🚀 Checking Docker services..."

if docker-compose ps | grep -q "Up"; then
    echo -e "${GREEN}✅ Some Docker services are running${NC}"
    
    # Test individual services
    docker-compose ps app | grep -q "Up"
    print_status $? "App service is running"
    
    docker-compose ps db | grep -q "Up"
    print_status $? "Database service is running"
    
    docker-compose ps redis | grep -q "Up"
    print_status $? "Redis service is running"
    
    docker-compose ps nginx | grep -q "Up"
    print_status $? "Nginx service is running"
    
    # Test application connectivity
    echo ""
    echo "🔗 Testing application connectivity..."
    
    # Wait a moment for services to be fully ready
    sleep 5
    
    # Test database connection
    docker-compose exec -T db pg_isready -U webhook_user > /dev/null 2>&1
    print_status $? "Database is accepting connections"
    
    # Test Redis connection
    docker-compose exec -T redis redis-cli ping | grep -q "PONG" > /dev/null 2>&1
    print_status $? "Redis is responding"
    
    # Test web server
    curl -f -s http://localhost > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Web server is responding${NC}"
    else
        print_warning "Web server may not be fully ready yet"
    fi
    
    # Test application status
    echo ""
    echo "🎯 Testing Laravel application..."
    
    docker-compose exec -T app php artisan --version > /dev/null 2>&1
    print_status $? "Laravel Artisan is working"
    
    # Check if migrations have been run
    if docker-compose exec -T app php artisan migrate:status 2>/dev/null | grep -q "Migration table not found"; then
        print_warning "Database migrations haven't been run yet. Run 'make migrate' to set up the database."
    else
        echo -e "${GREEN}✅ Database migrations are set up${NC}"
    fi
    
    # Check application key
    docker-compose exec -T app php artisan env:decrypt --key=dummy 2>&1 | grep -q "APP_KEY" > /dev/null
    if [ $? -ne 0 ]; then
        ENV_CHECK=$(docker-compose exec -T app cat .env | grep APP_KEY || echo "")
        if [[ $ENV_CHECK == *"base64:"* ]] && [[ $ENV_CHECK != *"GENERATE_NEW_KEY_HERE"* ]]; then
            echo -e "${GREEN}✅ Application key is set${NC}"
        else
            print_warning "Application key needs to be generated. Run 'make key' to generate one."
        fi
    fi
    
else
    echo -e "${YELLOW}⚠️  Docker services are not currently running${NC}"
    echo "Run 'make up' or 'docker-compose up -d' to start services"
fi

# Check port availability
echo ""
echo "🔌 Checking port availability..."

ports=(80 5432 6379)
for port in "${ports[@]}"; do
    if lsof -i :$port > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Port $port is in use (likely by Docker services)${NC}"
    else
        print_warning "Port $port is not in use. Services may not be running."
    fi
done

# Check disk space
echo ""
echo "💾 Checking system resources..."

DISK_USAGE=$(df -h . | awk 'NR==2{print $5}' | sed 's/%//')
if [ $DISK_USAGE -lt 90 ]; then
    echo -e "${GREEN}✅ Sufficient disk space available${NC}"
else
    print_warning "Disk space is running low ($DISK_USAGE% used)"
fi

# Check if Docker images exist
echo ""
echo "🐳 Checking Docker images..."

if docker images | grep -q webhook-management-platform; then
    echo -e "${GREEN}✅ Application Docker images are built${NC}"
else
    print_warning "Application Docker images not found. Run 'make build' to build them."
fi

# Final summary
echo ""
echo "📊 Validation Summary:"
echo "===================="

if docker-compose ps | grep -q "Up"; then
    RUNNING_SERVICES=$(docker-compose ps | grep "Up" | wc -l)
    echo -e "${GREEN}✅ $RUNNING_SERVICES Docker services are running${NC}"
else
    echo -e "${YELLOW}⚠️  No Docker services are currently running${NC}"
fi

echo ""
echo "🚀 Next steps:"
if ! docker-compose ps | grep -q "Up"; then
    echo "1. Start services: make up"
    echo "2. Run setup: make setup"
elif docker-compose exec -T app php artisan migrate:status 2>/dev/null | grep -q "Migration table not found"; then
    echo "1. Run database migrations: make migrate"
    echo "2. Generate application key: make key"
else
    echo "1. Access the application: http://localhost"
    echo "2. View logs: make logs"
    echo "3. Access shell: make shell"
fi

echo ""
echo "🎉 Docker validation completed!"
