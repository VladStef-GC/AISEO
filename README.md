<p align="center">
  <img src="https://img.shields.io/badge/version-1.3.1-blue?style=flat-square" alt="Version 1.3.1" />
  <img src="https://img.shields.io/badge/WordPress-6.7%2B-21759b?style=flat-square&logo=wordpress" alt="WordPress 6.7+" />
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 7.4+" />
  <img src="https://img.shields.io/badge/license-proprietary-lightgrey?style=flat-square" alt="License" />
</p>

# SEO Captain

**The AI-powered SEO copilot for WordPress.**

SEO Captain uses artificial intelligence to generate, manage, and optimize every aspect of your site's SEO — from meta tags and schema markup to full page audits, content editing, and search engine notifications — all from a single plugin.

---

## Highlights

| | Feature | Why it matters |
|---|---------|----------------|
| 🤖 | **AI-Generated SEO Metadata** | One click generates optimized titles and descriptions using GPT-4.1 or Google Gemini |
| 🏷️ | **Title Branding** | Automatic ` | Brand` suffix on all SEO titles with per-page opt-out |
| ✅ | **Approval Workflow** | AI suggestions stay as drafts until you approve — nothing goes live without your say |
| 📊 | **Full Page Audits with Scoring** | Every page gets an SEO score (0-100) with specific issues and actionable suggestions |
| 💬 | **AI SEO Strategist** | Dedicated site-wide AI chat with focus-page scoping and run-based context |
| 🔄 | **Yoast SEO Migration** | One-click import of all Yoast metadata — switch without losing anything |
| 🗺️ | **Advanced XML Sitemaps** | Standard, News, and Video sitemaps with browser-friendly XSL styling |
| 🔗 | **301/302/307 Redirects + 404 Monitor** | Manage redirects and catch broken links before they hurt rankings |
| ⚡ | **IndexNow Integration** | Instantly notify search engines when content changes — no waiting for crawlers |
| 🧠 | **AI Discovery Documents** | Auto-generated `llms.txt` makes your site discoverable by AI search agents |
| 🏢 | **Local SEO & Business Schema** | Full local business markup with address, hours, map, and price range |
| 📋 | **Scale-Aware Runs System** | Saved page lists let large sites audit and generate in manageable batches |
| 🎬 | **Video SEO** | Manage SEO titles and descriptions for YouTube, Vimeo, and self-hosted videos across your site |
| 📄 | **Document SEO** | Optimize PDF, Word, Excel, PowerPoint, and other document metadata for search |
| 🛒 | **WooCommerce Integration** | Product-aware filtering in wizard, bulk editor, and keyword tracking |
| 🔍 | **Skip Rules** | Exclude pages by URL pattern from both metadata generation and audits |

---

## Table of Contents

