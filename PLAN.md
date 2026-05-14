# AI SEO Keeper Plan

## Mission now

AI SEO Keeper is being built as a standalone WordPress SEO plugin so this site does not need another free SEO plugin for core SEO operations.

The working model is hybrid by design:

- Deterministic code owns frontend SEO output, settings, storage, validation, audits, discovery documents, and refresh signaling.
- AI owns drafting, explanation, site-wide audits, and page-level assistance.
- Manual page metadata and approved AI suggestions are both first-class inputs.
- Migration helpers are secondary. Standalone replacement behavior is the primary goal.

## Documentation anchors

- `PLAN.md`: strategic direction, current shipped scope, and next priorities.
- `PROJECT-HANDOFF.md`: current-state snapshot, architecture graph, runtime flow, data model, and new-chat briefing.

## Current status as of 2026-05-14

- Plugin version: **1.3.1** with modular admin architecture and PSR-4 autoloader.
- The plugin is a complete standalone SEO layer for both singular and non-singular content.
- **Modular admin**: `class-admin.php` is a slim coordinator delegating to 5 sub-modules (`class-admin-ajax.php`, `class-admin-rollout.php`, `class-admin-import-export.php`, `class-admin-taxonomy.php`, `class-seo-analysis.php`).
- **10 admin pages**: Dashboard, Settings, Setup Wizard, Audit, Bulk Editor, Image SEO, Keywords, AI Strategist (Site Chat), Export/Import, Redirects.
- **Scale-aware Runs system**: saved page lists for batch processing on large sites, with `completed_steps` tracking.
- **Setup Wizard**: 3-step guided flow with cost/time warning modal (always shown), pause/resume/stop, WooCommerce Products filter, skip rules, runs system.
- **Bulk Editor**: row counter, real-time search, WooCommerce-aware post type filter, site structure tree.
- **AI SEO Strategist**: dedicated site-wide AI chat page with focus-page scoping and run context.
- **Data Management**: scope-based clearing (metadata, audits, everything) deleting 17 post meta + 4 term meta + conversations + messages + IndexNow log + runs + active_runs user meta.
- **Gutenberg sidebar panel** with dedicated JS/CSS assets.
- The editor workflow, AI drafting, saved manual metadata, history/approval, schema, breadcrumbs, discovery documents, audits, and IndexNow are all in place.
- Manual or imported metadata can render on the frontend without requiring an approved AI suggestion.
- Automatic search appearance covers singular content plus archives, categories, tags, author pages, date archives, search results, the posts page, post type archives, and 404 pages.
- Non-singular title templates are configurable per context type with support for tokens like `%%term_title%%`, `%%author%%`, `%%date%%`, `%%searchphrase%%`, `%%archive_title%%`.
- Per-context noindex controls allow operators to set categories, tags, author archives, date archives, search results, and format archives to noindex individually.
- Google and Bing verification tags now emit sitewide from plugin settings.
- XML Sitemap engine generates a sitemap index at `/sitemap_index.xml` with separate post, page, category, and tag sitemaps.
- XML Sitemap replaces WordPress core sitemaps when enabled, includes an XSL stylesheet for human-readable display, and adds a Sitemap directive to robots.txt.
- Sitemap respects per-page noindex robots directives and excludes noindexed content.
- Schema output adapts to non-singular contexts with appropriate entity types.
- Title branding system with per-page opt-out and AI prompt budget enforcement.
- AI generation sends live browser field values as context overrides, evaluates existing drafts, enforces keyphrase presence, passes full tab data, and writes AI-generated keyphrase back.
- AI Content Editor with changeset-based editing, preview, apply/discard, backup/restore, multi-builder support.
- Redirects & 404 Monitor with 301/302/307 management, 404 logging, hit counters.
- Export/Import with selective JSON export/import (settings, metadata, redirects).
- WooCommerce integration for product-aware filtering.
- **81 PHPUnit tests, 141 assertions** — all passing.
- **Full production audit**: all 9 admin pages verified, 0 orphaned controls, 0 broken handlers.
- GreenCoders design identity applied to the editor metabox.

## Current replacement target

### Primary target

- Replace the practical day-to-day value of a free SEO plugin on this site without depending on Yoast or similar plugins.
- Keep the plugin useful even when no AI suggestion has been approved yet.
- Make frontend output reliable enough that site owners can run the plugin as their main SEO layer.

### Later target

