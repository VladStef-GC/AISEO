# LOCAL AI (LM Studio / Ollama) — Complete Feature Plan

## Overview

Add a **Local AI provider** as a standalone module in `modules/local-ai/`, enabling users to connect to **LM Studio** or **Ollama** running on the same machine (or any reachable server). When the user switches to "Local AI" as their provider, ALL plugin AI operations route through the local server — no cloud API calls, zero cost.

Two models may run simultaneously:
- **Chat model** — text reasoning for metadata, audits, chat, strategic analysis
- **Vision model** — multimodal model for image/video alt text generation

---

## CRITICAL CHALLENGE: Context Window Detection

### The problem

Cloud providers (OpenAI, Google) have known, fixed context windows per model. We hardcode these in `Settings::PROVIDER_MODELS` and use them throughout the plugin:
- `Settings::get_context_window()` → returns token limit for prompt budgets
- `Settings::get_max_focus_pages_for_model()` → Site Chat page-count gate
- `Settings::get_max_pages_for_model()` → tree-mode page limit
- `AI_Generator` → content truncation at 20,000 chars
- Chat memory manager → conversation summarization threshold

With LM Studio, we **cannot predict** what model the user will load. It could be:
- A tiny 1B model with 2,048 context → almost useless for audits
- A 7B model with 4,096 context → basic metadata generation only
- A 13B model with 8,192 context → comfortable for most tasks
- A 32B model with 32,768+ context → handles everything
- A 70B model with 128k context → full parity with cloud

If we assume 200k (current fallback), a 4k model will crash on every prompt.

### The solution: Auto-detect + Manual override

**Step 1: Query LM Studio's `/v1/models` endpoint**

LM Studio returns model metadata including context length:
```json
{
  "data": [{
    "id": "qwen2.5-7b-instruct",
    "object": "model",
    "owned_by": "lmstudio",
    "context_length": 32768
  }]
}
```

We read `context_length` from the response when the user tests connection or selects a model.

**Step 2: Manual override with presets**

If auto-detect fails (older LM Studio, Ollama without metadata), the user can set context window manually via a dropdown:

| Preset | Tokens | Use case |
|--------|--------|----------|
| Tiny (2K) | 2,048 | Emergency fallback — only shortest prompts |
| Small (4K) | 4,096 | Basic metadata (title + description) |
| Medium (8K) | 8,192 | Metadata + page audits |
| Standard (16K) | 16,384 | All features including chat |
| Large (32K) | 32,768 | Full feature parity |
| Extended (64K) | 65,536 | Site audits with many pages |
| Maximum (128K) | 131,072 | Equivalent to cloud models |
| Custom | User-defined | For niche models |

**Step 3: Adaptive prompt builder**

When provider is `local`, the existing `get_context_window()` returns the detected/configured value. All downstream code (focus pages gate, tree-mode limit, content truncation, chat memory) automatically adapts — **no changes needed** to `AI_Generator`, `Site_Chat`, or `Admin`.

Only change: make `get_context_window()` check for local provider settings:
```php
public static function get_context_window(string $model_id): int
{
    // Check local AI override first.
    $options = get_option(self::OPTION_NAME, array());
    if ('local' === ($options['provider'] ?? '') && ! empty($options['local_context_window'])) {
        return (int) $options['local_context_window'];
    }
    // ... existing cloud model lookup ...
}
```

**Step 4: Graceful error handling for context overflow**

LM Studio returns HTTP 400 with a message like `"context_length_exceeded"` or `"maximum context length"`. We catch this specifically and show:
> "This content exceeds your model's context window (4,096 tokens). You can:
> • Load a model with a larger context in LM Studio
> • Increase the context window in Settings → Local AI
> • Try a shorter page or use the bulk editor for individual fields"

---

## Architecture: Standalone Module

```
modules/
└── local-ai/
    ├── class-local-ai-provider.php    # Core: API calls, model discovery, vision
    ├── class-local-ai-admin.php       # Admin page, AJAX handlers, test chat
    ├── view-local-ai.php              # Settings UI + test chat interface
    ├── local-ai.js                    # Model discovery, test chat, connection test
    └── local-ai.css                   # Styles for the admin page
```

### Why a separate module?

1. **Safe development** — build and test without touching core files
2. **Clean rollback** — delete the folder to remove the feature entirely
3. **Integration later** — when ready, the core `AI_Generator` just checks `if ('local' === $provider)` and delegates to this module

---

## Part 1: Local AI Provider

