# PRIORITY PLAN — Scale-Aware Plugin Architecture

**Created**: 2026-05-13  
**Status**: ✅ COMPLETED — All features implemented in v1.3.1  
**Goal**: Make AI SEO Keeper fully scale-aware. Gate features behind indexing + audit. Allow large sites to work in saved subsets ("Runs") instead of all-or-nothing.

---

## The Problem

On a large site (1K–50K+ pages), the plugin currently has no awareness of scale:

1. **No enforcement** — A user can click "Full SEO Audit" on a 10,000-page WooCommerce store and unknowingly trigger 10,000 API calls (~$100+ and 15+ hours).
2. **No prerequisite checks** — Users can chat with AI about pages that were never indexed or audited, producing empty/misleading responses.
3. **No partial-site workflow** — Large sites can't realistically audit all pages at once. There's no way to save and reuse a partial analysis.

---

## Core Principle

> **"THE ONLY LIMIT SHOULD BE BY ELIMINATING JUNK! NOT BY LIMITING AI CONTEXT!"**

The plugin should:
- Always know the real page count (indexing is mandatory)
- Let AI see ALL available data for the selected scope
- Never silently truncate or omit data
- Warn users about cost/time before expensive operations
- Let large-site users work in saved subsets ("Runs")

---

## Architecture Overview

### Feature Gate Hierarchy

```
INSTALL PLUGIN
    │
    ▼
STEP 1: INDEX SITE (mandatory — fast, free, no AI calls)
    │   Result: page count known, content_index table populated
    │   Without this: ALL plugin pages show "0" data + floating banner
    │
    ▼
STEP 2: GENERATE SEO METADATA (optional — 1 API call per page)
    │   If pages > model threshold → cost/time warning popup
    │   Offers Focus Pages alternative for large sites
    │
    ▼
STEP 3: RUN FULL SEO AUDIT (optional — 1 API call per page, heavier)
    │   If pages > model threshold → cost/time warning popup
    │   Offers Focus Pages alternative for large sites
    │
    ▼
AI FEATURES UNLOCKED (require: index exists + at least 1 audited page)
    ├── AI Strategist (Site Chat) — needs index + audit data
    ├── AI Strategic Audit — needs index + audit data
    └── Per-page AI Chat — needs index (audit optional per page)
```

### What Gets Disabled Without Indexing

When no index exists (content_index table has 0 published rows):

| Page | Behavior |
|------|----------|
| **Dashboard** | Shows all "0" values. Floating banner: "Index your site to enable all features" |
| **Setup Wizard** | Fully functional (this IS where indexing happens) |
| **Settings** | Fully functional (API key needed before indexing is useful) |
| **Audit** | Cards show "0". Buttons disabled. Floating banner |
| **AI Strategist** | Chat disabled. Floating banner |
| **Bulk Editor** | Empty table. Floating banner |
| **Image SEO** | Empty. Floating banner |
| **Keyword Tracking** | Empty. Floating banner |
| **Redirects & 404** | Fully functional (independent of indexing) |
| **Export / Import** | Fully functional |

### Floating Banner Design

```
┌─────────────────────────────────────────────────────────────────┐
│  ⚠  Site not indexed. Please [run site indexing] first,        │
│     then [run a full audit] to enable all AI features.         │
└─────────────────────────────────────────────────────────────────┘
```

- Yellow/orange background, fixed at top of content area (below WP admin bar)
- `[run site indexing]` links to Setup Wizard page
- `[run a full audit]` links to Setup Wizard Step 3
- Two states:
  - **No index**: "Please [run site indexing] to enable all features."
  - **Index exists, no audit**: "Site indexed (X pages). [Run a full audit] or [audit specific pages] to enable AI features."

---

## Implementation Steps

### STEP 1: Indexing Gate (Foundation)

**Files to modify:**
- `includes/class-admin.php` — Add gate check method + floating banner render
- `includes/admin/view-*.php` — Each view template checks gate status

**New method in `class-admin.php`:**
```php
public function get_plugin_readiness(): array
{
    $page_count = $this->content_indexer->get_published_page_count();
    $has_index = $page_count > 0;
    $has_any_audit = $this->has_any_audit_data();
    
    return [
        'has_index'     => $has_index,
        'page_count'    => $page_count,
        'has_any_audit' => $has_any_audit,
        'is_fully_ready'=> $has_index && $has_any_audit,
    ];
}
```

**New method to check audit existence:**
```php
private function has_any_audit_data(): bool
{
    global $wpdb;
    $result = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} 
         WHERE meta_key = '_ai_seo_keeper_page_audit' LIMIT 1"
    );
    return (int) $result > 0;
}
```

**Floating banner** — rendered via `admin_notices` hook or injected at top of each plugin page. Appears on ALL plugin pages except Settings and Setup Wizard.

