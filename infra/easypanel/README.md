# EasyPanel Deployment Guide for CodexFlow.dev

## Prerequisites

- OVH KS4 server (or equivalent)
- EasyPanel installed
- Domain pointed to server (e.g., api.codexflow.dev)
- SSL certificate (Let's Encrypt)

## Services to Create

### 1. MySQL 8 Database

```yaml
Name: codexflow-db
Image: mysql:8
Persistent Volume: /var/lib/mysql
Environment:
  MYSQL_ROOT_PASSWORD: <strong-password>
  MYSQL_DATABASE: codexflow
  MYSQL_USER: codexflow
  MYSQL_PASSWORD: <strong-password>
```

### 2. Redis 7

```yaml
Name: codexflow-redis
Image: redis:7-alpine
Command: redis-server --requirepass <redis-password>
Persistent Volume: /data
```

### 3. LiteLLM Proxy

```yaml
Name: codexflow-litellm
Image: ghcr.io/berriai/litellm:main-latest
Port: 4000
Command: --config /app/config.yaml --port 4000
Volumes:
  - ./proxy_config.yaml:/app/config.yaml
Environment:
  LITELLM_MASTER_KEY: <master-key>
  ANTHROPIC_KEY_ORG_A: <key>
  ANTHROPIC_KEY_ORG_B: <key>
  ANTHROPIC_KEY_ORG_C: <key>
  OPENAI_API_KEY_PLANNER: <key>
  OPENAI_API_KEY_GRACE: <key>
  OPENROUTER_API_KEY: <key>
  REDIS_HOST: codexflow-redis
  REDIS_PORT: 6379
  REDIS_PASSWORD: <redis-password>
```

### 4. Laravel Application

```yaml
Name: codexflow-app
Build: From repository
Dockerfile: Dockerfile
Port: 8000
Environment: (see env.example)
```

### 5. Laravel Queue Worker

```yaml
Name: codexflow-queue
Build: Same as app
Command: php artisan queue:work --tries=3 --timeout=90
```

### 6. Laravel Scheduler

```yaml
Name: codexflow-scheduler
Build: Same as app
Command: sh -c "while true; do php artisan schedule:run; sleep 60; done"
```

## Environment Variables

All required environment variables are documented in `env.example`.

### Critical Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `LITELLM_MASTER_KEY` | Auth key for LiteLLM proxy | ✅ |
| `ANTHROPIC_KEY_ORG_A/B/C` | 3 Anthropic org keys | ✅ |
| `OPENAI_API_KEY_PLANNER` | OpenAI for decompose | ✅ |
| `OPENAI_API_KEY_GRACE` | OpenAI for grace fallback | ✅ |
| `OPENROUTER_API_KEY` | Llama 405B FREE | ✅ |
| `DB_PASSWORD` | MySQL password | ✅ |
| `REDIS_PASSWORD` | Redis password | ✅ |

## SSL Setup

1. Enable Let's Encrypt in EasyPanel
2. Add domain: `api.codexflow.dev`
3. Force HTTPS redirect

## Post-Deployment

```bash
# Run migrations
docker exec codexflow-app php artisan migrate --force

# Generate app key
docker exec codexflow-app php artisan key:generate

# Clear cache
docker exec codexflow-app php artisan config:cache
docker exec codexflow-app php artisan route:cache

# Create admin user
docker exec -it codexflow-app php artisan tinker
>>> User::create(['name'=>'Admin','email'=>'admin@codexflow.dev','password'=>bcrypt('password'),'role'=>'admin','status'=>'active']);
```

## Monitoring

### Health Checks

- Laravel: `GET /api/health`
- LiteLLM: `GET :4000/health`

### Logs

```bash
# Laravel logs
docker logs codexflow-app -f

# LiteLLM logs
docker logs codexflow-litellm -f

# Queue worker logs
docker logs codexflow-queue -f
```

### Alerts (Telegram Bot)

Configure Telegram bot for:
- Error rate > 10%
- Response time > 5s
- Queue backup > 100 jobs
- Disk usage > 80%

## Backup

```bash
# Database backup
docker exec codexflow-db mysqldump -u codexflow -p codexflow > backup.sql

# Redis backup (optional - mostly cache)
docker exec codexflow-redis redis-cli BGSAVE
```

## Scaling

For more than 50 users:

1. Upgrade to OVH KS8 or similar
2. Add read replica for MySQL
3. Consider Redis Cluster
4. Add second app instance behind load balancer

