=== SEO Captain - AI SEO Copilot for WordPress ===
Contributors: greencoders, freemius
Tags: seo, ai seo, meta description, schema, sitemap
Requires at least: 6.7
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The AI-powered SEO copilot for WordPress: generate metadata, run page audits, build schema & sitemaps, and notify search engines — all in one plugin.

== Description ==

**SEO Captain** is an AI-assisted SEO copilot for WordPress. It generates, manages, and optimizes every part of your site's SEO — from meta tags and schema markup to full page audits, content editing, and instant search-engine notifications — from a single, friendly dashboard.

Bring your own AI provider (OpenAI GPT, Google Gemini) or run a **Local AI** model privately on your own hardware for zero cloud cost and full data privacy.

= What you get for free =

* **AI-Generated SEO Metadata** — one-click optimized titles, descriptions, focus keyphrases, and social tags (up to 30 AI-generated pages on the Free plan; manual SEO editing is always unlimited).
* **Editor Metabox & Gutenberg Sidebar** — edit and approve SEO for any post or page.
* **Approval Workflow** — AI suggestions stay as drafts until you approve them.
* **Page Audits with Scoring** — every page gets an SEO score (0–100) with specific, actionable fixes.
* **Bulk SEO Editor** — review and edit metadata across many pages at once.
* **Image, Video & Document SEO** — manage alt text and metadata for media and files.
* **AI SEO Strategist Chat** — a site-wide assistant scoped to your pages and saved lists.
* **Advanced XML Sitemaps** — standard, news, and video sitemaps with XSL styling.
* **IndexNow** — instantly notify search engines when content changes.
* **Redirects & 404 Monitor** — manage redirects and catch broken URLs.
* **Schema & Structured Data** — rich results, local business, and social tags.
* **AI Discovery Documents** — auto-generated `llms.txt` for AI search agents.
* **Yoast SEO Migration** — one-click import of your existing Yoast metadata.
* **Scheduled Tasks Manager** — built-in cron dashboard with health checks.

= Unlock with Pro =

* **Unlimited AI generation** — no page cap.
* **Google Search Console integration** — performance data right inside WordPress and the editor.
* **High-performance Cache System** — page & object caching with smart preloading.
* **Export / Import** — migrate all SEO data and settings between sites.
* **Broken Link Scanner** — site-wide scanning for broken links and media.
* **WooCommerce Integration** — product-aware wizard, bulk editor, and keyword tracking.
* **Local AI module** — private, on-device AI for metadata and image alt text.
* **Headless REST API** — SEO data for decoupled / headless front-ends.

[Upgrade to Pro »](https://greencoders.net/seo-captain/)

== Frequently Asked Questions ==

= Do I need an API key? =
To use the cloud AI providers (OpenAI or Google Gemini) you supply your own API key. Alternatively, run a Local AI model (LM Studio / Ollama) with no key and no cloud cost.

= Does it work alongside Yoast or Rank Math? =
SEO Captain is a full SEO solution and is designed to replace them. It includes a one-click Yoast migration so you don't lose your existing metadata. Running two SEO plugins that both output meta tags at once is not recommended.

= Will AI changes go live automatically? =
No. AI suggestions stay as drafts until you explicitly approve them, so nothing is published without your say.

= Is my content sent to the cloud? =
Only when you use a cloud AI provider, and only the content needed to generate suggestions. With the Local AI module (Pro) nothing leaves your server.

= How many pages can the Free plan generate? =
The Free plan includes 30 AI-generated pages. Manual SEO editing, audits, sitemaps, and the other free features remain unlimited. Pro removes the AI generation cap.

== Screenshots ==

1. Guided setup wizard.
2. AI-generated SEO metadata in the editor sidebar.
3. Full page audit with score and suggestions.
4. Bulk SEO editor.
5. AI SEO Strategist chat.
6. Google Search Console dashboard (Pro).

== Changelog ==

= 1.4.0 =
* New: Native Contact & Feedback page (replaces the third-party contact iframe).
* New: Server-side Pro enforcement on all premium AJAX endpoints (Search Console, Export/Import, cache settings).
* New: Freemius uninstall event is now fired so licensing data is cleaned up correctly.
* Improvement: Account page and Freemius UI now match the plugin's global button styling.
* Various stability and documentation fixes.

= 1.3.1 =
* AI data-flow consistency fixes across generation, chat, and audit modes.
* JSON parsing hardened with structure-aware extraction and automatic retry.
* Uninstall cleanup expanded to cover all post/term meta and dynamic video keys.

== Upgrade Notice ==

= 1.4.0 =
Adds proper Pro license enforcement and a native contact page. Recommended for all users.