### Settings fields (stored in `ai_seo_captain_options`)

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `local_base_url` | URL | `http://localhost:1234/v1` | LM Studio / Ollama API base URL |
| `local_model` | text | *(auto-detected)* | Chat model name (e.g. `qwen2.5-7b-instruct`) |
| `local_vision_model` | text | *(empty)* | Vision model name (e.g. `llava-1.6-7b`). Empty = no vision |
| `local_api_key` | text | *(empty)* | Optional API key (LM Studio doesn't require one) |
| `local_context_window` | int | 4096 | Context window in tokens (auto-detected or manual) |
| `local_timeout` | int | 120 | Request timeout in seconds (local models are slower) |

### Connection flow

```
User enters Base URL → clicks "Discover Models"
  ↓
GET {base_url}/models → returns list of loaded models
  ↓
Populate "Chat Model" dropdown + "Vision Model" dropdown
  ↓
User selects model → auto-fill context_window from metadata
  ↓
User clicks "Test Connection" → sends test prompt
  ↓
Shows response + latency + model info
```

### API compatibility

LM Studio and Ollama use the **same OpenAI-compatible format**:

```
POST {base_url}/chat/completions
Content-Type: application/json
Authorization: Bearer {api_key}   ← optional

{
  "model": "qwen2.5-7b-instruct",
  "messages": [
    {"role": "system", "content": "..."},
    {"role": "user", "content": "..."}
  ],
  "temperature": 0.3,
  "max_tokens": 2048
}
```

**For vision** (multimodal models):
```json
{
  "model": "llava-1.6-7b",
  "messages": [{
    "role": "user",
    "content": [
      {"type": "text", "text": "Describe this image..."},
      {"type": "image_url", "image_url": {"url": "data:image/jpeg;base64,..."}}
    ]
  }]
}
```

---

## Part 2: Admin UI Design

### Page location

New admin page: **SEO Captain → Local AI** (only visible when plugin is active)

### UI sections

#### Section 1: Connection Setup
```
┌─────────────────────────────────────────────────────────┐
│ 🖥️ Local AI Server                                      │
│                                                         │
│ Server URL:  [http://localhost:1234/v1          ]        │
│ API Key:     [________________________________ ] (opt)  │
│ Timeout:     [120] seconds                              │
│                                                         │
│ [🔍 Discover Models]                                    │
│                                                         │
│ ┌─ Models Found ──────────────────────────────────┐     │
│ │ Chat Model:   [▼ qwen2.5-7b-instruct        ]  │     │
│ │ Vision Model: [▼ llava-1.6-7b       ] (opt)  │  │     │
│ │ Context:      [▼ 32K (auto-detected) ]       │  │     │
│ └─────────────────────────────────────────────────┘     │
│                                                         │
│ [✅ Test Connection]  [💾 Save Settings]                │
└─────────────────────────────────────────────────────────┘
```

#### Section 2: Status Banner
Dynamic banner showing connection state:

| State | Banner | Color |
|-------|--------|-------|
| Not configured | "Configure your Local AI server below." | Blue (info) |
| Connected | "Connected to LM Studio — qwen2.5-7b (32K context)" | Green |
| Connection failed | "Cannot reach LM Studio at localhost:1234. Is it running?" | Red |
| No models loaded | "LM Studio is running but no models are loaded." | Orange |

#### Section 3: Test Chat
```
┌─────────────────────────────────────────────────────────┐
│ 💬 Test Chat                                            │
│ Verify your local AI works before switching providers.  │
│                                                         │
│ ┌───────────────────────────────────────────────────┐   │
│ │ AI: Hello! I'm running locally via LM Studio.    │   │
│ │     Model: qwen2.5-7b-instruct                   │   │
│ │     Context: 32,768 tokens                        │   │
│ │     Response time: 1.2s                           │   │
│ └───────────────────────────────────────────────────┘   │
│                                                         │
│ [Type a message...                          ] [Send]    │
│                                                         │
│ Quick tests:                                            │
│ [Generate SEO title] [Test page audit] [Test vision]    │
└─────────────────────────────────────────────────────────┘
```

### Error messages (all scenarios)

| Error | User sees | Suggested action |
|-------|-----------|-----------------|
| Connection refused | "Cannot connect to {url}. Is LM Studio running?" | "Start LM Studio and load a model, then try again." |
| Timeout (no response) | "Request timed out after {n} seconds." | "Your model may need more RAM. Try a smaller model or increase timeout." |
| No models in response | "LM Studio is running but no models are loaded." | "Open LM Studio and load a model from the Models tab." |
| Context overflow (HTTP 400) | "Content exceeds your model's {n}-token context window." | "Load a larger model or reduce the Context Window preset." |
| Vision not supported | "Your model doesn't support image analysis." | "Load a multimodal model (LLaVA, Qwen2-VL) for vision tasks." |
| Invalid JSON response | "Unexpected response from the AI server." | "Check that your model is compatible with OpenAI chat format." |
| HTTP 401/403 | "Authentication failed." | "Check the API key in your Local AI settings." |
| HTTP 500 | "The AI server encountered an error." | "Check LM Studio logs for details." |
| Model mismatch | "Model '{name}' not found on the server." | "The model may have been unloaded. Click Discover Models to refresh." |

---

## Part 3: Integration with Core Plugin

### How switching works

In **Settings → AI Provider**, the user sees three options:
- OpenAI
- Google Gemini
- **Local AI (LM Studio / Ollama)** ← new

When "Local AI" is selected:
1. All `AI_Generator` calls route through the local module's provider
2. `get_context_window()` returns the local model's context window
3. All page-count gates, truncation, and memory management auto-adapt
4. Vision calls use the vision model (if configured)
5. If no vision model is set, vision features fall back to Smart Alt (context-only)

### Code integration points

| Core file | Change needed | Description |
|-----------|---------------|-------------|
| `class-settings.php` | ~5 lines | `get_context_window()` checks local provider first |
| `class-settings.php` | ~3 lines | Add `'local'` to provider list |
| `class-ai-generator.php` | ~10 lines | `call_local()` method delegates to module |
| `class-plugin.php` | ~5 lines | Load module if `modules/local-ai/` exists |
| `view-settings.php` | ~5 lines | Show "Local AI" option in provider dropdown |

**Total core changes: ~28 lines** — everything else lives in the module.

---

## Part 4: AI Media SEO (Alt Text Generation)

*(Deferred to Phase 2 — after Local AI is stable)*

Uses the same provider abstraction — works with cloud OR local AI.
See original plan sections for Smart Alt, Vision Alt, UI integration, and prompt structures.

---

## Development Phases

### Phase 1: Module Foundation (current)
- [x] Create `modules/local-ai/` folder structure
- [ ] Build `Local_AI_Provider` class (model discovery, chat completions, vision)
- [ ] Build `Local_AI_Admin` class (admin page, AJAX handlers)
- [ ] Build `view-local-ai.php` (connection UI + test chat)
- [ ] Build `local-ai.js` (model discovery, test chat, connection test)
- [ ] Build `local-ai.css` (admin page styles)
- [ ] Syntax check + manual testing with LM Studio

### Phase 2: Core Integration
- [ ] Add `'local'` provider to Settings
- [ ] Update `get_context_window()` for local models
- [ ] Add `call_local()` to AI_Generator
- [ ] Wire module loading in Plugin bootstrap
- [ ] Full regression test (all AI features via local model)

### Phase 3: AI Media SEO
- [ ] Smart Alt (context-only) for images
- [ ] Vision Alt for images (via local or cloud vision model)
- [ ] Video description generation
- [ ] Bulk generation UI
- [ ] Gutenberg sidebar integration

### Phase 4: Polish
- [ ] Setup wizard / onboarding
- [ ] Performance monitoring (response time tracking)
- [ ] Model recommendation engine ("Your model handles X pages, consider upgrading to Y")

---

## Hardware Reference

| Model size | RAM needed | Context | SEO capability |
|-----------|-----------|---------|----------------|
| 1B-3B | 4-8 GB | 2-4K | ⚠️ Basic metadata only |
| 7B | 8-16 GB | 4-32K | ✅ Metadata + audits |
| 13B | 16-32 GB | 8-32K | ✅ Full features |
| 30B-34B | 32-64 GB | 16-64K | ✅ Everything + large audits |
| 70B | 64-128 GB | 32-128K | ✅ Cloud-equivalent quality |

### Recommended multimodal models (vision)

| Model | RAM | Vision quality | Notes |
|-------|-----|---------------|-------|
| LLaVA-1.6-7B | 8-16 GB | Good | Fastest, good for bulk |
| Qwen2-VL-7B | 8-16 GB | Better | Also handles documents |
| InternVL2-26B | 32-64 GB | Excellent | Best local vision |

---

## Cost comparison

| Approach | Per request | 500 pages | 5000 images |
|----------|-----------|-----------|-------------|
| GPT-4.1 (cloud) | ~$0.005 | $2.50 | N/A |
| Gemini Flash (cloud) | ~$0.001 | $0.50 | N/A |
| GPT-4o Vision (cloud) | ~$0.02 | N/A | $100 |
| Local AI (LM Studio) | **$0** | **$0** | **$0** |
