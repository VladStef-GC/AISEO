# AI SEO Keeper — Copilot / AI Coding Instructions

These rules apply to ALL AI agents (GitHub Copilot, Claude, GPT, etc.) working on this codebase.

---

## 1. PHP View Files — `@var` Annotations Are Mandatory

**Problem (recurring):** PHP view files in `includes/admin/view-*.php` are included via `require`
inside a method scope (e.g. `Admin::render_audit_page()`). Intelephense reports **P1008 "Undefined variable"**
for every variable in the template because it cannot trace variables across the `require` boundary.

**Wrong (causes 100+ P1008 errors):**
```php
defined('ABSPATH') || exit;
?>
<div><?php echo esc_html($audit_message); ?></div>   <!-- P1008: $audit_message undefined -->
```

**Correct — always add `@var` block immediately after the `defined()` guard:**
```php
defined('ABSPATH') || exit;

/** @var array  $report */
/** @var array  $summary */
/** @var string $audit_message */
/** @var string $audit_status */
// ... one line per injected variable
?>
<div><?php echo esc_html($audit_message); ?></div>   <!-- no P1008 -->
```

**Rule:** Every `view-*.php` file must have a `/** @var Type $varName */` block after `defined('ABSPATH') || exit;`,
listing **every variable injected by the calling render method**. Check the render method in `class-admin.php`
to find the full variable list.

---

## 2. PHP String Encoding — No JavaScript `\u` Escapes in PHP

**Problem (recurring):** When an AI generates PHP strings containing Unicode characters, it sometimes
writes JavaScript-style escape sequences (`\u2014`, `\ud83d\ude80`) inside **PHP single-quoted strings**.
PHP does **not** process `\u` escapes — they are emitted literally to the browser.

**Wrong:**
```php
<h1><?php echo esc_html('\ud83d\ude80 ' . __('Title \u2014 Subtitle', 'ai-seo-keeper')); ?></h1>
// Outputs: \ud83d\ude80 Title \u2014 Subtitle   ← broken
```

**Correct — use the actual UTF-8 characters in source, or keep decorative glyphs as HTML literals:**
```php
<h1>🚀 <?php esc_html_e('Title — Subtitle', 'ai-seo-keeper'); ?></h1>
// Outputs: 🚀 Title — Subtitle   ← correct
```

**Rule:** Never use `\u####` or `\U########` in PHP single-quoted or double-quoted strings.
Always paste the actual UTF-8 character directly into the source file.

---

## 3. i18n — Text Domain Is `ai-seo-keeper`

All translatable strings must use the text domain `ai-seo-keeper`:

```php
__('My string', 'ai-seo-keeper')
_e('My string', 'ai-seo-keeper')
esc_html__('My string', 'ai-seo-keeper')
esc_html_e('My string', 'ai-seo-keeper')
esc_attr__('My string', 'ai-seo-keeper')
esc_attr_e('My string', 'ai-seo-keeper')
```

- Decorative characters (emoji, `&rarr;`, etc.) stay **outside** translation functions.
- Use `sprintf(__('Found %d items', 'ai-seo-keeper'), $count)` for strings with variables.

---

## 4. PowerShell File Writes — No UTF-8 BOM

**Problem:** `Set-Content -Encoding UTF8` in PowerShell 5.1 adds a **BOM** (`\xEF\xBB\xBF`) before `<?php`,
breaking PHP namespace declarations with: *"Namespace declaration statement has to be the very first statement"*.

**Wrong:**
```powershell
Set-Content $file $content -Encoding UTF8       # adds BOM → PHP namespace error
```

**Correct:**
```powershell
[System.IO.File]::WriteAllText($file, $content, [System.Text.UTF8Encoding]::new($false))
```

---

## 5. Plugin Architecture

- **Namespace:** `AI_SEO_Keeper`
- **PSR-4 autoloader root:** `includes/` → `AI_SEO_Keeper\`
- **View files** are in `includes/admin/view-*.php` — they are pure output templates, no business logic.
- **Do not modify** core WordPress files. All plugin code lives under `wp-content/plugins/ai-seo-keeper/`.
- **Module pattern:** New features go in `includes/` sub-classes, registered via `class-admin.php` hooks.

---

## 6. Testing

Run unit tests after every change:
```powershell
cd "wp-content/plugins/ai-seo-keeper"
c:\xampp\php\php.exe -d extension=php_zip.dll vendor\bin\phpunit --testsuite Unit
```

All 74 tests must pass before committing.
