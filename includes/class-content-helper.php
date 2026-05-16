<?php

namespace AI_SEO_Captain;

/**
 * Universal content extractor for WordPress posts.
 *
 * Works regardless of page builder by using a layered strategy:
 *   1. Custom filter hook (lets any theme/builder provide content)
 *   2. Shortcode-stripped post_content (WPBakery, Divi, Avada, Cornerstone …)
 *   3. Known page-builder meta keys (BeTheme, Elementor, Beaver, Bricks, Oxygen, Thrive, Brizy, Themify …)
 *   4. WordPress rendering pipeline fallback (the_content filter)
 *
 * Results are cached per request to avoid duplicate meta lookups during bulk operations.
 */
class Content_Helper
{
    /** @var array<int,string> Per-request cache keyed by post ID. */
    private static array $cache = array();

    /** Recursion guard for the_content filter fallback. */
    private static bool $resolving = false;

    /** Minimum word count to consider content "meaningful". */
    private const MIN_WORDS = 20;

    /**
     * Return the best available body content for a post.
     *
     * @param \WP_Post|int $post Post object or ID.
     * @return string Raw HTML/text content (may contain HTML tags).
     */
    public static function get_content($post): string
    {
        if (is_numeric($post)) {
            $post = get_post((int) $post);
        }

        if (! $post instanceof \WP_Post) {
            return '';
        }

        if (isset(self::$cache[$post->ID])) {
            return self::$cache[$post->ID];
        }

        $content = self::resolve_content($post);
        self::$cache[$post->ID] = $content;

        return $content;
    }

    /**
     * Clear the per-request cache (useful in long-running CLI scripts).
     */
    public static function flush_cache(): void
    {
        self::$cache = array();
    }

    // ------------------------------------------------------------------
    //  Resolution pipeline
    // ------------------------------------------------------------------

    private static function resolve_content(\WP_Post $post): string
    {
        // 0. Allow any theme, builder, or ACF integration to supply content.
        $custom = apply_filters('ai_seo_captain_post_content', '', $post);

        if (is_string($custom) && self::is_meaningful($custom)) {
            return $custom;
        }

        // 1. Try post_content with shortcode brackets removed.
        //    Covers: Gutenberg, Classic Editor, WPBakery, Divi, Avada,
        //    Cornerstone, TagDiv Composer, Live Composer, and any builder
        //    that stores shortcodes in post_content.
        $raw = (string) $post->post_content;

        if ('' !== trim($raw)) {
            $cleaned = self::strip_shortcode_tags($raw);

            if (self::is_meaningful($cleaned)) {
                return $cleaned;
            }
        }

        // 2. Check known page-builder meta fields.
        $builder = self::extract_builder_content($post->ID);

        if ('' !== $builder) {
            return $builder;
        }

        // 3. Last resort: render through WordPress the_content pipeline.
        //    Many builders hook into this filter to inject their output.
        $rendered = self::try_rendered_content($post);

        if (self::is_meaningful($rendered)) {
            return $rendered;
        }

        // Return whatever raw content we have, even if minimal.
        return '' !== trim($raw) ? self::strip_shortcode_tags($raw) : '';
    }

    // ------------------------------------------------------------------
    //  Step 1 helpers – shortcode-based builders
    // ------------------------------------------------------------------

    /**
     * Strip shortcode bracket tags but preserve inner text content.
     *
     * Turns `[vc_column width="1/2"]Hello world[/vc_column]`
     * into `Hello world`.
     */
    private static function strip_shortcode_tags(string $content): string
    {
        if ('' === trim($content)) {
            return '';
        }

        return (string) preg_replace('/\[\/?[a-zA-Z_][a-zA-Z0-9_]*(?:\s[^\]]*?)?\]/', '', $content);
    }

    // ------------------------------------------------------------------
    //  Step 2 – known builder meta keys
    // ------------------------------------------------------------------

