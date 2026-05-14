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
- **PSR-4 autoloader:** `includes/autoload.php` maps `AI_SEO_Keeper\ClassName` → `includes/class-classname.php`
- **Admin sub-modules:** `includes/admin/class-admin-ajax.php`, `class-admin-rollout.php`, `class-admin-import-export.php`, `class-admin-taxonomy.php`, `class-seo-analysis.php`
- **View files** are in `includes/admin/view-*.php` — they are pure output templates, no business logic.
- **Do not modify** core WordPress files. All plugin code lives under `wp-content/plugins/ai-seo-keeper/`.
- **Module pattern:** New features go in `includes/` sub-classes, registered via `class-admin.php` hooks.
- **Asset auto-loading:** Create `assets/js/page-{slug}.js` or `assets/css/page-{slug}.css` — auto-enqueued by slug.
- **DB auto-upgrade:** `plugins_loaded` checks `ai_seo_keeper_db_version` vs `AI_SEO_KEEPER_VERSION`.
- **Current version:** 1.3.1 with 5 SQL tables, 17 post meta keys, 4 term meta keys, 10 admin pages.

---

## 7. View-Called Methods Must Be `public`

**Problem (recurring — Intelephense P1080):** When a render method on `Admin` (or any class) is called
from a view file via `$admin->someMethod(...)`, that method **must be `public`**. If it is `private`
or `protected`, Intelephense reports *"Cannot access private method"* and PHP will fatal-error if
called from outside the class scope (which `require`-based views are).

**Wrong:**
```php
// class-admin.php
private function render_audit_post_links(array $ids): string { ... }

// view-audit.php — injected as $admin
echo $admin->render_audit_post_links($group['ids']);  // P1080: cannot access private method
```

**Correct:**
```php
// class-admin.php
public function render_audit_post_links(array $ids): string { ... }
```

**Rule:** Any method called from a `view-*.php` template through an injected object (`$admin`, etc.)
must be declared `public`. Never inject `$this` as `$admin` and then call private helpers from the view.
If a method is view-only and should not be part of the public API, prefix its name with `render_` to
signal intent but keep it `public`.


Run unit tests after every change:
```powershell
cd "wp-content/plugins/ai-seo-keeper"
c:\xampp\php\php.exe -d extension=php_zip.dll vendor\bin\phpunit --testsuite Unit
```

All 81 tests must pass before committing.
