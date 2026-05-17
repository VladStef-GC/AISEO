# Cache Module — Build Plan

> **Target plugin:** AI SEO Captain v1.0.0-beta  
> **Branch:** `Dev-env`  
> **Priority:** Build incrementally — 1-2 files at a time, test after each.  
> **CDN:** NOT included in v1 — show "Coming Soon" banner in settings.

---

## 1. Module Overview

A full page-cache + browser-cache + object-cache system integrated into AI SEO Captain.  
Built as standalone classes inside `/includes/cache/` with its own admin view, CSS, and JS files.  
Follows the exact same architectural patterns already used in the plugin (Settings, Admin, Frontend).

### What we build (v1)

| Feature | Description |
|---------|-------------|
| **Page cache** | File-based full-page HTML cache via `advanced-cache.php` drop-in |
| **Smart invalidation** | Purge the right URLs when content changes — posts, terms, archives, homepage, sitemap |
| **Browser cache headers** | `Cache-Control`, `Expires`, `ETag` on static assets and cached pages |
| **GZIP compression** | PHP-level gzip for hosts without server config access |
| **Object cache** | File-based `object-cache.php` drop-in for transients and DB query caching |
| **Cache preloading** | Crawl sitemap URLs after purge to warm the cache |
| **CSS/JS minification** | Inline minification of enqueued styles and scripts |
| **Lazy loading** | Native `loading="lazy"` injection for images and iframes |
| **Exclusion rules** | Skip logged-in users, WooCommerce cart/checkout/my-account, REST API, admin-ajax |
| **WooCommerce compat** | Cart cookie detection, product stock change purge, exclude dynamic pages |
| **Admin UI** | New "Cache" admin page under SEO Captain menu with status, controls, settings |
| **CDN placeholder** | "CDN Integration — Coming Soon" banner in settings |

### What we do NOT build (v1)

- CDN purge API integration (Cloudflare, BunnyCDN, etc.)
- Redis / Memcached adapters (file-based only)
- Image optimization / WebP conversion
- Critical CSS extraction
- Database optimization / cleanup

---

## 2. File Structure

```
ai-seo-captain/
├── includes/
│   ├── cache/
│   │   ├── class-cache-manager.php      ← Main orchestrator: enable/disable, purge, preload
│   │   ├── class-page-cache.php         ← File-based page cache: write, serve, purge
│   │   ├── class-cache-invalidator.php  ← Smart invalidation: map post→URLs, hook into save/delete/term edits
│   │   ├── class-browser-cache.php      ← HTTP headers: Cache-Control, Expires, ETag
│   │   ├── class-object-cache.php       ← File-based object cache logic + drop-in installer
│   │   ├── class-cache-preloader.php    ← Sitemap crawler to warm cache after purge
│   │   ├── class-minifier.php           ← CSS/JS minification via output buffer
│   │   └── class-lazy-loader.php        ← Image/iframe lazy loading injection
│   ├── admin/
│   │   └── view-cache.php              ← Cache admin page template
│   └── class-plugin.php                ← UPDATE: wire Cache_Manager into boot()
│   └── class-settings.php              ← UPDATE: add cache defaults + feature flag
│
├── assets/
│   ├── css/
│   │   └── page-cache.css              ← Cache admin page styles
│   └── js/
│       └── page-cache.js               ← Cache admin page interactions
│
├── advanced-cache.php                   ← Drop-in: intercepts requests before WP loads (installed to wp-content/)
├── object-cache.php                     ← Drop-in: file-based object cache (installed to wp-content/)
```

---

## 3. Class-by-Class Specifications

### 3.1 `class-cache-manager.php`

**Namespace:** `AI_SEO_Captain\Cache`  
**Role:** Central orchestrator — coordinates all cache subsystems.

