# 📋 CODEXFLOW.DEV — PROJE MASTER PLANI

> **Versiyon:** 1.0  
> **Tarih:** 27 Aralık 2025  
> **Hazırlayan:** Claude Opus 4.5  
> **Durum:** Planlama Aşaması

---

## 📑 İÇİNDEKİLER

1. [Yönetici Özeti](#1-yönetici-özeti)
2. [MEGA_PROMPT Analizi](#2-mega_prompt-analizi)
3. [Tespit Edilen Sorunlar & Tutarsızlıklar](#3-tespit-edilen-sorunlar--tutarsızlıklar)
4. [Revize Edilmiş Mimari](#4-revize-edilmiş-mimari)
5. [Detaylı Teknik Spesifikasyon](#5-detaylı-teknik-spesifikasyon)
6. [Maliyet & Fiyatlandırma Modeli](#6-maliyet--fiyatlandırma-modeli)
7. [Risk Analizi](#7-risk-analizi)
8. [Uygulama Yol Haritası](#8-uygulama-yol-haritası)
9. [Sonuç & Öneriler](#9-sonuç--öneriler)

---

## 1. YÖNETİCİ ÖZETİ

### 🎯 Proje Amacı
CodexFlow.dev, Cursor AI kullanıcılarına TL bazlı, sabit fiyatlı LLM gateway hizmeti sunacak. 3 Anthropic org API key havuzu + Grace Lane (OpenAI fallback) ile kesintisiz kodlama deneyimi sağlayacak.

### ✅ Genel Değerlendirme

| Kriter | Değerlendirme | Puan |
|--------|---------------|------|
| Teknik Fizibilite | Yapılabilir | 8/10 |
| İş Modeli | Kârlı olabilir | 7/10 |
| Prompt Kalitesi | İyi ama eksikler var | 7/10 |
| Mimari Tasarım | Temiz ama tutarsızlıklar var | 7/10 |
| Müşteri Değeri | Yüksek | 9/10 |

### ⚠️ Kritik Bulgular

1. **PART 0 ile PART 1 arasında tutarsızlık:** `cf-grace` LiteLLM config'de tanımlı DEĞİL
2. **Token counting mekanizması belirsiz:** Maliyet hesabı nasıl yapılacak?
3. **Streaming desteği hiç yok:** Modern LLM kullanımının %80'i streaming
4. **Fallback mantığı karışık:** LiteLLM vs Laravel - kim karar veriyor?
5. **Model isimleri güncelliği:** Anthropic model naming convention değişebilir

---

## 2. MEGA_PROMPT ANALİZİ

### PART 0: LiteLLM Proxy Config

**Analiz:**
```yaml
# Mevcut Durum
cf-fast:     3 key × Claude Haiku 4.5    ✅
cf-deep:     3 key × Claude Sonnet 4.5   ✅
cf-deep-fallback: 3 key × Claude Sonnet 4 ✅
cf-grace:    ???                          ❌ EKSİK!
```

**Sorunlar:**
- `cf-grace` tanımlanmamış - Laravel bu alias'ı çağırdığında 404 alacak
- Fallback zinciri `cf-deep -> cf-deep-fallback -> cf-fast` ama Grace nerede?
- `simple-shuffle` RPM/TPM set edildiğinde optimal değil, `usage-based-routing` daha iyi

**Düzeltme Gerekli:**
```yaml
# Eklenmesi gereken cf-grace bloğu
- model_name: cf-grace
  litellm_params:
    model: openai/gpt-4o-mini  # veya gpt-5-nano varsa
    api_key: os.environ/OPENAI_API_KEY_GRACE
    timeout: 60
    rpm: 500
    tpm: 200000
```

---

### PART 1 v2: Foundations

**Analiz:**

| Bileşen | Durum | Yorum |
|---------|-------|-------|
| users tablosu | ✅ | role enum doğru |
| subscriptions | ✅ | status enum doğru |
| projects | ⚠️ | plan_code user'da mı project'te mi? Çift tanım |
| project_api_keys | ✅ | key_hash yaklaşımı doğru |
| quota_monthly | ⚠️ | grace quota aylık mı günlük mü? Tutarsız |
| quota_daily | ✅ | grace_tokens doğru |
| llm_requests | ⚠️ | model_used nasıl bilinecek? LiteLLM bunu döndürmüyor |
| usage_daily_aggregates | ✅ | project_id + date unique doğru |

**Sorunlar:**

1. **plan_code Çoğaltması:**
   - `subscriptions.plan_code` var
   - `projects.plan_code` da var
   - Hangisi geçerli? Subscription'dan miras mı alacak?
   
2. **Grace Quota Tutarsızlığı:**
   - `quota_monthly`: grace yok
   - `quota_daily`: grace_tokens + grace_req var
   - Prompt'ta: "grace daily: req 40, tokens 120_000"
   - Bu mantıklı (grace sadece günlük) ama explicit belirtilmeli

3. **model_used Alanı:**
   - LiteLLM proxy "hangi deployment kullanıldı" bilgisini response header'da dönmüyor (standart olarak)
   - Bu bilgiyi almak için LiteLLM'de `litellm_settings.set_verbose: true` veya callback gerekiyor
   - Ya da sadece tier kaydedip deployment detayını atlayacağız

4. **cost_decimal Hesabı:**
   - Token başına maliyet sabit mi? Model bazlı mı?
   - LiteLLM `/spend/logs` endpoint'i mi kullanılacak?
   - Yoksa Laravel'de manuel hesap mı?

---

### PART 2: Auth + API Keys + Middleware

**Analiz:**

| Bileşen | Durum | Yorum |
|---------|-------|-------|
| Sanctum auth | ✅ | Doğru tercih |
| API key format | ✅ | "cf_" + 32-48 char iyi |
| Key hashing | ✅ | password-like yaklaşım doğru |
| RequestIdMiddleware | ✅ | X-Request-Id forwarding önemli |
| AuthenticateProjectApiKey | ⚠️ | Timing-safe compare kritik |
| Rate limiting | ❌ | PART 2'de yok, nerede? |

**Sorunlar:**

1. **Key Validation Performansı:**
   - Her request'te TÜM key'leri hash'leyip karşılaştırmak pahalı
   - Çözüm: Key'in ilk 8 karakterini plain sakla, önce onu filtrele, sonra hash compare

2. **Rate Limiting Eksik:**
   - PART 2'de rate limiting middleware yok
   - PART 3'te "overload => 429" var ama middleware olarak değil
   - Laravel native rate limiting mi? Redis-based mi?

3. **Suspended User Check:**
   - Auth sonrası her request'te user.status kontrolü gerekiyor
   - Middleware chain'de nerede?

---

### PART 3: Gateway Core

**Analiz:**

| Bileşen | Durum | Yorum |
|---------|-------|-------|
| Tier routing | ✅ | x-quality header yaklaşımı iyi |
| Admission control | ✅ | Token limit'leri mantıklı |
| Quota atomic decrement | ✅ | DB transaction doğru |
| Deterministic cache | ⚠️ | temp=0 + stream=false kontrolü iyi ama key generation? |
| LiteLLM client | ⚠️ | Error mapping eksik detay |
| Retry/failover | ❌ | "switch deployment" Laravel'de nasıl? |
| Telemetry | ✅ | llm_requests logging iyi |

**Sorunlar:**

1. **STREAMING YOK!** 🚨
   - Tüm prompt "stream==false" varsayıyor
   - Cursor AI varsayılan olarak streaming kullanır
   - Streaming olmadan UX çok kötü olur (uzun bekleme)
   - **KRİTİK:** Streaming desteği şart

2. **"Switch Deployment" Karışıklığı:**
   - Prompt diyor: "retry must switch deployment within tier"
   - Ama deployment seçimi LiteLLM'de yapılıyor
   - Laravel sadece `cf-fast` diyor, hangisine gittiğini bilmiyor
   - **Çözüm:** Bu mantık LiteLLM'e bırakılmalı, Laravel karışmamalı

3. **Quota Race Condition:**
   - "Atomic decrement using DB transactions" iyi ama...
   - SELECT FOR UPDATE mu? Optimistic locking mi?
   - Yüksek concurrency'de deadlock riski var
   - **Öneri:** Redis DECRBY ile atomic işlem, DB'ye async sync

4. **Cache Key Generation:**
   - "sha256(version + normalized payload + tier)"
   - "normalized payload" nasıl? Message order? System prompt dahil mi?
   - Tool calls varsa nasıl normalize edilecek?

5. **Token Counting (Input):**
   - Request'i göndermeden önce input token'ı nasıl bileceğiz?
   - tiktoken kullanılacak mı? PHP'de performans sorunu olabilir
   - **Öneri:** Karakter sayısı / 4 yaklaşık tahmin, sonra gerçek değerle düzelt

---

### PART 4: Usage + Jobs + Scheduler

**Analiz:**

| Bileşen | Durum | Yorum |
|---------|-------|-------|
| Usage endpoints | ✅ | from/to parametreleri iyi |
| AggregateUsageDailyJob | ✅ | Nightly aggregation mantıklı |
| PruneLlmRequestsJob | ✅ | 21 gün retention iyi |
| RefreshDeploymentHealthJob | ⚠️ | "compute recent success/429/timeout" - hangi deployment? |
| Scheduler | ✅ | Laravel scheduler yeterli |
| Admin health endpoint | ⚠️ | "disabled deployments cache state" - nasıl? |

**Sorunlar:**

1. **Deployment Health Paradoksu:**
   - Laravel hangi deployment'a gittiğini bilmiyor (LiteLLM seçiyor)
   - O zaman "deployment health" nasıl hesaplanacak?
   - **Çözüm:** LiteLLM `/health` endpoint'ini kullan veya tier-based health tut

2. **Cooldown Mekanizması:**
   - Prompt'ta "mark deployment disabled in cache (cooldown)"
   - Ama LiteLLM zaten `allowed_fails` + `cooldown_time` destekliyor
   - Çift mekanizma karışıklık yaratır
   - **Öneri:** LiteLLM'e bırak, Laravel sadece tier-level health izlesin

---

### PART 5: UI + Tests + Infra

**Analiz:**

| Bileşen | Durum | Yorum |
|---------|-------|-------|
| Landing page | ✅ | Tailwind iyi tercih |
| Customer dashboard | ⚠️ | Quota meters - real-time mi? |
| Admin dashboard | ✅ | Temel gereksinimler tamam |
| Feature tests | ✅ | Mock LiteLLM yaklaşımı doğru |
| LiteLLM config | ⚠️ | PART 0 ile çakışma var |
| EasyPanel README | ✅ | Deployment dokümantasyonu önemli |

**Sorunlar:**

1. **Real-time Quota Display:**
   - Quota meters güncel veriyi nasıl gösterecek?
   - Her sayfa yüklemesinde DB sorgusu mu?
   - **Öneri:** Redis'te quota cache, 1 dakika TTL

2. **Test Coverage:**
   - Happy path testleri var ama edge case'ler eksik
   - Concurrent request testi yok
   - Load test planı yok

---

## 3. TESPİT EDİLEN SORUNLAR & TUTARSIZLIKLAR

### 🔴 KRİTİK (Projeyi Engelleyebilir)

| # | Sorun | Etki | Çözüm |
|---|-------|------|-------|
| 1 | **cf-grace LiteLLM'de yok** | Grace Lane çalışmaz | PART 0'a ekle |
| 2 | **Streaming desteği yok** | UX felaket olur | SSE/streaming endpoint ekle |
| 3 | **Token counting belirsiz** | Quota yönetimi çalışmaz | tiktoken veya LiteLLM response'dan al |
| 4 | **Deployment switching Laravel'de** | LiteLLM zaten yapıyor, çakışma | Laravel'den kaldır |

### 🟡 ÖNEMLİ (Performans/Güvenilirlik)

| # | Sorun | Etki | Çözüm |
|---|-------|------|-------|
| 5 | plan_code çoğaltması | Data inconsistency | Sadece subscription'da tut |
| 6 | Quota race condition riski | Aşım veya deadlock | Redis DECRBY kullan |
| 7 | Key lookup performansı | Yavaş auth | Prefix-based filtering |
| 8 | model_used bilinmiyor | Eksik telemetry | LiteLLM callback veya header |
| 9 | Maliyet hesabı belirsiz | Yanlış billing | Tier-based sabit fiyat veya LiteLLM spend API |

### 🟢 İYİLEŞTİRME (Nice to Have)

| # | Sorun | Etki | Çözüm |
|---|-------|------|-------|
| 10 | Rate limiting middleware yok | DDoS riski | Laravel throttle + Redis |
| 11 | Webhook/callback yok | Entegrasyon zorluğu | Usage alert webhook ekle |
| 12 | Multi-tier pricing yok | Gelir optimizasyonu | 3 plan tier ekle |

---

## 4. REVİZE EDİLMİŞ MİMARİ

### 4.1 Sistem Mimarisi (Güncellenmiş)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           CODEXFLOW.DEV v2                               │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────┐     ┌──────────────────────────────────────────────┐   │
│  │   CURSOR    │────▶│              LARAVEL 12 GATEWAY              │   │
│  │   AI IDE    │     │                                              │   │
│  └─────────────┘     │  ┌─────────────────────────────────────────┐ │   │
│                      │  │         MIDDLEWARE CHAIN                 │ │   │
│                      │  │  1. RateLimitMiddleware (Redis)          │ │   │
│                      │  │  2. RequestIdMiddleware                  │ │   │
│                      │  │  3. AuthenticateProjectApiKey            │ │   │
│                      │  │  4. CheckUserStatus                      │ │   │
│                      │  │  5. QuotaCheckMiddleware                 │ │   │
│                      │  └─────────────────────────────────────────┘ │   │
│                      │                     │                         │   │
│                      │                     ▼                         │   │
│                      │  ┌─────────────────────────────────────────┐ │   │
│                      │  │           GATEWAY CONTROLLER            │ │   │
│                      │  │  • Tier Selection (fast/deep/grace)     │ │   │
│                      │  │  • Admission Control (token clamp)      │ │   │
│                      │  │  • Cache Check (deterministic)          │ │   │
│                      │  │  • Streaming Handler (SSE)              │ │   │
│                      │  └─────────────────────────────────────────┘ │   │
│                      │                     │                         │   │
│                      └─────────────────────┼─────────────────────────┘   │
│                                            │                             │
│                                            ▼                             │
│  ┌─────────────────────────────────────────────────────────────────────┐ │
│  │                        LITELLM PROXY                                │ │
│  │                                                                      │ │
│  │   ┌───────────────┐  ┌───────────────┐  ┌───────────────┐           │ │
│  │   │   cf-fast     │  │   cf-deep     │  │   cf-grace    │           │ │
│  │   │  Haiku 4.5    │  │  Sonnet 4.5   │  │  GPT-4o-mini  │           │ │
│  │   │  3 API Keys   │  │  3 API Keys   │  │  1 API Key    │           │ │
│  │   │  Pool+LB      │  │  Pool+LB      │  │  Fallback     │           │ │
│  │   └───────────────┘  └───────────────┘  └───────────────┘           │ │
│  │                             │                                        │ │
│  │                             ▼                                        │ │
│  │   ┌─────────────────────────────────────────────────────────────┐   │ │
│  │   │  LiteLLM Internal: Retry + Fallback + Cooldown + Cache      │   │ │
│  │   │  cf-deep → cf-deep-fallback → cf-fast (LiteLLM yönetir)     │   │ │
│  │   └─────────────────────────────────────────────────────────────┘   │ │
│  │                                                                      │ │
│  └─────────────────────────────────────────────────────────────────────┘ │
│                                            │                             │
│                                            ▼                             │
│  ┌────────────────┐  ┌─────────────────┐  ┌─────────────────────────┐   │
│  │   ANTHROPIC    │  │     OPENAI      │  │   REDIS + MYSQL         │   │
│  │   3 Org Keys   │  │   Grace Key     │  │   Cache + Quota + Log   │   │
│  └────────────────┘  └─────────────────┘  └─────────────────────────┘   │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### 4.2 Veri Akış Diyagramı (Streaming Dahil)

```
┌──────────────────────────────────────────────────────────────────────────┐
│                         REQUEST FLOW (Streaming)                          │
└──────────────────────────────────────────────────────────────────────────┘

Client                Laravel Gateway              LiteLLM              Provider
  │                        │                          │                    │
  │  POST /v1/chat/completions (stream=true)          │                    │
  │  Authorization: Bearer cf_xxx                     │                    │
  │───────────────────────▶│                          │                    │
  │                        │                          │                    │
  │                        │ 1. Auth Key              │                    │
  │                        │ 2. Check User Status     │                    │
  │                        │ 3. Check Quota           │                    │
  │                        │ 4. Select Tier           │                    │
  │                        │ 5. Clamp Tokens          │                    │
  │                        │                          │                    │
  │                        │  POST /v1/chat/completions                    │
  │                        │  model: cf-fast          │                    │
  │                        │  stream: true            │                    │
  │                        │─────────────────────────▶│                    │
  │                        │                          │                    │
  │                        │                          │  API Call          │
  │                        │                          │───────────────────▶│
  │                        │                          │                    │
  │                        │                          │  SSE Stream        │
  │                        │                          │◀───────────────────│
  │                        │                          │                    │
  │                        │  SSE: data: {...}        │                    │
  │                        │◀─────────────────────────│                    │
  │  SSE: data: {...}      │                          │                    │
  │◀───────────────────────│                          │                    │
  │  ...                   │                          │                    │
  │  SSE: data: [DONE]     │                          │                    │
  │◀───────────────────────│                          │                    │
  │                        │                          │                    │
  │                        │ 6. Parse Final Usage     │                    │
  │                        │ 7. Decrement Quota       │                    │
  │                        │ 8. Log Telemetry         │                    │
  │                        │                          │                    │
```

### 4.3 Quota Flow (Atomic)

```
┌──────────────────────────────────────────────────────────────────────────┐
│                           QUOTA MANAGEMENT                                │
└──────────────────────────────────────────────────────────────────────────┘

                    ┌─────────────────────────────────────┐
                    │         QUOTA CHECK FLOW            │
                    └─────────────────────────────────────┘
                                     │
                                     ▼
                    ┌─────────────────────────────────────┐
                    │  1. Check Monthly Quota (fast/deep)  │
                    │     Redis: quota:monthly:{user}:{ym} │
                    │     If miss → Load from DB           │
                    └─────────────────────────────────────┘
                                     │
                         ┌───────────┴───────────┐
                         │                       │
                         ▼                       ▼
              ┌─────────────────┐     ┌─────────────────┐
              │  Monthly OK     │     │  Monthly EXHAUSTED│
              │  → Check Daily  │     │  → Try Lower Tier │
              └─────────────────┘     │  → Or Grace       │
                         │            └─────────────────┘
                         ▼
              ┌─────────────────────────────────────┐
              │  2. Check Daily Safety Cap           │
              │     Redis: quota:daily:{user}:{date} │
              └─────────────────────────────────────┘
                         │
                         ▼
              ┌─────────────────────────────────────┐
              │  3. Pre-authorize (Estimated Tokens) │
              │     DECRBY estimated_input_tokens    │
              │     If < 0 → Rollback + Reject       │
              └─────────────────────────────────────┘
                         │
                         ▼
              ┌─────────────────────────────────────┐
              │           CALL LITELLM              │
              └─────────────────────────────────────┘
                         │
                         ▼
              ┌─────────────────────────────────────┐
              │  4. Post-adjust (Actual Tokens)      │
              │     Delta = actual - estimated       │
              │     DECRBY delta (can be negative)   │
              │     Sync to DB async (queue)         │
              └─────────────────────────────────────┘
```

---

## 5. DETAYLI TEKNİK SPESİFİKASYON

### 5.1 Revize Edilmiş Database Schema

```sql
-- =============================================
-- USERS
-- =============================================
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') DEFAULT 'customer',
    status ENUM('active', 'suspended', 'pending') DEFAULT 'pending',
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_status (status)
);

-- =============================================
-- SUBSCRIPTIONS (plan bilgisi burada, tek kaynak)
-- =============================================
CREATE TABLE subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    plan_code VARCHAR(50) NOT NULL DEFAULT 'pro_1000_try',
    starts_at DATE NOT NULL,
    ends_at DATE NOT NULL,
    status ENUM('active', 'paused', 'canceled', 'expired') DEFAULT 'active',
    payment_provider VARCHAR(50) NULL, -- iyzico, stripe
    payment_ref VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_ends_at (ends_at)
);

-- =============================================
-- PROJECTS (plan_code KALDIRILDI - subscription'dan inherit)
-- =============================================
CREATE TABLE projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    status ENUM('active', 'paused', 'deleted') DEFAULT 'active',
    settings JSON NULL, -- proje bazlı ayarlar (ileride)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_slug (user_id, slug),
    INDEX idx_status (status)
);

-- =============================================
-- PROJECT API KEYS (prefix eklendi performans için)
-- =============================================
CREATE TABLE project_api_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    key_prefix VARCHAR(12) NOT NULL, -- ilk 12 karakter (cf_xxxxxxxx)
    key_hash VARCHAR(255) NOT NULL,  -- bcrypt hash
    last_used_at TIMESTAMP NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_prefix (key_prefix),
    INDEX idx_revoked (revoked_at)
);

-- =============================================
-- QUOTA MONTHLY (user bazlı, grace YOK - günlük)
-- =============================================
CREATE TABLE quota_monthly (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    month CHAR(7) NOT NULL, -- YYYY-MM
    
    -- FAST tier
    fast_input_tokens BIGINT DEFAULT 0,
    fast_output_tokens BIGINT DEFAULT 0,
    fast_requests INT DEFAULT 0,
    
    -- DEEP tier
    deep_input_tokens BIGINT DEFAULT 0,
    deep_output_tokens BIGINT DEFAULT 0,
    deep_requests INT DEFAULT 0,
    
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_month (user_id, month)
);

-- =============================================
-- QUOTA DAILY (günlük güvenlik limiti + grace)
-- =============================================
CREATE TABLE quota_daily (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    
    -- FAST tier
    fast_tokens BIGINT DEFAULT 0,
    fast_requests INT DEFAULT 0,
    
    -- DEEP tier
    deep_tokens BIGINT DEFAULT 0,
    deep_requests INT DEFAULT 0,
    
    -- GRACE tier (sadece günlük)
    grace_tokens BIGINT DEFAULT 0,
    grace_requests INT DEFAULT 0,
    
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_date (user_id, date)
);

-- =============================================
-- LLM REQUESTS (telemetry - model_used opsiyonel)
-- =============================================
CREATE TABLE llm_requests (
    id CHAR(36) PRIMARY KEY, -- UUID
    user_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    api_key_id BIGINT UNSIGNED NULL,
    
    request_id VARCHAR(64) NOT NULL, -- X-Request-Id
    tier ENUM('fast', 'deep', 'grace') NOT NULL,
    
    -- Model bilgileri
    model_requested VARCHAR(100) NULL, -- client'ın istediği
    model_alias VARCHAR(50) NULL,      -- cf-fast, cf-deep, cf-grace
    
    -- Token kullanımı
    prompt_tokens INT UNSIGNED DEFAULT 0,
    completion_tokens INT UNSIGNED DEFAULT 0,
    total_tokens INT UNSIGNED DEFAULT 0,
    
    -- Maliyet (tier-based hesap)
    cost_usd DECIMAL(10, 6) NULL,
    
    -- Performans
    latency_ms INT UNSIGNED NULL,
    time_to_first_token_ms INT UNSIGNED NULL, -- streaming için
    is_cached BOOLEAN DEFAULT FALSE,
    is_streaming BOOLEAN DEFAULT FALSE,
    
    -- Durum
    status_code SMALLINT UNSIGNED NULL,
    error_type VARCHAR(50) NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_project_created (project_id, created_at),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_tier (tier),
    INDEX idx_error (error_type)
);

-- =============================================
-- USAGE DAILY AGGREGATES
-- =============================================
CREATE TABLE usage_daily_aggregates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    
    -- Tier breakdown
    fast_tokens BIGINT DEFAULT 0,
    fast_requests INT DEFAULT 0,
    fast_cost_usd DECIMAL(10, 4) DEFAULT 0,
    
    deep_tokens BIGINT DEFAULT 0,
    deep_requests INT DEFAULT 0,
    deep_cost_usd DECIMAL(10, 4) DEFAULT 0,
    
    grace_tokens BIGINT DEFAULT 0,
    grace_requests INT DEFAULT 0,
    grace_cost_usd DECIMAL(10, 4) DEFAULT 0,
    
    -- Totals
    total_tokens BIGINT DEFAULT 0,
    total_requests INT DEFAULT 0,
    total_cost_usd DECIMAL(10, 4) DEFAULT 0,
    
    -- Cache stats
    cache_hits INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY uk_project_date (project_id, date)
);

-- =============================================
-- DEPLOYMENT HEALTH (opsiyonel - LiteLLM'den alınabilir)
-- =============================================
CREATE TABLE deployment_health (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tier VARCHAR(20) NOT NULL, -- fast, deep, grace
    recorded_at TIMESTAMP NOT NULL,
    
    success_count INT DEFAULT 0,
    error_count INT DEFAULT 0,
    timeout_count INT DEFAULT 0,
    rate_limit_count INT DEFAULT 0,
    
    avg_latency_ms INT NULL,
    p95_latency_ms INT NULL,
    
    INDEX idx_tier_recorded (tier, recorded_at)
);
```

### 5.2 Revize LiteLLM Config (PART 0 Düzeltilmiş)

```yaml
# infra/litellm/proxy_config.yaml
# CODEXFLOW.DEV - Production Ready

model_list:
  # =========================
  # FAST POOL (Haiku) x3 keys
  # =========================
  - model_name: cf-fast
    litellm_params:
      model: anthropic/claude-3-5-haiku-latest
      api_key: os.environ/ANTHROPIC_KEY_ORG_A
      timeout: 60
      rpm: 120
      tpm: 50000

  - model_name: cf-fast
    litellm_params:
      model: anthropic/claude-3-5-haiku-latest
      api_key: os.environ/ANTHROPIC_KEY_ORG_B
      timeout: 60
      rpm: 120
      tpm: 50000

  - model_name: cf-fast
    litellm_params:
      model: anthropic/claude-3-5-haiku-latest
      api_key: os.environ/ANTHROPIC_KEY_ORG_C
      timeout: 60
      rpm: 120
      tpm: 50000

  # =========================
  # DEEP POOL (Sonnet) x3 keys
  # =========================
  - model_name: cf-deep
    litellm_params:
      model: anthropic/claude-sonnet-4-20250514
      api_key: os.environ/ANTHROPIC_KEY_ORG_A
      timeout: 120
      rpm: 60
      tpm: 30000

  - model_name: cf-deep
    litellm_params:
      model: anthropic/claude-sonnet-4-20250514
      api_key: os.environ/ANTHROPIC_KEY_ORG_B
      timeout: 120
      rpm: 60
      tpm: 30000

  - model_name: cf-deep
    litellm_params:
      model: anthropic/claude-sonnet-4-20250514
      api_key: os.environ/ANTHROPIC_KEY_ORG_C
      timeout: 120
      rpm: 60
      tpm: 30000

  # =========================
  # DEEP FALLBACK (Sonnet 3.5) x3 keys - Daha ucuz alternatif
  # =========================
  - model_name: cf-deep-fallback
    litellm_params:
      model: anthropic/claude-3-5-sonnet-latest
      api_key: os.environ/ANTHROPIC_KEY_ORG_A
      timeout: 120
      rpm: 60
      tpm: 30000

  - model_name: cf-deep-fallback
    litellm_params:
      model: anthropic/claude-3-5-sonnet-latest
      api_key: os.environ/ANTHROPIC_KEY_ORG_B
      timeout: 120
      rpm: 60
      tpm: 30000

  - model_name: cf-deep-fallback
    litellm_params:
      model: anthropic/claude-3-5-sonnet-latest
      api_key: os.environ/ANTHROPIC_KEY_ORG_C
      timeout: 120
      rpm: 60
      tpm: 30000

  # =========================
  # GRACE LANE (OpenAI) - EKLENDİ!
  # =========================
  - model_name: cf-grace
    litellm_params:
      model: openai/gpt-4o-mini
      api_key: os.environ/OPENAI_API_KEY_GRACE
      timeout: 60
      rpm: 500
      tpm: 200000

router_settings:
  routing_strategy: usage-based-routing  # simple-shuffle yerine
  enable_pre_call_check: true
  num_retries: 2
  timeout: 140
  allowed_fails: 3                        # 3 fail sonrası cooldown
  cooldown_time: 60                       # 60 saniye cooldown
  
  redis_host: os.environ/REDIS_HOST
  redis_port: os.environ/REDIS_PORT
  redis_password: os.environ/REDIS_PASSWORD

litellm_settings:
  num_retries: 2
  request_timeout: 140
  
  # Fallback zinciri
  fallbacks:
    - cf-deep: [cf-deep-fallback, cf-fast]
  
  # Cache (deterministic requests için)
  cache: true
  cache_params:
    type: redis
    host: os.environ/REDIS_HOST
    port: os.environ/REDIS_PORT
    password: os.environ/REDIS_PASSWORD
    ttl: 3600

  # Logging
  success_callback: ["langfuse"]  # opsiyonel
  failure_callback: ["langfuse"]

general_settings:
  master_key: os.environ/LITELLM_MASTER_KEY
  database_url: os.environ/DATABASE_URL   # LiteLLM DB (opsiyonel)
```

### 5.3 Laravel Config Dosyaları

**config/litellm.php**
```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LiteLLM Proxy Connection
    |--------------------------------------------------------------------------
    */
    'base_url' => env('LITELLM_BASE_URL', 'http://localhost:4000'),
    'master_key' => env('LITELLM_MASTER_KEY'),
    
    /*
    |--------------------------------------------------------------------------
    | Model Aliases (LiteLLM'deki model_name'ler)
    |--------------------------------------------------------------------------
    */
    'aliases' => [
        'fast'  => 'cf-fast',
        'deep'  => 'cf-deep',
        'grace' => 'cf-grace',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Tier Configurations
    |--------------------------------------------------------------------------
    */
    'tiers' => [
        'fast' => [
            'timeout' => 60,
            'max_input_tokens' => 8000,
            'max_output_tokens' => 900,
        ],
        'deep' => [
            'timeout' => 120,
            'max_input_tokens' => 16000,
            'max_output_tokens' => 1400,
        ],
        'grace' => [
            'timeout' => 60,
            'max_input_tokens' => 8000,
            'max_output_tokens' => 800,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Retry Policy (Laravel-level, LiteLLM'in üstünde)
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => 2,
        'delay_ms' => 1000,
        'multiplier' => 2,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'forward_request_id' => true,
        'request_id_header' => 'X-Request-Id',
    ],
];
```

**config/codexflow.php**
```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plan Definitions
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'starter_500_try' => [
            'name' => 'Starter',
            'price_try' => 500,
            'monthly_quotas' => [
                'fast' => [
                    'input_tokens' => 2_000_000,
                    'output_tokens' => 400_000,
                    'requests' => 600,
                ],
                'deep' => [
                    'input_tokens' => 150_000,
                    'output_tokens' => 30_000,
                    'requests' => 60,
                ],
            ],
            'daily_safety' => [
                'fast' => ['requests' => 30, 'tokens' => 90_000],
                'deep' => ['requests' => 3, 'tokens' => 12_500],
            ],
            'grace_daily' => [
                'requests' => 20,
                'tokens' => 60_000,
            ],
        ],
        
        'pro_1000_try' => [
            'name' => 'Pro',
            'price_try' => 1000,
            'monthly_quotas' => [
                'fast' => [
                    'input_tokens' => 4_000_000,
                    'output_tokens' => 800_000,
                    'requests' => 1200,
                ],
                'deep' => [
                    'input_tokens' => 300_000,
                    'output_tokens' => 60_000,
                    'requests' => 120,
                ],
            ],
            'daily_safety' => [
                'fast' => ['requests' => 60, 'tokens' => 180_000],
                'deep' => ['requests' => 6, 'tokens' => 25_000],
            ],
            'grace_daily' => [
                'requests' => 40,
                'tokens' => 120_000,
            ],
        ],
        
        'team_2500_try' => [
            'name' => 'Team',
            'price_try' => 2500,
            'seats' => 5,
            'monthly_quotas' => [
                'fast' => [
                    'input_tokens' => 10_000_000,
                    'output_tokens' => 2_000_000,
                    'requests' => 3000,
                ],
                'deep' => [
                    'input_tokens' => 750_000,
                    'output_tokens' => 150_000,
                    'requests' => 300,
                ],
            ],
            'daily_safety' => [
                'fast' => ['requests' => 150, 'tokens' => 450_000],
                'deep' => ['requests' => 15, 'tokens' => 62_500],
            ],
            'grace_daily' => [
                'requests' => 100,
                'tokens' => 300_000,
            ],
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Admission Control (Token Clamping)
    |--------------------------------------------------------------------------
    */
    'admission' => [
        'fast' => [
            'max_input_tokens' => 8000,
            'max_output_tokens' => 900,
            'timeout' => 60,
        ],
        'deep' => [
            'max_input_tokens' => 16000,
            'max_output_tokens' => 1400,
            'timeout' => 120,
        ],
        'grace' => [
            'max_input_tokens' => 8000,
            'max_output_tokens' => 800,
            'timeout' => 60,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'version' => 'v1',
        'only_deterministic' => true, // temp=0, stream=false
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'per_key_per_minute' => 60,
        'per_user_per_minute' => 120,
        'burst_allowance' => 10,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'llm_requests_days' => 21,
        'aggregates_months' => 12,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cost Calculation (USD per 1M tokens)
    |--------------------------------------------------------------------------
    */
    'costs' => [
        'fast' => [
            'input' => 0.80,   // $0.80 / 1M input
            'output' => 4.00,  // $4.00 / 1M output
        ],
        'deep' => [
            'input' => 3.00,   // $3.00 / 1M input
            'output' => 15.00, // $15.00 / 1M output
        ],
        'grace' => [
            'input' => 0.15,   // $0.15 / 1M input
            'output' => 0.60,  // $0.60 / 1M output
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Key Settings
    |--------------------------------------------------------------------------
    */
    'api_keys' => [
        'prefix' => 'cf_',
        'length' => 40, // prefix hariç
        'hash_algo' => 'bcrypt',
    ],
];
```

### 5.4 Service Layer Tasarımı

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   └── V1/
│   │   │       ├── GatewayController.php      # Ana LLM endpoint
│   │   │       ├── ProjectController.php
│   │   │       ├── ApiKeyController.php
│   │   │       └── UsageController.php
│   │   ├── Auth/
│   │   │   └── AuthController.php
│   │   └── Admin/
│   │       ├── UserController.php
│   │       └── HealthController.php
│   │
│   ├── Middleware/
│   │   ├── RateLimitMiddleware.php
│   │   ├── RequestIdMiddleware.php
│   │   ├── AuthenticateProjectApiKey.php
│   │   ├── CheckUserStatus.php
│   │   └── QuotaCheckMiddleware.php
│   │
│   └── Requests/
│       ├── ChatCompletionRequest.php
│       └── CreateApiKeyRequest.php
│
├── Services/
│   ├── Llm/
│   │   ├── GatewayService.php            # Orchestrator
│   │   ├── TierSelector.php              # fast/deep/grace seçimi
│   │   ├── AdmissionController.php       # Token clamping
│   │   ├── LiteLLMClient.php             # HTTP client
│   │   ├── StreamHandler.php             # SSE handling
│   │   ├── CacheManager.php              # Deterministic cache
│   │   └── TelemetryLogger.php           # Request logging
│   │
│   ├── Quota/
│   │   ├── QuotaService.php              # Ana quota logic
│   │   ├── QuotaChecker.php              # Check available
│   │   ├── QuotaDecrementer.php          # Atomic decrement
│   │   └── QuotaSync.php                 # Redis → DB sync
│   │
│   ├── ApiKey/
│   │   ├── ApiKeyService.php
│   │   ├── KeyGenerator.php
│   │   └── KeyValidator.php
│   │
│   └── Usage/
│       ├── UsageAggregator.php
│       └── UsageReporter.php
│
├── Exceptions/
│   └── Llm/
│       ├── LlmException.php              # Base
│       ├── QuotaExceededException.php
│       ├── RateLimitException.php
│       ├── TimeoutException.php
│       ├── ProviderException.php
│       └── AdmissionRejectedException.php
│
├── Jobs/
│   ├── AggregateUsageDailyJob.php
│   ├── PruneLlmRequestsJob.php
│   ├── SyncQuotaToDbJob.php
│   └── RefreshHealthJob.php
│
└── Models/
    ├── User.php
    ├── Subscription.php
    ├── Project.php
    ├── ProjectApiKey.php
    ├── QuotaMonthly.php
    ├── QuotaDaily.php
    ├── LlmRequest.php
    └── UsageDailyAggregate.php
```

---

## 6. MALİYET & FİYATLANDIRMA MODELİ

### 6.1 API Maliyetleri (Güncel Tahmini)

| Model | Input (1M token) | Output (1M token) |
|-------|------------------|-------------------|
| Claude Haiku 3.5 | $0.80 | $4.00 |
| Claude Sonnet 4 | $3.00 | $15.00 |
| GPT-4o-mini | $0.15 | $0.60 |

### 6.2 Pro Plan (1000 TL) Maliyet Analizi

```
AYLIK KOTA:
- Fast: 4M input + 800K output
- Deep: 300K input + 60K output

EN KÖTÜ SENARYO (Tüm kota kullanılırsa):

Fast Maliyet:
  Input:  4,000,000 × $0.80 / 1,000,000 = $3.20
  Output:   800,000 × $4.00 / 1,000,000 = $3.20
  Toplam Fast = $6.40

Deep Maliyet:
  Input:    300,000 × $3.00 / 1,000,000 = $0.90
  Output:    60,000 × $15.00 / 1,000,000 = $0.90
  Toplam Deep = $1.80

Grace (Ortalama 15 gün × 80K token):
  Grace:  1,200,000 × $0.375 / 1,000,000 = $0.45

TOPLAM MALİYET (worst case): $8.65 ≈ 310 TL

GELİR: 1000 TL
MALİYET: ~310 TL
BRÜT KÂR: ~690 TL (%69 margin)
```

### 6.3 Break-Even Analizi

```
SABİT MALİYETLER (Aylık):
- OVH KS4 Sunucu: ~600 TL
- Domain + SSL: ~50 TL
- Toplam Sabit: 650 TL

DEĞİŞKEN MALİYET (Kullanıcı başı): ~310 TL

BREAK-EVEN:
650 / (1000 - 310) = 0.94 kullanıcı

YANİ: 1 ÖDEME YAPAN KULLANICI İLE BAŞABAŞ!

50 KULLANICI SENARYOSU:
Gelir: 50 × 1000 = 50,000 TL
API Maliyeti: 50 × 310 = 15,500 TL
Sabit: 650 TL
NET KÂR: 33,850 TL
```

### 6.4 Fiyatlandırma Önerisi (3 Tier)

| Plan | Fiyat | Fast Token | Deep Token | Grace | Hedef Kitle |
|------|-------|------------|------------|-------|-------------|
| **Starter** | 500 TL | 2M in / 400K out | 150K in / 30K out | 60K/gün | Hobi |
| **Pro** | 1000 TL | 4M in / 800K out | 300K in / 60K out | 120K/gün | Freelancer |
| **Team** | 2500 TL | 10M in / 2M out | 750K in / 150K out | 300K/gün | Startup |

---

## 7. RİSK ANALİZİ

### 7.1 Risk Matrisi

| Risk | Olasılık | Etki | Skor | Önlem |
|------|----------|------|------|-------|
| Anthropic ToS ihlali | Orta | Kritik | 🔴 | Reseller/enterprise görüşmesi |
| Tek kullanıcı aşırı tüketimi | Yüksek | Orta | 🟡 | Günlük cap + soft limit |
| LiteLLM downtime | Düşük | Yüksek | 🟡 | Health check + alert |
| Ödeme başarısızlığı | Orta | Orta | 🟡 | Grace period + suspend |
| DDoS saldırısı | Düşük | Yüksek | 🟡 | Cloudflare + rate limit |
| API key sızıntısı | Düşük | Kritik | 🔴 | Hashing + rotation |
| Model fiyat artışı | Orta | Orta | 🟡 | Buffer margin + tier güncelleme |

### 7.2 Contingency Planları

**Anthropic ToS Riski:**
```
Plan A: Enterprise görüşmesi yap
Plan B: Tek org key, rate limit artırımı talep et  
Plan C: Alternatif provider (Mistral, Gemini) ekle
```

**Maliyet Kontrolü:**
```
- Real-time cost tracking dashboard
- Günlük maliyet alert'leri (Slack/Telegram)
- Otomatik grace lane geçişi
- Emergency pause butonu (admin)
```

---

## 8. UYGULAMA YOL HARİTASI

### 8.1 Sprint Planı

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         SPRINT PLANI                                     │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  SPRINT 1 (Hafta 1-2): FOUNDATIONS                                      │
│  ─────────────────────────────────────                                  │
│  □ Laravel 12 kurulum + Docker setup                                    │
│  □ LiteLLM Docker + proxy_config.yaml                                   │
│  □ Database migrations + models                                          │
│  □ Config dosyaları (litellm.php, codexflow.php)                        │
│  □ Basic Sanctum auth                                                    │
│  □ User/Project/Subscription seeder                                      │
│  Milestone: LiteLLM'e curl ile test request atılabilir                  │
│                                                                          │
│  SPRINT 2 (Hafta 3): API KEY + MIDDLEWARE                               │
│  ─────────────────────────────────────────                              │
│  □ API Key generation + validation                                       │
│  □ RequestIdMiddleware                                                   │
│  □ AuthenticateProjectApiKey                                             │
│  □ RateLimitMiddleware (Redis)                                           │
│  □ QuotaCheckMiddleware (basic)                                          │
│  Milestone: API key ile auth geçilebilir                                │
│                                                                          │
│  SPRINT 3 (Hafta 4-5): GATEWAY CORE                                     │
│  ────────────────────────────────────                                   │
│  □ GatewayService + TierSelector                                         │
│  □ AdmissionController (token clamp)                                     │
│  □ LiteLLMClient (non-streaming)                                         │
│  □ QuotaService (atomic decrement)                                       │
│  □ TelemetryLogger                                                       │
│  □ Basic error handling                                                  │
│  Milestone: /v1/chat/completions çalışır (non-streaming)                │
│                                                                          │
│  SPRINT 4 (Hafta 6): STREAMING + CACHE                                  │
│  ──────────────────────────────────────                                 │
│  □ StreamHandler (SSE)                                                   │
│  □ CacheManager (deterministic)                                          │
│  □ Grace Lane fallback                                                   │
│  □ Retry logic refinement                                                │
│  Milestone: Cursor AI'dan streaming test başarılı                       │
│                                                                          │
│  SPRINT 5 (Hafta 7): DASHBOARDS                                         │
│  ───────────────────────────────────                                    │
│  □ Landing page (Tailwind)                                               │
│  □ Customer dashboard (quota, usage)                                     │
│  □ Admin dashboard (users, health)                                       │
│  □ API key management UI                                                 │
│  Milestone: Temel UI'lar hazır                                          │
│                                                                          │
│  SPRINT 6 (Hafta 8): JOBS + POLISH                                      │
│  ─────────────────────────────────────                                  │
│  □ AggregateUsageDailyJob                                                │
│  □ PruneLlmRequestsJob                                                   │
│  □ Payment integration (Iyzico)                                          │
│  □ Email notifications                                                   │
│  □ Feature tests                                                         │
│  Milestone: Production-ready MVP                                         │
│                                                                          │
│  SPRINT 7 (Hafta 9): BETA + LAUNCH                                      │
│  ───────────────────────────────────                                    │
│  □ 5-10 beta kullanıcı                                                   │
│  □ Feedback toplama                                                      │
│  □ Bug fixes                                                             │
│  □ Production deployment                                                 │
│  □ Monitoring setup (Grafana)                                            │
│  Milestone: LAUNCH! 🚀                                                  │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### 8.2 Revize PART Sıralaması

Orijinal PART'lar iyi ama şu sıralama daha mantıklı:

```
PART 0: LiteLLM Config (cf-grace eklendi) ✅
PART 1: Foundations (schema + models + config) - REVİZE EDİLDİ
PART 2: Auth + API Keys + Middleware
PART 3: Gateway Core (NON-STREAMING önce)
PART 3.5: Streaming Support (YENİ PART!)
PART 4: Usage + Jobs
PART 5: UI + Tests
```

---

## 9. SONUÇ & ÖNERİLER

### 9.1 Kesin Yapılması Gerekenler

| # | Aksiyon | Öncelik |
|---|---------|---------|
| 1 | `cf-grace` LiteLLM config'e ekle | 🔴 Kritik |
| 2 | Streaming desteği ekle | 🔴 Kritik |
| 3 | Token counting stratejisi belirle | 🔴 Kritik |
| 4 | plan_code çoğaltmasını düzelt | 🟡 Önemli |
| 5 | Rate limiting middleware ekle | 🟡 Önemli |
| 6 | API key prefix-based lookup | 🟢 İyileştirme |

### 9.2 Önerilen Değişiklikler (Prompt'lara)

**PART 0 Eklentisi:**
```yaml
# cf-grace bloğu eklenmeli
- model_name: cf-grace
  litellm_params:
    model: openai/gpt-4o-mini
    api_key: os.environ/OPENAI_API_KEY_GRACE
    timeout: 60
    rpm: 500
    tpm: 200000
```

**PART 1 Düzeltmeleri:**
- `projects.plan_code` kaldır
- `project_api_keys.key_prefix` ekle
- `llm_requests.is_streaming` ekle
- `llm_requests.time_to_first_token_ms` ekle

**PART 3 Eklentisi:**
```
PART 3.5: STREAMING SUPPORT
- SSE endpoint handler
- Stream chunk parsing
- Token counting from final message
- Streaming-aware quota decrement
```

### 9.3 Final Değerlendirme

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         PROJE SKORKART                                   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  Teknik Fizibilite      ████████░░  8/10  Yapılabilir                   │
│  İş Modeli              ███████░░░  7/10  Kârlı (dikkatli yönetimle)    │
│  Prompt Kalitesi        ███████░░░  7/10  İyi, eksikler giderilmeli     │
│  Pazar Potansiyeli      █████████░  9/10  Boşluk var                    │
│  Müşteri Değeri         █████████░  9/10  Grace Lane büyük artı         │
│  Risk Seviyesi          ██████░░░░  6/10  Yönetilebilir                 │
│                                                                          │
│  GENEL SKOR:            █████████░  7.7/10                              │
│                                                                          │
│  VERDİKT: ✅ DEVAM ET!                                                  │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### 9.4 Son Sözler

Bu proje **yapılabilir ve kârlı olabilir**. Şu koşullarla:

1. **Streaming desteği şart** - yoksa Cursor deneyimi çok kötü olur
2. **Maliyet kontrolü kritik** - real-time monitoring kur
3. **Küçük başla** - 10 kullanıcı ile beta, sonra scale
4. **Grace Lane altın** - bu özellik seni rakiplerden ayırır
5. **Türkçe destek** - yerel pazarda büyük avantaj

---

**HAZIR OLDUĞUNDA "BAŞLA" DE, PART 1'DEN KODLAMAYA GEÇELİM!** 🚀

---

*Rapor Sonu*  
*CodexFlow.dev Master Plan v1.0*  
*27 Aralık 2025*

