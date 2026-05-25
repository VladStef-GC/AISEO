# LOCAL AI — IMPLEMENTATION INTO PLUGIN

> **Status**: Stage 1 COMPLETE. Stage 2 COMPLETE (provider integration, context window, JSON robustness, capability test).
> **Last updated**: 2026-06-10
> **Branch**: Dev-env

---

## WHAT IS DONE (Stage 1) ✅

### Module Files (all in `modules/local-ai/`)
- **class-local-ai-admin.php** — Admin page, AJAX handlers, heartbeat, admin bar status
- **class-local-ai-provider.php** — Core API client (OpenAI-compatible: LM Studio, Ollama)
- **view-local-ai.php** — Admin page template (form POST save, no AJAX dependency)
- **local-ai.js** — Connect, Test Selected Model, Test Chat, model dropdown population
- **local-ai.css** — Styles for the admin page

### Working Features
- [x] Server connection with model discovery (`GET /v1/models`)
- [x] Model dropdowns: Chat Model (all models) + Vision Model (filtered by name pattern)
- [x] Vision model filtering regex: `-vl`, `vision`, `llava`, `bakllava`, `minicpm-v`, `cogvlm`, `internvl`, `phi-3.*vision`, `gemma-3.*it`
- [x] Vision probe: sends tiny 1×1 image to model to confirm vision capability
- [x] Combined "Test Selected Model" button (assess capabilities + vision probe + live chat test)
- [x] Single-column capability list with ✅/🚫 per operation
- [x] Context window: minimum 128K tokens (131,072) — full parity with cloud
- [x] Custom context window option with explanation: 128K = 128 × 1,024 = 131,072
- [x] Form POST save (bombproof persistence, no JS dependency)
- [x] Local AI fields registered in `class-settings.php` sanitize whitelist (ROOT CAUSE of save bug)
- [x] Admin bar status: 🟢 Running / 🔴 Offline with tooltip
- [x] Background heartbeat: pings LM Studio every 2 min on all admin pages (via `admin_footer`)
- [x] Heartbeat updates admin bar label AND tooltip dynamically
- [x] Test Chat with quick-test buttons (SEO Title, Meta Description, SEO Tips)
- [x] Disconnect button (clears all local AI settings)
- [x] Browser autocomplete prevention (`autocomplete="off"`, `autocomplete="new-password"`)
- [x] `filemtime()` cache busting for JS/CSS (no more stale browser cache)
- [x] Split error messages: server unreachable vs model RAM issues
- [x] 128 unit tests passing

### Settings Stored (in `ai_seo_captain_options`)
| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `local_base_url` | URL | `''` | Server URL (e.g., `http://192.168.1.157:1234`) |
| `local_model` | string | `''` | Chat model ID (e.g., `qwen2.5-72b-instruct`) |
| `local_vision_model` | string | `''` | Vision model ID (empty = no vision) |
| `local_api_key` | string | `''` | Optional API key |
| `local_context_window` | int | `131072` | Context window in tokens |
| `local_timeout` | int | `120` | Request timeout in seconds |

### Key Architecture Decisions
- **Form POST for save** — not AJAX. WordPress's `register_setting()` sanitize callback runs on every `update_option()` call, which was silently stripping unknown fields. Fields MUST be in the sanitize whitelist.
- **API Key field is `type="text"`** — not `type="password"`. Password fields trigger browser password managers which auto-fill credentials into the URL field.
- **Heartbeat, not cron** — WP-Cron is unreliable and wastes resources. Heartbeat fires only when an admin is active, throttled to 2-minute intervals via `sessionStorage`.

---

## WHAT IS DONE (Stage 2) ✅

### Provider Integration (Completed 2026-06)
- [x] `'local'` added to provider dropdown on Settings page — appears only when `local_model` is configured
- [x] `class-ai-generator.php` routes to `Local_AI_Provider` when `provider=local`
- [x] `get_context_window()` returns `local_context_window` when provider=local
- [x] All AI operations (metadata generation, page audit, site audit, chat) work through Local AI

### JSON Robustness (Completed 2026-06)
- [x] `decode_json_payload()` rewritten — removed naive `strrpos('}')` extraction (was finding `}` inside CSS/markdown strings)
- [x] New pipeline: strip markdown fences → `escape_json_strings()` → `extract_json_object_safe()` (structure-aware depth tracking) → trailing comma fix → `json_decode()` → `repair_truncated_json()` fallback
- [x] `call_ai_and_decode(callable $call_fn, string $context)` — wraps AI call + JSON decode with automatic 1-retry on parse failure
- [x] All AI modes (generate_for_post, generate_site_audit, generate_page_audit) use `call_ai_and_decode()`

