# AI SEO Captain — Code Audit Report

**Date**: May 18, 2026  
**Scope**: Full plugin code (excluding Cache system)  
**Method**: MD documentation cross-referenced against actual source code

---

## 1. VERSION & NAMING INCONSISTENCIES

| # | Issue | Location | Severity |
|---|-------|----------|----------|
| 1 | **Version mismatch**: Plugin header + `AI_SEO_KEEPER_VERSION` constant = `'1.0.0-beta'`, but MD docs reference v1.3.1 | `ai-seo-captain.php` | Medium |
| 2 | **Legacy constant prefix**: Constants use `AI_SEO_KEEPER_*` (old name) while namespace is `AI_SEO_Captain` | `ai-seo-captain.php` | Low (cosmetic) |

---

## 2. BUGS (Confirmed)

| # | Bug | Location | Severity |
|---|-----|----------|----------|
| 3 | **Index button stays disabled after success**: On indexing completion, `btn.prop('disabled', true)` is set at start but never reversed on success. Text stays "Indexing..." — never updated to "Re-Index Site". Only the error path re-enables the button. | `assets/js/page-setup-wizard.js` L632-663 | **High** (user-reported, UX-breaking) |
| 4 | **Deprecated Google Sitemap Ping**: Uses `google.com/ping?sitemap=` which Google deprecated (no longer processes pings). Cron job runs but achieves nothing. | `includes/class-cron-manager.php` L245 | Medium |

---

## 3. UNINSTALL / CLEANUP GAPS

| # | Issue | Location | Severity |
|---|-------|----------|----------|
| 5 | **`uninstall.php` missing 2 post meta keys**: `_ai_seo_captain_keywords` and `_ai_seo_captain_exclude_sitemap` are written by the plugin but NOT deleted in uninstall.php. (They ARE cleaned in `handle_clear_seo_data`.) | `uninstall.php` L23-41 | Medium |
| 6 | **Dynamic video meta keys orphaned**: `_ai_seo_captain_video_title_{hash}` and `_ai_seo_captain_video_desc_{hash}` are never cleaned by uninstall or clear-data — requires wildcard `LIKE` query. | `class-admin-ajax.php` L971-972, `class-admin.php` L2231-2232, `uninstall.php` | Medium |
| 7 | **Meta_Keys class incomplete**: `Meta_Keys::all_post_meta_keys()` does NOT include `_ai_seo_captain_keywords` or `_ai_seo_captain_exclude_sitemap`, making it unreliable as a single source of truth. | `includes/class-meta-keys.php` L50-69 | Low-Medium |

---

## 4. DOCUMENTATION vs CODE MISMATCHES

| # | Issue | Docs Say | Code Actually Uses |
|---|-------|----------|-------------------|
| 8 | Term meta key naming | `_ai_seo_captain_term_title` (PROJECT-HANDOFF.md, PLAN.md, CODE-MAP.md) | `_ai_seo_captain_seo_title` (code, uninstall, taxonomy class) |
| 9 | Version number | 1.3.1 (docs) | 1.0.0-beta (code) |

---

## 5. ARCHITECTURAL CONCERNS

