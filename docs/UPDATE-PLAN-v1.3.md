# AI SEO Keeper — v1.3 Update Plan

> **Created:** May 10, 2026
> **Status:** ✅ COMPLETED — All 7 concerns resolved in v1.3.0/v1.3.1
> **Goal:** Address all 7 structural concerns identified in the code audit to make the plugin maintainable, team-ready, and marketplace-competitive.

---

## Overview

| # | Concern | Severity | Status |
|---|---------|----------|--------|
| 1 | Admin monolith (5,324 lines) | **Critical** | ✅ Split into 5 sub-modules |
| 2 | No autoloader | Medium | ✅ PSR-4 autoloader added |
| 3 | No automated tests | **High** | ✅ 81 tests, 141 assertions |
| 4 | Incomplete uninstall cleanup | Medium | ✅ All meta keys covered |
| 5 | No i18n/translation support | **High** | ✅ All strings use text domain |
| 6 | No Gutenberg sidebar panel | **High** | ✅ gutenberg-sidebar.js/css |
| 7 | No WooCommerce integration | Medium | ✅ class-woocommerce-integration.php |

---

## Resolution Details

### Concern 1 — Admin Monolith → Modular Architecture

Split into 5 focused classes under `includes/admin/`:
- `class-admin-ajax.php` — All AJAX handlers
- `class-admin-rollout.php` — Sync index, submit IndexNow, site audit, bulk frontend
- `class-admin-import-export.php` — Export/import (JSON), Yoast migration
- `class-admin-taxonomy.php` — Taxonomy term SEO fields
- `class-seo-analysis.php` — Deterministic SEO checks

`class-admin.php` is now a slim coordinator handling only menu registration, asset enqueuing, editor metabox, and delegation.
   - Receives: `$settings`, `$history_store`, `$ai_generator` via constructor
   - **Estimated lines:** ~2,200

3. **Create `includes/admin/class-admin-ajax.php`**
   - Move all `handle_ajax_*` methods:
     - `handle_ajax_save_editor_meta()` (line 3690)
     - `handle_ajax_generate_editor_meta()` (line 3724)
     - `handle_ajax_approve_suggestion()` (line 3823)
     - `handle_ajax_chat_for_post()` (line 3879)
     - `handle_ajax_setup_index()` (line 3969)
     - `handle_ajax_bulk_generate()` (line 3990)
     - `handle_ajax_page_audit()` (line 4088)
     - `handle_ajax_toggle_audit_skip()` (line 4172)
     - `handle_ajax_save_skip_patterns()` (line 4201)
     - `handle_ajax_content_edit()` (line 4300)
     - `handle_ajax_apply_changes()` (line 4335)
     - `handle_ajax_apply_suggestion()` (line 4386)
     - `handle_ajax_restore_backup()` (line 4422)
     - `handle_ajax_clear_chat()` (line 4445)
     - `handle_ajax_test_model()` (line 4464)
     - `handle_ajax_delete_edit_plan()` (line 4527)
     - `handle_ajax_bulk_save_seo()` (line 1616)
     - `handle_ajax_save_image_alt()` (line 1756)
   - Helper methods:
     - `count_pages_matching_skip_patterns()`, `parse_skip_patterns()`
     - `is_audit_skipped()`, `path_matches_skip_patterns()`
   - Receives: `$settings`, `$content_indexer`, `$ai_generator`, `$history_store`, `$indexnow_service`
   - **Estimated lines:** ~1,100

4. **Create `includes/admin/class-admin-pages.php`**
   - Move page renderers:
     - `render_dashboard()` (line 1532)
     - `render_redirects_page()` (line 1556)
     - `render_bulk_editor_page()` (line 1574)
     - `render_image_seo_page()` (line 1641)
     - `render_keyword_tracking_page()` (line 1778)
     - `render_export_import_page()` (line 1836)
     - `render_settings_page()` (line 2024)
     - `render_audit_page()` (line 2042)
     - `render_setup_wizard_page()` (line 4547)
     - `render_audit_post_links()` (line 5559)
   - Receives: `$settings`, `$content_indexer`, `$history_store`, `$audit_engine`, `$indexnow_service`
   - **Estimated lines:** ~500

5. **Create `includes/admin/class-admin-taxonomy.php`**
   - Move:
     - `register_taxonomy_seo_fields()` (line 204)
     - `render_term_seo_fields()` (line 216)
     - `save_term_seo_fields()` (line 261)
   - Standalone — no dependencies beyond WordPress
   - **Estimated lines:** ~120

