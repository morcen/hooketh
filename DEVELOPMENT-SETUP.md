# 🛠️ Development Setup Options

This document explains the different ways to set up your development environment for the Webhook Management Platform.

## 🎯 Quick Setup Comparison

| Method | Pros | Cons | Best For |
|--------|------|------|----------|
| **Docker + Local Vite** ⭐ | Fast HMR, Easy setup, Real development feel | Need Node.js installed | Most developers |
| **Docker Only** | Everything containerized, Consistent | Slower frontend builds | Docker purists |
| **Local Everything** | Maximum control, Native performance | Complex setup, Many dependencies | Advanced users |

---

## ⭐ Option 1: Docker + Local Vite (Recommended)

This is the **recommended approach** for most developers. Laravel runs in Docker while Vite runs on your local machine for optimal frontend development.

### Setup Steps

1. **Start Docker services:**
   ```bash
   docker-compose up -d
   ```

2. **Install Node.js dependencies:**
   ```bash
   npm install
   ```

3. **Start Vite development server:**
   ```bash
   npm run dev
   ```

### Access Points
- **Application**: http://localhost:8080
- **Hot Module Replacement**: Automatic (via Vite)
- **Email Testing**: http://localhost:8025 (MailHog)

### Development Workflow
```bash
# Terminal 1: Keep Docker running
docker-compose up -d

# Terminal 2: Keep Vite running for frontend development
npm run dev

# Make changes to Vue.js files → Automatically reflected
# Make changes to PHP files → Refresh browser page
```

---

## 🐳 Option 2: Full Development Docker

Use this if you want everything in containers or don't want to install Node.js locally.

### Setup Steps

1. **Start development containers:**
   ```bash
   docker-compose -f docker-compose.yml -f docker-compose.dev.yml up -d
   ```

2. **The setup includes:**
   - Laravel app with development settings
   - MailHog for email testing
   - File synchronization for code changes

### Access Points
- **Application**: http://localhost:8080
- **Email Testing**: http://localhost:8025 (MailHog)

### Development Workflow
```bash
# Start development environment
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# For frontend changes, you'll need to rebuild assets
docker-compose exec app npm run build

# Or run Vite inside the container (slower)
docker-compose exec app npm run dev
```

---

## 💻 Option 3: Local Everything

Run everything locally without Docker. This requires more setup but gives you maximum control.

### Prerequisites
- PHP 8.2+ with extensions: `pgsql`, `pdo_pgsql`, `redis`, `mbstring`, `zip`, `gd`
- PostgreSQL 13+
- Redis 6+
- Node.js 18+
- Composer 2+

### Setup Steps

1. **Install PHP dependencies:**
   ```bash
   composer install
   ```

2. **Install Node.js dependencies:**
   ```bash
   npm install
   ```

3. **Setup environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure your local database in `.env`**

5. **Run migrations:**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Start services:**
   ```bash
   # Terminal 1: PHP development server
   php artisan serve

   # Terminal 2: Vite development server
   npm run dev

   # Terminal 3: Queue worker
   php artisan queue:work

   # Terminal 4: Task scheduler (optional)
   php artisan schedule:work
   ```

---

## 🔧 Development Commands

### Docker-based Development
```bash
# Start/stop services
docker-compose up -d
docker-compose down

# View logs
docker-compose logs -f app
docker-compose logs -f nginx

# Access containers
docker-compose exec app bash
docker-compose exec db psql -U webhook_user -d webhook_management

# Run artisan commands
docker-compose exec app php artisan migrate
docker-compose exec app php artisan tinker

# Run tests
docker-compose exec app php artisan test
```

### Local Development
```bash
# Laravel commands
php artisan migrate
php artisan db:seed
php artisan queue:work
php artisan test

# Frontend commands
npm run dev          # Development server
npm run build        # Production build
npm run preview      # Preview production build
```

---

## 🎨 Frontend Development

### Hot Module Replacement (HMR)
With Vite running locally (`npm run dev`), changes to Vue.js files are immediately reflected in the browser without losing component state.

### Supported File Types
- **Vue Components**: `.vue` files with hot reloading
- **JavaScript/TypeScript**: `.js`, `.ts` files with hot reloading
- **CSS/SCSS**: Styles with hot reloading
- **Assets**: Images, fonts, etc.

### Build Process
```bash
# Development
npm run dev        # Start Vite dev server with HMR

# Production
npm run build      # Build optimized assets
npm run preview    # Preview production build locally
```

---

## 🔍 Troubleshooting

### Common Issues

#### 1. Vite Not Hot Reloading
```bash
# Make sure Vite is running
npm run dev

# Check if port 5173 is available
lsof -i :5173

# Clear Vite cache
rm -rf node_modules/.vite
npm run dev
```

#### 2. Docker Permission Issues
```bash
# Fix storage permissions
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
docker-compose exec app chmod -R 775 storage bootstrap/cache
```

#### 3. Database Connection Issues
```bash
# Check database container
docker-compose ps db

# Test connection
docker-compose exec app php artisan tinker
# In tinker: DB::connection()->getPdo();
```

#### 4. Cache Issues
```bash
# Clear all caches
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
```

### Performance Tips

1. **Use the recommended Docker + Local Vite setup** for best performance
2. **Enable Docker BuildKit** for faster builds:
   ```bash
   export DOCKER_BUILDKIT=1
   ```
3. **Use volumes for node_modules** to avoid syncing issues
4. **Keep Vite running** for instant frontend updates

---

## 📚 Additional Resources

- [Docker Documentation](DOCKER.md) - Detailed Docker setup and configuration
- [Development Guide](DEVELOPMENT.md) - Comprehensive development workflows
- [Contributing Guide](CONTRIBUTING.md) - How to contribute to the project

---

## ❓ Need Help?

- Check the troubleshooting section above
- Review [DEVELOPMENT.md](DEVELOPMENT.md) for detailed workflows
- Open an issue if you encounter persistent problems
- Join our community discussions for support

**Happy Developing! 🚀**
