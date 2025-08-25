# 🚀 Cloud Deployment Guide

This guide covers deploying the Webhook Management Platform to various cloud providers with proper PostgreSQL extension configuration.

## 🔧 Common Deployment Issues

### PostgreSQL Extension Error
If you encounter:
```
Root composer.json requires PHP extension ext-pgsql * but it is missing from your system
```

This means the PostgreSQL PHP extensions are not installed. Our Docker image handles this automatically, but here are solutions for different deployment scenarios.

## 🐳 Docker-Based Deployments (Recommended)

### Prerequisites
- Docker and Docker Compose support on your cloud provider
- PostgreSQL and Redis services (managed or containerized)

### Build and Deploy
```bash
# Build for production
docker build --target production -t webhook-platform:latest .

# Run with proper environment
docker run -d \
  --name webhook-app \
  -p 80:9000 \
  -e APP_ENV=production \
  -e DB_HOST=your-db-host \
  -e DB_PASSWORD=your-db-password \
  -e REDIS_HOST=your-redis-host \
  webhook-platform:latest
```

## ☁️ Cloud Provider Specific Deployments

### 1. **DigitalOcean App Platform**

Create `app.yaml`:
```yaml
name: webhook-platform
services:
- name: web
  source_dir: /
  dockerfile_path: Dockerfile
  build_command: docker build --target production .
  environment_slug: docker
  instance_count: 1
  instance_size_slug: basic-xxs
  envs:
  - key: APP_ENV
    value: production
  - key: APP_KEY
    value: base64:your-app-key-here
  - key: DB_CONNECTION
    value: pgsql
  - key: DB_HOST
    value: ${db.HOSTNAME}
  - key: DB_DATABASE
    value: ${db.DATABASE}
  - key: DB_USERNAME
    value: ${db.USERNAME}
  - key: DB_PASSWORD
    value: ${db.PASSWORD}

databases:
- name: db
  engine: PG
  version: "13"

- name: redis
  engine: REDIS
  version: "6"
```

### 2. **Railway**

Create `railway.json`:
```json
{
  "$schema": "https://railway.app/railway.schema.json",
  "build": {
    "builder": "dockerfile",
    "dockerfilePath": "Dockerfile",
    "buildCommand": "docker build --target production ."
  },
  "deploy": {
    "startCommand": "supervisord -c /etc/supervisor/conf.d/supervisord.conf",
    "healthcheckPath": "/health",
    "healthcheckTimeout": 100
  }
}
```

### 3. **Heroku** (Using Docker)

Create `heroku.yml`:
```yaml
build:
  docker:
    web: Dockerfile
  config:
    DOCKER_BUILD_TARGET: production
run:
  web: /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
addons:
  - plan: heroku-postgresql:mini
  - plan: heroku-redis:mini
```

Add Heroku-specific Dockerfile:
```dockerfile
# Add this to the end of your Dockerfile
# Heroku stage
FROM production AS heroku

# Heroku expects the app to bind to $PORT
RUN sed -i 's/9000/\$PORT/g' /etc/supervisor/conf.d/supervisord.conf

# Use PORT environment variable
ENV PORT=8080
EXPOSE $PORT

CMD /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
```

### 4. **Google Cloud Run**

Deploy command:
```bash
# Build and push to Google Container Registry
gcloud builds submit --tag gcr.io/PROJECT-ID/webhook-platform

# Deploy to Cloud Run
gcloud run deploy webhook-platform \
  --image gcr.io/PROJECT-ID/webhook-platform \
  --platform managed \
  --region us-central1 \
  --allow-unauthenticated \
  --set-env-vars APP_ENV=production \
  --set-env-vars DB_HOST=YOUR_DB_HOST \
  --set-env-vars REDIS_HOST=YOUR_REDIS_HOST
```

### 5. **Laravel Cloud**

For Laravel's official cloud platform:

**See**: [LARAVEL-CLOUD.md](LARAVEL-CLOUD.md) for complete Laravel Cloud deployment guide.

**Quick Setup:**
1. Push code to your repository
2. Connect repository to Laravel Cloud
3. Configure environment variables
4. Deploy (PostgreSQL extensions automatically available)

### 6. **AWS ECS with Fargate**

