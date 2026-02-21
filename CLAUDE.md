\# HR Platform — Claude Code Context



\## Source of Truth

The full PRD is located at `./PRD\_EventDriven\_Platform.docx`.

Always refer to it for acceptance criteria, test cases, and definitions of done.



\## Session Rule

At the start of every feature implementation session, read the relevant

section of ./PRD\_EventDriven\_Platform.docx before writing any code.

Do not rely solely on CLAUDE.md for feature-level details.



\## PRD Structure (PRD\_EventDriven\_Platform.docx)

\- Epic 1 — HR Service: Employee Management \& Event Publishing

&nbsp; - Feature 1.1 — Employee CRUD API

&nbsp; - Feature 1.2 — RabbitMQ Event Publishing

\- Epic 2 — HubService: Event Processing Pipeline

&nbsp; - Feature 2.1 — RabbitMQ Event Consumer

&nbsp; - Feature 2.2 — Event Handlers + Cache Invalidation

\- Epic 3 — Checklist System

\- Epic 4 — Server-Driven UI APIs

\- Epic 5 — WebSocket Broadcasting



\## Project Overview

Event-driven multi-country HR platform. Two Laravel microservices connected via RabbitMQ,

with Redis caching and real-time WebSocket broadcasting to frontend clients.



\## Repository Structure

```

hr-platform/

├── hr-service/                          # Laravel app — Employee CRUD + RabbitMQ publisher

├── hub-service/                         # Laravel app — Event consumer + Cache + WebSockets

├── docker-compose.yml

├── websocket-test.html

├── PRD\_EventDriven\_Platform.docx

├── README.md

└── CLAUDE.md

```



\## Tech Stack

\- \*\*Framework:\*\* Laravel (both services)

\- \*\*Message Broker:\*\* RabbitMQ (topic exchange)

\- \*\*Cache:\*\* Redis (TTL + tag-based invalidation)

\- \*\*WebSockets:\*\* Soketi (self-hosted)

\- \*\*Database:\*\* PostgreSQL (hr-service only)

\- \*\*Testing:\*\* PHPUnit

\- \*\*Orchestration:\*\* Docker Compose



\## Service Responsibilities



\### hr-service (port 8001)

\- Authoritative source of truth for employee data

\- Exposes REST CRUD API for employees

\- Publishes RabbitMQ events on every CRUD operation

\- Owns the PostgreSQL database



\### hub-service (port 8002)

\- Consumes RabbitMQ events from hr-service

\- Maintains Redis cache of employee data and checklist calculations

\- Exposes read APIs: /api/steps, /api/employees, /api/schema/{step\_id}, /api/checklists

\- Broadcasts real-time WebSocket events to connected frontend clients via Soketi



\## Methodology — TDD Enforced

\- \*\*Always write tests BEFORE writing implementation code\*\*

\- Minimum 80% unit test coverage on all business logic

\- Test order: Unit tests → Integration tests → Feature tests → Edge cases

\- Run `php artisan test` after every feature to confirm green before moving on

\- Never move to the next feature until the current one has passing tests



\## Country-Specific Data Models



\### USA Employees

\- Shared fields: id, name, last\_name, salary, country

\- USA-specific: ssn (masked in responses as \*\*\*-\*\*-XXXX), address



\### Germany Employees

\- Shared fields: id, name, last\_name, salary, country

\- Germany-specific: tax\_id (format: DE + 9 digits e.g. DE123456789), goal



\### Unsupported Countries

\- Any country not in \[USA, Germany] must return 422 validation error

\- No event should be published for invalid payloads



\## RabbitMQ Configuration

\- \*\*Exchange type:\*\* Topic exchange

\- \*\*Routing key format:\*\* `employee.{event\_type\_lower}.{country}`

\- \*\*Examples:\*\*

&nbsp; - `employee.created.USA`

&nbsp; - `employee.updated.Germany`

&nbsp; - `employee.deleted.USA`



\## Event Payload Structure

Every event published by hr-service must include:

```json

{

&nbsp; "event\_id": "uuid-v4",

&nbsp; "event\_type": "EmployeeCreated | EmployeeUpdated | EmployeeDeleted",

&nbsp; "timestamp": "ISO 8601",

&nbsp; "country": "USA | Germany",

&nbsp; "data": {

&nbsp;   "employee\_id": 1,

&nbsp;   "changed\_fields": \["salary", "goal"],

&nbsp;   "employee": { /\* full current employee object \*/ }

&nbsp; }

}

```

