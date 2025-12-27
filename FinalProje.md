# 🚀 CODEXFLOW.DEV — FINAL MASTER PLAN v2.0

> **Versiyon:** 2.0 (Decompose Pipeline Edition)  
> **Tarih:** 27 Aralık 2025  
> **Hazırlayan:** Claude Opus 4.5  
> **Durum:** Production-Ready Specification  
> **Hedef Kar Marjı:** %30 (Worst Case)

---

## 📑 İÇİNDEKİLER

1. [Yönetici Özeti](#1-yönetici-özeti)
2. [Decompose Pipeline Mimarisi](#2-decompose-pipeline-mimarisi)
3. [LiteLLM Alias Stratejisi](#3-litellm-alias-stratejisi)
4. [Maliyet & Kar Marjı Optimizasyonu](#4-maliyet--kar-marjı-optimizasyonu)
5. [Revize Edilmiş PART Prompt'ları](#5-revize-edilmiş-part-promptları)
6. [Database Schema](#6-database-schema)
7. [Service Layer Tasarımı](#7-service-layer-tasarımı)
8. [Config Dosyaları](#8-config-dosyaları)
9. [Test Stratejisi](#9-test-stratejisi)
10. [Deployment & Monitoring](#10-deployment--monitoring)

---

## 1. YÖNETİCİ ÖZETİ

### 🎯 Temel Konsept

**"GPT-5 nano planner + Claude 4.5 executor"** mimarisi ile:
- **Planner (nano):** Ucuz, hızlı → sadece JSON plan üretir
- **Executor (Claude):** Pahalı, güçlü → sadece diff üretir
- **Tasarruf:** Pahalı output şişmesi önlenir, retry chunk bazına iner

### 📊 Hedef Metrikler

| Metrik | Değer |
|--------|-------|
| Hedef Kar Marjı | %30 (worst case) |
| Max Kullanıcı | 50 |
| Aylık Fiyat | 1000 TL (Pro) |
| Deneme Süresi | 7 gün ücretsiz |
| Max API Maliyeti/Kullanıcı | 700 TL |
| Hedef Net Kar/Kullanıcı | 300 TL |

### 🔑 5 LiteLLM Alias

```
cf-fast    → Claude Haiku 3.5 (3 org key pool)           - Hızlı işler
cf-deep    → Claude Sonnet 4 (3 org key pool)            - Zor işler  
cf-planner → GPT-4o-mini                                 - Plan/chunk JSON
cf-grace   → Llama 3.1 405B FREE (OpenRouter)            - Kota bitince (ÜCRETSİZ!)
cf-grace-fallback → GPT-4o-mini                          - Llama fail olursa
```

### 💰 Grace Lane Tasarruf

```
ESKİ (Sadece GPT-4o-mini):     ~$750/ay Grace maliyeti
YENİ (Llama Free + Fallback):  ~$75/ay  (%90 tasarruf!)
```

### ✅ Llama 405B Test Sonuçları (27 Aralık 2025)

| Test | İçerik | Latency | Cost |
|------|--------|---------|------|
| PHP Email Validator | Kod üretimi | 6.4s | $0 |
| Laravel Middleware | Karmaşık kod | 18.4s | $0 |
| Simple Query | Basit soru | 1.0s | $0 |
| Back-to-back | Rate limit | 0.7s | $0 |

**Sonuç:** Çalışıyor, ücretsiz, kod kalitesi iyi (8/10)

---

## 1.5 TRIAL (DENEME) PLANI

### 🎁 Neden Trial Önemli?

- Müşteri sistemi denemeden ödeme yapmak istemez
- Güven oluşturur
- Viral büyüme (arkadaşa öner)
- Conversion rate artırır

### 📊 Trial Plan Detayları

| Özellik | Değer |
|---------|-------|
| **Süre** | **24 saat** |
| **Fiyat** | ÜCRETSİZ |
| **Kredi Kartı** | Gerektirmez ❌ |
| **Fast Tokens** | 200K input / 40K output |
| **Deep Tokens** | 50K input / 10K output |
| **Requests** | 100 fast / 20 deep |
| **Grace (Llama FREE)** | ♾️ Sınırsız (24 saat boyunca) |
| **Max Maliyet Riski** | ~$0.50 |

### 🔄 Trial Kota Biterse?

```
Trial kotası bitti (fast+deep = 0)
           ↓
Llama 3.1 405B FREE ile devam et (Grace Lane)
           ↓
24 saat boyunca sınırsız Grace kullanabilir
           ↓
Müşteri sistemi tam test edebilir!
```

**Neden bu mantıklı?**
- Müşteri asla "stuck" kalmaz
- Llama FREE = maliyet $0
- 24 saat sınırlı = abuse riski düşük
- Sistemi gerçekten test edebilir

### 🔄 Trial Flow (24 Saat)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      TRIAL USER JOURNEY (24 SAAT)                        │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  1. KAYIT (Saat 0)                                                      │
│     ├── Email + Password (kart yok!)                                    │
│     ├── Email doğrulama (zorunlu)                                       │
│     ├── Otomatik trial_free planı atanır                                │
│     ├── 24 saat geri sayım başlar                                       │
│     └── Welcome email + Cursor kurulum rehberi                          │
│                                                                          │
│  2. KULLANIM (0-24 saat)                                                │
│     ├── API key oluştur                                                 │
│     ├── Cursor'a bağla                                                  │
│     ├── Fast/Deep kotasını kullan                                       │
│     │                                                                    │
│     │  ┌─────────────────────────────────────────┐                      │
│     │  │  KOTA BİTTİ? → LLAMA 405B FREE DEVAM!  │                      │
│     │  │  Sınırsız Grace, 24 saat boyunca       │                      │
│     │  └─────────────────────────────────────────┘                      │
│     │                                                                    │
│     └── Dashboard'da canlı kota + süre takibi                           │
│                                                                          │
│  3. HATIRLATMALAR                                                       │
│     ├── Saat 12: "12 saat kaldı!" email                                 │
│     ├── Saat 20: "4 saat kaldı!" email                                  │
│     └── Saat 23: "1 saat kaldı!" email + acil upgrade CTA               │
│                                                                          │
│  4. BİTİŞ (Saat 24)                                                     │
│     ├── Hesap suspend                                                   │
│     ├── API key'ler çalışmayı durdurur                                  │
│     ├── Dashboard'da büyük upgrade prompt                               │
│     └── "Deneyiminiz nasıldı?" feedback formu                           │
│                                                                          │
│  5. UPGRADE                                                              │
│     ├── Starter (500 TL) veya Pro (1000 TL) seç                         │
│     ├── Ödeme yap (Iyzico/Stripe)                                       │
│     ├── Hesap anında aktif                                              │
│     └── İlk ay %10 indirim (sadece 24 saat içinde)                      │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### 💡 Anti-Abuse Önlemleri

```php
// config/codexflow.php → trial section

'trial' => [
    // Temel ayarlar
    'duration_hours' => 24,              // 24 saat
    'plan_code' => 'trial_free',
    
    // Grace Lane (Llama FREE) - kota bitince
    'grace_on_quota_exhausted' => true,  // Fast/Deep biterse Llama ile devam
    'grace_unlimited_during_trial' => true, // Trial süresince sınırsız Grace
    
    // Abuse önleme
    'limits' => [
        'max_trials_per_email_domain' => 3,  // Gmail spam önleme
        'max_trials_per_ip' => 2,            // IP başına limit
        'disposable_email_block' => true,    // Temp mail engelle
        'require_email_verification' => true,
    ],
    
    // Dönüşüm
    'conversion' => [
        'reminder_hours' => [12, 20, 23],    // Email hatırlatma saatleri
        'extend_on_feedback' => false,       // 24 saat'te uzatma yok
        'discount_on_upgrade' => 0.10,       // İlk ay %10 indirim
        'discount_valid_hours' => 24,        // İndirim 24 saat geçerli
    ],
],
```

### 📈 Beklenen Conversion Rate

| Metrik | Hedef |
|--------|-------|
| Trial → Paid | %15-25 |
| Trial → Starter | %10 |
| Trial → Pro | %12 |
| Trial → Team | %3 |

### 🎯 Trial Başarı Kriterleri

Bir trial başarılı sayılır eğer:
1. En az 3 gün aktif kullanım
2. Minimum 20 request
3. En az 1 "deep" tier kullanımı
4. Dashboard'a 3+ login

---

## 2. DECOMPOSE PIPELINE MİMARİSİ

### 2.1 Neden Decompose?

```
PROBLEM:
┌─────────────────────────────────────────────────────────┐
│  Büyük request (24K+ karakter)                          │
│  → Claude'a tek seferde gönder                          │
│  → 50K output token (roman yazıyor)                     │
│  → Maliyet patlıyor                                     │
│  → Retry gerekirse tamamı tekrar                        │
│  → $$$$$                                                │
└─────────────────────────────────────────────────────────┘

ÇÖZÜM (Decompose Pipeline):
┌─────────────────────────────────────────────────────────┐
│  Büyük request geldi                                    │
│  → GPT-5 nano: Plan JSON üret (ucuz)                    │
│  → Claude Chunk A: schema+models (fast, max 700 tok)    │
│  → Claude Chunk B: gateway core (deep, max 1200 tok)    │
│  → Claude Chunk C: jobs+tests (fast, max 700 tok)       │
│  → Birleştir, tek response dön                          │
│  → Retry sadece fail eden chunk'ta                      │
│  → $                                                    │
└─────────────────────────────────────────────────────────┘
```

### 2.2 Pipeline Flow

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                        DECOMPOSE PIPELINE FLOW                                │
└──────────────────────────────────────────────────────────────────────────────┘

     REQUEST
        │
        ▼
┌───────────────────┐
│ LargeRequestDetector │
│ ─────────────────── │
│ Trigger koşulları:  │
│ • x-decompose: 1    │
│ • input >= 8K tok   │
│ • char_len >= 24K   │
└─────────┬───────────┘
          │
    ┌─────┴─────┐
    │           │
    ▼           ▼
  SMALL       LARGE
    │           │
    ▼           ▼
┌─────────┐  ┌──────────────────────────────────────────────────────────┐
│ Normal  │  │                    DECOMPOSE MODE                        │
│ Gateway │  │                                                          │
│ Flow    │  │  ┌─────────────────────────────────────────────────────┐ │
└─────────┘  │  │  STEP 1: PLANNER (cf-planner / GPT-5 nano)          │ │
             │  │  ────────────────────────────────────────            │ │
             │  │  Input: Full request + context                       │ │
             │  │  Output: JSON plan (chunks array)                    │ │
             │  │  Cost: ~0.001$ (çok ucuz)                            │ │
             │  └─────────────────────────────────────────────────────┘ │
             │                          │                               │
             │                          ▼                               │
             │  ┌─────────────────────────────────────────────────────┐ │
             │  │  STEP 2: CHUNK EXECUTION (paralel veya sıralı)      │ │
             │  │  ────────────────────────────────────────            │ │
             │  │                                                      │ │
             │  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ │ │
             │  │  │  CHUNK A     │ │  CHUNK B     │ │  CHUNK C     │ │ │
             │  │  │  (cf-fast)   │ │  (cf-deep)   │ │  (cf-fast)   │ │ │
             │  │  │  schema+     │ │  gateway     │ │  jobs+       │ │ │
             │  │  │  models      │ │  core        │ │  tests       │ │ │
             │  │  │  max 700 tok │ │  max 1200 tok│ │  max 700 tok │ │ │
             │  │  └──────────────┘ └──────────────┘ └──────────────┘ │ │
             │  │                                                      │ │
             │  └─────────────────────────────────────────────────────┘ │
             │                          │                               │
             │                          ▼                               │
             │  ┌─────────────────────────────────────────────────────┐ │
             │  │  STEP 3: MERGE & RESPOND                            │ │
             │  │  ────────────────────────────────────────            │ │
             │  │  PLAN: {...}                                         │ │
             │  │  PATCHES:                                            │ │
             │  │    [Chunk A diffs]                                   │ │
             │  │    [Chunk B diffs]                                   │ │
             │  │    [Chunk C diffs]                                   │ │
             │  └─────────────────────────────────────────────────────┘ │
             │                                                          │
             └──────────────────────────────────────────────────────────┘
```

### 2.3 Hard Limits (Maliyet Kontrolü)

```php
// config/codexflow.php → decompose section

'decompose' => [
    // Trigger eşikleri
    'triggers' => [
        'header' => 'x-decompose',           // Manuel trigger
        'min_input_tokens' => 8000,          // Otomatik trigger
        'min_char_length' => 24000,          // Token tahmini yoksa
    ],
    
    // Hard limits (maliyet patlamasın)
    'limits' => [
        'max_planner_calls' => 1,            // Tek plan
        'max_chunks' => 3,                   // Max 3 chunk
        'max_total_calls' => 4,              // 1 planner + 3 chunk
        'max_files_per_chunk' => 5,          // Chunk başına max dosya
        'total_timeout_seconds' => 480,      // 8 dakika max
    ],
    
    // Chunk output limits
    'chunk_limits' => [
        'fast' => [
            'max_output_tokens' => 700,
            'timeout' => 60,
        ],
        'deep' => [
            'max_output_tokens' => 1200,
            'timeout' => 120,
        ],
    ],
    
    // Planner limits
    'planner' => [
        'max_output_tokens' => 500,          // Sadece JSON
        'timeout' => 30,
    ],
],
```

### 2.4 Planner Prompt & JSON Schema

**System Prompt (cf-planner):**
```
You are a code task decomposer. Output ONLY valid JSON.
Never explain. Never add prose. JSON only.

RULES:
- Max 3 chunks
- Max 5 files per chunk  
- Assign tier: "fast" for simple, "deep" for complex logic
- No full file rewrites, prefer patches
- Be minimal and precise

OUTPUT SCHEMA:
{
  "summary": ["line1", "line2", ...],  // max 10 lines
  "chunks": [
    {
      "id": "A",
      "title": "short title",
      "goal": "what to accomplish",
      "files": ["path1", "path2", "NEW:path3"],  // max 5
      "tier": "fast|deep",
      "max_output_tokens": 700|1200,
      "depends_on": []  // chunk IDs if sequential needed
    }
  ],
  "execution_order": "parallel|sequential",
  "safety": {
    "max_chunks": 3,
    "max_files_per_chunk": 5,
    "no_full_rewrites": true
  }
}
```

**Default Chunk Pattern:**
```json
{
  "chunks": [
    {
      "id": "A",
      "title": "Schema + Models + Config",
      "tier": "fast",
      "max_output_tokens": 700
    },
    {
      "id": "B", 
      "title": "Gateway Core (Router + Quota + LiteLLM Client)",
      "tier": "deep",
      "max_output_tokens": 1200
    },
    {
      "id": "C",
      "title": "Jobs + Endpoints + Tests",
      "tier": "fast", 
      "max_output_tokens": 700
    }
  ]
}
```

### 2.5 Chunk Execution Prompt (Claude)

**Her chunk için minimal context:**
```
[CHUNK {id}] {title}

GOAL: {goal}

TARGET FILES:
{files listesi}

CONTEXT (only relevant parts):
{sadece gerekli dosya parçaları - tam repo değil}

RULES:
- Output ONLY unified diff patches
- Max {max_output_tokens} tokens
- Max {max_files} files
- No full rewrites
- No prose outside diffs

BEGIN:
```

### 2.6 Kota Muhasebesi

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                        QUOTA ACCOUNTING                                       │
└──────────────────────────────────────────────────────────────────────────────┘

Planner (cf-planner) tokenları:
  → "planner_overhead" olarak ayrı kaydet
  → Kullanıcı kotasından DÜŞME (sistem maliyeti)
  → Aylık max 50K token planner pool (kullanıcı başı)

Chunk tokenları:
  → Chunk tier'ına göre ilgili kotadan düş
  → Chunk A (fast) → fast quota
  → Chunk B (deep) → deep quota
  → Chunk C (fast) → fast quota

Fallback zinciri:
  deep biter → fast'e düş
  fast biter → grace'e düş
  grace biter → 429 + Retry-After
```

---

## 3. LITELLM ALIAS STRATEJİSİ

### 3.1 Neden 5 Alias?

| Alias | Model | Amaç | Maliyet |
|-------|-------|------|---------|
| `cf-fast` | Claude Haiku 3.5 | Hızlı, basit işler | $ |
| `cf-deep` | Claude Sonnet 4 | Karmaşık logic | $$$ |
| `cf-planner` | GPT-4o-mini | Plan JSON | ¢ |
| `cf-grace` | **Llama 3.1 405B** | Kota bitince (OpenRouter) | **FREE!** |
| `cf-grace-fallback` | GPT-4o-mini | Llama fail olursa | $ |

**Grace Lane Stratejisi:**
```
┌─────────────────────────────────────────────────────────────┐
│  User quota exhausted → Try cf-grace (Llama FREE)          │
│      ↓                                                      │
│  Success? → Return ($0 cost)                                │
│  Fail?    → Try cf-grace-fallback (GPT-4o-mini)            │
│      ↓                                                      │
│  Return response                                            │
│                                                             │
│  Result: ~90% FREE, ~10% paid fallback = %90 tasarruf!     │
└─────────────────────────────────────────────────────────────┘
```

**Neden cf-planner ayrı?**
- Planner her zaman ucuz kalmalı
- Grace lane ile karıştırılmamalı
- Farklı rate limit/timeout ayarları

### 3.2 LiteLLM proxy_config.yaml (Final)

```yaml
# infra/litellm/proxy_config.yaml
# CODEXFLOW.DEV - Production v2.0 (Decompose Edition)

model_list:
  # =========================
  # FAST POOL (Haiku 3.5) x3 keys
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
  # DEEP POOL (Sonnet 4) x3 keys
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
  # DEEP FALLBACK (Sonnet 3.5) x3 keys
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
  # PLANNER (GPT-5 nano) - Plan/Chunk JSON
  # =========================
  - model_name: cf-planner
    litellm_params:
      model: openai/gpt-4o-mini  # veya gpt-5-nano varsa
      api_key: os.environ/OPENAI_API_KEY_PLANNER
      timeout: 30
      rpm: 500
      tpm: 100000
      max_tokens: 500  # Sadece JSON plan

  # =========================
  # GRACE LANE PRIMARY (Llama 405B FREE via OpenRouter)
  # =========================
  - model_name: cf-grace
    litellm_params:
      model: openrouter/meta-llama/llama-3.1-405b-instruct:free
      api_key: os.environ/OPENROUTER_API_KEY
      timeout: 90
      rpm: 20
      tpm: 100000

  # =========================
  # GRACE LANE FALLBACK (GPT-4o-mini - Llama fail olursa)
  # =========================
  - model_name: cf-grace-fallback
    litellm_params:
      model: openai/gpt-4o-mini
      api_key: os.environ/OPENAI_API_KEY_GRACE
      timeout: 60
      rpm: 500
      tpm: 200000

router_settings:
  routing_strategy: usage-based-routing
  enable_pre_call_check: true
  num_retries: 2
  timeout: 140
  allowed_fails: 3
  cooldown_time: 60
  
  redis_host: os.environ/REDIS_HOST
  redis_port: os.environ/REDIS_PORT
  redis_password: os.environ/REDIS_PASSWORD

litellm_settings:
  num_retries: 2
  request_timeout: 140
  
  # Fallback zinciri (LiteLLM internal)
  fallbacks:
    - cf-deep: [cf-deep-fallback]
    - cf-grace: [cf-grace-fallback]  # Llama fail → GPT-4o-mini
  
  # Cache (deterministic requests için)
  cache: true
  cache_params:
    type: redis
    host: os.environ/REDIS_HOST
    port: os.environ/REDIS_PORT
    password: os.environ/REDIS_PASSWORD
    ttl: 3600

general_settings:
  master_key: os.environ/LITELLM_MASTER_KEY
  database_url: os.environ/DATABASE_URL
```

---

## 4. MALİYET & KAR MARJI OPTİMİZASYONU

### 4.1 Hedef: %30 Kar Marjı (Worst Case)

```
FORMÜL:
Kar Marjı = (Gelir - Maliyet) / Gelir × 100

HEDEF:
%30 = (1000 - Maliyet) / 1000 × 100
Maliyet = 700 TL (max)

DOLAR BAZINDA (~35 TL/USD):
700 TL = $20 max API maliyeti/kullanıcı/ay
```

### 4.2 Revize Edilmiş Plan Kotaları

**Eski kotalar (proje.md) → ~$8.65 maliyet (çok az)**  
**Yeni kotalar → $15-18 maliyet (%30 margin için)**

```php
// config/codexflow.php → plans

'plans' => [
    'starter_500_try' => [
        'name' => 'Starter',
        'price_try' => 500,
        'target_margin' => 0.30,
        'max_cost_usd' => 10.00,
        
        'monthly_quotas' => [
            'fast' => [
                'input_tokens' => 3_000_000,    // ↓ 2M'den 3M'e (daha cömert)
                'output_tokens' => 600_000,
                'requests' => 800,
            ],
            'deep' => [
                'input_tokens' => 200_000,
                'output_tokens' => 40_000,
                'requests' => 80,
            ],
        ],
        'grace_daily' => [
            'requests' => 30,
            'tokens' => 80_000,
        ],
    ],
    
    'pro_1000_try' => [
        'name' => 'Pro',
        'price_try' => 1000,
        'target_margin' => 0.30,
        'max_cost_usd' => 20.00,
        
        'monthly_quotas' => [
            'fast' => [
                'input_tokens' => 6_000_000,    // 4M → 6M (daha cömert)
                'output_tokens' => 1_200_000,   // 800K → 1.2M
                'requests' => 1500,             // 1200 → 1500
            ],
            'deep' => [
                'input_tokens' => 400_000,      // 300K → 400K
                'output_tokens' => 80_000,      // 60K → 80K
                'requests' => 150,              // 120 → 150
            ],
        ],
        'daily_safety' => [
            'fast' => ['requests' => 80, 'tokens' => 250_000],
            'deep' => ['requests' => 8, 'tokens' => 35_000],
        ],
        'grace_daily' => [
            'requests' => 50,
            'tokens' => 150_000,
        ],
        
        // Planner overhead (kullanıcı kotasından düşmez)
        'planner_pool' => [
            'monthly_tokens' => 100_000,
            'cost_absorbed' => true,  // Sistem karşılar
        ],
    ],
    
    'team_2500_try' => [
        'name' => 'Team',
        'price_try' => 2500,
        'seats' => 5,
        'target_margin' => 0.30,
        'max_cost_usd' => 50.00,
        
        'monthly_quotas' => [
            'fast' => [
                'input_tokens' => 15_000_000,
                'output_tokens' => 3_000_000,
                'requests' => 4000,
            ],
            'deep' => [
                'input_tokens' => 1_000_000,
                'output_tokens' => 200_000,
                'requests' => 400,
            ],
        ],
        'grace_daily' => [
            'requests' => 120,
            'tokens' => 400_000,
        ],
    ],
],
```

### 4.3 Maliyet Hesabı (Pro Plan - Worst Case)

```
PRO PLAN - TÜM KOTA KULLANILIRSA:

FAST (Haiku 3.5):
  Input:  6,000,000 × $0.80 / 1M = $4.80
  Output: 1,200,000 × $4.00 / 1M = $4.80
  Subtotal: $9.60

DEEP (Sonnet 4):
  Input:    400,000 × $3.00 / 1M = $1.20
  Output:    80,000 × $15.00 / 1M = $1.20
  Subtotal: $2.40

GRACE (Llama 405B FREE + %10 GPT-4o-mini fallback):
  Llama (90%): 1,800,000 × $0 = $0
  GPT-4o-mini (10%): 200,000 × $0.375 / 1M = $0.075
  Total Grace: ~$0.08

PLANNER (sistem karşılar, hesaba katılmaz):
  ~$0.10 (çok düşük)

TOPLAM MALİYET: $12.08 + buffer = ~$13
DOLAR KURU (35 TL): 455 TL

GELİR: 1000 TL
MALİYET: ~455 TL
KAR: 545 TL
KAR MARJI: %54.5 ✅ (hedef %30'un çok üstünde!)

💡 Llama FREE sayesinde Grace Lane neredeyse bedava!
```

### 4.4 Decompose'un Tasarruf Etkisi

```
SENARYO: 50K karakter request (büyük bir feature)

ESKİ YÖNTEM (tek shot):
  Input: ~12K token × $3/1M = $0.036
  Output: ~8K token × $15/1M = $0.12
  Toplam: ~$0.16
  Başarısızlık riski: YÜKSEK
  Retry maliyeti: $0.16 × 2 = $0.32

DECOMPOSE YÖNTEM:
  Planner: ~1K token = $0.0004
  Chunk A (fast): 2K out × $4/1M = $0.008
  Chunk B (deep): 1.2K out × $15/1M = $0.018
  Chunk C (fast): 0.7K out × $4/1M = $0.003
  Toplam: ~$0.03
  
  Chunk B fail? Sadece B retry: +$0.018
  Toplam with retry: $0.048

TASARRUF: %70-80 (retry dahil)
```

---

## 5. REVİZE EDİLMİŞ PART PROMPT'LARI

### PART 0: LiteLLM Proxy Configuration

```markdown
# PART 0/6 — LiteLLM Proxy Configuration

You are configuring LiteLLM proxy for CodexFlow.dev.

## OUTPUT RULES:
- Output ONLY the complete proxy_config.yaml file
- Use os.environ for all secrets
- Include comments for clarity

## ALIAS STRATEGY (4 aliases):
1. cf-fast    → Claude Haiku 3.5 (3 org key pool) - Quick tasks
2. cf-deep    → Claude Sonnet 4 (3 org key pool) - Complex logic
3. cf-planner → GPT-4o-mini - Plan/chunk JSON only
4. cf-grace   → GPT-4o-mini - Quota exhausted fallback

## REQUIREMENTS:

### Model Pools:
- cf-fast: 3 deployments with ANTHROPIC_KEY_ORG_A/B/C
- cf-deep: 3 deployments with same keys
- cf-deep-fallback: 3 deployments (Sonnet 3.5) for internal fallback
- cf-planner: 1 deployment, max_tokens=500
- cf-grace: 1 deployment

### Router Settings:
- routing_strategy: usage-based-routing
- num_retries: 2
- allowed_fails: 3
- cooldown_time: 60

### Fallback Chain (LiteLLM internal):
- cf-deep → cf-deep-fallback (within LiteLLM)
- cf-fast and cf-grace have no LiteLLM fallbacks (Laravel handles)

### Cache:
- Redis-based
- TTL: 3600 seconds

### Timeouts:
- cf-fast: 60s
- cf-deep: 120s
- cf-planner: 30s
- cf-grace: 60s

## ENV VARIABLES REQUIRED:
- ANTHROPIC_KEY_ORG_A, ANTHROPIC_KEY_ORG_B, ANTHROPIC_KEY_ORG_C
- OPENAI_API_KEY_PLANNER, OPENAI_API_KEY_GRACE
- OPENROUTER_API_KEY (for Llama 405B FREE)
- REDIS_HOST, REDIS_PORT, REDIS_PASSWORD
- LITELLM_MASTER_KEY
- DATABASE_URL (optional)

OUTPUT the complete proxy_config.yaml now.
```

---

### PART 1: Foundations (Migrations + Models + Config)

```markdown
# PART 1/6 — Foundations (Migrations + Models + Config)

You are implementing CodexFlow.dev MVP on Laravel 12.

## ABSOLUTE OUTPUT RULES:
- Output ONLY unified diff patches
- Never rewrite full files. Patch only changed parts
- New files must use --- /dev/null
- No prose outside diffs
- Secrets via ENV only

## GOAL:
Create database schema + core models + config scaffolding.
Lock Laravel to 4 LiteLLM aliases: cf-fast, cf-deep, cf-planner, cf-grace.

## STACK:
- Laravel 12, PHP 8.3
- MySQL 8, Redis 7
- Sanctum for auth

## DATABASE SCHEMA:

### users
- id (bigint unsigned, PK)
- name (string 255)
- email (string 255, unique)
- password (string 255)
- role (enum: admin|customer, default customer)
- status (enum: active|suspended|pending, default pending)
- email_verified_at (timestamp nullable)
- remember_token (string 100 nullable)
- timestamps

### subscriptions
- id (bigint unsigned, PK)
- user_id (FK users)
- plan_code (string 50, default 'pro_1000_try')
- starts_at (date)
- ends_at (date)
- status (enum: active|paused|canceled|expired|trial, default active)
- is_trial (boolean, default false)
- trial_ends_at (date nullable)
- converted_from_trial (boolean, default false)
- payment_provider (string 50 nullable)
- payment_ref (string 255 nullable)
- timestamps

### projects
- id (bigint unsigned, PK)
- user_id (FK users)
- name (string 255)
- slug (string 255)
- status (enum: active|paused|deleted, default active)
- settings (json nullable)
- timestamps
- unique(user_id, slug)

### project_api_keys
- id (bigint unsigned, PK)
- project_id (FK projects)
- name (string 255)
- key_prefix (string 12) — first 12 chars for fast lookup
- key_hash (string 255) — bcrypt hash
- last_used_at (timestamp nullable)
- revoked_at (timestamp nullable)
- created_at (timestamp)
- index(key_prefix)

### quota_monthly
- id (bigint unsigned, PK)
- user_id (FK users)
- month (char 7) — YYYY-MM
- fast_input_tokens (bigint default 0)
- fast_output_tokens (bigint default 0)
- fast_requests (int default 0)
- deep_input_tokens (bigint default 0)
- deep_output_tokens (bigint default 0)
- deep_requests (int default 0)
- planner_tokens (bigint default 0) — overhead tracking
- updated_at (timestamp)
- unique(user_id, month)

### quota_daily
- id (bigint unsigned, PK)
- user_id (FK users)
- date (date)
- fast_tokens (bigint default 0)
- fast_requests (int default 0)
- deep_tokens (bigint default 0)
- deep_requests (int default 0)
- grace_tokens (bigint default 0)
- grace_requests (int default 0)
- updated_at (timestamp)
- unique(user_id, date)

### llm_requests
- id (uuid, PK)
- user_id (bigint nullable)
- project_id (bigint)
- api_key_id (bigint nullable)
- parent_request_id (uuid nullable) — for decompose chunks
- chunk_index (tinyint nullable) — 0=planner, 1/2/3=chunks
- request_id (string 64) — X-Request-Id
- tier (enum: fast|deep|planner|grace)
- model_alias (string 50) — cf-fast, cf-deep, etc.
- prompt_tokens (int unsigned default 0)
- completion_tokens (int unsigned default 0)
- total_tokens (int unsigned default 0)
- cost_usd (decimal 10,6 nullable)
- latency_ms (int unsigned nullable)
- time_to_first_token_ms (int unsigned nullable)
- is_cached (boolean default false)
- is_streaming (boolean default false)
- is_decomposed (boolean default false)
- status_code (smallint unsigned nullable)
- error_type (string 50 nullable)
- created_at (timestamp)
- index(project_id, created_at)
- index(parent_request_id)

### usage_daily_aggregates
- id (bigint unsigned, PK)
- project_id (FK projects)
- date (date)
- fast_tokens, fast_requests, fast_cost_usd
- deep_tokens, deep_requests, deep_cost_usd
- grace_tokens, grace_requests, grace_cost_usd
- planner_tokens, planner_requests
- total_tokens, total_requests, total_cost_usd
- cache_hits (int default 0)
- decomposed_requests (int default 0)
- timestamps
- unique(project_id, date)

## MODELS:
Create Eloquent models with:
- Proper $fillable arrays
- Relationship methods
- Casts for enums/json/dates

Relationships:
- User hasMany Subscriptions, Projects
- Project belongsTo User, hasMany ProjectApiKeys, LlmRequests
- Subscription belongsTo User
- LlmRequest belongsTo Project, optionally hasMany (self-reference for chunks)

## CONFIG FILES:

### config/litellm.php
```php
return [
    'base_url' => env('LITELLM_BASE_URL', 'http://localhost:4000'),
    'master_key' => env('LITELLM_MASTER_KEY'),
    
    'aliases' => [
        'fast' => 'cf-fast',
        'deep' => 'cf-deep',
        'planner' => 'cf-planner',
        'grace' => 'cf-grace',              // Llama 405B FREE
        'grace_fallback' => 'cf-grace-fallback',  // GPT-4o-mini backup
    ],
    
    'tiers' => [
        'fast' => ['timeout' => 60, 'max_input' => 8000, 'max_output' => 900],
        'deep' => ['timeout' => 120, 'max_input' => 16000, 'max_output' => 1400],
        'planner' => ['timeout' => 30, 'max_input' => 12000, 'max_output' => 500],
        'grace' => ['timeout' => 90, 'max_input' => 8000, 'max_output' => 800],  // 90s for Llama
    ],
    
    'retry' => ['max_attempts' => 2, 'delay_ms' => 1000],
];
```

### config/codexflow.php
Full plan definitions with:
- Monthly quotas (fast/deep)
- Daily safety caps
- Grace daily limits
- Planner pool
- Admission control
- Decompose settings
- Rate limits
- Retention settings
- Cost per tier (USD per 1M tokens)
- API key settings

Do NOT implement controllers/services yet.
Implement only schema, models, config.
```

---

### PART 2: Auth + API Keys + Middleware

```markdown
# PART 2/6 — Auth + API Keys + Middleware

You are implementing CodexFlow.dev MVP on Laravel 12.

## ABSOLUTE OUTPUT RULES:
- Output ONLY unified diff patches
- New files: --- /dev/null
- No prose outside diffs
- Secrets via ENV only

## GOAL:
Implement auth skeleton + project API key issuance + middleware chain.

## REQUIREMENTS:

### 1) Sanctum Auth (minimal):
- POST /auth/login (email + password → token)
- POST /auth/logout
- GET /auth/me
- Block suspended users
- Roles: admin|customer

### 2) Project API Keys:
- POST /v1/projects/{id}/keys → returns plaintext ONCE
- GET /v1/projects/{id}/keys → list (no plaintext)
- DELETE /v1/projects/{id}/keys/{keyId} → revoke
- Key format: cf_ + 40 random chars
- Store: key_prefix (first 12 chars) + key_hash (bcrypt)
- Timing-safe comparison

### 3) Middleware Chain:
Order matters for gateway:

1. **RateLimitMiddleware**
   - Redis-based
   - Per-key: 60/min
   - Per-user: 120/min
   - Return 429 + Retry-After header

2. **RequestIdMiddleware**
   - Read X-Request-Id header or generate UUID
   - Attach to request for forwarding

3. **AuthenticateProjectApiKey**
   - Read Authorization: Bearer <key>
   - Lookup by key_prefix first (performance)
   - Then bcrypt verify full key
   - Attach: project, apiKey, user to request
   - 401 on invalid/revoked

4. **CheckUserStatus**
   - Verify user.status === 'active'
   - Verify subscription active and not expired
   - 403 on suspended/expired

5. **QuotaCheckMiddleware** (basic, PART 3 implements full)
   - Placeholder that passes through
   - Will be enhanced in PART 3

### 4) Route Skeleton:
```php
// routes/api.php

// Auth routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

// Project API Key routes (requires auth)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/v1/projects/{project}/keys', [ApiKeyController::class, 'store']);
    Route::get('/v1/projects/{project}/keys', [ApiKeyController::class, 'index']);
    Route::delete('/v1/projects/{project}/keys/{key}', [ApiKeyController::class, 'destroy']);
});

// Gateway route (API key auth, not Sanctum)
Route::post('/v1/chat/completions', [GatewayController::class, 'complete'])
    ->middleware([
        'rate.limit',
        'request.id',
        'auth.api_key',
        'check.user_status',
        'quota.check',
    ]);
```

### 5) Services:
- App\Services\ApiKey\KeyGenerator
- App\Services\ApiKey\KeyValidator

Do NOT implement LLM calling yet.
Do NOT implement full quota logic yet.
```

---

### PART 3: Gateway Core + Decompose Pipeline

```markdown
# PART 3/6 — Gateway Core + Decompose Pipeline

You are implementing CodexFlow.dev MVP on Laravel 12.

## ABSOLUTE OUTPUT RULES:
- Output ONLY unified diff patches
- New files: --- /dev/null
- No prose outside diffs
- Try/catch only in controllers/jobs

## GOAL:
Implement the core gateway with Decompose Pipeline.

## ENDPOINTS:
POST /v1/chat/completions (OpenAI-compatible)

## DECOMPOSE PIPELINE:

### Trigger Conditions (auto OR manual):
- Header: x-decompose: 1 (forced)
- OR estimated_input_tokens >= 8000
- OR messages char_length >= 24000

### Pipeline Steps:

1. **LargeRequestDetector**
   - Estimate input tokens (chars / 4)
   - Check trigger conditions
   - Return: shouldDecompose boolean

2. **PlannerService** (if decompose)
   - Call cf-planner (GPT-5 nano)
   - System prompt: strict JSON schema
   - Parse JSON response
   - Validate: max 3 chunks, max 5 files each
   - Return: DecomposePlan object

3. **ChunkRunner**
   - For each chunk in plan:
     - Select tier (fast/deep from plan)
     - Build minimal context (only needed files)
     - Call LiteLLM with chunk prompt
     - Enforce max_output_tokens
     - Collect diff output
   - Return: ChunkResult[]

4. **DecompositionOrchestrator**
   - Coordinate: 1 planner + max 3 chunks = 4 calls max
   - Budget checks at each step
   - Timeout: 8 minutes total
   - Merge results into single response

### Regular Flow (small requests):

1. **TierSelector**
   - Default: fast
   - Header x-quality: deep → deep
   - Quota exhausted → fallback chain

2. **AdmissionController**
   - Clamp input/output tokens per tier
   - Reject if exceeds hard limits
   
3. **QuotaService**
   - Check monthly + daily limits
   - Pre-authorize estimated tokens (Redis DECRBY)
   - Post-adjust with actual tokens
   - Fallback: deep→fast→grace

4. **CacheManager**
   - Only if: temperature=0 AND stream=false
   - Key: sha256(version + normalized_payload + tier)
   - Cache hit → still log telemetry

5. **LiteLLMClient**
   - POST {base_url}/v1/chat/completions
   - Forward: X-Request-Id, Authorization
   - Parse response or stream
   - Map errors to exceptions

6. **StreamHandler** (if stream=true)
   - SSE passthrough from LiteLLM
   - Parse final message for usage
   - Decrement quota after stream ends

7. **TelemetryLogger**
   - Log every attempt to llm_requests
   - For decompose: link chunks to parent_request_id
   - Record: tokens, cost, latency, tier, status

## QUOTA LOGIC:

### Pre-authorization:
```php
// Estimate input tokens
$estimated = strlen(json_encode($messages)) / 4;

// Reserve in Redis (atomic)
$remaining = Redis::decrby("quota:{$tier}:{$userId}:{$month}", $estimated);
if ($remaining < 0) {
    Redis::incrby(...); // Rollback
    throw new QuotaExceededException();
}
```

### Post-adjustment:
```php
// After LLM response
$actual = $response->usage->total_tokens;
$delta = $actual - $estimated;
Redis::decrby("quota:{$tier}:{$userId}:{$month}", $delta);

// Async sync to DB
dispatch(new SyncQuotaToDbJob($userId, $month));
```

### Fallback Chain:
```
deep exhausted → try fast
fast exhausted → try grace (daily only)
grace exhausted → 429 + Retry-After
```

## ERROR HANDLING:

### Exception Types:
- QuotaExceededException → 429
- RateLimitException → 429 + Retry-After
- TimeoutException → 504
- ProviderException → 502
- AdmissionRejectedException → 400
- DecomposeFailedException → 500

### Response Format:
```json
{
  "error": {
    "message": "...",
    "type": "quota_exceeded",
    "code": "quota_monthly_fast_exhausted",
    "retry_after": 3600
  }
}
```

## FILE STRUCTURE:
```
app/Services/Llm/
├── GatewayService.php           # Main orchestrator
├── LargeRequestDetector.php     # Check if decompose needed
├── PlannerService.php           # Call cf-planner
├── ChunkRunner.php              # Execute single chunk
├── DecompositionOrchestrator.php # Manage decompose flow
├── TierSelector.php             # fast/deep/grace selection
├── AdmissionController.php      # Token clamping
├── LiteLLMClient.php            # HTTP client
├── StreamHandler.php            # SSE handling
├── CacheManager.php             # Deterministic cache
└── TelemetryLogger.php          # Request logging

app/Services/Quota/
├── QuotaService.php             # Main quota logic
├── QuotaChecker.php             # Check availability
├── QuotaDecrementer.php         # Atomic decrement
└── QuotaSync.php                # Redis → DB sync

app/Exceptions/Llm/
├── LlmException.php             # Base
├── QuotaExceededException.php
├── RateLimitException.php
├── TimeoutException.php
├── ProviderException.php
├── AdmissionRejectedException.php
└── DecomposeFailedException.php
```

## CONTROLLER:
```php
class GatewayController extends Controller
{
    public function complete(ChatCompletionRequest $request): Response
    {
        try {
            $result = $this->gateway->process($request);
            
            if ($request->stream) {
                return $this->streamResponse($result);
            }
            
            return response()->json($result);
        } catch (LlmException $e) {
            return $this->errorResponse($e);
        }
    }
}
```

Implement full gateway with decompose support.
```

---

### PART 4: Usage + Jobs + Scheduler

```markdown
# PART 4/6 — Usage + Jobs + Scheduler

You are implementing CodexFlow.dev MVP on Laravel 12.

## ABSOLUTE OUTPUT RULES:
- Output ONLY unified diff patches
- New files: --- /dev/null
- No prose outside diffs

## GOAL:
Implement usage reporting + nightly aggregates + health monitoring.

## USAGE ENDPOINTS (Sanctum auth):

### GET /v1/usage/daily
Query params:
- from: YYYY-MM-DD (required)
- to: YYYY-MM-DD (required)
- project_id: optional filter

Response:
```json
{
  "data": [
    {
      "date": "2025-12-27",
      "project_id": 1,
      "tiers": {
        "fast": { "tokens": 50000, "requests": 100, "cost_usd": 0.05 },
        "deep": { "tokens": 10000, "requests": 20, "cost_usd": 0.15 },
        "grace": { "tokens": 5000, "requests": 10, "cost_usd": 0.002 }
      },
      "totals": { "tokens": 65000, "requests": 130, "cost_usd": 0.202 },
      "cache_hits": 15,
      "decomposed_requests": 5
    }
  ]
}
```

### GET /v1/usage/summary
Query params:
- month: YYYY-MM (default: current)

Response:
```json
{
  "month": "2025-12",
  "plan": "pro_1000_try",
  "quotas": {
    "fast": {
      "used": { "input": 2500000, "output": 500000, "requests": 800 },
      "limit": { "input": 6000000, "output": 1200000, "requests": 1500 },
      "remaining_percent": 58
    },
    "deep": { ... },
    "grace_today": { ... }
  },
  "totals": {
    "tokens": 3500000,
    "requests": 1200,
    "cost_usd": 5.50
  }
}
```

### GET /v1/usage/quota
Real-time quota status from Redis.

## JOBS:

### AggregateUsageDailyJob
- Schedule: Daily at 02:00
- Process: Aggregate llm_requests → usage_daily_aggregates
- Batch: Process in chunks of 1000
- Idempotent: Skip if already aggregated

### PruneLlmRequestsJob
- Schedule: Daily at 03:00
- Process: Delete llm_requests older than retention_days
- Retention: 21 days (config)
- Batch delete to avoid locks

### SyncQuotaToDbJob
- Triggered: After each request (async)
- Process: Sync Redis quota counters to DB
- Debounced: Max once per minute per user

### RefreshHealthJob
- Schedule: Every 5 minutes
- Process: 
  - Query recent llm_requests (last 5 min)
  - Calculate success/error/timeout rates per tier
  - Store in Redis cache
  - Alert if error rate > 10%

## SCHEDULER:
```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    $schedule->job(new AggregateUsageDailyJob)
        ->dailyAt('02:00')
        ->withoutOverlapping();
    
    $schedule->job(new PruneLlmRequestsJob)
        ->dailyAt('03:00')
        ->withoutOverlapping();
    
    $schedule->job(new RefreshHealthJob)
        ->everyFiveMinutes();
    
    // Subscription expiry check
    $schedule->command('subscriptions:check-expiry')
        ->dailyAt('00:00');
}
```

## ADMIN HEALTH ENDPOINT (admin only):

### GET /v1/admin/health/tiers
```json
{
  "tiers": {
    "fast": {
      "status": "healthy",
      "success_rate": 98.5,
      "avg_latency_ms": 450,
      "requests_5min": 120
    },
    "deep": {
      "status": "degraded",
      "success_rate": 92.0,
      "avg_latency_ms": 2100,
      "requests_5min": 30,
      "issues": ["high_latency"]
    },
    "planner": { ... },
    "grace": { ... }
  },
  "last_updated": "2025-12-27T10:00:00Z"
}
```

### GET /v1/admin/health/deployments
Query LiteLLM /health endpoint and return status.

Implement all usage endpoints and jobs.
```

---

### PART 5: Dashboards (Landing + Customer + Admin)

```markdown
# PART 5/6 — Dashboards (Landing + Customer + Admin)

You are implementing CodexFlow.dev MVP on Laravel 12.

## ABSOLUTE OUTPUT RULES:
- Output ONLY unified diff patches
- New files: --- /dev/null
- No prose outside diffs

## STACK:
- Blade + Tailwind CSS
- Alpine.js for interactivity
- Chart.js for graphs

## 1) LANDING PAGE (/)

Modern, professional, Turkish market focused.

Sections:
- Hero: "Cursor AI için Türkçe LLM Gateway" + CTA
  - Primary CTA: "7 Gün Ücretsiz Dene" (büyük, yeşil)
  - Secondary CTA: "Fiyatları Gör" (outline)
- Features: Grace Lane, 3 Org Pool, TL Fiyatlandırma
- Pricing: 4 tier cards (Trial/Starter/Pro/Team)
  - Trial card: "ÜCRETSİZ" badge, "Kart Gerekmez" notu
- How to Connect: Code snippet for Cursor settings
- Trust signals: "500+ Geliştirici", "99.9% Uptime"
- FAQ: Collapsible
- Footer: Links, social

Trial CTA yerleşimi:
```
┌─────────────────────────────────────────────────────────────────────────┐
│                                HERO                                      │
│                                                                          │
│     "Cursor AI için Türkiye'nin LLM Gateway'i"                          │
│                                                                          │
│     ┌─────────────────────┐    ┌─────────────────────┐                  │
│     │  7 GÜN ÜCRETSİZ     │    │   Fiyatları Gör     │                  │
│     │       DENE          │    │                     │                  │
│     │   (Kart gerekmez)   │    │                     │                  │
│     └─────────────────────┘    └─────────────────────┘                  │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

Cursor connection snippet:
```json
{
  "openai.baseUrl": "https://api.codexflow.dev/v1",
  "openai.apiKey": "cf_your_api_key_here"
}
```

## 2) CUSTOMER DASHBOARD (/app)

### /app (Overview)
- Quota meters (circular progress)
  - Fast: used/total
  - Deep: used/total
  - Grace today: used/daily_limit
- Quick stats cards
  - Requests today
  - Tokens today
  - Cost this month
- Mini usage chart (last 7 days)

### /app/projects
- List projects (card grid)
- Create project modal
- Project status badges

### /app/projects/{id}
- Project details
- Settings (name, status)
- Delete project

### /app/projects/{id}/keys
- List API keys (masked)
- Create key → show once modal
- Revoke key button
- Last used timestamp

### /app/usage
- Date range picker
- Daily usage table
- Tier breakdown
- Export CSV button
- Usage chart (line graph)

### /app/billing
- Current plan
- Subscription status
- Payment history
- Upgrade/downgrade buttons

### /app/settings
- Profile (name, email)
- Change password
- Notification preferences

## 3) ADMIN DASHBOARD (/admin)

### /admin (Overview)
- Total users count
- Active subscriptions
- Today's requests
- Revenue this month
- System health status

### /admin/users
- User list (paginated, searchable)
- Status filter (active/suspended/pending)
- Actions: view, suspend, activate
- Subscription details

### /admin/users/{id}
- User details
- Projects list
- Usage history
- Subscription management
- Manual quota adjustment

### /admin/health
- Tier health cards
- Error rate graphs
- Latency graphs
- Recent errors list

### /admin/subscriptions
- All subscriptions list
- Expiring soon alert
- Revenue metrics

## UI COMPONENTS:

### Shared Layout
- Sidebar navigation
- Top bar with user menu
- Breadcrumbs
- Toast notifications

### Quota Meter Component
```blade
<x-quota-meter 
    label="Fast Tier"
    :used="$quota->fast_used"
    :total="$quota->fast_limit"
    color="blue"
/>
```

### API Key Display
```blade
<x-api-key-card 
    :key="$apiKey"
    :showActions="true"
/>
```

## ROUTES:
```php
// Customer routes
Route::middleware(['auth', 'verified'])->prefix('app')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::resource('projects', ProjectController::class);
    Route::resource('projects.keys', ApiKeyController::class);
    Route::get('usage', [UsageController::class, 'index']);
    Route::get('billing', [BillingController::class, 'index']);
    Route::get('settings', [SettingsController::class, 'index']);
});

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index']);
    Route::resource('users', AdminUserController::class);
    Route::get('health', [AdminHealthController::class, 'index']);
    Route::get('subscriptions', [AdminSubscriptionController::class, 'index']);
});
```

Implement all UI pages with Tailwind + Alpine.js.
```

---

### PART 6: Tests + Infra + Deployment

```markdown
# PART 6/6 — Tests + Infra + Deployment

You are implementing CodexFlow.dev MVP on Laravel 12.

## ABSOLUTE OUTPUT RULES:
- Output ONLY unified diff patches
- New files: --- /dev/null
- No prose outside diffs

## 1) FEATURE TESTS

### Gateway Tests:
```php
// tests/Feature/GatewayTest.php

public function test_fast_tier_success()
{
    // Mock LiteLLM response
    Http::fake([
        'litellm:4000/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Hello']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]
        ])
    ]);
    
    $response = $this->withApiKey($this->apiKey)
        ->postJson('/v1/chat/completions', [
            'messages' => [['role' => 'user', 'content' => 'Hi']]
        ]);
    
    $response->assertOk();
    $this->assertDatabaseHas('llm_requests', ['tier' => 'fast']);
}

public function test_deep_tier_via_header()
{
    // x-quality: deep → deep tier
}

public function test_decompose_triggers_on_large_request()
{
    // 30K char request → should decompose
}

public function test_decompose_produces_multiple_chunks()
{
    // Verify planner + chunk calls
}

public function test_quota_exhausted_fallback_to_grace()
{
    // Exhaust fast+deep → grace
}

public function test_cache_hit_still_logs_telemetry()
{
    // temp=0, stream=false → cache hit
}

public function test_revoked_key_returns_401()
{
    // Revoked key → 401
}

public function test_rate_limit_returns_429()
{
    // Exceed rate limit → 429 + Retry-After
}

public function test_streaming_response()
{
    // stream=true → SSE response
}

public function test_concurrent_requests_quota_atomic()
{
    // 10 parallel requests → quota correctly decremented
}
```

### API Key Tests:
```php
// tests/Feature/ApiKeyTest.php

public function test_create_key_returns_plaintext_once()
public function test_list_keys_hides_plaintext()
public function test_revoke_key_works()
public function test_revoked_key_cannot_auth()
```

### Quota Tests:
```php
// tests/Feature/QuotaTest.php

public function test_monthly_quota_enforced()
public function test_daily_safety_cap_enforced()
public function test_grace_daily_limit_enforced()
public function test_quota_rollback_on_failure()
```

### Usage Tests:
```php
// tests/Feature/UsageTest.php

public function test_daily_usage_endpoint()
public function test_summary_endpoint()
public function test_aggregate_job_works()
```

## 2) UNIT TESTS

```php
// tests/Unit/

LargeRequestDetectorTest.php
PlannerServiceTest.php
ChunkRunnerTest.php
TierSelectorTest.php
AdmissionControllerTest.php
QuotaDecrementerTest.php
KeyValidatorTest.php
```

## 3) INFRA FILES

### docker-compose.yml
```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "8000:8000"
    environment:
      - DB_HOST=mysql
      - REDIS_HOST=redis
      - LITELLM_BASE_URL=http://litellm:4000
    depends_on:
      - mysql
      - redis
      - litellm
    volumes:
      - .:/var/www/html
  
  mysql:
    image: mysql:8
    environment:
      MYSQL_DATABASE: codexflow
      MYSQL_ROOT_PASSWORD: secret
    volumes:
      - mysql_data:/var/lib/mysql
  
  redis:
    image: redis:7-alpine
    command: redis-server --requirepass ${REDIS_PASSWORD}
  
  litellm:
    image: ghcr.io/berriai/litellm:main-latest
    ports:
      - "4000:4000"
    environment:
      - LITELLM_MASTER_KEY=${LITELLM_MASTER_KEY}
    volumes:
      - ./infra/litellm/proxy_config.yaml:/app/config.yaml
    command: --config /app/config.yaml

  queue:
    build: .
    command: php artisan queue:work --tries=3
    depends_on:
      - mysql
      - redis
    environment:
      - DB_HOST=mysql
      - REDIS_HOST=redis

volumes:
  mysql_data:
```

### infra/easypanel/README.md
```markdown
# EasyPanel Deployment Guide

## Prerequisites
- OVH KS4 server
- EasyPanel installed
- Domain pointed to server

## Services to Create:

1. **MySQL 8**
   - Name: codexflow-db
   - Persistent volume

2. **Redis 7**
   - Name: codexflow-redis
   - Password protected

3. **LiteLLM Proxy**
   - Name: codexflow-litellm
   - Image: ghcr.io/berriai/litellm:main-latest
   - Mount: proxy_config.yaml
   - Env vars: All API keys

4. **Laravel App**
   - Name: codexflow-app
   - Build from repo
   - Env vars: See .env.example

5. **Laravel Queue**
   - Name: codexflow-queue
   - Same image as app
   - Command: queue:work

## Environment Variables:
[Full list with descriptions]

## SSL Setup:
- Enable Let's Encrypt for api.codexflow.dev
- Force HTTPS redirect

## Monitoring:
- Enable EasyPanel metrics
- Set up Telegram alerts
```

### .env.example
```env
APP_NAME=CodexFlow
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api.codexflow.dev

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=codexflow
DB_USERNAME=codexflow
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=
REDIS_PORT=6379

LITELLM_BASE_URL=http://localhost:4000
LITELLM_MASTER_KEY=

ANTHROPIC_KEY_ORG_A=
ANTHROPIC_KEY_ORG_B=
ANTHROPIC_KEY_ORG_C=
OPENAI_API_KEY_PLANNER=
OPENAI_API_KEY_GRACE=
OPENROUTER_API_KEY=

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

## 4) CI/CD

### .github/workflows/tests.yml
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_DATABASE: testing
          MYSQL_ROOT_PASSWORD: secret
        ports:
          - 3306:3306
      redis:
        image: redis:7
        ports:
          - 6379:6379
    
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install
      - run: php artisan test --parallel
```

Implement all tests and infra configs.
```

---

## 6. DATABASE SCHEMA

Yukarıdaki PART 1'de detaylı şekilde tanımlandı. Özet:

| Tablo | Amaç |
|-------|------|
| users | Kullanıcılar |
| subscriptions | Abonelikler |
| projects | Projeler |
| project_api_keys | API anahtarları |
| quota_monthly | Aylık kota kullanımı |
| quota_daily | Günlük güvenlik limiti |
| llm_requests | Request telemetry |
| usage_daily_aggregates | Günlük aggregates |

**Önemli:** `llm_requests` tablosuna decompose için eklenen alanlar:
- `parent_request_id` (UUID nullable)
- `chunk_index` (tinyint nullable)
- `is_decomposed` (boolean)

---

## 7. SERVICE LAYER TASARIMI

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/
│   │   │   ├── GatewayController.php
│   │   │   ├── ProjectController.php
│   │   │   ├── ApiKeyController.php
│   │   │   └── UsageController.php
│   │   ├── Auth/
│   │   │   └── AuthController.php
│   │   ├── Dashboard/
│   │   │   ├── DashboardController.php
│   │   │   ├── ProjectController.php
│   │   │   └── UsageController.php
│   │   └── Admin/
│   │       ├── AdminController.php
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
│   │   ├── GatewayService.php              # Ana orkestratör
│   │   ├── LargeRequestDetector.php        # Decompose trigger
│   │   ├── PlannerService.php              # GPT-5 nano → JSON
│   │   ├── ChunkRunner.php                 # Claude → diff
│   │   ├── DecompositionOrchestrator.php   # Pipeline yönetimi
│   │   ├── TierSelector.php                # Tier seçimi
│   │   ├── AdmissionController.php         # Token clamp
│   │   ├── LiteLLMClient.php               # HTTP client
│   │   ├── StreamHandler.php               # SSE
│   │   ├── CacheManager.php                # Cache
│   │   └── TelemetryLogger.php             # Logging
│   │
│   ├── Quota/
│   │   ├── QuotaService.php
│   │   ├── QuotaChecker.php
│   │   ├── QuotaDecrementer.php
│   │   └── QuotaSync.php
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
├── Exceptions/Llm/
│   ├── LlmException.php
│   ├── QuotaExceededException.php
│   ├── RateLimitException.php
│   ├── TimeoutException.php
│   ├── ProviderException.php
│   ├── AdmissionRejectedException.php
│   └── DecomposeFailedException.php
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

## 8. CONFIG DOSYALARI

### config/litellm.php

```php
<?php

return [
    'base_url' => env('LITELLM_BASE_URL', 'http://localhost:4000'),
    'master_key' => env('LITELLM_MASTER_KEY'),
    
    'aliases' => [
        'fast' => 'cf-fast',
        'deep' => 'cf-deep',
        'planner' => 'cf-planner',
        'grace' => 'cf-grace',
    ],
    
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
        'planner' => [
            'timeout' => 30,
            'max_input_tokens' => 12000,
            'max_output_tokens' => 500,
        ],
        'grace' => [
            'timeout' => 60,
            'max_input_tokens' => 8000,
            'max_output_tokens' => 800,
        ],
    ],
    
    'retry' => [
        'max_attempts' => 2,
        'delay_ms' => 1000,
        'multiplier' => 2,
    ],
    
    'headers' => [
        'forward_request_id' => true,
        'request_id_header' => 'X-Request-Id',
    ],
];
```

### config/codexflow.php

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plan Definitions (%30 kar marjı hedefli)
    |--------------------------------------------------------------------------
    */
    'plans' => [
        // ==========================================
        // TRIAL PLAN - 24 Saat Ücretsiz Deneme
        // ==========================================
        'trial_free' => [
            'name' => 'Deneme (24 Saat)',
            'price_try' => 0,
            'is_trial' => true,
            'trial_hours' => 24,           // 24 saat!
            'requires_card' => false,      // Kart gerektirmiyor!
            'max_cost_usd' => 0.50,        // Max $0.50 risk
            
            // Fast/Deep kotaları (ilk test için)
            'trial_quotas' => [
                'fast' => [
                    'input_tokens' => 200_000,
                    'output_tokens' => 40_000,
                    'requests' => 100,
                ],
                'deep' => [
                    'input_tokens' => 50_000,
                    'output_tokens' => 10_000,
                    'requests' => 20,
                ],
            ],
            
            // Grace Lane - KOTA BİTİNCE LLAMA FREE İLE DEVAM!
            'grace_unlimited' => true,     // 24 saat boyunca sınırsız Llama
            'grace_fallback_on_quota_exhausted' => true,
            
            'planner_pool' => [
                'tokens' => 10_000,
            ],
            
            // Trial sonrası
            'on_expire' => 'suspend',
            'upgrade_prompt' => true,
            'upgrade_discount' => 0.10,    // 24 saat içinde %10 indirim
        ],
        
        'starter_500_try' => [
            'name' => 'Starter',
            'price_try' => 500,
            'target_margin' => 0.30,
            'max_cost_usd' => 10.00,
            'monthly_quotas' => [
                'fast' => [
                    'input_tokens' => 3_000_000,
                    'output_tokens' => 600_000,
                    'requests' => 800,
                ],
                'deep' => [
                    'input_tokens' => 200_000,
                    'output_tokens' => 40_000,
                    'requests' => 80,
                ],
            ],
            'daily_safety' => [
                'fast' => ['requests' => 40, 'tokens' => 120_000],
                'deep' => ['requests' => 4, 'tokens' => 18_000],
            ],
            'grace_daily' => [
                'requests' => 30,
                'tokens' => 80_000,
            ],
            'planner_pool' => [
                'monthly_tokens' => 50_000,
            ],
        ],
        
        'pro_1000_try' => [
            'name' => 'Pro',
            'price_try' => 1000,
            'target_margin' => 0.30,
            'max_cost_usd' => 20.00,
            'monthly_quotas' => [
                'fast' => [
                    'input_tokens' => 6_000_000,
                    'output_tokens' => 1_200_000,
                    'requests' => 1500,
                ],
                'deep' => [
                    'input_tokens' => 400_000,
                    'output_tokens' => 80_000,
                    'requests' => 150,
                ],
            ],
            'daily_safety' => [
                'fast' => ['requests' => 80, 'tokens' => 250_000],
                'deep' => ['requests' => 8, 'tokens' => 35_000],
            ],
            'grace_daily' => [
                'requests' => 50,
                'tokens' => 150_000,
            ],
            'planner_pool' => [
                'monthly_tokens' => 100_000,
            ],
        ],
        
        'team_2500_try' => [
            'name' => 'Team',
            'price_try' => 2500,
            'seats' => 5,
            'target_margin' => 0.30,
            'max_cost_usd' => 50.00,
            'monthly_quotas' => [
                'fast' => [
                    'input_tokens' => 15_000_000,
                    'output_tokens' => 3_000_000,
                    'requests' => 4000,
                ],
                'deep' => [
                    'input_tokens' => 1_000_000,
                    'output_tokens' => 200_000,
                    'requests' => 400,
                ],
            ],
            'daily_safety' => [
                'fast' => ['requests' => 200, 'tokens' => 600_000],
                'deep' => ['requests' => 20, 'tokens' => 90_000],
            ],
            'grace_daily' => [
                'requests' => 120,
                'tokens' => 400_000,
            ],
            'planner_pool' => [
                'monthly_tokens' => 250_000,
            ],
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Decompose Pipeline Settings
    |--------------------------------------------------------------------------
    */
    'decompose' => [
        'triggers' => [
            'header' => 'x-decompose',
            'min_input_tokens' => 8000,
            'min_char_length' => 24000,
        ],
        'limits' => [
            'max_planner_calls' => 1,
            'max_chunks' => 3,
            'max_total_calls' => 4,
            'max_files_per_chunk' => 5,
            'total_timeout_seconds' => 480,
        ],
        'chunk_limits' => [
            'fast' => ['max_output_tokens' => 700, 'timeout' => 60],
            'deep' => ['max_output_tokens' => 1200, 'timeout' => 120],
        ],
        'planner' => [
            'max_output_tokens' => 500,
            'timeout' => 30,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Admission Control
    |--------------------------------------------------------------------------
    */
    'admission' => [
        'fast' => ['max_input' => 8000, 'max_output' => 900, 'timeout' => 60],
        'deep' => ['max_input' => 16000, 'max_output' => 1400, 'timeout' => 120],
        'grace' => ['max_input' => 8000, 'max_output' => 800, 'timeout' => 60],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'version' => 'v1',
        'only_deterministic' => true,
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
        'fast' => ['input' => 0.80, 'output' => 4.00],
        'deep' => ['input' => 3.00, 'output' => 15.00],
        'planner' => ['input' => 0.15, 'output' => 0.60],
        'grace' => ['input' => 0.00, 'output' => 0.00],           // Llama FREE!
        'grace_fallback' => ['input' => 0.15, 'output' => 0.60],  // GPT-4o-mini
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Key Settings
    |--------------------------------------------------------------------------
    */
    'api_keys' => [
        'prefix' => 'cf_',
        'length' => 40,
        'hash_algo' => 'bcrypt',
    ],
];
```

---

## 9. TEST STRATEJİSİ

### Test Kategorileri

| Kategori | Dosya Sayısı | Kapsam |
|----------|--------------|--------|
| Feature/Gateway | 10+ | Gateway flow, decompose, quota |
| Feature/Auth | 5+ | Login, API key auth |
| Feature/Usage | 5+ | Usage endpoints, aggregation |
| Unit/Services | 15+ | Her service için |
| Integration | 5+ | LiteLLM mock tests |

### Kritik Test Senaryoları

1. **Decompose Trigger:** 24K+ karakter → otomatik decompose
2. **Chunk Budget:** Max 3 chunk, aşılırsa hata
3. **Quota Atomic:** 10 concurrent request → doğru decrement
4. **Grace Fallback:** Fast+deep biter → grace aktif
5. **Cache Hit:** Temp=0 → cache, hala log yazılır

---

## 10. DEPLOYMENT & MONITORING

### EasyPanel Servis Listesi

| Servis | Image | Port |
|--------|-------|------|
| codexflow-app | Laravel | 8000 |
| codexflow-queue | Laravel | - |
| codexflow-litellm | LiteLLM | 4000 |
| codexflow-mysql | MySQL 8 | 3306 |
| codexflow-redis | Redis 7 | 6379 |

### Monitoring

- **Grafana:** LiteLLM + Laravel metrics
- **Sentry:** Error tracking
- **Telegram:** Alert notifications

### Alert Kuralları

| Kural | Eşik | Aksiyon |
|-------|------|---------|
| Error Rate > 10% | 5 dakika | Telegram alert |
| Latency p95 > 5s | 5 dakika | Telegram alert |
| Quota > 90% | Günlük | Email to user |
| Subscription expiring | 3 gün | Email to user |

---

## 📋 ÖZET: UYGULAMA SIRASI

```
HAFTA 1-2:
├── PART 0: LiteLLM proxy_config.yaml
├── PART 1: Migrations + Models + Config
└── Test: LiteLLM curl ile çalışıyor

HAFTA 3:
├── PART 2: Auth + API Keys + Middleware
└── Test: API key ile auth geçiyor

HAFTA 4-5:
├── PART 3: Gateway Core + Decompose Pipeline
└── Test: /v1/chat/completions çalışıyor

HAFTA 6:
├── PART 4: Usage + Jobs
└── Test: Usage endpoints + aggregation

HAFTA 7:
├── PART 5: Dashboards
└── Test: UI'lar render ediyor

HAFTA 8:
├── PART 6: Tests + Infra
└── Test: All tests green

HAFTA 9:
├── Beta: 5-10 kullanıcı
├── Bug fixes
└── LAUNCH! 🚀
```

---

**HAZIR OLDUĞUNDA "BAŞLA" DE, PART 0'DAN KODLAMAYA GEÇELİM!** 🚀

---

*Final Proje Planı v2.0*  
*Decompose Pipeline Edition*  
*27 Aralık 2025*

