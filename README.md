# ⚡ QuickMessage

Real-time chat application built with Laravel 12 and Laravel Reverb WebSocket server.

## Tech Stack


| Component               | Technology                     |
| ----------------------- | ------------------------------ |
| Backend                 | Laravel 12, PHP 8.3            |
| WebSocket               | Laravel Reverb                 |
| Database                | MySQL 8.0                      |
| Cache / Queue / Session | Redis 7                        |
| Frontend                | Blade, Alpine.js, Laravel Echo |
| Web Server              | Nginx                          |
| Containerization        | Docker, Docker Compose         |
| Static Analysis         | PHPStan (Larastan, level 6)    |
| Testing                 | PHPUnit 11                     |
| Load Testing            | K6                             |


## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) v24+
- [Docker Compose](https://docs.docker.com/compose/) v2+
- `make`

## Quick Start

```bash
# Clone the repository
git clone <repository-url>
cd QuickMessage

# One-command deploy
make setup
```

The setup command will:

1. Copy `.env.example` → `.env`
2. Build Docker images
3. Start all containers (app, nginx, mysql, redis, reverb, queue)
4. Install Composer and npm dependencies
5. Generate app key
6. Build frontend assets (Vite)
7. Run database migrations
8. Seed test users

App will be available at **[http://localhost](http://localhost)**

## Test Users

All test users are seeded automatically by `make setup`.


| Name    | Email                                             | Password     |
| ------- | ------------------------------------------------- | ------------ |
| Anton   | [anton@example.com](mailto:anton@example.com)     | Password123! |
| Bob     | [bob@example.com](mailto:bob@example.com)         | Password123! |
| Charlie | [charlie@example.com](mailto:charlie@example.com) | Password123! |
| Diana   | [diana@example.com](mailto:diana@example.com)     | Password123! |
| Elena   | [elena@example.com](mailto:elena@example.com)     | Password123! |


## Available Commands

```bash
# Start / Stop
make up          # Start containers
make down        # Stop containers
make restart     # Restart containers
make logs        # Tail all container logs
make logs-reverb # Tail Reverb WebSocket logs

# Development
make shell       # Open bash in app container
make migrate     # Run migrations
make fresh       # Drop all tables, re-migrate, re-seed
make artisan cmd="route:list"  # Run any artisan command

# Testing
make test        # Run all PHPUnit tests
make test-unit   # Run unit tests only
make test-feature # Run feature tests only
make stan        # Run PHPStan static analysis
make pint        # Fix code style with Laravel Pint
make k6          # Run K6 load test (requires docker)
```

## Architecture

```
Browser ─→ Nginx :80 ─┬─ /app/* ──→ Reverb :8080 (WebSocket)
                       └─ /*.php ──→ PHP-FPM :9000

PHP-FPM ──→ MySQL :3306
PHP-FPM ──→ Redis :6379
Reverb  ──→ Redis :6379 (pub/sub)
Queue   ──→ Redis :6379 (jobs)
```

### Layers

```
HTTP:      Controller → FormRequest
Business:  Service (ServiceInterface)
Data:      Repository (RepositoryInterface)
Events:    MessageSent (ShouldBroadcast) → Queue → Reverb → Echo
```

### Message Flow

```
1. User POSTs /messages/{userId}
2. MessageController → MessageService::send()
3. MessageRepository::create() → saved to MySQL
4. event(new MessageSent($message)) → Redis queue
5. Queue worker dispatches to Reverb
6. Reverb broadcasts on private-messages.{receiverId}
7. Browser receives via Laravel Echo → Alpine.js appends to DOM
```

## Running Tests

```bash
# PHPUnit (uses SQLite :memory:, no Docker needed)
make test

# PHPStan static analysis
make stan

# K6 load test (50 VUs, 60s, requires running app)
make k6
```

## Environment Variables

See `.env.example` for all available configuration options.

Key variables:


| Variable               | Description                                               |
| ---------------------- | --------------------------------------------------------- |
| `BROADCAST_CONNECTION` | `reverb`                                                  |
| `REVERB_HOST`          | Public hostname for browser clients (`localhost` for dev) |
| `REVERB_PORT`          | `8080`                                                    |
| `QUEUE_CONNECTION`     | `redis`                                                   |
| `SESSION_DRIVER`       | `redis`                                                   |


