# Webhook Management Platform

An open-source webhook management platform built with Laravel that allows users to register webhook endpoints, send events, log deliveries, and retry failed requests.

## Features

- **User Authentication**: Basic Laravel authentication with Jetstream
- **Endpoint Management**: Create, update, delete, and manage webhook endpoints
- **Event Management**: Define and manage webhook events
- **Event Delivery**: Asynchronous webhook delivery with queue processing
- **Retry Logic**: Automatic retry with exponential backoff for failed deliveries
- **Dashboard**: Beautiful Inertia.js dashboard for managing webhooks and viewing logs
- **API**: Full REST API for programmatic access
- **Delivery Logs**: Detailed logging of all webhook attempts with filtering

## Technology Stack

- **Backend**: Laravel 12
- **Database**: PostgreSQL
- **Queue**: Redis
- **Frontend**: Inertia.js + Vue.js + Tailwind CSS
- **Authentication**: Laravel Jetstream

## Installation

### Option 1: Docker Setup (Recommended)

#### Prerequisites

- Docker
- Docker Compose

#### Quick Start with Docker

1. **Clone and setup**
```bash
cd webhook-management-platform
./docker/setup.sh
```

2. **Or manual setup**
```bash
# Build and start all services
make build
make setup

# Or using docker-compose directly
docker-compose up -d
docker-compose exec app php artisan migrate --force
```

3. **Access the application**
- Web Interface: http://localhost
- Development: http://localhost:8000 (with override)
- MailHog: http://localhost:8025 (development emails)

#### Docker Services

- **app**: Laravel application (PHP 8.2-FPM)
- **nginx**: Web server (Nginx)
- **db**: PostgreSQL 15 database
- **redis**: Redis cache and queue
- **queue**: Queue worker for webhooks
- **scheduler**: Cron scheduler for Laravel tasks
- **mailhog**: Email testing (development only)

#### Useful Docker Commands

```bash
# View all available commands
make help

# View logs
make logs

# Access application shell
make shell

# Run migrations
make migrate

# Start development environment (with hot reload)
make dev

# Start production environment
make prod

# Stop all services
make down

# Clean everything (remove containers, volumes, images)
make clean
```

### Option 2: Manual Setup

#### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+
- PostgreSQL
- Redis

#### Setup

1. **Install PHP dependencies**
```bash
composer install
```

2. **Install Node dependencies**
```bash
npm install --legacy-peer-deps
```

3. **Environment configuration**
```bash
cp .env.example .env
```

Update the following environment variables:
```env
APP_NAME="Webhook Management Platform"
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=webhook_management
DB_USERNAME=postgres
DB_PASSWORD=your_password

QUEUE_CONNECTION=redis
CACHE_STORE=redis
```

4. **Generate application key**
```bash
php artisan key:generate
```

5. **Create PostgreSQL database**
```bash
createdb webhook_management
```

6. **Run migrations**
```bash
php artisan migrate
```

7. **Build frontend assets**
```bash
npm run build
```

## Usage

### Starting the Application

1. **Start the web server**
```bash
php artisan serve
```

2. **Start the queue worker**
```bash
php artisan queue:work redis --queue=webhooks --tries=5
```

3. **Start the scheduler (optional)**
```bash
php artisan schedule:run
# Or add to crontab: * * * * * cd /path/to/webhook-management-platform && php artisan schedule:run >> /dev/null 2>&1
```

### API Usage

#### Authentication

All API endpoints require authentication using Sanctum. First, create a personal access token from the dashboard or use session-based authentication.

#### Create an Endpoint

```bash
curl -X POST http://localhost:8000/api/endpoints \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "My Webhook Endpoint",
    "url": "https://example.com/webhook",
    "description": "Receives user events",
    "is_active": true
  }'
```

#### Create an Event

```bash
curl -X POST http://localhost:8000/api/events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "user.created",
    "description": "Triggered when a new user is created",
    "endpoint_ids": [1, 2]
  }'
```

#### Trigger a Webhook Event

```bash
curl -X POST http://localhost:8000/api/webhooks/trigger/user.created \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "payload": {
      "user_id": 123,
      "email": "user@example.com",
      "name": "John Doe",
      "timestamp": "2023-12-01T10:00:00Z"
    }
  }'
```

#### View Delivery Logs

```bash
curl -X GET "http://localhost:8000/api/deliveries?status=failed&endpoint_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Retry a Failed Delivery

```bash
curl -X POST http://localhost:8000/api/deliveries/123/retry \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Webhook Security

Webhooks include security headers for verification:

- `X-Webhook-Secret`: HMAC-SHA256 signature of the payload
- `X-Webhook-Event`: The event name that triggered the webhook
- `User-Agent`: Webhook-Management-Platform/1.0

Example verification (Node.js):
```javascript
const crypto = require('crypto');

function verifyWebhook(payload, signature, secret) {
  const expectedSignature = crypto
    .createHmac('sha256', secret)
    .update(JSON.stringify(payload))
    .digest('hex');
  
  return signature === expectedSignature;
}
```

## Database Schema

### Tables

- **users**: User accounts
- **endpoints**: Webhook endpoints
- **events**: Event definitions
- **event_endpoint**: Many-to-many relationship between events and endpoints
- **deliveries**: Webhook delivery attempts and logs

## Queue Configuration

The application uses Redis queues for asynchronous webhook delivery. Configure queue workers using:

### Supervisor Configuration

Create `/etc/supervisor/conf.d/webhook-worker.conf`:

```ini
[program:webhook-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/webhook-management-platform/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --queue=webhooks
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/webhook-worker.log
stopwaitsecs=3600
```

Then restart supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start webhook-worker:*
```

## Commands

- `php artisan webhooks:process-retries` - Process failed webhook deliveries that are ready for retry

## Development

### Running Tests

```bash
php artisan test
```

### Code Style

```bash
./vendor/bin/pint
```

### Frontend Development

```bash
npm run dev
```

## License

This project is open-source software licensed under the [MIT License](LICENSE).
