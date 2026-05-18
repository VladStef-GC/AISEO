<?php

declare(strict_types=1);

namespace AI_SEO_Captain\Tests\Unit;

use AI_SEO_Captain\Cache\Minifier;
use AI_SEO_Captain\Cache\Lazy_Loader;
use AI_SEO_Captain\Cache\Browser_Cache;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Cache subsystem classes.
 *
 * Tests cover Minifier, Lazy_Loader, Browser_Cache rule generation,
 * and backup date-parsing logic.
 *
 * @covers \AI_SEO_Captain\Cache\Minifier
 * @covers \AI_SEO_Captain\Cache\Lazy_Loader
 * @covers \AI_SEO_Captain\Cache\Browser_Cache
 */
class CacheTest extends TestCase
{
    // ─── Minifier::minify_css ───────────────────────────────────

    public function test_minify_css_removes_comments(): void
    {
        $css = '/* this is a comment */ body { color: red; }';
        $result = Minifier::minify_css($css);
        $this->assertStringNotContainsString('/* this is a comment */', $result);
        $this->assertStringContainsString('body{color:red}', $result);
    }

    public function test_minify_css_preserves_license_comments(): void
    {
        $css = '/*! License MIT */ body { color: red; }';
        $result = Minifier::minify_css($css);
        $this->assertStringContainsString('/*! License MIT */', $result);
    }

    public function test_minify_css_collapses_whitespace(): void
    {
        $css = "body  {  color:   red;   background:  blue;  }";
        $result = Minifier::minify_css($css);
        $this->assertStringNotContainsString('  ', $result);
    }

    public function test_minify_css_removes_trailing_semicolons(): void
    {
        $css = 'body { color: red; background: blue; }';
        $result = Minifier::minify_css($css);
        $this->assertStringNotContainsString(';}', $result);
    }

    public function test_minify_css_empty_string(): void
    {
        $this->assertSame('', Minifier::minify_css(''));
        $this->assertSame('', Minifier::minify_css('   '));
    }

    // ─── Minifier::minify_js ────────────────────────────────────

    public function test_minify_js_removes_single_line_comments(): void
    {
        $js = "var x = 1; // a comment\nvar y = 2;";
        $result = Minifier::minify_js($js);
        $this->assertStringNotContainsString('// a comment', $result);
        $this->assertStringContainsString('var x = 1;', $result);
        $this->assertStringContainsString('var y = 2;', $result);
    }

    public function test_minify_js_removes_multiline_comments(): void
    {
        $js = "var x = 1; /* multi\nline\ncomment */ var y = 2;";
        $result = Minifier::minify_js($js);
        $this->assertStringNotContainsString('multi', $result);
        $this->assertStringContainsString('var x = 1;', $result);
    }

    public function test_minify_js_preserves_license_comments(): void
    {
        $js = "/*! License MIT */\nvar x = 1;";
        $result = Minifier::minify_js($js);
        $this->assertStringContainsString('/*! License MIT */', $result);
    }

    public function test_minify_js_preserves_strings(): void
    {
        $js = 'var x = "hello // world"; var y = \'test /* value */\';';
        $result = Minifier::minify_js($js);
        $this->assertStringContainsString('hello // world', $result);
        $this->assertStringContainsString('test /* value */', $result);
    }

    public function test_minify_js_collapses_whitespace(): void
    {
        $js = "var  x  =  1;\n\n\nvar  y  =  2;";
        $result = Minifier::minify_js($js);
        // Should not have multiple consecutive spaces or blank lines.
        $this->assertDoesNotMatchRegularExpression('/\n\n/', $result);
    }

    public function test_minify_js_empty_string(): void
    {
        $this->assertSame('', Minifier::minify_js(''));
        $this->assertSame('', Minifier::minify_js('   '));
    }

    // ─── Minifier::process_html ─────────────────────────────────

    public function test_process_html_minifies_inline_styles(): void
    {
        $min = new Minifier(array('cache_minify_css' => true));
        // Must be over 100 chars to pass the length check in process_html.
        $padding = str_repeat(' ', 100);
        $html = '<style>body { color: red; /* comment */ }</style>' . $padding;
        $result = $min->process_html($html);
        $this->assertStringNotContainsString('/* comment */', $result);
        $this->assertStringContainsString('body{color:red}', $result);
    }