- Selectively add higher-value Pro-style capabilities only where deterministic engineering and AI together can produce stable output.

## Non-goals right now

- Full Yoast Pro parity in one release.
- Autonomous publishing of raw AI output without sanitization or operator control.
- SEO score claims that imply ranking guarantees.
- Spending more time on importer breadth before standalone frontend coverage is complete.

## Architecture

### 1. Plugin shell and composition

- `ai-seo-keeper.php` loads the runtime classes and registers activation.
- `includes/class-plugin.php` wires the services together.
- `includes/class-activator.php` creates SQL tables and initializes defaults.
- Admin-only services are booted only in wp-admin.
- Frontend output is booted only on the public site.

### 2. Settings and provider layer

- `includes/class-settings.php` owns defaults, registration, and sanitization.
- Stores provider selection, model, API key, system prompt, feature flags, frontend toggles, search appearance settings, Google/Bing verification values, and IndexNow options.

### 3. Storage, history, and index layer

- `includes/class-content-indexer.php` maintains the content inventory and powers audit queries.
- `includes/class-history-store.php` stores conversations, AI messages, approvals, and site audit history.
- Post meta stores page-level SEO drafts, overrides, and frontend gates.

### 4. Admin and operator layer

- `includes/class-admin.php` owns the dashboard, audit page, settings page, editor metabox, AJAX handlers, rollout actions, and Yoast import flow.
- The editor metabox is the main operator surface for drafting, analyzing, saving, approving, and gating SEO output.

### 5. Frontend SEO output layer

- `includes/class-frontend.php` owns document title overrides, meta description, canonical, robots, Open Graph, Twitter cards, schema, breadcrumbs, and webmaster verification tags.
- Frontend output is guarded by global settings, conflict detection, and page-level or automatic-search-appearance rules.
- Precedence is: approved AI suggestion, then saved manual metadata, then automatic search appearance defaults.

### 6. Discovery and refresh layer

- `includes/class-discovery.php` serves `llms.txt` and `llms-full.txt`.
- `includes/class-indexnow.php` manages the key file endpoint, auto-submit, manual submission, and logging.

### 7. Audit and reporting layer

- `includes/class-audit-engine.php` builds the readiness report used in admin.
- The plugin tracks missing drafts, approval coverage, frontend readiness, duplicate titles, and thin content.

## Data model summary

### SQL tables (5)

- `wp_ai_seo_keeper_content_index`
- `wp_ai_seo_keeper_conversations`
- `wp_ai_seo_keeper_messages`
- `wp_ai_seo_keeper_redirects`
- `wp_ai_seo_keeper_runs`

### Option storage

- `ai_seo_keeper_options`
- `ai_seo_keeper_indexnow_log`
- `ai_seo_keeper_db_version`

### Important post meta (17 keys)

- `_ai_seo_keeper_meta_title`
- `_ai_seo_keeper_meta_description`
- `_ai_seo_keeper_focus_keyphrase`
- `_ai_seo_keeper_social_title`
- `_ai_seo_keeper_social_description`
- `_ai_seo_keeper_social_image`
- `_ai_seo_keeper_canonical_url`
- `_ai_seo_keeper_robots_directives`
- `_ai_seo_keeper_schema_type`
- `_ai_seo_keeper_approved_message_id`
- `_ai_seo_keeper_frontend_enabled`
- `_ai_seo_keeper_page_audit`
- `_ai_seo_keeper_audit_skip`
- `_ai_seo_keeper_pending_content_changes`
- `_ai_seo_keeper_content_backup`
- `_ai_seo_keeper_cornerstone`
- `_ai_seo_keeper_title_branding_off`
- `_ai_seo_keeper_hreflang`

### Term meta (4 keys)

- `_ai_seo_keeper_term_title`
- `_ai_seo_keeper_term_description`
- `_ai_seo_keeper_term_canonical`
- `_ai_seo_keeper_term_noindex`

## Production scope now shipped

