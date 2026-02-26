# Event-Driven Multi-Country HR Platform

An event-driven HR platform built with two Laravel microservices connected via RabbitMQ, with Redis caching and real-time WebSocket broadcasting.

## Architecture Overview

```
 ┌─────────────────────────────────────────────────────────────────────────────────────────┐
 │                                 HR Platform Architecture                                │
 ├─────────────────────────────────────────────────────────────────────────────────────────┤
 │                                                                                         │
 │  ┌───────────────┐    RabbitMQ (5672)              ┌─────────────────────────┐          │
 │  │               │ Topic Exchange: hr.events       │                         │          │
 │  │  hr-service   │ ────────────────────────────►   │  hub-service            │          │
 │  │  :8001        │  Routing: employee.{event}.{cc} │  :8002                  │          │
 │  │               │                                 │                         │          │
 │  │  - CRUD API   │                                 │  - Consumer             │          │
 │  │  - Publishes  │                                 │  - Cache mgmt           │          │
 │  │    events     │                                 │  - Checklists           │          │
 │  │               │                                 │  - Server-Driven UI Api │          │
 │  └──────┬────────┘                                 │  - WebSocket            │          │
 │         │                                          └──┬───────────────┬──────┘          │
 │    ┌────▼─────┐                               ┌───────▼─┐          ┌──▼────┐            │
 │    │PostgreSQL│                               │ Redis   │          │Soketi │            │
 │    │  :5432   │                               │ :6379   │          │ :6001 │            │
 │    └──────────┘                               └─────────┘          └───────┘            │
 └─────────────────────────────────────────────────────────────────────────────────────────┘


  - hr-service (:8001) — Employee CRUD + publishes events to RabbitMQ
  - hub-service (:8002) — Consumes events, caches data in Redis, serves checklists, broadcasts via WebSocket (Soketi)
  - RabbitMQ — Topic exchange hr.events, routing keys like employee.created.USA
  - Redis — Cache with TTL + tag-based invalidation
  - PostgreSQL — Source of truth for employee data
  - Soketi — Self-hosted WebSocket server (Pusher-compatible)

```

### Event Flow

1. Client sends CRUD request to **HR Service**
2. HR Service persists to **PostgreSQL**
3. HR Service publishes event to **RabbitMQ** (topic exchange `hr.events`)
4. Hub Service consumer receives and routes to typed handler
5. Handler updates **Redis** cache and invalidates stale entries
6. Handler broadcasts **WebSocket** event via **Soketi**
7. Connected browser clients receive real-time updates

### HR Service (port 8001)

- Authoritative source of truth for employee data
- REST CRUD API for employees (USA and Germany)
- Publishes events to RabbitMQ on every create/update/delete
- Graceful degradation: DB writes succeed even if RabbitMQ is unavailable
- Owns the PostgreSQL database

### Hub Service (port 8002)

- Consumes employee events from RabbitMQ (3 retries + dead-letter queue)
- Maintains Redis cache with tag-based invalidation
- Exposes read-only APIs: steps, schema, employees, checklists
- Broadcasts real-time WebSocket events via Soketi
- SSN masking in all API responses and WebSocket payloads

## Tech Stack

| Technology | Version | Purpose |
|------------|---------|---------|
| Laravel | 12.x | Application framework (both services) |
| PHP | 8.2+ | Runtime |
| PostgreSQL | 15 | Employee database (hr-service) |
| RabbitMQ | 3.12 | Message broker (topic exchange) |
| Redis | 7 | Cache layer with tag-based invalidation (hub-service) |
| Soketi | 1.6 | Self-hosted Pusher-compatible WebSocket server |
| PHPUnit | 11.x | Testing framework |
| Docker Compose | 3.8+ | Service orchestration |

## Prerequisites

- **Docker Desktop** (includes Docker Compose)
- **Git**

## Setup

```bash
# Clone the repository
git clone https://github.com/netwrkx/hr-platform.git
cd hr-platform

# Start all services
docker compose up -d

# Run hr-service database migrations
docker compose exec hr-service php artisan migrate

# Start the RabbitMQ consumer (in a separate terminal)
docker compose exec hub-service php artisan rabbitmq:consume
```

## Service URLs

| Service | URL | Description |
|---------|-----|-------------|
| HR Service API | http://localhost:8001/api/employees | Employee CRUD API |
| Hub Service — Employees | http://localhost:8002/api/employees?country=USA | Cached employee list |
| Hub Service — Steps | http://localhost:8002/api/steps?country=USA | Server-driven UI navigation steps |
| Hub Service — Schema | http://localhost:8002/api/schema/dashboard?country=USA | Dashboard widget schema |
| Hub Service — Checklists | http://localhost:8002/api/checklists?country=USA | Checklist completion data |
| RabbitMQ Management UI | http://localhost:15672 | Broker dashboard |
| WebSocket Test Page | Open `websocket-test.html` in browser | Real-time event viewer |

