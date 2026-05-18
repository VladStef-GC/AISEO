# SEO Captain — Plugin Capabilities & Feature Summary

**Version:** 1.3.1  
**Date:** 2026-05-14

---

## 1. SEO Metadata Fields (Per-Page/Post)

| Field | AI Can Edit | User Can Edit |
|-------|:-----------:|:-------------:|
| SEO Title (max 60 chars) | ✅ | ✅ |
| Meta Description (max 155 chars) | ✅ | ✅ |
| Focus Keyphrase | ✅ | ✅ |
| Social Title (OG/Twitter) | ✅ | ✅ |
| Social Description | ✅ | ✅ |
| Social Image (Media Library) | ❌ | ✅ |
| Canonical URL override | ❌ | ✅ |
| Robots directives (noindex/nofollow) | ❌ | ✅ |
| Schema type selector | ❌ | ✅ |
| Cornerstone content flag | ❌ | ✅ |
| Hreflang alternate links | ❌ | ✅ |
| Title branding opt-out | ❌ | ✅ |
| Frontend gate (per-page enable) | ❌ | ✅ |
| Audit skip flag | ❌ | ✅ |

**Per-Taxonomy (Category/Tag):** SEO Title, Meta Description, Canonical, Noindex

---

## 2. SEO Analysis & Audit (17 Deterministic Checks)

| Check | What It Measures |
|-------|-----------------|
| Title length | 30–60 chars sweet spot |
| Description length | 70–155 chars sweet spot |
| Keyphrase in title | Focus keyphrase appears in SEO title |
| Keyphrase in description | Focus keyphrase appears in meta description |
| Keyphrase in content | Keyphrase appears naturally in body |
| Keyphrase density | 0.5–2.5% target range |
| Content length | Word count (thin < 300 words) |
| Heading structure | H2–H6 count & distribution |
| Transition words | "however," "therefore," etc. (min 3) |
| Passive voice | % of sentences in passive voice |
| Repeated sentence starts | Consecutive sentences starting same word |
| Long sentences | Sentences > 24 words (max 30%) |
| Long paragraphs | Paragraphs > 120 words |
| Image alt text | Images with/without descriptive alt |
| Internal links | Links to other site pages |
| External links | Outbound links |
| Generic anchors | "click here," "read more" detection |
| Lists (UL/OL) | Structured content presence |
| Question-style headings | FAQ schema opportunity detection |

Plus site-wide: **orphaned content**, **duplicate titles**, **keyword cannibalization**

---

## 3. AI-Powered Features

| Feature | Scope | What AI Does |
|---------|-------|--------------|
| **Generate SEO Metadata** | Per-page | Reads content → produces title + description + keyphrase + social title + social description |
| **Bulk Generate (Wizard)** | Site-wide | Batch AI generation with pause/resume, tracked via Runs |
| **Page Chat** | Per-page | AI assistant in editor sidebar — answers SEO questions, suggests improvements |
| **AI Content Edits** | Per-page | AI proposes text rewrites (headings, alt text, thin content) with approval workflow |
| **Page Audit** | Per-page | AI deep-dive: score 0–100, issues, improvement suggestions |
| **AI Strategist (Site Chat)** | Site-wide | Strategic SEO advice across entire site or focused page set |
| **Site Audit Report** | Site-wide | AI executive summary, priority actions, quick wins |
| **Preserve-if-good** | Per-page | AI evaluates existing drafts before rewriting — keeps if well-optimized |
| **Keyphrase enforcement** | Per-page | AI ensures keyphrase appears naturally in generated title & description |

---

## 4. Frontend SEO Output (Live Site)

| Output | Source |
|--------|--------|
| `<title>` tag | Approved title or template + brand suffix |
| `<meta name="description">` | Approved description or auto-derived |
| `<link rel="canonical">` | Custom override or default URL |
| `<meta name="robots">` | Per-page directives + archive noindex rules |
| Open Graph tags (og:title, og:description, og:image, og:type) | Social fields or post data |
| Twitter Card tags | Social fields or post data |
| Schema JSON-LD | Auto-typed (Article, Product, LocalBusiness, FAQPage, etc.) |
| Hreflang `<link>` tags | Per-page alternates + WPML/Polylang auto-detect |
| Breadcrumb schema + shortcode | `[ai_seo_captain_breadcrumbs]` |
| Google/Bing verification meta | Settings codes |
| Title templates | 9 templates (post, page, category, tag, author, date, search, archive, 404) |
| Separator & brand suffix | Configurable `|` + site brand |

---

## 5. Sitemaps & Discovery

| Sitemap | Description |
|---------|------------|
| `/sitemap_index.xml` | Master index listing all sub-sitemaps |
| `/post-sitemap.xml` | All published posts |
| `/page-sitemap.xml` | All published pages |
| `/category-sitemap.xml` | Category archives |
| `/post_tag-sitemap.xml` | Tag archives |
| `/product-sitemap.xml` | WooCommerce products |
| `/product_cat-sitemap.xml` | Product categories |
| `/product_tag-sitemap.xml` | Product tags |
| `/news-sitemap.xml` | Google News (last 48h) |
| `/video-sitemap.xml` | YouTube/Vimeo embeds |
| `/sitemap-xsl.xml` | Browser-friendly stylesheet |
| `/llms.txt` + `/llms-full.txt` | AI discovery documents |
| `robots.txt` | Auto-generated + custom rules + sitemap link |