**Disabling features:**
- Each view template receives `$readiness` array
- If `!$has_index` → wrap feature sections in disabled state (grayed out, pointer-events: none)
- Cards show "0" with muted styling
- Buttons get `disabled` attribute
- Chat textareas get `disabled` + placeholder text explaining why

### STEP 2: Cost/Time Warning on Bulk Operations

**Files to modify:**
- `includes/admin/view-setup-wizard.php` — Add confirmation popup logic
- `assets/js/page-setup-wizard.js` (or inline JS in view) — Popup JS

**When "Generate SEO Metadata" or "Full SEO Audit" button is clicked:**

1. JS intercepts the click
2. Reads page count from `window.aiSeoSetup.pageCount` (localized by PHP)
3. Reads model info from `window.aiSeoSetup.modelId` and `window.aiSeoSetup.maxPages`
4. If `pageCount > safeThreshold` (e.g., 100 pages):
   ```
   ┌──────────────────────────────────────────────────────────────┐
   │  ⚠  Large Site Detected                                     │
   │                                                              │
   │  Your site has 2,500 pages.                                  │
   │                                                              │
   │  Running [Full SEO Audit] will make ~2,500 API calls.        │
   │  Estimated time: ~2-5 hours                                  │
   │  Estimated cost: ~$25-50 (depends on provider/model)         │
   │                                                              │
   │  [ Continue with all pages ]  [ Use Focus Pages instead ]    │
   │                                                              │
   │  Focus Pages lets you select specific pages to audit.        │
   │  You can always audit more pages later.                      │
   └──────────────────────────────────────────────────────────────┘
   ```
5. "Continue with all pages" → proceeds normally
6. "Use Focus Pages instead" → scrolls to / opens Focus Pages section

**Safe threshold**: 100 pages (configurable). Below 100, no popup — just run.

### STEP 3: Runs System (Saved Page Lists)

**This is the biggest change. New DB table + new class + UI changes.**

#### 3A: New Database Table

**File**: `includes/class-activator.php` — add 5th table

```sql
CREATE TABLE {$prefix}ai_seo_keeper_runs (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL DEFAULT 0,
    name varchar(255) NOT NULL,
    description text NOT NULL DEFAULT '',
    page_ids longtext NOT NULL,
    page_count int unsigned NOT NULL DEFAULT 0,
    model_used varchar(100) NOT NULL DEFAULT '',
    status varchar(20) NOT NULL DEFAULT 'active',
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY status (status)
) {$charset_collate};
```

**Key decisions:**
- `page_ids` stores JSON array of post IDs (e.g., `[42, 67, 103, 205]`)
- Actual SEO + audit data stays in `wp_postmeta` (no duplication)
- A Run is a **saved filter** — it says "show me data for THESE pages only"
- `status`: `active` | `archived`
- A "Full Site" run has ALL page IDs (created automatically after full indexing + audit)

#### 3B: New Class

**File**: `includes/class-run-manager.php`

```php
namespace AI_SEO_Keeper;

class Run_Manager {
    public function create_run(string $name, array $page_ids, string $model = ''): int;
    public function get_run(int $run_id): ?array;
    public function get_all_runs(string $status = 'active'): array;
    public function update_run_pages(int $run_id, array $page_ids): bool;
    public function delete_run(int $run_id): bool;
    public function get_active_run_ids(): array;      // from user meta or option
    public function set_active_run_ids(array $ids): void;
    public function get_combined_page_ids(): array;    // union of all active runs
    public function is_multi_run_selected(): bool;     // true if >1 run active
}
```

#### 3C: UI — Run Selector

**Location**: Appears on AI Strategist page + Audit page (top, above content)

```
┌──────────────────────────────────────────────────────────────┐
│  Active Analysis Scope:                                       │
│  ☑ Full Site Audit (60 pages) — May 13, 2026                 │
│  ☐ Homepage + Services (5 pages) — May 12, 2026              │
│  ☐ Blog Posts (25 pages) — May 10, 2026                      │
│                                                               │
│  [ + New Run ]                                                │
└──────────────────────────────────────────────────────────────┘
```

- Checkboxes allow multi-select
- If >1 checked → warning: "Multiple runs selected. AI Strategist disabled — select one run for strategic analysis."
- "New Run" opens Focus Pages input (URL textarea) → creates a new run

#### 3D: Data Flow with Runs

When a Run is active, ALL data-fetching methods receive a `$page_ids` filter:

1. **Dashboard cards** → filtered to active run's page IDs
2. **Audit page** → shows only pages from active run
3. **AI Strategist** → `build_user_prompt()` already supports `$focus_ids` — wire it to active run
4. **Bulk Editor** → filtered to active run
5. **Strategic Audit** → `build_site_audit_report()` filtered to active run's pages

**Special case**: If NO run is selected and user has a full-site index + audit, show everything (backward compatible).

### STEP 4: Focus Pages → Run Conversion

When user pastes URLs in Focus Pages and clicks "Audit these pages":