\- `changed\_fields` lists only modified fields (empty array for Created/Deleted)

\- `data.employee` must always contain the \*\*full\*\* employee object — hub-service must never need to call back to hr-service to get current state



\## Cache Rules (hub-service)

\- Employee list cache TTL: \*\*5 minutes\*\*

\- Checklist calculation cache TTL: \*\*10 minutes\*\*

\- Cache must be \*\*immediately invalidated\*\* when a relevant RabbitMQ event arrives

\- Use Redis tag-based invalidation where possible



\## Graceful Degradation Rules

\- If RabbitMQ is unavailable during a CRUD operation in hr-service:

&nbsp; - The database write MUST still succeed

&nbsp; - The publishing failure MUST be caught in a try-catch block

&nbsp; - A structured error log entry MUST be written (event\_type, employee\_id, exception message)

&nbsp; - The HTTP response to the client MUST reflect the successful DB operation (never return 500)



\## Retry \& Dead-Letter Queue Rules (hub-service consumer)

\- On handler failure: nack and requeue the message

\- Maximum retry attempts: \*\*3\*\*

\- Each retry attempt must be logged with attempt number and error message

\- After 3 failed attempts: move message to dead-letter queue

\- Write a critical-level log entry for every dead-lettered message



\## SSN Masking

\- SSN must never be returned in full in API responses

\- Display format: `\*\*\*-\*\*-XXXX` (last 4 digits only, e.g. `\*\*\*-\*\*-6789`)



\## API Endpoints



\### hr-service

| Method | Endpoint | Description |

|--------|----------|-------------|

| GET | /api/employees | Paginated employee list, filterable by country |

| POST | /api/employees | Create employee |

| GET | /api/employees/{id} | Get single employee |

| PUT | /api/employees/{id} | Update employee |

| DELETE | /api/employees/{id} | Delete employee |



\### hub-service

| Method | Endpoint | Description |

|--------|----------|-------------|

| GET | /api/steps | Server-driven UI step list (country-aware) |

| GET | /api/schema/{step\_id} | Form schema for a given step |

| GET | /api/employees | Cached employee list |

| GET | /api/checklists | Checklist completion data, filterable by country |



\## Out of Scope — Do Not Implement

\- Authentication, JWT, OAuth, or session management

\- Countries beyond USA and Germany

\- Frontend UI beyond websocket-test.html

\- CI/CD pipelines or deployment automation

\- Role-based access control (RBAC)

\- Mobile support

\- Third-party HR system integrations

\- Admin interfaces or CMS



\## Docker Services

| Service | Port | Purpose |

|---------|------|---------|

| postgres | 5432 | hr-service database |

| rabbitmq | 5672 / 15672 | Message broker / Management UI |

| redis | 6379 | Cache layer |

| soketi | 6001 | WebSocket server |

| hr-service | 8001 | Laravel HR API |

| hub-service | 8002 | Laravel Hub API |



\## Git Commit Rules

\- Initialise a git repo in the project root if one does not exist

\- Commit after every feature that has fully passing tests

\- Never commit if `php artisan test` has failures

\- Use conventional commit format: feat|fix|test|chore(scope): description

\- Scope should reference the service and feature e.g. feat(hr-service): ...

\- Always run tests before committing — never skip this step

\- Do not commit generated files: vendor/, .env, node\_modules/



\## Git Commit Checkpoints

| Commit | Trigger |

|--------|---------|

| `chore: initial scaffold` | After Docker Compose + Laravel apps created |

| `feat(hr-service): employee CRUD API` | After Feature 1.1 tests pass |

| `feat(hr-service): rabbitmq event publishing` | After Feature 1.2 tests pass |

| `feat(hub-service): rabbitmq consumer` | After Feature 2.1 tests pass |

| `feat(hub-service): event handlers and cache` | After Feature 2.2 tests pass |

| `feat(hub-service): checklist system` | After Epic 3 tests pass |

| `feat(hub-service): server-driven UI APIs` | After Epic 4 tests pass |

| `feat(hub-service): websocket broadcasting` | After Epic 5 tests pass |

| `chore: readme and coverage audit` | Final session |

