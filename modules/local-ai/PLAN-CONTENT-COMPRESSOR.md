# Local AI Content Compressor — Architecture Plan

## Problem
Local AI models have small context windows (32K–131K tokens) compared to cloud
models (128K–2M). Large pages or deep-analysis prompts can exceed the input
budget and trigger a hard API error (`context_length_exceeded`). Cloud providers
are never affected — they have massive context windows.

## Solution
A **progressive compression engine** that automatically shrinks page content to
fit the model's context window. It preserves everything that matters for SEO
while trimming low-value body text.

## File
`modules/local-ai/class-local-ai-content-compressor.php`

## Integration Point
`call_local()` in `includes/class-ai-generator.php` — runs **after** the
messages array is built and **before** the first API call. Only activates when
the estimated prompt exceeds the input budget. Cloud calls never touch this.

## Token Budget Calculation
```
input_budget = context_window × 0.6          (60% input, 40% output)
budget_chars = input_budget × 3.5            (3.5 chars ≈ 1 token)
total_chars  = system_prompt + user_prompt
overflow     = total_chars − budget_chars
```

## Compression Levels (progressive — each builds on the previous)

### Level 0 — None
Prompt fits. Send as-is. No changes.

### Level 1 — Trim Paragraphs
- `<p>` blocks > 150 visible chars → keep first sentence + `[…]`
- `<blockquote>` > 150 chars → first sentence + `[…]`
- `<pre>` / `<code>` > 300 chars → first 3 lines + `[…]`
- Long `<li>` items > 200 chars → first sentence + `[…]`
- **Expected savings: 30–50%** of body text

### Level 2 — Sparse Paragraphs
- Apply Level 1 first
- Keep only: first `<p>` after each heading, plus first and last `<p>` overall
- Remove all other `<p>` blocks (replaced with nothing — they vanish)
- Lists and tables kept in full
- **Expected savings: 50–70%** of body text

### Level 3 — Structural Skeleton
- Remove ALL `<p>` and `<blockquote>` blocks
- Remove `<pre>` / `<code>` blocks → `[code block omitted]`
- Keep: headings, images (src+alt), links (href+text), tables, lists
- **Expected savings: 70–85%** of body text

### Level 4 — Ultra-Compact
- Start from Level 3
- Remove tables → `[table omitted]`
- Remove lists → `[list omitted]`
- Convert images → `[image: alt text]`
- Unwrap links (keep anchor text, drop href)
- Remove videos/iframes → `[video]`
- Only headings and minimal inline text survive
- **Expected savings: 85–95%** of body text

## What is NEVER removed (across all levels)
- Heading tags (`<h1>`–`<h6>`) with full text
- Image `src` + `alt` (Levels 1–3) or alt-only placeholder (Level 4)
- Link `href` + anchor text (Levels 1–3) or anchor-only (Level 4)
- The prompt instruction text (non-HTML portions of the user message)

## AI Transparency
When content is compressed, a `[COMPRESSION NOTE]` is prepended to the user
message so the AI knows it's working with condensed content. The note includes:
- Compression level and description
- Token savings (original → compressed)
- Model context window size
- Recommendation for full-detail analysis

## Performance
- Regex-based (no DOMDocument) — the input is already sanitized HTML
- Single-pass per level, no recursion
- Levels are tried sequentially: if Level 1 fits → done. If not → Level 2, etc.
- Worst case: 4 regex passes on one string

## Testing
All compression methods are public static — can be unit-tested independently:
```php
$compressed = Local_AI_Content_Compressor::compress($html, 1); // Level 1
$result     = Local_AI_Content_Compressor::fit_messages($messages, 32000);
```
