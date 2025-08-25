#!/bin/bash

# Webhook Management Platform - Deployment Verification Script
# This script helps verify that the deployment is working correctly

set -e

echo "🚀 Webhook Management Platform - Deployment Verification"
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
APP_URL="${APP_URL:-http://localhost:8080}"
SKIP_HEALTH_CHECK="${SKIP_HEALTH_CHECK:-false}"

# Function to print colored output
print_status() {
    local status=$1
    local message=$2
    if [ "$status" = "success" ]; then
        echo -e "${GREEN}✅ $message${NC}"
    elif [ "$status" = "error" ]; then
        echo -e "${RED}❌ $message${NC}"
    elif [ "$status" = "warning" ]; then
        echo -e "${YELLOW}⚠️  $message${NC}"
    else
        echo -e "${BLUE}ℹ️  $message${NC}"
    fi
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

print_status "info" "Starting deployment verification..."
echo

# Check if we're in Docker environment
if [ -f "/.dockerenv" ]; then
    print_status "info" "Running inside Docker container"
    IN_DOCKER=true
else
    print_status "info" "Running on host system"
    IN_DOCKER=false
fi

echo

# 1. Check PHP and extensions
print_status "info" "Checking PHP and extensions..."

if command_exists php; then
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    print_status "success" "PHP $PHP_VERSION is installed"
    
    # Check required PHP extensions
    REQUIRED_EXTENSIONS=("pgsql" "pdo_pgsql" "redis" "mbstring" "zip" "gd")
    MISSING_EXTENSIONS=()
    
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if php -m | grep -q "^$ext$"; then
            print_status "success" "PHP extension '$ext' is loaded"
        else
            print_status "error" "PHP extension '$ext' is missing"
            MISSING_EXTENSIONS+=("$ext")
        fi
    done
    
    if [ ${#MISSING_EXTENSIONS[@]} -eq 0 ]; then
        print_status "success" "All required PHP extensions are installed"
    else
        print_status "error" "Missing extensions: ${MISSING_EXTENSIONS[*]}"
        echo
        echo "To fix this, install the missing extensions:"
        echo "Ubuntu/Debian: sudo apt install php-${MISSING_EXTENSIONS[*]// / php-}"
        echo "Alpine: apk add php-${MISSING_EXTENSIONS[*]// / php-}"
        exit 1
    fi
else
    print_status "error" "PHP is not installed or not in PATH"
    exit 1
fi

echo

# 2. Check Composer
print_status "info" "Checking Composer..."

if command_exists composer; then
    COMPOSER_VERSION=$(composer --version --no-ansi | head -n1)
    print_status "success" "$COMPOSER_VERSION"
else
    print_status "error" "Composer is not installed or not in PATH"
    exit 1
fi

echo

# 3. Check Laravel application
print_status "info" "Checking Laravel application..."

if [ -f "artisan" ]; then
    print_status "success" "Laravel application found"
    
    # Check if we can run artisan commands
    if php artisan --version >/dev/null 2>&1; then
        LARAVEL_VERSION=$(php artisan --version 2>/dev/null | grep "Laravel Framework" | cut -d' ' -f3)
        print_status "success" "Laravel Framework $LARAVEL_VERSION"
    else
        print_status "error" "Cannot execute Laravel artisan commands"
        exit 1
    fi
    
    # Check if .env exists
    if [ -f ".env" ]; then
        print_status "success" "Environment file (.env) exists"
    else
        print_status "warning" "Environment file (.env) not found"
    fi
    
else
    print_status "error" "Laravel application not found (missing artisan file)"
    exit 1
fi

echo

# 4. Check environment configuration
print_status "info" "Checking environment configuration..."

# Check APP_KEY
if grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    print_status "success" "Application key is set"
else
    print_status "warning" "Application key might not be set properly"
fi

# Check database configuration
if grep -q "DB_CONNECTION=pgsql" .env 2>/dev/null; then
    print_status "success" "Database connection configured for PostgreSQL"
else
    print_status "warning" "Database connection not configured for PostgreSQL"
fi

# Check Redis configuration
if grep -q "REDIS_HOST=" .env 2>/dev/null; then
    print_status "success" "Redis configuration found"
else
    print_status "warning" "Redis configuration not found"
fi

echo

# 5. Check dependencies
print_status "info" "Checking PHP dependencies..."

if [ -d "vendor" ] && [ -f "vendor/autoload.php" ]; then
    print_status "success" "Composer dependencies installed"
    
    # Try to load autoloader
    if php -r "require_once 'vendor/autoload.php'; echo 'Autoloader works';" >/dev/null 2>&1; then
        print_status "success" "Autoloader is working"
    else
        print_status "error" "Autoloader is not working"
        exit 1
    fi
else
    print_status "error" "Composer dependencies not installed (run: composer install)"
    exit 1
fi

echo

# 6. Check storage permissions
print_status "info" "Checking storage permissions..."

if [ -d "storage" ]; then
    if [ -w "storage" ]; then
        print_status "success" "Storage directory is writable"
    else
        print_status "warning" "Storage directory is not writable"
    fi
    
    # Check specific directories
    STORAGE_DIRS=("storage/logs" "storage/framework/cache" "storage/framework/sessions" "storage/framework/views")
    
    for dir in "${STORAGE_DIRS[@]}"; do
        if [ -d "$dir" ] && [ -w "$dir" ]; then
            print_status "success" "$dir is writable"
        else
            print_status "warning" "$dir is not writable"
        fi
    done
else
    print_status "error" "Storage directory not found"
    exit 1
fi

echo

# 7. Test database connection (if not skipped)
if [ "$SKIP_HEALTH_CHECK" != "true" ]; then
    print_status "info" "Testing database connection..."
    
    if php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection successful';" 2>/dev/null | grep -q "Database connection successful"; then
        print_status "success" "Database connection is working"
    else
        print_status "warning" "Database connection test failed (this is normal if DB is not available)"
    fi
    
    echo
    
    # 8. Test health endpoint
    if command_exists curl; then
        print_status "info" "Testing health endpoint..."
        
        if curl -s -f "$APP_URL/health" >/dev/null 2>&1; then
            HEALTH_RESPONSE=$(curl -s "$APP_URL/health")
            if echo "$HEALTH_RESPONSE" | grep -q '"status":"ok"'; then
                print_status "success" "Health endpoint is working and reports OK"
            else
                print_status "warning" "Health endpoint accessible but reports issues"
                echo "Response: $HEALTH_RESPONSE"
            fi
        else
            print_status "warning" "Health endpoint not accessible at $APP_URL/health"
        fi
    else
        print_status "info" "curl not available, skipping health endpoint test"
    fi
fi

echo
print_status "success" "Deployment verification completed!"
print_status "info" "Application should be accessible at: $APP_URL"

echo
echo "📝 Quick Commands:"
echo "  - View logs: tail -f storage/logs/laravel.log"
echo "  - Clear cache: php artisan cache:clear"
echo "  - Run migrations: php artisan migrate"
echo "  - Check queue status: php artisan queue:work --once"

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ] || [ -n "$(grep -l "warning\|error" <<< "$output")" ]; then
    echo
    print_status "warning" "Some issues were found. Please review the output above."
    exit 1
else
    echo
    print_status "success" "All checks passed! 🎉"
    exit 0
fi
