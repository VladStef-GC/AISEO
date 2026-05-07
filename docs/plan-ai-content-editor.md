# AI Content Editor — Implementation Plan

**Date:** May 7, 2026
**Status:** Planned
**Module:** `includes/class-content-writer.php` (new)

---

## Overview

Enable the AI chat assistant to not only suggest SEO improvements, but **directly edit page content** — rephrasing text, fixing heading hierarchy, and optimizing for the focus keyphrase — while preserving all non-text elements (buttons, links, images, styles, widgets).

---

## Architecture: Changeset Pattern

The industry-standard approach used by Cursor, GitHub Copilot, Notion AI, and similar tools. **Not** a multi-batch rewrite — a single API call that returns surgical find-and-replace changes.

### Why Not Multi-Batch?

| Factor | Multi-batch | Changeset |
|---|---|---|
| API calls per edit | 5–20 | **1** |
| Context between batches | Lost | **Full context, single call** |
| Cost | Expensive | **Cheap** |
| Speed | Slow (sequential) | **Fast (~3-5s)** |
| Review UX | Hard to show diff | **Natural diff cards** |
| Consistency | Risk of style drift | **Single voice** |

### Token Budget Reality

| Model | Input Limit | Output Limit |
|---|---|---|
| gpt-4.1-mini | 128K tokens (~96K words) | 16K tokens |
| gemini-2.0-flash | 1M tokens (~750K words) | 8K tokens |
| gpt-4o | 128K tokens | 16K tokens |

A typical WordPress page is 500–5,000 words (750–7,500 tokens). Even with full HTML markup, that's ~15K tokens — **12% of gpt-4.1-mini's input**. Single-call changeset works for 99.9% of real pages.

---

## Three Phases

### Phase 1: ANALYZE (Single API Call)

```
INPUT (fits easily in one call):
├── Full page content (structured HTML/text via Content_Helper)
├── SEO context (keyphrase, current title/description drafts, snippet scores)
├── Page audit results (score, issues, suggestions — if available)
├── User instruction ("rephrase for SEO", "fix headings", etc.)
└── Page URL, site name, related pages

OUTPUT (small & structured JSON):
└── Array of targeted changes:
    [
      {
        "section": "Hero paragraph",
        "old": "We make software for businesses",
        "new": "We deliver AI-powered automation solutions for enterprises",
        "reason": "Incorporates focus keyphrase and strengthens value proposition",
        "tag_change": null
      },
      {
        "section": "Services heading",
        "old": "<h3>What We Do</h3>",
        "new": "<h2>AI Robotic Process Automation Services</h2>",
        "reason": "Page missing H2 level; promotes heading and adds keyphrase",
        "tag_change": "h3 → h2"
      }
    ]
```

### Phase 2: REVIEW (UI — Draft Cards)

User sees diff cards for each proposed change:

```
┌──────────────────────────────────────┐
│ 📝 Hero paragraph                    │
│                                      │
│ BEFORE: "We make software for..."    │
│ AFTER:  "We deliver AI-powered..."   │
│ WHY:    Incorporates focus keyphrase │
│                                      │
│ [✓ Accept]  [✗ Reject]               │
└──────────────────────────────────────┘
```

- Each change is independently accept/reject
- No changes applied until user clicks "Apply N accepted changes"
- Clear before/after diff with reasoning

### Phase 3: APPLY (Write Back)

For each accepted change, apply `str_replace($old, $new)` to the correct storage:

| Builder | Storage | Write Method |
|---|---|---|
| Classic Editor | `post_content` | `wp_update_post()` |
| Gutenberg | `post_content` (with block comments) | `wp_update_post()` |
| BeTheme | `mfn-page-items-seo` meta | `update_post_meta()` |
| Elementor | `_elementor_data` meta (JSON) | `update_post_meta()` |
| Beaver Builder | `_fl_builder_data` meta | `update_post_meta()` |
| Bricks | `_bricks_page_content_2` meta | `update_post_meta()` |
| WPBakery | `post_content` (shortcodes) | `wp_update_post()` |
| Divi | `post_content` (shortcodes) | `wp_update_post()` |

