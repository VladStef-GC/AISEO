# AI SEO Keeper Project Handoff

Snapshot date: 2026-05-09

Plugin root: `wp-content/plugins/ai-seo-keeper`

Plugin version header: `1.2.0`

Purpose: this is the fast-start handoff document for any new chat session. Read this together with `PLAN.md` before making new changes.

## One-paragraph brief

AI SEO Keeper is a hybrid AI plus deterministic SEO plugin for WordPress. The current objective is not to be a helper next to another SEO plugin, but to become the main standalone SEO layer for this site. Deterministic code owns the live frontend behavior, schema, discovery documents, sitemaps, audits, and signaling. AI is used for drafting metadata, answering editor questions, and generating site-wide audit guidance. The plugin now covers both singular and non-singular content with configurable search appearance, XML sitemaps, and full frontend SEO output.

## Current mission

- Replace the practical day-to-day need for another free SEO plugin on this site.
- Keep the plugin useful even when no AI suggestion has been approved yet.
- Prefer standalone frontend behavior over migration convenience.
- Keep AI as an assistant and drafter, not the only route to usable SEO output.

## What is shipped today

- Plugin bootstrap, service wiring, activation, uninstall cleanup, and namespaced runtime.
- Settings page with provider, model, API key, system prompt, feature flags, frontend toggles, search appearance controls, Google/Bing verification fields, and IndexNow controls.
- Content indexing into dedicated SQL tables for inventory, audits, and AI context.
- Page-level editor metabox with snippet analysis, readability and SEO checks, social previews, schema and advanced fields, AI drafting, approval, history, and chat.
- Audit dashboard with readiness scoring, duplicate detection, thin-content reporting, rollout candidate views, and site-audit generation.
- Frontend output for title, meta description, canonical, robots, Open Graph, Twitter cards, schema, breadcrumb schema, visible breadcrumbs, and sitewide webmaster verification tags.
- Automatic search appearance baseline for public singular content when explicit metadata is missing.
- Non-singular search appearance for category, tag, author, date, search, home/posts page, post type archive, and 404 contexts with configurable title templates and per-context noindex controls.
- XML Sitemap engine with sitemap index at `/sitemap_index.xml`, per-type sitemaps, XSL stylesheet, robots.txt directive, WordPress core sitemap replacement, and noindex-aware filtering.
- AI discovery documents at `llms.txt` and `llms-full.txt`.
- IndexNow key serving, logging, manual submission, and auto-submit hooks.
- Non-destructive Yoast metadata import for migration without overwriting existing AI SEO Keeper values.
- Title branding system: automatic ` | Brand` suffix on page-specific SEO titles, configurable site brand setting, per-page opt-out, and AI prompt budget enforcement.
- AI generation context intelligence: live browser field overrides, preserve-if-good evaluation, keyphrase enforcement in both title and description, full tab data (social, schema, canonical, robots, cornerstone) in AI prompts, and keyphrase write-back to editor.
- GreenCoders design identity in the editor metabox: promoted accordion style with purple-to-green gradient, branded button styles, and custom help-tip iconography.

## What is not finished yet

## What is not finished yet

- AI site-structure assistant: dedicated admin page where AI can view the full sitemap, propose URL restructuring, rename slugs, and manage redirects.
- Taxonomy-level SEO fields (title/description per individual category/tag) beyond template-based defaults.
- Broader schema enrichment from trusted site-specific data sources such as product price, SKU, and organization/contact details where available.

## Architecture graph

```mermaid
flowchart TD
    WP[WordPress boot] --> Bootstrap[ai-seo-keeper.php]
    Bootstrap --> Activator[class-activator.php]
    Bootstrap --> Plugin[class-plugin.php]

    Plugin --> Settings[class-settings.php]
    Plugin --> History[class-history-store.php]
    Plugin --> IndexNow[class-indexnow.php]
    Plugin --> Discovery[class-discovery.php]

    Plugin -->|is_admin()| Indexer[class-content-indexer.php]
    Plugin -->|is_admin()| AIGenerator[class-ai-generator.php]
    Plugin -->|is_admin()| Admin[class-admin.php]
    Admin --> Audit[class-audit-engine.php]

    Plugin -->|public frontend| Frontend[class-frontend.php]
    Plugin --> Sitemap[class-sitemap.php]

    Settings --> Options[(wp_options)]
    Indexer --> ContentIndex[(ai_seo_keeper_content_index)]
    History --> Conversations[(ai_seo_keeper_conversations)]
    History --> Messages[(ai_seo_keeper_messages)]
    Admin --> PostMeta[(wp_postmeta)]
    Frontend --> PostMeta

    Frontend --> HeadOutput[document title and wp_head output]
    Discovery --> DiscoveryDocs[llms.txt and llms-full.txt]
    IndexNow --> KeyEndpoint[IndexNow key endpoint]
```

