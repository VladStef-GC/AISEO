# SEO Captain — Code Architecture Map

> **Version:** 1.3.1 · **Last updated:** May 2026

This document maps the entire codebase for developers working on the plugin.
Use it to quickly locate any feature, page, handler, or asset file.

---

## File Tree

```
ai-seo-captain/
├── ai-seo-captain.php              ← Plugin bootstrap (constants, autoloader, activation hook, DB auto-upgrade)
├── uninstall.php                  ← Cleanup on plugin deletion (5 tables, all meta, options, user meta)
│
├── includes/                      ← All PHP classes (PSR-4 style, AI_SEO_Captain namespace)
│   ├── autoload.php               ← PSR-4 autoloader (AI_SEO_Captain\ClassName → class-classname.php)
│   ├── class-plugin.php           ← Singleton controller — wires all modules together
│   ├── class-settings.php         ← Options registry, defaults, get/save, title branding helpers
│   ├── class-admin.php            ← Slim coordinator: menus, assets, metabox, delegation to sub-modules
│   ├── class-frontend.php         ← Frontend SEO output (meta tags, schema, OG, crawl, title branding)
│   ├── class-ai-generator.php     ← AI provider integration (OpenAI / Google), live context overrides, preserve-if-good logic
│   ├── class-content-indexer.php  ← Site content indexing, summary stats, get_all_indexed_pages()
│   ├── class-content-writer.php   ← Pending content changes workflow (changeset pattern)
│   ├── class-content-helper.php   ← Content extraction helper for AI prompts
│   ├── class-audit-engine.php     ← Readiness scoring, coverage, duplicate detection
│   ├── class-history-store.php    ← Conversation & message logging (chat + generation)
│   ├── class-sitemap.php          ← XML sitemap generation (index, news, video)
│   ├── class-redirects.php        ← 301/302/307 redirect manager + 404 logging
│   ├── class-indexnow.php         ← IndexNow refresh signaling integration
│   ├── class-discovery.php        ← AI discovery documents (llms.txt)
│   ├── class-activator.php        ← Database table creation (5 tables) on activation
│   ├── class-meta-keys.php        ← Centralized post/term meta key constants
│   ├── class-run-manager.php      ← Runs CRUD, step tracking, page selector data
│   ├── class-site-chat.php        ← AI Strategist chat handler (send, clear, focus pages)
│   ├── class-cron-manager.php     ← Scheduled tasks: registration, execution, logging, pause/resume
│   ├── class-woocommerce-integration.php ← WooCommerce detection and product-aware features
│   │
│   └── admin/                     ← Extracted view templates and admin sub-modules
│       ├── class-admin-ajax.php          ← All AJAX handlers (generate, chat, bulk, setup, runs, clear)
│       ├── class-admin-rollout.php       ← Sync index, submit IndexNow, site audit, bulk frontend
│       ├── class-admin-import-export.php ← Export/import (JSON), Yoast migration
│       ├── class-admin-taxonomy.php      ← Taxonomy term SEO fields (render + save)
│       ├── class-seo-analysis.php        ← Deterministic SEO checks (keyphrase, readability, links)
│       ├── view-dashboard.php
│       ├── view-settings.php
│       ├── view-audit.php
│       ├── view-bulk-editor.php
│       ├── view-images.php
│       ├── view-videos.php
│       ├── view-documents.php
│       ├── view-keywords.php
│       ├── view-export-import.php
│       ├── view-setup-wizard.php
│       ├── view-site-chat.php
│       └── view-cron-manager.php
│
├── assets/
│   ├── css/
│   │   ├── admin-common.css       ← Shared styles (accordions, stat cards, tables, boxes)
│   │   ├── page-settings.css      ← Settings page styles
│   │   ├── page-setup-wizard.css  ← Setup Wizard page styles
│   │   ├── page-site-chat.css     ← AI Strategist page styles
│   │   ├── page-cron-manager.css  ← Scheduled Tasks page styles
│   │   └── gutenberg-sidebar.css  ← Gutenberg sidebar panel styles
│   ├── img/
│   │   └── info-icon.svg          ← Purple-to-green gradient info icon
│   └── js/
│       ├── admin-common.js        ← Shared JS (accordion toggle, sortable table headers)
│       ├── page-bulk-editor.js    ← Bulk Editor AJAX save per row
│       ├── page-images.js         ← Image SEO alt-text save, "Used on" toggle
│       ├── page-videos.js         ← Video SEO title/description save
│       ├── page-documents.js      ← Document SEO title/description save, "Used on" toggle
│       ├── page-settings.js       ← Settings page interactions
│       ├── page-site-chat.js      ← AI Strategist chat interface, focus pages, runs
│       ├── page-cron-manager.js   ← Scheduled Tasks page AJAX controls
│       └── gutenberg-sidebar.js   ← Gutenberg sidebar panel registration
│
├── tests/
│   ├── bootstrap.php              ← PHPUnit bootstrap with WP stubs
│   ├── hallucination-test.php     ← AI output validation tool
│   ├── prompt-inspector.php       ← Prompt debugging tool
│   ├── stubs/                     ← WordPress function mocks
│   └── unit/                      ← PHPUnit test cases (81 tests, 141 assertions)
│
└── docs/
    ├── CODE-MAP.md                ← This file
    ├── UPDATE-PLAN-v1.3.md        ← v1.3 refactoring plan (completed)
    ├── PRIORITY-PLAN-SCALE-AWARE.md ← Scale-aware architecture plan (completed)
    └── plan-ai-content-editor.md  ← AI Content Editor feature plan (completed)
```

