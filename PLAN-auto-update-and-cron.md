# SEO Captain — Auto-Update & Cron Manager Plan

**Date**: 2026-05-17  
**Scope**: Incremental content index, IndexNow deletion, Exclude from Sitemap, Cron Job Manager  
**Constraint**: WordPress 6.7+, PHP 7.4+, WooCommerce compatible, all public CPTs

---

## Problem Statement

The plugin currently has three gaps:

1. **Content Index is manual-only** — `Content_Indexer::sync()` does a full TRUNCATE + re-insert. No hooks for `save_post`, `delete_post`, `trashed_post`, or `transition_post_status`. Deleted/trashed posts remain in the index until the user manually clicks "Sync Index."

2. **IndexNow doesn't fire on deletion** — Only hooks into `save_post` for publish/update. Search engines are never notified when content is removed.

3. **No "Exclude from Sitemap" UI** — The sitemap already excludes posts with `noindex` in robots directives, but there's no dedicated checkbox. Users must know to select "noindex" from the robots dropdown — unintuitive.

4. **No cron infrastructure** — Zero `wp_schedule_event()` calls. No scheduled index health checks, no stale entry cleanup, no periodic audits.

---

## Architecture Decisions

### A. Incremental Index Updates

**Approach**: Add `upsert_post()` and `delete_post()` methods to `Content_Indexer`. These use `$wpdb->replace()` (upsert) and `$wpdb->delete()` (delete) for single-row operations. The existing `sync()` method remains unchanged for manual full re-index.

**Hooks** (registered in `Plugin::boot()`, not `Admin` — must fire on both admin and frontend cron):
- `save_post` (priority 50, after core and other plugins) → `upsert_post()`
- `delete_post` (priority 10) → `delete_from_index()`
- `trashed_post` (priority 10) → `delete_from_index()`
- `untrashed_post` (priority 10) → `upsert_post()`
- `transition_post_status` (priority 10) → handle draft↔publish transitions

**WooCommerce coverage**: All public post types are handled generically via `get_post_types(['public' => true])`. Products, coupons, etc. are automatically included.

### B. IndexNow on Deletion

**Approach**: Add hooks in `IndexNow::__construct()`:
- `trashed_post` → submit URL with reason `post_trash`
- `before_delete_post` → submit URL with reason `post_delete` (must fire BEFORE deletion so we can still get the permalink)

**Condition**: Only fire if IndexNow is enabled + auto_submit is on + post was published + supported post type.

### C. Exclude from Sitemap Checkbox

**Approach**: Add a new meta key `_ai_seo_captain_exclude_sitemap` (boolean `'1'`/`''`). Add a checkbox in the editor metabox's Advanced tab. Update `Sitemap::get_post_type_urls()` to also exclude posts with this meta key set to `'1'`.

This is deliberately separate from robots directives because:
- A page can be `noindex` but still in sitemap (rare but valid)
- A page can be indexed but excluded from sitemap (e.g., thin landing pages)
- Users expect a simple toggle

### D. Cron Manager

**New class**: `Cron_Manager` — Registers and manages all plugin cron jobs.

**Cron jobs to implement**:

| Job ID | Schedule | Purpose |
|--------|----------|---------|
| `ai_seo_captain_index_health` | Daily | Verify index integrity: remove entries for deleted posts, add entries for new posts missed by hooks |
| `ai_seo_captain_stale_cleanup` | Weekly | Clean stale data: old IndexNow logs, expired edit plans, orphaned conversations |
| `ai_seo_captain_sitemap_ping` | On publish (debounced) | Ping Google/Bing sitemap endpoints when sitemap content changes |

**New admin page**: "Scheduled Tasks" — submenu under SEO Captain, showing:
- All registered cron jobs with status (active/paused/error)
- Next run time, last run time, last result
- Execution log (last 20 runs per job)
- Pause/Resume/Run Now controls
- Error details if last run failed

**Storage**: 
- Job metadata (paused state, last result, error count): `ai_seo_captain_cron_jobs` option
- Execution log: `ai_seo_captain_cron_log` option (capped at 100 entries)

---

## Files to Create

| File | Purpose |
|------|---------|
| `includes/class-cron-manager.php` | Cron registration, execution, logging, health checks |
| `includes/admin/view-cron-manager.php` | Admin page view for Scheduled Tasks |
| `assets/css/page-cron-manager.css` | Page-specific styles |
| `assets/js/page-cron-manager.js` | AJAX controls (pause/resume/run now) |

## Files to Modify

| File | Changes |
|------|---------|
| `includes/class-content-indexer.php` | Add `upsert_post()`, `delete_from_index()`, `verify_index_integrity()` methods |
| `includes/class-indexnow.php` | Add `trashed_post` and `before_delete_post` hooks in constructor |
| `includes/class-plugin.php` | Instantiate `Cron_Manager`, register content index hooks (not in Admin — must fire on cron too) |
| `includes/class-admin.php` | Add "Scheduled Tasks" submenu, add "Exclude from Sitemap" checkbox in Advanced tab, register new meta key, add AJAX handlers |
| `includes/class-sitemap.php` | Check `_ai_seo_captain_exclude_sitemap` meta in `get_post_type_urls()` |
| `includes/class-settings.php` | Add cron-related defaults |
| `includes/class-activator.php` | Schedule cron jobs on activation, clear on deactivation |
| `includes/admin/class-admin-ajax.php` | Add AJAX handlers for cron pause/resume/run-now |
| `assets/css/admin-common.css` | Cron status badge styles |