### Capability Test (Completed 2026-06)
- [x] `test_json_capability()` in `class-local-ai-admin.php` — sends real JSON test prompt to model
- [x] Tests connection AND JSON parsing quality in one step
- [x] Vision probe tests multimodal capability with 1×1 pixel image
- [x] Results shown as ✅/🚫 per operation in the settings UI

### Setup Wizard Stop = Reset (Completed 2026-06)
- [x] Stop button clears all cached data (audits or metadata) via AJAX
- [x] Button text always shows "Start Page Audits" / "Start AI Generation" (not "Continue")
- [x] Next run starts fresh — no stale cache from previous interrupted runs

### Key Commits
- `38793a3` — Fix JSON extraction fallback + real capability test
- `1e7d6e9` — Fix strrpos JSON corruption + add retry on parse failure
- `e04deec` — Stop = full reset, clears cached data for fresh restart

---

## ORIGINAL PLAN (Stage 2) — Provider Integration (Kept for Reference)

### Step 2.1: Add 'local' to Provider Dropdown on Settings Page

**Files to modify:**
- `includes/class-settings.php` — Add `'local'` to `PROVIDER_MODELS` and `get_supported_providers()`
- `includes/admin/view-settings.php` — Show "Local AI" in provider dropdown, with read-only model display

**Logic:**
- "Local AI (LM Studio / Ollama)" appears in the provider dropdown ONLY if `local_model` is configured (non-empty)
- When selected: show the saved model name as **read-only text** (not a dropdown)
- Below it: link "⚙️ Change model in Local AI settings" → `admin.php?page=ai-seo-captain-local-ai`
- On selection: trigger heartbeat check. If server offline → show warning banner but still allow saving
- If `local_model` is empty: show "Local AI" as disabled option with hint "Configure in Local AI settings first"

**Model dropdown behavior when provider = 'local':**
- Hide the standard model dropdown (cloud models are irrelevant)
- Show: "Model: qwen2.5-72b-instruct (128K context)" as static text
- If vision model configured, show: "Vision: qwen2-vl-7b" below

### Step 2.2: Route AI_Generator to Local AI Provider

**Files to modify:**
- `includes/class-ai-generator.php` — Add local provider routing

**Logic:**
- When `provider === 'local'`, create `Local_AI_Provider` instance instead of calling OpenAI/Google
- The provider already has `chat()` which uses the same OpenAI-compatible API format
- Pass the same `$messages` array — the prompt format is identical
- Use `local_context_window` for content truncation limits
- Use `local_timeout` for request timeout

**Key method to modify:** `generate()` or equivalent — the method that sends messages to the AI
- Add: `if ('local' === $provider) { return $this->generate_local($messages); }`
- `generate_local()` creates `Local_AI_Provider` from saved settings and calls `chat()`

### Step 2.3: Context Window Integration

**Files to modify:**
- `includes/class-settings.php` — Update `get_context_window()`

**Logic:**
```php
public static function get_context_window(string $model_id): int
{
    $options = get_option(self::OPTION_NAME, array());
    if ('local' === ($options['provider'] ?? '')) {
        return (int) ($options['local_context_window'] ?? 131072);
    }
    // ... existing cloud model lookup ...
}
```
- All downstream code (focus pages gate, tree-mode limit, content truncation, chat memory) automatically adapts — no changes needed

### Step 2.4: Connection Failure Handling

**Rule: Option (A) — NEVER silently fall back to cloud.**

The user chose Local AI deliberately (likely for privacy). If the server goes offline:

1. **During page audit (Steps 2-3 in setup wizard):**
   - PAUSE processing immediately
   - Show exact status: "✅ Pages 1-47 completed. ❌ Connection lost at page 48."
   - Show "Retry" button that resumes from where it stopped
   - All completed work is SAVED — nothing is lost
   - User can also cancel (keeps what was done)

2. **During Editor Chat / Site Chat:**
   - Show error message: "🔴 Local AI server not responding. Check LM Studio is running."
   - The message is per-request — previous messages in the conversation are preserved
   - User can retry when server is back

3. **During single page metadata generation:**
   - Show error in the metabox: "Local AI unavailable. Check server connection."
   - No silent degradation — user sees exactly what happened

**Error detection:**
- `Local_AI_Provider::chat()` already returns `['success' => false, 'error' => '...']`
- `server_connection_error()` for unreachable server (timeout, refused)
- `connection_error()` for model issues (RAM, context overflow)

---

## STAGE 3 — Image & Document SEO (Local AI Only)

### Core Rule: Asset metadata generation is LOCAL AI ONLY
- **NEVER** send images/documents to cloud AI for SEO metadata
- If no Local AI configured → image/document SEO remains manual (same as today)
- This is a privacy boundary: assets stay on the user's network

### Step 3.1: Image SEO with Vision Model

**Two-model pipeline (preferred):**
1. **Vision model** receives the image → "Describe this image objectively in 2-3 sentences"
2. **Text model** receives: page context + vision description → generates SEO metadata