Create `task-definition.json`:
```json
{
  "family": "webhook-platform",
  "networkMode": "awsvpc",
  "requiresCompatibilities": ["FARGATE"],
  "cpu": "256",
  "memory": "512",
  "executionRoleArn": "arn:aws:iam::ACCOUNT:role/ecsTaskExecutionRole",
  "containerDefinitions": [
    {
      "name": "webhook-app",
      "image": "your-account.dkr.ecr.region.amazonaws.com/webhook-platform:latest",
      "portMappings": [
        {
          "containerPort": 9000,
          "protocol": "tcp"
        }
      ],
      "environment": [
        {"name": "APP_ENV", "value": "production"},
        {"name": "DB_CONNECTION", "value": "pgsql"}
      ],
      "secrets": [
        {"name": "DB_PASSWORD", "valueFrom": "arn:aws:ssm:region:account:parameter/webhook/db/password"}
      ]
    }
  ]
}
```

## 🔧 Traditional Server Deployment (VPS/Dedicated)

If you need to deploy without Docker:

### 1. Install PHP 8.2+ with Extensions
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-pgsql php8.2-redis php8.2-mbstring php8.2-xml php8.2-zip php8.2-bcmath php8.2-gd

# CentOS/RHEL
sudo yum install -y php82 php82-php-fpm php82-php-pgsql php82-php-redis php82-php-mbstring php82-php-xml php82-php-zip

# Alpine Linux
apk add --no-cache php82 php82-fpm php82-pgsql php82-redis php82-mbstring php82-xml php82-zip
```

### 2. Verify Extensions
```bash
php -m | grep pgsql
php -m | grep pdo_pgsql
php -m | grep redis
```

### 3. Deploy Application
```bash
# Clone repository
git clone https://github.com/your-username/webhook-management-platform.git
cd webhook-management-platform

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set up environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate --force

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## 🌐 Environment Variables

### Required Environment Variables
```env
# Application
APP_NAME="Webhook Management Platform"
APP_ENV=production
APP_KEY=base64:your-generated-key-here
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_PORT=5432
DB_DATABASE=webhook_management
DB_USERNAME=your-db-user
DB_PASSWORD=your-secure-password

# Redis
REDIS_HOST=your-redis-host
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Optional Environment Variables
```env
# Webhook Settings
WEBHOOK_TIMEOUT=30
WEBHOOK_MAX_RETRIES=5
WEBHOOK_RETRY_DELAY=300

# Security
APP_FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=info
```

## 🔒 Security Considerations

### SSL/TLS Configuration
```nginx
# Nginx SSL configuration
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    ssl_certificate /path/to/your/cert.pem;
    ssl_certificate_key /path/to/your/private.key;
    
    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
    
    location / {
        proxy_pass http://127.0.0.1:9000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Database Security
- Use strong passwords
- Enable SSL connections
- Restrict database access by IP
- Regular backups
- Use connection pooling

## 📊 Monitoring and Health Checks

### Health Check Endpoint
Add to `routes/web.php`:
```php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'redis' => Redis::ping() ? 'connected' : 'disconnected',
    ]);
});
```

### Docker Health Check
Add to Dockerfile:
```dockerfile
# Add health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php artisan inspire || exit 1
```

## 🔄 CI/CD Pipeline Example

### GitHub Actions
```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Build Docker image
        run: |
          docker build --target production -t webhook-platform:latest .
      
      - name: Run tests
        run: |
          docker run --rm webhook-platform:latest php artisan test
      
      - name: Deploy to production
        run: |
          # Add your deployment commands here
          echo "Deploy to your cloud provider"
```

## 🆘 Troubleshooting

### Common Issues

1. **Extension Missing Error**
   ```bash
   # Check if extensions are loaded
   php -m | grep -E "(pgsql|pdo_pgsql|redis)"
   
   # Check PHP-FPM configuration
   php-fpm -tt
   ```

2. **Permission Issues**
   ```bash
   # Fix storage permissions
   sudo chown -R www-data:www-data storage bootstrap/cache
   sudo chmod -R 775 storage bootstrap/cache
   ```

3. **Database Connection Issues**
   ```bash
   # Test database connection
   php artisan tinker
   DB::connection()->getPdo();
   ```

4. **Queue Not Processing**
   ```bash
   # Restart queue workers
   php artisan queue:restart
   
   # Check failed jobs
   php artisan queue:failed
   ```

### Performance Optimization

1. **Enable OPcache** in production:
   ```ini
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.max_accelerated_files=4000
   opcache.validate_timestamps=0
   ```

2. **Use Redis for sessions and cache**
3. **Enable gzip compression** in your web server
4. **Use CDN** for static assets
5. **Database connection pooling**

---

## 📞 Support

If you encounter deployment issues:
1. Check the [troubleshooting section](#troubleshooting)
2. Review server logs and error messages
3. Verify all environment variables are set
4. Test the Docker image locally first
5. Open an issue with deployment details

**Happy Deploying! 🚀**
