# E-commerce Search Engine

A production-grade, self-hostable **e-commerce search service** powered by **Elasticsearch 8.x** and **Laravel 12**. It provides fast full-text product search, autocomplete, faceted navigation, and a full merchandising admin UI — plus the pipelines to keep the index in sync with your catalog.

> Multi-tenant by design: a single deployment is scoped to a company via `COMPANY_ID`, which names its Elasticsearch index (`<COMPANY_ID>_products`) and synonym set (`<COMPANY_ID>_synonyms`).

---

## What it does

- **Full-text product search** — typo tolerance (fuzzy), stemming, and synonym expansion, with relevance tuning.
- **Autocomplete / suggestions** — search-as-you-type and completion suggesters.
- **Faceted search** — brand, category, price-range and attribute facets with counts.
- **Merchandising query rules** — pin, boost, bury/exclude, redirect, and banner rules; scope a rule to a category (and **all its subcategories**) or brand; priority and active time windows.
- **Synonyms** — global synonym management via the native **Elasticsearch Synonyms API**; edits are pushed to the live synonym set and search analyzers are reloaded **immediately** (no reindex).
- **Campaigns** — scheduled boosts and promotional banners.
- **Search analytics** — queries, click-through, conversion, latency; a dashboard for zero-result queries, top terms, and performance.
- **Catalog import pipeline** — per-store CSV import of products/categories, auto-picked-up on a schedule and **bulk-indexed** into Elasticsearch.
- **Admin UI** — dashboard, analytics, query rules, synonyms, campaigns, live search preview, ranking, audit log, and user management, with **role-based access** (`system_admin`, `search_admin`, `merchandiser`, `analyst`, `read_only`).

## Tech stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 (PHP 8.4) |
| Search | Elasticsearch 8.x |
| Database | MySQL 8.4 |
| Cache / queue / sessions | Redis (3 isolated logical DBs) |
| Admin UI | Blade + Alpine.js + Tailwind CSS |
| Auth / permissions | Spatie Laravel-Permission, activity log |
| Runtime | Docker Compose (app + nginx + worker + scheduler) |

---

## Quickstart (Docker)

**Prerequisites:** Docker and Docker Compose.

```bash
# 1. Configure
cp .env.example .env
#   edit .env: set COMPANY_ID (e.g. "acme"), APP_KEY is generated below,
#   and set the ADMIN_EMAIL / ADMIN_PASSWORD you want for the first admin user.

# 2. Start the stack (app, nginx, mysql, elasticsearch, redis, worker, kibana, grafana)
docker compose up -d --build

# 3. Install PHP dependencies + app key
docker compose exec app composer install
docker compose exec app php artisan key:generate

# 4. Database schema + seed roles/permissions + first admin user
docker compose exec app php artisan migrate --seed

# 5. Create the Elasticsearch index + synonym set (and reindex any catalog)
docker compose exec app php artisan search:reindex --fresh
```

Then open:

- **Admin UI** → http://localhost:8080/admin (log in with your `ADMIN_EMAIL` / `ADMIN_PASSWORD`)
- **Kibana** → http://localhost:5601
- **Grafana** → http://localhost:3000

> Ports are configurable in `.env` (`APP_PORT`, `ES_PORT`, `KIBANA_PORT`, `GRAFANA_PORT`, …).

---

## Search API

The public search endpoints are authenticated with a per-store API token (`Authorization: Bearer <token>`), which you create in the admin UI under **Stores**.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/search?q=...` | Product search (supports `filters`, `facets`, `sort`, `page`, `per_page`) |
| `GET` | `/api/search/suggest?q=...` | Autocomplete suggestions |
| `POST` | `/api/search/click` | Record a result click (for analytics/CTR) |
| `GET` | `/api/health` | Health check |

```bash
curl -H "Authorization: Bearer <API_TOKEN>" \
  "http://localhost:8080/api/search?q=wireless+headphones&per_page=24"
```

---

## Configuration highlights

All configuration is via environment variables (see `.env.example`):

- **`COMPANY_ID`** — tenant id; drives the ES index (`<COMPANY_ID>_products`) and synonym set (`<COMPANY_ID>_synonyms`).
- **Redis DBs** — isolated per subsystem: `REDIS_DB` (queues), `REDIS_CACHE_DB` (cache), `REDIS_SESSION_DB` (sessions).
- **Search tuning** — `SEARCH_PER_PAGE_MAX`, `SEARCH_FUZZY_ENABLED`, `SEARCH_MIN_SCORE`, etc.
- **Elasticsearch** — `ELASTICSEARCH_HOST`, `ELASTICSEARCH_SHARD_COUNT`, `ELASTICSEARCH_REPLICA_COUNT`.

---

## Catalog import

Drop per-store CSVs (`categories.csv`, `products.csv`, `product_categories.csv`) into the store's import folder. A scheduled command picks them up and imports + bulk-indexes them:

```bash
# manually trigger a pending-import check (also runs every 15 min on the scheduler)
docker compose exec app php artisan import:check-pending
```

Indexing after import is done in bulk (not per-row), and product edits reindex incrementally via a model observer.

---

## Background processing

- **Worker** (`queue:work`) — processes imports and indexing jobs on dedicated queues.
- **Scheduler** (`schedule:work`, started by the app container) — runs recurring tasks such as `import:check-pending`.

---

## Useful commands

```bash
php artisan search:reindex            # bulk reindex all products
php artisan search:reindex --fresh    # drop & recreate the index (and synonym set), then reindex
php artisan import:check-pending      # scan store folders for pending CSVs and queue imports
php artisan migrate --seed            # schema + roles/permissions + admin user
php artisan test                      # run the test suite
```

---

## Project structure

```
app/
  Http/Controllers/{Api,Admin}/   # search API + admin endpoints
  Services/Search/                # SearchService, SuggestService, IndexingService, CategoryService, …
  Services/Admin/                 # ImportService, SynonymService, QueryRuleService, …
  Repositories/                   # ElasticsearchRepository
  Models/                         # Product, Category, Store, QueryRule, Synonym, …
config/elasticsearch.php          # index + synonym-set naming, analysis config
elasticsearch/mappings/           # products index mapping (templated with the synonym set)
resources/views/admin/            # Blade + Alpine admin UI
routes/{api,web}.php              # API and admin routes
docs/OPERATIONS.md                # deployment & operations runbook
```

---

## Operations

See [`docs/OPERATIONS.md`](docs/OPERATIONS.md) for deployment, scaling, and day-2 operations (queue/worker management, reindexing, synonym sync, troubleshooting).