---

## Implementation Order

### Phase 1: Content Indexer Incremental Updates
1. Add `upsert_post(int $post_id)` to `Content_Indexer`
2. Add `delete_from_index(int $post_id)` to `Content_Indexer`
3. Add `verify_index_integrity()` to `Content_Indexer`
4. Register hooks in `Plugin::boot()` (save_post, delete_post, trashed_post, untrashed_post, transition_post_status)
5. Test: create post → verify index row, trash → verify removed, untrash → verify restored

### Phase 2: IndexNow Deletion Hooks
1. Add `trashed_post` and `before_delete_post` hooks in `IndexNow::__construct()`
2. Add `handle_post_trash()` and `handle_post_delete()` methods
3. Both call existing `submit_post()` / `submit_urls()` with appropriate reasons
4. Test: trash a published post → verify IndexNow log entry

### Phase 3: Exclude from Sitemap
1. Add `EXCLUDE_SITEMAP_META_KEY` constant to `Admin`
2. Add checkbox in Advanced tab of editor metabox
3. Save/delete in `persist_editor_meta()`
4. Register in Gutenberg meta
5. Add exclusion check in `Sitemap::get_post_type_urls()`
6. Test: check box → verify post absent from sitemap XML

### Phase 4: Cron Manager
1. Create `Cron_Manager` class with job registration, execution wrappers, logging
2. Implement `index_health` job (verify_index_integrity)
3. Implement `stale_cleanup` job (prune old logs/data)
4. Implement `sitemap_ping` job (debounced ping)
5. Create admin view page
6. Create CSS/JS assets
7. Add AJAX handlers for pause/resume/run-now
8. Wire into Plugin::boot() and Admin menu
9. Handle activation/deactivation scheduling

### Phase 5: Testing & Verification
1. PHP syntax check all modified files
2. Run full unit test suite (81+ tests)
3. Manual verification in WP admin

---

## Technical Details

### Content_Indexer::upsert_post()

```php
public function upsert_post(int $post_id): bool
{
    $post = get_post($post_id);
    if (!$post || !$this->is_indexable_post($post)) {
        $this->delete_from_index($post_id);
        return false;
    }
    // Use $wpdb->replace() with UNIQUE KEY (object_type, object_id)
    // Returns true on success
}
```

### Content_Indexer::delete_from_index()

```php
public function delete_from_index(int $post_id): bool
{
    // $wpdb->delete() where object_id = $post_id AND object_type = 'post'
}
```

### Cron_Manager Architecture

```php
class Cron_Manager {
    const JOBS_OPTION = 'ai_seo_captain_cron_jobs';
    const LOG_OPTION  = 'ai_seo_captain_cron_log';
    
    // Job definitions (ID => config)
    private array $jobs = [
        'ai_seo_captain_index_health' => [
            'schedule'    => 'daily',
            'label'       => 'Index Health Check',
            'description' => 'Verifies content index integrity...',
            'callback'    => 'run_index_health',
        ],
        // ...
    ];
    
    public function schedule_all(): void;      // On activation
    public function unschedule_all(): void;    // On deactivation
    public function pause_job(string $id): void;
    public function resume_job(string $id): void;
    public function run_job_now(string $id): array;
    public function get_job_status(): array;   // For admin view
    public function log_execution(): void;     // After each run
}
```

### Admin View (Scheduled Tasks page)

Professional table with columns:
- **Task** (name + description)
- **Schedule** (Daily/Weekly/On Event)
- **Status** (Active ●, Paused ◉, Error ✕)
- **Last Run** (timestamp + duration + result)
- **Next Run** (timestamp or "Paused")
- **Actions** (Pause/Resume, Run Now)

Plus expandable error log section and execution history.

---

## Post Types Covered

All public post types are handled automatically:
- `post`, `page` (WordPress core)
- `product` (WooCommerce)
- Any registered custom post type (themes, plugins)
- Excludes: `attachment` (explicitly filtered out)

---

## Safety & Edge Cases

1. **Autosaves**: `save_post` handler checks `wp_is_post_autosave()` and skips
2. **Revisions**: Checks `wp_is_post_revision()` and skips
3. **Bulk operations**: Each post triggers its own upsert (no batch needed — WP fires hooks per post)
4. **Race conditions**: `$wpdb->replace()` is atomic (INSERT ... ON DUPLICATE KEY UPDATE)
5. **Cron overlap**: Jobs use WP transient locks to prevent concurrent execution
6. **Deactivation cleanup**: All cron jobs unscheduled on plugin deactivation
7. **WooCommerce products**: Handled as standard public CPT — no special logic needed
8. **Multisite**: Each site has its own index table (prefixed) — works out of the box
