# AI SEO Keeper — Code Architecture Map

> **Version:** 1.2.0 · **Last updated:** May 2026

This document maps the entire codebase for developers working on the plugin.
Use it to quickly locate any feature, page, handler, or asset file.

---

## File Tree

```
ai-seo-keeper/
├── ai-seo-keeper.php              ← Plugin bootstrap (constants, autoloader, activation hook)
├── uninstall.php                  ← Cleanup on plugin deletion
│
├── includes/                      ← All PHP classes (PSR-4 style, AI_SEO_Keeper namespace)
│   ├── class-plugin.php           ← Singleton controller — wires all modules together
│   ├── class-settings.php         ← Options registry, defaults, get/save, title branding helpers
│   ├── class-admin.php            ← Admin UI, AJAX handlers, editor metabox, GreenCoders design (~5,200 lines)
│   ├── class-frontend.php         ← Frontend SEO output (meta tags, schema, OG, crawl, title branding)
│   ├── class-ai-generator.php     ← AI provider integration (OpenAI / Google), live context overrides, preserve-if-good logic
│   ├── class-content-indexer.php  ← Site content indexing & summary stats
│   ├── class-content-writer.php   ← Pending content changes workflow
│   ├── class-content-helper.php   ← Content extraction helper for AI prompts
│   ├── class-audit-engine.php     ← Readiness scoring, coverage, duplicate detection
│   ├── class-history-store.php    ← Conversation & message logging (chat + generation)
│   ├── class-sitemap.php          ← XML sitemap generation (index, news, video)
│   ├── class-redirects.php        ← 301/302/307 redirect manager + 404 logging
│   ├── class-indexnow.php         ← IndexNow refresh signaling integration
│   ├── class-discovery.php        ← AI discovery documents (llms.txt)
│   ├── class-activator.php        ← Database table creation on activation
│   │
│   └── admin/                     ← Extracted view templates (HTML + inline JS where needed)
│       ├── view-dashboard.php
│       ├── view-settings.php
│       ├── view-audit.php
│       ├── view-bulk-editor.php
│       ├── view-images.php
│       ├── view-keywords.php
│       ├── view-export-import.php
│       └── view-setup-wizard.php
│
├── assets/
│   ├── css/
│   │   ├── admin-common.css       ← Shared styles (accordions, stat cards, tables, boxes)
│   │   ├── page-settings.css      ← Settings page styles
│   │   └── page-setup-wizard.css  ← Setup Wizard page styles
│   ├── img/
│   │   └── info-icon.svg          ← Purple-to-green gradient info icon
│   └── js/
│       ├── admin-common.js        ← Shared JS (accordion toggle, sortable table headers)
│       ├── page-bulk-editor.js    ← Bulk Editor AJAX save per row
│       ├── page-images.js         ← Image SEO alt-text save, "Used on" toggle
│       └── page-settings.js       ← Settings page interactions
│
└── docs/
    ├── CODE-MAP.md                ← This file
    └── plan-ai-content-editor.md  ← Feature planning notes
```

---

## Module Dependency Graph

