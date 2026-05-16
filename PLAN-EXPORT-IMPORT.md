# AI SEO Keeper — Export/Import Overhaul Plan

**Version:** 1.0  
**Date:** 2026-05-16  
**Status:** Planning  
**Plugin Version Target:** 1.0.0-beta → 1.1.0

---

## 1. Core Principle (ABSOLUTE RULE)

> **The Import process will NEVER create or delete any page/post/term on the target site.**  
> It only updates SEO metadata on pages that already exist on BOTH the export source AND the import target.

This applies to ALL import modes — including Force Import.

**Example:**  
- Export file contains SEO data for 23 pages  
- Target site has 26 pages  
- After import: target site still has 26 pages  
- Only the pages that match between the 23 exported and the 26 existing get their SEO fields updated  
- The 3 extra pages on the target are completely untouched  
- Any pages in the export that don't exist on the target are reported as "skipped/orphaned"

---

## 2. Current State (What Exists Today)

### 2.1 Exported (partial)
| Data | Status |
|------|--------|
| Plugin settings (`ai_seo_keeper_options`) | ✅ Exported (minus API keys) |
| 12 of 18 post meta keys | ✅ Partial |
| Redirects (type=redirect only, no stats) | ✅ Partial |

### 2.2 NOT Exported (gaps)
| Data | Type |
|------|------|
| 6 post meta keys | `title_branding_off`, `page_audit`, `audit_skip`, `approved_message_id`, `pending_content_changes`, `content_backup` |
| ALL 4 term meta keys | `_ai_seo_keeper_seo_title`, `_ai_seo_keeper_meta_description`, `_ai_seo_keeper_canonical`, `_ai_seo_keeper_noindex` |
| Content index table | `{prefix}ai_seo_keeper_content_index` |
| Conversations table | `{prefix}ai_seo_keeper_conversations` |
| Messages table | `{prefix}ai_seo_keeper_messages` |
| Runs table | `{prefix}ai_seo_keeper_runs` |
| 404 entries | Redirects table where type=404 |
| Redirect stats | `hit_count`, `last_hit`, `created_at` columns |
| IndexNow log | `ai_seo_keeper_indexnow_log` option |
| Wizard flags | `ai_seo_keeper_step2_all_done`, `ai_seo_keeper_step3_all_done` |

### 2.3 Import Limitations
- Single JSON upload, no mode selection
- No page matching logic
- No conflict resolution
- No progress reporting
- No mismatch reporting

---

## 3. New Export Design

### 3.1 Export Sections (User-Selectable Checkboxes)

| # | Checkbox Label | Data Included | Default |
|---|---------------|---------------|---------|
| 1 | **Plugin Settings** | `ai_seo_keeper_options` (minus API keys), wizard completion flags | ✅ On |
| 2 | **SEO Metadata (Posts & Pages)** | All 18 post meta keys for all post types | ✅ On |
| 3 | **SEO Metadata (Terms)** | All 4 term meta keys for all taxonomies | ✅ On |
| 4 | **Page Audits & Content Index** | `page_audit` meta + content index table | ✅ On |
| 5 | **Redirect Rules** | Redirects (type=redirect) with hit counts and timestamps | ✅ On |
| 6 | **404 Log** | Redirects (type=404) — site-specific data | ⬜ Off |
| 7 | **Batch Run Lists** | Runs table (user-created page lists) | ⬜ Off |
| 8 | **AI Chat History** | Conversations + messages tables | ⬜ Off |

### 3.2 Excluded From Export (By Design)

| Data | Reason |
|------|--------|
| API keys (OpenAI, etc.) | Security — never export credentials |
| `pending_content_changes` | Transient/in-progress data |
| `content_backup` | Transient — content before AI edit |
| `approved_message_id` | References chat IDs that won't exist on target |
| User meta (`_ai_seo_keeper_active_runs`) | Per-user state, not site data |
| `ai_seo_keeper_db_version` | Target site manages its own DB version |
| Page content / media | Out of scope — WordPress handles this natively |

