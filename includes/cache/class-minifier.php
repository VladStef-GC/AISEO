<?php

namespace AI_SEO_Captain\Cache;

/**
 * CSS/JS/HTML minification via output buffer.
 */
class Minifier
{

    /** @var bool */
    private $minify_css;

    /** @var bool */
    private $minify_js;

    /** @var array URL patterns to exclude from minification. */
    private $exclude_patterns;

    public function __construct(array $options = array())
    {
        $this->minify_css = ! empty($options['cache_minify_css']);
        $this->minify_js  = ! empty($options['cache_minify_js']);

        $this->exclude_patterns = array();
        // The settings field cache_exclude_urls is shared; we add nothing extra here.
    }

    /**
     * Register hooks for minification.
     */
    public function register_hooks(): void
    {
        if (is_admin()) {
            return;
        }

        if ($this->minify_css || $this->minify_js) {
            add_action('template_redirect', array($this, 'start_output_buffer'), 1);
        }
    }

    /**
     * Start output buffer for HTML processing.
     */
    public function start_output_buffer(): void
    {
        if (is_admin()) {
            return;
        }

        ob_start(array($this, 'process_html'));
    }

    /**
     * Output buffer callback: minify inline <style> and <script> blocks.
     */
    public function process_html(string $html): string
    {
        if (strlen($html) < 100) {
            return $html;
        }

        if ($this->minify_css) {
            // Minify inline <style> blocks.
            $html = preg_replace_callback(
                '/<style\b([^>]*)>(.*?)<\/style>/is',
                function ($m) {
                    $attrs = $m[1];

                    // Skip blocks with data-no-minify.
                    if (false !== stripos($attrs, 'data-no-minify')) {
                        return $m[0];
                    }

                    return '<style' . $attrs . '>' . self::minify_css($m[2]) . '</style>';
                },
                $html
            );
        }

        if ($this->minify_js) {
            // Minify inline <script> blocks (no src attribute).
            $html = preg_replace_callback(
                '/<script\b((?![^>]*\bsrc\b)[^>]*)>(.*?)<\/script>/is',
                function ($m) {
                    $attrs = $m[1];

                    // Skip blocks with data-no-minify.
                    if (false !== stripos($attrs, 'data-no-minify')) {
                        return $m[0];
                    }

                    // Skip JSON-LD (application/ld+json).
                    if (false !== stripos($attrs, 'application/ld+json')) {
                        return $m[0];
                    }

                    return '<script' . $attrs . '>' . self::minify_js($m[2]) . '</script>';
                },
                $html
            );
        }

        return $html;
    }

    /**
     * Minify CSS string (strip comments, whitespace, collapse rules).
     *
     * Preserves /*! ... * / license comments.
     */
    public static function minify_css(string $css): string
    {
        if ('' === trim($css)) {
            return $css;
        }

        // Protect license comments.
        $protected = array();
        $css = preg_replace_callback('/\/\*!.*?\*\//s', function ($m) use (&$protected) {
            $key = '/*AISC_LICENSE_' . count($protected) . '*/';
            $protected[$key] = $m[0];
            return $key;
        }, $css);

        // Remove normal comments.
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);

        // Collapse whitespace.
        $css = preg_replace('/\s+/', ' ', $css);

        // Remove spaces around certain characters.
        $css = preg_replace('/\s*([{};:,>~+])\s*/', '$1', $css);

        // Remove trailing semicolons in blocks.
        $css = str_replace(';}', '}', $css);

        // Restore license comments.
        foreach ($protected as $key => $value) {
            $css = str_replace($key, $value, $css);
        }

        return trim($css);
    }

    /**
     * Minify JS string (conservative — only strip comments, collapse whitespace).
     *
     * Does NOT attempt AST-level transforms.
     */
    public static function minify_js(string $js): string
    {
        if ('' === trim($js)) {
            return $js;
        }

        // Protect license comments.
        $protected = array();
        $js = preg_replace_callback('/\/\*!.*?\*\//s', function ($m) use (&$protected) {
            $key = '/*AISC_LICENSE_' . count($protected) . '*/';
            $protected[$key] = $m[0];
            return $key;
        }, $js);

        // Protect strings (single-quoted, double-quoted, template literals).
        $strings = array();
        $js = preg_replace_callback('/("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|`(?:[^`\\\\]|\\\\.)*`)/', function ($m) use (&$strings) {
            $key = '"AISC_STR_' . count($strings) . '"';
            $strings[$key] = $m[0];
            return $key;
        }, $js);

        // Remove single-line comments.
        $js = preg_replace('/\/\/[^\n]*/', '', $js);

        // Remove multi-line comments.
        $js = preg_replace('/\/\*.*?\*\//s', '', $js);

        // Collapse whitespace runs to single space.
        $js = preg_replace('/[ \t]+/', ' ', $js);

        // Remove whitespace around newlines.
        $js = preg_replace('/\s*\n\s*/', "\n", $js);

        // Collapse multiple newlines.
        $js = preg_replace('/\n+/', "\n", $js);

        // Restore strings.
        foreach ($strings as $key => $value) {
            $js = str_replace($key, $value, $js);
        }

        // Restore license comments.
        foreach ($protected as $key => $value) {
            $js = str_replace($key, $value, $js);
        }

        return trim($js);
    }
}