---

## Module Dependency Graph

```
ai-seo-captain.php
  └── class-plugin.php (singleton)
        ├── class-settings.php
        ├── class-admin.php (slim coordinator)
        │     ├── admin/class-admin-ajax.php
        │     │     ├── class-ai-generator.php
        │     │     ├── class-content-indexer.php
        │     │     ├── class-content-writer.php
        │     │     ├── class-content-helper.php
        │     │     ├── class-history-store.php
        │     │     └── class-run-manager.php
        │     ├── admin/class-admin-rollout.php
        │     │     ├── class-content-indexer.php
        │     │     └── class-indexnow.php
        │     ├── admin/class-admin-import-export.php
        │     ├── admin/class-admin-taxonomy.php
        │     ├── admin/class-seo-analysis.php
        │     └── class-audit-engine.php
        ├── class-site-chat.php
        │     └── class-history-store.php
        ├── class-run-manager.php
        ├── class-frontend.php
        │     └── class-settings.php
        ├── class-sitemap.php
        ├── class-redirects.php
        ├── class-indexnow.php
        ├── class-discovery.php
        ├── class-cron-manager.php
        └── class-woocommerce-integration.php
```

---

## Admin Pages & View Architecture

Each admin page follows the **thin-stub pattern**:

1. The render method in `class-admin.php` prepares data (queries, options, variables)
2. It passes all data as **local PHP variables** (no `self::` or `$this->` in views)
3. It calls `require __DIR__ . '/admin/view-{page}.php'`
4. The view file receives variables via PHP scope and outputs HTML

### Page Map

| Menu Item | Slug | Render Method | View File | Page CSS | Page JS |
|-----------|------|---------------|-----------|----------|---------|
| Dashboard | `ai-seo-captain` | `render_dashboard()` | `view-dashboard.php` | — | — |
| Audit | `ai-seo-captain-audit` | `render_audit_page()` | `view-audit.php` | — | — |
| Setup Wizard | `ai-seo-captain-setup` | `render_setup_wizard_page()` | `view-setup-wizard.php` | `page-setup-wizard.css` | *(inline)* |
| Settings | `ai-seo-captain-settings` | `render_settings_page()` | `view-settings.php` | `page-settings.css` | `page-settings.js` |
| Redirects | `ai-seo-captain-redirects` | `render_redirects_page()` | *(delegated to Redirects class)* | — | — |
| Bulk Editor | `ai-seo-captain-bulk-editor` | `render_bulk_editor_page()` | `view-bulk-editor.php` | — | `page-bulk-editor.js` |
| Image SEO | `ai-seo-captain-images` | `render_image_seo_page()` | `view-images.php` | — | `page-images.js` |
| Keywords | `ai-seo-captain-keywords` | `render_keyword_tracking_page()` | `view-keywords.php` | — | — |
| AI Strategist | `ai-seo-captain-site-chat` | `render_site_chat_page()` | `view-site-chat.php` | `page-site-chat.css` | `page-site-chat.js` |
| Scheduled Tasks | `ai-seo-captain-cron-manager` | `render_cron_manager_page()` | `view-cron-manager.php` | `page-cron-manager.css` | `page-cron-manager.js` |
| Export/Import | `ai-seo-captain-export-import` | `render_export_import_page()` | `view-export-import.php` | — | — |

### Asset Loading

`enqueue_page_assets()` runs on `admin_enqueue_scripts` and auto-loads:
- **Always** on plugin pages: `admin-common.css` + `admin-common.js`
- **Per-page** (if file exists): `assets/css/page-{slug}.css` + `assets/js/page-{slug}.js`

To add CSS/JS for a new page: just create the file — no code changes needed.

---

## AJAX Handlers

All AJAX handlers are registered in `class-admin.php` via `wp_ajax_{action}` and delegated to sub-module classes.