1. Validate URLs → resolve to post IDs via `url_to_postid()`
2. Create a new Run with those page IDs + auto-generated name (e.g., "Focus: 5 pages — May 13")
3. Run SEO Metadata generation for those pages (if not already done)
4. Run Page Audit for those pages (if not already done)
5. Activate this Run as the current scope
6. All UI now shows data for this Run only

### STEP 5: Multi-Run Safeguards

When multiple Runs are selected:
- **AI Strategist**: Disabled with warning "Select a single run for strategic analysis"
- **Dashboard cards**: Combined (union of all page IDs, deduplicated)
- **Bulk Editor**: Combined view
- **Per-page chat**: Unaffected (always works on the single page being edited)

---

## Implementation Priority & Order

| Priority | Step | Effort | Dependencies |
|----------|------|--------|-------------|
| **P0** | STEP 1: Indexing gate + floating banner | 1 session | None |
| **P0** | STEP 2: Cost/time warning popups | 1 session | Step 1 |
| **P1** | STEP 3A: Runs DB table | 0.5 session | None |
| **P1** | STEP 3B: Run_Manager class | 1 session | Step 3A |
| **P1** | STEP 3C: Run selector UI | 1 session | Step 3B |
| **P1** | STEP 3D: Wire data fetching through runs | 1-2 sessions | Step 3C |
| **P2** | STEP 4: Focus Pages → Run conversion | 1 session | Step 3B |
| **P2** | STEP 5: Multi-run safeguards | 0.5 session | Step 3D |

**Total estimate**: 6-8 focused sessions

---

## Files Affected

### New Files
- `includes/class-run-manager.php` — Run CRUD + selection logic

### Modified Files
- `includes/class-activator.php` — New `runs` table in `dbDelta()`
- `includes/class-admin.php` — `get_plugin_readiness()`, floating banner, pass readiness to views
- `includes/class-site-chat.php` — Wire active run to focus_ids
- `includes/class-content-indexer.php` — Accept optional page_ids filter in report methods
- `includes/admin/class-admin-rollout.php` — Strategic audit uses active run
- `includes/admin/view-site-chat.php` — Run selector UI
- `includes/admin/view-audit.php` — Run selector UI + warning popups
- `includes/admin/view-setup-wizard.php` — Cost/time warning popup
- `includes/admin/view-dashboard.php` — Filtered cards + floating banner
- `includes/admin/view-bulk-editor.php` — Filtered to active run
- `assets/js/page-setup-wizard.js` — Popup logic
- `assets/js/page-site-chat.js` — Run selection handling
- `assets/css/admin-common.css` or similar — Floating banner + disabled state styles

### NOT Modified (Independent Features)
- `includes/class-redirects.php` — Independent of indexing
- `includes/class-woocommerce-integration.php` — Works with existing index
- `includes/class-ai-generator.php` — Called by others, no direct changes needed
- Frontend output files — Serve whatever is in postmeta, unaffected

---

## Database Schema After Implementation

### Existing Tables (unchanged)
1. `wp_ai_seo_keeper_content_index` — All indexed pages
2. `wp_ai_seo_keeper_conversations` — Chat conversations
3. `wp_ai_seo_keeper_messages` — Chat messages
4. `wp_ai_seo_keeper_redirects` — Redirects & 404s

### New Table
5. `wp_ai_seo_keeper_runs` — Saved analysis scopes (page lists)

### Existing PostMeta Keys (unchanged — Runs reference these, not duplicate them)
- `_ai_seo_keeper_meta_title`
- `_ai_seo_keeper_meta_description`
- `_ai_seo_keeper_focus_keyphrase`
- `_ai_seo_keeper_page_audit`
- `_ai_seo_keeper_audit_skip`
- `_ai_seo_keeper_frontend_active`
- `_ai_seo_keeper_approved_suggestion_id`
- + social, canonical, robots, schema keys

---

## Backward Compatibility

- **Existing installs with full audit**: Everything works as before. A virtual "Full Site" run is implied when no explicit run exists.
- **New installs**: Setup Wizard guides through indexing → metadata → audit. No AI features until index exists.
- **Skipped setup**: User can skip wizard, but all AI-dependent pages show "0" + floating banner.

---

## Open Questions (Decide During Implementation)

1. **Auto-create "Full Site" run after complete audit?** — Yes, automatically create a run named "Full Site Audit" containing all page IDs after Step 3 of wizard completes.
2. **Run naming** — Auto-generated (e.g., "5 pages — May 13") or user-provided? Start with auto, add rename later.
3. **Run archival vs deletion** — Archive by default (soft delete), hard delete as option.
4. **Max runs** — No hard limit initially. Revisit if DB bloat becomes an issue.
5. **Run refresh** — When user re-audits pages in a run, the run itself doesn't change (it's just IDs). The fresh audit data is in postmeta automatically.