    /**
     * Walk a prioritized list of known page-builder meta keys and return
     * the first one that yields meaningful content.
     */
    private static function extract_builder_content(int $post_id): string
    {
        // --- Plain HTML / text fields (cheapest to read) ----------------

        // BeTheme / Muffin Builder – parse actual builder data for correct heading tags.
        $result = self::extract_betheme_content($post_id);
        if ('' !== $result) {
            return $result;
        }

        // Thrive Architect – stores fully rendered HTML.
        $result = self::try_string_meta($post_id, 'tve_updated_post');
        if ('' !== $result) {
            return $result;
        }

        // Oxygen Builder – stores shortcodes.
        $oxy = self::try_string_meta($post_id, 'ct_builder_shortcodes');
        if ('' !== $oxy) {
            return self::strip_shortcode_tags($oxy);
        }

        // --- JSON fields (need recursive extraction) --------------------

        // Elementor.
        $elementor_raw = get_post_meta($post_id, '_elementor_data', true);
        if (is_string($elementor_raw) && '' !== trim($elementor_raw)) {
            $decoded = json_decode($elementor_raw, true);
            if (is_array($decoded)) {
                $text = self::extract_structured_text($decoded);
                if ('' !== $text) {
                    return $text;
                }
            }
        }

        // Themify Builder.
        $themify_raw = get_post_meta($post_id, '_themify_builder_settings_json', true);
        if (is_string($themify_raw) && '' !== trim($themify_raw)) {
            $decoded = json_decode($themify_raw, true);
            if (is_array($decoded)) {
                $text = self::extract_structured_text($decoded);
                if ('' !== $text) {
                    return $text;
                }
            }
        }

        // --- Serialized PHP fields (need recursive extraction) ----------

        // Beaver Builder.
        $bb_data = get_post_meta($post_id, '_fl_builder_data', true);
        if (is_array($bb_data) && ! empty($bb_data)) {
            $text = self::extract_structured_text($bb_data);
            if ('' !== $text) {
                return $text;
            }
        }

        // Bricks Builder.
        $bricks = get_post_meta($post_id, '_bricks_page_content_2', true);
        if (is_array($bricks) && ! empty($bricks)) {
            $text = self::extract_structured_text($bricks);
            if ('' !== $text) {
                return $text;
            }
        }

        // Brizy – compiled editor HTML.
        $brizy = get_post_meta($post_id, 'brizy_post_uid', true);
        if (! empty($brizy)) {
            $result = self::try_string_meta($post_id, 'brizy-post-editor-data');
            if ('' !== $result) {
                return $result;
            }
        }

        // SeedProd.
        $seedprod = get_post_meta($post_id, '_seedprod_page', true);
        if (is_string($seedprod) && '' !== trim($seedprod)) {
            $decoded = json_decode($seedprod, true);
            if (is_array($decoded)) {
                $text = self::extract_structured_text($decoded);
                if ('' !== $text) {
                    return $text;
                }
            }
        }

        // Tatsu (Flavor theme).
        $tatsu = get_post_meta($post_id, 'tatsu_sections', true);
        if (is_array($tatsu) && ! empty($tatsu)) {
            $text = self::extract_structured_text($tatsu);
            if ('' !== $text) {
                return $text;
            }
        }

        return '';
    }

    // ------------------------------------------------------------------
    //  Step 3 – WordPress rendering pipeline fallback
    // ------------------------------------------------------------------

    /**
     * Render post_content through the_content filter.
     *
     * Many builders that store data in meta fields still hook into
     * the_content to inject their output during a normal page load.
     * This catches those cases when all other methods fail.
     */
    private static function try_rendered_content(\WP_Post $post_obj): string
    {
        // Nothing to render.
        if ('' === trim((string) $post_obj->post_content) && ! self::has_any_builder_flag($post_obj->ID)) {
            return '';
        }

        // Prevent infinite recursion if someone calls get_content()
        // from within a the_content filter.
        if (self::$resolving) {
            return '';
        }

        self::$resolving = true;

        // Preserve global state.
        global $post;
        $saved_post = $post;
        $post = $post_obj;
        setup_postdata($post_obj);

        $rendered = apply_filters('the_content', (string) $post_obj->post_content);

        // Restore.
        $post = $saved_post;
        if ($saved_post instanceof \WP_Post) {
            setup_postdata($saved_post);
        } else {
            wp_reset_postdata();
        }

        self::$resolving = false;

        return is_string($rendered) ? $rendered : '';
    }

