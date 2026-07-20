# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

Hooketh is a webhook management platform: users create named events and endpoint URLs, subscribe endpoints to events, then trigger deliveries via API. Each delivery is dispatched as a queued job with HMAC-signed payloads, retry logic, and full delivery logging.

## Commands

All commands run inside Docker containers via `make`:

```bash
make setup          # first-time setup (builds images, runs migrations, seeds DB)
make up             # start all services
make down           # stop all services
make test           # run test suite (PHPUnit inside the app container)
make migrate        # run pending migrations
make fresh          # drop and re-migrate database
make seed           # seed the database
make shell          # open a shell in the app container
make logs           # tail all container logs
make clear          # clear config, route, view, and app caches
make queue-work     # start a queue worker manually (webhooks + default queues)
make process-retries # manually trigger retry processing
```

Run a single test file:
```bash
docker-compose exec app php artisan test --filter=AuthenticationTest
```

Format PHP (run from inside the container or with a local Composer install):
```bash
vendor/bin/pint --dirty
```

Frontend dev server with hot reloading (runs outside Docker):
```bash
npm install && npm run dev
```

## Architecture

### Request paths

Two distinct paths exist side by side:

1. **Inertia (web UI)** — `routes/web.php` routes authenticated users to `DashboardController`, which returns `Inertia::render()` responses. Vue pages live in `resources/js/Pages/`. No separate API call is needed for the UI; data is passed directly as Inertia props.

2. **REST API** — `routes/api.php` exposes versioned routes under `/api/v1/` (`endpoints`, `events`, `deliveries`, `webhooks/trigger/{eventName}`). Unversioned routes remain as deprecated aliases. All routes require `auth:sanctum`.

### Webhook delivery pipeline

`POST /api/v1/webhooks/trigger/{eventName}` in `WebhookController::trigger()`:
1. Validates the payload against the event's optional JSON schema (stored as Laravel validation rules in `events.schema`).
2. Creates a `Delivery` record per active endpoint.
3. Dispatches a `SendWebhook` job onto the `webhooks` queue.

`SendWebhook` job (`app/Jobs/SendWebhook.php`):
- Signs each request with `X-Webhook-Secret: sha256=<hmac>`.
- Records response code, body, and `duration_ms` on the `Delivery`.
- On failure, calls `$this->release($delay)` with exponential backoff from `config('webhooks.backoff_delays')`.

The scheduler (`routes/console.php`) runs `webhooks:process-retries` every minute to pick up deliveries whose `next_retry_at` has passed and re-dispatch them.

### Health check

`GET /health` returns JSON with `database`, `redis`, and `queue_worker` status. `queue_worker` is `stale` (503) when the Redis key `queue:heartbeat` is older than 2 minutes. The `QueueHeartbeat` artisan command writes this key every minute via the scheduler.

### Key models

| Model | Notable behavior |
|---|---|
| `Endpoint` | Auto-generates `secret_key` on create; soft-deletable; `secret_key` hidden from serialization |
| `Event` | Optional `schema` (JSON, cast to array) used for payload validation; soft-deletable; composite unique on `(user_id, name)` |
| `Delivery` | Tracks `status`, `response_code`, `response_body`, `duration_ms`, `attempt_count`, `next_retry_at` |

Events and endpoints share a many-to-many `event_endpoint` pivot. `Event::activeEndpoints()` filters to `is_active = true`.

### Stack

- **Backend**: Laravel 12, PHP 8.2+, PostgreSQL
- **Frontend**: Vue 3, Inertia.js, Tailwind CSS, Vite
- **Queue/Cache**: Redis (`webhooks` queue for delivery jobs, `default` for everything else)
- **Auth**: Laravel Jetstream + Sanctum (session auth for the UI, API tokens for external callers)
- **Dev email**: MailHog at http://localhost:8025

### Tests

Tests use SQLite in-memory with `QUEUE_CONNECTION=sync`, so `SendWebhook` jobs run inline. No Redis or PostgreSQL required for the test suite.

### Webhook tuning env vars

```
WEBHOOK_MAX_RETRIES=5
WEBHOOK_BACKOFF_DELAYS=60,300,900,1800,3600   # seconds per attempt
WEBHOOK_RATE_LIMIT=60                          # trigger requests per minute per user
WEBHOOK_PAYLOAD_MAX_SIZE=102400                # max JSON-encoded trigger payload size, in bytes
```
