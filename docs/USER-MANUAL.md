# AI SEO Captain — User Manual

**Version**: 1.4.0
**Last Updated**: June 2026

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [Dashboard](#3-dashboard)
4. [Settings](#4-settings)
   - [AI API Settings](#41-ai-api-settings)
   - [General Settings](#42-general-settings)
   - [Tracking and Social](#43-tracking-and-social)
   - [Local SEO / Business Schema](#44-local-seo--business-schema)
   - [RSS Feed Optimization](#45-rss-feed-optimization)
   - [Crawl Budget Optimization](#46-crawl-budget-optimization)
   - [Experiments](#47-experiments)
   - [Migration Tools](#48-migration-tools)
5. [Setup Wizard](#5-setup-wizard)
6. [AI Captain (Site Chat)](#6-ai-captain-site-chat)
7. [SEO Audit](#7-seo-audit)
8. [Bulk SEO Editor](#8-bulk-seo-editor)
9. [Keyword Tracking](#9-keyword-tracking)
10. [Image SEO](#10-image-seo)
11. [Video SEO](#11-video-seo)
12. [Document SEO](#12-document-seo)
13. [Redirects & 404 Monitor](#13-redirects--404-monitor)
    - [Redirects Tab](#131-redirects-tab)
    - [404 Monitor Tab](#132-404-monitor-tab)
    - [Broken Links Tab](#133-broken-links-tab)
    - [URL Editor Tab](#134-url-editor-tab)
14. [Cache & Performance](#14-cache--performance)
15. [Scheduled Tasks](#15-scheduled-tasks)
16. [Google Search Console](#16-google-search-console)
17. [Export / Import](#17-export--import)
18. [Local AI (LM Studio / Ollama)](#18-local-ai-lm-studio--ollama)
19. [The WordPress Editor — SEO Sidebar](#19-the-wordpress-editor--seo-sidebar)
20. [Frontend Output — What Visitors and Search Engines See](#20-frontend-output--what-visitors-and-search-engines-see)
21. [Admin Bar Quick Actions](#21-admin-bar-quick-actions)
22. [Understanding SEO Concepts](#22-understanding-seo-concepts)
23. [Troubleshooting & FAQ](#23-troubleshooting--faq)

---

## 1. Introduction

AI SEO Captain is a WordPress plugin that combines AI-powered content optimization with a full suite of technical SEO tools. It helps you:

- **Generate SEO titles and meta descriptions** using AI (cloud or local models)
- **Manage structured data** (schema.org) automatically
- **Monitor and fix** broken links, 404 errors, and redirect chains
- **Speed up your site** with built-in page caching, browser caching, minification, and lazy loading
- **Track keywords** and detect cannibalization risks
- **Optimize images, videos, and documents** for search visibility
- **Connect to Google Search Console** for real performance data
- **Generate AI discovery files** (llms.txt) for AI crawlers like ChatGPT and Perplexity

The plugin works with cloud AI providers (OpenAI, Anthropic, Google, OpenRouter) or with local AI servers (LM Studio, Ollama) for completely free, private operation.

---

## 2. Getting Started

### Requirements

- WordPress 5.9 or higher
- PHP 7.4 or higher
- An AI provider API key (OpenAI, Anthropic, Google, or OpenRouter), **or** a local AI server (LM Studio/Ollama)

### First-Time Setup

1. **Install and activate** the plugin from your WordPress admin panel
2. Go to **SEO Captain → Settings** and configure your AI provider and API key
3. Run the **Setup Wizard** (SEO Captain → Setup Wizard) to index your site and generate metadata
4. Review generated metadata in the **Bulk SEO Editor**
5. Enable **Frontend Output** in Settings when ready to go live

### Understanding the Workflow

AI SEO Captain uses a **three-stage workflow** to keep you in control:

1. **Draft** — AI generates SEO title and description suggestions (these are proposals, not live)
2. **Approve** — You review and approve the suggestions (or edit them manually)
3. **Frontend Gate** — You enable frontend output per page to make the approved data visible to search engines

This workflow ensures AI never changes what search engines see without your explicit approval.

> **Tip**: If you want a faster workflow, you can skip the approval step by generating metadata directly (not as drafts) in the Setup Wizard. The metadata goes live immediately if Frontend Output is enabled globally.

---

## 3. Dashboard

**Location**: SEO Captain → Dashboard

The dashboard is your control center. It shows:

### Overview Cards

| Card | What It Shows |
|---|---|
| **Indexed content** | Total pages/posts the plugin has scanned and indexed |
| **Provider** | Your current AI provider (OpenAI, Anthropic, Local, etc.) |
| **Last sync** | When the content index was last updated |
| **Published content** | Number of published pages and posts on your site |
| **Approved suggestions** | Pages where you've approved AI-generated metadata |
| **Frontend-ready pages** | Pages where SEO Captain metadata is actually rendering on your live site |

### Coverage Gaps

Shows where you still need to work:

- **Missing AI title drafts** — Pages without an AI-generated title suggestion
- **Missing AI description drafts** — Pages without an AI-generated description
- **Frontend opt-in pages** — Pages where the frontend gate is enabled
- **Yoast conflict protection** — Detects if Yoast SEO is also installed and active

### AI Discovery Surfaces

Links to automatically generated files that help search engines and AI crawlers understand your site:

- **llms.txt** — A lightweight summary of your site for AI crawlers (ChatGPT, Perplexity, etc.). Auto-generated from your indexed content
- **llms-full.txt** — Full version with detailed page descriptions so AI models deeply understand your content
- **Sitemap** — Your XML sitemap for Google, Bing, and other search engines
- **Frontend output status** — Whether approved metadata is being shown to visitors

### Audit Snapshot

A table showing every indexed page with its current state: draft status, approval status, and frontend readiness. Click any page title to edit it directly.

### Current Rollout State

A summary explaining how frontend output, AI discovery documents, and structured data work together.

---

## 4. Settings

**Location**: SEO Captain → Settings

Settings are organized into collapsible accordion sections. Changes apply site-wide.

---

### 4.1. AI API Settings

Configure the AI engine that powers all automated SEO operations.

| Setting | Description |
|---|---|
| **Provider** | Choose your AI provider: OpenAI, Anthropic (Claude), Google (Gemini), OpenRouter, or Local AI (LM Studio/Ollama) |
| **Model** | The specific AI model to use (e.g., `gpt-4o`, `claude-sonnet-4-20250514`, `gemini-pro`). Available models depend on your provider |
| **API Key** | Your provider's API key. Required for cloud providers. Stored securely in the database |
| **Temperature** | Controls how creative the AI is. `0.0` = very predictable, deterministic output. `1.0+` = more creative and varied. **Recommended for SEO: 0.2–0.4** |
| **Context window** | The maximum number of tokens (text units) the AI can process per request. Must match your model's actual limit. Too low = content gets cut off. Too high = wasted API budget. Common values: 4K, 8K, 32K, 128K |
| **AI instructions** | A global system prompt sent with every AI request. Use this to set the tone, language, or writing style for all generated content. Example: *"Write in British English. Keep descriptions under 150 characters. Focus on e-commerce conversion."* |
| **Site context for AI** | Tell the AI about your business: what you sell, your audience, and your goals. This context is included in every AI conversation so it generates relevant, on-brand content |

---

### 4.2. General Settings

#### Features

Toggle individual SEO data types on or off. When a feature is turned off, that data type is not rendered on the frontend — but your saved data is never deleted.

Available features to toggle:
- Meta title
- Meta description
- Social meta tags
- Schema/structured data
- Canonical URLs
- Robots directives

#### Editor Chat

Adds a per-page AI chat panel inside the WordPress editor. When enabled, you can ask the AI to generate or refine SEO data for the specific page you're editing.

#### Frontend Output

**This is the master switch.** When turned off, no SEO Captain data appears on your live site — not AI suggestions, not manual overrides, nothing. Your data stays saved in the database, but nothing is output to HTML.

> **Important**: You must enable this for any SEO Captain metadata to be visible to search engines and visitors.

#### Search Appearance

The baseline SEO template system. It automatically generates titles and descriptions from templates when no AI-generated or manual data exists. This works independently of AI — even without an API key.

**Title Separator**: The character between your page title and site name (e.g., `|`, `-`, `–`). Maximum 3 characters.

**Site Brand**: The brand name appended to titles. Defaults to your WordPress site name.

**Title Templates**: Customize how titles are generated for different content types. Available template variables:

| Variable | Output |
|---|---|
| `%%title%%` | The page/post title |
| `%%sitename%%` | Your site name |
| `%%sep%%` | Your chosen title separator |
| `%%term_title%%` | Category or tag name |
| `%%author%%` | Author display name |
| `%%date%%` | Archive date |
| `%%searchphrase%%` | The user's search query |
| `%%archive_title%%` | Archive page title |

Templates are available for: Posts, Pages, Categories, Tags, Author Archives, Date Archives, Search Results, 404 Page, and a Fallback for unmapped post types.

**Noindex Controls**: Choose which types of pages should be hidden from search engines:

| Option | What It Does |
|---|---|
| **Categories** | Adds `noindex` to category archive pages |
| **Tags** | Adds `noindex` to tag archive pages |
| **Author archives** | Adds `noindex` to author listing pages |
| **Date archives** | Adds `noindex` to date-based archive pages |
| **Search results** | Adds `noindex` to internal search result pages |
| **Format archives** | Adds `noindex` to post format archive pages |

> **What does noindex mean?** It tells Google: "Please don't include this page in your search results." Useful for low-value pages that would dilute your site's quality signals.

#### XML Sitemap

Generates a `sitemap_index.xml` that lists all your published URLs for search engines. When enabled, this replaces WordPress's built-in core sitemaps.

The sitemap system generates:

- **Post sitemap** — All published blog posts
- **Page sitemap** — All published pages
- **Category sitemap** — Categories that contain posts
- **Tag sitemap** — Tags that contain content
- **News sitemap** — Posts from the last 48 hours (Google News format)
- **Video sitemap** — Pages with YouTube/Vimeo embeds

Each sitemap holds up to 1,000 URLs and automatically splits if your site has more. Pages marked as `noindex` are automatically excluded.

The sitemap reference is also added to your `robots.txt` file.

#### WooCommerce Integration

*Only visible when WooCommerce is active.*

| Setting | Description |
|---|---|
| **Enable WooCommerce integration** | Enriches Product schema with price, SKU, rating, brand, and availability data |
| **Include product data in AI context** | Sends product details (price, SKU, categories, etc.) to the AI when generating metadata |
| **Include products in sitemap** | Adds your WooCommerce products to the XML sitemap |
| **Include product categories in sitemap** | Adds product category pages to the sitemap |
| **Include product tags in sitemap** | Adds product tag pages to the sitemap |

When enabled, your product pages get enriched with:
- Full Product schema (price, currency, availability, SKU, brand, ratings)
- Open Graph product tags for social sharing
- GTIN/EAN barcodes (if stored in product meta)

#### Robots.txt Custom Rules

Add custom rules to your `robots.txt` file. Each rule goes on its own line. Example:

```
Disallow: /private/
Disallow: /staging/
Allow: /public-api/
```

#### IndexNow

Instantly notifies Bing, Yandex, and other supporting search engines when you publish or update content. This replaces the traditional wait for search engines to discover your changes naturally.

- Generates and manages an API key automatically
- Skips notifications when running on localhost (development)
- Supports batch URL submissions

---

### 4.3. Tracking and Social

#### Google Tracking or Verification

Paste your Google site verification code or Google Analytics tracking ID. The plugin outputs it as a `<meta>` tag in your site's `<head>` section.

#### Bing Tracking or Verification

Paste your Bing Webmaster Tools verification code. Same behavior as above.

#### Social Profiles

Enter your social media profile URLs (one per line). These are added to your site's Organization schema as `sameAs` links, helping search engines connect your social accounts to your website.

Supported platforms include: Facebook, Twitter/X, Instagram, LinkedIn, YouTube, Pinterest, TikTok, and any other URL.

---

### 4.4. Local SEO / Business Schema

Enable this to output `LocalBusiness` structured data on your homepage. This helps your business appear in Google Maps and local search results ("near me" searches).

| Field | Description |
|---|---|
| **Business type** | Select from a list of schema.org business types (Restaurant, Store, Professional Service, etc.) |
| **Business name** | Your official business name |
| **Street address** | Physical address |
| **City / Locality** | City name |
| **Region / State** | State or region |
| **Postal code** | ZIP or postal code |
| **Country** | Country code (e.g., US, GB, RO) |
| **Phone** | Business phone number |
| **Email** | Business email address |
| **Latitude / Longitude** | GPS coordinates for precise map placement |
| **Opening hours** | Business hours for each day of the week |
| **Price range** | Price indicator (e.g., `$$`, `$$$`) |

---

### 4.5. RSS Feed Optimization

Control how your content appears in RSS feeds to protect against content scraping and improve SEO attribution.

| Setting | Description |
|---|---|
| **Content before RSS items** | Text or HTML prepended to each feed item. Use this for branding or "originally published at" notices |
| **Content after RSS items** | Text or HTML appended to each feed item. Common for backlink attribution |
| **Featured image in feed** | Include the post's featured image in RSS output |
| **Publication delay (minutes)** | Delays feed updates so your content gets indexed by Google before scrapers can copy it. Example: set to 30 minutes to give search engines a head start |

---

### 4.6. Crawl Budget Optimization

Help search engines focus on your important content by removing low-value pages and cleaning up unnecessary HTML.

#### Disable Archive Pages

Redirects low-value archive pages to the homepage (301 redirect). Options:

- **Author archives** — Useful for single-author sites where author pages duplicate the blog
- **Date archives** — Monthly/yearly archives that duplicate your blog listing
- **Format archives** — Aside, Gallery, Link, and other post format archives

#### Clean Up `<head>`

Removes unnecessary tags from your HTML `<head>` section:

- **WordPress version** — Hides your WP version from attackers (security best practice)
- **Shortlinks** — Removes `<link rel="shortlink">` tags
- **RSD links** — Removes Really Simple Discovery links (needed only for XML-RPC)
- **Feed links** — Removes RSS/Atom feed discovery links

---

### 4.7. Experiments

#### Local AI Toggle

Enables the Local AI page in the admin menu. This is the gateway to connecting LM Studio, Ollama, or any OpenAI-compatible local server. See [Chapter 18](#18-local-ai-lm-studio--ollama) for details.

---

### 4.8. Migration Tools

One-click tools to import SEO data from other plugins:

- **Import from Yoast SEO** — Copies Yoast's meta titles, descriptions, focus keyphrases, and social data to SEO Captain fields
- **Import from Rank Math** — Same for Rank Math data
- **Import from SEOPress** — Same for SEOPress data

> **Note**: Migration copies data — it doesn't delete the original plugin's data. You can safely test SEO Captain alongside your current plugin before switching.

---

## 5. Setup Wizard

**Location**: SEO Captain → Setup Wizard

The Setup Wizard walks you through the initial configuration in three steps. It's designed for first-time setup but can be re-run at any time.

### Step 1: Index Your Site

Scans all published pages and posts to build a content index. This index is required before any AI processing can begin.

- Click **Start Indexing** to begin
- Progress is shown in real-time
- Once complete, you'll see a summary of indexed content

### Parallel Requests Setting

Controls how many pages are processed at the same time:

| Value | Best For |
|---|---|
| **1** | Local AI servers (safest, prevents overload) |
| **2–3** | Most cloud providers |
| **5–10** | High-capacity cloud providers with fast API responses |

### Step 2: Generate SEO Metadata

AI reads each page and generates:

- SEO title
- Meta description
- Focus keyphrase
- Keywords
- Social title
- Social description

**Options**:

| Option | Description |
|---|---|
| **Override all Metadata** | AI regenerates ALL fields for every page, even if data already exists |
| **Save as draft** | AI-generated data is saved but won't appear on your site until you approve and publish each page from the editor |

Progress is shown in real-time with per-page status. You can **Pause** or **Stop** the process at any time.

### Step 3: Full SEO Audit

AI reads the full content of each page — body text, headings, images, links, media, and all SEO metadata — then produces a comprehensive audit report:

- SEO score (0-100)
- Detected issues with explanations
- Actionable improvement suggestions
- Prioritized fix list
- Cannibalization risk detection (checks up to 20 related pages)

This is a **read-only analysis** — no SEO fields are modified. Results are cached, so previously audited pages load instantly on subsequent runs.

**Options**:

| Option | Description |
|---|---|
| **Override all Audits** | Re-audits ALL pages, ignoring cached results |
| **Skip patterns** | Exclude pages matching specific URL patterns from auditing |

### Lists

The Setup Wizard creates "Lists" — named groups of pages processed together. You can create multiple lists (e.g., "Blog Posts", "Product Pages", "Landing Pages") and track their progress independently.

---

## 6. AI Captain (Site Chat)

**Location**: SEO Captain → AI Captain

A site-wide AI chat interface where you can discuss your entire site's SEO strategy with the AI. Unlike the per-page editor chat, this has full context of your entire content index.

### What You Can Do

- Ask about your site structure and content gaps
- Get strategic SEO recommendations
- Discuss keyphrase conflicts and cannibalization
- Review audit results and get prioritized action plans
- Ask the AI to analyze specific pages or groups of pages

### Summary Cards

| Card | Description |
|---|---|
| **Published Pages** | Total published content on your site |
| **Readiness Score** | Your overall SEO readiness percentage |
| **Draft Coverage** | Percentage of pages with AI-generated metadata |
| **Approval Coverage** | Percentage of pages with approved suggestions |
| **Frontend Coverage** | Percentage of pages with live SEO Captain output |

### Lists Panel

Shows the lists you created from the Setup Wizard. The AI Captain is enabled when at least one list or the full site has been audited.

### Readiness Levels

The AI chat adapts based on how much data is available:

| Level | Data Available | AI Can Do |
|---|---|---|
| **Level 0** | No metadata indexed | Basic conversation only |
| **Level 1** | Metadata indexed | Strategic advice based on titles, descriptions, and structure |
| **Level 2** | Metadata + page audits | Full analysis with audit scores, issues, and recommendations |

---

## 7. SEO Audit

**Location**: SEO Captain → Audit

The audit page is your operational command center. It combines deterministic analysis (facts from your content) with AI strategic insights.

### Score Cards

| Card | What It Measures |
|---|---|
| **Readiness** | Overall score (0-100) based on draft coverage, approval coverage, and frontend readiness |
| **Draft Coverage** | Percentage of published pages that have AI-generated title and description drafts |
| **Approval Coverage** | Percentage of pages where you've approved the AI suggestion |
| **Frontend Coverage** | Percentage of pages where SEO Captain metadata actually renders on your live site |
| **Search Console (30d)** | Clicks, impressions, and average position from Google (if connected) |

### Coverage Gaps

Shows specific numbers:

- Missing AI title drafts
- Missing AI description drafts
- Frontend opt-in pages vs. frontend-ready pages
- Cornerstone pages count

### IndexNow Status

Shows whether IndexNow is active and allows you to manually submit your priority queue to search engines. Recent IndexNow activity is logged with timestamps, URL counts, and status.

### AI Strategic Audit

Click **Generate AI Strategic Audit** to have the AI analyze your deterministic report and produce:

- An executive summary
- Priority actions (what to do first)
- Quick wins (easy improvements)
- Notes and recommendations

Multiple audits are saved and can be reviewed later or exported as `.txt` files.

### Priority Queue

A table of pages sorted by urgency — published pages missing AI drafts come first. Work through this list top-to-bottom for maximum impact. Columns show:

- Content title and type
- Whether title/description drafts exist
- Approval status
- Frontend gate status

### Approved Rollout Queue

Pages that already have approved AI suggestions, ready for batch frontend enablement. You can:

- **Enable Frontend For Approved Selections** — Makes selected pages' metadata visible to search engines
- **Disable Frontend For Selections** — Hides selected pages' metadata

When IndexNow is enabled, bulk enable actions also send refresh signals to search engines.

### Unapproved Draft Candidates

Pages with AI-generated drafts waiting for your review.

### Thin Content Risks

Pages with very few words. Google may consider them low-quality and rank them lower. Aim for 300+ words on important pages.

### Duplicate Titles

Two sections detect exact duplicate titles:

- **Duplicate Native Titles** — Multiple published pages sharing the same WordPress title
- **Duplicate AI Draft Titles** — Multiple AI-generated title suggestions that are identical

Each duplicate page shares the same title, which can confuse search engines about which one to rank.

### Orphaned Content

Pages with zero internal links pointing to them. Search engine crawlers discover pages by following links, so orphaned pages may never get indexed. The table shows each orphaned page and links to edit it.

---

## 8. Bulk SEO Editor

**Location**: SEO Captain → Bulk Editor

A spreadsheet-style editor for quickly reviewing and editing SEO data across all your content.

### Features

- **Inline editing** — Click any field to edit directly in the table
- **Auto-save** — Changes save via AJAX, no page reload needed
- **Live changes** — All edits are published instantly and visible to visitors right away

> **⚠ Important**: Changes made here are live immediately. There is no draft/approval step in the bulk editor.

### Columns

| Column | Description |
|---|---|
| **Title** | The WordPress page/post title (read-only, click to edit the page) |
| **SEO Title** | The title shown in Google search results. Keep it under 60 characters. Leave empty to use the template from Settings → Search Appearance |
| **Meta Description** | The snippet shown below your title in search results. Keep it under 160 characters to avoid truncation |
| **Keyphrase** | *(Hidden by default)* The main search term you want this page to rank for. Toggle visibility with the checkbox above the table |
| **Keywords** | *(Hidden by default)* Comma-separated keywords for the page. Toggle visibility with the checkbox above the table |

### Filtering & Navigation

- **Post type filter** — Switch between Pages, Posts, Products, etc.
- **Search** — Filter pages by title
- **Sorting** — Click any column header to sort
- **Pagination** — Navigate through large sites

### Site Structure Tree

Below the editor table, a visual tree shows your site's page hierarchy. Expand/collapse branches to understand your content organization.

---

## 9. Keyword Tracking

**Location**: SEO Captain → Keywords

Monitors focus keyphrases across your entire site and detects cannibalization risks.

### Overview Cards

| Card | Description |
|---|---|
| **Unique keyphrases** | Total distinct focus keyphrases across your site |
| **Pages with keyphrase** | Pages that have a focus keyphrase assigned |
| **Without keyphrase** | Pages missing a focus keyphrase — set one in the editor to help AI optimize your SEO |
| **Cannibalization risks** | When multiple pages target the same keyphrase, they compete with each other in Google, splitting your ranking power |

### Cannibalization Warnings

When detected, a warning banner shows:
- The duplicated keyphrase
- All pages targeting it
- Links to edit each page

**How to fix**: Either consolidate the content into one page or differentiate each page's focus keyphrase.

### All Focus Keyphrases Table

A sortable table listing every keyphrase and the page(s) using it. Rows highlighted in yellow indicate cannibalization risks.

---

## 10. Image SEO

**Location**: SEO Captain → Images

Manage alt text for all images attached to or used as featured images on published content.

### Why Alt Text Matters

Alt text (alternative text) describes images for:
- **Search engines** — Google cannot "see" images without alt text
- **Screen readers** — Makes your site accessible to visually impaired visitors
- **Broken images** — Shows when an image fails to load

### Overview Cards

| Card | Description |
|---|---|
| **Total images** | All images found on published content |
| **With alt text** | Images that have alt text |
| **Missing alt text** | Images without alt text — these hurt both SEO and accessibility |

### Features

- **Filter buttons** — View all images, only missing alt, or only with alt
- **Search** — Find images by filename
- **Inline editing** — Edit alt text directly in the table
- **AI Generate Missing Alt** — *(Requires Local AI)* Uses AI to generate alt text for all missing images in bulk. If a vision model is configured, it can actually "see" the image to produce accurate descriptions
- **Purge Cache** — Clears the page cache for all pages using an image when you update its alt text
- **Used on** — Shows which pages use each image

### Writing Good Alt Text

- Be **short and specific** (5-15 words)
- **Describe what's in the image**, not what the page is about
- Include relevant keywords naturally, but don't stuff
- **Good**: "Red hiking boots on a rocky mountain trail at sunset"
- **Bad**: "image1.jpg" or "best hiking boots buy now cheap hiking boots sale"

---

## 11. Video SEO

**Location**: SEO Captain → Videos

Manage SEO metadata for videos embedded in your published content. The plugin automatically detects YouTube, Vimeo, and self-hosted video files.

### Overview Cards

| Card | Description |
|---|---|
| **Total videos** | All videos found across published content |
| **With description** | Videos that have an SEO title and description |
| **Missing description** | Videos without SEO metadata — they won't appear in Google Video search results |

### Features

- **Filter by source** — YouTube, Vimeo, Self-hosted, or all
- **Search** — Find videos by title
- **Inline editing** — Edit SEO title and description directly
- **Preview thumbnails** — See video thumbnails in the table
- **Provider badges** — Color-coded badges show the video source
- **Used on** — Shows which pages contain each video

### Video Sitemap

When XML Sitemap is enabled in Settings, a video sitemap is automatically generated (`/video-sitemap.xml`) containing all detected videos with:
- Video thumbnail URL
- Title and description
- Player URL
- Publication date

This helps Google discover and index your videos for Video search results.

---

## 12. Document SEO

**Location**: SEO Captain → Documents

Manage SEO titles and descriptions for documents uploaded to your site (PDFs, Word, Excel, PowerPoint, etc.).

### Why Document SEO Matters

Documents without SEO metadata use the filename as their search result title (e.g., "report-v3-final.pdf"). Adding a descriptive title and description helps users find your documents through search.

### Supported Formats

PDF, DOC, DOCX, XLS, XLSX, CSV, PPT, PPTX, ODT, ODS, ODP, RTF, TXT

### Features

- **Filter by format** — PDF, Word, Spreadsheet, Presentation, or all
- **Search** — Find documents by filename
- **Inline editing** — Edit title and description directly
- **Format badges** — Visual icons for each document type
- **Used on** — Shows which pages link to each document

---

## 13. Redirects & 404 Monitor

**Location**: SEO Captain → Redirects

A four-tab page for managing URL redirections, monitoring 404 errors, scanning for broken links, and bulk-editing URLs.

---

### 13.1. Redirects Tab

Create and manage URL redirects. When a visitor or search engine requests an old URL, they're automatically sent to the new one.

#### Adding a Redirect

| Field | Description |
|---|---|
| **Source path** | The old URL path that visitors might request. Use relative paths like `/old-page/` |
| **Target URL** | Where to send visitors. Use the full URL like `https://yoursite.com/new-page/` |
| **Type** | The redirect type (see below) |

#### Redirect Types

| Type | When to Use |
|---|---|
| **301 — Permanent** | The page has moved permanently. Tells Google to transfer ranking power to the new URL. **Use this for most cases.** |
| **302 — Temporary** | The page is temporarily at a different URL. Google keeps the old URL in its index |
| **307 — Temporary (strict)** | Like 302 but preserves the HTTP method (POST stays POST). Rarely needed |

#### Redirect Chain Detection

The plugin automatically detects redirect chains — where URL A redirects to URL B, which redirects to URL C. Chains cause:

- Extra loading time for visitors
- SEO power loss at each hop
- Potential crawl budget waste

When chains are detected, a warning banner appears with a **Fix All** button that flattens every chain so each redirect points directly to the final destination.

#### Redirect Table

Shows all active redirects with:
- Source and target URLs
- Redirect type (301/302/307)
- Hit count (how many times the redirect has been used)
- Last hit date
- Delete button

---

### 13.2. 404 Monitor Tab

Tracks URLs that visitors tried to access but don't exist. High-hit 404s should be redirected to relevant pages.

| Column | Description |
|---|---|
| **URL** | The non-existent URL that was requested |
| **Hits** | How many times this 404 was triggered |
| **Last hit** | When it was last triggered |
| **Dismiss** | Remove this entry from the log |

Use the search bar to filter, and **Clear all 404s** to reset the log.

> **Tip**: Sort by "Hits" to find the most impactful 404s. Create redirects for URLs with many hits to recover lost traffic and link equity.

---

### 13.3. Broken Links Tab

Scans all published content for broken internal links and missing media files. Uses database and filesystem checks only — zero HTTP requests, no performance impact.

#### How It Works

1. Click **Scan Now** to start a scan
2. The scanner checks every published post, page, and product
3. Results are categorized by type:

| Type | What It Checks |
|---|---|
| **Images** | Missing image files, broken `<img>` sources |
| **Documents** | Missing PDF, Word, Excel, etc. |
| **Video** | Broken video embeds |
| **Links** | Internal links pointing to non-existent pages |
| **CSS** | Missing stylesheet references |
| **JS** | Missing JavaScript file references |

Each broken entry shows:
- The broken URL
- Which page references it
- Hit count and detection date
- Detailed context

---

### 13.4. URL Editor Tab

Change page and post URL slugs in bulk, with optional automatic 301 redirects from old URLs to new ones.

| Column | Description |
|---|---|
| **Page Title** | The page name (with redirect badge if redirects already point to this page) |
| **Current Slug** | The current URL slug |
| **Change to** | Type the new slug here |
| **Redirect** | Creates a 301 redirect from the old URL to the new URL. **Always recommended** — without it, old links become 404 errors |
| **Update refs** | Finds and updates internal links in your content that point to the old URL. Reduces redirect hops and saves crawl budget |
| **Type** | Post type (Page, Post, Product, etc.) |

> **⚠ Caution**: Changing URLs affects SEO and existing links. Always enable "Redirect" to preserve link equity. Changes are applied immediately after confirmation.

After bulk URL changes, if IndexNow is enabled, the plugin automatically notifies search engines about the changed URLs.

---

## 14. Cache & Performance

**Location**: SEO Captain → Cache

A full caching and performance optimization system built into the plugin.

### Dashboard

The top of the page shows:

| Card | Description |
|---|---|
| **Cache Status** | Active or Inactive |
| **Cached Pages** | Number of cached HTML files and total disk size |
| **Drop-ins** | Status of `advanced-cache.php` and `object-cache.php` WordPress drop-in files. These are PHP files placed in `wp-content/` that WordPress loads very early, making the cache work before the full WordPress stack loads |
| **Last Full Purge** | When the entire cache was last cleared |

### Action Buttons

- **Purge All Cache** — Deletes all cached pages. Every page regenerates fresh on the next visit
- **Preload Cache** — Crawls all sitemap URLs in the background to rebuild the cache, so visitors immediately get fast cached pages

---

### Section 1: Page Cache

Caches full HTML pages to disk so they're served without loading WordPress. Dramatically reduces server response time.

| Setting | Description |
|---|---|
| **Enable Cache** | Master cache toggle |
| **Page Cache** | Cache full HTML pages to disk |
| **Cache TTL** | How long cached pages are valid before expiring. Default: 24 hours. Range: 5 minutes to 7 days |
| **GZIP Compression** | Compresses cached HTML before sending to browsers. Reduces page size by 60-80%. Only needed if your server doesn't already compress responses |
| **Cache Query Strings** | When off, pages with `?param=value` are never cached (safer). Turn on only if your site uses query strings for normal page variations |

#### Advanced Cache Drop-in

Install `advanced-cache.php` to `wp-content/` so cached pages are served before WordPress fully loads. This also sets the `WP_CACHE` constant in `wp-config.php`. A backup of `wp-config.php` is created automatically before any changes.

---

### Section 2: Browser Cache

Tell visitors' browsers to cache static assets locally, reducing repeat downloads.

| Setting | Description |
|---|---|
| **Browser Cache Headers** | Sends `Cache-Control`, `Expires`, and `ETag` HTTP headers |
| **.htaccess Rules** | Installs Apache rules for browser caching. Automatic backups are created before every change (up to 5 kept). A **Restore Backup** button is available for safety |

---

### Section 3: Object Cache

A file-based object cache for database query results and transients. Reduces database load on dynamic pages.

| Setting | Description |
|---|---|
| **Object Cache toggle** | Enable file-based object cache |
| **Drop-in Status** | Install/remove the `object-cache.php` drop-in. If another plugin's object cache is already installed, SEO Captain won't overwrite it |

---

### Section 4: Minification

Reduce file sizes by removing whitespace and comments.

| Setting | Description |
|---|---|
| **Minify CSS** | Removes whitespace from inline CSS |
| **Minify JavaScript** | Conservative JS minification (safe for most sites) |
| **Minify HTML** | Strips whitespace from the full HTML output |

---

### Section 5: Lazy Loading

Defer loading of images and iframes until they're scrolled into view.

| Setting | Description |
|---|---|
| **Lazy Loading** | Adds native `loading="lazy"` to images and iframes |
| **Skip First N Images** | How many above-the-fold images to keep loading immediately. Default: 2. Set to 2-3 to keep your hero/banner images loading right away |

---

### Section 6: Exclusions

Specify what should never be cached.

| Setting | Description |
|---|---|
| **Exclude URL Patterns** | One URL pattern per line. Matching pages won't be cached |
| **Exclude Cookies** | One cookie name per line. Visitors with these cookies see uncached pages. Useful for logged-in users, A/B tests, or affiliate tracking |
| **Exclude User Agents** | One user-agent pattern per line. Matching requests bypass the cache |

---

### Section 7: WooCommerce

*Only visible when WooCommerce is active.*

| Setting | Description |
|---|---|
| **Exclude Cart & Checkout** | Never cache Cart, Checkout, and My Account pages (these are dynamic and should never be cached) |

---

### Section 8: Preloading

| Setting | Description |
|---|---|
| **Auto-Preload** | Automatically preloads purged URLs by crawling your sitemap |
| **Batch Size** | Number of URLs to preload per cron tick. Default: 5. Higher values warm the cache faster but use more server resources |

---

### Section 9: CDN Integration

Rewrite static asset URLs to serve them from a Content Delivery Network.

| Setting | Description |
|---|---|
| **CDN URL** | The base URL of your CDN (e.g., `https://cdn.example.com`). Leave empty to disable |
| **Included directories** | Comma-separated directory paths to rewrite. Default: `wp-content,wp-includes` |
| **Exclude URLs containing** | One pattern per line. URLs containing these strings won't be rewritten to the CDN |

---

## 15. Scheduled Tasks

**Location**: SEO Captain → Scheduled Tasks

Monitor and control all background tasks that the plugin runs automatically via WordPress Cron.

### Summary Cards

| Card | Description |
|---|---|
| **Active** | Tasks running on schedule |
| **Paused** | Tasks you've manually paused |
| **Errors** | Tasks that encountered errors on their last run |
| **Total Tasks** | All registered tasks |

### Task Table

Each task shows:

| Column | Description |
|---|---|
| **Task** | Name and description of what the task does |
| **Schedule** | How often it runs (hourly, daily, etc.) |
| **Status** | Active, Paused, or Error (with error count) |
| **Last Run** | When it last executed and how long it took |
| **Next Run** | When it's scheduled to run next, or "Overdue" if behind schedule |
| **Actions** | Pause/Resume the task, or **Run Now** to execute immediately |

### Execution Log

Shows recent task executions (up to 100 entries), including:
- Timestamp
- Task name
- Status (success/error)
- Duration
- Status message
- Whether it was triggered manually or by schedule

---

## 16. Google Search Console

**Location**: SEO Captain → Search Console

Connect your Google Search Console account to view real search performance data directly in WordPress.

### Setup Process

**Step 1: API Credentials**

1. Go to [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Create a project (or select existing)
3. Enable the "Google Search Console API"
4. Create OAuth 2.0 credentials (Web Application type)
5. Add the Redirect URI shown on the settings page
6. Enter your Client ID and Client Secret

**Step 2: Authorize**

Click the authorization link to grant access to your Search Console data.

**Step 3: Select Site**

Choose which property (website) to pull data from.

### Performance Dashboard

Once connected, you see:

#### Metric Cards

| Metric | Description |
|---|---|
| **Total Clicks** | How many times someone clicked on your site in Google search results |
| **Total Impressions** | How many times your pages appeared in search results, even without clicks. High impressions with low clicks means your titles/descriptions need work |
| **Average CTR** | Click-Through Rate — the percentage of impressions that resulted in clicks. Industry average is 2-5%. Improve with better SEO titles and meta descriptions |
| **Avg. Position** | Your average ranking in Google. 1.0 = first result. Under 10 = page 1. Lower numbers are better |

Click any metric card to toggle it on/off in the trend chart.

#### Performance Trend Chart

A visual graph showing how your metrics change over time. Select a date range with the From/To date pickers.

#### Top Queries Table

Shows which search terms bring visitors to your site, with clicks, impressions, CTR, and position for each.

#### Top Pages Table

Shows which pages get the most search traffic, with the same metrics.

### Actions

| Button | Description |
|---|---|
| **Apply** | Refresh the dashboard with the selected date range |
| **Sync Now** | Pull fresh data from Google (data usually has a 2-3 day delay) |
| **Test** | Test the connection to Google |
| **Disconnect** | Remove the connection and credentials |

---

## 17. Export / Import

**Location**: SEO Captain → Export / Import

Back up your SEO data or migrate it between sites.

### Export

Download a JSON file containing your SEO Captain data. Choose which sections to include:

| Section | What's Included |
|---|---|
| **Plugin Settings** | All configuration options |
| **SEO Metadata (Posts & Pages)** | Titles, descriptions, keyphrases, social data, schema types for all posts and pages |
| **SEO Metadata (Categories & Tags)** | Same for taxonomy terms |
| **Page Audits & Content Index** | AI audit reports and the content index |
| **Redirect Rules** | All 301/302/307 redirects |
| **404 Log** | Recorded 404 errors |
| **Batch Run Lists** | Setup Wizard lists and their progress |
| **AI Chat History** | Conversation history with the AI |

> **Tip**: Use exports to create backups before major changes, or to replicate settings from a staging site to production.

### Import

Upload a previously exported JSON file. The import process has 5 steps:

1. **Upload** — Drag & drop or select your `.json` file
2. **Configure** — Choose your import mode and which sections to import
3. **Preview** — Review how exported pages map to your current site
4. **Import** — Watch progress as data is imported
5. **Done** — Review a summary of what was imported

#### Import Modes

| Mode | Description |
|---|---|
| **Update Import** | Only fills in empty/missing fields. Existing data is never overwritten. **The safest option.** |
| **Overwrite Import** | Export data wins for all matched pages. Extra pages and fields on your site are left alone |
| **Force Import** | Deletes all existing SEO data on matched pages, then replaces with export data. **Use with caution** |

---

## 18. Local AI (LM Studio / Ollama)

**Location**: SEO Captain → Local AI *(requires enabling in Settings → Experiments)*

Connect to a local AI server for free, private SEO operations. Works with LM Studio, Ollama, or any OpenAI-compatible API.

### Benefits

- **Free** — No API costs, unlimited usage
- **Private** — Your content never leaves your network
- **Fast** — No internet latency (if running on the same machine or local network)

### Server Connection

| Setting | Description |
|---|---|
| **Server URL** | Your AI server's address. LM Studio: `http://your-ip:1234`. Ollama: `http://your-ip:11434`. The `/v1` API path is added automatically |
| **API Key** | Optional. Only needed if your server requires authentication |
| **Timeout** | Maximum seconds to wait for a response. Default: 3600 (1 hour). Large models may need long timeouts |

Click **Connect to Server** to test the connection and discover available models.

### Model Configuration

| Setting | Description |
|---|---|
| **Chat Model** | The main AI model for all SEO tasks: generating titles, descriptions, audits, and chat. Larger models (13B+ parameters) give better results but need more RAM |
| **Vision Model** | *(Optional)* A multimodal model that can "see" images and generate accurate alt text. Without this, alt text is generated from filenames and context only. Examples: Qwen2-VL, LLaVA |
| **Context Window** | Must exactly match the context length in your LM Studio or Ollama server settings. Setting it higher than the model's actual limit will cause requests to fail |

> **⚠ Critical**: The Context Window value must match your server's configuration exactly. In LM Studio, check `n_ctx` in model settings. In Ollama, run `ollama show <model>` and check `num_ctx`.

### Capability Assessment

After connecting, click **Test Selected Model** to run a capability assessment. The plugin evaluates your model's:

- Response quality and coherence
- Instruction-following ability
- JSON output capability
- SEO content generation quality

Results are shown with a capability tier rating and recommendations.

---

## 19. The WordPress Editor — SEO Sidebar

When editing any post or page in WordPress, SEO Captain adds a comprehensive metabox with multiple tabs.

### SEO Tab

| Field | Description |
|---|---|
| **Focus keyphrase** | The main search term you want this page to rank for. The AI uses this to optimize title and description suggestions |
| **SEO keywords** | Additional keywords (comma-separated) that describe the page's topic |
| **Meta title** | The title shown in search results. Keep under 60 characters |
| **Meta description** | The snippet below the title in search results. Keep under 160 characters |
| **Title branding** | Toggle to remove the site name from this page's title |
| **Frontend enabled** | The page-level gate. When off, SEO Captain data is not output on this page even if approved |

### Social Tab

| Field | Description |
|---|---|
| **Social title** | Title used for Facebook, Twitter, and other social platforms when sharing this page |
| **Social description** | Description used on social platforms |
| **Social image** | The image shown when the page is shared on social media |

### Schema Tab

| Field | Description |
|---|---|
| **Schema type** | Override the auto-detected schema type. Options include Article, Product, Service, WebPage, FAQPage, etc. |

### Advanced Tab

| Field | Description |
|---|---|
| **Canonical URL** | Override the default canonical URL. Useful when content is intentionally duplicated across URLs |
| **Robots directives** | Custom robots meta directives (noindex, nofollow, etc.) |
| **Cornerstone content** | Mark this page as cornerstone — your most important, comprehensive content |
| **Exclude from sitemap** | Remove this page from the XML sitemap |
| **hreflang** | Define language/region alternates for this page. Auto-detected if you use WPML or Polylang |

### Checks Tab

Shows focus keyphrase readiness checks and an overall SEO score analysis for the current page.

### Links Tab

Displays internal link suggestions to help you interlink related content across your site.

### Chat Accordion

A per-page AI chat panel where you can ask the AI to generate or improve SEO data specifically for the page you're editing. Shows the last 8 messages.

### History Accordion

Shows:
- Recent AI suggestions (last 3)
- Recent content edits (last 5)
- Approved suggestions

### Readiness Accordion

Displays the page's current state:
- Frontend output status (enabled/disabled)
- Page audit score (if available)
- Pending changes indicator

---

## 20. Frontend Output — What Visitors and Search Engines See

When frontend output is enabled and a page has approved metadata, SEO Captain outputs the following in your HTML:

### Meta Tags

```html
<meta name="description" content="Your SEO description" />
<meta name="robots" content="index, follow" />
<link rel="canonical" href="https://yoursite.com/page/" />
```

### Open Graph Tags (Facebook, LinkedIn, etc.)

```html
<meta property="og:type" content="article" />
<meta property="og:title" content="Your Social Title" />
<meta property="og:description" content="Your Social Description" />
<meta property="og:url" content="https://yoursite.com/page/" />
<meta property="og:site_name" content="Your Site Name" />
<meta property="og:image" content="https://yoursite.com/image.jpg" />
```

### Twitter Card Tags

```html
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="Your Social Title" />
<meta name="twitter:description" content="Your Social Description" />
<meta name="twitter:image" content="https://yoursite.com/image.jpg" />
```

### Structured Data (JSON-LD)

SEO Captain outputs a rich schema.org graph as JSON-LD, which helps Google understand your content for rich results:

| Schema Type | When It's Used |
|---|---|
| **WebSite** | Always (includes site search action) |
| **Organization** | Always (with social profile links) |
| **Article** | Blog posts |
| **WebPage** | Default for static pages |
| **Product** | Product pages or URLs containing `/products/` |
| **Service** | Service pages or URLs containing `/services/` |
| **ContactPage** | Pages with "contact" in the URL |
| **AboutPage** | Pages with "about" in the URL |
| **FAQPage** | Pages with 2+ Q&A blocks in content |
| **LocalBusiness** | Homepage (when Local SEO is enabled in Settings) |
| **BreadcrumbList** | Pages with taxonomy or hierarchy |

For WooCommerce products, the Product schema includes:
- Price, currency, and availability
- SKU and brand
- Aggregate ratings and review count
- GTIN/EAN barcodes

### hreflang Tags

If configured (manually or via WPML/Polylang), language alternate tags are output:

```html
<link rel="alternate" hreflang="en" href="https://yoursite.com/page/" />
<link rel="alternate" hreflang="fr" href="https://yoursite.com/fr/page/" />
```

### Verification Tags

If configured in Settings → Tracking and Social:

```html
<meta name="google-site-verification" content="your-code" />
<meta name="msvalidate.01" content="your-code" />
```

---

## 21. Admin Bar Quick Actions

SEO Captain adds quick-access buttons to the WordPress admin bar (the top toolbar):

| Button | What It Does |
|---|---|
| **SEO Captain** | Goes to the dashboard |
| **AI Captain Chat** | Opens the site-wide AI chat |
| **AI Commander Chat** | Opens per-page AI chat (only available when viewing or editing a specific page) |
| **Purge All Cache** | Clears the entire site cache |
| **Purge This Page** | Clears the cache for the current page (only available on singular pages) |

> These buttons are only visible to administrators (users with `manage_options` capability).

---

## 22. Understanding SEO Concepts

A quick reference for non-experts.

### Core Concepts

| Term | What It Means |
|---|---|
| **SEO Title** | The title that appears in Google search results. Different from your page title — it's optimized for search |
| **Meta Description** | The 1-2 sentence summary shown below the title in search results. Doesn't directly affect rankings but heavily influences click-through rate |
| **Focus Keyphrase** | The main search term you want a page to rank for. Each page should target a unique keyphrase |
| **Canonical URL** | Tells search engines "this is the master version of this page." Prevents duplicate content issues |
| **Noindex** | Tells search engines not to include a page in search results. The page still exists — it just won't appear in Google |
| **Schema / Structured Data** | Machine-readable data about your page content. Enables rich results (star ratings, prices, FAQ dropdowns) in Google |
| **Sitemap** | An XML file listing all your pages so search engines can discover them efficiently |
| **Robots.txt** | A text file that tells search engine crawlers which parts of your site they can and cannot access |
| **Alt Text** | Text description of an image, used by search engines and screen readers |
| **301 Redirect** | A permanent redirect from one URL to another. Transfers SEO ranking power to the new URL |
| **404 Error** | "Page not found" — the URL doesn't exist on your site |
| **Crawl Budget** | The number of pages search engines will crawl on your site in a given time period. Larger sites need to be more careful about wasting it |
| **IndexNow** | A protocol for instantly notifying search engines about content changes, instead of waiting for them to discover changes naturally |
| **CTR (Click-Through Rate)** | The percentage of people who click your link after seeing it in search results |
| **Impressions** | How many times your page appeared in search results, regardless of clicks |
| **Keyword Cannibalization** | When multiple pages on your site compete for the same search term, splitting your ranking power |
| **Orphaned Content** | Pages with no internal links pointing to them — invisible to crawlers |
| **Thin Content** | Pages with very little text content that search engines may consider low-quality |
| **Cornerstone Content** | Your most important, comprehensive pages that you want to rank the highest |
| **hreflang** | HTML tags that tell search engines which language and region a page is intended for |
| **Open Graph** | A protocol (created by Facebook) that controls how your pages look when shared on social media |
| **JSON-LD** | A format for embedding structured data in your HTML. Google's preferred format for schema.org data |
| **CDN** | Content Delivery Network — serves your static files (images, CSS, JS) from servers closer to your visitors for faster loading |
| **TTL** | Time To Live — how long a cached page is valid before it needs to be regenerated |
| **llms.txt** | A text file that describes your site for AI language models (ChatGPT, Perplexity, etc.), similar to how robots.txt describes your site for search engines |

---

## 23. Troubleshooting & FAQ

### Common Issues

**Q: My SEO titles and descriptions aren't showing on my site.**

Check these three things in order:
1. **Settings → Frontend Output** must be enabled (the master switch)
2. Each page's **frontend gate** must be enabled (in the editor sidebar)
3. The page must have an **approved suggestion** or manually entered metadata

**Q: The AI is not generating metadata.**

- Check that you have a valid API key in **Settings → AI API Settings**
- Verify your provider is selected correctly
- For Local AI: ensure your server is running and the URL is correct
- Check the **Scheduled Tasks** page for any errors

**Q: My cache isn't working.**

1. Make sure **Enable Cache** is on in **Cache → Page Cache**
2. Install the **Advanced Cache Drop-in** (button in the Page Cache section)
3. Check that `WP_CACHE` is set to `true` in your `wp-config.php`
4. Exclude dynamic pages (cart, checkout, my account) from caching

**Q: I'm getting "Yoast conflict protection: Detected" on the dashboard.**

This means Yoast SEO is also active. SEO Captain detects this and avoids outputting duplicate meta tags. You can:
- Keep both plugins (SEO Captain respects Yoast's output)
- Migrate your data using **Settings → Migration Tools** and deactivate Yoast
- Enable "conflict override" to let SEO Captain take priority

**Q: My redirects show "CHAIN" badges.**

This means you have redirect chains (A → B → C). Click the **Fix All** button to flatten them so each redirect points directly to the final destination.

**Q: IndexNow shows "skipped" in the log.**

This is normal on localhost/development environments. IndexNow only sends real notifications on live (non-localhost) sites.

**Q: How do I make the plugin work with WPML or Polylang?**

hreflang tags are auto-detected from WPML and Polylang. No additional configuration is needed — SEO Captain reads your translation setup and generates the correct hreflang tags automatically.

**Q: What's the difference between AI Captain and AI Commander?**

- **AI Captain** (Site Chat) — Discusses your entire site's SEO strategy with full site context
- **AI Commander** (Editor Chat) — Discusses a specific page's SEO with that page's content in context

---

*This manual covers AI SEO Captain version 1.4.0. Features and settings may change in future versions.*