6. **Create `includes/admin/class-admin-import-export.php`**
   - Move:
     - `handle_export()` (line 1851)
     - `handle_import()` (line 1944)
     - `handle_import_yoast_metadata()` (line 5863)
     - `import_yoast_metadata()` (line 5991)
     - `get_yoast_import_candidate_ids()` (line 6107)
     - `map_yoast_robots_directives()` (line 6158)
   - Receives: `$settings`
   - **Estimated lines:** ~350

7. **Create `includes/admin/class-admin-rollout.php`**
   - Move:
     - `handle_sync_index()` (line 5750)
     - `handle_generate_site_audit()` (line 5772)
     - `handle_submit_indexnow()` (line 5829)
     - `handle_bulk_frontend_rollout()` (line 5900)
     - `apply_bulk_frontend_gate()` (line 5938)
     - `redirect_to_audit_page()`, `redirect_to_settings_page()`
   - Receives: `$settings`, `$content_indexer`, `$ai_generator`, `$history_store`, `$audit_engine`, `$indexnow_service`
   - **Estimated lines:** ~250

8. **Slim down `class-admin.php` to coordinator**
   - Keep only:
     - Constructor that creates sub-module instances and passes dependencies
     - `register_menu()` — registers all admin pages
     - `enqueue_editor_assets()` + `enqueue_page_assets()`
     - `is_supported_post_type()` (shared utility)
     - `has_conflicting_seo_plugin()` (shared utility, or move to Settings)
   - Delegates everything else to sub-modules
   - **Target: ≤200 lines**

### Concern 2 — PSR-4 Autoloader
- `includes/autoload.php` added with SPL autoloader mapping `AI_SEO_Keeper\` → `includes/`
- All manual `require_once` calls removed from bootstrap

### Concern 3 — Automated Test Suite
- `tests/` directory with `bootstrap.php`, `stubs/`, `unit/`
- 81 PHPUnit tests, 141 assertions — all passing
- Covers: SEO analysis, settings, content helper, meta keys, frontend output, sitemap

### Concern 4 — Uninstall Cleanup
- `uninstall.php` updated to delete all 5 tables, all 17 post meta keys, 4 term meta keys, options, user meta

### Concern 5 — i18n / Translation Support
- All user-facing strings wrapped with `__()`, `_e()`, `esc_html__()`, `esc_attr__()` using text domain `ai-seo-keeper`

### Concern 6 — Gutenberg Sidebar Panel
- `assets/js/gutenberg-sidebar.js` + `assets/css/gutenberg-sidebar.css` added
- Conditionally enqueued on block editor screens

### Concern 7 — WooCommerce Integration
- `includes/class-woocommerce-integration.php` added
- Product-aware filtering in wizard modal, bulk editor, site structure tree
- Automatic detection — no configuration needed

- [ ] **Open Graph Enrichment**
  - `og:type` = `product` for single products
  - `product:price:amount` and `product:price:currency` tags
  - Product gallery images as additional `og:image` tags

- [ ] **AI Context for Products**
  - Include price, SKU, stock status, product attributes in AI generation prompts
  - Product-specific AI prompt template: "Generate SEO metadata for an e-commerce product page"

---

## Execution Order

The concerns have dependencies. Recommended execution sequence:

```
Phase 1 — Foundation (do first)
├── 1. Admin monolith split (blocks everything else)
├── 2. Autoloader (makes split cleaner)
└── 4. Fix uninstall.php (quick win, do alongside)

Phase 2 — Quality & Reach
├── 3. Automated tests (validates Phase 1 didn't break anything)
└── 5. i18n (wrap strings in the new smaller files)

Phase 3 — Market Features
├── 6. Gutenberg sidebar (biggest UX impact)
└── 7. WooCommerce integration (biggest market impact)
```

### Rules for All Changes

- **One class extraction at a time** — extract, test all admin pages, commit
- **Zero functional changes during refactoring** — same inputs, same outputs, same AJAX actions
- **Browser-test every admin page and editor after each extraction**
- **Keep the Classic Editor metabox working** — the Gutenberg sidebar is an addition, not a replacement
- **All new files must use the `AI_SEO_Keeper` namespace**
- **No new features until the monolith split is complete**

---

## Success Criteria

| Metric | Before | After |
|--------|--------|-------|
| Largest file | 5,324 lines | ≤500 lines |
| Files in `includes/admin/` | 8 (views only) | 15+ (views + logic classes) |
| `require_once` in bootstrap | 15 | 1 (autoloader) |
| Test coverage | 0% | ≥60% on core logic |
| Translatable strings | 0 | 200+ |
| Gutenberg sidebar | ❌ | ✅ |
| WooCommerce support | ❌ | ✅ |
| Clean uninstall | Partial | Complete |
