# LOCAL AI & Vision-Based Media SEO — Feature Plan

## Overview

Add a **Local AI provider** (LM Studio / Ollama) alongside existing cloud providers (OpenAI, Google Gemini), and build **AI-powered media alt text & description generation** for images, videos, and documents.

This plan covers two tightly related features:

1. **Local AI Provider** — connect to LM Studio or Ollama via their OpenAI-compatible API
2. **AI Media SEO** — generate alt text, titles, and descriptions for media assets using AI (cloud or local)

---

## Part 1: Local AI Provider (LM Studio / Ollama)

### Why

- **Zero cost**: Run unlimited SEO operations (audits, metadata, alt text) on local hardware
- **Privacy**: No data leaves the user's network
- **Power users**: Agencies managing 20+ sites can use a Mac Studio or GPU server as a dedicated SEO AI engine
- **Vision for free**: Local multimodal models (LLaVA, Qwen2-VL) enable unlimited image analysis

### Technical approach

LM Studio and Ollama both expose an **OpenAI-compatible API** at `http://localhost:1234/v1/chat/completions` (LM Studio) or `http://localhost:11434/v1/chat/completions` (Ollama).

Our existing `AI_Generator` already calls OpenAI at `https://api.openai.com/v1/chat/completions`. The implementation is:

1. **New provider option**: `local` alongside `openai` and `google`
2. **Settings fields**:
   - `local_base_url` (default: `http://localhost:1234/v1`) — configurable for remote servers too
   - `local_model` — text field (user enters model name from LM Studio/Ollama)
   - `local_api_key` — optional (LM Studio doesn't require one, Ollama may)
   - `local_vision_model` — separate field for multimodal model (used for image analysis)
3. **Code change**: In `call_openai()`, swap base URL when provider is `local`
4. **Model list**: No hardcoded list — user picks from what's loaded in LM Studio/Ollama
5. **Connection test**: Existing `test_model_connection()` works as-is (same API format)

### Estimated code changes

| File | Change | Lines |
|------|--------|-------|
| `class-settings.php` | Add `local` provider + 4 settings fields + defaults | ~20 |
| `class-ai-generator.php` | Add `local` branch in provider routing (reuses `call_openai` with different base URL) | ~15 |
| `view-settings.php` | Add Local AI settings section in UI (base URL, model, API key fields) | ~60 |
| **Total** | | **~95 lines** |

### Hardware requirements (reference)

| Model size | RAM needed | Good for |
|-----------|-----------|----------|
| 7B (Qwen2.5-7B, LLaVA-1.6-7B) | 8-16 GB | Alt text, basic metadata |
| 13B (Llama 3.1-13B) | 16-32 GB | Full page audits, metadata |
| 30B (Qwen2.5-32B) | 32-64 GB | Site audits, strategic analysis |
| 70B+ (Llama 3.1-70B) | 64-128 GB | Overkill for SEO, but available |

### Multimodal models for vision (local)

| Model | Parameters | RAM | Vision quality |
|-------|-----------|-----|---------------|
| LLaVA-1.6-7B | 7B | 8-16 GB | Good for website images |
| Qwen2-VL-7B | 7B | 8-16 GB | Better, supports documents |
| LLaVA-1.6-13B | 13B | 16-32 GB | Very good |
| InternVL2-26B | 26B | 32-64 GB | Excellent |

---

## Part 2: AI Media SEO (Alt Text & Description Generation)

### Scope

Generate AI-powered metadata for all media types in the WordPress Media Library:

| Media type | What AI generates | AI approach |
|-----------|------------------|-------------|
| **Images** (jpg, png, webp, gif, svg) | Alt text, SEO title | Vision (send image) + page context |
| **Videos** (mp4, mov, webm) | SEO description, title | Thumbnail vision + filename + page context |
| **Documents** (pdf, doc, etc.) | SEO description, title | Filename + page context only |

### Two generation modes

#### Mode 1: Smart Alt (context-only)
- Uses: filename, page title, focus keyphrase, surrounding content
- Works with: any text model (cloud or local)
- Cost: minimal (text-only prompt)
- Quality: good when filenames are descriptive, weak when `IMG00023.jpg`
- Best for: bulk operations on hundreds of assets

#### Mode 2: Vision Alt (image analysis)
- Uses: actual image sent to multimodal model + page context
- Works with: GPT-4o, Claude, Gemini, LLaVA, Qwen2-VL
- Cost: higher per-image (cloud), free (local)
- Quality: excellent — AI sees and describes the actual content
- Best for: important pages, individual image optimization

### UI integration

#### Image SEO page (existing `view-images.php`)
- Add **"Generate Alt"** button per row (next to Save button)
- Add **"Bulk Generate"** button at top (process all missing-alt images)
- Mode selector: Smart Alt / Vision Alt
- Progress indicator for bulk operations

#### Video SEO page (existing `view-videos.php`)
- Add **"Generate Description"** button per row
- Uses thumbnail extraction + vision for video poster frame

#### Gutenberg sidebar
- Add **"Generate Alt"** button in image block settings panel
- Inline generation without leaving the editor

### API implementation

#### New AJAX endpoints
- `wp_ajax_ai_seo_captain_generate_media_alt` — single asset
- `wp_ajax_ai_seo_captain_bulk_generate_media_alt` — batch (chunked)

#### Prompt structure (Vision mode)
```
System: You are an SEO specialist. Generate a concise, descriptive alt text for this image.
The alt text should:
- Accurately describe what is shown in the image
- Be 10-15 words maximum
- Include the focus keyphrase naturally if relevant
- Not start with "Image of" or "Picture of"

User: [IMAGE attached]
Page context:
- Title: {post_title}
- Focus keyphrase: {focus_keyphrase}
- Page topic: {first_150_words_of_content}

Generate:
1. alt_text: descriptive alt text
2. seo_title: SEO-friendly title for the media asset
```

#### Prompt structure (Smart/context-only mode)
```
System: You are an SEO specialist. Generate alt text based on the context provided.

User:
- Filename: {original_filename}
- Page title: {post_title}
- Focus keyphrase: {focus_keyphrase}
- Page topic: {first_150_words_of_content}
- Image dimensions: {width}x{height}
- Caption (if any): {caption}

Generate:
1. alt_text: descriptive alt text (10-15 words)
2. seo_title: SEO-friendly title
```

### Vision API format (OpenAI-compatible, works with LM Studio too)

```json
{
  "model": "llava-1.6-7b",
  "messages": [
    {
      "role": "user",
      "content": [
        { "type": "text", "text": "Describe this image for SEO alt text..." },
        { "type": "image_url", "image_url": { "url": "data:image/jpeg;base64,..." } }
      ]
    }
  ]
}
```

For Google Gemini vision:
```json
{
  "contents": [{
    "role": "user",
    "parts": [
      { "text": "Describe this image..." },
      { "inline_data": { "mime_type": "image/jpeg", "data": "<base64>" } }
    ]
  }]
}
```

### Video thumbnail extraction

WordPress automatically generates a poster image for uploaded videos via `wp_get_attachment_metadata()` → `thumb` key. If no thumbnail exists, we use context-only mode.

### Estimated code changes

| File | Change | Lines |
|------|--------|-------|
| `class-ai-generator.php` | Add `generate_media_alt()` method with vision/context modes | ~120 |
| `class-admin.php` | Add AJAX handlers for media alt generation | ~80 |
| `view-images.php` | Add Generate Alt buttons (single + bulk) | ~40 |
| `view-videos.php` | Add Generate Description buttons | ~30 |
| `assets/js/page-images.js` | AJAX handlers for generate buttons | ~80 |
| `assets/js/page-videos.js` | AJAX handlers for generate buttons | ~60 |
| **Total** | | **~410 lines** |

---

## Implementation order

### Phase 1: Local AI Provider
1. Settings UI for Local AI (base URL, model, API key)
2. Provider routing in AI_Generator
3. Connection test support
4. Documentation in settings page

### Phase 2: Context-only Media Alt (Smart Alt)
1. `generate_media_alt()` in AI_Generator (text-only prompt)
2. AJAX endpoint for single media alt generation
3. "Generate Alt" button in Image SEO page
4. "Generate Description" button in Video SEO page
5. Bulk generation with progress

### Phase 3: Vision Media Alt
1. Vision API support in AI_Generator (multimodal content array)
2. Google Gemini vision support (different format)
3. Local vision model support (LM Studio + LLaVA/Qwen2-VL)
4. Mode selector in UI (Smart Alt / Vision Alt)
5. Image-to-base64 conversion for API calls

### Phase 4: Editor Integration
1. Gutenberg sidebar "Generate Alt" for image blocks
2. Classic editor metabox integration

---

## Dependencies

- **No new PHP libraries** — uses existing `wp_remote_post()` for API calls
- **No new JS libraries** — vanilla AJAX with existing patterns
- **No new database tables** — alt text stored in standard `_wp_attachment_image_alt` post meta
- **WordPress image functions**: `wp_get_attachment_url()`, `wp_get_attachment_metadata()`, `get_attached_file()`

## Cost comparison

| Approach | Per image | 500 images | 5000 images |
|----------|----------|------------|-------------|
| GPT-4o Vision (cloud) | ~$0.01-0.03 | $5-15 | $50-150 |
| Gemini Flash Vision (cloud) | ~$0.001 | $0.50 | $5 |
| Smart Alt / context-only (cloud) | ~$0.001 | $0.50 | $5 |
| Local AI (LM Studio) | $0 | $0 | $0 |

## Notes

- The Local AI provider is useful beyond media SEO — it benefits ALL AI features (page audits, metadata, chat, site audit)
- For large sites, the recommended workflow: connect LM Studio → run overnight bulk alt text generation → review in Image SEO page
- The existing manual alt text editing in Image/Video SEO pages is preserved — AI generation is additive