## Docker Services

| Service | Port(s) | Image | Purpose |
|---------|---------|-------|---------|
| postgres | 5432 | postgres:15-alpine | HR Service database |
| rabbitmq | 5672, 15672 | rabbitmq:3.12-management-alpine | Message broker + Management UI |
| redis | 6379 | redis:7-alpine | Cache layer |
| soketi | 6001, 6002 | quay.io/soketi/soketi:1.6-16-debian | WebSocket server |
| hr-service | 8001 | Custom (Laravel) | Employee CRUD + Event Publisher |
| hub-service | 8002 | Custom (Laravel) | Event Consumer + Cache + APIs |

## API Endpoints

### HR Service (port 8001)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/employees` | Paginated employee list, filterable by `?country=USA\|Germany` |
| POST | `/api/employees` | Create employee (USA or Germany) |
| GET | `/api/employees/{id}` | Get single employee (SSN masked) |
| PUT | `/api/employees/{id}` | Update employee |
| DELETE | `/api/employees/{id}` | Delete employee |

#### Create USA Employee

```bash
curl -X POST http://localhost:8001/api/employees \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John",
    "last_name": "Doe",
    "salary": 75000,
    "country": "USA",
    "ssn": "123-45-6789",
    "address": "123 Main St, New York, NY"
  }'
```

#### Create Germany Employee

```bash
curl -X POST http://localhost:8001/api/employees \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Hans",
    "last_name": "Mueller",
    "salary": 65000,
    "country": "Germany",
    "tax_id": "DE123456789",
    "goal": "Lead Q3 product launch"
  }'
```

### Hub Service (port 8002)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/steps?country=USA` | Server-driven UI step list (country-aware navigation) |
| GET | `/api/schema/{step_id}?country=USA` | Form/widget schema for a step (`dashboard`, `employees`) |
| GET | `/api/employees?country=USA` | Cached employee list with column definitions |
| GET | `/api/checklists?country=USA` | Checklist completion data per employee |

All hub-service endpoints require the `country` query parameter (`USA` or `Germany`).

## RabbitMQ

### Management UI

- **URL:** http://localhost:15672
- **Username:** `hr_rabbit`
- **Password:** `hr_rabbit_secret`

### Routing Keys

Format: `employee.{event_type}.{country}`

| Event | Example Routing Key |
|-------|-------------------|
| Employee Created | `employee.created.USA` |
| Employee Updated | `employee.updated.Germany` |
| Employee Deleted | `employee.deleted.USA` |

### Event Payload Structure

```json
{
  "event_id": "550e8400-e29b-41d4-a716-446655440000",
  "event_type": "EmployeeCreated",
  "timestamp": "2026-02-23T12:00:00.000000Z",
  "country": "USA",
  "data": {
    "employee_id": 1,
    "changed_fields": [],
    "employee": {
      "id": 1,
      "name": "John",
      "last_name": "Doe",
      "salary": "75000.00",
      "country": "USA",
      "ssn": "123-45-6789",
      "address": "123 Main St, New York, NY"
    }
  }
}
```

## WebSocket Test Page

The `websocket-test.html` file provides a browser-based real-time event viewer.

1. Ensure all Docker services are running: `docker compose up -d`
2. Open `websocket-test.html` directly in your browser
3. The page connects to Soketi on `localhost:6001`
4. It subscribes to channels: `employees.USA` and `employees.Germany`
5. Create/update/delete employees via the HR Service API to see events appear

Events are color-coded:
- **Green** — EmployeeCreated
- **Yellow** — EmployeeUpdated
- **Red** — EmployeeDeleted

## Cache Strategy

### Cache Keys (hub-service)

| Key Pattern | TTL | Description |
|-------------|-----|-------------|
| `employee:{id}` | 5 min | Individual employee record |
| `employees:{country}:page:{p}:per_page:{pp}` | 5 min | Paginated employee list |
| `checklist:{country}` | 10 min | Checklist calculation result |
| `schema:{step_id}:{country}` | 60 min | UI schema configuration |
| `steps:{country}` | 60 min | Navigation steps |

All caches are immediately invalidated when a relevant RabbitMQ event arrives. Tag-based invalidation ensures all entries for a given country are cleared together.

## Country-Specific Data Models

### USA Employees
- Fields: `name`, `last_name`, `salary`, `country`, `ssn`, `address`
- SSN is masked in all responses as `***-**-XXXX` (last 4 digits visible)

### Germany Employees
- Fields: `name`, `last_name`, `salary`, `country`, `tax_id`, `goal`
- Tax ID format: `DE` followed by 9 digits (e.g., `DE123456789`)

Unsupported countries return a `422` validation error.

## Testing

### Run Tests Locally

