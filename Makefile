.PHONY: help dev dev-up dev-down prod-up prod-down prod-build prod-check deploy test test-deploy lint backup health

help:
	@echo "SIMA Makefile"
	@echo ""
	@echo "  make dev          Start Laravel API + Next.js (local, no Docker)"
	@echo "  make dev-up       Start development stack (docker compose)"
	@echo "  make dev-down     Stop development stack"
	@echo "  make prod-up      Start production stack"
	@echo "  make prod-down    Stop production stack"
	@echo "  make prod-build   Build production images"
	@echo "  make prod-check   Validate production environment + Compose"
	@echo "  make deploy       Deploy to DEPLOY_PATH using scripts/deploy-vps.sh"
	@echo "  make test         Run backend tests"
	@echo "  make test-deploy  Run production deployment preflight tests"
	@echo "  make lint         Run Laravel Pint"
	@echo "  make backup       Run database backup (requires running app)"
	@echo "  make health       Hit health endpoint"

dev-up:
	docker compose up -d --build

dev:
	./dev.sh

dev-down:
	docker compose down

prod-build:
	docker compose -f docker-compose.prod.yml build

prod-up:
	docker compose -f docker-compose.prod.yml up -d --build

prod-check:
	sh scripts/check-production-env.sh

deploy:
	sh scripts/deploy-vps.sh

prod-down:
	docker compose -f docker-compose.prod.yml down

test:
	php artisan test

test-deploy:
	sh tests/Scripts/check-production-env.sh

lint:
	./vendor/bin/pint --test

backup:
	docker compose -f docker-compose.prod.yml exec app php artisan sima:backup-db

health:
	curl -fsS "$${APP_URL:-http://localhost}/api/health" | python3 -m json.tool