**Why two models?** Vision models are good at *seeing* but text models write better SEO. The text model gets:
```
Page: {title, url, headings}
Image: {src, filename, dimensions, position_in_page}
Context: {surrounding_paragraphs, nearest_heading, caption_if_any}
Vision: {description_from_vision_model}
Task: Generate alt_text, title, caption. If decorative → return alt=""
```

**Single vision model (fallback):**
- If the chat model itself has vision capability → send image + context in one request
- Works but SEO quality may be lower than the two-model pipeline

**No vision model:**
- Text model still works with: filename, existing alt text, surrounding text, CSS classes, dimensions
- Can significantly improve metadata from context alone — just can't *see* the image

### Step 3.2: Decorative Image Detection

AI identifies decorative images from:
- Filename patterns: `bg-`, `icon-`, `spacer-`, `divider-`, `separator-`
- Tiny dimensions: 1×1 pixels, tracking pixels
- CSS context: `background-image`, `role="presentation"`, `aria-hidden="true"`
- Vision model output: "solid color", "abstract gradient", "geometric pattern"
- **Result:** `alt=""` (empty, W3C-correct — explicitly decorative, not missing)

### Step 3.3: Same Image on Different Pages

- Each page generates its OWN metadata for the image based on page context
- `office.jpg` on "About Us" → alt="Our open-plan workspace in downtown office"
- `office.jpg` on "Careers" → alt="Join our team in our modern office environment"
- This is correct SEO practice — alt text should be contextual, not global

### Step 3.4: Document SEO (PDFs, etc.)

- **No vision needed** — extract text content (title, first paragraph, metadata)
- Text model generates: title, description, schema markup
- Works with the chat model only

### Step 3.5: Video SEO (DEFERRED)

- Requires FFmpeg for keyframe/thumbnail extraction → adds server dependency
- Complex: extract first frame → send to vision → generate thumbnail alt + video schema
- **Not implementing now** — will be the last asset type to support
- Videos remain manual SEO for now

---

## STAGE 4 — Advanced Features (Future)

### 4.1: Provider Auto-Switch
- If user has both cloud and local configured, allow per-operation provider selection
- Example: use cloud for fast metadata, local for image analysis (privacy)

### 4.2: Model Hot-Swap
- Detect when user loads a different model in LM Studio
- Auto-update the model name and context window via heartbeat

### 4.3: Batch Image Processing
- Queue-based system for bulk image SEO generation
- Process images in background with progress tracking
- Resume-capable (same as audit pause/resume)

---

## FILE MAP — What Goes Where

| File | Change Type | Purpose |
|------|------------|---------|
| `modules/local-ai/*` | ✅ DONE | Module files (admin, provider, view, JS, CSS) |
| `includes/class-settings.php` | MODIFY | Add `'local'` provider, update `get_context_window()`, sanitize whitelist ✅ |
| `includes/admin/view-settings.php` | MODIFY | Show Local AI in provider dropdown, read-only model |
| `includes/class-ai-generator.php` | MODIFY | Route to Local AI provider when `provider=local` |
| `includes/class-plugin.php` | ✅ DONE | Loads local-ai module in `is_admin()` block |

---

## TESTING CHECKLIST

### Stage 2 Tests
- [x] Provider dropdown shows "Local AI" only when `local_model` is configured
- [x] Selecting "Local AI" shows read-only model name (not editable dropdown)
- [x] Selecting "Local AI" triggers heartbeat → shows 🟢 or warns if offline
- [x] Saving with provider=local persists correctly
- [x] AI_Generator uses Local AI provider for all operations when provider=local
- [x] Context window correctly returned from `get_context_window()` for local provider
- [x] Connection failure during audit shows exact progress and pauses
- [x] Connection failure during chat shows error, preserves conversation
- [x] Switching back to cloud provider works without issues
- [x] All 128+ unit tests still pass

### Stage 3 Tests
- [ ] Image with vision model → generates contextual alt text
- [ ] Image without vision model → generates alt from text context only
- [ ] Decorative image → returns `alt=""`
- [ ] Same image on different pages → different contextual alt text
- [ ] Document SEO → generates metadata from extracted text
- [ ] No Local AI configured → image/document SEO remains manual

---

## QUICK REFERENCE — Key Commands

```powershell
# PHP syntax check
c:\xampp\php\php.exe -l <file>

# Unit tests
cd c:\xampp\htdocs\greencoders\wp-content\plugins\ai-seo-captain
c:\xampp\php\php.exe -d extension=php_zip.dll vendor\bin\phpunit --testsuite Unit

# Git push
$env:Path += ";C:\Program Files\Git\bin"
cd c:\xampp\htdocs\greencoders\wp-content\plugins\ai-seo-captain
git add -A ; git commit -m "message" ; git push origin Dev-env

# User's LM Studio server
http://192.168.1.157:1234
```