**Key insight:** Since the AI's changeset uses exact original text as the search key, `str_replace()` works regardless of the wrapper format (HTML, JSON, serialized arrays, shortcodes). The text content is the same in all formats.

---

## Prerequisites (Phase A — Do First)

Before building the content editor, inject full SEO context into the chat prompt:

### Add to `build_chat_user_prompt()`:

- [x] Page content via Content_Helper (already done)
- [ ] Current `_ai_seo_keeper_meta_title` draft
- [ ] Current `_ai_seo_keeper_meta_description` draft
- [ ] Snippet score metrics (title length, desc length, keyphrase-in-title, keyphrase-in-desc)
- [ ] Page audit data if exists (`_ai_seo_keeper_page_audit` — score, issues, suggestions)

### Add "Apply" button for chat suggestions:

- [ ] When AI suggests a title/description, show "Apply to draft" button
- [ ] Button writes the value into the corresponding meta field via AJAX
- [ ] Field updates in real-time without page reload

---

## New Files & Classes

### `includes/class-content-writer.php`

Companion to `Content_Helper` — same builder detection logic, but for writing back.

```php
namespace AI_SEO_Keeper;

class Content_Writer {
    /**
     * Apply a set of text changes to a post's content.
     *
     * @param int   $post_id  The post to modify.
     * @param array $changes  Array of ['old' => string, 'new' => string] pairs.
     * @return array Results: ['applied' => int, 'failed' => int, 'details' => [...]]
     */
    public static function apply_changes(int $post_id, array $changes): array;

    /**
     * Detect which storage format the post uses.
     * Returns: 'classic', 'gutenberg', 'betheme', 'elementor', etc.
     */
    public static function detect_builder(int $post_id): string;

    /**
     * Create a revision/backup before applying changes.
     */
    private static function create_backup(int $post_id): void;
}
```

### New AJAX Actions

| Action | Purpose |
|---|---|
| `ai_seo_keeper_content_edit` | Request AI changeset for current page |
| `ai_seo_keeper_apply_changes` | Apply accepted changes to page content |
| `ai_seo_keeper_apply_suggestion` | Apply a single chat suggestion to a meta field |

---

## AI Rules for Content Editing

Added to the system prompt when content editing is requested:

1. **Only rephrase text** — never remove or add structural elements
2. **Fix heading hierarchy** — add missing H1, fix H3→H2 where appropriate
3. **Preserve all HTML attributes** — classes, IDs, styles, data-* attributes
4. **Never touch:** buttons, forms, images, iframes, scripts, shortcodes, widgets
5. **Keep hyperlinks intact** — may rephrase anchor text but never change href
6. **Each change must have a clear SEO reason**
7. **Return exact original text** in the "old" field for reliable find-and-replace
8. **Limit to 30 changes max** per request

---

## Safety Net: Section Fallback

For the rare case where a page exceeds 80K tokens (~60K words):

1. **Token estimator:** `strlen($content) / 3 ≈ token count`
2. **Split by headings** — each section is a natural boundary
3. Each batch gets: section content + **compact page summary** (title, URL, keyphrase, full heading outline)
4. Compact summary ensures context is maintained across batches
5. Collect all changesets → merge → show unified review UI

**Expected frequency:** Essentially never for real WordPress pages.

---

## Implementation Order

1. **Phase A:** Inject full SEO context into chat prompt (quick win)
2. **Phase A:** Add "Apply" buttons for chat title/description suggestions
3. **Phase B:** Build `Content_Writer` class with builder detection + str_replace
4. **Phase B:** Add content edit AJAX action + AI prompt for changeset
5. **Phase B:** Build review UI (diff cards with Accept/Reject)
6. **Phase B:** Wire Apply action to Content_Writer
7. **Phase B:** Add revision/backup before changes
8. **Testing:** Test with Classic, Gutenberg, BeTheme builders