    /**
     * Quick check: does this post have any known builder meta flag?
     *
     * Used to decide whether the the_content fallback is worth trying
     * even when post_content is empty (builders may still inject via filter).
     */
    private static function has_any_builder_flag(int $post_id): bool
    {
        $flags = array(
            '_elementor_edit_mode',   // Elementor marks active posts.
            '_fl_builder_enabled',    // Beaver Builder flag.
            '_bricks_editor_mode',    // Bricks.
            'ct_other_template',      // Oxygen active flag.
            'tve_landing_page',       // Thrive.
        );

        foreach ($flags as $key) {
            $val = get_post_meta($post_id, $key, true);
            if (! empty($val)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    //  BeTheme / Muffin Builder parser
    // ------------------------------------------------------------------

    /**
     * Parse BeTheme's mfn-page-items (base64-encoded serialized array)
     * and reconstruct HTML with CORRECT heading tags.
     *
     * BeTheme stores heading levels in attr['header_tag'] for heading items,
     * and inline HTML (with correct tags) in attr['content'] for column items.
     * The mfn-page-items-seo field is unreliable for heading levels.
     */
    private static function extract_betheme_content(int $post_id): string
    {
        $raw = get_post_meta($post_id, 'mfn-page-items', true);

        if (! is_string($raw) || '' === trim($raw)) {
            return '';
        }

        // BeTheme stores base64-encoded serialized PHP arrays.
        $decoded = base64_decode($raw, true);
        if (false === $decoded) {
            return '';
        }

        $items = @unserialize($decoded);
        if (! is_array($items)) {
            return '';
        }

        $texts = array();
        self::walk_betheme_items($items, $texts);

        $content = implode("\n", $texts);

        return self::is_meaningful($content) ? $content : '';
    }

    /**
     * Recursively walk BeTheme builder items and collect content with correct tags.
     */
    private static function walk_betheme_items(array $data, array &$texts, int $depth = 0): void
    {
        if ($depth > 15) {
            return;
        }

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? '';

            // Heading items: use attr['header_tag'] and attr['title'].
            if ('heading' === $type && isset($item['attr']['title'])) {
                $tag = $item['attr']['header_tag'] ?? 'div';
                $title = $item['attr']['title'];

                // Skip empty titles.
                if ('' === trim(strip_tags($title))) {
                    continue;
                }

                // Skip paragraph-type headings that are just decorative.
                if ('p' === $tag) {
                    $texts[] = '<p>' . $title . '</p>';
                } else {
                    $texts[] = '<' . $tag . '>' . $title . '</' . $tag . '>';
                }
                continue;
            }

            // Column / content items: include raw HTML (already has correct inline tags).
            if (isset($item['attr']['content'])) {
                $content = $item['attr']['content'];
                if ('' !== trim(strip_tags($content))) {
                    $texts[] = $content;
                }
            }

            // Image items: reconstruct <img> tag so alt-text audit works.
            if ('image' === $type && isset($item['attr']['src']) && '' !== trim((string) $item['attr']['src'])) {
                $src = (string) $item['attr']['src'];

                // BeTheme appends #attachment_id to the URL — extract it for WP alt lookup.
                $alt = '';
                if (preg_match('/#(\d+)$/', $src, $id_match)) {
                    $attachment_id = (int) $id_match[1];
                    $alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
                    $src = preg_replace('/#\d+$/', '', $src);
                }

                $texts[] = '<img src="' . esc_attr($src) . '"' . ('' !== $alt ? ' alt="' . esc_attr($alt) . '"' : '') . ' />';
            }

            // Recurse into nested structures (sections → wraps → items).
            foreach (array('wraps', 'items', 'fields') as $child_key) {
                if (isset($item[$child_key]) && is_array($item[$child_key])) {
                    self::walk_betheme_items($item[$child_key], $texts, $depth + 1);
                }
            }

            // Also recurse into numerically-indexed sub-arrays.
            if (! isset($item['type']) && ! isset($item['attr'])) {
                self::walk_betheme_items($item, $texts, $depth + 1);
            }
        }
    }

    // ------------------------------------------------------------------
    //  Generic structure walker
    // ------------------------------------------------------------------

    /**
     * Recursively extract text from arrays / objects produced by
     * page-builder JSON or serialized-PHP storage.
     *
     * Looks for keys commonly used to hold user-visible content
     * (text, editor, heading, html, content, description, caption)
     * and ignores values that are too short to be real body content.
     */
    private static function extract_structured_text($data, int $depth = 0): string
    {
        // Guard against excessively nested or circular structures.
        if ($depth > 15) {
            return '';
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        if (! is_array($data)) {
            return '';
        }

        $text_keys = array(
            'text',
            'html',
            'content',
            'editor',
            'heading',
            'title',
            'description',
            'caption',
            'text_editor',
            'before_text',
            'after_text',
            'tab_content',
            'accordion_content',
        );

        $texts = array();

        // Check for image widgets/elements (Elementor, Beaver, Bricks, etc.).
        // These store image data in keys like 'image', 'url', 'src' with optional 'alt'.
        $widget_type = strtolower((string) ($data['widgetType'] ?? $data['elType'] ?? $data['type'] ?? ''));
        if (in_array($widget_type, array('image', 'photo', 'image-module', 'wp-image'), true)) {
            $img_url = '';
            $img_alt = '';

            // Elementor: settings.image.url / settings.image.alt
            if (isset($data['settings']['image']['url'])) {
                $img_url = (string) $data['settings']['image']['url'];
                $img_alt = (string) ($data['settings']['image']['alt'] ?? '');
            }
            // Beaver Builder: photo_src / alt
            if ('' === $img_url && isset($data['photo_src'])) {
                $img_url = (string) $data['photo_src'];
                $img_alt = (string) ($data['alt'] ?? '');
            }
            // Generic: src / url + alt
            if ('' === $img_url) {
                $img_url = (string) ($data['src'] ?? $data['url'] ?? '');
                $img_alt = (string) ($data['alt'] ?? '');
            }

            if ('' !== $img_url) {
                $texts[] = '<img src="' . esc_attr($img_url) . '"' . ('' !== $img_alt ? ' alt="' . esc_attr($img_alt) . '"' : '') . ' />';
            }
        }

        foreach ($data as $key => $value) {
            if (is_string($value) && in_array(strtolower((string) $key), $text_keys, true)) {
                // Only keep values that look like real content (≥3 words).
                $stripped = trim(wp_strip_all_tags($value));
                if (str_word_count($stripped) >= 3) {
                    $texts[] = $value;
                }
            } elseif (is_array($value) || is_object($value)) {
                $inner = self::extract_structured_text($value, $depth + 1);
                if ('' !== $inner) {
                    $texts[] = $inner;
                }
            }
        }

        return implode("\n", $texts);
    }

    // ------------------------------------------------------------------
    //  Shared utilities
    // ------------------------------------------------------------------

    private static function is_meaningful(string $content): bool
    {
        $text = trim(wp_strip_all_tags($content));

        return str_word_count($text) >= self::MIN_WORDS;
    }

    /**
     * Read a single post-meta string and return it when non-empty.
     */
    private static function try_string_meta(int $post_id, string $meta_key): string
    {
        $value = get_post_meta($post_id, $meta_key, true);

        return (is_string($value) && '' !== trim($value)) ? $value : '';
    }
}
