# SEO Captain — AI Data Flow Reference

> **Version:** 1.3.1 · **Last updated:** May 20, 2026  
> **Source of truth:** This document is derived directly from the source code.

This document describes exactly what data the AI sees, how it processes it, and where results are stored — for every AI-powered feature in the plugin.

---

## Table of Contents

- [1. Step 2 — AI Metadata Generation](#1-step-2--ai-metadata-generation)
- [2. Step 3 — Full Page Audit (Wizard Bulk)](#2-step-3--full-page-audit-wizard-bulk)
- [3. Site Chat — AI Captain (Site-Wide)](#3-site-chat--ai-captain-site-wide)
- [4. Editor Mode — AI Commander Chat](#4-editor-mode--ai-commander-chat)
- [5. Editor Mode — Page Audit](#5-editor-mode--page-audit)
- [6. AI Content Editor](#6-ai-content-editor)
- [7. Site Audit Report (Dashboard)](#7-site-audit-report-dashboard)
- [Appendix A — Shared Data Methods](#appendix-a--shared-data-methods)
- [Appendix B — Token Budgets & Limits](#appendix-b--token-budgets--limits)

---

## 1. Step 2 — AI Metadata Generation

**Entry point:** `AI_Generator::generate_for_post(int $post_id)`  
**AJAX handler:** `Admin_Ajax::handle_generate_suggestion()` / `handle_bulk_generate()`  
**Trigger:** "Generate" button in editor metabox, or Setup Wizard Step 2 bulk flow.

### What the AI Receives

#### System Prompt (`build_system_prompt`)
- Plugin identity (SEO Captain — never mention other SEO plugins)
- Required JSON output format: `seo_title`, `meta_description`, `focus_keyphrase`, `keywords`, `social_title`, `social_description`, `notes`
- Field length constraints (title ≤ 60 chars, description ≤ 155 chars, social title ≤ 70, social description ≤ 200)
- Keywords: 5–8 comma-separated SEO keywords/phrases
- Title branding rules (if enabled): budget = 60 − branding suffix length
- Keyphrase enforcement: must appear naturally in both title and description
- Preserve-if-good instruction: don't rewrite drafts that are already well-optimized

#### User Prompt (`build_user_prompt`)

| Data Category | What's Included | Source |
|---|---|---|
| **Page basics** | Site name, page type, current title, URL, excerpt | WordPress core |
| **Current SEO metadata** | SEO title draft (with char count), meta description draft (with char count), focus keyphrase, keyphrase presence flags (in title, in description) | `_ai_seo_captain_*` post meta, or live browser overrides |
| **Social & Advanced** | Social title, social description, schema type, canonical URL, robots directives, cornerstone flag | Post meta or browser overrides |
| **Audit results** | SEO score (0–100), issues list, suggestions list, audit summary (if page was previously audited) | `_ai_seo_captain_page_audit` post meta |
| **Content analysis** | Word count, heading structure (H1–H6 counts), images total + missing alt count, internal/external link counts, video embed count, document link count | Computed from raw HTML via `Content_Helper::get_content()` |
| **Image details** | Per-image `src` URL + `alt` text status (max 30 images) | Regex on raw HTML |
| **Link URLs** | Internal link URLs (max 50, deduplicated), External link URLs (max 30, deduplicated) | Regex on raw HTML |
| **Page hierarchy** | Grandparent (title, slug, keyphrase, keywords), Parent (all 6 SEO fields), Siblings (up to 20, all 6 SEO fields each), Children (up to 20, all 6 SEO fields each), Position string | `Content_Indexer::get_hierarchy_context()` |
| **Keyphrase conflicts** | All pages targeting the same focus keyphrase (title, slug, all 6 SEO fields, post type) — **no SQL limit** | `Content_Indexer::get_keyphrase_conflicts()` |
| **Topically related pages** | Up to 20 pages matched by keyword overlap across the site (all 6 SEO fields per page), relevance-scored | `Content_Indexer::get_topically_related_pages()` |
| **Site tree** | Compact hierarchy showing all pages with titles + keyphrases | `Content_Indexer::get_compact_site_tree()` |
| **WooCommerce** | Price, SKU, availability, product type, rating, categories, tags, brand (if product and `wc_ai_context_enabled`) | `ai_seo_captain_product_context` filter |
| **Taxonomy terms** | All public taxonomy terms (categories, tags, custom) | WordPress `get_the_terms()` |
| **Dates** | Published date, last modified date | Post object |
| **Featured image** | Has/doesn't have featured image | `has_post_thumbnail()` |
| **Site locale** | Language/locale code | `get_locale()` |
| **Body content** | Full normalized plain text (no truncation) | `Content_Helper::get_content()` → `normalize_text()` |
| **Site context** | Site owner's business description and goals | `site_chat_context` setting |
| **Deep analysis extras** | Sibling body content excerpts (~1,500 chars each, up to 20), Topical page content excerpts (~1,500 chars each, up to 20) | Only when `deep_analysis = true` |

**The 6 SEO metadata fields always included for each sibling/parent/child/conflict/topical page:**
1. Page title (post title)
2. SEO title draft (`_ai_seo_captain_meta_title`)
3. Meta description draft (`_ai_seo_captain_meta_description`)
4. Focus keyphrase (`_ai_seo_captain_focus_keyphrase`)
5. Keywords (`_ai_seo_captain_keywords`)
6. Social title (`_ai_seo_captain_social_title`)
7. Social description (`_ai_seo_captain_social_description`)

### What the AI Returns
```json
{
  "seo_title": "Page-specific title (≤60 chars, or ≤budget if branding)",
  "meta_description": "Compelling description (≤155 chars)",
  "focus_keyphrase": "2-4 word target phrase",
  "keywords": "keyword1, keyword2, keyword3, keyword4, keyword5",
  "social_title": "Engaging OG/Twitter title (≤70 chars)",
  "social_description": "Click-worthy social hook (≤200 chars)",
  "notes": "Why this positioning was chosen"
}
```

### How Data Is Saved
1. AI response → returned to browser via `wp_send_json_success()`
2. User reviews in editor metabox (fields pre-populated)
3. User clicks **Save Draft** → `handle_save_editor_meta()` stores in post meta:
   - `_ai_seo_captain_meta_title`
   - `_ai_seo_captain_meta_description`
   - `_ai_seo_captain_focus_keyphrase`
   - `_ai_seo_captain_keywords`
   - `_ai_seo_captain_social_title`
   - `_ai_seo_captain_social_description`
4. History entry stored via `History_Store::add_suggestion()`
5. User optionally clicks **Approve** → `handle_approve_suggestion()` marks as approved
6. Approved metadata rendered on frontend by `class-frontend.php`

---

## 2. Step 3 — Full Page Audit (Wizard Bulk)

**Entry point:** `AI_Generator::generate_page_audit(int $post_id, bool $deep_analysis)`  
**AJAX handler:** `Admin_Ajax::handle_page_audit()`  
**Trigger:** Setup Wizard Step 3 bulk flow, or "Run Audit" button in editor metabox.

### What the AI Receives

#### System Prompt (`build_page_audit_system_prompt`)
- Plugin identity (SEO Captain)
- Required JSON output: `score`, `issues`, `suggestions`, `missing_alt_tags`, `word_count`, `heading_structure`, `summary`, `full_report`
- Detailed instructions for `full_report` sections:
  - (A) Executive summary
  - (B) Every issue with WHY and HOW to fix
  - (C) Content analysis: headings, keyword density, readability, thin content
  - (D) Media audit: every image/video/document with missing SEO
  - (E) Link analysis: internal/external, broken patterns, nofollow
  - (F) Metadata assessment: title, description, keyphrase, social, schema
  - (G) Cannibalization risks: sibling/related page overlap
  - (H) Prioritized numbered action list

#### User Prompt (`build_page_audit_user_prompt`)

| Data Category | What's Included | Notes |
|---|---|---|
| **Page basics** | Site name, page type, title, URL | Same as Step 2 |
| **Content stats** | Word count, image count + missing alt, video count, doc count, heading structure, internal/external link counts | Computed from raw HTML |
| **Full SEO context** | **Entire output of `format_seo_context_lines()`** — includes ALL data from Step 2's context | Shares `get_seo_context()` |
| **Body content** | Full normalized plain text (NO truncation) | `Content_Helper::get_content()` |
| **Site context** | Business description | `site_chat_context` setting |

**Key difference from Step 2:** The audit prompt is identical in the data it receives. The only difference is the system prompt instructions (audit scoring format vs. metadata generation format). Both use `get_seo_context()` which includes the complete hierarchy, conflicts, topical pages, content analysis, and WooCommerce data.

**Deep analysis mode:** When `deep_analysis = true`, adds ~1,500 char body content excerpts for up to 20 siblings and up to 20 topically related pages.

### What the AI Returns
```json
{
  "score": 72,
  "issues": ["Issue 1", "Issue 2", "...max 10"],
  "suggestions": ["Fix 1", "Fix 2", "...max 10"],
  "missing_alt_tags": 3,
  "word_count": 1250,
  "heading_structure": "H1: 1, H2: 3, H3: 2 — good structure",
  "summary": "Brief 1-2 sentence quality assessment",
  "full_report": "## Comprehensive Markdown audit report..."
}
```

### How Data Is Saved
Stored as a single serialized array in post meta `_ai_seo_captain_page_audit`:
```php
update_post_meta($post_id, '_ai_seo_captain_page_audit', array(
    'score'             => (int),
    'issues'            => (array of strings),
    'suggestions'       => (array of strings),
    'missing_alt_tags'  => (int),
    'word_count'        => (int),
    'heading_structure' => (string),
    'summary'           => (string),
    'full_report'       => (string, Markdown),
    'deep_analysis'     => (bool),
    'audited_at'        => (datetime string),
));
```

**Caching:** On subsequent calls, `handle_page_audit()` checks for existing cached audit data. If `_ai_seo_captain_page_audit` already contains a valid `score`, the cached result is returned immediately without calling the AI.

---

## 3. Site Chat — AI Captain (Site-Wide)

**Entry point:** `Site_Chat::send(string $message, array $focus_ids, array $audit_ids)`  
**AJAX handler:** `Admin_Ajax::handle_site_chat_send()`  
**Trigger:** AI Strategist page chat input.

### Two Modes

#### Mode A: Site-Wide (no focus pages selected)

| Data Category | What's Included | Source |
|---|---|---|
| **Site basics** | Site name, URL, current timestamp | WordPress core |
| **Site context** | Business description | `site_chat_context` setting |
| **Complete site tree** | Every page with title, slug, focus keyphrase | `Content_Indexer::get_compact_site_tree()` |
| **Audit summary** | Score distribution (Starting/Early/Building/Strong counts) | `Content_Indexer::get_audit_summary()` |
| **Duplicate issues** | Duplicate live titles, duplicate AI title drafts, duplicate AI description drafts | `Content_Indexer::build_site_audit_report()` |
| **Thin content** | Pages with low word count | `Content_Indexer::get_thin_content_rows()` |
| **Orphaned content** | Pages with no internal links pointing to them | Computed |
| **Keyphrase cannibalization** | Pages sharing the same focus keyphrase | Site-wide scan |
| **Coverage stats** | Draft %, approval %, frontend % | `Content_Indexer::get_audit_summary()` |
| **Sitemap config** | Status, included types, WooCommerce sitemap settings | Plugin settings |
| **Redirect/404 stats** | Redirect count, 404 count | `Redirects` class |
| **Image usage** | Total images, images with/without alt text | Aggregated |
| **Skip patterns** | Excluded URL patterns | Plugin settings |
| **Recent conversation** | Up to 20 recent messages for context | `History_Store` |

#### Mode B: Focus Pages (1+ pages selected)

For **each selected focus page**, the AI receives:

| Data Category | What's Included |
|---|---|
| **Page identity** | Title, URL, post type, status |
| **Dates** | Published, last modified |
| **Featured image** | Yes/No |
| **Taxonomy terms** | Categories, tags, custom taxonomies |
| **Hierarchy** | Site tree position, parent page (title + URL), breadcrumb chain |
| **SEO metadata** | Focus keyphrase, SEO title (with char count), meta description (with char count), **keywords**, social title, social description, schema type, canonical URL, robots directives, cornerstone flag |
| **Keyphrase quality** | Keyphrase in title (found/missing), keyphrase in description (found/missing) |
| **SEO audit score** | 0–100 or "Not audited yet"; full issues/suggestions if audit page selected |
| **Content analysis** | Word count, heading structure, images total + missing alt, videos, docs, internal/external link counts |
| **Link URLs** | Internal link URLs (max 50), External link URLs (max 30) |
| **Image details** | Per-image src + alt status (max 30) |
| **WooCommerce** | Price, SKU, availability, type, rating, categories, tags, brand (if product) |
| **Body content** | **Full plain text** (no truncation) |

**Cross-page analysis included:**
- Keyphrase cannibalization among selected pages
- Duplicate title/description detection among selected pages

### What the AI Returns
```json
{
  "reply": "Markdown-formatted strategic analysis...",
  "notes": "Internal analysis approach note"
}
```

### How Data Is Saved
- Conversation stored via `History_Store` (conversations + messages tables)
- No SEO metadata is modified by Site Chat — it's advisory only

### Dynamic Limits

| Mode | Formula | Example (200K model) | Example (1M model) |
|---|---|---|---|
| **Tree mode** | `floor((context_window × 0.6) / 175)` | 685 pages | 3,591 pages |
| **Focus mode** | `floor(((context_window × 0.6) - 5000) / 3000)` | 38 pages | 208 pages |

---

## 4. Editor Mode — AI Commander Chat

**Entry point:** `AI_Generator::chat_for_post(int $post_id, string $message, array $recent_messages, bool $deep_analysis)`  
**AJAX handler:** `Admin_Ajax::handle_chat_for_post()`  
**Trigger:** Chat input in the editor metabox Commander tab.

### What the AI Receives

#### System Prompt (`build_chat_system_prompt`)
- Plugin identity (SEO Captain)
- Required JSON: `reply`, `suggested_title`, `suggested_description`, `wants_edits`, `notes`
- Full knowledge declaration: URL, all metadata, full content, headings, images, links, audit, hierarchy, conflicts, site tree
- Two SEO categories defined: A) Metadata vs B) Content/Structure
- Hierarchy & cannibalization rules (8–11)
- Response rules (1–7): differentiation, flag gaps, reference audit data, keyphrase enforcement
- Title branding rules (if enabled)

#### User Prompt (`build_chat_user_prompt`)

**Everything from Step 2's context PLUS:**

| Additional Data | What's Included |
|---|---|
| **Page HTML structure** | Raw HTML with shortcodes stripped (for heading/image/link analysis) |
| **Plain text content** | Normalized body text (no truncation) |
| **Recent conversation** | Up to 8 most recent chat messages (USER: ... / ASSISTANT: ...) |
| **User question** | The current message from the user |
| **Site context** | Business description |
| **Branding note** | Title branding suffix and budget (if enabled) |

**The AI sees the SAME context as Step 2 (via `get_seo_context()`) plus HTML structure, conversation history, and the user's question.** Deep analysis mode adds sibling/topical body content excerpts.

### What the AI Returns
```json
{
  "reply": "Detailed answer to the user's question...",
  "suggested_title": "Improved SEO Title (optional)",
  "suggested_description": "Improved meta description (optional)",
  "wants_edits": false,
  "notes": "Analysis approach note"
}
```

### How Data Is Saved
- Chat messages stored via `History_Store` (conversations + messages tables)
- `suggested_title` / `suggested_description` displayed in UI for user to manually apply
- `wants_edits` triggers the AI Content Editor workflow if `true`

---

## 5. Editor Mode — Page Audit

**Entry point:** `AI_Generator::generate_page_audit(int $post_id, bool $deep_analysis)`  
**AJAX handler:** `Admin_Ajax::handle_page_audit()`  
**Trigger:** "Run Audit" button in editor metabox, or Setup Wizard Step 3.

### Identical to Step 3

The editor-mode audit uses **exactly the same code path** as Step 3 in the wizard:
- Same `generate_page_audit()` method
- Same `build_page_audit_system_prompt()` and `build_page_audit_user_prompt()`
- Same `get_seo_context()` call with all hierarchy, conflicts, topical pages, content analysis
- Same data saved to `_ai_seo_captain_page_audit` post meta

**The only difference is the trigger:**
- **Step 3 (Wizard):** Bulk flow processes multiple pages sequentially with progress bar, pause/resume/stop
- **Editor mode:** Single page, triggered by button click

**Deep analysis toggle:** Available in both contexts. Adds ~1,500 char body content excerpts for siblings and topically related pages.

**Caching:** Both paths check for cached audit. If audit exists, cached result returned without AI call.

---

## 6. AI Content Editor

**Entry point:** `AI_Generator::generate_content_changes(int $post_id, string $instruction, array $recent_messages)`  
**AJAX handler:** `Admin_Ajax::handle_content_edit()`  
**Trigger:** Content edit instruction from editor metabox or Commander chat with `wants_edits = true`.

### What the AI Receives

| Data Category | What's Included |
|---|---|
| **Page basics** | Site name, page type, title, URL |
| **Full SEO context** | Everything from `get_seo_context()` (hierarchy, conflicts, topical, content analysis, WooCommerce) |
| **Recent conversation** | Chat context (if coming from Commander chat) |
| **User instruction** | The specific edit request (e.g., "Improve the intro paragraph") |
| **Full page content** | Raw HTML content (NOT normalized — needs exact text for find/replace) |

### What the AI Returns
```json
{
  "changes": [
    {
      "section": "Heading 1",
      "old": "exact original text",
      "new": "improved replacement text",
      "reason": "SEO improvement explanation",
      "tag_change": "h3→h2"
    }
  ],
  "summary": "Overall improvement description"
}
```

### How Data Is Saved
1. Changes stored as pending via `Content_Writer` class → `_ai_seo_captain_pending_changes` post meta
2. User reviews changeset in diff view
3. User clicks **Apply** → `handle_apply_changes()` applies changes to `post_content`
4. Original content backed up in `_ai_seo_captain_content_backup` post meta
5. User can **Restore** backup at any time

---

## 7. Site Audit Report (Dashboard)

**Entry point:** `AI_Generator::generate_site_audit(array $report)`  
**AJAX handler:** `Admin_Ajax::handle_generate_site_audit()`  
**Trigger:** "Generate AI Audit" button on Dashboard page.

### What the AI Receives

| Data Category | What's Included |
|---|---|
| **Summary counts** | Total pages, audited count, score distribution (Starting/Early/Building/Strong) |
| **Priority content rows** | Pages needing work (title draft status, description draft status, approval status, frontend status) — max 8 |
| **Duplicate live titles** | Groups of pages sharing the same post title — max 8 groups |
| **Duplicate AI title drafts** | Groups of pages sharing the same AI-generated SEO title — max 8 groups |
| **Duplicate AI description drafts** | Groups of pages sharing the same AI-generated description — max 8 groups |
| **Thin content rows** | Pages under 140 words — max 8 |
| **Title branding** | Branding suffix info (if enabled) |
| **Discovery URLs** | `llms.txt`, `llms-full.txt`, sitemap URL |

**Note:** This is a SITE-LEVEL audit (not per-page). It does NOT read individual page content — it works from the content index aggregates.

### What the AI Returns
```json
{
  "audit_title": "SEO Captain Site Audit",
  "executive_summary": "Overall site SEO health assessment...",
  "priority_actions": ["Action 1", "Action 2", "...max 5"],
  "quick_wins": ["Quick fix 1", "Quick fix 2", "...max 5"],
  "notes": "Analysis notes"
}
```

### How Data Is Saved
- Rendered directly in Dashboard UI
- History entry stored for the site audit conversation

---

## Appendix A — Shared Data Methods

### `get_seo_context()` — The Central Context Builder

Used by: Step 2, Step 3, Editor Chat, Editor Audit, Content Editor.

Calls these 4 Content_Indexer methods:

| Method | Returns | SQL Limit |
|---|---|---|
| `get_hierarchy_context()` | Parent, grandparent, siblings, children, position string | 20 siblings, 20 children |
| `get_keyphrase_conflicts()` | All pages with same focus keyphrase | **No limit** |
| `get_topically_related_pages()` | Cross-hierarchy keyword matches | 20 pages (default) |
| `get_compact_site_tree()` | Full site tree with titles + keyphrases | All published pages |

Also calls:
- `fetch_page_with_seo_meta()` for parent/grandparent individual lookups
- `ai_seo_captain_product_context` filter for WooCommerce data
- `Content_Helper::get_content()` for raw HTML (with per-request caching)

### `format_seo_context_lines()` — The Formatter

Converts the context array into human-readable text for AI prompts. Includes:
- Current page SEO metadata with character counts and keyphrase presence flags
- Social & Advanced fields (schema, canonical, robots, cornerstone)
- WooCommerce product data (if applicable)
- Content analysis stats (word count, headings, images, links, videos, docs)
- Image details (src + alt, max 30)
- Link URLs (internal max 50, external max 30)
- Full page hierarchy with all 6 SEO fields per page
- Keyphrase conflict warnings
- Topically related pages with all 6 SEO fields
- Compact site tree

### The 6+1 SEO Fields Per Sibling/Related Page

Every sibling, child, parent, grandparent, keyphrase conflict, and topically related page includes:
1. **Page title** — WordPress post title
2. **SEO title** — `_ai_seo_captain_meta_title`
3. **Meta description** — `_ai_seo_captain_meta_description`
4. **Focus keyphrase** — `_ai_seo_captain_focus_keyphrase`
5. **Keywords** — `_ai_seo_captain_keywords`
6. **Social title** — `_ai_seo_captain_social_title`
7. **Social description** — `_ai_seo_captain_social_description`

---

## Appendix B — Token Budgets & Limits

### Model Context Windows

| Model | Context Window | Provider |
|---|---|---|
| GPT-5.5 | 1,050,000 | OpenAI |
| GPT-5.4 | 1,000,000 | OpenAI |
| GPT-5.4 Mini | 400,000 | OpenAI |
| GPT-5.4 Nano | 1,000,000 | OpenAI |
| o3 / o4-mini | 200,000 | OpenAI |
| GPT-4.1 / 4.1 Mini / 4.1 Nano | 1,047,576 | OpenAI |
| Gemini 2.5 Pro / Flash / Flash-Lite | 1,048,576 | Google |
| Gemini 3 Flash / 3.1 Pro / 3.1 Flash-Lite | 1,048,576 | Google |
| **Unknown model fallback** | **200,000** | — |

### Per-Feature Token Estimates (Worst Case)

| Feature | Base | +20 Siblings | +20 Topical | +20 Children | Conflicts | Deep Analysis | Total |
|---|---|---|---|---|---|---|---|
| **Step 2** | ~2K | ~5K | ~5K | ~5K | ~2K | +20K (excerpts) | **~14K** / **~34K deep** |
| **Step 3 Audit** | ~3K | ~5K | ~5K | ~5K | ~2K | +20K | **~15K** / **~35K deep** |
| **Editor Chat** | ~5K | ~5K | ~5K | ~5K | ~2K | +20K | **~17K** / **~37K deep** |
| **Site Chat (focus)** | ~3K/page | — | — | — | — | — | **~3K × N pages** |
| **Site Chat (tree)** | ~175/page | — | — | — | — | — | **175 × N pages** |

**All features are safe on the smallest supported model (200K tokens).** Even worst-case deep analysis (~37K tokens) uses less than 19% of the 200K budget.

### Hardcoded Limits

| Limit | Value | Location |
|---|---|---|
| Siblings per page | 20 | `get_hierarchy_context()` SQL LIMIT |
| Children per page | 20 | `get_hierarchy_context()` SQL LIMIT |
| Topically related pages | 20 | `get_topically_related_pages()` $limit param |
| Keyphrase conflicts | **Unlimited** | `get_keyphrase_conflicts()` — no SQL LIMIT |
| Site tree pages | All published | `get_compact_site_tree()` |
| Image details | 30 | `get_seo_context()` array_slice |
| Internal link URLs | 50 | `get_seo_context()` array_slice |
| External link URLs | 30 | `get_seo_context()` loop cap |
| Sibling content excerpt | 1,500 chars | `get_seo_context()` mb_substr |
| Topical page excerpt | 1,500 chars | `get_topically_related_pages()` truncate_text |
| Audit issues | 10 max | `sanitize_string_list()` |
| Audit suggestions | 10 max | `sanitize_string_list()` |
| Priority actions | 5 max | `sanitize_string_list()` |
| Quick wins | 5 max | `sanitize_string_list()` |
| Content edit changes | 30 max | System prompt instruction |
| Chat recent messages | 8 | `get_recent_messages(8)` |
| Site Chat recent messages | 20 | `get_recent_messages(20)` |
| Focus pages max | Dynamic | `floor(((context_window × 0.6) - 5000) / 3000)` |
| Tree mode max pages | Dynamic | `floor((context_window × 0.6) / 175)` |
