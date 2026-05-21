<?php

namespace AI_SEO_Captain;

/**
 * REST API controller — exposes SEO metadata for headless WordPress.
 *
 * Endpoints:
 *   GET /wp-json/ai-seo-captain/v1/meta/{id}        — per-post metadata
 *   GET /wp-json/ai-seo-captain/v1/meta?slug={slug}  — lookup by slug
 *   GET /wp-json/ai-seo-captain/v1/global             — global SEO settings
 *
 * Also adds SEO metadata to the default WP REST responses via register_rest_field.
 */
class REST_API
{
    private const NAMESPACE = 'ai-seo-captain/v1';

    /** @var Settings */
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Register all REST routes and fields.
     */
    public function register(): void
    {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('rest_api_init', array($this, 'register_fields'));
    }

    /**
     * Register custom REST routes.
     */
    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/meta/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_post_meta_endpoint'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        register_rest_route(self::NAMESPACE, '/meta', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_post_meta_by_slug'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'slug' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_title',
                ),
                'type' => array(
                    'default'           => 'post',
                    'sanitize_callback' => 'sanitize_key',
                ),
            ),
        ));

        register_rest_route(self::NAMESPACE, '/global', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_global_settings'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Register SEO fields on default post/page REST responses.
     */
    public function register_fields(): void
    {
        $post_types = get_post_types(array('public' => true, 'show_in_rest' => true), 'names');

        foreach ($post_types as $post_type) {
            if ('attachment' === $post_type) {
                continue;
            }

            register_rest_field($post_type, 'ai_seo_captain', array(
                'get_callback' => function ($object) {
                    return $this->build_seo_data((int) $object['id']);
                },
                'schema'       => array(
                    'description' => 'AI SEO Captain metadata',
                    'type'        => 'object',
                    'context'     => array('view', 'embed'),
                ),
            ));
        }
    }

    /**
     * GET /meta/{id} — returns SEO metadata for a specific post.
     */
    public function get_post_meta_endpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $post_id = $request->get_param('id');
        $post    = get_post($post_id);

        if (! $post || 'publish' !== $post->post_status) {
            return new \WP_REST_Response(array('code' => 'not_found', 'message' => 'Post not found.'), 404);
        }

        return new \WP_REST_Response($this->build_seo_data($post_id));
    }

    /**
     * GET /meta?slug={slug}&type={type} — lookup by slug.
     */
    public function get_post_meta_by_slug(\WP_REST_Request $request): \WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $type = $request->get_param('type');

        $posts = get_posts(array(
            'name'        => $slug,
            'post_type'   => $type,
            'post_status' => 'publish',
            'numberposts' => 1,
        ));

        if (empty($posts)) {
            return new \WP_REST_Response(array('code' => 'not_found', 'message' => 'Post not found.'), 404);
        }

        return new \WP_REST_Response($this->build_seo_data($posts[0]->ID));
    }

    /**
     * GET /global — returns global SEO settings (title templates, schema, etc.).
     */
    public function get_global_settings(): \WP_REST_Response
    {
        $options = $this->settings->get();

        $global = array(
            'site_name'             => get_bloginfo('name'),
            'site_description'      => get_bloginfo('description'),
            'separator'             => $options['separator'] ?? '|',
            'title_templates'       => array(),
            'schema'                => array(
                'organization_name' => $options['organization_name'] ?? '',
                'organization_logo' => $options['organization_logo'] ?? '',
                'social_profiles'   => $options['social_profiles'] ?? array(),
            ),
            'webmaster_verification' => array(
                'google'    => $options['google_verification'] ?? '',
                'bing'      => $options['bing_verification'] ?? '',
                'yandex'    => $options['yandex_verification'] ?? '',
                'pinterest' => $options['pinterest_verification'] ?? '',
            ),
        );

        // Collect title templates.
        $template_keys = array(
            'search_title_post', 'search_title_page', 'search_title_category',
            'search_title_tag', 'search_title_author', 'search_title_date',
            'search_title_search', 'search_title_archive', 'search_title_404',
        );

        foreach ($template_keys as $key) {
            if (isset($options[$key])) {
                $global['title_templates'][$key] = $options[$key];
            }
        }

        return new \WP_REST_Response($global);
    }

    /**
     * Build the SEO data array for a given post ID.
     */
    private function build_seo_data(int $post_id): array
    {
        $post = get_post($post_id);

        if (! $post) {
            return array();
        }

        $options   = $this->settings->get();
        $separator = $options['separator'] ?? '|';

        // Read all SEO Captain meta.
        $meta_title       = (string) get_post_meta($post_id, Admin::META_TITLE_KEY, true);
        $meta_description = (string) get_post_meta($post_id, Admin::META_DESCRIPTION_KEY, true);
        $focus_keyphrase  = (string) get_post_meta($post_id, Admin::FOCUS_KEYPHRASE_META_KEY, true);
        $canonical_url    = (string) get_post_meta($post_id, Admin::CANONICAL_URL_META_KEY, true);
        $robots           = (string) get_post_meta($post_id, Admin::ROBOTS_DIRECTIVES_META_KEY, true);
        $social_title     = (string) get_post_meta($post_id, Admin::SOCIAL_TITLE_META_KEY, true);
        $social_desc      = (string) get_post_meta($post_id, Admin::SOCIAL_DESCRIPTION_META_KEY, true);
        $social_image     = (string) get_post_meta($post_id, Admin::SOCIAL_IMAGE_META_KEY, true);
        $schema_type      = (string) get_post_meta($post_id, '_ai_seo_captain_schema_type', true);
        $cornerstone      = (string) get_post_meta($post_id, '_ai_seo_captain_cornerstone', true);
        $frontend_enabled = (string) get_post_meta($post_id, Admin::FRONTEND_ENABLE_META_KEY, true);

        // Build computed title (fallback to template).
        $computed_title = $meta_title;
        if ('' === $computed_title) {
            $template_key = 'search_title_' . $post->post_type;
            $template     = $options[$template_key] ?? '%title% ' . $separator . ' %sitename%';
            $computed_title = str_replace(
                array('%title%', '%sitename%', '%sep%'),
                array($post->post_title, get_bloginfo('name'), $separator),
                $template
            );
        }

        // Build computed description (fallback to excerpt).
        $computed_description = $meta_description;
        if ('' === $computed_description) {
            $computed_description = wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 30, '…');
        }

        // Canonical fallback.
        $computed_canonical = $canonical_url ?: get_permalink($post_id);

        // Robots array.
        $robots_array = array();
        if ('' !== $robots) {
            $robots_array = array_map('trim', explode(',', $robots));
        }

        // Featured image for OG fallback.
        $og_image = $social_image;
        if ('' === $og_image && has_post_thumbnail($post_id)) {
            $og_image = get_the_post_thumbnail_url($post_id, 'large');
        }

        return array(
            'post_id'          => $post_id,
            'title'            => $computed_title,
            'description'      => $computed_description,
            'canonical'        => $computed_canonical,
            'robots'           => $robots_array,
            'focus_keyphrase'  => $focus_keyphrase,
            'schema_type'      => $schema_type ?: 'WebPage',
            'cornerstone'      => '1' === $cornerstone,
            'frontend_enabled' => '1' === $frontend_enabled,
            'open_graph'       => array(
                'title'       => $social_title ?: $computed_title,
                'description' => $social_desc ?: $computed_description,
                'image'       => $og_image,
                'type'        => 'article',
                'url'         => $computed_canonical,
            ),
            'twitter'          => array(
                'card'        => 'summary_large_image',
                'title'       => $social_title ?: $computed_title,
                'description' => $social_desc ?: $computed_description,
                'image'       => $og_image,
            ),
            'raw_meta'         => array(
                'meta_title'       => $meta_title,
                'meta_description' => $meta_description,
                'social_title'     => $social_title,
                'social_description' => $social_desc,
                'social_image'     => $social_image,
                'canonical_url'    => $canonical_url,
                'robots_directives' => $robots,
            ),
        );
    }
}