- [Features](#features)
  - [Setup Wizard](#-setup-wizard)
  - [AI SEO Generation](#-ai-seo-generation)
  - [Editor Metabox](#-editor-metabox)
  - [Page Audits & Scoring](#-page-audits--scoring)
  - [Bulk Editor](#-bulk-editor)
  - [Image SEO](#-image-seo)
  - [Video SEO](#-video-seo)
  - [Document SEO](#-document-seo)
  - [Keyword Tracking](#-keyword-tracking)
  - [AI SEO Strategist](#-ai-seo-strategist)
  - [XML Sitemaps](#-xml-sitemaps)
  - [IndexNow](#-indexnow-integration)
  - [Redirects & 404 Monitor](#-redirects--404-monitor)
  - [Frontend SEO Output](#-frontend-seo-output)
  - [Schema & Structured Data](#-schema--structured-data)
  - [Local SEO](#-local-seo)
  - [Social Media Tags](#-social-media-tags)
  - [Crawl Budget Optimization](#-crawl-budget-optimization)
  - [RSS Feed Optimization](#-rss-feed-optimization)
  - [AI Discovery Documents](#-ai-discovery-documents)
  - [Search Appearance Templates](#-search-appearance-templates)
  - [Title Branding](#-title-branding)
  - [AI Context Intelligence](#-ai-context-intelligence)
  - [AI Content Editor](#-ai-content-editor)
  - [Page Builder Compatibility](#-page-builder-compatibility)
  - [Export & Import](#-export--import)
  - [Yoast SEO Migration](#-yoast-seo-migration)
  - [Data Management](#-data-management)
  - [WooCommerce Integration](#-woocommerce-integration)
  - [Gutenberg Sidebar](#-gutenberg-sidebar)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Testing](#testing)
- [For Developers](#for-developers)
- [Support](#support)

---

## Features

### 🚀 Setup Wizard

Get your entire site SEO-ready in three guided steps:

1. **Index Your Site** — Scans all published pages and builds the content index (fast, free, no API calls)
2. **Generate SEO Metadata** — AI reads every page and writes optimized titles and descriptions (pages with existing metadata are skipped automatically)
3. **Full SEO Audit** — AI analyzes each page individually for issues: missing alt tags, heading structure problems, thin content, and more

Each step includes real-time progress bars, pause/resume/stop controls, and a detailed processing log. Previously audited pages load from cache instantly.

**Scale-aware features:**
- **Cost/time warning modal** — always shown before bulk operations, displaying page count and estimated API calls
- **Runs system** — save named page lists for partial-site processing; create custom lists or use "Full Site"
- **WooCommerce products filter** — when WooCommerce is active, filter the page selector by Products
- **Skip Rules** — define URL patterns (e.g. `/experiments/*`) to exclude pages from both metadata generation and audits; server-side enforced
- **"View Full Page List" button** — after indexing, jump directly to the Bulk Editor to review all indexed pages

---

### 🤖 AI SEO Generation

- **Supported Providers:** OpenAI (GPT-4.1-mini default) and Google Gemini
- **What it generates:** SEO title, meta description, focus keyphrase, and notes
- **Custom System Prompt:** Tailor the AI's behavior to your brand voice and industry
- **Draft-first approach:** AI suggestions are stored as drafts — they never go live until you explicitly approve them

---

### ✏️ Editor Metabox

A comprehensive SEO panel inside every post/page editor with six tabs:

| Tab | What's Inside |
|-----|---------------|
| **SEO** | Focus keyphrase, SEO title (30-60 char guide), meta description (70-155 char guide), one-click AI generation, approve/reject workflow, suggestion history |
| **Social** | Open Graph title, description, and image with live preview |
| **Schema** | Schema.org type selector per page |
| **Advanced** | Canonical URL override, robots directives (noindex, nofollow, noodp, noimageindex, etc.), cornerstone content toggle, hreflang entries |
| **Checks** | Real-time SEO analysis — keyphrase density, title/description length, readability score, transition word usage, link anchor quality (detects "click here", "read more", etc.) |
| **Links** | Internal and external link analysis — outbound count, inbound links, orphan detection, link suggestions |

**Plus collapsible panels:**
- **AI Chat** — Ask the AI anything about the page's SEO, get instant advice
- **History** — Browse all previous AI suggestions and content edit plans
- **Readiness** — See at a glance whether the page is approved, frontend-enabled, and conflict-free

---

### 📊 Page Audits & Scoring

Every page receives an **SEO score from 0 to 100** based on AI analysis:

- **Issues detected:** Missing alt tags, poor heading hierarchy, thin content, missing metadata, keyword stuffing
- **Actionable suggestions:** Specific improvements the AI recommends for each page
- **Audit Overview dashboard:** Score distribution cards, average score, top 10 / bottom 10 pages
- **Detailed reports:** Sortable and filterable by score range, with expandable issue/suggestion lists
- **Skip rules:** Exclude pages by pattern (`/experiments/*`, `/test-pages/**`) or individually — enforced server-side in both metadata generation and audits
- **Cached results:** Previously audited pages load instantly without re-calling the AI
- **Collapsible history sections:** Recent AI Site Audits and Recent IndexNow Activity are collapsible with entry counts, "Load More" (5 at a time), and "Export All (.txt)" download buttons
- **Approved Rollout Queue:** Bulk enable/disable frontend output for approved pages via checkboxes

**Site-wide readiness scoring:**
- Draft coverage (50% weight) — How many pages have AI-generated metadata
- Approval coverage (30%) — How many suggestions are approved
- Frontend coverage (20%) — How many pages have live SEO output

---

### 📝 Bulk Editor

Edit SEO titles and meta descriptions for all your content in one table view:

- **Row counter** — sequential `#` column for easy reference
- **Real-time search** — filter pages by title as you type
- Filter by post type (posts, pages, products, custom types) — WooCommerce products hidden when WooCommerce is inactive
- Paginated table with inline editing
- Character count indicators
- Individual row save via AJAX — no full page reload
- **Site Structure tree** — visual hierarchical view of your site showing parent/child relationships with expand/collapse and icons per content type (📄 pages, 📝 posts, 🛒 products)

---

### 🖼️ Image SEO

Dedicated dashboard for managing image alt text across your entire site:

- Shows all images with current alt text
- Filter: all / with alt / missing alt
- Inline alt text editing with AJAX save
- "Used on" toggle showing which pages use each image
- Stats bar: total images, images with alt text, missing alt text count

---

### 🎬 Video SEO

Dedicated dashboard for managing video SEO metadata across your entire site:

- **Auto-detection:** Finds YouTube embeds, Vimeo embeds, and self-hosted videos (MP4, WebM, OGG, MOV, AVI, WMV, FLV, 3GP, MKV)
- **Provider badges:** Color-coded labels (YouTube = red, Vimeo = blue, Self-hosted = gray) with format and file size details
- **Thumbnail preview:** YouTube thumbnails auto-loaded, self-hosted video thumbnails from Media Library
- **Inline editing:** SEO title and description per video with AJAX save
- **Filters:** All / Missing description / With description / YouTube / Vimeo / Self-hosted
- **Stats bar:** Total videos, videos with descriptions, missing descriptions
- **"Used on" column:** Shows which page each video appears on
- **AI-aware:** Video counts are included in page audit prompts so AI can flag missing video metadata

---

### 📄 Document SEO

Dedicated dashboard for managing document metadata for search engines:

- **Supported formats:** PDF, Word (DOC/DOCX), Excel (XLS/XLSX/CSV), PowerPoint (PPT/PPTX), OpenDocument (ODT/ODS/ODP), RTF, TXT
- **Format icons:** Visual emoji icons per document type (📕 PDF, 📘 Word, 📗 Excel, 📙 PowerPoint, 📄 Other)
- **File details:** Format label, file size, direct "View file" and "Edit in Media Library" links
- **Inline editing:** SEO title and description per document with AJAX save
- **Filters:** All / Missing title / With title / PDF / Word / Spreadsheet / Presentation
- **Stats bar:** Total documents, documents with titles, missing titles
- **"Used on" column:** Shows which pages link to each document, with expandable "+N" toggle for multiple references
- **AI-aware:** Document link counts are included in page audit prompts

---

### 🔑 Keyword Tracking

Monitor focus keyphrase usage across your site:

- **Keyphrase map:** See which pages target which keywords
- **Cannibalization detection:** Automatically flags when multiple pages target the same keyphrase
- **Coverage stats:** Total pages with keyphrases, pages without, cannibalized count — synced with the content index for accuracy
- Sortable table with keyphrase, page, and post type columns
- Helps prevent keyword competition between your own pages

---

### 🧠 AI SEO Strategist

A dedicated site-wide AI chat assistant for strategic SEO planning:

- **Focus page scoping** — select specific pages to include in the AI conversation context
- **Run-aware** — sees your saved Runs and their completion status
- **Full audit context** — AI receives all audit scores, issues, and suggestions for selected pages
- **Capacity display** — shows how much context is being sent to the AI
- **Persistent conversations** — chat history is saved and can be cleared when needed
- Accessible from the main plugin menu as "AI Strategist"

---

### 🗺️ XML Sitemaps

Full sitemap suite replacing WordPress core sitemaps:

| Sitemap | URL | Purpose |
|---------|-----|---------|
| Index | `/sitemap_index.xml` | Links to all sub-sitemaps |
| Posts | `/post-sitemap.xml` | All published posts |
| Pages | `/page-sitemap.xml` | All published pages |
| Categories | `/category-sitemap.xml` | Category archives |
| Tags | `/tag-sitemap.xml` | Tag archives |
| News | `/news-sitemap.xml` | Google News compatible |
| Video | `/video-sitemap.xml` | Video content |

- Max 1,000 URLs per sitemap
- Browser-friendly XSL stylesheet for human-readable display
- Auto-added to `robots.txt`
- Configurable: include/exclude posts, pages, categories, tags

---

### ⚡ IndexNow Integration

Instant search engine notification when content changes:

- **Auto-submit:** Automatically pings IndexNow when posts are published or updated
- **Bulk submit:** Send all frontend-ready URLs at once from the Audit page
- **Key management:** Generates and hosts your IndexNow key at `/.well-known/indexnow.txt`
- **Submission log:** Track every submission with status, reason, URL count, and timestamp
- **Smart triggers:** Fires on post publish, post update, manual submit, and bulk rollout
- Localhost-aware — skips live API calls during development

---

### 🔗 Redirects & 404 Monitor

Professional redirect management built in:

- **Redirect types:** 301 (permanent), 302 (temporary), 307 (temporary, preserve method)
- **Add/delete redirects** via AJAX — no page reloads
- **404 Monitor:** Automatically logs every 404 error with URL, referrer, user agent, and timestamp
- **One-click cleanup:** Clear the 404 log when resolved
- **Hit counter:** Track how many times each redirect fires

---

### 🌐 Frontend SEO Output

When enabled, SEO Captain renders optimized SEO tags in your page `<head>`:

- `<title>` with custom SEO title or template fallback
- `<meta name="description">` with AI-generated or manual description
- `<link rel="canonical">` with custom or auto-detected canonical URL
- `<meta name="robots">` with per-page directives
- Google and Bing webmaster verification tags
- **Per-page toggle:** Enable frontend output globally or per individual post
- **Conflict detection:** Warns if Yoast SEO is active and offers override mode

---

### 🏗️ Schema & Structured Data

JSON-LD structured data output for search engines:

- **Per-page schema type** selection (Article, Product, FAQ, etc.)
- **Organization schema** with social profiles
- **Local Business schema** (see Local SEO below)
- **Breadcrumb schema** via shortcode `[ai_seo_captain_breadcrumbs]`
- Toggle on/off globally via feature flags

---

### 🏢 Local SEO

Complete local business optimization:

- **Business details:** Name, type, address, phone, email
- **Opening hours:** Per-day schedule (Monday through Sunday)
- **Geo-coordinates:** Latitude and longitude for map pins
- **Price range indicator**
- **Map shortcode:** `[ai_seo_map]` renders an interactive map
- **Automatic JSON-LD output** with full LocalBusiness schema

---

### 📱 Social Media Tags

Open Graph and Twitter Card meta tags for rich social sharing:

**Open Graph (Facebook, LinkedIn, etc.):**
- `og:title`, `og:description`, `og:image`, `og:url`, `og:type`

**Twitter Cards:**
- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`

- Per-page social title, description, and image overrides
- Falls back to SEO title/description when social fields are empty
- Social profile links for organization schema (Facebook, Twitter/X, Instagram, LinkedIn, YouTube, Pinterest)

---

### 🕷️ Crawl Budget Optimization

Fine-tune what search engines crawl on your site:

| Setting | What it does |
|---------|--------------|
| Disable author archives | Prevents indexing of `/author/` pages |
| Disable date archives | Prevents indexing of `/2026/05/` pages |
| Disable attachment pages | Prevents indexing of media attachment pages |
| Disable search results indexing | Blocks `/?s=` from search engines |
| Disable format archives | Removes post format archives |
| Remove WordPress version | Strips `<meta name="generator">` tag |
| Remove shortlink | Removes `<link rel="shortlink">` header |
| Remove RSD link | Removes Really Simple Discovery link |
| Remove feed links | Removes RSS/Atom feed discovery links |

Plus per-archive noindex controls for categories, tags, authors, dates, search, and formats.

---

### 📡 RSS Feed Optimization

Control how your content appears in RSS readers:

- **Before/after content HTML:** Add attribution links, CTAs, or copyright notices
- **Featured image in feed:** Optionally include post thumbnails
- **Publication delay:** Hold new content from appearing in the feed for a configurable period (anti-scraping protection)

---

### 🧠 AI Discovery Documents

Make your site discoverable by AI search agents and large language models:

- **`/.well-known/llms.txt`** — Compact version (top 8 pages, 12 content items)
- **`/llms-full.txt`** — Extended version (top 20 pages, 30 content items)
- Auto-generated from your indexed content
- Includes site name, description, canonical URLs, sitemap reference, and top-ranked content
- Machine-readable format following the emerging `llms.txt` standard

---

### 🔤 Search Appearance Templates

Automatic title templates with variable placeholders:

| Variable | Resolves To |
|----------|-------------|
| `%%title%%` | Post/page title |
| `%%sitename%%` | Site name |
| `%%sep%%` | Title separator (configurable: `|`, `-`, `·`, etc.) |
| `%%term_title%%` | Category/tag name |
| `%%author%%` | Author display name |

Templates for: posts, pages, categories, tags, author archives, date archives, search results, general archives, and 404 pages.

---

### 🏷️ Title Branding

Automatic brand suffix on all SEO titles:

- **Site Brand setting** — define your brand name (defaults to site name)
- **Automatic suffix** — appends ` | Brand Name` to every page-specific SEO title
- **Smart budget** — AI prompt enforces a page-title character budget so the full branded title stays within 60 chars
- **Per-page opt-out** — disable branding on individual pages when needed
- **Template-safe** — search appearance templates already contain branding, so the suffix only applies to page-specific titles

---

### 🧠 AI Context Intelligence

The AI generation pipeline sends full real-time context for smarter results:

- **Live browser context** — when generating from the editor, the AI receives your current unsaved field values (title, description, keyphrase, social, schema, canonical, robots, cornerstone) — not stale database values
- **Preserve-if-good** — AI evaluates existing drafts before rewriting. If they're already well-optimized, it returns them unchanged
- **Keyphrase enforcement** — the focus keyphrase must appear naturally in both the SEO title and meta description
- **Full tab data** — AI sees social titles/descriptions, schema type, canonical URL, robots directives, and cornerstone status — not just the SEO tab
- **Keyphrase write-back** — if the user hasn't set a focus keyphrase, the AI-generated one is written back to the editor field automatically

---

### ✍️ AI Content Editor

AI-assisted content improvements with a safe editing workflow:

- **Plan edits:** AI analyzes a page and proposes specific content changes
- **Preview changes:** See pending edits before applying — works in the builder's own editor and in WordPress Preview
- **Apply or discard:** One-click apply writes changes to the post content
- **Backup & restore:** Original content is backed up — restore anytime
- **Multi-builder support:** Works with classic editor, Gutenberg, and all major page builders

---

### 🔌 Page Builder Compatibility

AI content editing, preview, and write-back work natively with every major WordPress page builder — no extra plugins or configuration needed.

| Builder | Storage Detected | Admin Preview | Content Write-back |
|---------|-----------------|---------------|-------------------|
| **Classic Editor** | `post_content` | ✅ via WP Preview | ✅ |
| **Gutenberg (Block Editor)** | `post_content` | ✅ via WP Preview | ✅ |
| **BeTheme / BeBuilder** | `mfn-page-items` | ✅ Live in builder | ✅ |
| **Elementor** | `_elementor_data` | ✅ Live in builder | ✅ |
| **Beaver Builder** | `_fl_builder_data` | ✅ Live in builder | ✅ |
| **Bricks** | `_bricks_page_content_2` | ✅ Live in builder | ✅ |
| **Themify Builder** | `_themify_builder_settings_json` | ✅ Live in builder | ✅ |
| **Oxygen Builder** | `ct_builder_shortcodes` | ✅ Live in builder | ✅ |
| **Thrive Architect** | `tve_updated_post` | ✅ Live in builder | ✅ |
| **Brizy** | `brizy-post-editor-data` | ✅ Live in builder | ✅ |
| **SeedProd** | `_seedprod_page` | ✅ Live in builder | ✅ |
| **Tatsu Builder** | `tatsu_sections` | ✅ Live in builder | ✅ |
| **WPBakery** | `post_content` (shortcodes) | ✅ via WP Preview | ✅ |
| **Divi Builder** | `post_content` (shortcodes) | ✅ via WP Preview | ✅ |

> **How it works:** When AI proposes content changes, they are stored as "pending" and injected into the builder's data on-the-fly. You see the changes in your editor before publishing. On Update/Publish, the changes are committed permanently. No content is ever pushed live without your explicit action.

---

### 💾 Export & Import

Full backup and restore of all plugin data:

**Export includes:**
- All plugin settings and configuration
- Per-page SEO metadata (titles, descriptions, keyphrases)
- Redirect rules
- Cornerstone content flags

**Format:** Single JSON file download — select what to include via checkboxes (settings, metadata, redirects)

---

### 🔄 Yoast SEO Migration

Seamless one-click migration from Yoast SEO:

- Detects all posts with Yoast metadata
- Maps and copies: focus keyphrase, meta title, meta description
- Handles robots directives with basic mapping
- Optionally enables frontend output on imported items
- Skips pages that already have SEO Captain data (safe to run multiple times)
- Detailed report: posts detected, fields imported, items updated, skipped, unsupported directives

---

### 🗑️ Data Management

Comprehensive data cleanup with scope-based clearing:

- **Clear SEO Metadata** — removes 14 post meta keys (focus keyphrase, social fields, canonical URL, robots directives, schema type, title branding, cornerstone, hreflang, pending content changes, content backup, meta title, meta description) plus 4 term meta keys
- **Clear Audits** — removes page audit data and audit skip flags
- **Clear Everything** — all of the above plus approved message IDs, frontend enabled flags, AI conversations, messages, IndexNow log, runs, and active runs user meta
- Confirmation modal always shown before any destructive operation
- Cleanup also runs on plugin uninstall via `uninstall.php`

---

### 🛒 WooCommerce Integration

Product-aware SEO management when WooCommerce is active:

- **Setup Wizard** — Products filter button in the page selector modal
- **Bulk Editor** — Post type dropdown includes "Products" only when WooCommerce is detected
- **Site Structure tree** — Products shown with 🛒 icon in a separate group
- WooCommerce detection is automatic — no configuration needed

---

### 📐 Gutenberg Sidebar

Native block editor integration:

- **Sidebar panel** — SEO fields accessible directly from the Gutenberg sidebar
- Dedicated JS (`gutenberg-sidebar.js`) and CSS (`gutenberg-sidebar.css`) assets
- Auto-enqueued only on block editor screens

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| WordPress | 6.7+ |
| PHP | 7.4+ |
| AI API Key | OpenAI or Google Gemini |
| WooCommerce | Optional — enables product-specific features |

---

## Installation

1. Upload the `ai-seo-captain` folder to `/wp-content/plugins/`
2. Activate the plugin in **Plugins → Installed Plugins**
3. Navigate to **SEO Captain → Settings** and enter your AI provider API key
4. Run the **Setup Wizard** to index your site, generate metadata, and audit all pages

---

## Configuration

After activation, configure the plugin in **SEO Captain → Settings**:

1. **AI Provider** — Choose OpenAI or Google, enter your API key, select model
2. **Feature Toggles** — Enable/disable meta titles, descriptions, Open Graph, Twitter Cards, canonical URLs, robots directives, schema
3. **Frontend Output** — Toggle whether AI-generated metadata renders on the frontend
4. **Search Appearance** — Set title templates and separator for all page types
5. **Sitemap** — Enable XML sitemaps, choose which content types to include
6. **IndexNow** — Enable instant search engine notifications
7. **Local SEO** — Configure business details for local search
8. **Crawl Budget** — Fine-tune what search engines can access
9. **Social Profiles** — Add your social media URLs for organization schema
10. **RSS** — Customize feed output and publication delay

---

## Testing

The plugin includes a PHPUnit test suite:

```bash
php -d extension=php_zip.dll vendor/bin/phpunit --testsuite Unit
```

- **81 tests, 141 assertions** — all passing
- Test stubs in `tests/stubs/` mock WordPress functions for isolated unit testing
- Additional tools: `tests/hallucination-test.php` (AI output validation), `tests/prompt-inspector.php` (prompt debugging)

---

## For Developers

- **Code Architecture:** See [`docs/CODE-MAP.md`](docs/CODE-MAP.md) for the complete codebase map
- **Namespace:** `AI_SEO_Captain` with PSR-4 autoloader (`includes/autoload.php`)
- **Modular admin:** `class-admin.php` is a slim coordinator delegating to focused sub-modules in `includes/admin/` (AJAX, rollout, import/export, taxonomy, SEO analysis)
- **Adding new admin pages:** Create a render stub + view file + optional CSS/JS (auto-detected by filename convention)
- **All AJAX handlers** use WordPress nonces for security
- **All user inputs** are sanitized via `sanitize_text_field()`, `wp_unslash()`, and `esc_*()` functions
- **DB auto-upgrade:** `plugins_loaded` hook checks `ai_seo_captain_db_version` vs `AI_SEO_KEEPER_VERSION` and runs `Activator::activate()` on version mismatch

---

## Support

- **Author:** [Green Coders](https://greencoders.co)
- **Repository:** [github.com/VladStef-GC/AISEO](https://github.com/VladStef-GC/AISEO)

---

*Built with AI. Controlled by you.*
