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

## Current status as of 2026-05-06

- The plugin is a complete standalone SEO layer for both singular and non-singular content.
- The editor workflow, AI drafting, saved manual metadata, history/approval, schema, breadcrumbs, discovery documents, audits, and IndexNow are already in place.
- Manual or imported metadata can render on the frontend without requiring an approved AI suggestion.
- Automatic search appearance covers singular content plus archives, categories, tags, author pages, date archives, search results, the posts page, post type archives, and 404 pages.
- Non-singular title templates are configurable per context type with support for tokens like `%%term_title%%`, `%%author%%`, `%%date%%`, `%%searchphrase%%`, `%%archive_title%%`.
- Per-context noindex controls allow operators to set categories, tags, author archives, date archives, search results, and format archives to noindex individually.
- Google and Bing verification tags now emit sitewide from plugin settings.
- XML Sitemap engine generates a sitemap index at `/sitemap_index.xml` with separate post, page, category, and tag sitemaps.
- XML Sitemap replaces WordPress core sitemaps when enabled, includes an XSL stylesheet for human-readable display, and adds a Sitemap directive to robots.txt.
- Sitemap respects per-page noindex robots directives and excludes noindexed content.
- Schema output adapts to non-singular contexts with appropriate entity types (CollectionPage, SearchResultsPage, WebPage).

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

### SQL tables

- `wp_ai_seo_keeper_content_index`
- `wp_ai_seo_keeper_conversations`
- `wp_ai_seo_keeper_messages`

### Option storage

- `ai_seo_keeper_options`
- `ai_seo_keeper_indexnow_log`

### Important post meta

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

## Production scope now shipped

- Plugin bootstrap, activation routine, namespaced runtime, and uninstall cleanup.
- Provider-backed AI generation for page metadata, page-level assistant chat, and strategic site audits.
- Content indexing for site inventory, overlap detection, audit summaries, and discovery prioritization.
- Per-page draft workflow with history, approval, and page-level frontend gating.
- Saved page-level SEO fields can render on the frontend through the same page-level gate even without an approved AI suggestion.
- Stable frontend output for title, description, canonical, Open Graph, Twitter, robots, schema, and webmaster verification meta tags.
- Automatic search appearance mode for public singular content using title templates, separators, and fallback descriptions.
- Non-singular search appearance for category, tag, author, date, search, home/posts page, post type archive, custom taxonomy, and 404 contexts with configurable title templates and per-context noindex controls.
- XML Sitemap engine with sitemap index, per-type sitemaps (post, page, category, tag), XSL human-readable stylesheet, robots.txt Sitemap directive, WordPress core sitemap replacement, noindex-aware URL filtering, lastmod, changefreq, and priority attributes.
- Visible breadcrumbs through the `[ai_seo_keeper_breadcrumbs]` shortcode, backed by the same trail logic used for breadcrumb schema.
- Yoast and competing-plugin conflict protection with explicit override controls.
- Non-destructive Yoast metadata import that preserves existing AI SEO Keeper values.
- AI discovery documents at `llms.txt` and `llms-full.txt`.
- Deterministic audit dashboard, duplicate detection, thin-content reporting, and rollout readiness scoring.
- IndexNow key serving, submission logging, manual priority submission, and auto-submit support.
- Schema coverage for website, organization, breadcrumb, article, product, service, collection, about, and contact contexts, plus FAQ and collection list augmentation when content supports it.

## Current priorities

1. ~~Extend automatic search appearance beyond singular content~~ — DONE: archives, taxonomies, author, date, search, posts page, 404.
2. ~~XML Sitemap~~ — DONE: sitemap index, per-type sitemaps, XSL stylesheet, robots.txt directive, core sitemap replacement.
3. Expand schema enrichment from trusted site fields such as price, SKU, and business/contact details where those values are available consistently.
4. Build the AI site-structure assistant: dedicated admin page where AI can see the full sitemap, propose URL restructuring, rename slugs, and manage redirects.
5. Add taxonomy-level SEO fields (title/description per category/tag) beyond the template-based defaults.
6. Keep prioritizing standalone replacement features over convenience import features.

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