### 3.3 Export File Format

**Filename:** `ai-seo-keeper-export-{domain}-{YYYY-MM-DD}.json`

```json
{
  "plugin": "ai-seo-keeper",
  "format_version": "2.0",
  "plugin_version": "1.0.0-beta",
  "exported_at": "2026-05-16T10:30:00Z",
  "source_domain": "site-a.com",
  "sections_included": ["settings", "seo_meta_posts", "seo_meta_terms", "audits", "redirects"],
  "counts": {
    "posts": 23,
    "terms": 8,
    "redirects": 15,
    "content_index_entries": 23
  },
  "data": {
    "settings": {
      "options": { "...all plugin options minus API keys..." },
      "wizard_flags": {
        "step2_all_done": true,
        "step3_all_done": true
      }
    },
    "seo_meta_posts": [
      {
        "match_key": {
          "post_type": "page",
          "slug": "about-us",
          "title": "About Us",
          "original_id": 42
        },
        "meta": {
          "_ai_seo_keeper_meta_title": "About Us | Company Name",
          "_ai_seo_keeper_meta_description": "Learn about...",
          "_ai_seo_keeper_focus_keyphrase": "about company",
          "_ai_seo_keeper_social_title": "...",
          "_ai_seo_keeper_social_description": "...",
          "_ai_seo_keeper_social_image": "https://site-a.com/wp-content/uploads/...",
          "_ai_seo_keeper_schema_type": "WebPage",
          "_ai_seo_keeper_canonical_url": "https://site-a.com/about-us/",
          "_ai_seo_keeper_robots_directives": "",
          "_ai_seo_keeper_frontend_enabled": "1",
          "_ai_seo_keeper_cornerstone": "1",
          "_ai_seo_keeper_hreflang": "",
          "_ai_seo_keeper_title_branding_off": "0",
          "_ai_seo_keeper_page_audit": "{...audit JSON...}",
          "_ai_seo_keeper_audit_skip": "0"
        }
      }
    ],
    "seo_meta_terms": [
      {
        "match_key": {
          "taxonomy": "category",
          "slug": "news",
          "name": "News",
          "original_term_id": 5
        },
        "meta": {
          "_ai_seo_keeper_seo_title": "News Articles",
          "_ai_seo_keeper_meta_description": "Latest news...",
          "_ai_seo_keeper_canonical": "",
          "_ai_seo_keeper_noindex": "0"
        }
      }
    ],
    "audits": {
      "content_index": [
        {
          "post_id_match_key": { "post_type": "page", "slug": "about-us" },
          "word_count": 850,
          "last_indexed": "2026-05-10T08:00:00Z",
          "content_hash": "abc123...",
          "...other columns..."
        }
      ]
    },
    "redirects": [
      {
        "source_url": "/old-page/",
        "target_url": "/new-page/",
        "type": "301",
        "hit_count": 42,
        "last_hit": "2026-05-15T12:00:00Z",
        "created_at": "2026-01-10T09:00:00Z"
      }
    ],
    "four_oh_four_log": [],
    "runs": [],
    "chat_history": {
      "conversations": [],
      "messages": []
    }
  }
}
```

---

## 4. Import Modes

### 4.1 Force Import

**What it does:**
- For every **matched** page/term: DELETES all existing AI SEO Keeper meta and REPLACES with export data
- Unmatched pages on target: **LEFT COMPLETELY ALONE** (no deletion, no modification)
- Unmatched pages in export: reported as "skipped — not found on target"
- Settings: completely overwritten with export values
- Redirects: existing redirects table is cleared, export redirects are inserted
- Content index: existing entries for matched pages are replaced

**What it does NOT do:**
- ❌ Never deletes any page, post, or term
- ❌ Never creates any page, post, or term
- ❌ Never touches pages that don't match

**Use case:** "I want this site's SEO data to be an exact mirror of my export, but only for the pages that exist here."

### 4.2 Update Import (Fill Gaps Only)

