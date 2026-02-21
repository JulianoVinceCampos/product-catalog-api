.PHONY: up down build setup migrate seed reindex test lint logs shell

## ─── Docker ──────────────────────────────────────────────────────────────
up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

## ─── Setup ───────────────────────────────────────────────────────────────
setup:
	docker compose up -d
	docker compose exec app composer install
	docker compose exec app cp .env.example .env || true
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate
	docker compose exec app php artisan db:seed
	docker compose exec app php artisan elastic:reindex
	@echo "✅ Setup complete! API at http://localhost:8000"

## ─── Database ────────────────────────────────────────────────────────────
migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

fresh:
	docker compose exec app php artisan migrate:fresh --seed

## ─── Elasticsearch ───────────────────────────────────────────────────────
reindex:
	docker compose exec app php artisan elastic:reindex

reindex-fresh:
	docker compose exec app php artisan elastic:reindex --fresh

## ─── Queue ───────────────────────────────────────────────────────────────
worker:
	docker compose exec app php artisan queue:work --tries=3

## ─── Testing ─────────────────────────────────────────────────────────────
test:
	docker compose exec app ./vendor/bin/pest

test-unit:
	docker compose exec app ./vendor/bin/pest --testsuite=Unit

test-feature:
	docker compose exec app ./vendor/bin/pest --testsuite=Feature

## ─── Code Quality ────────────────────────────────────────────────────────
lint:
	docker compose exec app ./vendor/bin/pint

lint-check:
	docker compose exec app ./vendor/bin/pint --test

## ─── Utilities ───────────────────────────────────────────────────────────
logs:
	docker compose logs app -f

shell:
	docker compose exec app bash

cache-clear:
	docker compose exec app php artisan cache:clear