```php
namespace AI_SEO_Captain\Cache;

class Cache_Manager {
    private Settings_ref $settings;  // Reference to AI_SEO_Captain\Settings
    private Page_Cache $page_cache;
    private Cache_Invalidator $invalidator;
    private Browser_Cache $browser_cache;
    private Object_Cache $object_cache;
    private Cache_Preloader $preloader;
    private Minifier $minifier;
    private Lazy_Loader $lazy_loader;

    public function __construct(\AI_SEO_Captain\Settings $settings) {}

    // Boot: register all hooks if cache is enabled in settings
    public function boot(): void {}

    // Master purge: clear everything
    public function purge_all(): void {}

    // Selective purge: clear specific URL(s)
    public function purge_url(string $url): void {}
    public function purge_urls(array $urls): void {}

    // Preload after purge
    public function preload(): void {}

    // Install/remove drop-in files
    public function install_dropin(string $type): bool {}   // 'advanced-cache' or 'object-cache'
    public function remove_dropin(string $type): bool {}

    // Status checks
    public function get_status(): array {}  // Returns cache stats: total files, size, last purge, drop-in status

    // Admin bar: "Purge Cache" button
    public function add_admin_bar_purge(WP_Admin_Bar $admin_bar): void {}
}
```

**Hooks to register in `boot()`:**
- `admin_bar_menu` → add "Purge Cache" button
- `wp_ajax_aisc_purge_cache` → AJAX handler for admin purge
- `wp_ajax_aisc_preload_cache` → AJAX handler for preload trigger
- `wp_ajax_aisc_cache_status` → AJAX handler for status polling
- Delegates invalidation hooks to `Cache_Invalidator`
- Delegates browser headers to `Browser_Cache`
- Delegates minification/lazy-load output buffer hooks to respective classes

---

### 3.2 `class-page-cache.php`

**Role:** Write and serve full-page HTML cache files.

```php
class Page_Cache {
    private string $cache_dir;  // wp-content/cache/ai-seo-captain/pages/

    // Start output buffering on template_redirect (priority 0)
    public function start_capture(): void {}

    // End capture: write HTML to file (callback from ob_start)
    public function end_capture(string $html): string {}

    // Serve cached file if it exists (called from advanced-cache.php, before WP loads)
    public static function serve_if_cached(string $request_uri): bool {}

    // Purge: delete a specific cached file by URL
    public function purge(string $url): void {}

    // Purge all cached files
    public function purge_all(): void {}

    // Get cache directory size and file count
    public function get_stats(): array {}

    // Should this request be cached?
    private function is_cacheable(): bool {}
}
```

**`is_cacheable()` must return FALSE when:**
- User is logged in (`is_user_logged_in()`)
- POST request
- Query string present (configurable)
- WooCommerce cart has items (`woocommerce_items_in_cart` cookie)
- Request is `wp-admin`, `wp-login`, `wp-cron`, `xmlrpc`, `wp-json`
- Page is in exclusion list (settings)
- `DONOTCACHEPAGE` constant is defined
- Request is a 404
- Response has `no-cache` headers

**Cache file naming:**
- URL → MD5 hash → `{cache_dir}/{hash_prefix}/{hash}.html`
- Also store a `.meta` file alongside with headers, timestamp, URL for debugging

---

### 3.3 `class-cache-invalidator.php`

**Role:** The brain — knows which URLs to purge when content changes. This is the most critical class.

```php
class Cache_Invalidator {
    private Page_Cache $page_cache;
    private Cache_Preloader $preloader;
    private bool $auto_preload;

    public function register_hooks(): void {}

    // Core: given a post ID, return all URLs that must be purged
    public function get_related_urls(int $post_id): array {}

    // Hook handlers
    public function on_post_save(int $post_id, \WP_Post $post): void {}
    public function on_post_delete(int $post_id): void {}
    public function on_comment_change(int $comment_id): void {}
    public function on_term_edit(int $term_id, int $tt_id, string $taxonomy): void {}
    public function on_theme_switch(): void {}       // Purge all
    public function on_widget_update(): void {}      // Purge all
    public function on_permalink_change(): void {}   // Purge all
    public function on_nav_menu_update(): void {}     // Purge all
}
```