**What it does:**
- For every **matched** page/term: only fills in fields that are currently EMPTY
- If a meta field already has a value on the target → it is NEVER overwritten
- Settings: only fills in settings keys that are empty/unset
- Redirects: only adds redirects whose source URL doesn't already exist
- Content index: only adds entries for pages that have no index entry

**What it does NOT do:**
- ❌ Never overwrites existing data
- ❌ Never deletes any data
- ❌ Never deletes/creates pages

**Use case:** "I have partial SEO data, fill in the blanks from this export."

### 4.3 Overwrite Import (Match & Replace)

**What it does:**
- For every **matched** page/term: overwrites ALL meta fields with export data (even if target has values)
- But does NOT clear meta fields that exist on target but are absent from export
- Unmatched pages: completely untouched
- Settings: merged (export values win for overlapping keys)
- Redirects: matched by source URL — existing ones updated, new ones added

**What it does NOT do:**
- ❌ Never deletes pages
- ❌ Never deletes redirects that aren't in the export
- ❌ Never clears fields that the export doesn't contain

**Use case:** "The export data should win for all matching pages, but don't destroy anything else."

---

## 5. Page/Term Matching Algorithm

### 5.1 Match Priority (Posts/Pages)

| Priority | Method | Confidence |
|----------|--------|-----------|
| 1 | Same `slug` + same `post_type` | **Strong** — auto-match |
| 2 | Same `title` + same `post_type` (slug differs) | **Fuzzy** — show to user for confirmation |
| 3 | Same `original_id` but different slug/title | **Weak** — likely different page, skip by default |
| 4 | No match found | **Orphaned** — skip and report |

### 5.2 Match Priority (Terms)

| Priority | Method | Confidence |
|----------|--------|-----------|
| 1 | Same `slug` + same `taxonomy` | **Strong** — auto-match |
| 2 | Same `name` + same `taxonomy` (slug differs) | **Fuzzy** — show to user |
| 3 | No match found | **Orphaned** — skip and report |

### 5.3 Content Index Matching

Content index entries reference `post_id`. On import:
1. Find the matching post via slug+type
2. Get the target post's ID
3. Insert/update the content index entry with the target's post ID

### 5.4 Chat History Matching

No matching needed — conversations and messages are imported as-is with new auto-increment IDs. References to post IDs inside messages are remapped using the match table.

---

## 6. Domain Change / URL Rewriting

### 6.1 When Domains Differ

If `source_domain` in the export ≠ current site domain, prompt user:

> "This export was created on **site-a.com**. Your site is **site-b.com**.  
> Would you like to automatically rewrite URLs?"  
> `[Yes, rewrite URLs]` `[No, import as-is]`

### 6.2 What Gets Rewritten

| Field | Rewrite? |
|-------|----------|
| `_ai_seo_keeper_canonical_url` | ✅ Yes — replace domain |
| `_ai_seo_keeper_social_image` | ✅ Yes — replace domain (media must exist on target) |
| Redirect `source_url` | ⚠️ Only if absolute URLs (relative paths stay as-is) |
| Redirect `target_url` | ✅ Yes — replace domain |
| Content index URLs | ✅ Yes — replace domain |

### 6.3 What Does NOT Get Rewritten

| Field | Reason |
|-------|--------|
| SEO titles | Text, no URLs |
| Meta descriptions | Text, no URLs |
| Focus keyphrases | Text, no URLs |
| Schema type | Enum value |
| Robots directives | Directives, not URLs |

---

## 7. Import UI Flow

### Step 1 — Upload & Validate

**Screen shows:**
- File upload dropzone
- After upload, display:
  - ✅ Valid AI SEO Keeper export file
  - Source: `site-a.com`
  - Exported: May 16, 2026
  - Plugin version: 1.0.0-beta
  - Sections: Settings, SEO Meta (23 posts, 8 terms), Audits, Redirects (15)

**Validation checks:**
- Valid JSON structure
- Has required `plugin` and `format_version` fields
- `format_version` compatible with current plugin
- File not corrupted (all expected sections present)

