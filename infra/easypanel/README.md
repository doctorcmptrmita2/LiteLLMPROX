# 🚀 EasyPanel Deploy Yol Haritası - CodexFlow.dev

## 📋 Ön Gereksinimler

| Gereksinim | Açıklama |
|------------|----------|
| 🖥️ Server | OVH KS4 veya benzeri (4 core, 16GB RAM, 160GB SSD) |
| 🎛️ Panel | EasyPanel kurulu |
| 🌐 Domain | api.codexflow.dev (DNS ayarlanmış) |
| 🔐 API Keys | Anthropic + OpenRouter |

---

## 🔑 GEREKLİ API KEYS

| Key | Nereden Alınır | Zorunlu |
|-----|----------------|---------|
| `ANTHROPIC_KEY_ORG_A` | https://console.anthropic.com/ | ✅ |
| `OPENROUTER_API_KEY` | https://openrouter.ai/keys | ✅ |

> **Not:** Sadece 2 key yeterli! OpenRouter üzerinden hem Llama FREE hem GPT-4o-mini kullanılıyor.

---

## 📦 ADIM 1: EasyPanel'de Proje Oluştur

1. EasyPanel Dashboard → **New Project**
2. İsim: `codexflow`
3. **Create**

---

## 📦 ADIM 2: Git Repository Bağla

1. Proje içinde → **Add Service** → **App**
2. **Source**: Git Repository
3. **Repository URL**: `https://github.com/KULLANICI/LiteLLMPROX.git`
4. **Branch**: `main`
5. **Build Method**: Dockerfile

---

## 📦 ADIM 3: Servisleri Oluştur

### 3.1 MySQL Database

```
Service Type: Database → MySQL
Name: mysql
Version: 8.0
Root Password: <güçlü-şifre>
Database: codexflow
Username: codexflow
Password: <güçlü-şifre>
```

### 3.2 Redis

```
Service Type: Database → Redis
Name: redis
Version: 7
Password: (boş bırakılabilir)
```

### 3.3 LiteLLM Proxy

```
Service Type: App → Docker Image
Name: litellm
Image: ghcr.io/berriai/litellm:main-latest
Port: 4000

Command: --config /app/config.yaml --port 4000

Volumes:
  Source: ./infra/litellm/proxy_config.yaml
  Target: /app/config.yaml

Environment Variables:
  LITELLM_MASTER_KEY=sk-codexflow-master-key-DEGISTIR
  ANTHROPIC_KEY_ORG_A=sk-ant-api03-xxxxx
  ANTHROPIC_KEY_ORG_B=${ANTHROPIC_KEY_ORG_A}
  ANTHROPIC_KEY_ORG_C=${ANTHROPIC_KEY_ORG_A}
  OPENROUTER_API_KEY=sk-or-v1-xxxxx
  REDIS_HOST=redis
  REDIS_PORT=6379
```

### 3.4 Laravel App (Ana Uygulama)

```
Service Type: App → Git
Name: app
Dockerfile: Dockerfile
Port: 8000

Environment Variables:
  APP_NAME=CodexFlow
  APP_ENV=production
  APP_DEBUG=false
  APP_URL=https://api.codexflow.dev
  
  DB_CONNECTION=mysql
  DB_HOST=mysql
  DB_PORT=3306
  DB_DATABASE=codexflow
  DB_USERNAME=codexflow
  DB_PASSWORD=<mysql-şifresi>
  
  REDIS_HOST=redis
  REDIS_PORT=6379
  
  CACHE_DRIVER=redis
  QUEUE_CONNECTION=redis
  SESSION_DRIVER=redis
  
  LITELLM_BASE_URL=http://litellm:4000
  LITELLM_MASTER_KEY=sk-codexflow-master-key-DEGISTIR
```

### 3.5 Queue Worker

```
Service Type: App → Git (aynı repo)
Name: queue
Dockerfile: Dockerfile
Command: php artisan queue:work --tries=3 --timeout=90

Environment Variables: (app ile aynı)
```

### 3.6 Scheduler

```
Service Type: App → Git (aynı repo)
Name: scheduler
Dockerfile: Dockerfile
Command: sh -c "while true; do php artisan schedule:run --verbose --no-interaction & sleep 60; done"

Environment Variables: (app ile aynı)
```

---

## 📦 ADIM 4: Domain & SSL

1. `app` servisine git → **Domains**
2. **Add Domain**: `api.codexflow.dev`
3. **Enable HTTPS**: ✅
4. **Force HTTPS**: ✅
5. Let's Encrypt otomatik SSL alacak

---

## 📦 ADIM 5: İlk Kurulum Komutları

EasyPanel'de `app` servisinin **Terminal** sekmesine git:

```bash
# 1. App key oluştur
php artisan key:generate --force

# 2. Migration çalıştır
php artisan migrate --force

# 3. Cache temizle
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Storage link oluştur
php artisan storage:link
```

---

## 📦 ADIM 6: Admin Kullanıcı Oluştur

Terminal'de:

```bash
php artisan tinker
```

Tinker içinde:

```php
App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@codexflow.dev',
    'password' => bcrypt('GucluSifre123!'),
    'role' => 'admin',
    'status' => 'active',
    'email_verified_at' => now(),
]);
```

CTRL+D ile çık.

---

## ✅ ADIM 7: Test Et

### LiteLLM Health Check

```bash
curl http://litellm:4000/health
```

Beklenen: `{"status":"healthy"}`

### Laravel Health Check

```bash
curl https://api.codexflow.dev/api/health
```

### LiteLLM Model Test

```bash
curl -X POST http://litellm:4000/v1/chat/completions \
  -H "Authorization: Bearer sk-codexflow-master-key-DEGISTIR" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "cf-fast",
    "messages": [{"role": "user", "content": "Say hello"}]
  }'
```

---

## 🔧 SORUN GİDERME

### Log Kontrol

```bash
# App logları
docker logs codexflow-app -f --tail 100

# LiteLLM logları
docker logs codexflow-litellm -f --tail 100

# Queue logları
docker logs codexflow-queue -f --tail 100

# MySQL logları
docker logs codexflow-mysql -f --tail 100
```

### Yaygın Hatalar

| Hata | Çözüm |
|------|-------|
| `SQLSTATE[HY000] Connection refused` | MySQL container'ı başlamadı, bekle veya restart |
| `Redis connection refused` | Redis container'ı kontrol et |
| `401 Unauthorized` (LiteLLM) | LITELLM_MASTER_KEY doğru mu? |
| `API key invalid` (Anthropic) | ANTHROPIC_KEY_ORG_A doğru mu? |

### Container Restart

```bash
# Tek servis
docker restart codexflow-app

# Tüm servisler
cd /etc/easypanel/projects/codexflow/
docker-compose down
docker-compose up -d
```

---

## 📊 İZLEME & MONITORING

### Günlük Kontroller

1. **LiteLLM Health**: `http://litellm:4000/health/liveliness`
2. **Redis**: `docker exec codexflow-redis redis-cli ping`
3. **MySQL**: `docker exec codexflow-mysql mysqladmin ping`

### Disk Kullanımı

```bash
df -h
docker system df
```

### Log Boyutları

```bash
du -sh /var/lib/docker/containers/*
```

---

## 💾 BACKUP

### Günlük MySQL Backup

```bash
# Manuel backup
docker exec codexflow-mysql mysqldump -u codexflow -p codexflow > backup_$(date +%Y%m%d).sql

# Sıkıştırılmış
docker exec codexflow-mysql mysqldump -u codexflow -p codexflow | gzip > backup_$(date +%Y%m%d).sql.gz
```

### Otomatik Backup (Cron)

```bash
# /etc/cron.daily/codexflow-backup
#!/bin/bash
docker exec codexflow-mysql mysqldump -u codexflow -pSIFRE codexflow | gzip > /backups/codexflow_$(date +%Y%m%d).sql.gz
find /backups -name "*.sql.gz" -mtime +7 -delete
```

---

## 📈 ÖLÇEKLENDİRME

### 50+ Kullanıcı İçin

| Bileşen | Öneri |
|---------|-------|
| Server | OVH KS8'e upgrade |
| MySQL | Read replica ekle |
| Redis | Redis Cluster |
| App | 2. instance + load balancer |

---

## 🎯 HIZLI BAŞVURU

### Servis Durumları

```bash
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
```

### Beklenen Çıktı

```
NAMES                STATUS          PORTS
codexflow-app        Up 2 hours      0.0.0.0:8000->8000/tcp
codexflow-litellm    Up 2 hours      0.0.0.0:4000->4000/tcp
codexflow-mysql      Up 2 hours      3306/tcp
codexflow-redis      Up 2 hours      6379/tcp
codexflow-queue      Up 2 hours      
codexflow-scheduler  Up 2 hours      
```

### Hızlı Debug

```bash
# Tüm logları gör
docker-compose logs -f

# Son 50 satır
docker-compose logs --tail 50

# Sadece hatalar
docker-compose logs | grep -i error
```

---

## ✨ DEPLOY TAMAMLANDI!

Başarılı deploy sonrası:

- 🌐 **API**: https://api.codexflow.dev
- 🔧 **LiteLLM**: http://localhost:4000 (internal)
- 👤 **Admin**: admin@codexflow.dev
- 📊 **Dashboard**: https://api.codexflow.dev/admin

---

*CodexFlow.dev - EasyPanel Deploy Guide v2.0*