## Frontend decision graph

```mermaid
flowchart TD
    Request[Frontend request] --> Singular{Singular and viewable post type?}
    Singular -- No --> Stop[No page-level AI SEO Keeper context]
    Singular -- Yes --> Global{frontend_output_enabled?}
    Global -- No --> Stop
    Global -- Yes --> Conflict{Conflicting SEO plugin active and override disabled?}
    Conflict -- Yes --> Stop
    Conflict -- No --> Access{Page gate enabled or automatic search appearance enabled?}
    Access -- No --> Stop
    Access -- Yes --> TitleOrder[Title order: approved AI then manual meta then auto template then post title]
    Access --> DescriptionOrder[Description order: approved AI then manual meta then auto fallback then content fallback]
    Access --> SocialOrder[Social order: explicit social fields then resolved title and description with image fallback]
    Access --> SchemaOrder[Schema uses resolved context plus entity-specific enrichment]
```

## File map

| File | Responsibility |
| --- | --- |
| `ai-seo-keeper.php` | Bootstrap, constants, class loading, activation hook, boot entrypoint |
| `includes/class-plugin.php` | Runtime composition and admin/frontend boot split |
| `includes/class-activator.php` | SQL table creation and default option initialization |
| `includes/class-settings.php` | Settings defaults, registration, sanitization, title branding helpers |
| `includes/class-admin.php` | Dashboard, settings, audit page, editor metabox, AJAX handlers, rollout actions, Yoast import |
| `includes/class-content-indexer.php` | Content inventory, audit summary SQL, readiness counts |
| `includes/class-audit-engine.php` | Higher-level audit report assembly for admin |
| `includes/class-ai-generator.php` | Provider calls, prompt building with live context overrides and preserve-if-good logic, AI response parsing |
| `includes/class-history-store.php` | Conversation storage, suggestion history, approvals, site audit history |
| `includes/class-frontend.php` | Document title with branding suffix, head tags, schema, breadcrumbs, automatic search appearance for singular and non-singular contexts, verification tags |
| `includes/class-sitemap.php` | XML Sitemap index, per-type sitemaps, XSL stylesheet, robots.txt directive, WordPress core sitemap replacement |
| `includes/class-discovery.php` | `llms.txt` and `llms-full.txt` generation |
| `includes/class-indexnow.php` | IndexNow key file handling, submissions, log storage |
| `uninstall.php` | Drops plugin tables, deletes options, removes post meta |

## Storage model

### Options

- Option name: `ai_seo_keeper_options`
- IndexNow log option: `ai_seo_keeper_indexnow_log`

### SQL tables

- `wp_ai_seo_keeper_content_index`
  - Site inventory used for audits and AI context.
- `wp_ai_seo_keeper_conversations`
  - Conversation containers for per-post chat and site-audit sessions.
- `wp_ai_seo_keeper_messages`
  - Stored user and assistant messages, including AI suggestion payloads.

### Important post meta keys

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

## Runtime flow

### 1. Boot flow

1. WordPress loads `ai-seo-keeper.php`.
2. Activation creates the content index, conversations, and messages tables.
3. `Plugin::boot()` instantiates shared services.
4. In admin, the plugin boots the content indexer, AI generator, and admin UI.
5. On the public site, the plugin boots the frontend SEO output engine.

### 2. Editor flow