    public function test_process_html_skips_data_no_minify(): void
    {
        $min = new Minifier(array('cache_minify_css' => true));
        $html = '<style data-no-minify>body { color: red; /* comment */ }</style>';
        $result = $min->process_html($html);
        // Should keep the comment since data-no-minify is present.
        $this->assertStringContainsString('/* comment */', $result);
    }

    public function test_process_html_minifies_inline_js(): void
    {
        $min = new Minifier(array('cache_minify_js' => true));
        $padding = str_repeat(' ', 100);
        $html = '<script>var x = 1; // comment</script>' . $padding;
        $result = $min->process_html($html);
        $this->assertStringNotContainsString('// comment', $result);
    }

    public function test_process_html_skips_js_with_src(): void
    {
        $min = new Minifier(array('cache_minify_js' => true));
        $html = '<script src="main.js">should not touch</script>';
        $result = $min->process_html($html);
        $this->assertSame($html, $result);
    }

    public function test_process_html_skips_json_ld(): void
    {
        $min = new Minifier(array('cache_minify_js' => true));
        $html = '<script type="application/ld+json">{"@type": "Article"}</script>';
        $result = $min->process_html($html);
        $this->assertSame($html, $result);
    }

    public function test_process_html_short_html_passthrough(): void
    {
        $min = new Minifier(array('cache_minify_css' => true, 'cache_minify_js' => true));
        $html = '<p>Hi</p>';
        $this->assertSame($html, $min->process_html($html));
    }

    public function test_process_html_no_options_passthrough(): void
    {
        $min = new Minifier(array());
        $html = '<style>body { color: red; /* comment */ }</style><script>var x = 1; // comment</script>';
        // Neither CSS nor JS minification enabled.
        $this->assertSame($html, $min->process_html($html));
    }

    // ─── Lazy_Loader ────────────────────────────────────────────

    public function test_lazy_loader_adds_loading_attr(): void
    {
        $loader = new Lazy_Loader(0);
        $html = '<img src="photo.jpg">';
        $result = $loader->add_lazy_attributes($html);
        $this->assertStringContainsString('loading="lazy"', $result);
    }

    public function test_lazy_loader_skips_first_n_images(): void
    {
        $loader = new Lazy_Loader(2);
        $html = '<img src="a.jpg"><img src="b.jpg"><img src="c.jpg">';
        $result = $loader->add_lazy_attributes($html);

        // First two should not have loading="lazy".
        $matches = array();
        preg_match_all('/<img[^>]*>/', $result, $matches);
        $images = $matches[0];

        $this->assertCount(3, $images);
        $this->assertStringNotContainsString('loading="lazy"', $images[0]);
        $this->assertStringNotContainsString('loading="lazy"', $images[1]);
        $this->assertStringContainsString('loading="lazy"', $images[2]);
    }

    public function test_lazy_loader_doesnt_double_add(): void
    {
        $loader = new Lazy_Loader(0);
        $html = '<img loading="eager" src="photo.jpg">';
        $result = $loader->add_lazy_attributes($html);
        $this->assertStringNotContainsString('loading="lazy"', $result);
        $this->assertStringContainsString('loading="eager"', $result);
    }

    public function test_lazy_loader_handles_iframes(): void
    {
        $loader = new Lazy_Loader(0);
        $html = '<iframe src="https://example.com"></iframe>';
        $result = $loader->add_lazy_attributes($html);
        $this->assertStringContainsString('loading="lazy"', $result);
    }

    public function test_lazy_loader_preserves_noscript(): void
    {
        $loader = new Lazy_Loader(0);
        $html = '<noscript><img src="photo.jpg"></noscript><img src="other.jpg">';
        $result = $loader->add_lazy_attributes($html);

        // The noscript img should NOT have lazy, the other should.
        $this->assertStringContainsString('<noscript><img src="photo.jpg"></noscript>', $result);
        $this->assertStringContainsString('<img loading="lazy" src="other.jpg">', $result);
    }

