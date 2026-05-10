# AI SEO Keeper — v1.3 Update Plan

> **Created:** May 10, 2026
> **Goal:** Address all 7 structural concerns identified in the code audit to make the plugin maintainable, team-ready, and marketplace-competitive.

---

## Overview

| # | Concern | Severity | Impact |
|---|---------|----------|--------|
| 1 | Admin monolith (5,324 lines) | **Critical** | Blocks future dev, onboarding, code reviews |
| 2 | No autoloader | Medium | Fragile, manual require chains |
| 3 | No automated tests | **High** | Regressions on every change |
| 4 | Incomplete uninstall cleanup | Medium | Dirty uninstall, WP.org rejection risk |
| 5 | No i18n/translation support | **High** | Blocks non-English markets |
| 6 | No Gutenberg sidebar panel | **High** | Poor UX for 80%+ of WP users |
| 7 | No WooCommerce integration | Medium | Misses largest WP vertical |

---

## Concern 1 — Split the Admin Monolith (CRITICAL)

### Problem
`class-admin.php` is **5,324 lines** containing 46 public methods and 40 private methods. It handles:
- Menu registration & asset enqueuing
- Editor metabox rendering (~1,600 lines of inline HTML/JS)
- 20+ AJAX handlers (generate, chat, approve, bulk, audit, content edit, restore, etc.)
- Page renderers for 8 admin pages (dashboard, settings, audit, bulk editor, images, keywords, export/import, setup wizard)
- Taxonomy SEO fields
- Yoast import logic
- SEO analysis engine (readability, keyphrase density, link checks)
- Export/Import handlers
- Skip pattern logic

No new developer can understand or safely modify this file. It is the #1 priority.

### Decomposition Plan

Split into **8 focused classes** under `includes/admin/`:

```
includes/
├── class-admin.php              ← SLIM coordinator (~200 lines)
│                                   Only: constructor, dependency wiring,
│                                   menu registration, asset enqueuing,
│                                   delegating to sub-modules
│
└── admin/
    ├── class-admin-metabox.php         ← Editor metabox rendering + save_post
    ├── class-admin-ajax.php            ← All AJAX handlers (generate, chat,
    │                                      approve, bulk, audit, content edit,
    │                                      restore, test model, clear chat, etc.)
    ├── class-admin-seo-analysis.php    ← Deterministic SEO checks: keyphrase
    │                                      density, readability, link analysis,
    │                                      content structure, FAQ detection
    ├── class-admin-pages.php           ← Page renderers: dashboard, audit,
    │                                      bulk editor, images, keywords,
    │                                      export/import, setup wizard, redirects
    ├── class-admin-taxonomy.php        ← Taxonomy term SEO fields (render + save)
    ├── class-admin-import-export.php   ← Yoast importer, settings export/import
    ├── class-admin-rollout.php         ← Bulk frontend gate, IndexNow submission,
    │                                      sync index, site audit generation
    └── view-*.php                      ← (Existing view templates stay here)
```

### Execution Steps

1. **Create `includes/admin/class-admin-seo-analysis.php`**
   - Move all SEO check methods:
     - `render_focus_keyphrase_checks_markup()` (lines 5012-5382)
     - `extract_content_blocks()`, `extract_sentences()`, `count_words()`
     - `count_transition_words()`, `count_passive_voice_sentences()`
     - `count_repeated_sentence_starts()`, `count_question_style_headings()`
     - `is_question_style_heading()`, `is_generic_anchor_text()`
     - `normalize_text_for_match()`
     - Constants: `READABILITY_TRANSITION_WORDS`, `GENERIC_ANCHOR_TEXTS`
     - Constants: `TITLE_MIN_LENGTH`, `TITLE_MAX_LENGTH`, `DESCRIPTION_MIN_LENGTH`, `DESCRIPTION_MAX_LENGTH`
   - Make it a static utility class (no dependencies)
   - **Estimated lines:** ~600