1. `class-admin.php` registers the metabox for public post types except attachments.
2. The operator can save manual SEO fields, request AI suggestions, approve a suggestion, or chat with the page assistant.
3. AI requests are built by `class-ai-generator.php` using the current post plus indexed site context. When called from the editor, live browser field values override stale database values. The AI evaluates existing drafts before rewriting and enforces keyphrase presence in titles and descriptions.
4. Requests and responses are stored by `class-history-store.php`.
5. Approved suggestions and saved manual fields become eligible frontend inputs.

### 3. Frontend flow

1. `class-frontend.php` checks whether global frontend output is enabled.
2. It suppresses output if another SEO plugin is active and conflict override is disabled.
3. It allows output when either the page gate is enabled or automatic search appearance is enabled.
4. It resolves metadata in this order:
   - approved AI suggestion
   - saved manual metadata
   - automatic search appearance defaults
5. It emits title, meta description, canonical, robots, Open Graph, Twitter, schema, breadcrumbs, and verification tags from one resolved context.

### 4. Audit and rollout flow

1. `class-content-indexer.php` scans public content into the inventory table.
2. `class-audit-engine.php` builds readiness metrics from the index plus post meta.
3. The admin audit page surfaces missing drafts, duplicate titles, thin content, rollout candidates, and site audit actions.
4. Bulk frontend rollout can enable page-level output for content that already has approved or saved frontend-ready metadata.

### 5. Discovery and signaling flow

1. `class-discovery.php` serves `llms.txt` and `llms-full.txt` directly on request.
2. The full document can include the latest stored AI site audit summary.
3. `class-indexnow.php` serves the verification key file, handles auto-submit on save, and logs manual or automatic submissions.
4. On localhost, IndexNow behavior is intentionally safe and does not act like live production refresh signaling.

## Key behavioral rules

- Manual page metadata is a valid production path. The plugin is not dependent on AI approval for every page.
- Approved AI suggestions still win over manual metadata when both exist for the same field.
- Automatic search appearance is a fallback layer, not a replacement for strong page-specific metadata.
- Visible breadcrumbs and breadcrumb schema now use the same underlying trail logic.
- Verification tags are sitewide and controlled from settings, not per page.
- Discovery documents are separate from the HTML head output and can remain useful even when another SEO plugin controls the frontend head.

## Operator and environment notes

- Workspace root: `c:/xampp/htdocs/greencoders`
- Plugin root: `c:/xampp/htdocs/greencoders/wp-content/plugins/ai-seo-keeper`
- Environment: local WordPress under XAMPP on Windows
- PHP executable typically used for validation: `c:/xampp/php/php.exe`
- There is no git repository in this workspace.
- The `assets` folder is currently empty.
- Inline editor JavaScript is intentionally kept inside `class-admin.php`.
- Uninstall currently performs hard cleanup of plugin tables, options, logs, and post meta.

## Validation patterns that have been used successfully

- `php -l` against changed PHP files.
- WordPress runtime probes via `wp-load.php` with direct class instantiation and buffered HTML/head output checks.
- Admin render checks that include `wp-admin/includes/template.php` when needed.
- SQL checks for readiness counts when UI sampling is capped.

## Current priorities for the next session

1. AI Content Editor Phase 2: "Apply" button for chat suggestions, snippet score metrics in chat prompt, page audit data in chat prompt.
2. AI site-structure assistant: dedicated admin page where AI can view the full sitemap, propose URL restructuring, rename slugs, and manage redirects.
3. Taxonomy-level SEO fields (title/description per individual category/tag) beyond template-based defaults.
4. Broader schema enrichment from trusted site-specific data sources.

## New chat briefing

Use this as the starting brief for a new chat:

```text
Read wp-content/plugins/ai-seo-keeper/PLAN.md and wp-content/plugins/ai-seo-keeper/PROJECT-HANDOFF.md first.

We are building AI SEO Keeper as a standalone WordPress SEO plugin that should replace the need for another free SEO plugin on this site.

Current state:
- strong singular-content frontend SEO output already exists
- manual metadata, approved AI suggestions, and automatic search appearance all feed the frontend
- breadcrumbs, schema, llms documents, IndexNow, and verification tags are already implemented
- the biggest remaining gap is non-singular search appearance and frontend coverage

Working rule:
prioritize standalone frontend behavior over additional importer or migration work.
```