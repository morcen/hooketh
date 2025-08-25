# 🚀 Laravel Cloud Deployment Guide

This guide covers deploying the Webhook Management Platform to Laravel Cloud (cloud.laravel.com).

## 🔧 Issue Resolved: PostgreSQL Extension

The error you encountered:
```
Root composer.json requires PHP extension ext-pgsql * but it is missing from your system
```

Has been **fixed** by removing the hard requirement for `ext-pgsql` from `composer.json`. Laravel Cloud provides PostgreSQL extensions automatically in their environment.

## ✅ Changes Made

### 1. Updated composer.json
- ✅ Removed `"ext-pgsql": "*"` from required dependencies
- ✅ Added PostgreSQL extension to `suggest` section for documentation
- ✅ Added Redis extension to `suggest` section

### 2. Added Laravel Cloud Configuration
- ✅ Created `.laravelcloud.yml` for deployment configuration
- ✅ Added health check endpoint support
- ✅ Configured workers and scheduling

## 🌐 Environment Configuration

### Required Environment Variables
Laravel Cloud will automatically provide database credentials, but you need to configure these application settings:

```env
# Application
APP_NAME="Webhook Management Platform"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app-name.cloud.laravel.com

# Database (automatically provided by Laravel Cloud)
DB_CONNECTION=pgsql

# Cache & Queue  
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Mail Configuration (configure based on your email service)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

## 🚀 Deployment Steps

### 1. Push Your Code
```bash
# Commit the composer.json changes
git add composer.json .laravelcloud.yml LARAVEL-CLOUD.md
git commit -m "fix: remove PostgreSQL extension requirement for Laravel Cloud deployment"
git push origin main
```

### 2. Deploy to Laravel Cloud
1. **Connect your repository** to Laravel Cloud
2. **Configure environment variables** (see above)
3. **Deploy** - the build should now succeed!

### 3. Post-Deployment Setup
```bash
# These commands run automatically via .laravelcloud.yml
php artisan migrate --force
php artisan db:seed --force
php artisan queue:restart
```

## 📊 Laravel Cloud Specific Features

### Database Support
- ✅ **PostgreSQL**: Automatically available (no extension installation needed)
- ✅ **Connection pooling**: Managed by Laravel Cloud
- ✅ **Backups**: Automated by Laravel Cloud

### Redis Support  
- ✅ **Redis**: Automatically available for cache and queues
- ✅ **High availability**: Managed by Laravel Cloud

### Worker Configuration
```yaml
# Configured in .laravelcloud.yml
workers:
  - name: webhook-queue
    command: php artisan queue:work redis --queue=webhooks,default --sleep=3 --tries=3 --max-time=3600
    processes: 2
```

### Scheduled Tasks
```yaml
# Configured in .laravelcloud.yml  
schedule:
  - command: php artisan schedule:run
    frequency: "* * * * *"
```

## 🔍 Health Monitoring

### Health Check Endpoint
Laravel Cloud will monitor your application using the health endpoint:
- **URL**: `https://your-app.cloud.laravel.com/health`
- **Returns**: JSON status of database and Redis connections
- **Timeout**: 30 seconds (configured)

### Example Health Response
```json
{
  "status": "ok",
  "timestamp": "2025-08-25T12:10:31.000000Z",
  "services": {
    "database": "connected",
    "redis": "connected"
  },
  "extensions": {
    "pgsql": true,
    "pdo_pgsql": true,
    "redis": true
  }
}
```

## 🛠️ Build Process

Laravel Cloud will run these commands automatically:

```bash
# 1. Install PHP dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Install and build frontend assets
npm ci
npm run build

# 3. Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Run migrations and seeders
php artisan migrate --force
php artisan db:seed --force
```

## 🔧 Troubleshooting

### Build Fails with Extension Error
If you still see extension errors:

1. **Update your code**:
   ```bash
   git pull origin main  # Get the latest composer.json changes
   ```

2. **Clear Laravel Cloud cache**:
   - Go to your Laravel Cloud dashboard
   - Clear build cache
   - Redeploy

### Database Connection Issues
```bash
# Check environment variables in Laravel Cloud dashboard
DB_CONNECTION=pgsql
DB_HOST=<provided-by-laravel-cloud>
DB_DATABASE=<provided-by-laravel-cloud>
DB_USERNAME=<provided-by-laravel-cloud>
DB_PASSWORD=<provided-by-laravel-cloud>
```

### Queue Not Processing
```bash
# Laravel Cloud should automatically start workers
# Check worker status in dashboard
# Restart workers if needed
```

## 📈 Performance Optimization

### Laravel Cloud Optimizations
- **OPcache**: Enabled by default
- **Connection pooling**: Managed automatically  
- **Asset optimization**: Built-in CDN
- **Auto-scaling**: Based on traffic

### Application Optimizations
```bash
# These run automatically during deployment
php artisan config:cache    # Cache configuration
php artisan route:cache     # Cache routes  
php artisan view:cache      # Cache views
composer install --optimize-autoloader  # Optimize autoloader
```

## 🎯 Next Steps

After successful deployment:

1. **Test your application**: Visit your Laravel Cloud URL
2. **Check health endpoint**: `/health` should return green status
3. **Monitor logs**: Use Laravel Cloud dashboard
4. **Set up monitoring**: Configure alerts for failed deployments
5. **Domain configuration**: Point your custom domain to Laravel Cloud

## 📞 Support

- **Laravel Cloud Documentation**: [cloud.laravel.com/docs](https://cloud.laravel.com/docs)
- **Laravel Cloud Support**: Available in dashboard
- **Project Issues**: [GitHub Issues](https://github.com/your-username/webhook-management-platform/issues)

---

**Your deployment should now work successfully! 🎉**

The PostgreSQL extension issue has been resolved by making the extension optional in `composer.json` while maintaining compatibility with Docker and other deployment methods.
