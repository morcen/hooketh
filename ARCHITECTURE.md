# 🏗️ Architecture Overview

This document provides a comprehensive overview of the Webhook Management Platform's architecture, design decisions, and system components.

## 🎯 System Overview

The Webhook Management Platform is designed as a scalable, enterprise-grade solution for managing webhook endpoints, processing events, and ensuring reliable delivery with comprehensive monitoring and analytics.

## 🏛️ High-Level Architecture

```mermaid
graph TB
    subgraph "Client Layer"
        A[Web Browser] --> B[Vue.js SPA]
        C[External APIs] --> D[REST API]
    end
    
    subgraph "Application Layer"
        B --> E[Inertia.js]
        D --> F[Laravel Controllers]
        E --> F
        F --> G[Service Layer]
    end
    
    subgraph "Business Logic"
        G --> H[Models & Eloquent ORM]
        G --> I[Queue Jobs]
        G --> J[Event System]
    end
    
    subgraph "Infrastructure Layer"
        H --> K[PostgreSQL]
        I --> L[Redis Queue]
        M[File Storage] --> N[Local/S3]
        O[Cache] --> L
    end
    
    subgraph "External Services"
        I --> P[Webhook Endpoints]
        Q[Email Service] --> R[MailHog/SMTP]
    end
```

## 🔧 Core Components

### 1. Web Application Layer

#### Frontend (Vue.js + Inertia.js)
- **Vue.js 3**: Modern reactive framework with Composition API
- **Inertia.js**: SPA experience without API complexity
- **Tailwind CSS**: Utility-first CSS framework
- **Vite**: Fast build tool with hot module replacement

#### Backend (Laravel)
- **Laravel 12**: PHP framework with robust ecosystem
- **PHP 8.2+**: Modern PHP with enhanced performance
- **MVC Architecture**: Clear separation of concerns
- **Middleware**: Request filtering and authentication

### 2. Data Layer

#### Primary Database (PostgreSQL)
```sql
-- Core Tables
- users          (user accounts and authentication)
- endpoints      (webhook endpoint configurations, soft-deletable)
- events         (webhook events, payloads, optional schema, soft-deletable)
- event_endpoint (many-to-many relationship)
- deliveries     (delivery attempts, responses, duration_ms)
- jobs           (queue job management)
```

#### Cache & Session Store (Redis)
- **Session Management**: User session data
- **Cache Layer**: Application-level caching
- **Queue Backend**: Background job processing
- **Scheduler Heartbeat**: `queue:heartbeat` key written every minute to verify the scheduler container is alive

### 3. Background Processing

#### Queue System
```php
// Job Types
- SendWebhook: Handle webhook HTTP POST with HMAC signing, timing, and retry logic
- ProcessWebhookRetries (command): Pick up failed deliveries ready for retry
- QueueHeartbeat (command): Write alive timestamp to Redis every minute
```

#### Scheduler
- **Laravel Scheduler**: Cron-like job scheduling via `routes/console.php`
- **Retry Logic**: `webhooks:process-retries` runs every minute — exponential backoff delays configured via `WEBHOOK_BACKOFF_DELAYS`
- **Health Heartbeat**: `queue:heartbeat` runs every minute; the `/health` endpoint reports `stale` if no heartbeat within 2 minutes

## 📊 Data Models & Relationships

### Entity Relationship Diagram

```mermaid
erDiagram
    USER ||--o{ ENDPOINT : owns
    USER ||--o{ EVENT : creates
    ENDPOINT ||--o{ DELIVERY : receives
    EVENT ||--o{ DELIVERY : triggers
    EVENT }o--o{ ENDPOINT : subscribes_to
    
    USER {
        bigint id PK
        string name
        string email UK
        timestamp email_verified_at
        string password
        timestamp created_at
        timestamp updated_at
    }
    
    ENDPOINT {
        bigint id PK
        bigint user_id FK
        string name
        string url
        string secret_key
        text description
        boolean is_active
        timestamp deleted_at
        timestamp created_at
        timestamp updated_at
    }
    
    EVENT {
        bigint id PK
        bigint user_id FK
        string name
        string event_type
        json payload
        json schema
        text description
        timestamp deleted_at
        timestamp created_at
        timestamp updated_at
    }
    
    DELIVERY {
        bigint id PK
        bigint event_id FK
        bigint endpoint_id FK
        json payload
        string status
        integer response_code
        text response_body
        integer duration_ms
        integer attempt_count
        timestamp delivered_at
        timestamp next_retry_at
        timestamp created_at
        timestamp updated_at
    }
```

