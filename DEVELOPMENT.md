# 🛠️ Development Guide

This guide covers everything you need to know for developing the Webhook Management Platform.

## 📋 Table of Contents

- [Prerequisites](#prerequisites)
- [Local Development Setup](#local-development-setup)
- [Development Workflow](#development-workflow)
- [Code Structure](#code-structure)
- [Testing](#testing)
- [Database](#database)
- [Frontend Development](#frontend-development)
- [API Development](#api-development)
- [Debugging](#debugging)
- [Performance](#performance)

## 🔧 Prerequisites

### Required Software
- **Docker Desktop** (latest version)
- **Node.js** 18+ and npm
- **Git**
- **Make** (usually pre-installed on macOS/Linux)

### Optional (for advanced development)
- **PHP** 8.2+ (for running artisan commands locally)
- **Composer** 2+ (for dependency management)
- **PostgreSQL** client tools

## 🚀 Local Development Setup

### Option 1: Docker + Local Node.js (Recommended)

This setup runs Laravel in Docker while running the Vite dev server locally for optimal frontend development experience.

```bash
# 1. Clone and enter the project
git clone https://github.com/morcen/hooketh.git
cd webhook-management-platform

# 2. Start Docker services
make setup

# 3. Install Node.js dependencies
npm install

# 4. Start Vite development server
npm run dev
```

**Access Points:**
- **Application**: http://localhost:8080
- **Hot Module Replacement**: http://localhost:5173
- **MailHog (Email Testing)**: http://localhost:8025

### Option 2: Full Docker Setup

```bash
# Use the development Docker compose
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

## 🔄 Development Workflow

### Daily Development
1. **Start services** (if not already running):
   ```bash
   docker-compose up -d
   npm run dev  # In a separate terminal
   ```

2. **Make your changes** to PHP, Vue, or other files

3. **Changes are automatically reflected**:
   - Vue.js files → Hot reloaded via Vite
   - PHP files → Require page refresh
   - Blade templates → Require page refresh

4. **Run tests** before committing:
   ```bash
   make test
   ```

### Common Commands

```bash
# Container management
make up               # Start all containers
make down             # Stop all containers
make restart          # Restart all containers
make logs             # View logs from all services

# Application management
make shell            # Access application shell
make migrate          # Run database migrations
make seed             # Seed database with test data
make fresh            # Fresh database + migrations
make optimize         # Optimize application caches

# Development utilities
make test             # Run tests
make queue-work       # Start queue worker manually
make clear            # Clear all caches
make key              # Generate application key
```

## 📁 Code Structure

### Backend (Laravel)
```
app/
├── Http/
│   ├── Controllers/     # HTTP controllers
│   ├── Middleware/      # HTTP middleware
│   └── Requests/        # Form request validation
├── Models/              # Eloquent models
├── Jobs/                # Queue jobs
├── Services/            # Business logic services
├── Events/              # Event classes
└── Listeners/           # Event listeners

database/
├── factories/           # Model factories
├── migrations/          # Database migrations
└── seeders/            # Database seeders

routes/
├── web.php             # Web routes
├── api.php             # API routes
└── console.php         # Artisan commands
```

### Frontend (Vue.js)
```
resources/
├── js/
│   ├── components/     # Vue components
│   ├── pages/          # Inertia.js pages
│   ├── layouts/        # Page layouts
│   └── app.js          # Application entry point
├── css/                # Tailwind CSS styles
└── views/              # Blade templates
```

### Key Models
- **User** - Platform users with authentication
- **Endpoint** - Webhook endpoints with configurations
- **Event** - Webhook events with payloads
- **Delivery** - Webhook delivery attempts and responses

## 🧪 Testing

### Running Tests
```bash
# Run all tests
make test

# Run specific test types
docker-compose exec app php artisan test --filter=Unit
docker-compose exec app php artisan test --filter=Feature

# Run with coverage
docker-compose exec app php artisan test --coverage
```

### Writing Tests
- **Unit Tests**: `tests/Unit/` - Test individual classes/methods
- **Feature Tests**: `tests/Feature/` - Test complete features
- **Browser Tests**: Using Laravel Dusk for E2E testing

Example test structure:
```php
// tests/Feature/WebhookDeliveryTest.php
class WebhookDeliveryTest extends TestCase
{
    public function test_webhook_delivery_success()
    {
        $endpoint = Endpoint::factory()->create();
        $event = Event::factory()->create();
        
        // Test webhook delivery logic
        $this->assertWebhookDelivered($endpoint, $event);
    }
}
```

## 💾 Database

### Migrations
```bash
# Create migration
docker-compose exec app php artisan make:migration create_example_table

# Run migrations
make migrate

# Rollback migrations
docker-compose exec app php artisan migrate:rollback

# Fresh migration (⚠️ Destructive)
make fresh
```

### Seeders
```bash
# Create seeder
docker-compose exec app php artisan make:seeder ExampleSeeder

# Run seeders
make seed

# Run specific seeder
docker-compose exec app php artisan db:seed --class=ExampleSeeder
```

### Database Access
```bash
# Access PostgreSQL directly
docker-compose exec db psql -U webhook_user -d webhook_management

# Or via Laravel Tinker
docker-compose exec app php artisan tinker
```

## 🎨 Frontend Development

### Vue.js Development
The frontend uses **Vue.js 3** with **Inertia.js** for SPA-like experience.

#### Component Structure
```vue
<!-- resources/js/components/Example.vue -->
<template>
  <div class="example-component">
    <h1>{{ title }}</h1>
    <button @click="handleClick">Click me</button>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  title: String
})

const handleClick = () => {
  // Handle click logic
}
</script>

<style scoped>
.example-component {
  @apply bg-white p-4 rounded-lg shadow;
}
</style>
```

#### Key Technologies
- **Vue 3** - Composition API
- **Inertia.js** - SPA without API
- **Tailwind CSS** - Utility-first CSS
- **Vite** - Build tool with HMR

### Styling with Tailwind CSS
The project uses Tailwind CSS for styling. Key files:
- `tailwind.config.js` - Tailwind configuration
- `resources/css/app.css` - Custom styles and Tailwind imports

### Hot Module Replacement
When `npm run dev` is running, Vue components update instantly without losing state.

## 🔌 API Development

### REST API Endpoints
The platform provides RESTful APIs for external integration:

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('endpoints', EndpointController::class);
    Route::apiResource('events', EventController::class);
    Route::apiResource('deliveries', DeliveryController::class);
});
```

### API Documentation
- Access Swagger/OpenAPI docs at: http://localhost:8080/api/docs
- API routes are defined in `routes/api.php`

### Creating API Controllers
```bash
# Create API controller
docker-compose exec app php artisan make:controller Api/ExampleController --api
```

## 🐛 Debugging

### Logs
```bash
# View application logs
make logs

# View specific container logs
docker-compose logs app
docker-compose logs nginx
docker-compose logs queue
```

### Debug Tools
- **Laravel Telescope** - Database queries, jobs, requests
- **Laravel Debugbar** - Request debugging
- **Vue DevTools** - Vue.js component debugging

### Common Issues

#### Vite Not Hot Reloading
1. Ensure `npm run dev` is running
2. Check browser developer tools for connection errors
3. Verify port 5173 is not blocked by firewall

#### Database Connection Issues
```bash
# Check database is running
docker-compose ps db

# Test connection
docker-compose exec app php artisan tinker
# Then: DB::connection()->getPdo()
```

#### Permission Issues
```bash
# Fix storage permissions
make fix-permissions
```

## ⚡ Performance

### Optimization Commands
```bash
# Optimize application
make optimize

# Clear all caches
make clear

# Queue monitoring
make queue-work
```

### Database Optimization
- Use database indexes appropriately
- Optimize N+1 queries with eager loading
- Use database transactions for multiple operations

### Frontend Optimization
- Use Vite's tree shaking for smaller bundles
- Lazy load components when appropriate
- Optimize images and assets

---

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Vue.js Guide](https://vuejs.org/guide/)
- [Inertia.js Documentation](https://inertiajs.com/)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)

## 🆘 Getting Help

- Check existing [GitHub Issues](https://github.com/morcen/hooketh/issues)
- Join our [Discussions](https://github.com/morcen/hooketh/discussions)
- Review this documentation thoroughly
- Check container logs for specific error messages