```bash
# HR Service tests (69 tests)
cd hr-service
php artisan test

# Hub Service tests (158 tests)
cd hub-service
php artisan test
```

### Run Tests in Docker

```bash
# HR Service
docker compose exec hr-service php artisan test

# Hub Service
docker compose exec hub-service php artisan test
```

### Test Coverage

Both services exceed the 80% minimum coverage requirement:

| Service | Tests | Assertions | Coverage |
|---------|-------|------------|----------|
| hr-service | 69 | 200 | 96.7% |
| hub-service | 158 | 473 | 86.0% |
| **Total** | **227** | **673** | |

### Test Suites

**HR Service:**
- `EmployeeFormRequestTest` — Country-specific validation (16 tests)
- `EmployeeModelTest` — SSN masking, model attributes (5 tests)
- `EventPayloadBuilderTest` — Event payload structure, routing keys (24 tests)
- `EmployeeCrudTest` — Full CRUD lifecycle, graceful degradation (20 tests)
- `RabbitMQEventPublishingTest` — Event publishing integration (4 tests)

**Hub Service:**
- `BroadcastServiceTest` — Channel naming, payload building, SSN masking (12 tests)
- `CacheServiceTest` — TTL, invalidation, tag-based clearing (10 tests)
- `ChecklistServiceTest` — Country validators, completion %, caching (21 tests)
- `ColumnConfigTest` — Column definitions, SSN masking (11 tests)
- `EmployeeCreated/Updated/DeletedHandlerTest` — Cache + broadcast (21 tests)
- `EventConsumerTest` — Routing, validation, retry/dead-letter (13 tests)
- `StepRegistryTest` / `SchemaBuilderTest` — Server-driven UI (20 tests)
- Feature tests — API integration, caching, broadcasting (50 tests)

## Environment Variables

### HR Service

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_CONNECTION` | `pgsql` | Database driver |
| `DB_HOST` | `postgres` | PostgreSQL host |
| `DB_PORT` | `5432` | PostgreSQL port |
| `DB_DATABASE` | `hr_platform` | Database name |
| `DB_USERNAME` | `hr_user` | Database user |
| `DB_PASSWORD` | `hr_secret` | Database password |
| `RABBITMQ_HOST` | `rabbitmq` | RabbitMQ host |
| `RABBITMQ_PORT` | `5672` | RabbitMQ port |
| `RABBITMQ_USER` | `hr_rabbit` | RabbitMQ user |
| `RABBITMQ_PASSWORD` | `hr_rabbit_secret` | RabbitMQ password |
| `RABBITMQ_VHOST` | `/` | RabbitMQ virtual host |
| `RABBITMQ_EXCHANGE` | `hr.events` | Topic exchange name |

### Hub Service

| Variable | Default | Description |
|----------|---------|-------------|
| `REDIS_HOST` | `redis` | Redis host |
| `REDIS_PORT` | `6379` | Redis port |
| `RABBITMQ_HOST` | `rabbitmq` | RabbitMQ host |
| `RABBITMQ_PORT` | `5672` | RabbitMQ port |
| `RABBITMQ_USER` | `hr_rabbit` | RabbitMQ user |
| `RABBITMQ_PASSWORD` | `hr_rabbit_secret` | RabbitMQ password |
| `RABBITMQ_EXCHANGE` | `hr.events` | Topic exchange name |
| `RABBITMQ_QUEUE` | `hub.employee.events` | Consumer queue name |
| `BROADCAST_CONNECTION` | `pusher` | Broadcasting driver |
| `PUSHER_APP_ID` | `hr-platform` | Soketi app ID |
| `PUSHER_APP_KEY` | `hr-platform-key` | Soketi app key |
| `PUSHER_APP_SECRET` | `hr-platform-secret` | Soketi app secret |
| `PUSHER_HOST` | `soketi` | Soketi host |
| `PUSHER_PORT` | `6001` | Soketi port |

## Git Commit History

| Commit | Description |
|--------|-------------|
| `chore: initial scaffold` | Docker Compose + Laravel apps |
| `feat(hr-service): employee CRUD API` | Feature 1.1 — REST API with validation |
| `feat(hr-service): rabbitmq event publishing` | Feature 1.2 — Event publisher |
| `feat(hub-service): rabbitmq consumer` | Feature 2.1 — Event consumer with retry/DLQ |
| `feat(hub-service): event handlers and cache` | Feature 2.2 — Handlers + Redis cache |
| `feat(hub-service): checklist system` | Epic 3 — Country-specific checklists |
| `feat(hub-service): server-driven UI APIs` | Epic 4 — Steps, schema, column configs |
| `feat(hub-service): websocket broadcasting` | Epic 5 — Real-time Soketi broadcasting |
| `chore: readme and coverage audit` | Final documentation + coverage audit |