### Key Relationships

1. **User → Endpoints**: One-to-Many (User owns multiple endpoints)
2. **User → Events**: One-to-Many (User creates multiple events)
3. **Event → Endpoints**: Many-to-Many (Events can be delivered to multiple endpoints)
4. **Event → Deliveries**: One-to-Many (Each event can have multiple delivery attempts)
5. **Endpoint → Deliveries**: One-to-Many (Each endpoint receives multiple deliveries)

## 🔄 Request Flow

### 1. Web Request Flow
```mermaid
sequenceDiagram
    participant Browser
    participant Nginx
    participant Laravel
    participant Database
    participant Redis
    
    Browser->>Nginx: HTTP Request
    Nginx->>Laravel: Forward Request
    Laravel->>Database: Query Data
    Database-->>Laravel: Return Results
    Laravel->>Redis: Cache Check/Store
    Redis-->>Laravel: Cache Data
    Laravel-->>Nginx: Inertia.js Response
    Nginx-->>Browser: JSON/HTML Response
```

### 2. Webhook Delivery Flow
```mermaid
sequenceDiagram
    participant Admin
    participant Laravel
    participant Queue
    participant Worker
    participant Endpoint
    participant Database
    
    Admin->>Laravel: Create Event
    Laravel->>Database: Store Event
    Laravel->>Queue: Dispatch WebhookJob
    Queue->>Worker: Process Job
    Worker->>Endpoint: HTTP POST Request
    Endpoint-->>Worker: HTTP Response
    Worker->>Database: Store Delivery Result
    alt Delivery Failed
        Worker->>Queue: Schedule Retry Job
    end
```

## 🔐 Security Architecture

### Authentication & Authorization
- **Laravel Sanctum**: API token authentication
- **Laravel Jetstream**: User authentication scaffolding
- **Team-based Access**: Multi-tenant architecture support
- **CSRF Protection**: Cross-site request forgery prevention

### Webhook Security
```php
// HMAC Signature Verification
$signature = hash_hmac('sha256', $payload, $endpoint->secret_key);
$expectedSignature = 'sha256=' . $signature;

if (!hash_equals($expectedSignature, $receivedSignature)) {
    throw new SecurityException('Invalid signature');
}
```

### Data Protection
- **Encrypted Environment**: Sensitive configuration encrypted
- **Database Encryption**: Sensitive fields encrypted at rest
- **HTTPS Enforcement**: All communications encrypted in transit
- **Input Validation**: Comprehensive request validation

## ⚡ Performance & Scalability

### Performance Optimizations

1. **Database Optimization**
   - Proper indexing on frequently queried columns
   - Query optimization with Eloquent relationships
   - Connection pooling and read replicas

2. **Caching Strategy**
   - Redis-based application caching
   - Query result caching
   - Session storage in Redis

3. **Queue Processing**
   - Background job processing
   - Queue workers for high-throughput processing
   - Job batching for bulk operations

### Scalability Considerations

1. **Horizontal Scaling**
   - Stateless application design
   - Load balancer ready
   - Container orchestration support (Docker)

2. **Database Scaling**
   - Read replica support
   - Connection pooling
   - Query optimization

3. **Queue Scaling**
   - Multiple queue workers
   - Queue prioritization
   - Failed job handling

## 🐳 Docker Architecture

### Container Strategy
```yaml
# Multi-service architecture
services:
  app:           # Laravel application (PHP-FPM)
  nginx:         # Web server and reverse proxy
  db:            # PostgreSQL database
  redis:         # Cache and queue backend
  queue:         # Background queue worker
  scheduler:     # Laravel task scheduler
  mailhog:       # Development email testing
```