### Step 2 — Choose Import Mode

```
┌─────────────────────────────────────────────────────┐
│  Import Mode                                         │
│                                                      │
│  ○ Force Import                                      │
│    Replace all SEO data for matched pages            │
│                                                      │
│  ○ Update Import (recommended)                       │
│    Only fill in missing/empty fields                 │
│                                                      │
│  ○ Overwrite Import                                  │
│    Export data wins for matched pages                 │
│                                                      │
├─────────────────────────────────────────────────────┤
│  Sections to Import:                                 │
│  ☑ Plugin Settings                                   │
│  ☑ SEO Metadata (Posts & Pages) — 23 entries         │
│  ☑ SEO Metadata (Terms) — 8 entries                  │
│  ☑ Page Audits & Content Index                       │
│  ☑ Redirect Rules — 15 entries                       │
│  ☐ 404 Log (not included in this export)             │
│  ☐ Batch Run Lists (not included in this export)     │
│  ☐ AI Chat History (not included in this export)     │
└─────────────────────────────────────────────────────┘
```

Sections not present in the export file are grayed out with "(not included in this export)".

### Step 3 — Domain Rewriting (conditional)

Only shown if `source_domain ≠ target_domain`:

```
┌─────────────────────────────────────────────────────┐
│  ⚠️ Domain Mismatch Detected                        │
│                                                      │
│  Export source: site-a.com                           │
│  Your site:     site-b.com                           │
│                                                      │
│  ○ Rewrite URLs (site-a.com → site-b.com)           │
│  ○ Import URLs as-is (keep site-a.com references)   │
└─────────────────────────────────────────────────────┘
```

### Step 4 — Matching Preview Table

```
┌──────────────────────────────────────────────────────────────────────────┐
│  Page Matching Results                                                    │
│                                                                          │
│  ✅ 20 strong matches  |  ⚠️ 2 fuzzy matches  |  ❌ 1 not found          │
│                                                                          │
│  ┌────────────────────────────────────────────────────────────────────┐  │
│  │ Export Page          │ Status    │ Target Match         │ Import? │  │
│  ├──────────────────────┼───────────┼──────────────────────┼─────────┤  │
│  │ /about-us (Page)     │ ✅ Match  │ /about-us (ID 42)    │ ☑       │  │
│  │ /services (Page)     │ ✅ Match  │ /services (ID 18)    │ ☑       │  │
│  │ /blog/post-1 (Post)  │ ⚠️ Fuzzy  │ /blog/post-one (ID 7)│ ☐       │  │
│  │ /old-page (Page)     │ ❌ Missing │ —                    │ —       │  │
│  └────────────────────────────────────────────────────────────────────┘  │
│                                                                          │
│  Term Matching Results                                                   │
│  ✅ 7 strong matches  |  ❌ 1 not found                                  │
│  (similar table for terms)                                               │
│                                                                          │
│  ℹ️ Only checked items will be imported.                                 │
│  ℹ️ Pages on your site that are NOT in the export will not be touched.   │
└──────────────────────────────────────────────────────────────────────────┘
```

- Strong matches: auto-checked ☑
- Fuzzy matches: unchecked by default ☐ (user must opt-in)
- Missing: no checkbox, just informational

### Step 5 — Confirmation (Force Import only)

Only for Force Import mode:

```
┌─────────────────────────────────────────────────────┐
│  ⚠️ Force Import Warning                            │
│                                                      │
│  This will REPLACE all existing SEO data for the     │
│  20 matched pages. Existing values will be lost.     │
│                                                      │
│  Your other 6 pages (not in the export) will NOT     │
│  be affected.                                        │
│                                                      │
│  Type "FORCE" to confirm: [___________]              │
│                                                      │
│  [Cancel]  [Start Import]                            │
└─────────────────────────────────────────────────────┘
```

### Step 6 — Import Progress