**Hooks to register:**
| WordPress Hook | Action |
|----------------|--------|
| `save_post` (priority 99, after SEO Captain's own save at 50) | Purge post URL + related |
| `delete_post`, `trashed_post` | Purge post URL + related |
| `transition_post_status` | Purge on publish/unpublish |
| `comment_post`, `edit_comment`, `delete_comment`, `wp_set_comment_status` | Purge the parent post |
| `edited_term`, `delete_term`, `created_term` | Purge term archive URL |
| `switch_theme` | Purge all |
| `update_option_sidebars_widgets` | Purge all |
| `wp_update_nav_menu` | Purge all |
| `permalink_structure_changed` | Purge all |
| `woocommerce_product_set_stock` | Purge product page |
| `woocommerce_variation_set_stock` | Purge parent product |

**`get_related_urls()` must return:**
1. The post permalink itself
2. The homepage (`home_url('/')`)
3. The post's category archive pages (all categories assigned)
4. The post's tag archive pages (all tags assigned)
5. The post's author archive page
6. The post type archive page (if applicable)
7. Pagination pages for affected archives (page 2, 3, etc. — use `get_option('posts_per_page')` to calculate)
8. The RSS feed URLs (`/feed/`, category feeds)
9. The sitemap URLs that include this post

---

### 3.4 `class-browser-cache.php`

**Role:** Set HTTP headers for optimal browser caching.

```php
class Browser_Cache {
    // Hook into 'send_headers' for HTML pages
    public function set_page_headers(): void {}

    // Generate .htaccess rules for static assets (Apache only)
    public function generate_htaccess_rules(): string {}

    // Install/remove rules from .htaccess
    public function install_htaccess(): bool {}
    public function remove_htaccess(): bool {}
}
```

**Headers to set:**
- `Cache-Control: public, max-age=86400` for cached pages (configurable TTL)
- `Cache-Control: public, max-age=31536000, immutable` for versioned static assets
- `ETag` based on file modification time
- `Vary: Accept-Encoding` when GZIP is enabled
- `X-Cache: HIT` or `X-Cache: MISS` for debugging

**`.htaccess` rules (Apache):**
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
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml
</IfModule>
# END AI SEO Captain Cache
```

---

### 3.5 `class-object-cache.php`

**Role:** File-based WordPress object cache with drop-in management.

```php
class Object_Cache {
    private string $cache_dir;  // wp-content/cache/ai-seo-captain/objects/

    // Generate the object-cache.php drop-in content
    public function generate_dropin(): string {}

    // Install the drop-in to wp-content/object-cache.php
    public function install(): bool {}

    // Remove the drop-in
    public function remove(): bool {}

    // Check if our drop-in is active (vs another plugin's)
    public function is_ours(): bool {}

    // Flush all object cache files
    public function flush(): void {}

    // Get stats
    public function get_stats(): array {}
}
```

**Drop-in behavior:**
- Implements `wp_cache_get()`, `wp_cache_set()`, `wp_cache_delete()`, `wp_cache_flush()`, `wp_cache_add()`, `wp_cache_replace()`
- Stores serialized data in `{cache_dir}/{group}/{md5_key}.php`
- TTL support: files older than configured TTL are treated as expired
- Must NOT overwrite an existing `object-cache.php` from another plugin (Redis, Memcached) — detect and show warning

---

### 3.6 `class-cache-preloader.php`

**Role:** Warm the cache by crawling sitemap URLs.

```php
class Cache_Preloader {
    // Crawl all URLs from the sitemap to warm page cache
    public function preload_all(): void {}

    // Crawl specific URLs (after targeted purge)
    public function preload_urls(array $urls): void {}

    // Get preload progress (for admin UI polling)
    public function get_progress(): array {}

    // Background preload via wp-cron
    public function schedule_preload(): void {}
    public function run_scheduled_preload(): void {}
}
```

**Implementation:**
- Read sitemap from `AI_SEO_Captain\Sitemap` (already exists in the plugin) — use `$sitemap->get_all_urls()` or parse `/sitemap_index.xml`
- Use `wp_remote_get()` with timeout=5, blocking=false for async warmup
- Process in batches of 5 URLs per cron tick to avoid timeout
- Store progress in a transient: `aisc_preload_progress` = `{total, done, running}`
- Admin UI can poll this via AJAX

---

### 3.7 `class-minifier.php`

**Role:** Minify CSS and JS output.

```php
class Minifier {
    // Hook into wp_print_styles / wp_print_scripts or use output buffer
    public function register_hooks(): void {}

    // Minify CSS string (strip comments, whitespace, collapse rules)
    public static function minify_css(string $css): string {}

    // Minify JS string (strip comments, collapse whitespace — conservative)
    public static function minify_js(string $js): string {}

    // Combine multiple CSS files into one (optional, configurable)
    public function combine_styles(): void {}

    // Output buffer callback: minify inline <style> and <script> blocks
    public function process_html(string $html): string {}
}
```

**Safety rules:**
- Never minify `wp-admin` assets
- Never minify scripts with `data-no-minify` attribute
- Never remove `/*! ... */` license comments
- CSS minification: remove comments, collapse whitespace, remove last semicolon in blocks
- JS minification: conservative — only strip `//` and `/* */` comments, collapse whitespace runs. Do NOT attempt AST-level transforms.
- Offer per-asset exclusion list in settings

---

### 3.8 `class-lazy-loader.php`

**Role:** Add native lazy loading to images and iframes.

```php
class Lazy_Loader {
    // Filter: 'the_content', 'post_thumbnail_html', 'widget_text_content'
    public function add_lazy_attributes(string $html): string {}

    // Skip first N images (above-the-fold content should NOT be lazy)
    private int $skip_count;  // Default: 2 (configurable in settings)
}
```

**Rules:**
- Add `loading="lazy"` to `<img>` tags that don't already have a `loading` attribute
- Add `loading="lazy"` to `<iframe>` tags (YouTube embeds, etc.)
- Skip the first N images (configurable, default 2) — these are likely above the fold
- Never lazy-load images inside `<noscript>` blocks
- Never lazy-load AMP images (`<amp-img>`)

---

## 4. Drop-in: `advanced-cache.php`

This file lives in `wp-content/advanced-cache.php` and is loaded by WordPress very early (before plugins load) when `WP_CACHE` is `true` in `wp-config.php`.

**What it does:**
1. Check if the request is cacheable (not logged in, not POST, not admin, etc.)
2. Build the cache file path from `$_SERVER['REQUEST_URI']`
3. If file exists and not expired → serve it directly and `exit()` — WordPress never loads
4. If no cache file → do nothing — WordPress loads normally, and `Page_Cache::start_capture()` will cache the output

**Installation:**
- `Cache_Manager::install_dropin('advanced-cache')` copies the file to `wp-content/`
- Also adds `define('WP_CACHE', true);` to `wp-config.php` (after the opening `<?php` tag)
- On deactivation / cache disable: removes the drop-in and the `WP_CACHE` constant

**Security:**
- The drop-in must check that the AI SEO Captain plugin is active before serving
- Include an `ABSPATH` check at the top
- Never serve cached pages to logged-in users (check cookies for `wordpress_logged_in_*`)

---

## 5. Settings Integration

### 5.1 New options (add to `Settings::defaults()`)

```php
// Cache module defaults
'cache_enabled'              => false,    // Master switch
'cache_page_enabled'         => true,     // Page cache on/off
'cache_page_ttl'             => 86400,    // Page cache TTL in seconds (24h default)
'cache_browser_enabled'      => true,     // Browser cache headers
'cache_gzip_enabled'         => true,     // GZIP compression
'cache_object_enabled'       => false,    // Object cache (off by default — needs drop-in)
'cache_preload_enabled'      => true,     // Auto-preload after purge
'cache_preload_batch_size'   => 5,        // URLs per cron tick
'cache_minify_css'           => false,    // CSS minification
'cache_minify_js'            => false,    // JS minification
'cache_minify_html'          => false,    // HTML minification (strip whitespace)
'cache_lazy_load'            => true,     // Lazy loading
'cache_lazy_skip_count'      => 2,        // Skip first N images
'cache_exclude_urls'         => '',       // Newline-separated URL patterns to never cache
'cache_exclude_cookies'      => '',       // Cookie names that prevent caching
'cache_exclude_useragents'   => '',       // User-agent patterns to exclude
'cache_query_string_cache'   => false,    // Cache pages with query strings
'cache_wc_exclude_cart'      => true,     // Auto-exclude WooCommerce cart/checkout
```

### 5.2 Feature flag

Add to `Settings::FEATURE_FLAGS`:
```php
'cache' => 'Page Cache & Performance',
```

### 5.3 New admin menu item

Add in `Admin::register_menus()`:
```php
add_submenu_page(
    'ai-seo-captain',
    __('Cache', 'ai-seo-captain'),
    __('Cache', 'ai-seo-captain'),
    'manage_options',
    'ai-seo-captain-cache',
    array($this, 'render_cache_page')
);
```

---

## 6. Admin UI: `view-cache.php`

### Layout (follows existing plugin accordion pattern)

**Top section — Status dashboard:**
- Cache status: ACTIVE / INACTIVE (with green/red indicator)
- Total cached pages: N files (X MB)
- Object cache: Active / Inactive
- Last full purge: timestamp
- Drop-in status: advanced-cache.php ✓/✗, object-cache.php ✓/✗
- WP_CACHE constant: ✓/✗

**Action buttons row:**
- 🗑 **Purge All Cache** — AJAX, confirmation dialog
- 🔄 **Preload Cache** — AJAX, shows progress bar
- ⚙️ **Test Cache** — fetches homepage, shows HIT/MISS header

**Accordion sections:**

1. **Page Cache** — master toggle, TTL input, GZIP toggle, query string toggle
2. **Browser Cache** — toggle, .htaccess status, install/remove button
3. **Object Cache** — toggle, drop-in install/remove, stats, conflict warning
4. **Minification** — CSS toggle, JS toggle, HTML toggle, exclusion textarea
5. **Lazy Loading** — toggle, skip count input
6. **Exclusions** — URL patterns textarea, cookie names textarea, user-agent patterns textarea
7. **WooCommerce** — auto-exclude cart/checkout toggle (only shown if WC active)
8. **Preloading** — toggle, batch size input, manual preload button
9. **CDN Integration** — `render_banner('is-info', 'CDN Integration', 'Coming soon in a future update. Currently supports local file-based caching only.')`

**CSS:** `page-cache.css` — reuse existing `.ai-seo-accordion-*`, `.aisc-toggle`, `.ai-seo-captain-notice` classes from `admin-common.css`  
**JS:** `page-cache.js` — purge/preload AJAX handlers, status polling, progress bar animation

---

## 7. Integration Points with Existing Plugin

| Existing Module | Integration |
|----------------|-------------|
| `class-plugin.php` | Add `Cache_Manager` to `boot()`, wire it after `Sitemap` (needs sitemap for preloading) |
| `class-settings.php` | Add cache defaults to `defaults()`, add `'cache'` to `FEATURE_FLAGS`, add validation in `sanitize()` |
| `class-admin.php` | Add cache menu item in `register_menus()`, add `render_cache_page()` method, enqueue `page-cache.css` + `page-cache.js` |
| `class-sitemap.php` | Expose `get_all_urls(): array` method (or reuse sitemap XML parsing) for preloader |
| `class-indexnow.php` | After cache purge on save_post, IndexNow still fires (no conflict — cache purge at priority 99, IndexNow at its own priority) |
| `class-frontend.php` | No changes — SEO output happens before page cache captures HTML, so cached pages include SEO meta |
| `class-woocommerce-integration.php` | Add `is_wc_dynamic_page(): bool` helper for cache exclusions |
| `advanced-cache.php` | New file in plugin root, installed to `wp-content/` by `Cache_Manager` |
| `object-cache.php` | New file in plugin root, installed to `wp-content/` by `Cache_Manager` |

---

## 8. WooCommerce Compatibility

**Auto-excluded pages (when WC detected + enabled):**
- `/cart/`
- `/checkout/`
- `/my-account/` and all sub-pages
- Any page with `[woocommerce_cart]`, `[woocommerce_checkout]`, `[woocommerce_my_account]` shortcodes

**Cookie detection:**
- If `woocommerce_items_in_cart` cookie is present → do NOT serve cached page (cart badge would be wrong)
- If `woocommerce_cart_hash` cookie is present → same

**Stock/price changes:**
- `woocommerce_product_set_stock` → purge product page + shop page + category archives
- `woocommerce_variation_set_stock` → purge parent product page
- Price/sale changes trigger `save_post` for the product → handled by normal invalidation

---

## 9. Cache Directory Structure

```
wp-content/
├── cache/
│   └── ai-seo-captain/
│       ├── pages/           ← Full-page HTML cache files
│       │   ├── ab/
│       │   │   └── ab3f...c7.html
│       │   │   └── ab3f...c7.meta
│       │   └── ...
│       └── objects/          ← Object cache files (if enabled)
│           ├── default/
│           │   └── {key_hash}.php
│           ├── transient/
│           └── ...
├── advanced-cache.php        ← Drop-in (installed by plugin)
└── object-cache.php          ← Drop-in (installed by plugin, optional)
```

**Cleanup on plugin deactivation:**
- Remove `advanced-cache.php` from `wp-content/`
- Remove `object-cache.php` from `wp-content/` (only if ours)
- Remove `WP_CACHE` constant from `wp-config.php`
- Delete `wp-content/cache/ai-seo-captain/` directory
- Remove `.htaccess` rules (between `# BEGIN/END AI SEO Captain Cache` markers)

---

## 10. Build Order (incremental)

Each step is a single commit. Test after each.

| Step | Files | What |
|------|-------|------|
| 1 | `class-settings.php`, `view-settings.php` | Add cache settings defaults, feature flag, settings UI section |
| 2 | `class-page-cache.php` | File-based page cache: write, serve, purge, stats |
| 3 | `class-cache-invalidator.php` | Smart invalidation with all WordPress hooks |
| 4 | `class-cache-manager.php`, `class-plugin.php` | Orchestrator + wire into plugin boot |
| 5 | `advanced-cache.php` | Drop-in file generation + wp-config.php WP_CACHE management |
| 6 | `class-browser-cache.php` | HTTP headers + .htaccess generation |
| 7 | `class-cache-preloader.php` | Sitemap crawling + cron batching |
| 8 | `class-object-cache.php` | File-based object cache + drop-in |
| 9 | `class-minifier.php` | CSS/JS/HTML minification |
| 10 | `class-lazy-loader.php` | Image/iframe lazy loading |
| 11 | `view-cache.php`, `page-cache.css`, `page-cache.js`, `class-admin.php` | Admin page UI |
| 12 | WooCommerce compat | Exclusion rules, cookie detection, stock hooks |
| 13 | Unit tests | Cache logic, invalidation URL mapping, minification, lazy loading |
| 14 | Integration tests | Full flow: save post → cache purge → preload → serve cached |

---

## 11. Testing Strategy

### Unit Tests (local, 100% coverage)

```
tests/Unit/Cache/
├── PageCacheTest.php           ← Write/read/purge cache files
├── CacheInvalidatorTest.php    ← Post ID → correct URLs resolved
├── BrowserCacheTest.php        ← Header generation, .htaccess content
├── MinifierTest.php            ← CSS/JS minification output correctness
├── LazyLoaderTest.php          ← HTML transformation, skip count
├── CachePreloaderTest.php      ← URL list generation from sitemap
└── ObjectCacheTest.php         ← Get/set/delete/flush/TTL
```

### Manual Integration Tests (XAMPP)

1. **Page cache write/serve:** Visit a page → check `wp-content/cache/ai-seo-captain/pages/` for HTML file → reload → check `X-Cache: HIT` header
2. **Invalidation:** Edit a post → verify its cache file is deleted → verify homepage cache is also deleted → verify category archive cache is deleted
3. **WooCommerce:** Add item to cart → verify cached page is NOT served (cookie detection)
4. **Preload:** Click preload → verify cache files appear for all sitemap URLs
5. **Minification:** Enable CSS minification → view source → confirm whitespace removed
6. **Lazy loading:** View a post with 5+ images → verify first 2 have NO `loading` attribute, rest have `loading="lazy"`
7. **Browser headers:** Open DevTools → Network → check response headers on cached page
8. **GZIP:** DevTools → Network → check `Content-Encoding: gzip` on responses
9. **Exclusions:** Add URL pattern → verify that URL is never cached
10. **Drop-in management:** Enable/disable object cache → verify `wp-content/object-cache.php` appears/disappears

---

## 12. Security Considerations

- Cache files must NOT be served for requests with `wordpress_logged_in_*` cookies
- Cache directory must have an `index.php` with `<?php // Silence is golden.` to prevent directory listing
- `.meta` files must NOT expose server paths or sensitive headers
- `wp-config.php` editing must use safe string replacement (find existing `WP_CACHE` or insert after `<?php`)
- Drop-in files must verify `ABSPATH` is defined
- Admin AJAX handlers must check `current_user_can('manage_options')` and nonce

---

## 13. Key Constants

```php
// In the main plugin file or class-cache-manager.php
define('AISC_CACHE_DIR', WP_CONTENT_DIR . '/cache/ai-seo-captain/');
define('AISC_PAGE_CACHE_DIR', AISC_CACHE_DIR . 'pages/');
define('AISC_OBJECT_CACHE_DIR', AISC_CACHE_DIR . 'objects/');
```

---

## 14. Admin Bar Integration

When cache is enabled, add a top-level "SEO Captain Cache" node to the admin bar:
- 🗑 **Purge This Page** (on singular views — purges current URL only)
- 🗑 **Purge All Cache** (purges everything)
- Appears for `manage_options` capability only
- Uses the same AJAX endpoint as the admin page purge button

---

## 15. Notes for the Build Session

1. **Autoloading:** The existing PSR-4 autoloader in `includes/autoload.php` maps `AI_SEO_Captain\ClassName` → `includes/class-classname.php`. For the `Cache` sub-namespace, update it to also map `AI_SEO_Captain\Cache\ClassName` → `includes/cache/class-classname.php`.
2. **The `advanced-cache.php` drop-in is the trickiest part.** It runs before WordPress loads, so it cannot use any WP functions. It must be standalone PHP that reads cookies and files. Keep it minimal (<100 lines).
3. **`wp-config.php` modification** is sensitive. Always back up before editing. Use `file_get_contents` + `str_replace` + `file_put_contents` with `LOCK_EX`.
4. **Do NOT minify admin assets** — only frontend output.
5. **Page cache must preserve SEO output.** Since `Frontend::render_*` hooks run during normal page load (before output buffer captures), the cached HTML already contains all SEO meta tags, schema, OG tags. No special handling needed.
6. **The plugin's existing `save_post` hook** (in `class-plugin.php`, priority 50) handles content indexing. Cache invalidation should fire at **priority 99** — after all other save_post processing is done.
7. **Use `render_banner()`** from `Admin` class for all notices in the cache admin page — maintains visual consistency.
8. **Use `aisc-toggle`** class for all checkboxes in the cache settings — slider toggles, not checkboxes.
9. **Cache page in admin** should follow the exact same pattern as other admin pages: logo at top, accordion sections, form-table layout inside sections.
