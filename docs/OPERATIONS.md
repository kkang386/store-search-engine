# Search Engine — Operations & Deployment Guide

## Table of Contents

1. [Architecture](#architecture)
2. [Infrastructure Reference](#infrastructure-reference)
3. [Building and Pushing Docker Images](#building-and-pushing-docker-images)
4. [Deploying to ECS](#deploying-to-ecs)
5. [SSH Access to Containers](#ssh-access-to-containers)
6. [Import Pipeline](#import-pipeline)
7. [Logs](#logs)
8. [Health Checks](#health-checks)
9. [Cache and Config Management](#cache-and-config-management)
10. [Catalog Reset](#catalog-reset)
11. [Elasticsearch Management](#elasticsearch-management)
12. [Queue Management](#queue-management)
13. [SSL Certificates](#ssl-certificates)
14. [Secrets Management](#secrets-management)
15. [Local Development](#local-development)
16. [Troubleshooting](#troubleshooting)

---

## Architecture

```
Internet
    │
    ▼
Elastic IP → EC2 instance (t3a.large)
    │
    ├── ECS Task: search-app
    │   ├── nginx container   (ports 80/443 → host)
    │   └── app container     (PHP-FPM on 9000, SSH on 2222)
    │
    └── ECS Task: search-worker
        └── worker container  (queue:work — imports, indexing, default)

Private network:
  ├── RDS MySQL (db.t3.medium)
  ├── ElastiCache Redis (cache.t3.micro)
  └── EC2 Elasticsearch (t3.medium, port 9200)

S3:
  └── <IMPORTS_BUCKET>  (import CSV files, FUSE-mounted into containers)
```

**Key design decisions:**
- SSH port 2222 (not 22) — non-privileged, avoids host conflict
- PHP-FPM runs as `www` (uid 1000), not `www-data`
- `clear_env = no` in `www.conf` — ECS env vars reach PHP workers without fastcgi_param
- Import CSV files stored in S3, FUSE-mounted at `storage/app/private/imports/` — survives container restarts and redeployments
- `copy()+delete()` used instead of `rename()` for file archiving — S3 FUSE does not support atomic rename

---

## Infrastructure Reference

| Resource | Value |
|---|---|
| Region | `us-west-2` |
| ECS Cluster | `search-engine-cluster-fargate` |
| App service | `search-app-service-xyz` |
| ECR image (app/worker) | `<AWS_ACCOUNT_ID>.dkr.ecr.us-west-2.amazonaws.com/<ECR_NAMESPACE>/search_engine:latest` |
| ECR image (nginx) | `<AWS_ACCOUNT_ID>.dkr.ecr.us-west-2.amazonaws.com/<ECR_NAMESPACE>/search_engine_nginx:latest` |
| S3 imports bucket | `<IMPORTS_BUCKET>` |
| Secrets Manager secret | `search-engine/prod` |
| Task def (app) | `ecs/task-def-app.json` |
| Task def (worker) | `ecs/task-def-worker.json` |
| IAM roles | `ecsInstanceRole`, `ecsTaskExecutionRole`, `ecsTaskRole` |

---

## Building and Pushing Docker Images

### App / Worker image

```bash
# Authenticate with ECR
aws ecr get-login-password --region us-west-2 | \
  docker login --username AWS --password-stdin \
  <AWS_ACCOUNT_ID>.dkr.ecr.us-west-2.amazonaws.com

# Build for linux/amd64 (required for EC2)
docker build --platform linux/amd64 --no-cache \
  -t <AWS_ACCOUNT_ID>.dkr.ecr.us-west-2.amazonaws.com/<ECR_NAMESPACE>/search_engine:latest .

docker push <AWS_ACCOUNT_ID>.dkr.ecr.us-west-2.amazonaws.com/<ECR_NAMESPACE>/search_engine:latest
```

### Nginx image

```bash
docker build --platform linux/amd64 --no-cache \
  -f docker/nginx/Dockerfile.ecs \
  -t <AWS_ACCOUNT_ID>.dkr.ecr.us-west-2.amazonaws.com/<ECR_NAMESPACE>/search_engine_nginx:latest .

docker push <AWS_ACCOUNT_ID>.dkr.ecr.us-west-2.amazonaws.com/<ECR_NAMESPACE>/search_engine_nginx:latest
```

### Force ECS to pull the new image

ECS caches images on the EC2 host. After pushing, remove the cached image on the EC2 instance so the next task start pulls the latest:

```bash
# SSH into EC2 host
ssh ec2-user@<ec2-ip>

docker rmi <AWS_ACCOUNT_ID>.dkr.ecr.us-west-2.amazonaws.com/<ECR_NAMESPACE>/search_engine:latest
docker rmi <AWS_ACCOUNT_ID>.dkr.ecr.us-west-2.amazonaws.com/<ECR_NAMESPACE>/search_engine_nginx:latest
```

---

## Deploying to ECS

### Register updated task definitions

Required whenever `ecs/task-def-app.json` or `ecs/task-def-worker.json` changes (env vars, memory, capabilities, etc.):

```bash
aws ecs register-task-definition \
  --cli-input-json file://ecs/task-def-app.json \
  --region us-west-2

aws ecs register-task-definition \
  --cli-input-json file://ecs/task-def-worker.json \
  --region us-west-2
```

### Force a new deployment (rolling restart)

```bash
aws ecs update-service \
  --cluster search-engine-cluster-fargate \
  --service search-app-service-260620 \
  --force-new-deployment \
  --region us-west-2
```

If you registered a new task definition revision, also pass `--task-definition search-app:<REVISION>`.

### Verify deployment status

```bash
aws ecs describe-services \
  --cluster search-engine-cluster-fargate \
  --services search-app-service-260620 \
  --region us-west-2 \
  --query 'services[0].{status:status,running:runningCount,desired:desiredCount,deployments:deployments[*].{id:id,status:status,running:runningCount}}'
```

### Run DB migrations after deployment

```bash
# SSH into app container, then:
php artisan migrate --force
```

---

## Scheduler (automatic imports)

The Laravel scheduler runs as a **background `schedule:work` loop inside the app container** —
no cron, no separate service or task. `docker/php/entrypoint.sh` (app mode) starts it as the
`www` user:

```bash
su -p www -c 'while true; do /usr/local/bin/php /var/www/artisan schedule:work >> .../scheduler.log 2>&1; sleep 60; done' &
```

`schedule:work` runs `schedule:run` every minute; the `while`/`sleep 60` restarts it if it
ever exits. It inherits the container environment directly (DB/Redis/APP_KEY), so there is no
env-file or PATH setup to go wrong. The only scheduled job is `import:check-pending` every
15 min (see `routes/console.php`), which queues a `StoreImportJob` for any store with pending
CSVs.

Verify it's running:

```bash
# SSH into app container, then:
pgrep -af schedule:work             # the background scheduler process
tail -f storage/logs/scheduler.log  # scheduler output, refreshed each minute
php artisan schedule:list           # confirm import:check-pending is registered
```

> If the app service ever scales to **more than one task**, every app container runs the
> loop. That is safe here — `withoutOverlapping()` (in `routes/console.php`) and the job's
> `ShouldBeUnique` contract both lock through the shared Redis, so only one run dispatches
> per window. No action needed, but be aware the guard depends on `CACHE_STORE=redis`.

---

## SSH Access to Containers

Containers accept SSH on port **2222** as the `www` user using RSA or Ed25519 public key auth. The authorized key is stored in AWS Secrets Manager under the key `SSH_AUTHORIZED_KEYS` and is injected at container startup.

```bash
# From your Mac
ssh -p 2222 www@search.example.com

# Or to the EC2 host directly (ec2-user, standard port 22)
ssh ec2-user@<ec2-ip>
```

To find a running container ID on the EC2 host:

```bash
docker ps | grep search_engine
```

To exec into a container as root (for diagnostics):

```bash
docker exec -it <container_id> bash
```

### Updating the authorized SSH key

Add or replace the public key in Secrets Manager:

```bash
aws secretsmanager put-secret-value \
  --secret-id search-engine/prod \
  --secret-string "$(aws secretsmanager get-secret-value \
    --secret-id search-engine/prod \
    --query SecretString --output text | \
    python3 -c "import sys,json; d=json.load(sys.stdin); d['SSH_AUTHORIZED_KEYS']='ssh-rsa AAAA... your-key'; print(json.dumps(d))")"
```

Then restart the app task so the new key is picked up at startup.

---

## Import Pipeline

### Overview

1. SCP the three CSV files into the store's import folder inside the app container
2. Trigger the import from the admin UI or CLI
3. The import runs (synchronously via CLI, or via worker queue from the UI)
4. On completion, CSV files are renamed to `*_done_YYYY-MM-DD_HH-MM-SS.csv`

### CSV file format

| File | Required columns |
|---|---|
| `categories.csv` | `category_id, parent_category_id, name, slug, depth, sort_order, is_active` |
| `products.csv` | `sku, name, slug, brand, description, price, inventory, is_active, attributes, images, meta, sales_rank` |
| `product_categories.csv` | `sku, category_id, is_primary` |

`attributes`, `images`, `meta` are JSON-encoded strings or empty.

### Upload files via SCP

```bash
# Replace store-a with the store code
scp -P 2222 categories.csv         www@search.example.com:/var/www/storage/app/private/imports/store-a/categories.csv
scp -P 2222 products.csv           www@search.example.com:/var/www/storage/app/private/imports/store-a/products.csv
scp -P 2222 product_categories.csv www@search.example.com:/var/www/storage/app/private/imports/store-a/product_categories.csv
```

Files are written directly to S3 (`<IMPORTS_BUCKET>/imports/store-a/`) via the FUSE mount — they persist across container restarts and redeployments.

### Run the import

```bash
# Synchronous (shows output, runs in app container — 3GB memory)
php artisan import:products store-a

# Queued (dispatches to worker, visible in admin UI)
php artisan import:products store-a --queue

# All active stores
php artisan import:products
```

### Check import status

```bash
php artisan tinker --execute="
App\Models\ImportLog::latest()->take(5)
  ->get(['id','store_id','status','started_at','completed_at','products_created','categories_created','errors'])
  ->each(function(\$l) {
    echo \$l->id.' store='.\$l->store_id.' status='.\$l->status
      .' products='.\$l->products_created.' errors='.json_encode(\$l->errors).PHP_EOL;
  });
"
```

### Clear stuck running logs

If a container restart interrupted an import, logs may be stuck as `running`, which blocks new imports:

```bash
php artisan tinker --execute="
App\Models\ImportLog::where('status','running')->update([
    'status'       => 'failed',
    'completed_at' => now(),
    'errors'       => ['Interrupted by container restart'],
]);
echo 'done';
"
```

### S3 imports bucket

```bash
# List files for a store
aws s3 ls s3://<IMPORTS_BUCKET>/imports/store-a/

# Download a file for inspection
aws s3 cp s3://<IMPORTS_BUCKET>/imports/store-a/products.csv /tmp/products.csv
```

---

## Logs

### CloudWatch (from your Mac)

```bash
# App container (PHP-FPM / Laravel)
aws logs tail /ecs/search-app \
  --log-stream-name-prefix app \
  --region us-west-2 --follow

# Nginx access log
aws logs tail /ecs/search-app \
  --log-stream-name-prefix nginx \
  --region us-west-2 --follow

# Worker
aws logs tail /ecs/search-worker \
  --log-stream-name-prefix worker \
  --region us-west-2 --follow
```

### From inside the container

```bash
# Laravel application log
tail -f storage/logs/laravel.log

# Live search requests
docker logs <nginx_container_id> -f

# Filter to search API hits only
docker logs <nginx_container_id> -f 2>&1 | grep "/api/search"
```

---

## Health Checks

```bash
# PHP-FPM
docker exec <app_container_id> php-fpm -t

# Elasticsearch
curl -s http://$ELASTICSEARCH_HOST:9200/_cluster/health | python3 -m json.tool

# Product count in Elasticsearch vs MySQL
curl -s http://$ELASTICSEARCH_HOST:9200/products/_count
php artisan tinker --execute="echo App\Models\Product::where('is_active',true)->count();"

# Redis queue depth
redis-cli -h $REDIS_HOST -n 3 LLEN "search_engine_database_queues:imports"
redis-cli -h $REDIS_HOST -n 3 LLEN "search_engine_database_queues:indexing"

# S3 FUSE mount is working
ls /var/www/storage/app/private/imports/
```

---

## Cache and Config Management

```bash
# Clear Laravel config cache (required after env var changes without redeployment)
php artisan config:clear

# Clear application cache (search results cache)
php artisan cache:clear

# Clear route cache
php artisan route:clear

# Clear all caches at once
php artisan optimize:clear
```

---

## Catalog Reset

Wipes all product and category data from MySQL and rebuilds an empty Elasticsearch index. Stores and import logs are not touched.

```bash
# Full reset (DB + ES)
php artisan catalog:reset --force

# DB only (leave ES untouched)
php artisan catalog:reset --force --db-only

# ES only (recreate empty index, leave DB untouched)
php artisan catalog:reset --force --es-only
```

Tables wiped: `products`, `categories`, `product_categories`, `store_products`, `store_categories`, `import_logs`.

After a reset, re-import from the CSV files:

```bash
php artisan import:products store-a
```

---

## Elasticsearch Management

```bash
# Index document count
curl -s http://$ELASTICSEARCH_HOST:9200/products/_count

# Reindex all products from MySQL (stops worker first to avoid conflicts)
php artisan search:reindex

# Check disk usage
curl -s "http://$ELASTICSEARCH_HOST:9200/_cat/allocation?v&h=node,disk.used,disk.avail,disk.percent,shards"

# Check active indexing operations
curl -s "http://$ELASTICSEARCH_HOST:9200/_cat/thread_pool/write?v&h=node_name,active,queue,rejected"

# Test search from CLI
curl -s "http://127.0.0.1/api/search?q=airsoft&store_id=4" | python3 -m json.tool
```

---

## Queue Management

```bash
# Check queue depths
redis-cli -h $REDIS_HOST -n 3 LLEN "search_engine_database_queues:imports"
redis-cli -h $REDIS_HOST -n 3 LLEN "search_engine_database_queues:indexing"
redis-cli -h $REDIS_HOST -n 3 KEYS "search_engine_database_queues:*"

# List failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Flush failed jobs table
php artisan queue:flush

# Clear a specific queue
php artisan queue:clear redis --queue=imports
php artisan queue:clear redis --queue=indexing
```

---

## SSL Certificates

Certificates are stored in AWS Secrets Manager and mounted into the nginx container at startup. Three keys are used:

| Secret key | Content |
|---|---|
| `SSL_CERT` | Domain certificate (`.crt`) |
| `SSL_CA_BUNDLE_1` | CA bundle part 1 |
| `SSL_CA_BUNDLE_2` | CA bundle part 2 |
| `SSL_KEY` | Private key (`.key` or `_key.txt`) |

To update certificates, update the secret values in Secrets Manager and restart the nginx container (no image rebuild required):

```bash
# On the EC2 host
docker restart <nginx_container_id>
```

---

## Secrets Management

All sensitive values are stored in AWS Secrets Manager under `search-engine/prod`.

| Key | Description |
|---|---|
| `APP_KEY` | Laravel application key |
| `DB_HOST` | RDS endpoint |
| `DB_USERNAME` | Database username |
| `DB_PASSWORD` | Database password |
| `ELASTICSEARCH_HOST` | Elasticsearch private IP/hostname |
| `REDIS_HOST` | ElastiCache endpoint |
| `SSH_AUTHORIZED_KEYS` | Public key(s) for www user SSH access |
| `SSL_CERT`, `SSL_KEY`, `SSL_CA_BUNDLE_1`, `SSL_CA_BUNDLE_2` | TLS certificate files |

Update a single key without replacing the rest:

```bash
# Fetch current secret, update one key, write back
SECRET=$(aws secretsmanager get-secret-value \
  --secret-id search-engine/prod \
  --query SecretString --output text)

UPDATED=$(echo "$SECRET" | python3 -c "
import sys, json
d = json.load(sys.stdin)
d['SSH_AUTHORIZED_KEYS'] = 'ssh-rsa AAAA...'
print(json.dumps(d))
")

aws secretsmanager put-secret-value \
  --secret-id search-engine/prod \
  --secret-string "$UPDATED"
```

---

## Local Development

Local dev uses Docker Compose and a local filesystem (no S3, no FUSE mount).

```bash
# Start all services
docker compose -f docker-compose.yml up -d

# Stop
docker compose -f docker-compose.yml down

# Rebuild after code changes
docker compose -f docker-compose.yml up -d --build app worker

# Shell into app container
docker compose -f docker-compose.yml exec app bash

# Run migrations
docker compose -f docker-compose.yml exec app php artisan migrate --force

# Reindex
docker compose -f docker-compose.yml exec app php artisan search:reindex

# Run import (local CSV files in storage/app/private/imports/<store_code>/)
docker compose -f docker-compose.yml exec app php artisan import:products store-a
```

Import files in local dev go to `storage/app/private/imports/<store_code>/` on the host filesystem (bind-mounted). No SCP needed — copy files there directly.

---

## Troubleshooting

### Container exits on startup (TaskFailedToStart)

Check CloudWatch logs for the failing container:

```bash
aws logs tail /ecs/search-app --log-stream-name-prefix app --region us-west-2 --since 30m
```

Common causes:
- Missing or invalid secret in Secrets Manager (check `APP_KEY`, `DB_HOST`, etc.)
- `config:cache` baked into the image with wrong values — entrypoint runs `config:clear` to prevent this
- S3 FUSE mount failing — check that `/dev/fuse` exists on the EC2 host (`ls -la /dev/fuse`) and the `fuse` module is loaded (`cat /etc/modules-load.d/fuse.conf`)

### `/dev/fuse` not available inside container

```bash
# On EC2 host — load the module
sudo modprobe fuse
echo "fuse" | sudo tee /etc/modules-load.d/fuse.conf

# Verify
ls -la /dev/fuse
```

Then verify `linuxParameters` with `devices` and `SYS_ADMIN` capability are in the registered task definition revision and that the service is using that revision.

### S3 imports not visible / mount not working

```bash
# Inside app container — check if mount succeeded
ls /var/www/storage/app/private/imports/

# If empty or missing, check entrypoint log output
docker logs <app_container_id> 2>&1 | head -20

# Test S3 connectivity
php artisan tinker --execute="
echo Storage::disk('imports')->exists('imports/') ? 'mounted' : 'empty (ok)';
"
```

### Import stuck as running / new import blocked

```bash
php artisan tinker --execute="
App\Models\ImportLog::where('status','running')->update([
    'status'       => 'failed',
    'completed_at' => now(),
    'errors'       => ['Interrupted by container restart'],
]);
echo 'done';
"
```

### Import completes with 0 products

Check the PHP memory limit — the default 128M is not enough for large CSV files:

```bash
php -r "echo ini_get('memory_limit');"
# Should be 512M (set in docker/php/local.ini, baked into image)

# Temporary workaround if image not yet rebuilt
php -d memory_limit=1G artisan import:products store-a
```

### Elasticsearch 403 from app container

The `ecsTaskRole` is missing S3 or Elasticsearch permissions. Check attached policies:

```bash
aws iam list-role-policies --role-name ecsTaskRole
```

### Search not returning results after import

Products are indexed during import. If the index is empty after a successful import:

```bash
# Check ES document count
curl -s http://$ELASTICSEARCH_HOST:9200/products/_count

# Check failed indexing jobs
php artisan queue:failed

# Manually reindex
php artisan search:reindex
```

### Admin login fails

The user password in the seeder is plain text; it must be hashed. Fix directly:

```bash
php artisan tinker --execute="
\$u = App\Models\User::where('email','admin@example.com')->first();
\$u->password = Hash::make('your-password');
\$u->save();
"
```
