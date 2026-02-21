# Event-Driven Multi-Country HR Platform

An event-driven HR platform built with two Laravel microservices connected via RabbitMQ, with Redis caching and real-time WebSocket broadcasting.

## Architecture Overview

```
┌──────────────┐       RabbitMQ        ┌──────────────┐
│  HR Service  │ ───── (topic) ──────► │  Hub Service  │
│  (port 8001) │    hr.events exchange │  (port 8002)  │
│              │                       │               │
│  PostgreSQL  │                       │  Redis Cache  │
│  Employee DB │                       │  WebSocket    │
└──────────────┘                       └───────┬───────┘
                                               │
                                          Soketi (6001)
                                               │
                                        ┌──────▼──────┐
                                        │   Browser    │
                                        │  (Pusher.js) │
                                        └─────────────┘
```

### HR Service (port 8001)
- Authoritative source of truth for employee data
- REST CRUD API for employees (USA and Germany)
- Publishes events to RabbitMQ on every create/update/delete
- Owns PostgreSQL database

### Hub Service (port 8002)
- Consumes employee events from RabbitMQ
- Maintains Redis cache with tag-based invalidation
- Exposes read APIs: steps, schema, employees, checklists
- Broadcasts real-time WebSocket events via Soketi

## Tech Stack

| Technology | Purpose |
|------------|---------|
| Laravel    | Application framework (both services) |
| PostgreSQL | Employee database (hr-service) |
| RabbitMQ   | Message broker (topic exchange) |
| Redis      | Cache layer (hub-service) |
| Soketi     | Self-hosted WebSocket server |
| Docker Compose | Service orchestration |

## Getting Started

### Prerequisites
- Docker and Docker Compose installed

### Setup

```bash
# Clone the repository
git clone <repo-url>
cd hr-platform

# Start all services
docker-compose up -d

# Run hr-service migrations
docker-compose exec hr-service php artisan migrate

# Start the RabbitMQ consumer (in a separate terminal)
docker-compose exec hub-service php artisan rabbitmq:consume
```

### Verify Services

Open the following URLs to verify the services are running:

| Service | URL | Description |
|---------|-----|-------------|
| HR Service API | http://localhost:8001/api/employees | Employee CRUD API |
| Hub Service API | http://localhost:8002/api/employees | Cached employee list |
| Hub Service Steps | http://localhost:8002/api/steps?country=USA | Server-driven UI steps |
| Hub Service Schema | http://localhost:8002/api/schema/dashboard?country=USA | Dashboard widget schema |
| Hub Service Checklists | http://localhost:8002/api/checklists?country=USA | Checklist completion data |
| RabbitMQ Management | http://localhost:15672 | Broker UI (hr_rabbit / hr_rabbit_secret) |
| WebSocket Test | Open `websocket-test.html` in browser | Real-time event viewer |

## API Endpoints

### HR Service (port 8001)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | /api/employees | Paginated employee list, filterable by country |
| POST   | /api/employees | Create employee |
| GET    | /api/employees/{id} | Get single employee |
| PUT    | /api/employees/{id} | Update employee |
| DELETE | /api/employees/{id} | Delete employee |

### Hub Service (port 8002)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | /api/steps | Server-driven UI step list (country-aware) |
| GET    | /api/schema/{step_id} | Form/widget schema for a given step |
| GET    | /api/employees | Cached employee list with column definitions |
| GET    | /api/checklists | Checklist completion data, filterable by country |

## Event Flow

1. Client sends CRUD request to HR Service
2. HR Service persists to PostgreSQL
3. HR Service publishes event to RabbitMQ (topic exchange `hr.events`)
4. Hub Service consumer receives and routes to typed handler
5. Handler updates Redis cache and invalidates stale entries
6. Handler broadcasts WebSocket event via Soketi
7. Connected browser clients receive real-time updates

### RabbitMQ Routing Keys

Format: `employee.{event_type}.{country}`

- `employee.created.USA`
- `employee.updated.Germany`
- `employee.deleted.USA`

## Cache Key Structure

| Key Pattern | TTL | Description |
|-------------|-----|-------------|
| `employee:{id}` | 5 min | Single employee record |
| `employees:{country}:page:{p}:per_page:{pp}` | 5 min | Paginated employee list |
| `checklist:{country}` | 10 min | Checklist calculation result |
| `schema:{step_id}:{country}` | 60 min | UI schema configuration |
| `steps:{country}` | 60 min | Navigation steps config |

## Country-Specific Data Models

### USA Employees
- Shared: id, name, last_name, salary, country
- USA-specific: ssn (masked as `***-**-XXXX`), address

### Germany Employees
- Shared: id, name, last_name, salary, country
- Germany-specific: tax_id (format: `DE` + 9 digits), goal

## Docker Services

| Service | Port | Purpose |
|---------|------|---------|
| postgres | 5432 | HR Service database |
| rabbitmq | 5672 / 15672 | Message broker / Management UI |
| redis | 6379 | Cache layer |
| soketi | 6001 | WebSocket server |
| hr-service | 8001 | Laravel HR API |
| hub-service | 8002 | Laravel Hub API |

## Testing

```bash
# Run hr-service tests
docker-compose exec hr-service php artisan test

# Run hub-service tests
docker-compose exec hub-service php artisan test
```