2. **Create `includes/admin/class-admin-metabox.php`**
   - Move:
     - `register_editor_metabox()` (line 2072)
     - `render_editor_metabox()` (line 2095 → ~line 3555)
     - `render_internal_links_tab()` (line 2527)
     - `render_editor_panel_styles()` (line 2704)
     - `render_accordion_section()` (line 3555)
     - `render_frontend_readiness_markup()` (line 3574)
     - `get_metabox_preview_image_url()` (line 3632)
     - `has_saved_frontend_data()` / `has_saved_frontend_data_for_post()` (lines 3663-3688)
     - `render_field_counter()` (line 4927)
     - `render_history_markup()` (line 5600)
     - `render_chat_history_markup()` (line 5701)
     - `get_schema_type_options()`, `get_robots_directive_options()`
     - `save_editor_meta()` (line 4746)
     - `persist_editor_meta()` (line 4796)
     - `apply_editor_text_limits()`, `get_text_length()`, `truncate_text()`
     - `filter_admin_pending_builder_meta()` (line 4643)
     - `get_editor_script()` (line 489) — the ~1,000-line inline JS
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

### Shared Constants

Create `includes/admin/class-admin-constants.php` (or a trait) for the 12+ post meta key constants that are currently duplicated across `class-admin.php`, `class-frontend.php`, `class-content-indexer.php`, and `class-audit-engine.php`:

```php
final class Meta_Keys {
    public const TITLE           = '_ai_seo_keeper_meta_title';
    public const DESCRIPTION     = '_ai_seo_keeper_meta_description';
    public const FOCUS_KEYPHRASE = '_ai_seo_keeper_focus_keyphrase';
    public const SOCIAL_TITLE    = '_ai_seo_keeper_social_title';
    public const SOCIAL_DESC     = '_ai_seo_keeper_social_description';
    public const SOCIAL_IMAGE    = '_ai_seo_keeper_social_image';
    public const CANONICAL       = '_ai_seo_keeper_canonical_url';
    public const ROBOTS          = '_ai_seo_keeper_robots_directives';
    public const SCHEMA_TYPE     = '_ai_seo_keeper_schema_type';
    public const FRONTEND_ON     = '_ai_seo_keeper_frontend_enabled';
    public const APPROVED_MSG    = '_ai_seo_keeper_approved_message_id';
    public const BRANDING_OFF    = '_ai_seo_keeper_title_branding_off';
    public const CORNERSTONE     = '_ai_seo_keeper_cornerstone';
    public const HREFLANG        = '_ai_seo_keeper_hreflang';
    public const PAGE_AUDIT      = '_ai_seo_keeper_page_audit';
    public const PENDING_CHANGES = '_ai_seo_keeper_pending_content_changes';
    public const CONTENT_BACKUP  = '_ai_seo_keeper_content_backup';
}
```

Then replace all per-class `private const` declarations with `Meta_Keys::TITLE`, etc.

### Inline JS Extraction

The `get_editor_script()` method returns ~1,000 lines of JavaScript as a PHP string. This is fragile (no syntax highlighting, no linting, no minification). 

- **Step 1:** Extract to `assets/js/editor-metabox.js` as a proper JS file
- **Step 2:** Pass PHP data via `wp_localize_script()` (already partially done via `aiSeoKeeperEditor`)
- **Step 3:** Enqueue normally in `enqueue_editor_assets()`

### Rules for the Split

- **Zero behavior changes** — this is a pure refactoring, no features added or removed
- **Same AJAX action names** — all `wp_ajax_*` hooks remain identical
- **Same nonce names** — no frontend breakage
- **One class at a time** — extract, test in browser, commit, move to next
- **Run all admin pages after each extraction** to catch regressions

---

## Concern 2 — Add PSR-4 Autoloader

### Problem
15 manual `require_once` calls in `ai-seo-keeper.php` and duplicate requires in multiple class files. Adding new classes requires editing the bootstrap every time.

### Plan