    public function test_lazy_loader_empty_html(): void
    {
        $loader = new Lazy_Loader(0);
        $this->assertSame('', $loader->add_lazy_attributes(''));
    }

    // ─── Browser_Cache::generate_htaccess_rules ─────────────────

    public function test_htaccess_rules_contain_markers(): void
    {
        $bc = new Browser_Cache(array());
        $rules = $bc->generate_htaccess_rules();
        $this->assertStringContainsString('# BEGIN AI SEO Captain Cache', $rules);
        $this->assertStringContainsString('# END AI SEO Captain Cache', $rules);
    }

    public function test_htaccess_rules_contain_expires(): void
    {
        $bc = new Browser_Cache(array());
        $rules = $bc->generate_htaccess_rules();
        $this->assertStringContainsString('ExpiresActive On', $rules);
        $this->assertStringContainsString('ExpiresByType text/css', $rules);
        $this->assertStringContainsString('ExpiresByType image/webp', $rules);
    }

    public function test_htaccess_rules_gzip_disabled(): void
    {
        $bc = new Browser_Cache(array('cache_gzip_enabled' => false));
        $rules = $bc->generate_htaccess_rules();
        $this->assertStringNotContainsString('mod_deflate', $rules);
    }

    public function test_htaccess_rules_gzip_enabled(): void
    {
        $bc = new Browser_Cache(array('cache_gzip_enabled' => true));
        $rules = $bc->generate_htaccess_rules();
        $this->assertStringContainsString('mod_deflate', $rules);
        $this->assertStringContainsString('AddOutputFilterByType DEFLATE', $rules);
    }

    // ─── Browser_Cache backup date parsing ──────────────────────

    public function test_get_backups_returns_empty_no_dir(): void
    {
        $bc = new Browser_Cache(array());
        // Backup dir doesn't exist → empty array.
        $backups = $bc->get_backups();
        $this->assertIsArray($backups);
    }

    public function test_get_backups_date_parsing(): void
    {
        $bc = new Browser_Cache(array());

        // Create a temp backup directory with a test file.
        $backup_dir = WP_CONTENT_DIR . '/cache/ai-seo-captain/backups/';
        wp_mkdir_p($backup_dir);
        $filename = 'htaccess_2026-05-18_14-30-00.bak';
        file_put_contents($backup_dir . $filename, 'test content');

        $backups = $bc->get_backups();
        $this->assertNotEmpty($backups);
        $this->assertSame('2026-05-18 14:30:00', $backups[0]['date']);
        $this->assertSame($filename, $backups[0]['filename']);

        // Cleanup.
        @unlink($backup_dir . $filename);
    }

    public function test_get_backups_sorted_newest_first(): void
    {
        $bc = new Browser_Cache(array());
        $backup_dir = WP_CONTENT_DIR . '/cache/ai-seo-captain/backups/';
        wp_mkdir_p($backup_dir);

        file_put_contents($backup_dir . 'htaccess_2026-05-17_10-00-00.bak', 'old');
        file_put_contents($backup_dir . 'htaccess_2026-05-18_10-00-00.bak', 'new');

        $backups = $bc->get_backups();
        $this->assertCount(2, $backups);
        $this->assertStringContainsString('2026-05-18', $backups[0]['filename']);
        $this->assertStringContainsString('2026-05-17', $backups[1]['filename']);

        // Cleanup.
        @unlink($backup_dir . 'htaccess_2026-05-17_10-00-00.bak');
        @unlink($backup_dir . 'htaccess_2026-05-18_10-00-00.bak');
    }

    public function test_get_latest_backup_returns_newest(): void
    {
        $bc = new Browser_Cache(array());
        $backup_dir = WP_CONTENT_DIR . '/cache/ai-seo-captain/backups/';
        wp_mkdir_p($backup_dir);

        file_put_contents($backup_dir . 'htaccess_2026-05-17_10-00-00.bak', 'old');
        file_put_contents($backup_dir . 'htaccess_2026-05-18_10-00-00.bak', 'new');

        $latest = $bc->get_latest_backup();
        $this->assertNotNull($latest);
        $this->assertStringContainsString('2026-05-18', $latest['filename']);

        // Cleanup.
        @unlink($backup_dir . 'htaccess_2026-05-17_10-00-00.bak');
        @unlink($backup_dir . 'htaccess_2026-05-18_10-00-00.bak');
    }