- Plugin bootstrap, activation routine, PSR-4 autoloader, namespaced runtime, DB auto-upgrade, and uninstall cleanup.
- Modular admin architecture: slim coordinator + 5 sub-modules (AJAX, rollout, import/export, taxonomy, SEO analysis).
- 10 admin pages: Dashboard, Settings, Setup Wizard, Audit, Bulk Editor, Image SEO, Keywords, AI Strategist, Export/Import, Redirects.
- Provider-backed AI generation for page metadata, page-level assistant chat, AI content editor, and strategic site audits.
- Content indexing for site inventory, overlap detection, audit summaries, and discovery prioritization.
- Per-page draft workflow with history, approval, and page-level frontend gating.
- Saved page-level SEO fields can render on the frontend through the same page-level gate even without an approved AI suggestion.
- Stable frontend output for title, description, canonical, Open Graph, Twitter, robots, schema, and webmaster verification meta tags.
- Automatic search appearance mode for public singular content using title templates, separators, and fallback descriptions.
- Non-singular search appearance for category, tag, author, date, search, home/posts page, post type archive, custom taxonomy, and 404 contexts.
- XML Sitemap engine with sitemap index, per-type sitemaps (post, page, category, tag), News, Video, XSL stylesheet, robots.txt directive, WordPress core sitemap replacement, noindex-aware filtering.
- Visible breadcrumbs through the `[ai_seo_keeper_breadcrumbs]` shortcode.
- Yoast and competing-plugin conflict protection with explicit override controls.
- Non-destructive Yoast metadata import that preserves existing AI SEO Keeper values.
- AI discovery documents at `llms.txt` and `llms-full.txt`.
- Deterministic audit dashboard, duplicate detection, thin-content reporting, and rollout readiness scoring.
- IndexNow key serving, submission logging, manual priority submission, and auto-submit support.
- Schema coverage for website, organization, breadcrumb, article, product, service, collection, about, and contact contexts, plus FAQ and collection list augmentation.
- Redirects & 404 Monitor with 301/302/307 management, 404 logging, hit counters, AJAX add/delete.
- Scale-aware Runs system with saved page lists, completed_steps tracking, create/delete via AJAX.
- Skip Rules with URL pattern matching, server-side enforcement in both metadata generation and audits.
- Scope-based data management (metadata, audits, everything) with confirmation modals.
- WooCommerce integration for product-aware filtering in wizard, bulk editor, and site tree.
- Gutenberg sidebar panel with dedicated JS/CSS assets.
- Export/Import with selective JSON scope (settings, metadata, redirects).
- Taxonomy-level SEO fields (title, description, canonical, noindex per category/tag).
- AI Content Editor with changeset-based editing, preview, apply/discard, backup/restore, multi-builder support.
- Keyword tracking with cannibalization detection and content-index-synced coverage stats.
- Image SEO dashboard with alt text editing, filters, "Used on" toggle.
- 81 PHPUnit tests, 141 assertions — all passing.
- Full production audit: all 9 admin pages verified, 0 orphaned controls.

## Current priorities

### Completed (formerly Phase 1 — Yoast Parity Core)

All critical Yoast Free parity features have been shipped:

1. ✅ **Redirect Manager (301/302/307 + 404 monitor)** — `class-redirects.php`, DB table, admin page, AJAX add/delete, 404 logging, hit counter
2. ✅ **Robots.txt Editor** — settings tab with textarea editor, validation
3. ✅ **Keyword Density / Keyphrase Analysis** — `class-seo-analysis.php` with density, first paragraph, subheadings, image alt, URL slug checks; readability scoring
4. ✅ **Social Profiles for Organization Schema** — settings fields + `sameAs` output
5. ✅ **Taxonomy-Level SEO Fields** — `class-admin-taxonomy.php` with per-category/tag title, description, canonical, noindex
6. ✅ **Bulk Editor for Titles/Descriptions** — dedicated page with search, row counter, WooCommerce filter, site tree

### Completed (formerly Phase 2 — Professional-Grade)

7. ✅ **Orphaned Content Detection** — link analysis in editor Checks tab
8. ✅ **Internal Linking Suggestions** — Links tab in editor metabox
9. ✅ **Cornerstone Content Marking** — per-page toggle in Advanced tab
10. ✅ **Image SEO Dashboard** — dedicated page with alt text editing, filters, "Used on" toggle
11. ✅ **Keyword Tracking / Content Insights** — dedicated page with cannibalization detection
12. ✅ **Export/Import Settings & Data** — JSON export/import with selective scope

### Completed (formerly Phase 3 — Advanced)

13. ✅ **Local SEO / Business Schema UI** — settings + LocalBusiness schema + map shortcode
14. ✅ **News Sitemap** — `/news-sitemap.xml`
15. ✅ **Video Sitemap** — `/video-sitemap.xml`
16. ✅ **Hreflang / Multi-Language** — manual hreflang per page in Advanced tab
17. ✅ **RSS Feed Optimization** — before/after content, featured image, publication delay
18. ✅ **Crawl Budget Optimization** — per-archive noindex, disable attachments/authors/dates, remove generator/shortlink/RSD/feeds