- [ ] Create `includes/autoload.php` with a simple SPL autoloader mapping `AI_SEO_Keeper\` → `includes/`
- [ ] Class file naming convention: `class-{name}.php` maps to `AI_SEO_Keeper\{Name}` (already consistent)
- [ ] Handle the `admin/` subdirectory: `AI_SEO_Keeper\Admin\{Name}` → `includes/admin/class-admin-{name}.php`
- [ ] Replace all 15+ `require_once` calls in `ai-seo-keeper.php` with a single `require_once 'includes/autoload.php'`
- [ ] Remove duplicate `require_once` from inside class files (`class-plugin.php` has 10, `class-admin.php` has 7, etc.)
- [ ] Keep `class-activator.php` explicitly required in the activation hook (runs before autoloader is registered)

### Autoloader Implementation

```php
spl_autoload_register(function (string $class): void {
    $prefix = 'AI_SEO_Keeper\\';
    if (0 !== strncmp($class, $prefix, strlen($prefix))) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    $class_name = array_pop($parts);
    $subdir = !empty($parts) ? strtolower(implode('/', $parts)) . '/' : '';
    $file = __DIR__ . '/' . $subdir . 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
```

---

## Concern 3 — Add Automated Test Suite

### Problem
No `tests/` directory. Every code change is a regression risk, especially after the admin decomposition.

### Plan

- [ ] Create `tests/` directory structure:
  ```
  tests/
  ├── bootstrap.php           ← WP test bootstrapper
  ├── phpunit.xml              ← Config
  ├── unit/                    ← Pure PHP tests (no WP)
  │   ├── test-seo-analysis.php
  │   ├── test-content-helper.php
  │   └── test-settings.php
  └── integration/             ← WP-dependent tests
      ├── test-frontend-output.php
      ├── test-sitemap.php
      ├── test-redirects.php
      ├── test-ai-generator.php
      └── test-admin-ajax.php
  ```

### Priority Test Cases (in order)

1. **SEO Analysis** (pure PHP, no WP needed)
   - Title/description length scoring boundaries (0, 29, 30, 60, 61 chars)
   - Keyphrase in title/description/URL detection
   - Keyphrase density calculation
   - Readability: sentence count, transition words, passive voice
   - Question-style heading detection
   - Generic anchor text detection

2. **Content Helper** (needs WP for `get_post_meta`)
   - `get_content()` returns post_content when no builder
   - Shortcode stripping preserves inner text
   - BeTheme base64 extraction correctness
   - Elementor JSON extraction
   - Cache hit on second call
   - Recursion guard

3. **Frontend Output** (needs WP)
   - Title cascade: approved > manual > template > fallback
   - Branding suffix appended / opt-out respected
   - Noindex controls per archive type
   - Schema type classification
   - FAQ schema extraction from content
   - Conflict detection (Yoast active → no output)

4. **Settings** (pure PHP + WP options)
   - `sanitize()` rejects invalid providers
   - Temperature clamped to 0.0-2.0
   - Custom model ID sanitization
   - Title template defaults
   - Boolean flags from checkbox inputs

5. **Sitemap** (needs WP)
   - Valid XML output
   - Noindexed pages excluded
   - News sitemap only includes last 48h
   - Video sitemap detects YouTube/Vimeo embeds

### Testing Framework

- Use WordPress's `WP_UnitTestCase` (via `wp-phpunit`)
- CI: GitHub Actions workflow running `phpunit` on push/PR
- No mocking of AI provider calls in unit tests (those belong in integration tests with stubbed HTTP)

---

## Concern 4 — Fix Uninstall Cleanup

### Problem
`uninstall.php` is missing:
- `ai_seo_keeper_redirects` table DROP
- `ai_seo_keeper_db_version` option deletion
- 6 post meta keys used in the codebase
- All term meta keys (taxonomy SEO fields)

### Missing Items (code-verified)

**Missing table:**
- `DROP TABLE IF EXISTS {prefix}ai_seo_keeper_redirects`

**Missing options:**
- `ai_seo_keeper_db_version`

**Missing post meta keys:**
- `_ai_seo_keeper_title_branding_off`
- `_ai_seo_keeper_cornerstone`
- `_ai_seo_keeper_page_audit`
- `_ai_seo_keeper_pending_content_changes`
- `_ai_seo_keeper_content_backup`
- `_ai_seo_keeper_hreflang`

**Missing term meta cleanup:**
- `_ai_seo_keeper_seo_title` (term meta)
- `_ai_seo_keeper_meta_description` (term meta)
- `_ai_seo_keeper_canonical` (term meta)
- `_ai_seo_keeper_noindex` (term meta)

### Updated `uninstall.php` should include:

```php
// Tables
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_keeper_redirects");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_keeper_messages");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_keeper_conversations");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_keeper_content_index");