### Editor AJAX (class-admin-ajax.php)

| Action | Method | Purpose |
|--------|--------|---------|
| `ai_seo_captain_save_meta` | `handle_save_editor_meta()` | Save SEO draft from editor |
| `ai_seo_captain_generate_meta` | `handle_generate_editor_meta()` | Generate AI SEO suggestions |
| `ai_seo_captain_approve_suggestion` | `handle_approve_suggestion()` | Approve suggestion for frontend |
| `ai_seo_captain_chat` | `handle_chat_for_post()` | AI chat assistant per page |
| `ai_seo_captain_clear_chat` | `handle_clear_chat()` | Clear chat history |
| `ai_seo_captain_content_edit` | `handle_content_edit()` | Plan AI content changes |
| `ai_seo_captain_apply_changes` | `handle_apply_changes()` | Apply pending content edits |
| `ai_seo_captain_apply_suggestion` | `handle_apply_suggestion()` | Apply SEO suggestion to post |
| `ai_seo_captain_restore_backup` | `handle_restore_backup()` | Restore content backup |
| `ai_seo_captain_delete_edit_plan` | `handle_delete_edit_plan()` | Delete pending edit plan |

### Bulk & Page-Level AJAX (class-admin-ajax.php)

| Action | Method | Purpose |
|--------|--------|---------|
| `ai_seo_captain_bulk_save_seo` | `handle_bulk_save_seo()` | Save row in Bulk Editor |
| `ai_seo_captain_save_image_alt` | `handle_save_image_alt()` | Save image alt text |
| `ai_seo_captain_bulk_generate` | `handle_bulk_generate()` | Wizard: batch AI generation (skip-rules enforced) |
| `ai_seo_captain_page_audit` | `handle_page_audit()` | Wizard: single page audit |
| `ai_seo_captain_setup_index` | `handle_setup_index()` | Wizard: index site |
| `ai_seo_captain_toggle_audit_skip` | `handle_toggle_audit_skip()` | Toggle audit skip flag |
| `ai_seo_captain_save_skip_patterns` | `handle_save_skip_patterns()` | Save audit skip patterns |
| `ai_seo_captain_clear_seo_data` | `handle_clear_seo_data()` | Scope-based data clearing |

### Cron Manager AJAX (class-admin-ajax.php)

| Action | Method | Purpose |
|--------|--------|---------|
| `ai_seo_captain_cron_pause` | `handle_cron_pause()` | Pause a scheduled task |
| `ai_seo_captain_cron_resume` | `handle_cron_resume()` | Resume a paused task |
| `ai_seo_captain_cron_run_now` | `handle_cron_run_now()` | Execute a task immediately |

### Runs AJAX (class-admin-ajax.php)

| Action | Method | Purpose |
|--------|--------|---------|
| `ai_seo_captain_create_run` | `handle_create_run()` | Create a saved page list |
| `ai_seo_captain_delete_run` | `handle_delete_run()` | Delete a saved page list |
| `ai_seo_captain_get_pages_for_selector` | `handle_get_pages_for_selector()` | Fetch indexed pages for modal |
| `ai_seo_captain_mark_run_step` | `handle_mark_run_step()` | Mark run step complete |

### Site Chat AJAX (class-site-chat.php)

| Action | Method | Purpose |
|--------|--------|---------|
| `ai_seo_captain_site_chat` | `handle_chat()` | Send message to AI Strategist |
| `ai_seo_captain_site_chat_clear` | `handle_clear_chat()` | Clear Strategist chat history |

### Redirects AJAX (class-redirects.php)

| Action | Method | Purpose |
|--------|--------|---------|
| `ai_seo_captain_add_redirect` | `ajax_add_redirect()` | Add redirect rule |
| `ai_seo_captain_delete_redirect` | `ajax_delete_redirect()` | Delete redirect rule |
| `ai_seo_captain_clear_404s` | `ajax_clear_404s()` | Clear 404 monitor log |

---

## Admin POST Actions

Non-AJAX form submissions handled via `admin_post_{action}`, delegated to sub-modules.

| Action | Handler Class | Method | Purpose |
|--------|---------------|--------|---------|
| `ai_seo_captain_sync_index` | `Admin_Rollout` | `handle_sync_index()` | Re-index all published content |
| `ai_seo_captain_submit_indexnow` | `Admin_Rollout` | `handle_submit_indexnow()` | Submit URLs to IndexNow |
| `ai_seo_captain_generate_site_audit` | `Admin_Rollout` | `handle_generate_site_audit()` | Generate AI site-wide audit |
| `ai_seo_captain_bulk_frontend_rollout` | `Admin_Rollout` | `handle_bulk_frontend_rollout()` | Bulk enable/disable frontend |
| `ai_seo_captain_import_yoast_metadata` | `Admin_Import_Export` | `handle_import_yoast()` | Migrate from Yoast SEO |
| `ai_seo_captain_export` | `Admin_Import_Export` | `handle_export()` | Export settings + metadata (JSON) |
| `ai_seo_captain_import` | `Admin_Import_Export` | `handle_import()` | Import settings + metadata (JSON) |