| # | Issue | Location | Severity |
|---|-------|----------|----------|
| 10 | **`schedule_all()` called every page load**: Plugin singleton calls `$this->cron_manager->schedule_all()` on every `plugins_loaded`. While safe (WordPress skips if already scheduled), it's unnecessary DB reads on every request. | `includes/class-plugin.php` | Low |
| 11 | **Nested `plugins_loaded` hook**: WooCommerce boot adds an action to `plugins_loaded` from within a `plugins_loaded` callback. Depending on priority, the inner hook may never fire (or fires unpredictably if WP hasn't finished the hook loop). | `includes/class-plugin.php` | Medium |
| 12 | **`sync()` uses TRUNCATE + re-insert**: For large sites (1000+ posts), this causes a window where the index is empty. During that window, any frontend queries to the index return nothing. No transaction wrapping. | `includes/class-content-indexer.php` L1157-1235 | Medium |
| 13 | **`sync()` loads ALL posts into memory**: `posts_per_page => -1` without `fields => 'ids'` loads full WP_Post objects. On sites with 10k+ posts, this can exceed PHP memory. | `includes/class-content-indexer.php` L1175-1183 | Medium-High |
| 14 | **`verify_index_integrity()` does N+1 queries**: Loops through all indexed IDs calling `get_post()` individually, then loops all posts calling individual `SELECT ... LIMIT 1` for each. On 5k+ posts this is very slow. | `includes/class-content-indexer.php` L1300-1370 | Low (cron only) |

---

## 6. SECURITY OBSERVATIONS

| # | Observation | Verdict |
|---|-------------|---------|
| 15 | **Google API key in URL query parameter**: `call_google()` passes `?key=...` in the URL. If server access logs or proxies log full URLs, the key is exposed. Not exploitable remotely but a best-practice gap. | Low risk |
| 16 | All AJAX handlers use `check_ajax_referer()` + `current_user_can()` | ✅ Good |
| 17 | All user input is sanitized with `sanitize_text_field()` / `sanitize_textarea_field()` | ✅ Good |
| 18 | All DB queries use `$wpdb->prepare()` for user-supplied values | ✅ Good |
| 19 | Content Writer uses `base64_decode` + `unserialize` for BeTheme data | Acceptable (data from own DB, not user-supplied directly) |

---

## 7. POTENTIAL BREAKING POINTS

| # | Scenario | Why It Breaks |
|---|----------|---------------|
| 20 | **Large site index sync**: 10k+ posts × `get_permalink()` per post = hundreds of SQL queries + object cache misses during TRUNCATE window | Memory exhaustion or timeout |
| 21 | **AI provider timeout at 60s**: If OpenAI/Google is slow, `wp_remote_post` timeout is 60s. During bulk generation (sequential), a few timeouts can make the wizard appear stuck. No retry logic. | UX confusion |
| 22 | **Content Writer BeTheme `unserialize()`**: If BeTheme changes its storage format, the hardcoded deserialization path will silently fail. | Data loss risk on content edits |

---

## 8. DEAD / ORPHAN CODE

| # | Item | Notes |
|---|------|-------|
| 23 | ~~`betheme_heading_replace_public()` and `walk_replace_public()` in Content_Writer~~ | **FALSE POSITIVE** — both methods ARE used by `class-frontend.php` (L1760, L1766) and `class-admin.php` (L4763, L4768) for live preview rendering. NOT dead code. |

---

## 9. COMPATIBILITY NOTES

| # | Item | Status |
|---|------|--------|
| 24 | WooCommerce integration | Well-isolated, filter-based. Only boots when WC is active AND enabled in settings. ✅ |
| 25 | Custom themes (BeTheme, Elementor, Beaver, Bricks, Themify, Oxygen, Thrive, Brizy, Seedprod, Tatsu) | Content_Writer has explicit support for all. Detection + read/write logic present. ✅ |
| 26 | PHP 7.4 compatibility | Uses typed properties (`private array $options`) which require PHP 7.4+. OK per stated requirements. ✅ |
| 27 | WordPress multisite | Not explicitly supported or tested — no multisite-specific code seen. |

---

## FIX PLAN — Priority Order

### P0 (Critical Bug)
- [x] **#3**: Fix index button disabled state after successful indexing (AJAX success path + page-load restore path)

### P1 (Data Integrity)
- [x] **#5**: Add missing meta keys to `uninstall.php`
- [x] **#6**: Add wildcard cleanup for dynamic video meta keys in `uninstall.php`
- [x] **#7**: Update `Meta_Keys::all_post_meta_keys()` to include all keys
- [x] **#12**: Wrap `sync()` in transaction to prevent empty-index window
- [x] **#13**: Optimize `sync()` memory usage for large sites

### P2 (Correctness / Docs)
- [x] **#1 + #2 + #9**: Fix version constant, naming prefix, align docs
- [x] **#4**: Remove or replace deprecated Google ping
- [x] **#8**: Fix term meta key references in documentation
- [x] **#11**: Fix nested `plugins_loaded` hook for WooCommerce

### P3 (Optimization / Cleanup)
- [x] **#10**: Guard `schedule_all()` with a static flag to avoid repeated calls
- [x] **#14**: Batch `verify_index_integrity()` queries
- [x] **#15**: Move Google API key from URL param to header
- [x] **#23**: ~~Verify dead code~~ — FALSE POSITIVE (methods are used)

### FALSE POSITIVES (Not Issues)
- **Site Chat page-count gate**: The `send_to_ai()` RuntimeException when page count exceeds model capacity is INTENTIONAL. It's model-aware (adapts to each model's context window via `get_max_pages_for_model()`), is bypassed when Focus Pages/Lists are used, and properly guides users to create a List. NOT a bug.
- **`get_compact_site_tree` max_pages=500**: Intentional fallback to branch-only tree for large sites. AI already receives targeted data (hierarchy, topical pages, keyphrase conflicts) so the full tree is unnecessary for >500 pages.
