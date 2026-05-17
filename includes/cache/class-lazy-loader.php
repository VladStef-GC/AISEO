<?php

namespace AI_SEO_Captain\Cache;

/**
 * Add native lazy loading to images and iframes.
 */
class Lazy_Loader
{

    /** @var int Number of initial images to skip (above-the-fold). */
    private $skip_count;

    public function __construct(int $skip_count = 2)
    {
        $this->skip_count = max(0, $skip_count);
    }

    /**
     * Register content filters.
     */
    public function register_hooks(): void
    {
        if (is_admin()) {
            return;
        }

        add_filter('the_content', array($this, 'add_lazy_attributes'), 99);
        add_filter('post_thumbnail_html', array($this, 'add_lazy_attributes'), 99);
        add_filter('widget_text_content', array($this, 'add_lazy_attributes'), 99);
    }

    /**
     * Add loading="lazy" to images and iframes.
     */
    public function add_lazy_attributes(string $html): string
    {
        if (empty($html)) {
            return $html;
        }

        // Remove <noscript> blocks from processing.
        $noscript_blocks = array();
        $html = preg_replace_callback('/<noscript\b[^>]*>.*?<\/noscript>/is', function ($m) use (&$noscript_blocks) {
            $key = '<!--AISC_NOSCRIPT_' . count($noscript_blocks) . '-->';
            $noscript_blocks[$key] = $m[0];
            return $key;
        }, $html);

        // Process <img> tags.
        $img_count = 0;
        $skip      = $this->skip_count;

        $html = preg_replace_callback('/<img\b([^>]*)>/i', function ($m) use (&$img_count, $skip) {
            $attrs = $m[1];

            // Already has a loading attribute — don't touch.
            if (preg_match('/\bloading\s*=/i', $attrs)) {
                ++$img_count;
                return $m[0];
            }

            // Skip AMP images.
            if (false !== stripos($m[0], '<amp-img')) {
                return $m[0];
            }

            ++$img_count;

            // Skip first N images (above the fold).
            if ($img_count <= $skip) {
                return $m[0];
            }

            return '<img loading="lazy"' . $attrs . '>';
        }, $html);

        // Process <iframe> tags.
        $html = preg_replace_callback('/<iframe\b([^>]*)>/i', function ($m) {
            $attrs = $m[1];

            // Already has a loading attribute.
            if (preg_match('/\bloading\s*=/i', $attrs)) {
                return $m[0];
            }

            return '<iframe loading="lazy"' . $attrs . '>';
        }, $html);

        // Restore <noscript> blocks.
        foreach ($noscript_blocks as $key => $value) {
            $html = str_replace($key, $value, $html);
        }

        return $html;
    }
}
