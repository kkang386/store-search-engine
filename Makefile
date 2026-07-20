.PHONY: up down build restart logs shell es-setup reindex reset-catalog import benchmark test migrate seed

## Start all containers
up:
	docker compose up -d

## Stop all containers
down:
	docker compose down

## Build containers
build:
	docker compose build

## Restart app container
restart:
	docker compose restart app worker

## Follow logs
logs:
	docker compose logs -f app worker

## Shell into app container
shell:
	docker compose exec app bash

## Copy .env and generate app key
env:
	#cp .env.example .env
	docker compose exec app php artisan key:generate

## Install PHP dependencies
install:
	docker compose exec app composer install

## Run database migrations
migrate:
	docker compose exec app php artisan migrate --force

## Run database seeders
seed:
	docker compose exec app php artisan db:seed --force

## Set up Elasticsearch indexes
es-setup:
	docker compose exec app bash /var/www/elasticsearch/setup.sh

## Reindex all products (stops worker to prevent race on index recreation)
reindex:
	docker compose stop worker
	docker compose exec app php artisan search:reindex --fresh
	docker compose start worker

## Wipe all product/category data from MySQL and rebuild the empty ES index
## After this, re-import via the admin UI or: make import
reset-catalog:
	docker compose stop worker
	docker compose exec app php artisan queue:clear redis --queue=imports
	docker compose exec app php artisan queue:clear redis --queue=indexing
	docker compose exec app php artisan queue:clear redis --queue=default
	docker compose exec app php artisan catalog:reset --force
	docker compose start worker

## Run CSV import for all active stores
import:
	docker compose exec app php artisan import:products

## Index a single product
index-product:
	docker compose exec app php artisan search:index-product $(id)

## Run search quality benchmark
benchmark:
	docker compose exec app php artisan search:benchmark

## Run CI benchmark with regression detection
benchmark-ci:
	docker compose exec app php artisan search:benchmark --ci

## Run all tests
test:
	docker compose exec app php artisan test

## Run unit tests only
test-unit:
	docker compose exec app php artisan test --testsuite=Unit

## Run feature tests only
test-feature:
	docker compose exec app php artisan test --testsuite=Feature

## Code style check
lint:
	docker compose exec app vendor/bin/pint --test

## Fix code style
fix:
	docker compose exec app vendor/bin/pint

## Full project setup from scratch
setup: build up env install migrate seed es-setup reindex
	@echo "Setup complete. App: http://localhost:8080 | Admin: http://localhost:8080/admin | Kibana: http://localhost:5601 | Grafana: http://localhost:3000"