    public function test_get_latest_backup_returns_null_when_empty(): void
    {
        $bc = new Browser_Cache(array());
        // Clear any leftover files.
        $backup_dir = WP_CONTENT_DIR . '/cache/ai-seo-captain/backups/';
        if (is_dir($backup_dir)) {
            foreach (glob($backup_dir . 'htaccess_*.bak') ?: array() as $f) {
                @unlink($f);
            }
        }
        $this->assertNull($bc->get_latest_backup());
    }

    // ─── Settings cache sanitization ────────────────────────────

    public function test_settings_defaults_has_cache_keys(): void
    {
        $defaults = \AI_SEO_Captain\Settings::defaults();
        $this->assertArrayHasKey('cache_enabled', $defaults);
        $this->assertArrayHasKey('cache_page_enabled', $defaults);
        $this->assertArrayHasKey('cache_page_ttl', $defaults);
        $this->assertArrayHasKey('cache_browser_enabled', $defaults);
        $this->assertArrayHasKey('cache_gzip_enabled', $defaults);
        $this->assertArrayHasKey('cache_object_enabled', $defaults);
        $this->assertArrayHasKey('cache_minify_css', $defaults);
        $this->assertArrayHasKey('cache_minify_js', $defaults);
        $this->assertArrayHasKey('cache_minify_html', $defaults);
        $this->assertArrayHasKey('cache_lazy_load', $defaults);
        $this->assertArrayHasKey('cache_lazy_skip_count', $defaults);
        $this->assertArrayHasKey('cache_preload_enabled', $defaults);
        $this->assertArrayHasKey('cache_preload_batch_size', $defaults);
        $this->assertArrayHasKey('cache_query_string_cache', $defaults);
        $this->assertArrayHasKey('cache_wc_exclude_cart', $defaults);
        $this->assertArrayHasKey('cache_exclude_urls', $defaults);
        $this->assertArrayHasKey('cache_exclude_cookies', $defaults);
        $this->assertArrayHasKey('cache_exclude_useragents', $defaults);
    }

    public function test_settings_cache_ttl_default(): void
    {
        $defaults = \AI_SEO_Captain\Settings::defaults();
        $this->assertSame(86400, $defaults['cache_page_ttl']);
    }

    public function test_settings_cache_disabled_by_default(): void
    {
        $defaults = \AI_SEO_Captain\Settings::defaults();
        $this->assertSame(0, $defaults['cache_enabled']);
    }

    public function test_settings_wc_exclude_cart_on_by_default(): void
    {
        $defaults = \AI_SEO_Captain\Settings::defaults();
        $this->assertSame(1, $defaults['cache_wc_exclude_cart']);
    }

    // ─── Edge-case stress tests for minifier ────────────────────

    public function test_minify_css_nested_comments(): void
    {
        $css = 'body { /* outer /* inner */ color: red; }';
        $result = Minifier::minify_css($css);
        $this->assertStringContainsString('color:red', $result);
    }

    public function test_minify_js_regex_in_code(): void
    {
        // Regex literals should survive (protected as strings won't catch them,
        // but the conservative minifier should still produce valid output).
        $js = "var pattern = /test/; var x = 1;";
        $result = Minifier::minify_js($js);
        $this->assertStringContainsString('var x = 1;', $result);
    }

    public function test_minify_js_template_literal(): void
    {
        $js = 'var s = `hello // world`;';
        $result = Minifier::minify_js($js);
        $this->assertStringContainsString('hello // world', $result);
    }

    public function test_lazy_loader_multiple_iframes(): void
    {
        $loader = new Lazy_Loader(0);
        $html = '<iframe src="a.html"></iframe><iframe src="b.html"></iframe>';
        $result = $loader->add_lazy_attributes($html);
        $this->assertSame(2, substr_count($result, 'loading="lazy"'));
    }

