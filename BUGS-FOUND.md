# Bugs Found During Documentation Review — May 20, 2026

> **Method:** Full code review during documentation update session.  
> **Rule:** NO CODE FIXES — documentation only. Track bugs here for future resolution.

---

## All Resolved (verified May 20, 2026)

| # | Bug | Resolution |
|---|-----|------------|
| 1 | **Version mismatch** | ✅ Fixed — bumped to `1.3.1` in plugin header and `AI_SEO_CAPTAIN_VERSION` constant |
| 8 | **Term meta key naming in docs** | ✅ Already correct — all docs use `_ai_seo_captain_seo_title` |
| 10 | **`schedule_all()` on every page load** | ✅ Already fixed — guarded by `ai_seo_captain_cron_check` transient (1-day TTL) |

---

## Bugs Found & Fixed (June 2026 Session)

| # | Bug | Location | Fix | Commit |
|---|-----|----------|-----|--------|
| J1 | **`get_model()` on private property** | `class-local-ai-admin.php` `test_json_capability()` | Changed `$provider->get_model()` to `''` (empty string) — `chat()` defaults to `$this->model` | `38793a3` |
| J2 | **`strrpos('}')` JSON corruption** | `class-ai-generator.php` `decode_json_payload()` | Removed naive `strrpos('}')` extraction. Replaced with `extract_json_object_safe()` structure-aware depth tracking | `1e7d6e9` |
| J3 | **Fake capability test** | `class-local-ai-admin.php` | Test now sends real JSON prompt to model and validates parsed output | `38793a3` |
| J4 | **Stop button acting as Pause** | `page-setup-wizard.js` + `view-setup-wizard.php` | Stop now clears all cached data via AJAX, buttons always say "Start" not "Continue" | `e04deec` |
| J5 | **No retry on JSON parse failure** | `class-ai-generator.php` | Added `call_ai_and_decode()` — wraps AI call + JSON decode with automatic 1-retry | `1e7d6e9` |

---

## New Findings (This Session)

| # | Finding | Location | Severity | Details |
|---|---------|----------|----------|---------|
| N1 | **No new functional bugs found** | — | — | All 5 AI data flow paths (Step 2, Step 3, Site Chat, Editor Chat, Page Audit) are consistent and correct. The recent sibling metadata additions (keywords, social_title, social_description) are properly wired through SQL JOINs, context builder, and formatter. |

---

## Resolved Since AUDIT-REPORT.md (Verified May 20, 2026)

| Original # | Fix |
|---|---|
| 2 | Constants renamed from `AI_SEO_KEEPER_*` to `AI_SEO_CAPTAIN_*` |
| 3 | Index button now re-enabled on success (`btn.prop('disabled', false).text('Re-Index Site')`) |
| 4 | Google sitemap ping removed; only Bing is pinged now |
| 5 | `uninstall.php` now includes `_ai_seo_captain_keywords` and `_ai_seo_captain_exclude_sitemap` |
| 6 | Dynamic video meta keys cleaned via `LIKE '_ai_seo_captain_video_title_%'` queries in `uninstall.php` |
| 7 | `Meta_Keys::all_post_meta_keys()` now lists all 20 post meta keys |

---

## Documentation Gaps Fixed (This Session)

| File | What Changed |
|---|---|
| `docs/AI-DATA-FLOW.md` | **Created** — comprehensive reference for all 7 AI modes |
| `docs/plugin-capabilities-and-feature-summary.md` | Added Keywords field, updated post meta count (17→20), updated AI feature descriptions |
| `docs/CODE-MAP.md` | Added `AI-DATA-FLOW.md`, `CACHE-SYSTEM.md`, `PLAN-CACHE-MODULE.md`, `plugin-capabilities-and-feature-summary.md` to file tree |
| `PLAN.md` | Added doc anchors for `AI-DATA-FLOW.md`, `CODE-MAP.md`, `plugin-capabilities-and-feature-summary.md`; updated post meta count; updated date |
| `PROJECT-HANDOFF.md` | Updated snapshot date, added `AI-DATA-FLOW.md` reference, updated post meta count (19→20 + dynamic video) |
| `README.md` | Updated AI generation description to include all 6 generated fields |
| `AUDIT-REPORT.md` | Added resolution status section with per-bug verified status |