```
ai-seo-keeper.php
  └── class-plugin.php (singleton)
        ├── class-settings.php
        ├── class-admin.php
        │     ├── class-ai-generator.php
        │     ├── class-content-indexer.php
        │     ├── class-content-writer.php
        │     ├── class-content-helper.php
        │     ├── class-audit-engine.php
        │     ├── class-history-store.php
        │     └── class-redirects.php
        ├── class-frontend.php
        │     └── class-settings.php
        ├── class-sitemap.php
        ├── class-indexnow.php
        └── class-discovery.php
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
| Dashboard | `ai-seo-keeper` | `render_dashboard()` | `view-dashboard.php` | — | — |
| Audit | `ai-seo-keeper-audit` | `render_audit_page()` | `view-audit.php` | — | — |
| Setup Wizard | `ai-seo-keeper-setup` | `render_setup_wizard_page()` | `view-setup-wizard.php` | `page-setup-wizard.css` | *(inline)* |
| Settings | `ai-seo-keeper-settings` | `render_settings_page()` | `view-settings.php` | — | — |
| Redirects | `ai-seo-keeper-redirects` | `render_redirects_page()` | *(delegated to Redirects class)* | — | — |
| Bulk Editor | `ai-seo-keeper-bulk-editor` | `render_bulk_editor_page()` | `view-bulk-editor.php` | — | `page-bulk-editor.js` |
| Image SEO | `ai-seo-keeper-images` | `render_image_seo_page()` | `view-images.php` | — | `page-images.js` |
| Keywords | `ai-seo-keeper-keywords` | `render_keyword_tracking_page()` | `view-keywords.php` | — | — |
| Export/Import | `ai-seo-keeper-export-import` | `render_export_import_page()` | `view-export-import.php` | — | — |

### Asset Loading

`enqueue_page_assets()` runs on `admin_enqueue_scripts` and auto-loads:
- **Always** on plugin pages: `admin-common.css` + `admin-common.js`
- **Per-page** (if file exists): `assets/css/page-{slug}.css` + `assets/js/page-{slug}.js`

To add CSS/JS for a new page: just create the file — no code changes needed.

---

## AJAX Handlers

All handlers are in `class-admin.php`, registered via `wp_ajax_{action}`.

### Editor AJAX

| Action | Method | Purpose |
|--------|--------|---------|
| `ai_seo_keeper_save_meta` | `handle_ajax_save_editor_meta()` | Save SEO draft from editor |
| `ai_seo_keeper_generate_meta` | `handle_ajax_generate_editor_meta()` | Generate AI SEO suggestions |
| `ai_seo_keeper_approve_suggestion` | `handle_ajax_approve_suggestion()` | Approve suggestion for frontend |
| `ai_seo_keeper_chat` | `handle_ajax_chat_for_post()` | AI chat assistant per page |
| `ai_seo_keeper_clear_chat` | `handle_ajax_clear_chat()` | Clear chat history |
| `ai_seo_keeper_content_edit` | `handle_ajax_content_edit()` | Plan AI content changes |
| `ai_seo_keeper_apply_changes` | `handle_ajax_apply_changes()` | Apply pending content edits |
| `ai_seo_keeper_apply_suggestion` | `handle_ajax_apply_suggestion()` | Apply SEO suggestion to post |
| `ai_seo_keeper_restore_backup` | `handle_ajax_restore_backup()` | Restore content backup |
| `ai_seo_keeper_delete_edit_plan` | `handle_ajax_delete_edit_plan()` | Delete pending edit plan |

### Bulk & Page-Level AJAX

| Action | Method | Purpose |
|--------|--------|---------|
| `ai_seo_keeper_bulk_save_seo` | `handle_ajax_bulk_save_seo()` | Save row in Bulk Editor |
| `ai_seo_keeper_save_image_alt` | `handle_ajax_save_image_alt()` | Save image alt text |
| `ai_seo_keeper_bulk_generate` | `handle_ajax_bulk_generate()` | Wizard: batch AI generation |
| `ai_seo_keeper_page_audit` | `handle_ajax_page_audit()` | Wizard: single page audit |
| `ai_seo_keeper_setup_index` | `handle_ajax_setup_index()` | Wizard: index site |
| `ai_seo_keeper_toggle_audit_skip` | `handle_ajax_toggle_audit_skip()` | Toggle audit skip flag |
| `ai_seo_keeper_save_skip_patterns` | `handle_ajax_save_skip_patterns()` | Save audit skip patterns |

### Redirects AJAX

| Action | Method | Purpose |
|--------|--------|---------|
| `ai_seo_keeper_add_redirect` | `ajax_add_redirect()` | Add redirect rule |
| `ai_seo_keeper_delete_redirect` | `ajax_delete_redirect()` | Delete redirect rule |
| `ai_seo_keeper_clear_404s` | `ajax_clear_404s()` | Clear 404 monitor log |

---

## Admin POST Actions

Non-AJAX form submissions handled via `admin_post_{action}`.

| Action | Method | Purpose |
|--------|--------|---------|
| `ai_seo_keeper_sync_index` | `handle_sync_index()` | Re-index all published content |
| `ai_seo_keeper_submit_indexnow` | `handle_submit_indexnow()` | Submit URLs to IndexNow |
| `ai_seo_keeper_import_yoast_metadata` | `handle_import_yoast_metadata()` | Migrate from Yoast SEO |
| `ai_seo_keeper_bulk_frontend_rollout` | `handle_bulk_frontend_rollout()` | Bulk enable/disable frontend |
| `ai_seo_keeper_generate_site_audit` | `handle_generate_site_audit()` | Generate AI site-wide audit |
| `ai_seo_keeper_export` | `handle_export()` | Export settings + metadata (JSON) |
| `ai_seo_keeper_import` | `handle_import()` | Import settings + metadata (JSON) |

---

## Database Tables

Created by `class-activator.php` on plugin activation.

| Table | Purpose |
|-------|---------|
| `{prefix}_ai_seo_keeper_content_index` | Indexed content records (object_id, type, title, status) |
| `{prefix}_ai_seo_keeper_conversations` | Chat/generation session tracking |
| `{prefix}_ai_seo_keeper_messages` | Individual chat/generation messages |
| `{prefix}_ai_seo_keeper_redirects` | Redirect rules (source, target, type, hits) |
| `{prefix}_ai_seo_keeper_redirects_404_log` | 404 error tracking (URL, referrer, timestamp) |

---

## Post Meta Keys

| Meta Key | Purpose |
|----------|---------|
| `_ai_seo_keeper_meta_title` | SEO title (draft) |
| `_ai_seo_keeper_meta_description` | Meta description (draft) |
| `_ai_seo_keeper_focus_keyphrase` | Focus keyphrase |
| `_ai_seo_keeper_social_title` | Social/OG title |
| `_ai_seo_keeper_social_description` | Social/OG description |
| `_ai_seo_keeper_social_image` | Social/OG image URL |
| `_ai_seo_keeper_canonical_url` | Canonical URL override |
| `_ai_seo_keeper_robots_directives` | Robots directives (noindex, nofollow, etc.) |
| `_ai_seo_keeper_schema_type` | Schema.org type |
| `_ai_seo_keeper_frontend_enabled` | Per-post frontend output toggle |
| `_ai_seo_keeper_approved_message_id` | Approved AI suggestion message ID |
| `_ai_seo_keeper_page_audit` | Page-level audit data (score, issues, suggestions) |
| `_ai_seo_keeper_pending_content_changes` | Pending AI content edits |
| `_ai_seo_keeper_cornerstone` | Cornerstone content flag |
| `_ai_seo_keeper_audit_skip` | Audit skip flag |
| `_ai_seo_keeper_title_branding_off` | Per-page title branding opt-out |

---

## Editor Metabox

Registered on all public post types. Contains these tabs:

| Tab | Features |
|-----|----------|
| **SEO** | Focus keyphrase, SEO title (30-60 chars), meta description (70-155 chars), AI generate, approve |
| **Social** | Social title, description, image upload, preview |
| **Schema** | Schema type selector |
| **Advanced** | Canonical URL, robots directives (noindex, nofollow, noodp, etc.), cornerstone flag, hreflang |
| **Checks** | Focus keyphrase analysis, readability scoring, link anchor quality, transition words |
| **Links** | Internal/external link analysis, outbound/inbound link counts |

Plus collapsible sections: **AI Chat**, **History**, **Readiness Status**

---

## Constants

Defined in `ai-seo-keeper.php`:

| Constant | Value |
|----------|-------|
| `AI_SEO_KEEPER_VERSION` | `'1.2.0'` |
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