---

## 6. Technical SEO (Plugin-Only, No AI)

| Feature | Details |
|---------|---------|
| **Redirect Manager** | 301/302/307 redirects with hit tracking |
| **404 Monitor** | Auto-logs broken URLs with hit count and timestamps; sortable + searchable table |
| **Broken Link Scanner** | 5-phase scan (content, menus, attachments, trashed, 404 cross-ref); type filters, pagination, real hit counts |
| **IndexNow** | Auto-submit to Bing/Yandex on content updates |
| **Crawl Budget** | Disable author/date/format/attachment archives (301→home) |
| **Head Cleanup** | Remove WP version, shortlink, RSD link, feed links |
| **RSS Feed** | Before/after content, featured image, publication delay |
| **Robots.txt Editor** | Custom rules textarea in settings |
| **Search Indexing Block** | Disable search result indexing |
| **Local SEO** | LocalBusiness schema + `[ai_seo_map]` shortcode |
| **Social Profiles** | 6 URLs → Organization `sameAs` schema |
| **Webmaster Verification** | Google & Bing meta tags |

---

## 7. Data Management

| Feature | Details |
|---------|---------|
| **Export** | Settings + SEO metadata → JSON download |
| **Import** | Upload JSON → merge/overwrite metadata |
| **Yoast Migration** | One-click import of Yoast titles, descriptions, keyphrases, social |
| **Bulk Editor** | Spreadsheet-style inline edit for all posts/pages |
| **Image SEO Dashboard** | Alt text editor with filter/search/sort |
| **Video SEO Dashboard** | SEO title/description for YouTube, Vimeo, and self-hosted videos; provider badges; filters by source/status |
| **Document SEO Dashboard** | SEO title/description for PDF, Word, Excel, PowerPoint, OpenDocument, RTF, TXT; format icons; "Used on" tracking |
| **Keyword Tracking** | Keyphrase distribution, cannibalization detection |
| **Clear SEO Data** | Scope-based clearing (metadata, audits, history, all) |
| **Runs (Named Batches)** | Create/track named page lists for repeated batch operations |

---

## 8. Integrations

| Integration | What It Does |
|-------------|-------------|
| **WooCommerce** | Product schema (price, SKU, availability, ratings), OG price tags, product sitemaps |
| **Gutenberg Sidebar** | REST API meta registration + sidebar panel |
| **Classic Editor** | Full metabox with tabs (SEO, Social, Advanced, Checks, Chat) |
| **BeTheme** | Parses `mfn-page-items` for real content + heading rewrites |
| **Elementor** | Reads `_elementor_data` for content extraction |
| **WPML / Polylang** | Auto-detect translations → hreflang output |
| **10 Page Builders** | Content extraction: Beaver, Bricks, Themify, Oxygen, Thrive, Brizy, SeedProd, Tatsu |

---

## 9. Admin Pages

| Page | Purpose |
|------|---------|
| **Dashboard** | Overview stats, recent activity, quick actions |
| **Settings** | 4-section accordion: API, Settings, Tracking/Social, Local SEO |
| **Setup Wizard** | 3-step onboarding: Index → Generate → Audit |
| **Audit** | Coverage metrics, IndexNow status, priority recommendations |
| **Bulk Editor** | Inline table editor for all posts/pages |
| **AI Strategist** | Strategic AI conversation (site-wide or focused pages) |
| **Image SEO** | Alt text editor with filter/search/sort |
| **Video SEO** | Video SEO metadata: YouTube, Vimeo, self-hosted (9 formats) |
| **Document SEO** | Document metadata: PDF, Word, Excel, PPT, OpenDocument, RTF, TXT |
| **Keywords** | Keyphrase analysis, distribution, conflicts |
| **Export/Import** | Data migration, Yoast import |
| **Redirects** | Redirect manager + 404 monitor + broken link scanner |

---

## 10. Database

| Table | Purpose |
|-------|---------|
| `wp_ai_seo_captain_content_index` | Site content index for AI context |
| `wp_ai_seo_captain_conversations` | Chat conversation containers |
| `wp_ai_seo_captain_messages` | Individual chat messages (AI + user) |
| `wp_ai_seo_captain_redirects` | Redirects + 404 log + broken link/media entries |
| `wp_ai_seo_captain_runs` | Named batch operation tracking |

**Post Meta Keys:** 17 (`_ai_seo_captain_*`)  
**Term Meta Keys:** 4 (`_ai_seo_captain_*`)  
**Settings:** 70+ options in single `ai_seo_captain_settings` row

---

## Totals

- **17** post meta fields
- **4** term meta fields
- **17** deterministic SEO checks
- **9** AI-powered features
- **13** sitemap/discovery endpoints
- **11** technical SEO tools
- **10** page builder integrations
- **70+** configurable settings
- **10** admin pages
- **5** database tables
