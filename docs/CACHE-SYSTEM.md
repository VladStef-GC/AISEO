# Cache System — Technical Reference

> **Plugin:** AI SEO Captain v1.0.0-beta  
> **Namespace:** `AI_SEO_Captain\Cache`  
> **Location:** `includes/cache/`  
> **Admin UI:** `includes/admin/view-cache.php`  
> **Assets:** `assets/css/page-cache.css` · `assets/js/page-cache.js`

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [File Structure](#2-file-structure)
3. [Settings & Defaults](#3-settings--defaults)
4. [Class Reference](#4-class-reference)
   - 4.1 [Cache_Manager](#41-cache_manager)
   - 4.2 [Page_Cache](#42-page_cache)
   - 4.3 [Cache_Invalidator](#43-cache_invalidator)
   - 4.4 [Browser_Cache](#44-browser_cache)
   - 4.5 [Object_Cache](#45-object_cache)
   - 4.6 [Cache_Preloader](#46-cache_preloader)
   - 4.7 [Minifier](#47-minifier)
   - 4.8 [Lazy_Loader](#48-lazy_loader)
5. [Drop-in Files](#5-drop-in-files)
6. [Admin UI & AJAX](#6-admin-ui--ajax)
7. [WooCommerce Compatibility](#7-woocommerce-compatibility)
8. [Conflict Detection](#8-conflict-detection)
9. [Safety & Backup System](#9-safety--backup-system)
10. [Cache Flow Diagrams](#10-cache-flow-diagrams)
11. [Exclusion Rules](#11-exclusion-rules)
12. [Deactivation & Cleanup](#12-deactivation--cleanup)
13. [Testing](#13-testing)
14. [What Is NOT Included (v1)](#14-what-is-not-included-v1)

---

## 1. Architecture Overview

The cache system is a **self-contained module** inside the AI SEO Captain plugin. It operates independently from the SEO features and can be enabled/disabled via a master toggle (`cache_enabled`).

**Design principles:**

- **File-based** — no external dependencies (Redis, Memcached).
- **Non-destructive** — automatic backups before modifying `.htaccess` or `wp-config.php`.
- **Conflict-aware** — detects other caching/minification/lazy-loading plugins and disables overlapping features.
- **WooCommerce-safe** — excludes cart, checkout, my-account; reacts to stock changes.
- **Drop-in support** — installs `advanced-cache.php` (full-page) and `object-cache.php` (DB query) into `wp-content/`.

**Subsystem hierarchy:**

```
Cache_Manager (orchestrator)
├── Page_Cache          — file-based full-page HTML cache
├── Cache_Invalidator   — smart purge on content changes
├── Browser_Cache       — HTTP headers + .htaccess rules
├── Object_Cache        — file-based WP object cache drop-in
├── Cache_Preloader     — sitemap crawler to warm cache
├── Minifier            — inline CSS/JS minification
└── Lazy_Loader         — native loading="lazy" injection
```

---

## 2. File Structure

```
ai-seo-captain/
├── includes/cache/
│   ├── class-cache-manager.php        Central orchestrator
│   ├── class-page-cache.php           File-based full-page HTML cache
│   ├── class-cache-invalidator.php    Smart cache invalidation
│   ├── class-browser-cache.php        HTTP headers + .htaccess rules
│   ├── class-object-cache.php         File-based object cache + drop-in
│   ├── class-cache-preloader.php      Sitemap crawler for cache warming
│   ├── class-minifier.php             CSS/JS minification
│   └── class-lazy-loader.php          Image/iframe lazy loading
│
├── includes/admin/
│   └── view-cache.php                 Cache admin page template
│
├── assets/css/
│   └── page-cache.css                 Cache admin page styles
│
├── assets/js/
│   └── page-cache.js                  Cache admin page interactions (AJAX)
│
├── advanced-cache.php                 Drop-in source (copied to wp-content/)
│
└── wp-content/  (runtime — created by the plugin)
    ├── advanced-cache.php             Full-page cache fast-path
    ├── object-cache.php               Object cache drop-in
    └── cache/ai-seo-captain/
        ├── pages/{xx}/{hash}.html     Cached HTML files (2-char prefix dirs)
        ├── pages/{xx}/{hash}.meta     Cache metadata (URL, created, TTL, gzip)
        ├── objects/{group}/{hash}.php Object cache files
        ├── backups/                   .htaccess and wp-config.php backups
        │   ├── htaccess_YYYY-MM-DD_HH-MM-SS.bak
        │   ├── wp-config_YYYY-MM-DD_HH-MM-SS.bak
        │   ├── index.php             Directory listing protection
        │   └── .htaccess             "Deny from all"
        └── index.php                 Directory listing protection
```

---

## 3. Settings & Defaults

All cache settings are stored in the shared `ai_seo_captain_settings` option. The Settings class uses a `save_source` flag (`cache`) to isolate cache checkbox saves from SEO settings saves, preventing one page from zeroing out the other.

| Setting Key                 | Type     | Default | Range / Notes                          |
|-----------------------------|----------|---------|----------------------------------------|
| `cache_enabled`             | bool     | `0`     | Master toggle for the entire module    |
| `cache_page_enabled`        | bool     | `1`     | Full-page HTML cache                   |
| `cache_page_ttl`            | int      | `86400` | 300–604800 seconds (5 min – 7 days)    |
| `cache_browser_enabled`     | bool     | `1`     | HTTP Cache-Control / Expires headers   |
| `cache_gzip_enabled`        | bool     | `1`     | PHP-level gzip via `ob_gzhandler`      |
| `cache_object_enabled`      | bool     | `0`     | File-based object cache                |
| `cache_preload_enabled`     | bool     | `1`     | Sitemap-based cache preloading         |
| `cache_preload_batch_size`  | int      | `5`     | 1–50 URLs per cron batch               |
| `cache_minify_css`          | bool     | `0`     | Inline CSS minification                |
| `cache_minify_js`           | bool     | `0`     | Inline JS minification                 |
| `cache_minify_html`         | bool     | `0`     | HTML minification on cached output     |
| `cache_lazy_load`           | bool     | `1`     | Image/iframe lazy loading              |
| `cache_lazy_skip_count`     | int      | `2`     | 0–10 above-the-fold images to skip     |
| `cache_exclude_urls`        | textarea | `''`    | One URL pattern per line               |
| `cache_exclude_cookies`     | textarea | `''`    | One cookie name per line               |
| `cache_exclude_useragents`  | textarea | `''`    | One user-agent substring per line      |
| `cache_query_string_cache`  | bool     | `0`     | Cache URLs with query strings          |
| `cache_wc_exclude_cart`     | bool     | `1`     | Exclude WooCommerce dynamic pages      |

---

## 4. Class Reference

### 4.1 Cache_Manager

**File:** `class-cache-manager.php`  
**Role:** Central orchestrator — boots all subsystems, registers AJAX handlers, manages drop-ins.

**Constructor:** `__construct(\AI_SEO_Captain\Settings $settings)`

**Boot sequence** (`boot()` — called from `class-plugin.php`):

1. Always registers all 10 AJAX handlers (so admin UI works even when cache is off).
2. If `cache_enabled` is off → return early (no subsystems loaded).
3. Instantiates: `Page_Cache`, `Cache_Preloader`, `Cache_Invalidator`, `Browser_Cache`, `Object_Cache`, `Minifier`, `Lazy_Loader`.
4. Registers hooks conditionally based on individual feature toggles.
5. Adds admin bar purge button.

**Public methods:**

| Method | Description |
|--------|-------------|
| `boot()` | Wire everything up. Called once at plugin init. |
| `purge_all()` | Deletes all page cache files + flushes object cache. Updates `aisc_cache_last_purge` option. |
| `purge_url(string $url)` | Selective purge of a single URL. |
| `purge_urls(array $urls)` | Selective purge of multiple URLs. |
| `preload()` | Triggers sitemap crawl to warm the cache. |
| `install_dropin(string $type)` | Installs `advanced-cache` or `object-cache` drop-in. |
| `remove_dropin(string $type)` | Removes a drop-in (only if it belongs to us). |
| `get_status(): array` | Returns full status for admin UI (file counts, sizes, drop-in status, etc). |
| `maybe_start_gzip()` | Starts `ob_gzhandler` if client supports it. |
| `add_admin_bar_purge($admin_bar)` | Adds "SEO Captain Cache" → "Purge All" / "Purge This Page" to admin bar. |
| `static deactivate()` | Cleanup on plugin deactivation (removes drop-ins, .htaccess rules, cache files). |

**AJAX handlers (10 total):**

| AJAX Action | Method | Description |
|-------------|--------|-------------|
| `aisc_purge_cache` | `ajax_purge_cache()` | Purge all cache |
| `aisc_preload_cache` | `ajax_preload_cache()` | Start sitemap preload |
| `aisc_cache_status` | `ajax_cache_status()` | Get status JSON for admin UI |
| `aisc_install_dropin` | `ajax_install_dropin()` | Install advanced-cache or object-cache |
| `aisc_remove_dropin` | `ajax_remove_dropin()` | Remove a drop-in |
| `aisc_install_htaccess` | `ajax_install_htaccess()` | Write .htaccess rules |
| `aisc_remove_htaccess` | `ajax_remove_htaccess()` | Remove .htaccess rules |
| `aisc_restore_htaccess` | `ajax_restore_htaccess()` | Restore from most recent backup |
| `aisc_purge_this_url` | `ajax_purge_this_url()` | Purge a single URL |
| `aisc_purge_post_cache` | `ajax_purge_post_cache()` | Purge all variants of a post (including pagination) |

All AJAX handlers enforce `check_ajax_referer('aisc_cache_nonce')` and `current_user_can('manage_options')`.

**wp-config.php management:**

- `set_wp_cache_constant(bool)` — adds/removes `define('WP_CACHE', true)` in `wp-config.php`.
- `backup_wp_config()` — creates timestamped backup before any modification, keeps max 5 backups.

---

### 4.2 Page_Cache

**File:** `class-page-cache.php`  
**Role:** File-based full-page HTML cache.

**How it works:**

1. **Capture:** On `template_redirect` (priority 0), starts `ob_start()` if the request is cacheable.
2. **Write:** On buffer flush (`end_capture`), writes `{hash}.html` + `{hash}.meta` to disk.
3. **Serve:** `serve_if_cached()` (static, called from `advanced-cache.php`) reads the file and outputs it before WordPress loads.
4. **GZIP:** On serve, compresses output with `gzencode()` if client accepts it.

**Cache directory structure:**

```
wp-content/cache/ai-seo-captain/pages/
├── a3/
│   ├── a3f7c2e1...html    ← cached HTML
│   └── a3f7c2e1...meta    ← serialized metadata {url, created, ttl, gzip}
├── b1/
│   └── ...
```

Files are stored in 2-character prefix subdirectories (first 2 chars of the MD5 hash) to avoid filesystem limitations on large sites.

**Cacheability checks** (`is_cacheable()`):

| Condition | Result |
|-----------|--------|
| Admin, login, cron, xmlrpc, REST, admin-ajax | Skip |
| Non-GET request | Skip |
| Logged-in user | Skip |
| 404 page | Skip |
| Search results | Skip |
| `DONOTCACHEPAGE` constant set | Skip |
| Query string present (unless `cache_query_string_cache` on) | Skip |
| WooCommerce cart/checkout cookies present | Skip |
| WooCommerce dynamic pages (cart, checkout, my-account) | Skip |
| URL matches exclusion pattern | Skip |
| Cookie name matches exclusion list | Skip |
| User-agent matches exclusion pattern | Skip |
| HTML output < 255 bytes | Skip |

**Public methods:**

| Method | Description |
|--------|-------------|
| `start_capture()` | Start output buffering (hooked to `template_redirect`) |
| `end_capture(string $html): string` | Buffer callback — write cache file |
| `static serve_if_cached(string $uri, array $config): bool` | Fast-path serve from `advanced-cache.php` |
| `purge(string $url)` | Delete cached file for a URL |
| `purge_all()` | Delete all cached files |
| `get_stats(): array` | Returns `{files: int, size: int}` |
| `get_cache_dir(): string` | Returns the cache directory path |

**HTTP headers set:**

- On cache MISS: `X-Cache: MISS`, `X-Cache-Engine: AI-SEO-Captain`
- On cache HIT: `X-Cache: HIT`, `X-Cache-Engine: AI-SEO-Captain`, `Cache-Control: public, max-age={ttl}`

---

### 4.3 Cache_Invalidator

**File:** `class-cache-invalidator.php`  
**Role:** Smart cache invalidation — maps content changes to the full list of URLs that need purging.

**Constructor:** `__construct(Page_Cache $page_cache, ?Cache_Preloader $preloader, bool $auto_preload)`

**Registered hooks:**

| Hook | Priority | Callback | Trigger |
|------|----------|----------|---------|
| `save_post` | 99 | `on_post_save()` | Post created/updated |
| `delete_post` | 10 | `on_post_delete()` | Post deleted |
| `trashed_post` | 10 | `on_post_delete()` | Post trashed |
| `transition_post_status` | 10 | `on_post_status_change()` | Publish ↔ draft transitions |
| `comment_post` | 10 | `on_comment_change()` | New comment |
| `edit_comment` | 10 | `on_comment_change()` | Comment edited |
| `delete_comment` | 10 | `on_comment_change()` | Comment deleted |
| `wp_set_comment_status` | 10 | `on_comment_change()` | Comment approved/unapproved |
| `edited_term` | 10 | `on_term_edit()` | Term updated |
| `delete_term` | 10 | `on_term_edit()` | Term deleted |
| `created_term` | 10 | `on_term_edit()` | Term created |
| `switch_theme` | — | `on_full_purge_event()` | Theme changed |
| `update_option_sidebars_widgets` | — | `on_full_purge_event()` | Widget area changed |
| `wp_update_nav_menu` | — | `on_full_purge_event()` | Navigation menu changed |
| `permalink_structure_changed` | — | `on_full_purge_event()` | Permalink structure changed |
| `woocommerce_product_set_stock` | — | `on_wc_stock_change()` | Product stock changed |
| `woocommerce_variation_set_stock` | — | `on_wc_variation_stock_change()` | Variation stock changed |

**URL mapping** (`get_related_urls(int $post_id)`):

When a post is saved/deleted, these URLs are purged:

1. The post permalink
2. The homepage (`/`)
3. All category archive pages the post belongs to
4. All tag archive pages
5. The author archive page
6. The post type archive (if applicable)
7. Pagination pages (`/page/2/` through `/page/5/`, capped)
8. RSS feed URLs (main + per-category)

**Auto-preload:** If `auto_preload` is enabled and a preloader is set, purged URLs are immediately re-warmed via non-blocking HTTP requests.

**Duplicate guard:** `$purged_all` flag prevents multiple full purges in the same request.

---

### 4.4 Browser_Cache

**File:** `class-browser-cache.php`  
**Role:** HTTP response headers for cached pages + `.htaccess` rules for static assets.

**HTTP headers** (sent via `send_headers` hook):

- `Cache-Control: public, max-age={ttl}` — on non-admin, non-logged-in pages
- `Expires: {date}` — HTTP/1.0 fallback
- `Vary: Accept-Encoding` — if GZIP enabled

Skipped if `is_admin()`, `is_user_logged_in()`, or headers already sent.

**.htaccess rules generated:**

```apache
# BEGIN AI SEO Captain Cache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType font/woff2 "access plus 1 year"
</IfModule>
<IfModule mod_deflate.c>                    ← only if GZIP enabled
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml
</IfModule>
# END AI SEO Captain Cache
```

Rules are inserted **before** `# BEGIN WordPress` in `.htaccess`.

**Backup system:**

| Method | Description |
|--------|-------------|
| `install_htaccess()` | Creates backup → removes old rules → inserts new rules |
| `remove_htaccess()` | Creates backup → removes rules |
| `remove_htaccess_without_backup()` | Removes rules without backup (used during deactivation) |
| `backup_htaccess()` | Creates timestamped backup: `htaccess_YYYY-MM-DD_HH-MM-SS.bak` |
| `restore_htaccess()` | Restores from most recent backup |
| `get_backups(): array` | Lists all backups (newest first) with path, filename, human-readable date, size |
| `get_latest_backup(): ?array` | Returns the newest backup or null |

**Backup rotation:** Maximum 5 backups kept. Oldest are auto-deleted via `prune_old_backups()`.

**Backup protection:** The backup directory has:
- `index.php` — "Silence is golden" (prevents directory listing)
- `.htaccess` — "Deny from all" (blocks direct HTTP access)

---

### 4.5 Object_Cache

**File:** `class-object-cache.php`  
**Role:** File-based WordPress object cache via `object-cache.php` drop-in.

**How it works:**

The class generates a complete `object-cache.php` drop-in that implements WordPress's `WP_Object_Cache` API:

- `wp_cache_get()` / `wp_cache_set()` / `wp_cache_add()` / `wp_cache_replace()` / `wp_cache_delete()`
- `wp_cache_flush()` — clears all files + in-memory cache
- `wp_cache_add_global_groups()` / `wp_cache_add_non_persistent_groups()`
- `wp_cache_switch_to_blog()` — multisite support

**Storage:** `wp-content/cache/ai-seo-captain/objects/{group}/{hash}.php`

**Data format:** Each file contains serialized PHP:
```php
array(
    'value'   => $data,
    'expires' => $expire > 0 ? time() + $expire : 0,
)
```

**Two-tier caching:**
1. **In-memory** (`$this->cache` array) — fast lookups within the same request.
2. **On-disk** — persists across requests. Non-persistent groups skip disk writes.

**Safety:** Will NOT overwrite an existing `object-cache.php` that belongs to another plugin. Uses `AI-SEO-Captain-Object-Cache` signature for ownership detection.

**Public methods (installer class):**

| Method | Description |
|--------|-------------|
| `generate_dropin(): string` | Returns the full PHP source for object-cache.php |
| `install(): bool` | Writes drop-in to `wp-content/object-cache.php` |
| `remove(): bool` | Deletes drop-in (only if ours) |
| `is_ours(): bool` | Checks signature in the existing drop-in |
| `flush()` | Deletes all files in the objects directory |
| `get_stats(): array` | Returns `{files: int, size: int}` |

---

### 4.6 Cache_Preloader

**File:** `class-cache-preloader.php`  
**Role:** Warm the cache by crawling sitemap URLs after a purge.

**How it works:**

1. User clicks "Preload Cache" → `preload_all()` is called.
2. Fetches `sitemap_index.xml` from the site, parses all `<loc>` URLs.
3. Fetches each child sitemap and extracts page URLs.
4. Queues all URLs in a transient (`aisc_preload_queue`).
5. Schedules a WP-Cron event (`aisc_cache_preload_batch`) to process URLs in batches.
6. Each batch sends blocking HTTP requests to N URLs (default 5), warming the page cache.
7. Progress is tracked in a transient (`aisc_preload_progress`) and polled by the admin UI.

**Immediate preloading:** `preload_urls(array $urls)` — sends non-blocking `wp_remote_get()` requests. Used by `Cache_Invalidator` for targeted re-warming after selective purges.

**Constants:**

| Constant | Value |
|----------|-------|
| `CRON_HOOK` | `aisc_cache_preload_batch` |
| `PROGRESS_TRANSIENT` | `aisc_preload_progress` |
| `QUEUE_TRANSIENT` | `aisc_preload_queue` |

**Progress array:**
```php
array(
    'total'   => 42,    // total URLs to preload
    'done'    => 15,    // URLs completed so far
    'running' => true,  // whether preload is active
)
```

**Batch scheduling:** Each batch schedules the next one 2 seconds later. When the queue is empty, `running` is set to `false`.

---

### 4.7 Minifier

**File:** `class-minifier.php`  
**Role:** Inline CSS and JS minification via output buffer.

**How it works:**

1. On `template_redirect` (priority 1), starts an output buffer.
2. Buffer callback (`process_html`) processes the full HTML output:
   - **CSS:** Finds `<style>` blocks, runs `minify_css()` on their contents.
   - **JS:** Finds inline `<script>` blocks (no `src` attribute), runs `minify_js()`.
3. Returns the modified HTML.

**CSS minification** (`minify_css()` — static):

1. Protects `/*! ... */` license comments with `___AISC_LICENSE_N___` placeholders.
2. Removes normal `/* ... */` comments.
3. Collapses whitespace to single spaces.
4. Removes spaces around `{ } ; : , > ~ +`.
5. Removes trailing semicolons before `}`.
6. Restores license comments.

**JS minification** (`minify_js()` — static, conservative):

1. Protects `/*! ... */` license comments.
2. Protects string literals (single, double, template literals).
3. Removes `//` single-line comments.
4. Removes `/* ... */` multi-line comments.
5. Collapses whitespace and newlines.
6. Restores strings and license comments.

Does NOT perform AST-level transforms — safe for all JS.

**Skip conditions:**

- `data-no-minify` attribute on `<style>` or `<script>` → skipped.
- `type="application/ld+json"` scripts (JSON-LD schema) → skipped.
- Scripts with `src` attribute (external files) → skipped.
- HTML output < 100 bytes → skipped entirely.
- Another minification plugin detected → entire Minifier disabled.

---

### 4.8 Lazy_Loader

**File:** `class-lazy-loader.php`  
**Role:** Inject `loading="lazy"` attribute on images and iframes.

**How it works:**

Filters `the_content`, `post_thumbnail_html`, and `widget_text_content` at priority 99:

1. Removes `<noscript>` blocks from processing (restores them after).
2. Processes `<img>` tags:
   - Skips if `loading=` attribute already exists (prevents doubling).
   - Skips AMP images (`<amp-img>`).
   - Skips the first N images (above-the-fold, configurable via `cache_lazy_skip_count`).
   - Adds `loading="lazy"` to qualifying images.
3. Processes `<iframe>` tags:
   - Skips if `loading=` attribute already exists.
   - Adds `loading="lazy"`.

**Dual safety:**

1. **System-level:** `is_another_lazy_loader_active()` checks if another lazy-load solution exists and skips registration entirely.
2. **Per-element:** Each `<img>` / `<iframe>` is checked for existing `loading=` attribute before injection.

---

## 5. Drop-in Files

### advanced-cache.php

Installed to `wp-content/advanced-cache.php`. Loaded by WordPress **before** plugins when `WP_CACHE` is `true`.

**Purpose:** Serve cached pages without loading WordPress at all (fastest possible response).

**Flow:**

```
Browser request
  → wp-settings.php detects WP_CACHE=true
    → loads wp-content/advanced-cache.php
      → calls Page_Cache::serve_if_cached()
        → HIT: output cached HTML + exit (WordPress never loads)
        → MISS: continue normal WordPress boot
```

**Signature:** Contains `AI-SEO-Captain-Advanced-Cache` for ownership detection.

### object-cache.php

Installed to `wp-content/object-cache.php`. Replaces WordPress's default in-memory-only object cache.

**Purpose:** Persist DB query results to disk so they survive across requests.

**Signature:** Contains `AI-SEO-Captain-Object-Cache` for ownership detection.

**Safety:** The installer refuses to overwrite an existing drop-in from another plugin.

---

## 6. Admin UI & AJAX

### Buttons

| Button | Action | Description |
|--------|--------|-------------|
| **Purge All Cache** | `aisc_purge_cache` | Deletes all cached pages across the site. Every page will be regenerated on next visit. |
| **Preload Cache** | `aisc_preload_cache` | Crawls all sitemap URLs in the background to rebuild the cache, so visitors immediately get fast cached pages. |

### Status Dashboard

The admin page polls `aisc_cache_status` and displays:

- Cache enabled/disabled state
- Page cache file count and total size
- Object cache file count and total size
- Drop-in status (advanced-cache.php / object-cache.php — installed, ours, or foreign)
- WP_CACHE constant status
- .htaccess rules status
- Last purge timestamp
- Preload progress (total / done / running)

### Preload Progress Bar

When preloading is active, a progress bar is shown with `{done} / {total}` count. The admin JS polls the status endpoint every few seconds and updates the bar.

### AJAX Nonce

All AJAX calls use `aisc_cache_nonce` for CSRF protection.

---

## 7. WooCommerce Compatibility

| Feature | Implementation |
|---------|---------------|
| **Cart cookie detection** | `is_cacheable()` checks for `woocommerce_items_in_cart` and `woocommerce_cart_hash` cookies. If present → skip cache. |
| **Dynamic page exclusion** | When `cache_wc_exclude_cart` is on (default), `is_cart()`, `is_checkout()`, `is_account_page()` → skip cache. |
| **Stock change purge** | `Cache_Invalidator` hooks into `woocommerce_product_set_stock` and `woocommerce_variation_set_stock`. Purges product page + shop page + related URLs. |
| **Variation stock** | When a variation's stock changes, the parent product is purged. |

---

## 8. Conflict Detection

### Minifier — `is_another_minifier_active()`

Checked at `register_hooks()` time. If another minification plugin is active **with minification enabled**, the entire Minifier is disabled.

| Plugin | Detection | Option-Aware |
|--------|-----------|:------------:|
| Autoptimize | `defined('AUTOPTIMIZE_PLUGIN_VERSION')` | ✅ Checks `autoptimize_css` / `autoptimize_js` options |
| WP Rocket | `defined('WP_ROCKET_VERSION')` | ✅ Checks `wp_rocket_settings[minify_css/minify_js]` |
| LiteSpeed Cache | `defined('LSCWP_V')` | ✅ Checks `litespeed.conf.optm-css_min` / `optm-js_min` |
| W3 Total Cache | `defined('W3TC')` | ❌ Always skips |
| WP Super Minify | `defined('FLAVOR_FLAVOR_FILE')` | ❌ Always skips |
| Fast Velocity Minify | `defined('FVM_VERSION')` | ❌ Always skips |
| SG Optimizer | `class_exists('SiteGround_Optimizer\Minifier\Minifier')` | ❌ Always skips |

### Lazy_Loader — `is_another_lazy_loader_active()`

Checked at `register_hooks()` time. If another lazy-load solution is active, the entire Lazy_Loader is disabled.

| Plugin / Feature | Detection | Option-Aware |
|-----------------|-----------|:------------:|
| WordPress 5.5+ native | `wp_lazy_loading_enabled('img', 'the_content')` | ✅ Native API |
| WP Rocket | `defined('WP_ROCKET_VERSION')` | ✅ Checks `wp_rocket_settings[lazyload]` |
| a3 Lazy Load | `class_exists('A3_Lazy_Load')` | ❌ Always skips |
| Jetpack | `class_exists('Jetpack')` | ✅ Checks `Jetpack::is_module_active('lazy-images')` |
| LiteSpeed Cache | `defined('LSCWP_V')` | ✅ Checks `litespeed.conf.media-lazy` |
| Smush | `defined('WP_SMUSH_VERSION')` | ✅ Checks `Smush\Core\Modules\Lazy` class |

---

## 9. Safety & Backup System

### .htaccess Backups

- **Auto-backup:** Every `install_htaccess()` and `remove_htaccess()` call creates a backup first.
- **Location:** `wp-content/cache/ai-seo-captain/backups/htaccess_YYYY-MM-DD_HH-MM-SS.bak`
- **Rotation:** Maximum 5 backups. Oldest auto-deleted.
- **Restore:** One-click restore from admin UI via `ajax_restore_htaccess()`.
- **Deactivation:** Uses `remove_htaccess_without_backup()` to clean up without creating orphan backups.

### wp-config.php Backups

- **Auto-backup:** Every `set_wp_cache_constant()` call creates a backup first.
- **Location:** `wp-content/cache/ai-seo-captain/backups/wp-config_YYYY-MM-DD_HH-MM-SS.bak`
- **Rotation:** Maximum 5 backups.

### Backup Directory Protection

```
backups/
├── index.php       ← "<?php // Silence is golden." (prevents directory listing)
└── .htaccess       ← "Deny from all" (blocks direct HTTP access)
```

### Drop-in Ownership

Both drop-in files contain unique signatures:
- `advanced-cache.php` → `AI-SEO-Captain-Advanced-Cache`
- `object-cache.php` → `AI-SEO-Captain-Object-Cache`

The plugin will NOT remove or overwrite a drop-in that belongs to another plugin.

---

## 10. Cache Flow Diagrams

### Page Request (with advanced-cache.php)

```
HTTP GET /about/
  │
  ├─ wp-settings.php → WP_CACHE=true → load advanced-cache.php
  │   │
  │   ├─ Page_Cache::serve_if_cached('/about/')
  │   │   ├─ hash = md5('/about/')
  │   │   ├─ file exists? → check TTL from .meta
  │   │   │   ├─ NOT expired → output HTML (+ gzip) → EXIT ✓ [HIT]
  │   │   │   └─ expired → delete stale files → continue
  │   │   └─ file not found → continue
  │   │
  │   └─ Continue normal WordPress boot
  │
  ├─ WordPress loads, runs template_redirect
  │   ├─ Page_Cache::start_capture() → ob_start()
  │   ├─ Minifier::start_output_buffer() → ob_start() (if enabled)
  │   └─ GZIP ob_start('ob_gzhandler') (if enabled)
  │
  ├─ WordPress renders the page
  │
  └─ Buffer flush
      ├─ Minifier::process_html() → minify inline CSS/JS
      ├─ Page_Cache::end_capture() → write .html + .meta to disk
      └─ Output to browser [MISS]
```

### Content Change → Smart Invalidation

```
save_post(42) → Cache_Invalidator::on_post_save()
  │
  ├─ get_related_urls(42)
  │   ├─ /my-post/           (permalink)
  │   ├─ /                   (homepage)
  │   ├─ /category/news/     (category archive)
  │   ├─ /tag/update/        (tag archive)
  │   ├─ /author/admin/      (author archive)
  │   ├─ /page/2/ ... /page/5/ (pagination)
  │   └─ /feed/              (RSS feed)
  │
  ├─ Purge each URL from page cache
  │
  └─ auto_preload? → preload_urls() → non-blocking GET to each URL
```

---

## 11. Exclusion Rules

### URL Exclusion (`cache_exclude_urls`)

One pattern per line. Matched with `strpos()` against the request URI.

```
/my-account/
/checkout/
/wp-json/
/members-only/
```

### Cookie Exclusion (`cache_exclude_cookies`)

One cookie name per line. If the visitor has this cookie, caching is bypassed.

```
logged_in_user
membership_level
```

### User-Agent Exclusion (`cache_exclude_useragents`)

One substring per line. Matched case-insensitively against the `HTTP_USER_AGENT` header.

```
bot
crawler
monitor
```

### Built-in Exclusions (always active)

- Logged-in users (WordPress `logged_in_*` cookies)
- Admin pages (`is_admin()`)
- POST/PUT/DELETE requests (only GET is cached)
- WP-Cron requests
- REST API requests
- XMLRPC requests
- Search result pages
- 404 pages
- Pages with `DONOTCACHEPAGE` constant
- WooCommerce cart/checkout/my-account (when `cache_wc_exclude_cart` is on)

---

## 12. Deactivation & Cleanup

`Cache_Manager::deactivate()` is called on plugin deactivation. It performs:

1. **Remove advanced-cache.php** drop-in and unset `WP_CACHE` in wp-config.php.
2. **Remove object-cache.php** drop-in (only if it belongs to us).
3. **Remove .htaccess rules** without creating a backup (since the backup dir is about to be deleted).
4. **Delete entire cache directory:** `wp-content/cache/ai-seo-captain/` and all contents (pages, objects, backups).

The cleanup uses `RecursiveIteratorIterator` to delete files bottom-up, then removes empty directories.

---

## 13. Testing

**Test file:** `tests/unit/CacheTest.php`

**Coverage:** 47 tests covering:

- **Minifier CSS** — comment removal, license comment preservation, whitespace collapse, trailing semicolons, empty input, complex selectors, media queries, `@font-face`
- **Minifier JS** — comment removal, string preservation, template literals, license comments, empty input
- **Minifier HTML** — `process_html()` integration, `data-no-minify` skip, JSON-LD skip, short HTML skip
- **Lazy_Loader** — loading attribute injection, existing attribute skip, above-the-fold skip, iframe support, noscript block protection, AMP skip, empty input
- **Browser_Cache** — `.htaccess` rule generation, GZIP toggle, backup date parsing
- **Conflict detection** — Autoptimize detection, no-conflict pass-through

**Run command:**
```bash
php -d extension=php_zip.dll vendor/bin/phpunit --testsuite Unit
```

**Stubs:** `tests/stubs/wp-stubs.php` provides `get_option()`, `is_admin()`, `add_action()`, `add_filter()`, `WP_CONTENT_DIR`, and other WordPress functions needed for isolated unit testing.

---

## 14. What Is NOT Included (v1)

| Feature | Status |
|---------|--------|
| CDN purge API (Cloudflare, BunnyCDN, etc.) | Coming Soon (banner shown in UI) |
| Redis / Memcached adapters | Not planned — file-based only |
| Image optimization / WebP conversion | Not included |
| Critical CSS extraction | Not included |
| Database optimization / cleanup | Not included |
| External CSS/JS file minification | Not included (inline only) |
