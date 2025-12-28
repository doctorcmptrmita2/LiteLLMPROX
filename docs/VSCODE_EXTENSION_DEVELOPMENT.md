# 🚀 CodexFlow VS Code Extension

## Genel Bakış

CodexFlow VS Code Extension, CodexFlow Gateway ile entegre çalışan, profesyonel bir AI-assisted coding aracıdır.

**Proje Konumu:** `C:\wamp64\www\codexflow-vscode`

**Mevcut Versiyon:** 0.9.0 (Pipeline Edition)

---

## 📊 Mevcut Özellikler

### ✅ Tamamlanan Özellikler

| Özellik | Açıklama |
|---------|----------|
| **Modern Chat UI** | Glassmorphism design, streaming, markdown + syntax highlight |
| **Agent System** | 17+ tool: create_file, edit_file, run_command, delete_file, vb. |
| **CursorLikeAgent** | Türkçe komut desteği, undo/redo, conversation history |
| **Live Preview** | Hot reload, built-in browser preview |
| **Inline Edit** | Seçili kod düzenleme (Cmd+K) |
| **Templates** | React, Website, Flask API şablonları |
| **Model Selection** | 7 model alias desteği |
| **Auto-Apply** | Kod değişikliklerini otomatik uygulama |
| **Context Pills** | Dosya, seçim, hata context ekleme |

### 🔧 v0.9.0 Güncellemeleri (Pipeline)

- `cf-cheap-coder` (Haiku) - Ucuz kodlama
- `cf-balanced-coder` (Sonnet 4) - Orta seviye
- `cf-premium-coder` (Sonnet 4.5) - Critical işler
- Model seçenekleri genişletildi

---

## 📁 Proje Yapısı

```
codexflow-vscode/
├── package.json              # v0.9.0
├── src/
│   ├── extension.ts          # Ana entry point
│   ├── api/
│   │   └── CodexFlowClient.ts    # Gateway API client
│   ├── agent/
│   │   ├── AgentService.ts       # Temel agent
│   │   ├── ProAgentService.ts    # Gelişmiş agent
│   │   ├── EnhancedAgentService.ts # Multi-step tasks
│   │   ├── CursorLikeAgent.ts    # Cursor-like tool system
│   │   └── AutoApplyService.ts   # Hot reload
│   ├── chat/
│   │   ├── ChatViewProvider.ts   # Temel chat
│   │   └── ModernChatViewProvider.ts # Glassmorphism UI
│   ├── browser/
│   │   └── BrowserViewProvider.ts # Built-in browser
│   ├── inline/
│   │   └── InlineEditProvider.ts # Cmd+K editing
│   ├── live/
│   │   └── LivePreviewService.ts # Hot reload
│   ├── diff/
│   │   └── DiffService.ts        # Diff uygulama
│   └── templates/
│       ├── TemplateService.ts    # Proje oluşturma
│       └── ProjectTemplates.ts   # Şablonlar
└── out/                      # Compiled JS
```

---

## 🔌 Model Aliases

Extension şu model alias'larını destekler:

| Alias | Model | Kullanım |
|-------|-------|----------|
| `cf-fast` | Claude Haiku 3.5 | Hızlı, basit işler |
| `cf-cheap-coder` | Claude Haiku 3.5 | Ucuz kodlama tier |
| `cf-balanced-coder` | Claude Sonnet 4 | Orta seviye işler |
| `cf-premium-coder` | Claude Sonnet 4.5 | Critical işler |
| `cf-deep` | Claude Sonnet 4 | Karmaşık logic |
| `cf-agent` | Grok 3 Beta | 2M context, agentic |
| `cf-grace` | Llama 405B FREE | Ücretsiz fallback |

---

## 🛠️ Geliştirme

### Build & Watch

```bash
cd C:\wamp64\www\codexflow-vscode

# Bağımlılıkları yükle
npm install

# Compile
npm run compile

# Watch mode (geliştirme için)
npm run watch
```

### Test Etme

VS Code'da `F5` tuşuna basarak Extension Development Host açılır.

### Package & Publish

```bash
# VSIX oluştur
npm run package

# Yayınla (marketplace)
vsce publish
```

---

## 🔗 Gateway Entegrasyonu

Extension, CodexFlow Gateway'e şu endpoint'leri kullanarak bağlanır:

```
POST /v1/chat/completions
  - Streaming destekli
  - Model seçimi
  - Max tokens, temperature

GET /v1/usage/quota
  - Kota bilgisi
```

### API Client Örneği

```typescript
// CodexFlowClient.ts
const response = await fetch(`${config.apiBase}/chat/completions`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${config.apiKey}`
  },
  body: JSON.stringify({
    model: 'cf-balanced-coder',
    messages,
    stream: true
  })
});
```

---

## 📋 Sonraki Adımlar

1. **Pipeline Agent Integration**
   - Triage → Plan → Code → Review → Test akışı
   - Quality gates entegrasyonu

2. **Quota Görüntüleme**
   - Sidebar'da kota widget'ı
   - Real-time güncelleme

3. **Review Panel**
   - must_fix / should_fix görüntüleme
   - Tek tıkla fix uygulama

4. **Test Generation**
   - Agent ile test oluşturma
   - Test coverage raporu

---

*CodexFlow VS Code Extension - Pipeline Edition*