```
┌─────────────────────────────────────────────────────┐
│  ⚠️ Do not close or refresh this page!              │
│                                                      │
│  Importing... 14 / 20 pages                          │
│  [████████████████░░░░░░░░] 70%                      │
│                                                      │
│  Current: Importing SEO meta for /contact-us         │
│                                                      │
│  Log:                                                │
│  ✅ /about-us — 12 fields imported                   │
│  ✅ /services — 8 fields imported                    │
│  ✅ /home — 15 fields imported                       │
│  ...                                                 │
└─────────────────────────────────────────────────────┘
```

### Step 7 — Import Report

```
┌─────────────────────────────────────────────────────┐
│  ✅ Import Complete!                                 │
│                                                      │
│  Summary:                                            │
│  • 20 pages updated                                  │
│  • 7 terms updated                                   │
│  • 15 redirects imported                             │
│  • 1 page skipped (not found on target)              │
│  • 2 pages skipped (fuzzy match — not confirmed)     │
│  • 0 errors                                          │
│                                                      │
│  [Download Full Report]  [Done]                      │
└─────────────────────────────────────────────────────┘
```

---

## 8. Technical Architecture

### 8.1 Processing Strategy

- **AJAX-based chunked processing** — not a single long request
- Process 10-20 items per AJAX call
- Each call returns progress data for the UI to update
- If connection drops mid-import, data remains consistent (each item is atomic)
- No PHP timeout issues (each chunk completes in < 10 seconds)

### 8.2 Import Sequence

```
1. Upload JSON → validate → store in transient (or temp file for large exports)
2. User selects mode + sections → AJAX call to run matching algorithm
3. Matching results returned → displayed in preview table
4. User confirms → AJAX loop begins:
   a. Each call processes N items from the queue
   b. Returns: items_done, items_total, current_item, log_entries
   c. JS updates progress bar and log
   d. Repeat until queue empty
5. Final AJAX call: generate summary report
```

### 8.3 File Structure

```
ai-seo-keeper/
├── includes/
│   ├── admin/
│   │   ├── class-admin-import-export.php    ← EXISTING (will be refactored)
│   │   └── view-export-import.php           ← EXISTING (will be updated)
│   └── modules/
│       └── import-export/
│           ├── class-exporter.php           ← NEW: builds export JSON
│           ├── class-importer.php           ← NEW: processes import (3 modes)
│           ├── class-matcher.php            ← NEW: page/term matching logic
│           └── class-url-rewriter.php       ← NEW: domain URL replacement
├── assets/
│   ├── js/
│   │   └── page-export-import.js            ← EXISTING (will be expanded)
│   └── css/
│       └── page-export-import.css           ← EXISTING (will be expanded)
```

### 8.4 Class Responsibilities

**`class-exporter.php`**
- Collects all data based on selected sections
- Strips API keys from settings
- Builds the `match_key` for each post/term
- Generates the JSON file and triggers download

**`class-matcher.php`**
- Receives export data + queries target site's posts/terms
- Runs matching algorithm (slug → title → ID fallback)
- Returns match results: `{ strong: [], fuzzy: [], orphaned: [] }`

**`class-importer.php`**
- Receives matched pairs + import mode
- Processes items in chunks (called repeatedly via AJAX)
- Force mode: `delete_post_meta` all plugin keys, then `update_post_meta` with export data
- Update mode: `get_post_meta` first, only `update_post_meta` where empty
- Overwrite mode: `update_post_meta` for all keys in export (no delete)
- Handles settings, redirects, content index, chat history

**`class-url-rewriter.php`**
- Simple string replacement: `source_domain` → `target_domain`
- Applied to canonical URLs, social image URLs, redirect targets
- Only activated if user opts in

### 8.5 AJAX Endpoints

| Action | Purpose |
|--------|---------|
| `aisk_export_generate` | Generate and download export file |
| `aisk_import_validate` | Validate uploaded JSON, return summary |
| `aisk_import_match` | Run matching algorithm, return results |
| `aisk_import_process` | Process next chunk of items |
| `aisk_import_report` | Generate final import report |

---

## 9. Safety & Edge Cases

### 9.1 Safety Measures