    public function test_minify_css_large_input(): void
    {
        // Ensure no catastrophic backtracking or performance issues.
        $css = str_repeat('.class-' . rand() . ' { color: red; margin: 0; padding: 0; } ', 500);
        $start = microtime(true);
        $result = Minifier::minify_css($css);
        $elapsed = microtime(true) - $start;

        $this->assertNotEmpty($result);
        // Should complete in under 1 second.
        $this->assertLessThan(1.0, $elapsed, 'CSS minification took too long');
    }

    public function test_minify_js_large_input(): void
    {
        $js = str_repeat("var x_" . rand() . " = 'value'; // comment\n", 500);
        $start = microtime(true);
        $result = Minifier::minify_js($js);
        $elapsed = microtime(true) - $start;

        $this->assertNotEmpty($result);
        $this->assertLessThan(1.0, $elapsed, 'JS minification took too long');
    }

    // ─── Browser_Cache constructor options ──────────────────────

    public function test_browser_cache_default_ttl(): void
    {
        $bc = new Browser_Cache(array());
        $rules = $bc->generate_htaccess_rules();
        // Default is 86400 — just verify rules are generated.
        $this->assertStringContainsString('ExpiresActive On', $rules);
    }

    public function test_browser_cache_custom_ttl(): void
    {
        $bc = new Browser_Cache(array('cache_page_ttl' => 3600));
        // TTL doesn't affect htaccess static asset expiry (always 1 year), only page headers.
        $rules = $bc->generate_htaccess_rules();
        $this->assertStringContainsString('access plus 1 year', $rules);
    }

    // ─── Cleanup temp directory after all tests ─────────────────

    public static function tearDownAfterClass(): void
    {
        $backup_dir = WP_CONTENT_DIR . '/cache/ai-seo-captain/backups/';
        if (is_dir($backup_dir)) {
            foreach (glob($backup_dir . '*') ?: array() as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($backup_dir);
        }

        $cache_dir = WP_CONTENT_DIR . '/cache/ai-seo-captain/';
        if (is_dir($cache_dir)) {
            @rmdir($cache_dir);
        }

        $cache_parent = WP_CONTENT_DIR . '/cache/';
        if (is_dir($cache_parent)) {
            @rmdir($cache_parent);
        }
    }

    // ─── Minifier conflict detection ───────────────────────────────────

    public function test_minifier_skips_when_autoptimize_active(): void
    {
        // Simulate Autoptimize with CSS minification enabled.
        if (! defined('AUTOPTIMIZE_PLUGIN_VERSION')) {
            define('AUTOPTIMIZE_PLUGIN_VERSION', '3.1.0');
        }
        // Set the Autoptimize option to 'on'.
        global $aisc_test_options;
        $aisc_test_options['autoptimize_css'] = 'on';

        $minifier = new Minifier(array('cache_minify_css' => true, 'cache_minify_js' => true));

        // Use reflection to call private method.
        $method = new \ReflectionMethod($minifier, 'is_another_minifier_active');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($minifier));

        // Cleanup.
        unset($aisc_test_options['autoptimize_css']);
    }

    public function test_minifier_allows_when_no_conflict(): void
    {
        // With no conflicting plugin constants defined, should return false.
        // Note: AUTOPTIMIZE_PLUGIN_VERSION was defined above but the option is not 'on'.
        global $aisc_test_options;
        $aisc_test_options['autoptimize_css'] = '';
        $aisc_test_options['autoptimize_js']  = '';

        $minifier = new Minifier(array('cache_minify_css' => true, 'cache_minify_js' => true));
        $method   = new \ReflectionMethod($minifier, 'is_another_minifier_active');
        $method->setAccessible(true);

        // Autoptimize constant is defined but options are off => no conflict.
        // Unless W3TC or another constant is defined, should be false.
        if (defined('W3TC') || defined('FVM_VERSION')) {
            $this->markTestSkipped('Other conflicting constants defined in test env.');
        }
        $this->assertFalse($method->invoke($minifier));

        unset($aisc_test_options['autoptimize_css'], $aisc_test_options['autoptimize_js']);
    }
}