### Current Priorities (v1.4+)

1. **AI site-structure assistant** — dedicated admin page where AI can view the full sitemap, propose URL restructuring, rename slugs, and manage redirects
2. **AI-powered image alt text generation** — generate alt text from page context and image analysis
3. **RankMath and SEOPress import wizards** — extend migration support beyond Yoast
4. **Broader schema enrichment** — product price/SKU, organization/contact details from trusted data sources

## Feature Comparison: AI SEO Keeper vs Yoast Free

| Feature | Yoast Free | AI SEO Keeper | Status |
|---------|-----------|---------------|--------|
| SEO title / meta description | ✅ | ✅ | Parity |
| Focus keyphrase analysis | ✅ Full | ✅ Full | Parity |
| Readability analysis | ✅ | ✅ | Parity |
| Search appearance templates | ✅ | ✅ | Parity |
| Open Graph / Twitter | ✅ | ✅ | Parity |
| Schema / JSON-LD | ✅ Basic | ✅ Rich | **Ahead** |
| XML Sitemap | ✅ | ✅ (+ News, Video) | **Ahead** |
| Breadcrumbs | ✅ | ✅ | Parity |
| Canonical URLs | ✅ | ✅ | Parity |
| Robots directives | ✅ | ✅ | Parity |
| Redirect manager | ✅ Premium | ✅ | **Ahead** |
| Internal linking | ✅ Premium | ✅ | **Ahead** |
| Orphaned content | ✅ Premium | ✅ | **Ahead** |
| Cornerstone content | ✅ | ✅ | Parity |
| Social profiles schema | ✅ | ✅ | Parity |
| Taxonomy SEO fields | ✅ | ✅ | Parity |
| Robots.txt editor | ❌ | ✅ | **Ahead** |
| Bulk editor | ❌ | ✅ | **Ahead** |
| Title branding | ❌ | ✅ | **Ahead** |
| AI page assistant | ❌ | ✅ | **Ahead** |
| AI content editor | ❌ | ✅ | **Ahead** |
| AI site audit | ❌ | ✅ | **Ahead** |
| AI SEO Strategist | ❌ | ✅ | **Ahead** |
| Scale-aware Runs | ❌ | ✅ | **Ahead** |
| Skip Rules | ❌ | ✅ | **Ahead** |
| Multi-builder support | ❌ | ✅ | **Ahead** |
| LLM discovery docs | ❌ | ✅ | **Ahead** |
| IndexNow auto-submit | ❌ | ✅ | **Ahead** |
| Image alt audit | ❌ | ✅ | **Ahead** |
| Pending changes + preview | ❌ | ✅ | **Ahead** |
| WooCommerce integration | ❌ | ✅ | **Ahead** |
| Gutenberg sidebar | ✅ | ✅ | Parity |
| Export/Import | ❌ | ✅ | **Ahead** |
| Keyword tracking | ❌ | ✅ | **Ahead** |
| Automated test suite | — | ✅ 81 tests | **Ahead** |

## AI discovery notes

- There is no proven magic AI-only meta tag that makes sites rank in AI answers by itself.
- The practical stack remains crawlability, XML sitemaps, feeds, structured data, internal linking, canonicals, robots control, and useful discovery documents.
- `llms.txt` is an additional discovery surface, not a replacement for normal SEO fundamentals.

## Safety rules

- Do not overwrite another SEO plugin's output automatically.
- Do not expose API keys outside admin settings.
- Do not trust AI output without sanitization and explicit operator control.
- Keep prompts, responses, and approvals auditable.
- Use the local content index instead of repeatedly sending the entire site to the model.

## Production acceptance criteria

- The plugin activates cleanly and creates the required tables and default options.
- Settings, provider credentials, search appearance options, verification values, and rollout controls persist correctly.
- The site index sync completes and powers audits, AI prompts, and discovery documents.
- AI generation, history, approval, chat, and site-audit workflows remain auditable.
- Frontend output renders only when global settings and conflict rules allow it, with page-gated or automatic search appearance support.
- Discovery documents, schema, verification tags, breadcrumbs, and IndexNow signaling extend visibility without bypassing operator safeguards.