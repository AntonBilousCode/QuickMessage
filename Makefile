.PHONY: setup up down restart build shell logs test stan pint k6 migrate seed fresh

# ─── Colors ────────────────────────────────────────────────────
GREEN  := \033[0;32m
YELLOW := \033[1;33m
CYAN   := \033[0;36m
RESET  := \033[0m

# ─── Primary target: full first-time setup ─────────────────────
## setup: Build images, install dependencies, migrate, seed — one command deploy
setup:
	@echo "$(CYAN)━━━ QuickMessage Setup ━━━━━━━━━━━━━━━━━━━━━━━━━$(RESET)"

	@# Copy .env if not exists
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		echo "$(GREEN)✓ .env created from .env.example$(RESET)"; \
	else \
		echo "$(YELLOW)⚠ .env already exists — skipping$(RESET)"; \
	fi

	@# Build and start containers
	@# Note: docker compose waits for mysql service_healthy before starting app
	@echo "$(CYAN)▶ Building Docker images and starting containers...$(RESET)"
	docker compose up -d --build
	@echo "$(GREEN)✓ All containers started (MySQL healthcheck passed)$(RESET)"

	@# Install PHP dependencies
	@echo "$(CYAN)▶ Installing PHP dependencies...$(RESET)"
	docker compose exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader

	@# Generate app key
	@echo "$(CYAN)▶ Generating application key...$(RESET)"
	docker compose exec -T app php artisan key:generate --no-interaction

	@# Install Node dependencies and build assets
	@echo "$(CYAN)▶ Installing Node dependencies...$(RESET)"
	docker compose exec -T app npm install --cache /tmp/.npm

	@echo "$(CYAN)▶ Building frontend assets...$(RESET)"
	docker compose exec -T app npm run build --cache /tmp/.npm

	@# Run migrations and seed
	@echo "$(CYAN)▶ Running database migrations...$(RESET)"
	docker compose exec -T app php artisan migrate --no-interaction --force

	@echo "$(CYAN)▶ Seeding database with test users...$(RESET)"
	docker compose exec -T app php artisan db:seed --no-interaction --force

	@echo ""
	@echo "$(GREEN)━━━ Setup Complete! ━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(RESET)"
	@echo ""
	@echo "  App:     http://localhost"
	@echo "  Reverb:  ws://localhost:$$(grep '^REVERB_PORT=' .env | cut -d= -f2)"
	@echo ""
	@echo "  Test users (password: Password123!):"
	@echo "    anton@example.com"
	@echo "    bob@example.com"
	@echo "    charlie@example.com"
	@echo "    diana@example.com"
	@echo "    elena@example.com"
	@echo ""
	@echo "  Run tests: make test"
	@echo "  View logs: make logs"
	@echo "$(GREEN)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(RESET)"

## up: Start all containers
up:
	docker compose up -d

## down: Stop all containers
down:
	docker compose down

## restart: Restart all containers
restart:
	docker compose restart

## build: Rebuild Docker images
build:
	docker compose build

## shell: Open bash shell in the app container
shell:
	docker compose exec app bash

## logs: Tail logs from all containers
logs:
	docker compose logs -f

## logs-app: Tail logs from app container only
logs-app:
	docker compose logs -f app

## logs-reverb: Tail logs from Reverb container
logs-reverb:
	docker compose logs -f reverb

# ─── Laravel commands ──────────────────────────────────────────

## migrate: Run database migrations
migrate:
	docker compose exec -T app php artisan migrate --no-interaction

## seed: Run database seeders
seed:
	docker compose exec -T app php artisan db:seed --no-interaction --force

## fresh: Drop all tables, re-migrate and seed
fresh:
	docker compose exec -T app php artisan migrate:fresh --seed --no-interaction

## artisan: Run artisan command (e.g. make artisan cmd="route:list")
artisan:
	docker compose exec -T app php artisan $(cmd)

# ─── Testing & Quality ─────────────────────────────────────────

## test: Run PHPUnit tests
test:
	docker compose exec -T -e APP_ENV=testing app ./vendor/bin/phpunit --colors=always

## test-unit: Run unit tests only
test-unit:
	docker compose exec -T -e APP_ENV=testing app ./vendor/bin/phpunit --testsuite Unit --colors=always

## test-feature: Run feature tests only
test-feature:
	docker compose exec -T -e APP_ENV=testing app ./vendor/bin/phpunit --testsuite Feature --colors=always

## stan: Run PHPStan static analysis (level 6)
stan:
	docker compose exec -T app ./vendor/bin/phpstan analyse --memory-limit=512M

## pint: Run Laravel Pint code style fixer
pint:
	docker compose exec -T app ./vendor/bin/pint

## pint-check: Check code style without fixing
pint-check:
	docker compose exec -T app ./vendor/bin/pint --test

## k6: Run K6 load test (requires Docker)
k6:
	docker run --rm -i --network host grafana/k6 run \
		--env BASE_URL=http://localhost \
		- < tests/k6/load-test.js

# ─── Default help ──────────────────────────────────────────────
help:
	@echo "$(CYAN)QuickMessage — Available commands:$(RESET)"
	@grep -E '^##' Makefile | sed 's/## /  make /' | sed 's/:/\t/'

.DEFAULT_GOAL := help
