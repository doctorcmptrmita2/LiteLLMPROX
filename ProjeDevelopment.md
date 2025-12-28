# 🚀 CODEXFLOW.DEV — PROFESYONEL GELİŞTİRME RAPORU

> **Versiyon:** 3.0 (Role-Based Production Edition)  
> **Tarih:** 28 Aralık 2025  
> **Durum:** Stratejik Geliştirme Dokümanı  
> **Hedef Kar Marjı:** %25+ (Sürdürülebilir)  
> **Odak:** Verimlilik • Karlılık • Müşteri Memnuniyeti

---

## 📋 İÇİNDEKİLER

1. [Yönetici Özeti](#1-yönetici-özeti)
2. [Mevcut Durum Analizi](#2-mevcut-durum-analizi)
3. [Profesyonel Model Kurgusu](#3-profesyonel-model-kurgusu)
4. [Model Registry & Maliyet Matrisi](#4-model-registry--maliyet-matrisi)
5. [Role-Based Pipeline Mimarisi](#5-role-based-pipeline-mimarisi)
6. [Routing & Fallback Stratejisi](#6-routing--fallback-stratejisi)
7. [Quality Gates & Guardrails](#7-quality-gates--guardrails)
8. [Maliyet Optimizasyonu (%25+ Kar)](#8-maliyet-optimizasyonu-25-kar)
9. [Müşteri Memnuniyeti Stratejisi](#9-müşteri-memnuniyeti-stratejisi)
10. [Uygulama Yol Haritası](#10-uygulama-yol-haritası)
11. [Risk Analizi & Mitigasyon](#11-risk-analizi--mitigasyon)
12. [KPI & Başarı Metrikleri](#12-kpi--başarı-metrikleri)
13. [Teknik Implementasyon Detayları](#13-teknik-implementasyon-detayları)

---

## 1. YÖNETİCİ ÖZETİ

### 🎯 Projenin Amacı

**CodexFlow.dev**, Cursor AI kullanıcıları için TL bazlı, yüksek performanslı bir LLM Gateway platformudur. Bu rapor, projenin:

- **Verimlilik:** Role-based pipeline ile optimal model kullanımı
- **Karlılık:** %25+ kar marjı garantisi
- **Müşteri Memnuniyeti:** Kalite kapıları ve SLA garantileri

hedeflerine ulaşması için stratejik yol haritasını sunar.

### 📊 Kritik Değişiklikler (v2 → v3)

| Özellik | Mevcut (v2) | Önerilen (v3) | Kazanım |
|---------|-------------|---------------|---------|
| Model Sayısı | 6 alias | 11 model (role-based) | Daha hassas model seçimi |
| Pipeline | Tier-based (fast/deep) | Role-based (7 agent) | Kalite artışı |
| Routing | Basit tier seçimi | Risk/budget scoring | Maliyet optimizasyonu |
| Quality Gates | Yok | 5 zorunlu kapı | Hata azaltma |
| Kar Marjı Hedefi | %30 | %25+ garantili | Sürdürülebilirlik |
| Müşteri Deneyimi | Temel | Premium (SLA) | Retention artışı |

### 💰 Finansal Projeksiyon

```
MEVCUT MODEL (Basit Tier):
├── Gelir: 1000 TL/kullanıcı
├── Ortalama Maliyet: ~450 TL
├── Kar Marjı: ~%55 (değişken)
└── Risk: Yüksek (büyük request maliyeti patlar)

ÖNERİLEN MODEL (Role-Based):
├── Gelir: 1000 TL/kullanıcı
├── Kontrollü Maliyet: ~650 TL (max)
├── Kar Marjı: %25+ (garantili)
├── Risk: Düşük (cost cap + fallback)
└── Ekstra: Daha yüksek kalite → Retention artışı
```

---

## 2. MEVCUT DURUM ANALİZİ

### 2.1 Mevcut Alias Yapısı

```yaml
# infra/litellm/proxy_config.yaml (mevcut)
model_list:
  - cf-fast        → Claude Haiku 3.5      (3 org key pool)
  - cf-deep        → Claude Sonnet 4       (3 org key pool)
  - cf-planner     → GPT-4o-mini           (OpenRouter)
  - cf-grace       → Llama 3.1 405B FREE   (OpenRouter)
  - cf-grace-fallback → GPT-4o-mini        (OpenRouter)
  - cf-agent       → Grok 3 Beta           (OpenRouter)
```

### 2.2 Mevcut Routing Mantığı

```php
// TierSelector.php - Basit tier seçimi
1. User fast istedi → fast quota var mı? → fast
2. User deep istedi → deep quota var mı? → deep, yoksa fast
3. Her ikisi de bitti → grace (Llama FREE)
4. Grace de bitti → 429 + Retry-After
```

### 2.3 Mevcut Durumun Güçlü Yanları ✅

| Güç | Açıklama |
|-----|----------|
| Grace Lane | Llama 405B FREE ile %90 tasarruf |
| 3 Org Key Pool | Rate limit dağıtımı |
| Redis Quota | Atomic decrement |
| Streaming | SSE desteği |
| Decompose | Büyük request bölme (disabled) |

### 2.4 Mevcut Durumun Zayıf Yanları ❌

| Zayıflık | Risk | Çözüm |
|----------|------|-------|
| Tek model her iş | Aşırı harcama | Role-based seçim |
| Review yok | Hatalı kod | Review agent |
| Test üretimi yok | Regresyon | Test agent |
| Risk skorlama yok | Critical işlerde hata | Triage agent |
| Quality gate yok | Merge edilemez kod | 5 kapı |
| Decompose disabled | Büyük requestler patlar | Yeniden etkinleştir |

### 2.5 Maliyet Analizi (Mevcut)

```
PRO PLAN - WORST CASE (Tüm kota kullanılırsa):

FAST (Claude Haiku 3.5):
  Input:  6,000,000 × $0.80 / 1M = $4.80
  Output: 1,200,000 × $4.00 / 1M = $4.80
  Subtotal: $9.60

DEEP (Claude Sonnet 4):
  Input:    400,000 × $3.00 / 1M = $1.20
  Output:    80,000 × $15.00 / 1M = $1.20
  Subtotal: $2.40

GRACE (Llama FREE + %10 fallback):
  ~$0.10

TOPLAM: ~$12.10 × 35 TL = ~425 TL
GELİR: 1000 TL
KAR: 575 TL (%57.5)

SORUN: Büyük requestlerde output şişmesi
kontrol edilemiyor → Kar %20'ye düşebilir!
```

---

## 3. PROFESYONEL MODEL KURGUSU

### 3.1 Temel Prensip: "Doğru İş İçin Doğru Model"

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       ROLE-BASED MODEL SEÇİMİ                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  MEVCUT (Tier-Based):                                                       │
│  ─────────────────────                                                       │
│  User → [fast/deep] → Claude → Response                                     │
│                                                                              │
│  ÖNERİLEN (Role-Based):                                                     │
│  ──────────────────────                                                      │
│  User Request                                                                │
│       ↓                                                                      │
│  ┌─────────────┐  JSON    ┌─────────────┐                                   │
│  │ TRIAGE      │ ───────→ │ PLANNER     │                                   │
│  │ (GPT-5 nano)│          │ (Grok Fast) │                                   │
│  └─────────────┘          └──────┬──────┘                                   │
│                                  │ Step Plan                                 │
│                                  ↓                                           │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                           CODING AGENT                               │   │
│  │  ┌─────────────┐   ┌─────────────┐   ┌─────────────┐                │   │
│  │  │ Codex-mini  │   │  Sonnet 4   │   │ Sonnet 4.5  │                │   │
│  │  │ (cheap)     │   │ (balanced)  │   │ (premium)   │                │   │
│  │  └─────────────┘   └─────────────┘   └─────────────┘                │   │
│  └───────────────────────────────────────────────────┬─────────────────┘   │
│                                                      │ Unified Diff         │
│                                                      ↓                      │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                          REVIEW AGENT                                │   │
│  │  DeepSeek V3.2 (budget) │ Sonnet 4.5 (critical)                     │   │
│  │  → must_fix / should_fix / nice_to_have                             │   │
│  └───────────────────────────────────────────────────┬─────────────────┘   │
│                                                      │ Checklist            │
│                                                      ↓                      │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                           TEST AGENT                                 │   │
│  │  Codex-mini → Unit/Feature Tests                                    │   │
│  └───────────────────────────────────────────────────┬─────────────────┘   │
│                                                      │ Test Files           │
│                                                      ↓                      │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                        FINAL REVIEW                                  │   │
│  │  → DONE (must_fix = 0)                                               │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.2 7 Agent Rolü

| # | Agent | Model | Görev | Çıktı |
|---|-------|-------|-------|-------|
| 1 | **Triage** | GPT-5 nano | İstek sınıflandırma | JSON (task_type, risk, budget) |
| 2 | **Planner** | Grok 4.1 Fast | 3-12 adımlık plan | Step Plan + Context List |
| 3 | **Coding** | Sonnet 4/4.5 veya Codex-mini | Kod üretimi | Unified Diff (ONLY) |
| 4 | **Review** | DeepSeek V3.2 veya Sonnet 4.5 | Risk analizi | Checklist |
| 5 | **Test** | Codex-mini | Test yazımı | Test Files + How-to |
| 6 | **UI/Vision** | GPT-4o mini / Qwen3-VL | Screenshot analizi | UI Önerileri |
| 7 | **Final Review** | DeepSeek V3.2 | Son kontrol | DONE / REWORK |

### 3.3 Neden Bu Yaklaşım?

```
MALIYET KARŞILAŞTIRMASI (Aynı iş için):

MEVCUT (Tek Sonnet 4 çağrısı):
├── Input: 15K token × $3/1M = $0.045
├── Output: 8K token × $15/1M = $0.12
├── TOPLAM: $0.165
├── Retry (başarısızlık): +$0.165
└── WORST: $0.33

ÖNERİLEN (Role-Based Pipeline):
├── Triage: ~$0.001 (nano)
├── Planner: ~$0.005 (Grok Fast)
├── Coding: ~$0.08 (Sonnet 4, clamped output)
├── Review: ~$0.01 (DeepSeek)
├── Test: ~$0.02 (Codex-mini)
├── TOPLAM: ~$0.116
├── Retry (sadece fail eden step): +$0.03
└── WORST: $0.146

TASARRUF: %56 + Daha yüksek kalite!
```

---

## 4. MODEL REGISTRY & MALİYET MATRİSİ

### 4.1 Canonical Model Registry

```yaml
# Önerilen: codexflow.policy.yaml → models section

models:
  # === CHEAP TIER ===
  gpt5_nano:
    provider: "openai"
    model_id: "gpt-5-nano"  # veya gpt-4o-mini
    role_tags: ["triage", "planner_fallback"]
    cost_per_1m:
      input: $0.15
      output: $0.60
    context_window: 128K
    notes: "Ucuz, hızlı. Sadece JSON/sınıflandırma için."

  gpt5_1_codex_mini:
    provider: "openai"
    model_id: "gpt-5.1-codex-mini"  # veya o3-mini
    role_tags: ["cheap_coder", "test_writer"]
    cost_per_1m:
      input: $0.15
      output: $0.60
    context_window: 128K
    notes: "Küçük kod işleri, test yazımı."

  # === AGENT/PLANNER TIER ===
  grok_4_1_fast:
    provider: "x-ai"  # OpenRouter üzerinden
    model_id: "x-ai/grok-4.1-fast"
    role_tags: ["planner", "agent", "reasoning"]
    cost_per_1m:
      input: $3.00
      output: $15.00
    context_window: 2M  # Devasa context!
    notes: "En iyi agentic model. Plan/reasoning için ideal."

  # === MAIN CODING TIER ===
  sonnet_4:
    provider: "anthropic"
    model_id: "claude-sonnet-4"
    role_tags: ["main_coder_secondary", "balanced"]
    cost_per_1m:
      input: $3.00
      output: $15.00
    context_window: 200K
    notes: "Balanced coding. 2-5 dosya değişikliği."

  sonnet_4_5:
    provider: "anthropic"
    model_id: "claude-sonnet-4-5-20250929"  # Yayın: 29 Eylül 2025
    role_tags: ["main_coder", "premium_reviewer", "critical"]
    cost_per_1m:
      input: $3.00
      output: $15.00
    context_window: 200K
    notes: "Premium. Sadece high/critical risk için. SWE-bench'te top performer."

  # === REVIEW/FALLBACK TIER ===
  deepseek_v3_2:
    provider: "deepseek"  # OpenRouter
    model_id: "deepseek/deepseek-v3.2"
    role_tags: ["budget_reviewer", "open_fallback"]
    cost_per_1m:
      input: $0.14
      output: $0.28
    context_window: 64K
    notes: "Ucuz review. İyi kalite/fiyat oranı."

  qwen3_coder:
    provider: "qwen"  # OpenRouter
    model_id: "qwen/qwen3-coder"
    role_tags: ["oss_fallback_coder"]
    cost_per_1m:
      input: $0.14
      output: $0.28
    context_window: 128K
    notes: "OSS fallback. Provider sorunlarında."

  # === VISION TIER ===
  gpt4o_mini:
    provider: "openai"
    model_id: "gpt-4o-mini"
    role_tags: ["vision_quick"]
    cost_per_1m:
      input: $0.15
      output: $0.60
    context_window: 128K
    notes: "Hızlı UI screenshot analizi."

  qwen3_vl:
    provider: "qwen"
    model_id: "qwen/qwen3-vl-235b"
    role_tags: ["vision_deep"]
    cost_per_1m:
      input: $1.00
      output: $3.00
    context_window: 128K
    notes: "Derin multimodal analiz."

  llama4_maverick:
    provider: "meta"  # OpenRouter
    model_id: "meta-llama/llama-4-maverick"
    role_tags: ["vision_long_context"]
    cost_per_1m:
      input: $0.20
      output: $0.80
    context_window: 1M
    notes: "Çok uzun multimodal doküman."

  # === FREE TIER ===
  llama_405b_free:
    provider: "openrouter"
    model_id: "meta-llama/llama-3.1-405b-instruct:free"
    role_tags: ["grace_lane", "free"]
    cost_per_1m:
      input: $0.00
      output: $0.00
    context_window: 128K
    notes: "ÜCRETSİZ! Grace lane için."
```

### 4.2 Maliyet Karşılaştırma Tablosu

| Model | Input/1M | Output/1M | Toplam (10K in + 2K out) | Kullanım |
|-------|----------|-----------|---------------------------|----------|
| GPT-5 nano | $0.15 | $0.60 | $0.0027 | Triage |
| Codex-mini | $0.15 | $0.60 | $0.0027 | Test, küçük fix |
| DeepSeek V3.2 | $0.14 | $0.28 | $0.0020 | Budget review |
| Qwen3 Coder | $0.14 | $0.28 | $0.0020 | OSS fallback |
| GPT-4o mini | $0.15 | $0.60 | $0.0027 | Vision quick |
| **Llama 405B** | **$0** | **$0** | **$0** | **Grace** |
| Sonnet 4 | $3.00 | $15.00 | $0.060 | Balanced coding |
| Sonnet 4.5 | $3.00 | $15.00 | $0.060 | Premium |
| Grok 4.1 Fast | $3.00 | $15.00 | $0.060 | Planner/Agent |

### 4.3 LiteLLM Alias Güncellemesi (Önerilen)

```yaml
# infra/litellm/proxy_config.yaml - UPDATED

model_list:
  # === TRIAGE (GPT-5 nano / GPT-4o-mini) ===
  - model_name: cf-triage
    litellm_params:
      model: openrouter/openai/gpt-4o-mini
      api_key: os.environ/OPENROUTER_API_KEY
      timeout: 15
      max_tokens: 800

  # === PLANNER (Grok 4.1 Fast) ===
  - model_name: cf-planner
    litellm_params:
      model: openrouter/x-ai/grok-4.1-fast
      api_key: os.environ/OPENROUTER_API_KEY
      timeout: 45
      max_tokens: 2000

  # === CHEAP CODER (Codex-mini) ===
  - model_name: cf-cheap-coder
    litellm_params:
      model: openrouter/openai/o3-mini  # veya gpt-5.1-codex-mini
      api_key: os.environ/OPENROUTER_API_KEY
      timeout: 60
      max_tokens: 3000

  # === BALANCED CODER (Sonnet 4) - 3 key pool ===
  - model_name: cf-balanced-coder
    litellm_params:
      model: anthropic/claude-sonnet-4-20250514
      api_key: os.environ/ANTHROPIC_KEY_ORG_A
      timeout: 120
      max_tokens: 6000

  - model_name: cf-balanced-coder
    litellm_params:
      model: anthropic/claude-sonnet-4-20250514
      api_key: os.environ/ANTHROPIC_KEY_ORG_B
      timeout: 120

  - model_name: cf-balanced-coder
    litellm_params:
      model: anthropic/claude-sonnet-4-20250514
      api_key: os.environ/ANTHROPIC_KEY_ORG_C
      timeout: 120

  # === PREMIUM CODER (Sonnet 4.5) - 3 key pool ===
  - model_name: cf-premium-coder
    litellm_params:
      model: anthropic/claude-sonnet-4-5-20250929  # Yayın: 29 Eylül 2025
      api_key: os.environ/ANTHROPIC_KEY_ORG_A
      timeout: 180
      max_tokens: 6000

  - model_name: cf-premium-coder
    litellm_params:
      model: anthropic/claude-sonnet-4-5-20250929
      api_key: os.environ/ANTHROPIC_KEY_ORG_B
      timeout: 180

  - model_name: cf-premium-coder
    litellm_params:
      model: anthropic/claude-sonnet-4-5-20250929
      api_key: os.environ/ANTHROPIC_KEY_ORG_C
      timeout: 180

  # === BUDGET REVIEWER (DeepSeek V3.2) ===
  - model_name: cf-budget-reviewer
    litellm_params:
      model: openrouter/deepseek/deepseek-v3.2
      api_key: os.environ/OPENROUTER_API_KEY
      timeout: 90
      max_tokens: 2500

  # === OSS FALLBACK (Qwen3 Coder) ===
  - model_name: cf-oss-fallback
    litellm_params:
      model: openrouter/qwen/qwen3-coder
      api_key: os.environ/OPENROUTER_API_KEY
      timeout: 90
      max_tokens: 4000

  # === VISION QUICK (GPT-4o mini) ===
  - model_name: cf-vision-quick
    litellm_params:
      model: openrouter/openai/gpt-4o-mini
      api_key: os.environ/OPENROUTER_API_KEY
      timeout: 30
      max_tokens: 1800

  # === VISION DEEP (Qwen3-VL) ===
  - model_name: cf-vision-deep
    litellm_params:
      model: openrouter/qwen/qwen3-vl-235b-a22b-thinking
      api_key: os.environ/OPENROUTER_API_KEY
      timeout: 90
      max_tokens: 2500

  # === GRACE LANE (Llama 405B FREE) ===
  - model_name: cf-grace
    litellm_params:
      model: openrouter/meta-llama/llama-3.1-405b-instruct:free
      api_key: os.environ/OPENROUTER_API_KEY
      timeout: 90
      max_tokens: 2000

  # === GRACE FALLBACK (GPT-4o-mini) ===
  - model_name: cf-grace-fallback
    litellm_params:
      model: openrouter/openai/gpt-4o-mini
      api_key: os.environ/OPENROUTER_API_KEY
      timeout: 60
      max_tokens: 2000

# Fallback zincirleri
litellm_settings:
  fallbacks:
    - cf-premium-coder: [cf-balanced-coder, cf-oss-fallback]
    - cf-balanced-coder: [cf-cheap-coder, cf-oss-fallback]
    - cf-grace: [cf-grace-fallback]
    - cf-planner: [cf-triage]
```

---

## 5. ROLE-BASED PIPELINE MİMARİSİ

### 5.1 Pipeline Akışı

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                     CODEXFLOW ROLE-BASED PIPELINE                             │
└──────────────────────────────────────────────────────────────────────────────┘

                              ┌──────────────┐
                              │   REQUEST    │
                              │   GELDI      │
                              └──────┬───────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ STAGE 1: TRIAGE                                                              │
│ ────────────────                                                             │
│ Model: GPT-5 nano (cf-triage)                                               │
│ Fallback: Grok 4.1 Fast                                                      │
│ Max Output: 800 tokens                                                       │
│                                                                              │
│ GÖREV:                                                                       │
│ • Bu istek bugfix mi, feature mı, refactor mı?                              │
│ • Risk seviyesi: low / medium / high / critical                             │
│ • Tahmini dosya sayısı                                                      │
│ • Test ihtiyacı var mı?                                                     │
│ • Eksik bilgi var mı?                                                       │
│                                                                              │
│ ÇIKTI: task_intake.json                                                      │
│ {                                                                            │
│   "task_type": "feature",                                                    │
│   "risk": "medium",                                                          │
│   "files_estimate": 4,                                                       │
│   "domains": ["gateway", "quota"],                                          │
│   "needs_ui": false,                                                         │
│   "needs_deep_review": true,                                                │
│   "budget_class": "balanced",                                               │
│   "acceptance_criteria": ["..."],                                           │
│   "missing_info": []                                                        │
│ }                                                                            │
└─────────────────────────────────────────────────┬───────────────────────────┘
                                                  │
                    ┌─────────────────────────────┴─────────────────────────────┐
                    │                                                           │
                    ▼                                                           ▼
         ┌─────────────────┐                                      ┌─────────────────┐
         │ SIMPLE PATH     │                                      │ COMPLEX PATH    │
         │ (low risk,      │                                      │ (medium+ risk,  │
         │  1-2 dosya)     │                                      │  3+ dosya)      │
         └────────┬────────┘                                      └────────┬────────┘
                  │                                                        │
                  │                                                        ▼
                  │                          ┌────────────────────────────────────────┐
                  │                          │ STAGE 2: PLANNER                       │
                  │                          │ ──────────────────                      │
                  │                          │ Model: Grok 4.1 Fast (cf-planner)      │
                  │                          │ Fallback: GPT-5 nano                   │
                  │                          │ Max Output: 2000 tokens                │
                  │                          │                                        │
                  │                          │ GÖREV:                                 │
                  │                          │ • 3-12 adımlık plan                    │
                  │                          │ • Her adım için model seçimi          │
                  │                          │ • Gerekli context listesi              │
                  │                          │                                        │
                  │                          │ ÇIKTI: step_plan.json                  │
                  │                          │ {                                      │
                  │                          │   "steps": [                           │
                  │                          │     { "id": 1, "goal": "...",          │
                  │                          │       "model": "balanced",             │
                  │                          │       "context": ["file1", "file2"] } │
                  │                          │   ],                                   │
                  │                          │   "execution_order": "sequential"     │
                  │                          │ }                                      │
                  │                          └───────────────────────┬────────────────┘
                  │                                                  │
                  └──────────────────────────────────────────────────┤
                                                                     │
                                                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ STAGE 3: CODING                                                              │
│ ───────────────                                                              │
│                                                                              │
│ MODEL SEÇİMİ (budget_class'a göre):                                         │
│                                                                              │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │                         CODING AGENT SELECTOR                           │ │
│ ├─────────────────────────────────────────────────────────────────────────┤ │
│ │                                                                         │ │
│ │  budget_class = "cheap"                                                 │ │
│ │  ├── files_estimate ≤ 2                                                 │ │
│ │  ├── risk = low                                                         │ │
│ │  └── MODEL: cf-cheap-coder (Codex-mini)                                │ │
│ │                                                                         │ │
│ │  budget_class = "balanced"                                              │ │
│ │  ├── risk = medium                                                      │ │
│ │  ├── files_estimate = 2-5                                               │ │
│ │  └── MODEL: cf-balanced-coder (Sonnet 4)                               │ │
│ │                                                                         │ │
│ │  budget_class = "premium"                                               │ │
│ │  ├── risk = high/critical                                               │ │
│ │  ├── domains ∈ [auth, billing, webhooks, encryption]                   │ │
│ │  └── MODEL: cf-premium-coder (Sonnet 4.5)                              │ │
│ │                                                                         │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│ ÇIKTI FORMATI: SADECE UNIFIED DIFF                                          │
│ • Tam dosya dökümü YASAK                                                    │
│ • Minimal patch                                                              │
│ • Dosya yolları açık                                                        │
└─────────────────────────────────────────────────┬───────────────────────────┘
                                                  │
                                                  ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ STAGE 4: REVIEW                                                              │
│ ───────────────                                                              │
│                                                                              │
│ MODEL SEÇİMİ:                                                                │
│ • risk = low/medium → cf-budget-reviewer (DeepSeek V3.2)                    │
│ • risk = high/critical → cf-premium-coder (Sonnet 4.5)                      │
│                                                                              │
│ GÖREV:                                                                       │
│ • Mantıksal açık                                                            │
│ • Güvenlik riskleri                                                         │
│ • Yarış koşulları                                                           │
│ • Idempotency                                                               │
│ • Migration riskleri                                                        │
│                                                                              │
│ ÇIKTI: review_checklist.json                                                 │
│ {                                                                            │
│   "must_fix": ["SQL injection riski satır 45"],                             │
│   "should_fix": ["Error handling eksik"],                                   │
│   "nice_to_have": ["Daha açıklayıcı variable isimleri"],                    │
│   "test_gaps": ["Edge case: boş input"],                                    │
│   "risk_notes": ["Rate limit bypass mümkün"]                                │
│ }                                                                            │
│                                                                              │
│ KURAL: must_fix.length > 0 → CODING AGENT'E GERİ GÖNDERİLİR                 │
└─────────────────────────────────────────────────┬───────────────────────────┘
                                                  │
                        ┌─────────────────────────┴─────────────────────────────┐
                        │                                                       │
            must_fix > 0 │                                           must_fix = 0│
                        ▼                                                       ▼
         ┌─────────────────┐                                      ┌─────────────────┐
         │ REWORK          │                                      │ CONTINUE        │
         │ → Stage 3       │                                      │ → Stage 5       │
         └─────────────────┘                                      └────────┬────────┘
                                                                           │
                                                                           ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ STAGE 5: TEST WRITING                                                        │
│ ─────────────────────                                                        │
│                                                                              │
│ MODEL: cf-cheap-coder (Codex-mini)                                          │
│ Deep assist (high/critical): cf-balanced-coder (Sonnet 4)                   │
│                                                                              │
│ GÖREV:                                                                       │
│ • Unit test                                                                  │
│ • Feature test                                                               │
│ • Edge case coverage                                                        │
│ • Mock/stub yapıları                                                        │
│                                                                              │
│ ÇIKTI: test_files.patch + how_to_run.md                                      │
└─────────────────────────────────────────────────┬───────────────────────────┘
                                                  │
                                                  ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ STAGE 6: FINAL REVIEW                                                        │
│ ─────────────────────                                                        │
│                                                                              │
│ MODEL: cf-budget-reviewer (DeepSeek V3.2)                                   │
│ Premium (high/critical): cf-premium-coder (Sonnet 4.5)                      │
│                                                                              │
│ KONTROLLER:                                                                  │
│ ✓ Tüm must_fix çözüldü mü?                                                  │
│ ✓ Test coverage yeterli mi?                                                 │
│ ✓ Sensitive domain varsa risk_notes var mı?                                 │
│                                                                              │
│ SONUÇ: DONE veya REWORK (Stage 3'e geri)                                    │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 5.2 Stage Timeout & Token Limits

| Stage | Model | Max Input | Max Output | Timeout |
|-------|-------|-----------|------------|---------|
| Triage | GPT-5 nano | 8K | 800 | 15s |
| Planner | Grok 4.1 Fast | 100K | 2000 | 45s |
| Coding (cheap) | Codex-mini | 32K | 3000 | 60s |
| Coding (balanced) | Sonnet 4 | 100K | 6000 | 120s |
| Coding (premium) | Sonnet 4.5 | 100K | 6000 | 180s |
| Review (budget) | DeepSeek V3.2 | 64K | 2500 | 90s |
| Test | Codex-mini | 32K | 3500 | 90s |
| Final Review | DeepSeek V3.2 | 64K | 2000 | 60s |

---

## 6. ROUTING & FALLBACK STRATEJİSİ

### 6.1 Triage JSON Schema

```json
{
  "task_type": "bugfix|feature|refactor|review|test_only|ui_feedback|research",
  "risk": "low|medium|high|critical",
  "files_estimate": 3,
  "domains": ["auth", "billing", "gateway", "quota", "ui", "infra"],
  "needs_ui": false,
  "needs_deep_review": true,
  "budget_class": "cheap|balanced|premium",
  "acceptance_criteria": ["API 200 dönmeli", "Test pass etmeli"],
  "missing_info": []
}
```

### 6.2 Risk Skorlama Kuralları (Deterministic)

```yaml
# Risk belirleme (en yüksek kazanır)
risk_rules:

  - name: "critical_if_sensitive_domain"
    when:
      any_domain_in: ["auth", "billing", "payment", "webhooks", 
                      "encryption", "acl", "permissions"]
    set_risk: "critical"

  - name: "high_if_multifile_or_concurrency"
    when:
      any:
        - files_estimate_gte: 3
        - any_domain_in: ["queue", "cron", "concurrency", "caching", 
                          "rate_limit", "retry", "data_consistency"]
    set_risk: "high"

  - name: "medium_if_two_or_three_files"
    when:
      files_estimate_between: [2, 3]
    set_risk: "medium"

  - name: "low_default"
    when: { always: true }
    set_risk: "low"
```

### 6.3 Budget Class Belirleme

```yaml
budget_rules:

  - name: "premium_if_high_or_critical"
    when: 
      risk_in: ["high", "critical"]
    set_budget_class: "premium"

  - name: "balanced_if_medium_or_mid_scope"
    when:
      any:
        - risk_in: ["medium"]
        - files_estimate_between: [2, 5]
    set_budget_class: "balanced"

  - name: "cheap_default"
    when: { always: true }
    set_budget_class: "cheap"
```

### 6.4 Coding Agent Routing Matrix

| Koşul | Model | Alias |
|-------|-------|-------|
| budget=cheap AND files≤2 AND risk=low | Codex-mini | cf-cheap-coder |
| budget=balanced AND risk=medium | Sonnet 4 | cf-balanced-coder |
| budget=premium OR risk=high/critical | Sonnet 4.5 | cf-premium-coder |
| domains ∈ [auth, billing, webhooks] | Sonnet 4.5 | cf-premium-coder |
| files≥3 | Sonnet 4+ | cf-balanced/premium |

### 6.5 Fallback Zinciri

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         FALLBACK CHAIN                                       │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  cf-premium-coder (Sonnet 4.5) timeout/error                                │
│       ↓                                                                      │
│  cf-balanced-coder (Sonnet 4) 1 retry                                       │
│       ↓                                                                      │
│  cf-oss-fallback (Qwen3 Coder)                                              │
│       ↓                                                                      │
│  cf-cheap-coder (Codex-mini) - degraded quality uyarısı                     │
│                                                                              │
│  ─────────────────────────────────────────────────────────────────────────  │
│                                                                              │
│  cf-balanced-coder (Sonnet 4) timeout/error                                 │
│       ↓                                                                      │
│  cf-cheap-coder (Codex-mini)                                                │
│       ↓                                                                      │
│  cf-oss-fallback (Qwen3 Coder)                                              │
│                                                                              │
│  ─────────────────────────────────────────────────────────────────────────  │
│                                                                              │
│  cf-planner (Grok 4.1 Fast) timeout/error                                   │
│       ↓                                                                      │
│  cf-triage (GPT-5 nano) - basit plan                                        │
│                                                                              │
│  ─────────────────────────────────────────────────────────────────────────  │
│                                                                              │
│  KOTA BİTTİĞİNDE:                                                           │
│  premium budget bitti → balanced'a düş                                      │
│  balanced budget bitti → cheap'e düş                                        │
│  cheap budget bitti → cf-grace (Llama FREE)                                 │
│  grace bitti (günlük) → 429 + Retry-After                                   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 6.6 Reasoning Policy

```yaml
# Reasoning (extended thinking) ne zaman açık?

reasoning_policy:
  enable_when:
    any:
      - risk_in: ["high", "critical"]
      - task_type_in: ["research"]
      - domains_any_in: ["concurrency", "idempotency", "data_consistency"]
      - requires_root_cause_analysis: true

  disable_when:
    any:
      - risk_in: ["low"]
      - task_type_in: ["ui_feedback"]
      - files_estimate_lte: 1
      - budget_class: "cheap"

# Reasoning açıkken maliyet ~2x artar, dikkatli kullan
```

---

## 7. QUALITY GATES & GUARDRAILS

### 7.1 5 Zorunlu Kalite Kapısı

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         QUALITY GATES (BLOCKING)                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  GATE 1: PLAN REQUIRED                                                       │
│  ─────────────────────                                                       │
│  Stage: plan                                                                 │
│  Require: step_plan produced                                                 │
│  On Fail: Abort with "Plan generation failed"                               │
│                                                                              │
│  GATE 2: PATCH ONLY                                                          │
│  ─────────────────────                                                       │
│  Stage: code                                                                 │
│  Require: Output is unified diff                                            │
│  On Fail: Reject and retry with strict prompt                               │
│  Rules:                                                                      │
│    • MUST be unified diff format                                            │
│    • MUST include file paths                                                │
│    • MUST be minimal (no full-file dumps)                                   │
│    • NO unrelated reformatting                                              │
│                                                                              │
│  GATE 3: REVIEW MUST_FIX = 0                                                │
│  ─────────────────────────────                                               │
│  Stage: review                                                               │
│  Rule: must_fix.count === 0                                                  │
│  On Fail: Reroute to coding_agent with review checklist                     │
│  Max Iterations: 3 (sonra escalate)                                         │
│                                                                              │
│  GATE 4: TESTS REQUIRED (medium+ risk)                                      │
│  ───────────────────────────────────────                                     │
│  Stage: tests                                                                │
│  When: risk in ["medium", "high", "critical"]                               │
│  Require: test_files.patch + how_to_run                                      │
│  On Fail: Reroute to test_agent                                              │
│                                                                              │
│  GATE 5: SAFETY GATE (sensitive domains)                                    │
│  ───────────────────────────────────────────                                 │
│  Stage: final_review                                                         │
│  When: domains ∩ [auth, billing, payment, webhooks, encryption] ≠ ∅         │
│  Require:                                                                    │
│    • risk_notes section exists                                              │
│    • test_gaps section exists                                               │
│    • idempotency notes (if webhooks)                                        │
│  On Fail: Reroute to review_agent with explicit requirements                │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 7.2 Output Format Contracts

```yaml
output_contracts:

  triage_json:
    type: "json"
    schema: "task_intake.schema.json"
    max_tokens: 800

  step_plan:
    type: "structured_text"
    required_sections:
      - "Steps"
      - "Per-step Required Context"
      - "Per-step Output Format"
    max_tokens: 2000

  unified_diff:
    type: "patch"
    rules:
      - "MUST be unified diff"
      - "MUST include file paths"
      - "MUST be minimal"
      - "NO full-file dumps"
      - "NO unrelated reformatting"
    max_tokens: 6000

  review_checklist:
    type: "structured_text"
    required_sections:
      - "must_fix"
      - "should_fix"
      - "nice_to_have"
      - "test_gaps"
      - "risk_notes"
    max_tokens: 2500

  tests_with_howto:
    type: "structured_text+patch"
    required_sections:
      - "How to run tests"
      - "Edge cases covered"
    patch_required: true
    max_tokens: 3500
```

### 7.3 Güvenlik Hard Rules

```yaml
security:
  hard_rules:
    - "NEVER output secrets (.env values, API keys, tokens)"
    - "NEVER log secrets or instruct to log secrets"
    - "Always use parameter binding/ORM for SQL (no string concat)"
    - "Webhooks domain requires idempotency strategy"
    - "Auth changes require permission matrix documentation"
    - "Billing changes require rollback plan"
    - "Encryption changes require key rotation notes"

  required_review_domains:
    - "auth"
    - "billing"
    - "payment"
    - "webhooks"
    - "encryption"
    - "acl"
    - "permissions"
    - "migrations"  # destructive olanlar
```

---

## 8. MALİYET OPTİMİZASYONU (%25+ KAR)

### 8.1 Hedef Kar Marjı Hesabı

```
FORMÜL:
Kar Marjı = (Gelir - Maliyet) / Gelir × 100

HEDEF: %25 garantili kar marjı

PRO PLAN (1000 TL):
%25 = (1000 - Maliyet) / 1000 × 100
Maliyet = 750 TL (max)

DOLAR BAZINDA (~35 TL/USD):
750 TL = ~$21.5 max API maliyeti/kullanıcı/ay
```

### 8.2 Role-Based Pipeline Maliyet Analizi

```
SENARYO: Orta büyüklükte feature (5 dosya, medium risk)

MEVCUT SİSTEM (Tek Sonnet 4 çağrısı):
├── Input: 20K token × $3/1M = $0.06
├── Output: 10K token × $15/1M = $0.15
├── TOPLAM: $0.21
├── Retry (başarısızlık durumunda): +$0.21
├── Test yazımı (yok, manuel): +$0.00
├── Review (yok, manuel): +$0.00
└── WORST CASE: $0.42/request

× 150 request/ay = $63/ay
× 35 TL = 2205 TL > 1000 TL gelir → ZARAR!

─────────────────────────────────────────────────────────

ÖNERİLEN SİSTEM (Role-Based Pipeline):

├── Triage (GPT-5 nano):
│   Input: 5K × $0.15/1M = $0.00075
│   Output: 0.5K × $0.60/1M = $0.0003
│   Subtotal: $0.001

├── Planner (Grok 4.1 Fast):
│   Input: 10K × $3/1M = $0.03
│   Output: 1K × $15/1M = $0.015
│   Subtotal: $0.045

├── Coding (Sonnet 4 - balanced):
│   Input: 15K × $3/1M = $0.045
│   Output: 4K × $15/1M = $0.06  (clamped!)
│   Subtotal: $0.105

├── Review (DeepSeek V3.2):
│   Input: 8K × $0.14/1M = $0.001
│   Output: 1K × $0.28/1M = $0.0003
│   Subtotal: $0.0013

├── Test (Codex-mini):
│   Input: 5K × $0.15/1M = $0.00075
│   Output: 2K × $0.60/1M = $0.0012
│   Subtotal: $0.002

├── Final Review (DeepSeek V3.2):
│   Input: 5K × $0.14/1M = $0.0007
│   Output: 0.5K × $0.28/1M = $0.00014
│   Subtotal: $0.001

TOPLAM: ~$0.155
Retry (sadece fail eden stage): +$0.05 (ortalama)
WORST CASE: $0.205/request

× 150 request/ay = $30.75/ay
× 35 TL = ~1076 TL

SORUN: Hala gelirin üstünde!
```

### 8.3 Kota Limitleri ile Maliyet Kontrolü

```yaml
# Revize plan kotaları (maliyet kontrolü için)

plans:
  pro_1000_try:
    price_try: 1000
    target_margin: 0.25
    max_cost_usd: 21.50  # 750 TL / 35

    monthly_quotas:
      # Cheap tier (Codex-mini) - SINIRSIZ SAYIDA
      cheap:
        requests: 2000  # Sınırsıza yakın
        tokens: 10_000_000

      # Balanced tier (Sonnet 4) - KONTROLLÜ
      balanced:
        input_tokens: 3_000_000
        output_tokens: 600_000
        requests: 400
        # Maliyet: (3M × $3 + 600K × $15) / 1M = $18

      # Premium tier (Sonnet 4.5) - KISITLI
      premium:
        input_tokens: 500_000
        output_tokens: 100_000
        requests: 50
        # Maliyet: (500K × $3 + 100K × $15) / 1M = $3

      # Grace (Llama FREE) - SINIRSIZ
      grace:
        daily_requests: 100
        daily_tokens: 500_000
        # Maliyet: $0

    # Toplam max maliyet: ~$21 < $21.5 hedef ✓
```

### 8.4 Maliyet Tabanlı Routing

```yaml
cost_controls:

  per_request:
    cheap:
      allowed_models: 
        - "cf-triage"
        - "cf-cheap-coder"
        - "cf-budget-reviewer"
        - "cf-grace"
        - "cf-grace-fallback"
      hard_block_models:
        - "cf-balanced-coder"
        - "cf-premium-coder"
        - "cf-planner"  # Grok pahalı

    balanced:
      allowed_models:
        - "cf-triage"
        - "cf-cheap-coder"
        - "cf-balanced-coder"
        - "cf-budget-reviewer"
        - "cf-oss-fallback"
        - "cf-grace"
      hard_block_models:
        - "cf-premium-coder"

    premium:
      allowed_models: ["*"]  # Hepsi serbest
      preferred_models:
        - "cf-premium-coder"
        - "cf-balanced-coder"

  behavior:
    on_cost_cap_hit:
      downgrade_budget: true
      downgrade_order: 
        - "premium -> balanced"
        - "balanced -> cheap"
      enforce_fallback_pool: 
        - "cf-oss-fallback"
        - "cf-grace"
      report_fields:
        - "budget_class"
        - "models_used"
        - "gates_failed_if_any"
        - "total_cost_usd"
```

### 8.5 Gerçekçi Maliyet Senaryoları

```
SENARYO ANALİZİ: 100 Kullanıcı / Ay

KULLANICI PROFİLİ (Ortalama):
├── Cheap requests: 80%
├── Balanced requests: 15%
├── Premium requests: 5%

CHEAP REQUEST (80 × 100 = 8000 request):
├── Triage + Codex-mini = $0.003/request
├── Toplam: $24

BALANCED REQUEST (15 × 100 = 1500 request):
├── Full pipeline (Sonnet 4) = $0.15/request
├── Toplam: $225

PREMIUM REQUEST (5 × 100 = 500 request):
├── Full pipeline (Sonnet 4.5) = $0.20/request
├── Toplam: $100

GRACE FALLBACK (tahmini 10%):
├── 1000 request × $0 = $0

TOPLAM MALİYET: $349 / 100 kullanıcı = $3.49/kullanıcı
TL: ~122 TL/kullanıcı

GELİR: 1000 TL/kullanıcı
MALİYET: 122 TL/kullanıcı
KAR: 878 TL (%87.8) 

⚠️ Bu çok iyimser. Gerçekte heavy user'lar 3-5× ortalama kullanır.

AYARLANMIŞ SENARYO (Heavy Users dahil):
├── %10 kullanıcı 5× ortalama kullanır
├── Ortalama maliyet: ~400 TL/kullanıcı
├── Kar marjı: %60

WORST CASE (Tüm kota kullanımı):
├── Maliyet: ~650 TL/kullanıcı
├── Kar marjı: %35 > %25 hedef ✓
```

### 8.6 Decompose Pipeline ile Tasarruf

```
BÜYÜK REQUEST (50K karakter feature):

MEVCUT (Tek shot):
├── Input: 12K token × $3/1M = $0.036
├── Output: 15K token × $15/1M = $0.225 (kontrol yok!)
├── Toplam: $0.261
├── Retry: +$0.261
└── WORST: $0.522

DECOMPOSE (3 chunk):
├── Planner: 1K token = $0.015
├── Chunk A (fast): 2K out × $4/1M = $0.008
├── Chunk B (deep): 1.5K out × $15/1M = $0.023
├── Chunk C (fast): 1K out × $4/1M = $0.004
├── Toplam: $0.05
├── Chunk retry (B fail): +$0.023
└── WORST: $0.073

TASARRUF: %86!
```

---

## 9. MÜŞTERİ MEMNUNİYETİ STRATEJİSİ

### 9.1 Müşteri Değer Önerisi

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    CODEXFLOW MÜŞTERİ DEĞERİ                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  1. KALİTE GARANTİSİ                                                        │
│     • Review agent ile hata yakalama                                        │
│     • Test agent ile regresyon önleme                                       │
│     • Quality gates ile mergeable kod                                       │
│                                                                              │
│  2. MALİYET PREDİKTABİLİTESİ                                                │
│     • Sabit aylık fiyat (TL bazlı)                                          │
│     • Grace lane ile "asla stuck kalmama"                                   │
│     • Şeffaf kota takibi                                                    │
│                                                                              │
│  3. PERFORMANS                                                               │
│     • Cheap tier ile hızlı basit işler                                      │
│     • Premium tier ile kaliteli karmaşık işler                              │
│     • Fallback ile %99.9 uptime                                             │
│                                                                              │
│  4. TÜRK PAZARI ODAKLI                                                      │
│     • TL fiyatlandırma (dolar dalgalanmasına karşı)                         │
│     • Türkçe destek                                                         │
│     • Yerel ödeme seçenekleri                                               │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 9.2 SLA Garantileri

| Metrik | Garanti | Ölçüm |
|--------|---------|-------|
| Uptime | %99.5 | Aylık ortalama |
| Response Time (cheap) | <3s | p95 |
| Response Time (balanced) | <10s | p95 |
| Response Time (premium) | <20s | p95 |
| Error Rate | <%2 | Aylık ortalama |
| Grace Availability | %100 | Llama FREE |

### 9.3 Müşteri Deneyimi İyileştirmeleri

```yaml
customer_experience:

  transparency:
    - "Dashboard'da real-time kota görüntüleme"
    - "Her request'te tier/model bilgisi"
    - "Maliyet tahmini gösterimi"
    - "Quality gate sonuçları"

  reliability:
    - "Grace lane ile asla stuck kalma"
    - "Otomatik fallback"
    - "Retry with exponential backoff"
    - "Graceful degradation"

  quality:
    - "Review agent feedback'i"
    - "Test coverage raporu"
    - "Risk skorları"
    - "Merge-ready guarantee"

  support:
    - "Email destek (24 saat response)"
    - "Discord community"
    - "Detaylı dokümantasyon"
    - "Cursor entegrasyon rehberi"
```

### 9.4 Trial to Paid Conversion Optimization

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    TRIAL CONVERSION FUNNEL                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  GÜNCEL SİSTEM (24 saat trial):                                             │
│  ├── Trial başla                                                            │
│  ├── Saat 12: Hatırlatma email                                              │
│  ├── Saat 20: "4 saat kaldı" email                                          │
│  ├── Saat 23: Acil upgrade CTA                                              │
│  ├── Saat 24: Suspend + %10 indirim teklifi                                 │
│  └── Tahmini conversion: %15-20                                             │
│                                                                              │
│  ÖNERİLEN İYİLEŞTİRMELER:                                                   │
│  ├── In-app quality metrics gösterimi                                       │
│  │   "Bu session'da 3 bug yakalandı, 5 test üretildi"                       │
│  ├── Competitor comparison                                                   │
│  │   "OpenAI direct: $X vs CodexFlow: $Y"                                   │
│  ├── Trial extension (feedback karşılığı)                                   │
│  │   "+12 saat: 2 dakikalık survey doldur"                                  │
│  ├── Team invite bonus                                                       │
│  │   "Arkadaşını davet et, 1 hafta extra"                                   │
│  └── Hedef conversion: %25-30                                                │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 9.5 Retention Stratejisi

```yaml
retention:

  early_warning_signals:
    - "7 gün request yok → check-in email"
    - "Quota %10'un altında kaldı → proactive alert"
    - "Error rate yükseldi → technical support offer"

  engagement_features:
    - "Haftalık usage summary email"
    - "Savings report (vs direct API)"
    - "Quality metrics dashboard"
    - "Feature request voting"

  loyalty_rewards:
    - "6 ay aktif → %5 indirim"
    - "12 ay aktif → %10 indirim"
    - "Referral: 1 ay free her referral için"

  churn_prevention:
    - "Downgrade offer before cancel"
    - "Pause subscription option"
    - "Exit interview for feedback"
```

---

## 10. UYGULAMA YOL HARİTASI

### 10.1 Phase 1: Foundation (Hafta 1-2)

```yaml
phase_1:
  name: "Foundation"
  duration: "2 hafta"
  
  tasks:
    - name: "Model Registry Güncellemesi"
      files:
        - "infra/litellm/proxy_config.yaml"
        - "config/litellm.php"
      priority: "P0"
      
    - name: "Triage Agent Implementasyonu"
      files:
        - "app/Services/Llm/TriageAgent.php"
        - "app/Services/Llm/TaskIntakeSchema.php"
      priority: "P0"
      
    - name: "Budget Class Logic"
      files:
        - "app/Services/Llm/BudgetClassifier.php"
        - "app/Services/Llm/RiskScorer.php"
      priority: "P0"

  deliverables:
    - "Yeni LiteLLM config çalışır durumda"
    - "Triage JSON üretimi test edildi"
    - "Budget routing doğru çalışıyor"

  tests:
    - "Unit: RiskScorer edge cases"
    - "Integration: Triage → Budget flow"
```

### 10.2 Phase 2: Pipeline Core (Hafta 3-4)

```yaml
phase_2:
  name: "Pipeline Core"
  duration: "2 hafta"
  
  tasks:
    - name: "Planner Agent Implementasyonu"
      files:
        - "app/Services/Llm/PlannerAgent.php"
        - "app/Services/Llm/StepPlanSchema.php"
      priority: "P0"
      
    - name: "Coding Agent Router"
      files:
        - "app/Services/Llm/CodingAgentRouter.php"
        - "app/Services/Llm/DiffValidator.php"
      priority: "P0"
      
    - name: "Pipeline Orchestrator"
      files:
        - "app/Services/Llm/PipelineOrchestrator.php"
      priority: "P0"

  deliverables:
    - "Full pipeline çalışır durumda"
    - "Triage → Plan → Code flow"
    - "Diff-only output enforced"

  tests:
    - "Feature: Small request → cheap path"
    - "Feature: Large request → full pipeline"
```

### 10.3 Phase 3: Quality Gates (Hafta 5-6)

```yaml
phase_3:
  name: "Quality Gates"
  duration: "2 hafta"
  
  tasks:
    - name: "Review Agent Implementasyonu"
      files:
        - "app/Services/Llm/ReviewAgent.php"
        - "app/Services/Llm/ReviewChecklist.php"
      priority: "P0"
      
    - name: "Test Agent Implementasyonu"
      files:
        - "app/Services/Llm/TestAgent.php"
      priority: "P0"
      
    - name: "Quality Gate Enforcer"
      files:
        - "app/Services/Llm/QualityGateEnforcer.php"
      priority: "P0"

  deliverables:
    - "5 quality gate enforced"
    - "Rework loop çalışıyor"
    - "must_fix = 0 requirement"

  tests:
    - "Feature: Review gate blocks bad code"
    - "Feature: Rework loop works"
```

### 10.4 Phase 4: Cost Control (Hafta 7-8)

```yaml
phase_4:
  name: "Cost Control"
  duration: "2 hafta"
  
  tasks:
    - name: "Cost Cap Implementasyonu"
      files:
        - "app/Services/Quota/CostCapService.php"
      priority: "P0"
      
    - name: "Budget Downgrade Logic"
      files:
        - "app/Services/Llm/BudgetDowngrader.php"
      priority: "P0"
      
    - name: "Fallback Chain Refinement"
      files:
        - "app/Services/Llm/FallbackChainManager.php"
      priority: "P1"

  deliverables:
    - "Cost cap per request working"
    - "Budget downgrade automatic"
    - "Fallback chain tested"

  tests:
    - "Unit: Cost calculation accuracy"
    - "Integration: Budget downgrade flow"
```

### 10.5 Phase 5: Polish & Launch (Hafta 9-10)

```yaml
phase_5:
  name: "Polish & Launch"
  duration: "2 hafta"
  
  tasks:
    - name: "Dashboard Updates"
      files:
        - "resources/views/dashboard/*"
      priority: "P1"
      
    - name: "Documentation"
      files:
        - "docs/api-reference.md"
        - "docs/cursor-integration.md"
      priority: "P1"
      
    - name: "Performance Optimization"
      priority: "P2"

  deliverables:
    - "Dashboard shows pipeline metrics"
    - "Documentation complete"
    - "Beta testing complete"

  tests:
    - "E2E: Full user flow"
    - "Load: 100 concurrent requests"
```

---

## 11. RİSK ANALİZİ & MİTİGASYON

### 11.1 Teknik Riskler

| Risk | Olasılık | Etki | Mitigasyon |
|------|----------|------|------------|
| LiteLLM rate limit | Orta | Yüksek | 3 org key pool + fallback |
| Model API down | Düşük | Yüksek | Multi-provider fallback |
| Pipeline timeout | Orta | Orta | Stage-level timeout + partial results |
| Output format violation | Yüksek | Orta | Format validator + retry |
| Cost overrun | Düşük | Yüksek | Hard cost cap + budget downgrade |

### 11.2 İş Riskleri

| Risk | Olasılık | Etki | Mitigasyon |
|------|----------|------|------------|
| Low conversion | Orta | Yüksek | Trial optimization + feedback |
| High churn | Orta | Yüksek | Retention strategies |
| Competitor entry | Yüksek | Orta | Quality differentiation |
| TL devaluation | Yüksek | Orta | USD-indexed pricing option |
| API price increase | Orta | Yüksek | Multi-model flexibility |

### 11.3 Contingency Plans

```yaml
contingency:

  anthropic_outage:
    trigger: "cf-premium-coder 3× fail"
    action: "Auto-switch to cf-oss-fallback"
    notification: "Admin alert + user warning"

  cost_overrun:
    trigger: "User cost > 80% of cap"
    action: "Force budget downgrade to cheap"
    notification: "User email with usage summary"

  quality_degradation:
    trigger: "Error rate > 10% (5 min window)"
    action: "Enable reasoning mode for all requests"
    notification: "Admin alert + incident creation"

  llama_free_unavailable:
    trigger: "cf-grace 5× fail"
    action: "Switch to cf-grace-fallback (GPT-4o-mini)"
    notification: "Log for cost tracking"
```

---

## 12. KPI & BAŞARI METRİKLERİ

### 12.1 Finansal KPI'lar

| KPI | Hedef | Ölçüm Periyodu |
|-----|-------|----------------|
| Kar Marjı | ≥%25 | Aylık |
| ARPU | 1000 TL | Aylık |
| CAC | <500 TL | Aylık |
| LTV | >6000 TL | 6 aylık |
| MRR Growth | %10 | Aylık |

### 12.2 Operasyonel KPI'lar

| KPI | Hedef | Ölçüm |
|-----|-------|-------|
| Uptime | %99.5 | Aylık |
| p95 Latency (cheap) | <3s | Günlük |
| p95 Latency (balanced) | <10s | Günlük |
| p95 Latency (premium) | <20s | Günlük |
| Error Rate | <%2 | Günlük |
| Cache Hit Rate | >%30 | Günlük |

### 12.3 Kalite KPI'ları

| KPI | Hedef | Ölçüm |
|-----|-------|-------|
| Review Pass Rate | >%70 | İlk denemede |
| Rework Rate | <%30 | İstek başına |
| Test Coverage | >%60 | Medium+ risk |
| Quality Gate Pass | >%85 | Tüm gate'ler |

### 12.4 Müşteri KPI'ları

| KPI | Hedef | Ölçüm |
|-----|-------|-------|
| Trial Conversion | %25 | Haftalık |
| Monthly Retention | %90 | Aylık |
| NPS Score | >50 | Çeyreklik |
| Support Response | <24h | Günlük |
| Feature Requests Resolved | >%50 | Aylık |

---

## 13. TEKNİK İMPLEMENTASYON DETAYLARI

### 13.1 Yeni Servis Yapısı

```
app/
├── Services/
│   ├── Llm/
│   │   ├── Pipeline/
│   │   │   ├── PipelineOrchestrator.php     # Ana orkestratör
│   │   │   ├── StageExecutor.php            # Stage çalıştırıcı
│   │   │   └── PipelineContext.php          # Request context
│   │   │
│   │   ├── Agents/
│   │   │   ├── TriageAgent.php              # Task sınıflandırma
│   │   │   ├── PlannerAgent.php             # Plan oluşturma
│   │   │   ├── CodingAgent.php              # Kod yazma
│   │   │   ├── ReviewAgent.php              # Code review
│   │   │   ├── TestAgent.php                # Test yazma
│   │   │   └── VisionAgent.php              # UI analizi
│   │   │
│   │   ├── Routing/
│   │   │   ├── BudgetClassifier.php         # Budget class belirleme
│   │   │   ├── RiskScorer.php               # Risk skorlama
│   │   │   ├── ModelSelector.php            # Model seçimi
│   │   │   └── FallbackManager.php          # Fallback yönetimi
│   │   │
│   │   ├── Quality/
│   │   │   ├── QualityGateEnforcer.php      # Gate kontrolü
│   │   │   ├── DiffValidator.php            # Diff format kontrolü
│   │   │   ├── ReviewParser.php             # Review checklist parser
│   │   │   └── ReworkManager.php            # Rework loop yönetimi
│   │   │
│   │   ├── Cost/
│   │   │   ├── CostCalculator.php           # Maliyet hesaplama
│   │   │   ├── CostCapEnforcer.php          # Cost cap kontrolü
│   │   │   └── BudgetDowngrader.php         # Budget downgrade
│   │   │
│   │   └── Schemas/
│   │       ├── TaskIntakeSchema.php         # Triage JSON
│   │       ├── StepPlanSchema.php           # Plan JSON
│   │       └── ReviewChecklistSchema.php    # Review JSON
│   │
│   └── ... (mevcut servisler)
│
├── Exceptions/
│   └── Llm/
│       ├── QualityGateFailedException.php
│       ├── BudgetExceededException.php
│       ├── ReworkLimitException.php
│       └── ... (mevcut exceptions)
│
└── ... (mevcut yapı)
```

### 13.2 Pipeline Orchestrator Pseudo-Code

```php
<?php

namespace App\Services\Llm\Pipeline;

class PipelineOrchestrator
{
    public function process(PipelineContext $context): PipelineResult
    {
        // Stage 1: Triage
        $triage = $this->triageAgent->analyze($context->request);
        $context->setTriage($triage);
        
        // Determine path
        if ($this->isSimplePath($triage)) {
            return $this->executeSimplePath($context);
        }
        
        // Stage 2: Plan
        $plan = $this->plannerAgent->createPlan($context);
        $context->setPlan($plan);
        $this->gateEnforcer->check('plan_required', $context);
        
        // Stage 3: Code (with potential rework loop)
        $maxReworks = 3;
        $reworkCount = 0;
        
        do {
            $code = $this->codingAgent->generate($context);
            $context->setCode($code);
            $this->gateEnforcer->check('patch_only', $context);
            
            // Stage 4: Review
            $review = $this->reviewAgent->review($context);
            $context->setReview($review);
            
            $mustFix = $this->gateEnforcer->check('must_fix_zero', $context);
            
            if ($mustFix > 0) {
                $context->addReworkFeedback($review);
                $reworkCount++;
            }
            
        } while ($mustFix > 0 && $reworkCount < $maxReworks);
        
        if ($mustFix > 0) {
            throw new ReworkLimitException("Max rework attempts reached");
        }
        
        // Stage 5: Tests (if required)
        if ($this->shouldWriteTests($triage)) {
            $tests = $this->testAgent->generate($context);
            $context->setTests($tests);
            $this->gateEnforcer->check('tests_required', $context);
        }
        
        // Stage 6: Final Review
        $finalReview = $this->finalReviewAgent->review($context);
        $this->gateEnforcer->check('safety_gate', $context);
        
        return new PipelineResult($context);
    }
    
    private function isSimplePath(TaskIntake $triage): bool
    {
        return $triage->budgetClass === 'cheap' 
            && $triage->filesEstimate <= 2 
            && $triage->risk === 'low';
    }
    
    private function executeSimplePath(PipelineContext $context): PipelineResult
    {
        // Planner atla, direkt coding
        $code = $this->codingAgent->generate($context);
        $context->setCode($code);
        
        // Basit review (budget)
        $review = $this->reviewAgent->quickReview($context);
        
        if ($review->mustFix->isNotEmpty()) {
            // Bir kez rework dene
            $context->addReworkFeedback($review);
            $code = $this->codingAgent->generate($context);
        }
        
        return new PipelineResult($context);
    }
}
```

### 13.3 config/codexflow.php Güncellemesi

```php
<?php

return [
    // ... mevcut config ...
    
    /*
    |--------------------------------------------------------------------------
    | Role-Based Pipeline Configuration
    |--------------------------------------------------------------------------
    */
    'pipeline' => [
        'enabled' => true,
        
        'stages' => [
            'triage' => [
                'model_alias' => 'cf-triage',
                'max_output_tokens' => 800,
                'timeout' => 15,
            ],
            'plan' => [
                'model_alias' => 'cf-planner',
                'max_output_tokens' => 2000,
                'timeout' => 45,
            ],
            'code_cheap' => [
                'model_alias' => 'cf-cheap-coder',
                'max_output_tokens' => 3000,
                'timeout' => 60,
            ],
            'code_balanced' => [
                'model_alias' => 'cf-balanced-coder',
                'max_output_tokens' => 6000,
                'timeout' => 120,
            ],
            'code_premium' => [
                'model_alias' => 'cf-premium-coder',
                'max_output_tokens' => 6000,
                'timeout' => 180,
            ],
            'review_budget' => [
                'model_alias' => 'cf-budget-reviewer',
                'max_output_tokens' => 2500,
                'timeout' => 90,
            ],
            'review_premium' => [
                'model_alias' => 'cf-premium-coder',
                'max_output_tokens' => 2500,
                'timeout' => 120,
            ],
            'test' => [
                'model_alias' => 'cf-cheap-coder',
                'max_output_tokens' => 3500,
                'timeout' => 90,
            ],
        ],
        
        'quality_gates' => [
            'plan_required' => ['stage' => 'plan', 'require' => 'step_plan'],
            'patch_only' => ['stage' => 'code', 'require' => 'unified_diff'],
            'must_fix_zero' => ['stage' => 'review', 'rule' => 'must_fix_count_eq_0'],
            'tests_required' => ['stage' => 'test', 'when' => 'risk_medium_plus'],
            'safety_gate' => ['stage' => 'final_review', 'when' => 'sensitive_domain'],
        ],
        
        'rework' => [
            'max_iterations' => 3,
            'escalate_on_limit' => true,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Budget Class Rules
    |--------------------------------------------------------------------------
    */
    'budget' => [
        'critical_domains' => [
            'auth', 'billing', 'payment', 'webhooks', 
            'encryption', 'acl', 'permissions'
        ],
        
        'high_risk_domains' => [
            'queue', 'cron', 'concurrency', 'caching',
            'rate_limit', 'retry', 'data_consistency'
        ],
        
        'risk_escalation' => [
            ['domains' => 'critical_domains', 'set_risk' => 'critical'],
            ['files_gte' => 3, 'set_risk' => 'high'],
            ['domains' => 'high_risk_domains', 'set_risk' => 'high'],
            ['files_between' => [2, 3], 'set_risk' => 'medium'],
        ],
        
        'class_rules' => [
            ['risk_in' => ['high', 'critical'], 'set_class' => 'premium'],
            ['risk_in' => ['medium'], 'set_class' => 'balanced'],
            ['files_between' => [2, 5], 'set_class' => 'balanced'],
            ['default' => true, 'set_class' => 'cheap'],
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cost Controls
    |--------------------------------------------------------------------------
    */
    'cost_control' => [
        'per_request_cap_usd' => [
            'cheap' => 0.01,
            'balanced' => 0.20,
            'premium' => 0.50,
        ],
        
        'downgrade_on_cap' => true,
        'downgrade_order' => ['premium', 'balanced', 'cheap'],
        
        'fallback_pool' => ['cf-oss-fallback', 'cf-grace'],
    ],
    
    // ... mevcut config devamı ...
];
```

---

## 📋 SONUÇ VE ÖNERİLER

### Kritik Başarı Faktörleri

1. **Role-Based Pipeline:** Doğru model, doğru iş için
2. **Quality Gates:** Hata erken yakalansın
3. **Cost Control:** %25+ kar garantisi
4. **Grace Lane:** Asla stuck kalma
5. **Müşteri Odak:** Kalite + transparanlık

### Öncelikli Aksiyonlar

| Öncelik | Aksiyon | Süre | Sorumlu |
|---------|---------|------|---------|
| P0 | Model registry güncellemesi | 2 gün | DevOps |
| P0 | Triage agent implementasyonu | 3 gün | Backend |
| P0 | Pipeline orchestrator | 5 gün | Backend |
| P1 | Quality gates | 4 gün | Backend |
| P1 | Cost control | 3 gün | Backend |
| P2 | Dashboard updates | 3 gün | Frontend |

### Beklenen Sonuçlar

```
3 AY SONRA:
├── Kar marjı: %25+ (garantili)
├── Müşteri memnuniyeti: NPS 50+
├── Uptime: %99.5
├── Conversion rate: %25
└── Monthly retention: %90

6 AY SONRA:
├── 500+ aktif kullanıcı
├── MRR: 500,000 TL
├── Net kar: 125,000 TL/ay
└── Türkiye'nin #1 LLM Gateway'i
```

---

### 📌 Güncel Model Durumu (Aralık 2025)

| Model | Yayın Tarihi | Durum | Model ID | Projede Kullanım |
|-------|--------------|-------|----------|------------------|
| Claude Haiku 3.5 | 2024 | ✅ Aktif | `claude-3-5-haiku-latest` | cf-fast |
| Claude Sonnet 4 | Mayıs 2025 | ✅ Aktif | `claude-sonnet-4-20250514` | cf-balanced-coder |
| Claude Sonnet 4.5 | 29 Eylül 2025 | ✅ Aktif | `claude-sonnet-4-5-20250929` | cf-premium-coder |

> **Not:** Opus 4/4.5 projede kullanılmıyor (maliyet çok yüksek). Sonnet 4.5 premium tier için yeterli.

---

**Hazırlayan:** Claude Opus 4.5  
**Tarih:** 29 Aralık 2025  
**Versiyon:** 3.1 (Implementation Complete)

---

## 📋 UYGULAMA LOG

### Tamamlanan İşler (29 Aralık 2025)

| # | Görev | Dosya(lar) | Durum |
|---|-------|-----------|-------|
| 1 | Opus 4.5 referansları kaldırıldı | ProjeDevelopment.md | ✅ |
| 2 | LiteLLM proxy config güncellendi | infra/litellm/proxy_config.yaml | ✅ |
| 3 | Laravel LiteLLM config güncellendi | config/litellm.php | ✅ |
| 4 | Pipeline config eklendi | config/codexflow.php | ✅ |
| 5 | Pipeline servisleri oluşturuldu | app/Services/Llm/Pipeline/* | ✅ |
| 6 | Agent servisleri oluşturuldu | app/Services/Llm/Agents/* | ✅ |
| 7 | Routing servisleri oluşturuldu | app/Services/Llm/Routing/* | ✅ |
| 8 | Quality gate servisleri oluşturuldu | app/Services/Llm/Quality/* | ✅ |
| 9 | Exception sınıfları eklendi | app/Exceptions/Llm/* | ✅ |
| 10 | Service Provider güncellendi | app/Providers/AppServiceProvider.php | ✅ |
| 11 | VS Code Extension rehberi yazıldı | docs/VSCODE_EXTENSION_DEVELOPMENT.md | ✅ |
| 12 | RooCode alternatifi belgelendi | docs/ROOCODE_ALTERNATIVE.md | ✅ |
| 13 | env.example güncellendi | env.example | ✅ |

### Oluşturulan Yeni Dosyalar

```
app/Services/Llm/
├── Pipeline/
│   ├── PipelineContext.php      # Context object for pipeline
│   ├── PipelineResult.php       # Result object
│   └── PipelineOrchestrator.php # Main orchestrator
├── Agents/
│   ├── TriageAgent.php          # Task classification
│   ├── PlannerAgent.php         # Step plan generation
│   ├── CodingAgent.php          # Code generation
│   ├── ReviewAgent.php          # Code review
│   └── TestAgent.php            # Test generation
├── Routing/
│   ├── BudgetClassifier.php     # Budget class logic
│   ├── RiskScorer.php           # Risk scoring
│   └── ModelSelector.php        # Model selection
└── Quality/
    ├── QualityGateEnforcer.php  # Gate checks
    └── DiffValidator.php        # Diff format validation

app/Exceptions/Llm/
├── QualityGateFailedException.php
├── ReworkLimitException.php
└── BudgetExceededException.php

docs/
├── CURSOR_EXTENSION_SETUP.md
└── ROOCODE_ALTERNATIVE.md
```

### Sonraki Adımlar

1. ~~**Test yazımı** - Pipeline için unit/feature testleri~~
2. ~~**GatewayService entegrasyonu** - Pipeline'ı gateway'e bağla~~
3. **Dashboard güncelleme** - Pipeline metrics görüntüleme
4. **Production deployment** - EasyPanel konfigürasyonu

---

## 📋 VS CODE EXTENSION - MAJOR UPDATE (29 Aralık 2025 - 2. Update)

### Yapılan Değişiklikler

#### Backend (LiteLLM & Laravel)

| # | Değişiklik | Dosya | Açıklama |
|---|------------|-------|----------|
| 1 | Grok 4.1 Fast eklendi | `infra/litellm/proxy_config.yaml` | `cf-grok-tools` alias - 2M context, background tool ops |
| 2 | Grok Tools tier config | `config/litellm.php` | Timeout: 300s, max_input: 500K, reasoning toggle |
| 3 | Grok Tools cost config | `config/codexflow.php` | $3/1M input, $15/1M output |

#### VS Code Extension - Yeni Dosyalar

| # | Dosya | Açıklama |
|---|-------|----------|
| 1 | `src/agent/BackgroundWorker.ts` | Grok 4.1 Fast ile arka plan işlemleri - 2M context, tool calling optimized |
| 2 | `src/agent/FileOperationService.ts` | Gelişmiş dosya işlemleri - Atomic ops, Undo/Redo, Batch operations |
| 3 | `src/agent/LiveFileSync.ts` | Cursor-like anlık dosya sync - Agent yazdıkça dosya anında değişir |
| 4 | `src/agent/AgentCore.ts` | Central Orchestrator - Triage → Plan → Code → Review pipeline |

#### VS Code Extension - Güncellenen Dosyalar

| # | Dosya | Değişiklik |
|---|-------|------------|
| 1 | `package.json` | v1.0.0, yeni komutlar, Grok Tools model, settings |
| 2 | `src/api/CodexFlowClient.ts` | `backgroundCompletion()`, `triageRequest()` metodları |
| 3 | `src/extension.ts` | Yeni agent servisleri, undo/redo, background task komutları |

### Yeni Özellikler

#### 1. Background Worker (Grok 4.1 Fast)
```typescript
// Büyük codebase analizi - 2M context window
const task = await backgroundWorker.analyzeCodebase(files, "Güvenlik açıklarını bul");

// Multi-file edit - Birden fazla dosyayı koordineli düzenle
const task = await backgroundWorker.multiFileEdit(edits, context);

// Dependency analizi
const task = await backgroundWorker.analyzeDependencies(packageFiles);
```

#### 2. Live File Sync (Cursor Benzeri)
```typescript
// Agent yazdıkça dosya ANLIK değişir
const session = liveSync.startSession(['src/index.ts']);
await liveSync.streamCodeToFile(session.id, 'src/index.ts', codeChunk, lineNumber);

// Streaming diff uygulama
await liveSync.applyDiffWithStreaming(session.id, 'src/index.ts', diff);
```

#### 3. Agent Core Pipeline
```
┌──────────────────────────────────────────────────────────────┐
│                    Agent Core Pipeline                        │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  [User Message] → [Triage] → [Plan] → [Code] → [Review]      │
│        │              │         │        │         │          │
│        │              │         │        │         ▼          │
│        │              │         │        │    [Live Sync]     │
│        │              │         │        │    (Anlık Update)  │
│        │              │         │        │         │          │
│        ▼              ▼         ▼        ▼         ▼          │
│  cf-triage    cf-planner   cf-*-coder  cf-reviewer [File]    │
│  (GPT-4o-mini) (GPT-4o-mini) (Claude)   (DeepSeek)           │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

#### 4. Yeni Komutlar

| Komut | Kısayol | Açıklama |
|-------|---------|----------|
| `codexflow.agentUndo` | `Ctrl+Alt+U` | Agent değişikliklerini geri al |
| `codexflow.agentRedo` | `Ctrl+Alt+Y` | Geri alınan değişikliği yeniden yap |
| `codexflow.runBackgroundTask` | - | Arka plan analiz görevi başlat |
| `codexflow.showAgentOutput` | - | Agent output panelini göster |

#### 5. Yeni Settings

```json
{
  "codexflow.backgroundModel": "cf-grok-tools",    // Grok 4.1 Fast
  "codexflow.enableLiveSync": true,                // Cursor-like live sync
  "codexflow.enableBackgroundWorker": true         // Background worker
}
```

### Grok 4.1 Fast Kullanım Senaryoları

| Senaryo | Neden Grok 4.1 Fast? |
|---------|---------------------|
| Büyük codebase analizi | 2M context - tüm repo tek seferde |
| Multi-file refactoring | Tool calling optimized |
| Dependency graph çıkarma | Uzun dosya akışları |
| Security audit | Geniş context, reasoning |
| Batch file operations | Non-blocking background |

### Mimari Özet

```
┌─────────────────────────────────────────────────────────────────────┐
│                    VS Code Extension v1.0.0                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────┐     ┌──────────────┐     ┌──────────────────────┐ │
│  │ Chat Panel   │────▶│ Agent Core   │────▶│ File Operation       │ │
│  │ (UI)         │     │ (Orchestrate)│     │ Service              │ │
│  └──────────────┘     └──────┬───────┘     └──────────┬───────────┘ │
│         │                    │                        │             │
│         │                    ▼                        ▼             │
│         │           ┌──────────────┐         ┌──────────────────┐   │
│         │           │ Background   │         │ Live File Sync   │   │
│         │           │ Worker       │         │ (Real-time edit) │   │
│         │           │ (Grok 4.1)   │         └──────────────────┘   │
│         │           └──────┬───────┘                  │             │
│         │                  │                          │             │
│         ▼                  ▼                          ▼             │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │                    CodexFlow Gateway API                     │    │
│  │  cf-triage │ cf-planner │ cf-*-coder │ cf-grok-tools        │    │
│  └─────────────────────────────────────────────────────────────┘    │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## ✅ TAMAMLANAN TÜM GÖREVLER

| # | Görev | Durum |
|---|-------|-------|
| 1 | Backend: proxy_config.yaml'a cf-grok-tools alias ekle | ✅ |
| 2 | Backend: config/litellm.php ve codexflow.php güncelle | ✅ |
| 3 | Extension: BackgroundWorker.ts oluştur (Grok 4.1 Fast) | ✅ |
| 4 | Extension: FileOperationService.ts - Gelişmiş dosya işlemleri | ✅ |
| 5 | Extension: LiveFileSync.ts - Cursor-like anlık dosya sync | ✅ |
| 6 | Extension: AgentCore.ts - Orchestrator | ✅ |
| 7 | Extension: package.json ve API client güncelle | ✅ |

---

## 🚀 SONRAKİ ADIMLAR

1. **Extension derleme ve test** - `npm run compile` ile TypeScript derleme
2. **Integration test** - Extension'ı VS Code'da test et
3. **Backend pipeline test** - Laravel pipeline unit testleri
4. **Production deployment** - EasyPanel konfigürasyonu

---

**Son Güncelleme:** 29 Aralık 2025 - VS Code Extension v1.0.0  
**Hazırlayan:** Claude Opus 4.5

---

*Bu doküman CodexFlow.dev'in stratejik geliştirme planını içermektedir. Uygulama detayları için ekip ile koordineli çalışılmalıdır.*