| Risk | Protection |
|------|-----------|
| Accidental data loss (Force mode) | Type "FORCE" confirmation |
| Browser close during import | `beforeunload` warning + server processes atomically per-item |
| Corrupt export file | JSON validation + schema check before import starts |
| Version mismatch | Warn if export `plugin_version` > installed version |
| Huge file (PHP limits) | Check `upload_max_filesize`, warn user if file exceeds |
| Timeout | Chunked AJAX (no single long request) |
| Partial failure | Each item independent — failures don't affect other items |

### 9.2 Edge Cases

| Case | Handling |
|------|----------|
| Same slug exists in multiple post types | Match requires slug + post_type match (not just slug) |
| Page was trashed on target | Trashed pages are NOT matched — only published/draft/pending |
| Term exists in multiple taxonomies | Match requires slug + taxonomy match |
| Export has duplicate slugs | First occurrence wins, second reported as warning |
| Empty export file (0 items in a section) | Skip section silently, no error |
| Import file from newer plugin version | Warning: "Export created with v1.2.0, you have v1.1.0. Some fields may not be recognized." — proceed anyway |
| Social image URL points to non-existent media | Import the URL as-is, flag in report: "Social image may not exist on target" |

---

## 10. What We Are NOT Building

| Feature | Reason |
|---------|--------|
| Page content import/export | WordPress has native tools for this (WXR export, All-in-One WP Migration) |
| Media file transfer | Out of scope — we handle metadata only |
| Multi-site network sync | Future feature, not v1 |
| Scheduled/automatic exports | Future feature |
| Export encryption/password protection | Future feature |
| CSV export format | JSON only for v1 |
| Rollback/undo import | Too complex for v1 — user should export before importing as backup |

---

## 11. User Workflow (Expected Usage)

### Scenario A: Moving SEO data from staging to production

1. On staging site: Export → select all sections → download JSON
2. On production: ensure all pages exist (use WP native import for content first)
3. On production: Import → upload JSON → choose "Force Import"
4. Review matching table → confirm → done

### Scenario B: Merging SEO data from old site

1. On old site: Export SEO metadata
2. On new site: Import → choose "Update Import"
3. Only empty fields get filled — existing work preserved

### Scenario C: Team collaboration (SEO specialist → developer)

1. SEO specialist works on staging, optimizes 50 pages
2. Exports SEO data
3. Sends JSON to developer
4. Developer imports on production with "Overwrite Import"
5. SEO specialist's work applied to production, developer's extra pages untouched

---

## 12. Build Order (Implementation Phases)

### Phase 1 — Exporter (simplest, start here)
- `class-exporter.php` — collect all data, build JSON, trigger download
- Update export UI with new checkboxes
- Test: export produces valid, complete JSON

### Phase 2 — Matcher
- `class-matcher.php` — slug+type matching, fuzzy matching
- AJAX endpoint to return match results
- Test: correct matching with various scenarios

### Phase 3 — Importer Core
- `class-importer.php` — all 3 modes, chunked processing
- AJAX loop for progress
- Test: each mode produces expected results

### Phase 4 — URL Rewriter
- `class-url-rewriter.php` — domain replacement
- Integration with importer
- Test: URLs correctly rewritten

### Phase 5 — UI Polish
- Progress bar, matching table, import report
- `beforeunload` warning
- Responsive design for the tables
- Test: full end-to-end flow in browser

### Phase 6 — Testing & Hardening
- Unit tests for matcher and importer
- Edge case testing (empty files, huge files, version mismatches)
- Security: nonce verification on all AJAX endpoints
- Capability checks: only administrators can import/export

---

## 13. Security Requirements

- All AJAX endpoints require valid nonce
- All AJAX endpoints require `manage_options` capability
- Export file does not contain API keys
- Import validates JSON structure before processing
- No `eval()` or dynamic code execution from import data
- SQL queries use `$wpdb->prepare()` for all user-provided data
- File upload restricted to `.json` extension and `application/json` MIME type

---

*End of Plan*