---

## Database Tables

Created by `class-activator.php` on plugin activation. Auto-upgraded via `plugins_loaded` hook.

| Table | Purpose |
|-------|---------|
| `{prefix}_ai_seo_captain_content_index` | Indexed content records (object_id, type, title, status) |
| `{prefix}_ai_seo_captain_conversations` | Chat/generation session tracking |
| `{prefix}_ai_seo_captain_messages` | Individual chat/generation messages |
| `{prefix}_ai_seo_captain_redirects` | Redirect rules (source, target, type, hits) |
| `{prefix}_ai_seo_captain_runs` | Saved page lists (name, page_ids, page_count, model_used, status, completed_steps) |

---

## Post Meta Keys

| Meta Key | Purpose |
|----------|---------|
| `_ai_seo_captain_meta_title` | SEO title (draft) |
| `_ai_seo_captain_meta_description` | Meta description (draft) |
| `_ai_seo_captain_focus_keyphrase` | Focus keyphrase |
| `_ai_seo_captain_social_title` | Social/OG title |
| `_ai_seo_captain_social_description` | Social/OG description |
| `_ai_seo_captain_social_image` | Social/OG image URL |
| `_ai_seo_captain_canonical_url` | Canonical URL override |
| `_ai_seo_captain_robots_directives` | Robots directives (noindex, nofollow, etc.) |
| `_ai_seo_captain_schema_type` | Schema.org type |
| `_ai_seo_captain_frontend_enabled` | Per-post frontend output toggle |
| `_ai_seo_captain_approved_message_id` | Approved AI suggestion message ID |
| `_ai_seo_captain_page_audit` | Page-level audit data (score, issues, suggestions) |
| `_ai_seo_captain_pending_content_changes` | Pending AI content edits |
| `_ai_seo_captain_content_backup` | Content backup before AI edits |
| `_ai_seo_captain_cornerstone` | Cornerstone content flag |
| `_ai_seo_captain_audit_skip` | Audit skip flag |
| `_ai_seo_captain_title_branding_off` | Per-page title branding opt-out |
| `_ai_seo_captain_keywords` | Additional keywords (comma-separated) |
| `_ai_seo_captain_exclude_sitemap` | Exclude from XML sitemap flag |
| `_ai_seo_captain_hreflang` | Hreflang entries |

## Term Meta Keys

| Meta Key | Purpose |
|----------|---------|
| `_ai_seo_captain_term_title` | Taxonomy term SEO title |
| `_ai_seo_captain_term_description` | Taxonomy term meta description |
| `_ai_seo_captain_term_canonical` | Taxonomy term canonical URL |
| `_ai_seo_captain_term_noindex` | Taxonomy term noindex override |

---

## Editor Metabox

Registered on all public post types. Contains these tabs:

| Tab | Features |
|-----|----------|
| **SEO** | Focus keyphrase, SEO title (30-60 chars), meta description (70-155 chars), AI generate, approve |
| **Social** | Social title, description, image upload, preview |
| **Schema** | Schema type selector |
| **Advanced** | Canonical URL, robots directives, exclude from sitemap, frontend gate, cornerstone flag, hreflang |
| **Checks** | Focus keyphrase analysis, readability scoring, link anchor quality, transition words |
| **Links** | Internal/external link analysis, outbound/inbound link counts |

Plus collapsible sections: **AI Chat**, **History**, **Readiness Status**

---

## Constants

Defined in `ai-seo-captain.php`:

| Constant | Value |
|----------|-------|
| `AI_SEO_KEEPER_VERSION` | `'1.3.1'` |
| `AI_SEO_KEEPER_FILE` | Main plugin file path |
| `AI_SEO_KEEPER_PATH` | Plugin directory path |
| `AI_SEO_KEEPER_URL` | Plugin directory URL |

---

## Adding a New Admin Page

1. Register the submenu in `class-admin.php` constructor (`add_submenu_page`)
2. Add the hook_suffix → slug mapping in `enqueue_page_assets()`
3. Create the render method as a thin stub (prepare data → `require` view)
4. Create `includes/admin/view-{slug}.php` (HTML template)
5. *(Optional)* Create `assets/css/page-{slug}.css` and/or `assets/js/page-{slug}.js` — auto-loaded

No changes to the asset enqueue system are needed — files are detected automatically.