### Development vs Production
- **Development**: Debug mode, file watching, verbose logging
- **Production**: Optimized builds, minimal logging, security hardening

## 📈 Monitoring & Observability

### Application Monitoring
- **Laravel Telescope**: Development debugging and profiling
- **Log Aggregation**: Structured logging with context
- **Performance Metrics**: Response times and throughput tracking

### Health Checks
```php
// Single unified health check endpoint
GET /health
// Returns:
// {
//   "status": "ok"|"error",
//   "services": {
//     "database": "connected"|"disconnected",
//     "redis": "connected"|"disconnected",
//     "queue_worker": "ok"|"stale"|"unknown"
//   }
// }
// HTTP 200 if healthy, 503 if any service is degraded
```

`queue_worker` is `stale` (and the response is 503) if the scheduler heartbeat in Redis is older than 2 minutes, indicating the scheduler container is down.

### Error Handling
- **Graceful Degradation**: Fallback mechanisms for service failures
- **Circuit Breaker**: Prevent cascading failures
- **Retry Logic**: Intelligent retry strategies with backoff

## 🔧 Configuration Management

### Environment-based Configuration
```env
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://webhook-platform.com

# Database
DB_CONNECTION=pgsql
DB_HOST=db
DB_DATABASE=webhook_management

# Queue & Cache
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
REDIS_HOST=redis

# Webhook delivery tuning
WEBHOOK_MAX_RETRIES=5
WEBHOOK_BACKOFF_DELAYS=60,300,900,1800,3600
WEBHOOK_RATE_LIMIT=60
```

### Feature Flags
- **Environment-based**: Different features per environment
- **Database-driven**: Runtime feature toggling capability
- **User-based**: Per-user or per-team feature access

## 🚀 Deployment Architecture

### Production Deployment
```mermaid
graph TB
    subgraph "Load Balancer"
        A[Nginx/HAProxy]
    end
    
    subgraph "Application Tier"
        A --> B[App Server 1]
        A --> C[App Server 2]
        A --> D[App Server N]
    end
    
    subgraph "Queue Workers"
        E[Queue Worker 1]
        F[Queue Worker 2]
        G[Queue Worker N]
    end
    
    subgraph "Data Tier"
        H[PostgreSQL Primary]
        I[PostgreSQL Replica]
        J[Redis Cluster]
    end
    
    B --> H
    C --> H
    D --> H
    B --> J
    C --> J
    D --> J
    E --> H
    E --> J
```

### CI/CD Pipeline
1. **Code Commit**: Developer pushes changes
2. **Automated Testing**: Unit and feature tests
3. **Build Process**: Docker image creation
4. **Security Scanning**: Vulnerability assessment
5. **Deployment**: Blue-green deployment strategy
6. **Health Checks**: Post-deployment verification

## 📋 Design Patterns

### Backend Patterns
- **Repository Pattern**: Data access abstraction
- **Service Layer Pattern**: Business logic encapsulation
- **Observer Pattern**: Event-driven architecture
- **Queue Pattern**: Asynchronous processing
- **Factory Pattern**: Object creation abstraction

### Frontend Patterns
- **Component-based Architecture**: Reusable Vue components
- **Composition API**: Logic composition and reuse
- **Single Responsibility**: Components with focused purposes
- **Props Down, Events Up**: Data flow pattern

---

## 🎯 Future Architecture Considerations

### Microservices Migration
- **API Gateway**: Central routing and authentication
- **Service Decomposition**: Separate webhook processing service
- **Event Sourcing**: Complete audit trail of system events
- **CQRS**: Command Query Responsibility Segregation

### Advanced Features
- **Webhook Transformations**: Payload transformation pipeline
- **Per-endpoint Rate Limiting**: Currently rate-limited per user; per-endpoint granularity is a future enhancement
- **Analytics Engine**: Advanced webhook analytics and insights
- **Multi-region Deployment**: Global webhook delivery optimization

This architecture provides a solid foundation for a scalable, maintainable webhook management platform while allowing for future enhancements and optimizations.