// Options
delete_option('ai_seo_keeper_options');
delete_option('ai_seo_keeper_indexnow_log');
delete_option('ai_seo_keeper_db_version');

// Post meta (all 17 keys)
$meta_keys = array(
    '_ai_seo_keeper_focus_keyphrase',
    '_ai_seo_keeper_meta_title',
    '_ai_seo_keeper_meta_description',
    '_ai_seo_keeper_social_title',
    '_ai_seo_keeper_social_description',
    '_ai_seo_keeper_social_image',
    '_ai_seo_keeper_canonical_url',
    '_ai_seo_keeper_robots_directives',
    '_ai_seo_keeper_schema_type',
    '_ai_seo_keeper_approved_message_id',
    '_ai_seo_keeper_frontend_enabled',
    '_ai_seo_keeper_title_branding_off',
    '_ai_seo_keeper_cornerstone',
    '_ai_seo_keeper_page_audit',
    '_ai_seo_keeper_pending_content_changes',
    '_ai_seo_keeper_content_backup',
    '_ai_seo_keeper_hreflang',
);

// Term meta (4 keys)
$term_meta_keys = array(
    '_ai_seo_keeper_seo_title',
    '_ai_seo_keeper_meta_description',
    '_ai_seo_keeper_canonical',
    '_ai_seo_keeper_noindex',
);
```

---

## Concern 5 — Add i18n / Translation Support

### Problem
Zero `__()` or `_e()` calls. The plugin is English-only. This blocks:
- WordPress.org plugin directory listing (i18n is a review requirement for featured plugins)
- Non-English markets (60%+ of WordPress sites)

### Plan

- [ ] **Audit all user-facing strings** across:
  - 8 admin view templates (`view-*.php`)
  - Editor metabox HTML in `class-admin.php` / `class-admin-metabox.php`
  - Taxonomy SEO field labels
  - AJAX success/error messages
  - Settings page labels and descriptions
  - Dashboard stat labels
  - Setup wizard steps
  - Readability/SEO check result text

- [ ] **Wrap all strings** with appropriate functions:
  - `__('text', 'ai-seo-keeper')` for strings returned as values
  - `_e('text', 'ai-seo-keeper')` for directly echoed strings
  - `esc_html__()` / `esc_attr__()` for strings inside HTML attributes
  - `_n()` for plurals
  - `sprintf(__('Found %d issues', 'ai-seo-keeper'), $count)` for interpolated strings

- [ ] **Create POT file**: `languages/ai-seo-keeper.pot` using WP-CLI:
  ```
  wp i18n make-pot . languages/ai-seo-keeper.pot
  ```

- [ ] **Register text domain** in plugin header (already has `Text Domain: ai-seo-keeper`)

- [ ] **Load text domain** in plugin bootstrap:
  ```php
  add_action('init', function() {
      load_plugin_textdomain('ai-seo-keeper', false, dirname(plugin_basename(__FILE__)) . '/languages');
  });
  ```

- [ ] **JS translations** for editor metabox inline script:
  - Use `wp_set_script_translations()` once the JS is extracted to a file
  - Or pass all UI strings via `wp_localize_script()` (simpler, already partially done)

### Scope Estimate
- ~200-300 strings to wrap across the codebase
- No functional changes — pure string wrapping
- Best done AFTER the admin decomposition (Concern 1) so strings are in smaller files

---

## Concern 6 — Gutenberg Block Editor Sidebar Panel

### Problem
The current editor integration is a Classic Editor metabox. In Gutenberg, this renders below the content area, not in the sidebar where users expect SEO controls (like Yoast). ~80%+ of WordPress 6.7+ sites use the block editor.

### Plan

- [ ] **Create a Gutenberg sidebar plugin** using `@wordpress/plugins` and `@wordpress/edit-post`
- [ ] Register a `PluginSidebar` component in the post editor
- [ ] Build React components for all existing metabox tabs:
  - SEO tab (title, description, keyphrase, search preview, snippet analyzer)
  - Social tab (OG/Twitter overrides, image picker, preview cards)
  - Advanced tab (schema, canonical, robots, cornerstone, hreflang)
  - Basic SEO Checks tab (all deterministic checks, readability)
  - AI Chat tab (conversation interface)
  - History/suggestions panel

### Architecture

```
assets/
├── js/
│   ├── gutenberg/
│   │   ├── index.js              ← Entry point, PluginSidebar registration
│   │   ├── components/
│   │   │   ├── SeoTab.js
│   │   │   ├── SocialTab.js
│   │   │   ├── AdvancedTab.js
│   │   │   ├── SeoChecksTab.js
│   │   │   ├── AiChatTab.js
│   │   │   ├── SearchPreview.js
│   │   │   ├── SnippetAnalyzer.js
│   │   │   └── SuggestionHistory.js
│   │   └── store/
│   │       └── index.js          ← WP data store for SEO state
│   └── editor-metabox.js         ← Existing Classic Editor JS (extracted)
```

### Build Tooling

- [ ] Add `package.json` with `@wordpress/scripts` for build pipeline
- [ ] `wp-scripts build` to compile JSX → production JS
- [ ] Conditional enqueue: only load Gutenberg sidebar JS when block editor is active
- [ ] Keep Classic Editor metabox for users with Classic Editor plugin active

### Data Flow

- Read/write post meta via `@wordpress/data` and `core/editor` store
- AJAX endpoints remain the same — React components call them via `apiFetch`
- SEO checks run client-side (port existing JS logic to React)
- AI generation calls the same AJAX handlers

### Incremental Delivery

1. **Phase A:** Basic sidebar with SEO tab (title, description, keyphrase, preview)
2. **Phase B:** Social tab + Advanced tab
3. **Phase C:** SEO Checks tab (port analysis logic to JS)
4. **Phase D:** AI Chat tab (real-time conversation UI)

---

## Concern 7 — WooCommerce Integration

### Problem
WooCommerce is the largest WordPress vertical (~6M active installs). The plugin has generic Product schema but no WooCommerce-specific hooks, no shop page SEO, and no product-specific metadata enrichment.

### Plan

- [ ] **Create `includes/class-woocommerce.php`** — loaded conditionally when WooCommerce is active
- [ ] **Conditional loading** in `class-plugin.php`:
  ```php
  if (class_exists('WooCommerce')) {
      $this->woocommerce = new WooCommerce_Integration($this->settings);
  }
  ```

### Feature Checklist

- [ ] **Product Schema Enrichment**
  - Read WooCommerce product data: price, currency, SKU, stock status, reviews, rating
  - Output `Product` schema with `offers` (price, availability, priceCurrency), `aggregateRating`, `brand`, `sku`
  - Handle variable products (multiple offers)

- [ ] **Shop Page SEO**
  - Title template for the shop page: `%%shop_title%% %%sep%% %%sitename%%`
  - Meta description for the main shop archive
  - Schema: `CollectionPage` with `ItemList` of products

- [ ] **Product Category SEO**
  - Title/description templates for product categories
  - Canonical URLs for paginated product archives
  - Category-level noindex controls

- [ ] **Product Sitemap**
  - Add `product-sitemap.xml` to the sitemap index
  - Include product images in sitemap entries
  - Respect WooCommerce visibility settings (exclude hidden/private products)

- [ ] **Breadcrumbs Integration**
  - Override WooCommerce default breadcrumbs via filter
  - Include product categories in breadcrumb trail + schema

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
