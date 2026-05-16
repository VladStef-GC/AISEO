<?php

namespace AI_SEO_Captain;

class Frontend
{
    public const FRONTEND_ENABLE_META_KEY = '_ai_seo_captain_frontend_enabled';

    private const META_TITLE_KEY = '_ai_seo_captain_meta_title';

    private const META_DESCRIPTION_KEY = '_ai_seo_captain_meta_description';

    private const SOCIAL_TITLE_META_KEY = '_ai_seo_captain_social_title';

    private const SOCIAL_DESCRIPTION_META_KEY = '_ai_seo_captain_social_description';

    private const SOCIAL_IMAGE_META_KEY = '_ai_seo_captain_social_image';

    private const CANONICAL_URL_META_KEY = '_ai_seo_captain_canonical_url';

    private const ROBOTS_DIRECTIVES_META_KEY = '_ai_seo_captain_robots_directives';

    private const SCHEMA_TYPE_META_KEY = '_ai_seo_captain_schema_type';

    private const TITLE_BRANDING_OFF_META_KEY = '_ai_seo_captain_title_branding_off';

    private const TITLE_MAX_LENGTH = 60;

    private const DESCRIPTION_MAX_LENGTH = 155;

    private Settings $settings;

    private History_Store $history_store;

    private array $context_cache = array();

    public function __construct(Settings $settings, History_Store $history_store)
    {
        $this->settings = $settings;
        $this->history_store = $history_store;

        add_shortcode('ai_seo_captain_breadcrumbs', array($this, 'render_breadcrumbs_shortcode'));
        add_shortcode('ai_seo_map', array($this, 'render_map_shortcode'));
        add_action('wp_head', array($this, 'output_webmaster_verification_tags'), 0);
        add_filter('pre_get_document_title', array($this, 'filter_document_title'), 50);
        add_action('wp_head', array($this, 'output_meta_description'), 1);
        add_action('wp_head', array($this, 'output_canonical_url'), 2);
        add_action('wp_head', array($this, 'output_open_graph_tags'), 3);
        add_action('wp_head', array($this, 'output_twitter_cards'), 4);
        add_action('wp_head', array($this, 'output_schema'), 5);
        add_action('wp_head', array($this, 'output_robots_directives'), 6);
        add_action('wp_head', array($this, 'output_hreflang_tags'), 7);

        // Apply pending AI content changes during WordPress Preview.
        add_filter('the_content', array($this, 'filter_preview_content'), 1);
        add_filter('get_post_metadata', array($this, 'filter_preview_builder_meta'), 1, 4);

        // RSS Feed Optimization.
        add_filter('the_content_feed', array($this, 'filter_rss_content'), 99);
        add_filter('the_excerpt_rss', array($this, 'filter_rss_content'), 99);

        // Crawl Budget Optimization.
        $opts = $settings->get();
        if (! empty($opts['crawl_disable_author_archives'])) {
            add_action('template_redirect', array($this, 'redirect_author_archives'));
        }
        if (! empty($opts['crawl_disable_date_archives'])) {
            add_action('template_redirect', array($this, 'redirect_date_archives'));
        }
        if (! empty($opts['crawl_disable_attachment_pages'])) {
            add_action('template_redirect', array($this, 'redirect_attachment_pages'));
        }
        if (! empty($opts['crawl_disable_format_archives'])) {
            add_action('template_redirect', array($this, 'redirect_format_archives'));
        }
        if (! empty($opts['crawl_remove_wp_version'])) {
            remove_action('wp_head', 'wp_generator');
            add_filter('the_generator', '__return_empty_string');
        }
        if (! empty($opts['crawl_remove_shortlink'])) {
            remove_action('wp_head', 'wp_shortlink_wp_head', 10);
            remove_action('template_redirect', 'wp_shortlink_header', 11);
        }
        if (! empty($opts['crawl_remove_rsd_link'])) {
            remove_action('wp_head', 'rsd_link');
        }
        if (! empty($opts['crawl_remove_feed_links'])) {
            remove_action('wp_head', 'feed_links', 2);
            remove_action('wp_head', 'feed_links_extra', 3);
        }
    }

    public function output_webmaster_verification_tags(): void
    {
        $options = $this->settings->get();

        if (! $this->can_output_global_head_tags($options)) {
            return;
        }

        $google_code = trim((string) ($options['google_tracking_code'] ?? ''));
        $bing_code = trim((string) ($options['bing_tracking_code'] ?? ''));

        if ('' !== $google_code) {
            $this->print_meta_tag('name', 'google-site-verification', $google_code, 'verification');
        }

        if ('' !== $bing_code) {
            $this->print_meta_tag('name', 'msvalidate.01', $bing_code, 'verification');
        }
    }

    public function render_breadcrumbs_shortcode(array $atts = array()): string
    {
        if (! is_singular()) {
            return '';
        }

        $post = get_queried_object();

        if (! $post instanceof \WP_Post || ! $this->supports_automatic_search_appearance($post)) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'separator' => '/',
                'home_label' => wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
            ),
            $atts,
            'ai_seo_captain_breadcrumbs'
        );
        $items = $this->build_breadcrumb_items($post, (string) $atts['home_label']);

        if (count($items) < 2) {
            return '';
        }

        ob_start();
?>
        <nav class="ai-seo-captain-breadcrumbs" aria-label="Breadcrumbs">
            <ol class="ai-seo-captain-breadcrumbs-list">
                <?php foreach ($items as $index => $item) : ?>
                    <li class="ai-seo-captain-breadcrumbs-item">
                        <?php if (! empty($item['is_current'])) : ?>
                            <span aria-current="page"><?php echo esc_html($item['name']); ?></span>
                        <?php else : ?>
                            <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['name']); ?></a>
                        <?php endif; ?>
                        <?php if ($index < count($items) - 1) : ?>
                            <span class="ai-seo-captain-breadcrumbs-separator" aria-hidden="true"><?php echo esc_html((string) $atts['separator']); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
<?php

        return (string) ob_get_clean();
    }

    public function filter_document_title(string $title): string
    {
        $context = $this->get_frontend_context();

        if (empty($context) || empty($context['options']['feature_meta_title']) || empty($context['has_title_source']) || '' === $context['title']) {
            return $title;
        }

        return $context['title'];
    }

    public function output_meta_description(): void
    {
        $context = $this->get_frontend_context();

        if (empty($context) || empty($context['options']['feature_meta_description']) || empty($context['has_description_source']) || '' === $context['description']) {
            return;
        }

        $this->print_meta_tag('name', 'description', $context['description'], 'meta_description');
    }

    public function output_canonical_url(): void
    {
        $context = $this->get_frontend_context();

        if (empty($context) || empty($context['options']['feature_canonical']) || '' === $context['canonical_url']) {
            return;
        }

        echo "\n" . '<link rel="canonical" href="' . esc_url($context['canonical_url']) . '" data-ai-seo-captain="approved" data-ai-seo-feature="canonical" />' . "\n";
    }

    public function output_open_graph_tags(): void
    {
        $context = $this->get_frontend_context();

        if (empty($context) || empty($context['options']['feature_open_graph']) || (empty($context['has_primary_source']) && empty($context['has_social_override']))) {
            return;
        }

        $this->print_meta_tag('property', 'og:type', $context['open_graph_type'], 'open_graph');
        $this->print_meta_tag('property', 'og:title', $context['open_graph_title'], 'open_graph');
        $this->print_meta_tag('property', 'og:description', $context['open_graph_description'], 'open_graph');
        $this->print_meta_tag('property', 'og:url', $context['canonical_url'], 'open_graph');
        $this->print_meta_tag('property', 'og:site_name', $context['site_name'], 'open_graph');

        if ('' !== $context['social_image_url']) {
            $this->print_meta_tag('property', 'og:image', $context['social_image_url'], 'open_graph');
        }

        // Extra OG tags (e.g. WooCommerce og:price, og:availability).
        $extra_tags = apply_filters('ai_seo_captain_extra_og_tags', array(), $context);
        foreach ($extra_tags as $tag) {
            if (! empty($tag['name']) && ! empty($tag['content'])) {
                $this->print_meta_tag('property', esc_attr($tag['name']), esc_attr($tag['content']), 'open_graph');
            }
        }
    }

    public function output_twitter_cards(): void
    {
        $context = $this->get_frontend_context();

        if (empty($context) || empty($context['options']['feature_twitter_cards']) || (empty($context['has_primary_source']) && empty($context['has_social_override']))) {
            return;
        }

        $this->print_meta_tag('name', 'twitter:card', '' !== $context['social_image_url'] ? 'summary_large_image' : 'summary', 'twitter_cards');
        $this->print_meta_tag('name', 'twitter:title', $context['twitter_title'], 'twitter_cards');
        $this->print_meta_tag('name', 'twitter:description', $context['twitter_description'], 'twitter_cards');

        if ('' !== $context['social_image_url']) {
            $this->print_meta_tag('name', 'twitter:image', $context['social_image_url'], 'twitter_cards');
        }
    }

    public function output_schema(): void
    {
        $context = $this->get_frontend_context();

        if (empty($context) || empty($context['options']['feature_schema'])) {
            return;
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@graph' => $this->build_schema_graph($context),
        );

        echo "\n" . '<script type="application/ld+json" data-ai-seo-captain="approved" data-ai-seo-feature="schema">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    public function output_robots_directives(): void
    {
        $context = $this->get_frontend_context();

        if (empty($context) || empty($context['options']['feature_robots'])) {
            return;
        }

        $this->print_meta_tag('name', 'robots', $context['robots_directives'], 'robots');
    }

    /**
     * Output hreflang alternate tags — manual entries or auto-detected from WPML/Polylang.
     */
    public function output_hreflang_tags(): void
    {
        if (! is_singular()) {
            return;
        }

        $post_id = get_the_ID();
        if (! $post_id) {
            return;
        }

        $alternates = array();

        // 1) Manual hreflang entries from post meta.
        $manual = trim((string) get_post_meta($post_id, '_ai_seo_captain_hreflang', true));
        if ('' !== $manual) {
            $lines = preg_split('/[\r\n]+/', $manual);
            foreach ($lines as $line) {
                $line = trim($line);
                if ('' === $line) {
                    continue;
                }
                $parts = explode('|', $line, 2);
                if (2 === count($parts)) {
                    $lang = sanitize_text_field(trim($parts[0]));
                    $url = esc_url(trim($parts[1]));
                    if ('' !== $lang && '' !== $url) {
                        $alternates[$lang] = $url;
                    }
                }
            }
        }

        // 2) Auto-detect from WPML.
        if (empty($alternates) && function_exists('icl_get_languages')) {
            $languages = \icl_get_languages('skip_missing=0');
            if (is_array($languages)) {
                foreach ($languages as $lang_data) {
                    if (! empty($lang_data['url']) && ! empty($lang_data['language_code'])) {
                        $alternates[$lang_data['language_code']] = $lang_data['url'];
                    }
                }
            }
        }

        // 3) Auto-detect from Polylang.
        if (empty($alternates) && function_exists('pll_the_languages')) {
            $translations = function_exists('pll_get_post_translations') ? \pll_get_post_translations($post_id) : array();
            if (! empty($translations)) {
                foreach ($translations as $lang => $translated_id) {
                    $url = get_permalink($translated_id);
                    if ($url) {
                        $alternates[$lang] = $url;
                    }
                }
            }
        }

        if (empty($alternates)) {
            return;
        }

        echo "\n<!-- SEO Captain: hreflang -->\n";
        foreach ($alternates as $lang => $url) {
            echo '<link rel="alternate" hreflang="' . esc_attr($lang) . '" href="' . esc_url($url) . '" />' . "\n";
        }
    }

    public function get_effective_suggestion_for_post(int $post_id): array
    {
        if ($post_id <= 0) {
            return array();
        }

        $options = $this->settings->get();

        if (! $this->can_render_frontend_output($post_id, $options)) {
            return array();
        }

        $approved_message_id = $this->history_store->get_approved_suggestion_id($post_id, 'post');

        if ($approved_message_id <= 0) {
            return array();
        }

        return $this->history_store->get_suggestion_by_id($post_id, 'post', $approved_message_id);
    }

    public static function has_conflicting_seo_plugin(): bool
    {
        $active_plugins = (array) get_option('active_plugins', array());
        $network_plugins = is_multisite() ? array_keys((array) get_site_option('active_sitewide_plugins', array())) : array();
        $all_active = array_unique(array_merge($active_plugins, $network_plugins));

        $conflicts = array(
            'wordpress-seo/wp-seo.php',
            'all-in-one-seo-pack/all_in_one_seo_pack.php',
            'seo-by-rank-math/rank-math.php',
            'autodescription/autodescription.php',
            'seopress/seopress.php',
            'slim-seo/slim-seo.php',
        );

        foreach ($conflicts as $plugin_file) {
            if (in_array($plugin_file, $all_active, true)) {
                return true;
            }
        }

        return false;
    }

    private function is_post_frontend_enabled(int $post_id): bool
    {
        return '1' === (string) get_post_meta($post_id, self::FRONTEND_ENABLE_META_KEY, true);
    }

    private function can_render_frontend_output(int $post_id, ?array $options = null): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        $options = is_array($options) ? $options : $this->settings->get();

        if (! $this->can_output_global_head_tags($options)) {
            return false;
        }

        if ($this->is_post_frontend_enabled($post_id)) {
            return true;
        }

        return $this->is_automatic_search_appearance_enabled($options);
    }

    private function can_output_global_head_tags(array $options): bool
    {
        if (empty($options['frontend_output_enabled'])) {
            return false;
        }

        if (self::has_conflicting_seo_plugin() && empty($options['frontend_override_conflicts'])) {
            return false;
        }

        return true;
    }

    private function is_automatic_search_appearance_enabled(array $options): bool
    {
        return ! empty($options['search_appearance_auto_enabled']);
    }

    private function get_manual_frontend_source(int $post_id): array
    {
        $title = trim(wp_strip_all_tags((string) get_post_meta($post_id, self::META_TITLE_KEY, true)));
        $description = trim(wp_strip_all_tags((string) get_post_meta($post_id, self::META_DESCRIPTION_KEY, true)));

        return array(
            'seo_title' => $this->truncate_text($title, self::TITLE_MAX_LENGTH),
            'meta_description' => $this->truncate_text($description, self::DESCRIPTION_MAX_LENGTH),
        );
    }

    private function supports_automatic_search_appearance(\WP_Post $post): bool
    {
        if ('attachment' === $post->post_type) {
            return false;
        }

        return is_post_type_viewable($post->post_type);
    }

    private function build_search_title(
        \WP_Post $post,
        array $options,
        string $site_name
    ): string {
        $template = (string) ($options['search_title_template_default'] ?? '%%title%% %%sep%% %%sitename%%');

        if ('post' === $post->post_type && ! empty($options['search_title_template_post'])) {
            $template = (string) $options['search_title_template_post'];
        } elseif ('page' === $post->post_type && ! empty($options['search_title_template_page'])) {
            $template = (string) $options['search_title_template_page'];
        }

        $separator = trim((string) ($options['search_title_separator'] ?? '|'));
        $title = trim(wp_strip_all_tags((string) get_the_title($post->ID)));
        $rendered = strtr(
            $template,
            array(
                '%%title%%' => $title,
                '%%sitename%%' => $site_name,
                '%%sep%%' => $separator,
            )
        );
        $rendered = preg_replace('/\s+/u', ' ', trim(wp_strip_all_tags($rendered)));

        return is_string($rendered) ? trim($rendered) : '';
    }

    private function get_frontend_context(): array
    {
        if (is_singular()) {
            return $this->get_singular_context();
        }

        return $this->get_non_singular_context();
    }

    private function get_singular_context(): array
    {
        $post_id = (int) get_queried_object_id();

        if ($post_id <= 0) {
            return array();
        }

        if (isset($this->context_cache[$post_id])) {
            return $this->context_cache[$post_id];
        }

        $options = $this->settings->get();
        $post = get_post($post_id);

        if (! $post instanceof \WP_Post || ! $this->supports_automatic_search_appearance($post) || ! $this->can_render_frontend_output($post_id, $options)) {
            $this->context_cache[$post_id] = array();
            return $this->context_cache[$post_id];
        }

        $suggestion = $this->get_effective_suggestion_for_post($post_id);
        $manual_source = $this->get_manual_frontend_source($post_id);
        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $auto_defaults_enabled = $this->is_automatic_search_appearance_enabled($options);
        $default_title = $auto_defaults_enabled ? $this->build_search_title($post, $options, $site_name) : '';
        $default_description = $auto_defaults_enabled ? $this->get_fallback_description($post) : '';
        $suggestion_title = trim(wp_strip_all_tags(isset($suggestion['seo_title']) ? (string) $suggestion['seo_title'] : ''));
        $suggestion_description = trim(wp_strip_all_tags(isset($suggestion['meta_description']) ? (string) $suggestion['meta_description'] : ''));
        $raw_social_title = trim(wp_strip_all_tags((string) get_post_meta($post_id, self::SOCIAL_TITLE_META_KEY, true)));
        $raw_social_description = trim(wp_strip_all_tags((string) get_post_meta($post_id, self::SOCIAL_DESCRIPTION_META_KEY, true)));
        $raw_social_image_url = $this->get_meta_url($post_id, self::SOCIAL_IMAGE_META_KEY);
        $raw_canonical_url = $this->get_meta_url($post_id, self::CANONICAL_URL_META_KEY);
        $raw_schema_override = trim((string) get_post_meta($post_id, self::SCHEMA_TYPE_META_KEY, true));
        $raw_robots_override = trim((string) get_post_meta($post_id, self::ROBOTS_DIRECTIVES_META_KEY, true));
        $has_title_source = '' !== $suggestion_title || '' !== $manual_source['seo_title'] || '' !== $default_title;
        $has_description_source = '' !== $suggestion_description || '' !== $manual_source['meta_description'] || '' !== $default_description;
        $has_social_override = '' !== $raw_social_title || '' !== $raw_social_description || '' !== $raw_social_image_url;
        $has_primary_source = ! empty($suggestion) || $has_title_source || $has_description_source;
        $has_frontend_data = $has_primary_source || $has_social_override || '' !== $raw_canonical_url || '' !== $raw_schema_override || '' !== $raw_robots_override;

        if (! $has_frontend_data) {
            $this->context_cache[$post_id] = array();
            return $this->context_cache[$post_id];
        }

        $title = $suggestion_title;
        $description = $suggestion_description;

        if ('' === $title) {
            $title = $manual_source['seo_title'];
        }

        // When the title comes from suggestion or manual source, append branding unless overridden.
        $title_branding_off = '1' === (string) get_post_meta($post_id, self::TITLE_BRANDING_OFF_META_KEY, true);
        $branding_suffix = $this->settings->get_branding_suffix();
        $has_page_specific_title = ('' !== $title);

        if ('' === $title) {
            // Fallback: build_search_title already includes branding via template
            $title = '' !== $default_title ? $default_title : trim(wp_strip_all_tags((string) get_the_title($post_id)));
        } elseif (! $title_branding_off && '' !== $branding_suffix) {
            // Append branding suffix to the page-specific title
            $title = $title . $branding_suffix;
        }

        if ('' === $description) {
            $description = $manual_source['meta_description'];
        }

        if ('' === $description) {
            $description = '' !== $default_description ? $default_description : $this->get_fallback_description($post);
        }

        $title = $this->truncate_text($title, self::TITLE_MAX_LENGTH);
        $description = $this->truncate_text($description, self::DESCRIPTION_MAX_LENGTH);

        $featured_image_url = $this->get_primary_image_url($post_id);
        $site_logo_url = $this->get_site_logo_url();
        $canonical_url = $raw_canonical_url;

        if ('' === $canonical_url) {
            $canonical_url = (string) get_permalink($post_id);
        }

        $social_title = $raw_social_title;
        if ('' === $social_title) {
            $social_title = $title;
        }
        $social_title = $this->truncate_text($social_title, self::TITLE_MAX_LENGTH);

        $social_description = $raw_social_description;
        if ('' === $social_description) {
            $social_description = $description;
        }
        $social_description = $this->truncate_text($social_description, self::DESCRIPTION_MAX_LENGTH);

        $social_image_url = $raw_social_image_url;
        if ('' === $social_image_url) {
            $social_image_url = '' !== $featured_image_url ? $featured_image_url : $site_logo_url;
        }

        $schema_type = $this->resolve_effective_schema_type($post, (string) get_permalink($post_id));

        $this->context_cache[$post_id] = array(
            'post' => $post,
            'post_id' => $post_id,
            'options' => $options,
            'title' => $title,
            'description' => $description,
            'url' => (string) get_permalink($post_id),
            'has_title_source' => $has_title_source,
            'has_description_source' => $has_description_source,
            'has_primary_source' => $has_primary_source,
            'has_social_override' => $has_social_override,
            'canonical_url' => $canonical_url,
            'open_graph_title' => $social_title,
            'open_graph_description' => $social_description,
            'twitter_title' => $social_title,
            'twitter_description' => $social_description,
            'site_name' => $site_name,
            'site_description' => wp_strip_all_tags((string) get_bloginfo('description')),
            'featured_image_url' => $featured_image_url,
            'site_logo_url' => $site_logo_url,
            'social_image_url' => $social_image_url,
            'schema_image_url' => '' !== $featured_image_url ? $featured_image_url : $social_image_url,
            'schema_type' => $schema_type,
            'robots_directives' => $this->resolve_robots_directives($post_id),
        );

        $this->context_cache[$post_id]['open_graph_type'] = $this->resolve_open_graph_type($post, $this->context_cache[$post_id]['schema_type']);

        return $this->context_cache[$post_id];
    }

    private function get_non_singular_context(): array
    {
        $cache_key = 'ns_' . $this->get_non_singular_cache_key();

        if (isset($this->context_cache[$cache_key])) {
            return $this->context_cache[$cache_key];
        }

        $options = $this->settings->get();

        if (! $this->can_output_global_head_tags($options) || ! $this->is_automatic_search_appearance_enabled($options)) {
            $this->context_cache[$cache_key] = array();
            return array();
        }

        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $site_description = wp_strip_all_tags((string) get_bloginfo('description'));
        $separator = trim((string) ($options['search_title_separator'] ?? '|'));
        $site_logo_url = $this->get_site_logo_url();

        $context_type = '';
        $title = '';
        $description = '';
        $canonical_url = '';
        $robots = 'index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1';
        $social_image_url = $site_logo_url;
        $og_type = 'website';
        $schema_type = 'CollectionPage';

        if (is_category()) {
            $context_type = 'category';
            $term = get_queried_object();
            if (! $term instanceof \WP_Term) {
                $this->context_cache[$cache_key] = array();
                return array();
            }
            $template = (string) ($options['search_title_template_category'] ?? '%%term_title%% %%sep%% %%sitename%%');
            $title = $this->render_non_singular_template($template, $separator, $site_name, array('%%term_title%%' => $term->name));
            $description = trim(wp_strip_all_tags((string) term_description($term->term_id, 'category')));
            $canonical_url = (string) get_term_link($term);
            if (! empty($options['noindex_categories'])) {
                $robots = 'noindex,follow';
            }
            // Term-level SEO overrides.
            $title       = $this->apply_term_seo_overrides_title($term->term_id, $title);
            $description = $this->apply_term_seo_overrides_description($term->term_id, $description);
            $canonical_url = $this->apply_term_seo_overrides_canonical($term->term_id, $canonical_url);
            $robots      = $this->apply_term_seo_overrides_noindex($term->term_id, $robots);
        } elseif (is_tag()) {
            $context_type = 'tag';
            $term = get_queried_object();
            if (! $term instanceof \WP_Term) {
                $this->context_cache[$cache_key] = array();
                return array();
            }
            $template = (string) ($options['search_title_template_tag'] ?? '%%term_title%% %%sep%% %%sitename%%');
            $title = $this->render_non_singular_template($template, $separator, $site_name, array('%%term_title%%' => $term->name));
            $description = trim(wp_strip_all_tags((string) term_description($term->term_id, 'post_tag')));
            $canonical_url = (string) get_term_link($term);
            if (! empty($options['noindex_tags'])) {
                $robots = 'noindex,follow';
            }
            // Term-level SEO overrides.
            $title       = $this->apply_term_seo_overrides_title($term->term_id, $title);
            $description = $this->apply_term_seo_overrides_description($term->term_id, $description);
            $canonical_url = $this->apply_term_seo_overrides_canonical($term->term_id, $canonical_url);
            $robots      = $this->apply_term_seo_overrides_noindex($term->term_id, $robots);
        } elseif (is_tax()) {
            $context_type = 'taxonomy';
            $term = get_queried_object();
            if (! $term instanceof \WP_Term) {
                $this->context_cache[$cache_key] = array();
                return array();
            }
            $template = (string) ($options['search_title_template_archive'] ?? '%%archive_title%% %%sep%% %%sitename%%');
            $title = $this->render_non_singular_template($template, $separator, $site_name, array('%%archive_title%%' => $term->name, '%%term_title%%' => $term->name));
            $description = trim(wp_strip_all_tags((string) term_description($term->term_id, $term->taxonomy)));
            $canonical_url = (string) get_term_link($term);
            // Term-level SEO overrides.
            $title       = $this->apply_term_seo_overrides_title($term->term_id, $title);
            $description = $this->apply_term_seo_overrides_description($term->term_id, $description);
            $canonical_url = $this->apply_term_seo_overrides_canonical($term->term_id, $canonical_url);
            $robots      = $this->apply_term_seo_overrides_noindex($term->term_id, $robots);
        } elseif (function_exists('is_woocommerce') && (\is_shop() || \is_product_category() || \is_product_tag())) {
            // Delegate to WooCommerce integration filter.
            $wc_context = apply_filters(
                'ai_seo_captain_wc_archive_context',
                array(
                    'robots_directives' => $robots,
                    'social_image_url'  => $social_image_url,
                    'open_graph_type'   => $og_type,
                    'schema_type'       => $schema_type,
                ),
                $options,
                trim((string) ($options['search_title_separator'] ?? '|'))
            );

            if (empty($wc_context['context_type'])) {
                $this->context_cache[$cache_key] = array();
                return array();
            }

            $context_type      = $wc_context['context_type'];
            $title             = $wc_context['title']            ?? '';
            $description       = $wc_context['description']      ?? '';
            $canonical_url     = $wc_context['canonical_url']    ?? '';
            $schema_type       = $wc_context['schema_type']      ?? $schema_type;
            $og_type           = $wc_context['open_graph_type']  ?? $og_type;
            $robots            = $wc_context['robots_directives'] ?? $robots;
            $social_image_url  = $wc_context['social_image_url'] ?? $social_image_url;
        } elseif (is_post_type_archive()) {
            $context_type = 'post_type_archive';
            $post_type_obj = get_queried_object();
            $archive_label = ($post_type_obj instanceof \WP_Post_Type) ? $post_type_obj->label : post_type_archive_title('', false);
            $template = (string) ($options['search_title_template_archive'] ?? '%%archive_title%% %%sep%% %%sitename%%');
            $title = $this->render_non_singular_template($template, $separator, $site_name, array('%%archive_title%%' => $archive_label));
            if ($post_type_obj instanceof \WP_Post_Type && ! empty($post_type_obj->description)) {
                $description = wp_strip_all_tags((string) $post_type_obj->description);
            }
            $canonical_url = (string) get_post_type_archive_link($post_type_obj instanceof \WP_Post_Type ? $post_type_obj->name : '');
        } elseif (is_author()) {
            $context_type = 'author';
            $author = get_queried_object();
            $author_name = ($author instanceof \WP_User) ? $author->display_name : '';
            $template = (string) ($options['search_title_template_author'] ?? '%%author%% %%sep%% %%sitename%%');
            $title = $this->render_non_singular_template($template, $separator, $site_name, array('%%author%%' => $author_name));
            if ($author instanceof \WP_User) {
                $description = trim(wp_strip_all_tags((string) get_the_author_meta('description', $author->ID)));
                $canonical_url = (string) get_author_posts_url($author->ID);
            }
            if (! empty($options['noindex_author_archives'])) {
                $robots = 'noindex,follow';
            }
        } elseif (is_date()) {
            $context_type = 'date';
            $date_label = is_day() ? get_the_date() : (is_month() ? get_the_date('F Y') : get_the_date('Y'));
            $template = (string) ($options['search_title_template_date'] ?? '%%date%% %%sep%% %%sitename%%');
            $title = $this->render_non_singular_template($template, $separator, $site_name, array('%%date%%' => $date_label));
            if (is_day()) {
                $canonical_url = (string) get_day_link((int) get_query_var('year'), (int) get_query_var('monthnum'), (int) get_query_var('day'));
            } elseif (is_month()) {
                $canonical_url = (string) get_month_link((int) get_query_var('year'), (int) get_query_var('monthnum'));
            } else {
                $canonical_url = (string) get_year_link((int) get_query_var('year'));
            }
            if (! empty($options['noindex_date_archives'])) {
                $robots = 'noindex,follow';
            }
        } elseif (is_search()) {
            $context_type = 'search';
            $search_query = get_search_query();
            $template = (string) ($options['search_title_template_search'] ?? 'Search results for "%%searchphrase%%" %%sep%% %%sitename%%');
            $title = $this->render_non_singular_template($template, $separator, $site_name, array('%%searchphrase%%' => $search_query));
            $canonical_url = (string) get_search_link($search_query);
            if (! empty($options['noindex_search_results'])) {
                $robots = 'noindex,follow';
            }
            $schema_type = 'SearchResultsPage';
        } elseif (is_404()) {
            $context_type = '404';
            $template = (string) ($options['search_title_template_404'] ?? 'Page not found %%sep%% %%sitename%%');
            $title = $this->render_non_singular_template($template, $separator, $site_name, array());
            $robots = 'noindex,follow';
            $schema_type = 'WebPage';
        } elseif (is_home()) {
            $context_type = 'home';
            $posts_page_id = (int) get_option('page_for_posts');
            if ($posts_page_id > 0) {
                $title = trim(wp_strip_all_tags((string) get_the_title($posts_page_id)));
                $description = trim(wp_strip_all_tags((string) get_post_field('post_excerpt', $posts_page_id)));
                $canonical_url = (string) get_permalink($posts_page_id);
            }
            if ('' === $title) {
                $template = (string) ($options['search_title_template_archive'] ?? '%%archive_title%% %%sep%% %%sitename%%');
                $title = $this->render_non_singular_template($template, $separator, $site_name, array('%%archive_title%%' => __('Blog')));
            } else {
                $title = $this->render_non_singular_template('%%archive_title%% %%sep%% %%sitename%%', $separator, $site_name, array('%%archive_title%%' => $title));
            }
            if ('' === $canonical_url) {
                $canonical_url = home_url('/');
            }
        } else {
            $this->context_cache[$cache_key] = array();
            return array();
        }

        if ('0' === (string) get_option('blog_public')) {
            $robots = 'noindex,nofollow';
        }

        $paged = (int) get_query_var('paged', 0);
        if ($paged > 1) {
            $title .= ' ' . $separator . ' Page ' . $paged;
        }

        $title = $this->truncate_text(trim($title), self::TITLE_MAX_LENGTH + 20);
        $description = $this->truncate_text(trim($description), self::DESCRIPTION_MAX_LENGTH);

        $this->context_cache[$cache_key] = array(
            'is_singular' => false,
            'context_type' => $context_type,
            'post' => null,
            'post_id' => 0,
            'options' => $options,
            'title' => $title,
            'description' => $description,
            'url' => $canonical_url,
            'has_title_source' => '' !== $title,
            'has_description_source' => '' !== $description,
            'has_primary_source' => '' !== $title,
            'has_social_override' => false,
            'canonical_url' => $canonical_url,
            'open_graph_title' => $title,
            'open_graph_description' => $description,
            'twitter_title' => $title,
            'twitter_description' => $description,
            'site_name' => $site_name,
            'site_description' => $site_description,
            'featured_image_url' => '',
            'site_logo_url' => $site_logo_url,
            'social_image_url' => $social_image_url,
            'schema_image_url' => $social_image_url,
            'schema_type' => $schema_type,
            'robots_directives' => $robots,
            'open_graph_type' => $og_type,
        );

        return $this->context_cache[$cache_key];
    }

    private function render_non_singular_template(string $template, string $separator, string $site_name, array $extra_tokens): string
    {
        $tokens = array_merge(
            array(
                '%%sep%%' => $separator,
                '%%sitename%%' => $site_name,
            ),
            $extra_tokens
        );

        $rendered = strtr($template, $tokens);
        $rendered = preg_replace('/\s+/u', ' ', trim(wp_strip_all_tags($rendered)));

        return is_string($rendered) ? trim($rendered) : '';
    }

    /**
     * Term-level SEO override helpers — read from term meta.
     */
    private function apply_term_seo_overrides_title(int $term_id, string $default): string
    {
        $custom = get_term_meta($term_id, '_ai_seo_captain_seo_title', true);
        return (is_string($custom) && '' !== $custom) ? $custom : $default;
    }

    private function apply_term_seo_overrides_description(int $term_id, string $default): string
    {
        $custom = get_term_meta($term_id, '_ai_seo_captain_meta_description', true);
        return (is_string($custom) && '' !== $custom) ? $custom : $default;
    }

    private function apply_term_seo_overrides_canonical(int $term_id, string $default): string
    {
        $custom = get_term_meta($term_id, '_ai_seo_captain_canonical', true);
        return (is_string($custom) && '' !== $custom) ? $custom : $default;
    }

    private function apply_term_seo_overrides_noindex(int $term_id, string $default): string
    {
        $noindex = get_term_meta($term_id, '_ai_seo_captain_noindex', true);
        return '1' === $noindex ? 'noindex,follow' : $default;
    }

    private function get_non_singular_cache_key(): string
    {
        if (is_category()) {
            return 'cat_' . get_queried_object_id();
        }
        if (is_tag()) {
            return 'tag_' . get_queried_object_id();
        }
        if (is_tax()) {
            $obj = get_queried_object();
            return 'tax_' . ($obj instanceof \WP_Term ? $obj->taxonomy . '_' . $obj->term_id : '0');
        }
        if (is_post_type_archive()) {
            $obj = get_queried_object();
            return 'pta_' . ($obj instanceof \WP_Post_Type ? $obj->name : '');
        }
        if (is_author()) {
            return 'author_' . get_queried_object_id();
        }
        if (is_date()) {
            return 'date_' . get_query_var('year') . '_' . get_query_var('monthnum') . '_' . get_query_var('day');
        }
        if (is_search()) {
            return 'search_' . md5(get_search_query());
        }
        if (is_404()) {
            return '404';
        }
        if (is_home()) {
            return 'home';
        }
        return 'other';
    }

    private function get_text_length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    private function truncate_text(string $text, int $max_length): string
    {
        if ('' === $text || $max_length < 1 || $this->get_text_length($text) <= $max_length) {
            return $text;
        }

        return function_exists('mb_substr') ? mb_substr($text, 0, $max_length) : substr($text, 0, $max_length);
    }

    private function build_schema_graph(array $context): array
    {
        $website = array(
            '@type' => 'WebSite',
            '@id' => home_url('/#website'),
            'name' => $context['site_name'],
            'url' => home_url('/'),
            'potentialAction' => array(
                '@type' => 'SearchAction',
                'target' => home_url('/?s={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ),
        );

        if ('' !== $context['site_description']) {
            $website['description'] = $context['site_description'];
        }

        $organization = array(
            '@type' => 'Organization',
            '@id' => home_url('/#organization'),
            'name' => $context['site_name'],
            'url' => home_url('/'),
        );

        if ('' !== $context['site_description']) {
            $organization['description'] = $context['site_description'];
        }

        if ('' !== $context['site_logo_url']) {
            $organization['logo'] = array(
                '@type' => 'ImageObject',
                'url' => $context['site_logo_url'],
            );
        }

        // Social profiles from settings → sameAs array.
        $options = $this->settings->get();
        $same_as = array();
        foreach (array('social_facebook', 'social_twitter', 'social_instagram', 'social_linkedin', 'social_youtube', 'social_pinterest') as $social_key) {
            $url = trim((string) ($options[$social_key] ?? ''));
            if ('' !== $url) {
                $same_as[] = $url;
            }
        }
        if (! empty($same_as)) {
            $organization['sameAs'] = $same_as;
        }

        $graph = array(
            $website,
            $organization,
        );

        // LocalBusiness schema (only on front page when enabled).
        if (! empty($options['local_seo_enabled']) && is_front_page()) {
            $local_schema = $this->build_local_business_schema($options);
            if (! empty($local_schema)) {
                $graph[] = $local_schema;
            }
        }

        if (null !== $context['post']) {
            $graph[] = $this->build_primary_schema_entity($context);

            $faq_schema = $this->build_faq_schema_entity($context);
            if (! empty($faq_schema)) {
                $graph[] = $faq_schema;
            }

            $collection_item_list = $this->build_collection_item_list($context);
            if (! empty($collection_item_list)) {
                $graph[] = $collection_item_list;
            }

            $breadcrumb = $this->build_breadcrumb_schema($context);
            if (! empty($breadcrumb)) {
                $graph[] = $breadcrumb;
            }
        } else {
            $graph[] = $this->build_non_singular_schema_entity($context);
        }

        return $graph;
    }

    private function build_primary_schema_entity(array $context): array
    {
        $entity = array(
            '@type' => $context['schema_type'],
            '@id' => $context['url'] . '#primary',
            'name' => $context['title'],
            'headline' => $context['title'],
            'description' => $context['description'],
            'url' => $context['url'],
            'mainEntityOfPage' => $context['url'],
            'isPartOf' => array(
                '@id' => home_url('/#website'),
            ),
        );

        if ('' !== $context['schema_image_url']) {
            $entity['image'] = array($context['schema_image_url']);
        }

        if ('Article' === $context['schema_type']) {
            $entity['datePublished'] = get_post_time(DATE_W3C, true, $context['post']);
            $entity['dateModified'] = get_post_modified_time(DATE_W3C, true, $context['post']);
            $entity['author'] = array(
                '@id' => home_url('/#organization'),
            );
            $entity['publisher'] = array(
                '@id' => home_url('/#organization'),
            );
        }

        if ('Service' === $context['schema_type']) {
            $entity['provider'] = array(
                '@id' => home_url('/#organization'),
            );
            $entity['serviceType'] = $context['title'];
            $offers = $this->build_offer_schema($context['post'], $context);

            if (! empty($offers)) {
                $entity['offers'] = $offers;
            }
        }

        if ('Product' === $context['schema_type']) {
            $entity['brand'] = array(
                '@id' => home_url('/#organization'),
            );

            $category = $this->get_primary_category_name($context['post']);

            if ('' !== $category) {
                $entity['category'] = $category;
            }

            $sku = $this->get_candidate_meta_value($context['post']->ID, array('_sku', 'sku', 'product_sku'));

            if ('' !== $sku) {
                $entity['sku'] = $sku;
            }

            $offers = $this->build_offer_schema($context['post'], $context);

            if (! empty($offers)) {
                $entity['offers'] = $offers;
            }
        }

        if ('CollectionPage' === $context['schema_type']) {
            $entity['about'] = $context['title'];

            if ($this->has_collection_children($context['post_id'])) {
                $entity['mainEntity'] = array(
                    '@id' => $context['url'] . '#items',
                );
            }
        }

        if ('ContactPage' === $context['schema_type'] || 'AboutPage' === $context['schema_type']) {
            $entity['about'] = array(
                '@id' => home_url('/#organization'),
            );
        }

        // Allow WooCommerce integration (and other extensions) to enrich the entity.
        if ('Product' === $context['schema_type']) {
            $entity = apply_filters('ai_seo_captain_product_schema', $entity, $context['post']);
        }

        return $entity;
    }

    private function build_faq_schema_entity(array $context): array
    {
        $faq_entities = $this->extract_faq_entities($context['post']);

        if (count($faq_entities) < 2) {
            return array();
        }

        return array(
            '@type' => 'FAQPage',
            '@id' => $context['url'] . '#faq',
            'url' => $context['url'],
            'name' => $context['title'] . ' FAQs',
            'mainEntity' => $faq_entities,
            'isPartOf' => array(
                '@id' => home_url('/#website'),
            ),
        );
    }

    private function extract_faq_entities(\WP_Post $post): array
    {
        $raw_content = Content_Helper::get_content($post);

        if ('' === trim($raw_content)) {
            return array();
        }

        $heading_matches = array();

        preg_match_all('/<h([2-6])[^>]*>(.*?)<\/h\1>/is', $raw_content, $heading_matches, PREG_OFFSET_CAPTURE);

        if (empty($heading_matches[0])) {
            return array();
        }

        $faq_entities = array();
        $seen_questions = array();
        $heading_total = count($heading_matches[0]);

        for ($index = 0; $index < $heading_total; $index++) {
            $heading_html = (string) $heading_matches[0][$index][0];
            $heading_offset = (int) $heading_matches[0][$index][1];
            $heading_inner_html = (string) $heading_matches[2][$index][0];
            $question_text = trim(wp_strip_all_tags((string) html_entity_decode($heading_inner_html, ENT_QUOTES)));

            if ('' === $question_text || ! $this->is_question_style_heading($question_text)) {
                continue;
            }

            $question_key = $this->normalize_text_for_match($question_text);

            if ('' === $question_key || isset($seen_questions[$question_key])) {
                continue;
            }

            $answer_start = $heading_offset + strlen($heading_html);
            $next_heading_offset = $index + 1 < $heading_total ? (int) $heading_matches[0][$index + 1][1] : strlen($raw_content);

            if ($next_heading_offset <= $answer_start) {
                continue;
            }

            $answer_text = $this->sanitize_faq_answer(substr($raw_content, $answer_start, $next_heading_offset - $answer_start));

            if ('' === $answer_text || $this->count_words($answer_text) < 5) {
                continue;
            }

            $faq_entities[] = array(
                '@type' => 'Question',
                'name' => $question_text,
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text' => $answer_text,
                ),
            );

            $seen_questions[$question_key] = true;

            if (count($faq_entities) >= 8) {
                break;
            }
        }

        return $faq_entities;
    }

    private function sanitize_faq_answer(string $answer_html): string
    {
        $answer_text = trim(wp_strip_all_tags((string) html_entity_decode($answer_html, ENT_QUOTES)));
        $answer_text = preg_replace('/\s+/u', ' ', $answer_text);

        return is_string($answer_text) ? trim($answer_text) : '';
    }

    private function is_question_style_heading(string $heading_text): bool
    {
        $normalized_heading = $this->normalize_text_for_match($heading_text);

        if ('' === $normalized_heading) {
            return false;
        }

        if (false !== strpos($heading_text, '?')) {
            return true;
        }

        return 1 === preg_match('/^(how|what|why|when|where|who|can|should|is|are|do|does|will|which)\b/u', $normalized_heading);
    }

    private function count_words(string $text): int
    {
        $normalized_text = $this->normalize_text_for_match($text);

        if ('' === $normalized_text) {
            return 0;
        }

        return count(preg_split('/\s+/u', $normalized_text));
    }

    private function normalize_text_for_match(string $text): string
    {
        $normalized_text = trim(wp_strip_all_tags((string) html_entity_decode($text, ENT_QUOTES)));

        if ('' === $normalized_text) {
            return '';
        }

        $normalized_text = function_exists('mb_strtolower') ? mb_strtolower($normalized_text) : strtolower($normalized_text);
        $normalized_text = preg_replace('/[^\p{L}\p{N}\s\?]+/u', ' ', $normalized_text);
        $normalized_text = preg_replace('/\s+/u', ' ', $normalized_text);

        return is_string($normalized_text) ? trim($normalized_text) : '';
    }

    private function build_collection_item_list(array $context): array
    {
        if ('CollectionPage' !== $context['schema_type']) {
            return array();
        }

        $children = get_pages(
            array(
                'parent' => $context['post_id'],
                'sort_column' => 'menu_order,post_title',
                'post_status' => 'publish',
            )
        );

        if (empty($children)) {
            return array();
        }

        $items = array();
        $position = 1;

        foreach (array_slice($children, 0, 12) as $child) {
            if (! $child instanceof \WP_Post) {
                continue;
            }

            $items[] = array(
                '@type' => 'ListItem',
                'position' => $position,
                'name' => wp_strip_all_tags((string) get_the_title($child->ID)),
                'url' => (string) get_permalink($child->ID),
            );
            $position++;
        }

        if (empty($items)) {
            return array();
        }

        return array(
            '@type' => 'ItemList',
            '@id' => $context['url'] . '#items',
            'name' => $context['title'] . ' items',
            'itemListElement' => $items,
        );
    }

    private function build_breadcrumb_schema(array $context): array
    {
        $breadcrumb_items = $this->build_breadcrumb_items($context['post'], $context['site_name']);

        if (empty($breadcrumb_items)) {
            return array();
        }

        $items = array();

        foreach ($breadcrumb_items as $position => $item) {
            $items[] = array(
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            );
        }

        return array(
            '@type' => 'BreadcrumbList',
            '@id' => $context['url'] . '#breadcrumb',
            'itemListElement' => $items,
        );
    }

    private function build_breadcrumb_items(\WP_Post $post, string $home_label): array
    {
        $items = array(
            array(
                'name' => '' !== trim($home_label) ? $home_label : wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
                'url' => home_url('/'),
                'is_current' => false,
            ),
        );

        foreach (array_reverse(get_post_ancestors($post)) as $ancestor_id) {
            $items[] = array(
                'name' => wp_strip_all_tags((string) get_the_title((int) $ancestor_id)),
                'url' => (string) get_permalink((int) $ancestor_id),
                'is_current' => false,
            );
        }

        $items[] = array(
            'name' => wp_strip_all_tags((string) get_the_title($post->ID)),
            'url' => (string) get_permalink($post->ID),
            'is_current' => true,
        );

        return array_values(
            array_filter(
                $items,
                static function (array $item): bool {
                    return '' !== trim((string) ($item['name'] ?? '')) && '' !== trim((string) ($item['url'] ?? ''));
                }
            )
        );
    }

    private function build_non_singular_schema_entity(array $context): array
    {
        $entity = array(
            '@type' => $context['schema_type'],
            '@id' => ('' !== $context['url'] ? $context['url'] : home_url('/')) . '#primary',
            'name' => $context['title'],
            'url' => $context['url'],
            'isPartOf' => array(
                '@id' => home_url('/#website'),
            ),
        );

        if ('' !== $context['description']) {
            $entity['description'] = $context['description'];
        }

        return $entity;
    }

    private function resolve_schema_type(\WP_Post $post, string $url): string
    {
        if ('post' === $post->post_type) {
            return 'Article';
        }

        $path = strtolower((string) wp_parse_url($url, PHP_URL_PATH));
        $slug = strtolower((string) $post->post_name);
        $segments = $this->get_path_segments($path);

        if (false !== strpos($slug, 'contact') || false !== strpos($path, '/contact')) {
            return 'ContactPage';
        }

        if (false !== strpos($slug, 'about') || false !== strpos($path, '/about')) {
            return 'AboutPage';
        }

        if (in_array('services', $segments, true) || false !== strpos($slug, 'service')) {
            return $this->path_segment_has_children($segments, 'services') ? 'Service' : 'CollectionPage';
        }

        if (in_array('products', $segments, true) || false !== strpos($slug, 'product')) {
            return $this->path_segment_has_children($segments, 'products') ? 'Product' : 'CollectionPage';
        }

        return 'WebPage';
    }

    private function resolve_open_graph_type(\WP_Post $post, string $schema_type): string
    {
        if ('post' === $post->post_type || 'Article' === $schema_type) {
            return 'article';
        }

        if ('Product' === $schema_type) {
            return 'product';
        }

        return 'website';
    }

    private function resolve_effective_schema_type(\WP_Post $post, string $url): string
    {
        $override = trim((string) get_post_meta($post->ID, self::SCHEMA_TYPE_META_KEY, true));

        if (in_array($override, $this->get_allowed_schema_types(), true)) {
            return $override;
        }

        return $this->resolve_schema_type($post, $url);
    }

    private function resolve_robots_directives(int $post_id): string
    {
        $override = trim((string) get_post_meta($post_id, self::ROBOTS_DIRECTIVES_META_KEY, true));

        if (in_array($override, $this->get_allowed_robots_directives(), true)) {
            return $override;
        }

        if ('0' === (string) get_option('blog_public')) {
            return 'noindex,nofollow';
        }

        return 'index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1';
    }

    private function get_fallback_description(\WP_Post $post): string
    {
        $excerpt = trim(wp_strip_all_tags((string) $post->post_excerpt));

        if ('' !== $excerpt) {
            return wp_trim_words($excerpt, 30, '...');
        }

        return wp_trim_words(wp_strip_all_tags(Content_Helper::get_content($post)), 30, '...');
    }

    private function get_primary_image_url(int $post_id): string
    {
        if (has_post_thumbnail($post_id)) {
            $image_url = get_the_post_thumbnail_url($post_id, 'full');

            if (is_string($image_url) && '' !== $image_url) {
                return $image_url;
            }
        }

        return '';
    }

    private function get_site_logo_url(): string
    {
        $custom_logo_id = (int) get_theme_mod('custom_logo');

        if ($custom_logo_id > 0) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');

            if (is_string($logo_url) && '' !== $logo_url) {
                return $logo_url;
            }
        }

        $site_icon_url = get_site_icon_url(512);

        return is_string($site_icon_url) ? $site_icon_url : '';
    }

    private function get_meta_url(int $post_id, string $meta_key): string
    {
        $value = esc_url_raw((string) get_post_meta($post_id, $meta_key, true));

        return is_string($value) ? $value : '';
    }

    private function get_allowed_schema_types(): array
    {
        return array('WebPage', 'AboutPage', 'ContactPage', 'Article', 'Service', 'Product', 'CollectionPage');
    }

    private function get_allowed_robots_directives(): array
    {
        return array('index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow');
    }

    private function build_offer_schema(\WP_Post $post, array $context): array
    {
        $raw_price = $this->get_candidate_meta_value($post->ID, array('_price', 'price', 'product_price', 'service_price', 'starting_price', 'price_from'));

        if ('' === $raw_price) {
            return array();
        }

        $price = $this->normalize_price_value($raw_price);

        if ('' === $price) {
            return array();
        }

        $raw_currency = $this->get_candidate_meta_value($post->ID, array('price_currency', '_currency', 'currency'));
        $currency = $this->normalize_currency($raw_currency, $raw_price);

        if ('' === $currency) {
            return array();
        }

        return array(
            '@type' => 'Offer',
            'url' => $context['url'],
            'price' => $price,
            'priceCurrency' => $currency,
        );
    }

    private function normalize_price_value(string $raw_price): string
    {
        if (! preg_match('/\d+(?:[\.,]\d+)?/', $raw_price, $matches)) {
            return '';
        }

        return str_replace(',', '.', $matches[0]);
    }

    private function normalize_currency(string $raw_currency, string $raw_price): string
    {
        $currency = strtoupper(trim($raw_currency));

        if ('' === $currency) {
            if (false !== strpos($raw_price, 'EUR') || false !== strpos($raw_price, '€')) {
                return 'EUR';
            }

            if (false !== strpos($raw_price, 'USD') || false !== strpos($raw_price, '$')) {
                return 'USD';
            }

            if (false !== strpos($raw_price, 'GBP') || false !== strpos($raw_price, '£')) {
                return 'GBP';
            }

            if (false !== strpos($raw_price, 'RON') || false !== strpos($raw_price, 'LEI')) {
                return 'RON';
            }

            return '';
        }

        if ('LEI' === $currency) {
            return 'RON';
        }

        return preg_replace('/[^A-Z]/', '', $currency);
    }

    private function get_candidate_meta_value(int $post_id, array $keys): string
    {
        foreach ($keys as $key) {
            $value = get_post_meta($post_id, $key, true);

            if (is_scalar($value) && '' !== trim((string) $value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function get_primary_category_name(\WP_Post $post): string
    {
        if ($post->post_parent > 0) {
            return wp_strip_all_tags((string) get_the_title((int) $post->post_parent));
        }

        $post_type_object = get_post_type_object($post->post_type);

        if ($post_type_object && ! empty($post_type_object->labels->singular_name)) {
            return (string) $post_type_object->labels->singular_name;
        }

        return '';
    }

    private function has_collection_children(int $post_id): bool
    {
        $children = get_pages(
            array(
                'parent' => $post_id,
                'number' => 1,
                'post_status' => 'publish',
            )
        );

        return ! empty($children);
    }

    private function get_path_segments(string $path): array
    {
        $segments = explode('/', trim($path, '/'));

        return array_values(array_filter(array_map('strtolower', $segments)));
    }

    private function path_segment_has_children(array $segments, string $segment): bool
    {
        $index = array_search($segment, $segments, true);

        if (false === $index) {
            return false;
        }

        return isset($segments[$index + 1]) && '' !== $segments[$index + 1];
    }

    private function print_meta_tag(string $attribute, string $key, string $content, string $feature): void
    {
        if ('' === $content) {
            return;
        }

        echo "\n" . '<meta ' . esc_attr($attribute) . '="' . esc_attr($key) . '" content="' . esc_attr($content) . '" data-ai-seo-captain="approved" data-ai-seo-feature="' . esc_attr($feature) . '" />' . "\n";
    }

    /**
     * During WordPress Preview, apply pending AI content changes to post_content.
     * Works with any theme using the_content (Classic, Gutenberg, WPBakery, Divi, etc.).
     *
     * @param string $content The post content.
     * @return string Modified content with pending changes applied, or original.
     */
    public function filter_preview_content(string $content): string
    {
        if (! is_preview() || ! is_singular()) {
            return $content;
        }

        $post = get_queried_object();
        if (! $post instanceof \WP_Post) {
            return $content;
        }

        $pending = Content_Writer::get_pending_changes((int) $post->ID);
        if (empty($pending) || 'post_content' !== ($pending['builder'] ?? 'post_content')) {
            return $content;
        }

        return Content_Writer::apply_changes_to_string($content, $pending['changes']);
    }

    /**
     * During WordPress Preview, apply pending AI content changes to page builder meta.
     * Works with BeTheme, Elementor, Beaver Builder, Bricks, and all other builders
     * by intercepting their meta reads.
     *
     * @param mixed  $value     The value to return (null = let WordPress handle it).
     * @param int    $object_id The post ID.
     * @param string $meta_key  The meta key being read.
     * @param bool   $single    Whether to return a single value.
     * @return mixed Modified meta value or null.
     */
    public function filter_preview_builder_meta($value, int $object_id, string $meta_key, bool $single)
    {
        if (! is_preview() || ! is_singular()) {
            return $value;
        }

        // Only intercept known builder meta keys.
        static $builder_meta_keys = array(
            'mfn-page-items',
            '_elementor_data',
            '_fl_builder_data',
            '_bricks_page_content_2',
            '_themify_builder_settings_json',
            'ct_builder_shortcodes',
            'tve_updated_post',
            'brizy-post-editor-data',
            '_seedprod_page',
            'tatsu_sections',
        );

        if (! in_array($meta_key, $builder_meta_keys, true)) {
            return $value;
        }

        $pending = Content_Writer::get_pending_changes($object_id);
        if (empty($pending)) {
            return $value;
        }

        // Only apply if the pending changes target this builder.
        $pending_builder = $pending['builder'] ?? 'post_content';
        $target_key = '';

        // Map builder name to meta key.
        static $builder_map = array(
            'betheme' => 'mfn-page-items',
            'elementor' => '_elementor_data',
            'beaver' => '_fl_builder_data',
            'bricks' => '_bricks_page_content_2',
            'themify' => '_themify_builder_settings_json',
            'oxygen' => 'ct_builder_shortcodes',
            'thrive' => 'tve_updated_post',
            'brizy' => 'brizy-post-editor-data',
            'seedprod' => '_seedprod_page',
            'tatsu' => 'tatsu_sections',
        );

        $target_key = $builder_map[$pending_builder] ?? '';
        if ($meta_key !== $target_key) {
            return $value;
        }

        // Remove this filter temporarily to prevent infinite loop when reading the actual meta.
        remove_filter('get_post_metadata', array($this, 'filter_preview_builder_meta'), 1);
        $raw = get_post_meta($object_id, $meta_key, $single);
        add_filter('get_post_metadata', array($this, 'filter_preview_builder_meta'), 1, 4);

        if (empty($raw)) {
            return $value;
        }

        // BeTheme: base64-encoded serialized array — needs special handling.
        if ('betheme' === $pending_builder && is_string($raw)) {
            $decoded_b64 = base64_decode($raw, true);
            if (false === $decoded_b64) {
                return $value;
            }
            $data = @unserialize($decoded_b64);
            if (! is_array($data)) {
                return $value;
            }

            $changes = $pending['changes'] ?? array();
            foreach ($changes as $change) {
                $old = (string) ($change['old'] ?? '');
                $new = (string) ($change['new'] ?? '');
                if ('' === $old) {
                    continue;
                }

                // Heading tag changes.
                if (
                    preg_match('/^<(h[1-6])>(.*)<\/\1>$/is', trim($old), $old_m)
                    && preg_match('/^<(h[1-6])>(.*)<\/\1>$/is', trim($new), $new_m)
                ) {
                    Content_Writer::betheme_heading_replace_public($data, strtolower($old_m[1]), $old_m[2], strtolower($new_m[1]), $new_m[2]);
                    continue;
                }

                // Standard text replacement.
                $found = false;
                $data = Content_Writer::walk_replace_public($data, $old, $new, $found);
            }

            $modified = base64_encode(serialize($data));
            return $single ? array($modified) : array($modified);
        }

        $content = is_string($raw) ? $raw : maybe_serialize($raw);
        $modified = Content_Writer::apply_changes_to_string($content, $pending['changes']);

        // Return in the format WordPress expects.
        if ($single) {
            $unserialized = maybe_unserialize($modified);
            return array($unserialized);
        }

        return array(maybe_unserialize($modified));
    }

    /**
     * RSS Feed Optimization — inject content before/after feed items and optional featured image.
     */
    public function filter_rss_content(string $content): string
    {
        $options = $this->settings->get();
        $before = '';
        $after = '';

        // Featured image.
        if (! empty($options['rss_featured_image'])) {
            $post_id = get_the_ID();
            if ($post_id && has_post_thumbnail($post_id)) {
                $img = get_the_post_thumbnail($post_id, 'medium');
                if ($img) {
                    $before .= '<p>' . $img . '</p>';
                }
            }
        }

        // Before content.
        $rss_before = trim((string) ($options['rss_before_content'] ?? ''));
        if ('' !== $rss_before) {
            $before .= '<p>' . $this->replace_rss_placeholders($rss_before) . '</p>';
        }

        // After content.
        $rss_after = trim((string) ($options['rss_after_content'] ?? ''));
        if ('' !== $rss_after) {
            $after .= '<p>' . $this->replace_rss_placeholders($rss_after) . '</p>';
        }

        // Publication delay.
        $delay = (int) ($options['rss_publication_delay'] ?? 0);
        if ($delay > 0) {
            $post_time = get_post_time('U', true);
            if ($post_time && (time() - $post_time) < ($delay * 60)) {
                return ''; // Still within delay window — suppress content.
            }
        }

        return $before . $content . $after;
    }

    private function replace_rss_placeholders(string $text): string
    {
        $text = str_replace('%%sitename%%', esc_html(get_bloginfo('name')), $text);
        $post_id = get_the_ID();
        if ($post_id) {
            $link = '<a href="' . esc_url(get_permalink($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>';
            $text = str_replace('%%post_link%%', $link, $text);
        }
        return $text;
    }

    /**
     * Crawl Budget: Redirect author archives to homepage.
     */
    public function redirect_author_archives(): void
    {
        if (is_author()) {
            wp_safe_redirect(home_url('/'), 301);
            exit;
        }
    }

    /**
     * Crawl Budget: Redirect date archives to homepage.
     */
    public function redirect_date_archives(): void
    {
        if (is_date()) {
            wp_safe_redirect(home_url('/'), 301);
            exit;
        }
    }

    /**
     * Crawl Budget: Redirect attachment pages to parent or file.
     */
    public function redirect_attachment_pages(): void
    {
        if (is_attachment()) {
            $parent_id = wp_get_post_parent_id(get_the_ID());
            if ($parent_id) {
                wp_safe_redirect(get_permalink($parent_id), 301);
            } else {
                $url = wp_get_attachment_url(get_the_ID());
                if ($url) {
                    wp_safe_redirect($url, 301);
                } else {
                    wp_safe_redirect(home_url('/'), 301);
                }
            }
            exit;
        }
    }

    /**
     * Crawl Budget: Redirect post format archives to homepage.
     */
    public function redirect_format_archives(): void
    {
        if (is_tax('post_format')) {
            wp_safe_redirect(home_url('/'), 301);
            exit;
        }
    }

    /**
     * Build LocalBusiness schema entity from Local SEO settings.
     */
    private function build_local_business_schema(array $options): array
    {
        $name = trim((string) ($options['local_business_name'] ?? ''));
        if ('' === $name) {
            $name = (string) get_bloginfo('name');
        }

        $entity = array(
            '@type' => $options['local_business_type'] ?? 'LocalBusiness',
            '@id'   => home_url('/#localbusiness'),
            'name'  => $name,
            'url'   => home_url('/'),
        );

        // Address.
        $street  = trim((string) ($options['local_street'] ?? ''));
        $city    = trim((string) ($options['local_city'] ?? ''));
        $state   = trim((string) ($options['local_state'] ?? ''));
        $zip     = trim((string) ($options['local_zip'] ?? ''));
        $country = trim((string) ($options['local_country'] ?? ''));

        if ('' !== $street || '' !== $city) {
            $address = array('@type' => 'PostalAddress');
            if ('' !== $street) {
                $address['streetAddress'] = $street;
            }
            if ('' !== $city) {
                $address['addressLocality'] = $city;
            }
            if ('' !== $state) {
                $address['addressRegion'] = $state;
            }
            if ('' !== $zip) {
                $address['postalCode'] = $zip;
            }
            if ('' !== $country) {
                $address['addressCountry'] = $country;
            }
            $entity['address'] = $address;
        }

        // Contact.
        $phone = trim((string) ($options['local_phone'] ?? ''));
        $email = trim((string) ($options['local_email'] ?? ''));
        if ('' !== $phone) {
            $entity['telephone'] = $phone;
        }
        if ('' !== $email) {
            $entity['email'] = $email;
        }

        // Geo coordinates.
        $lat = trim((string) ($options['local_lat'] ?? ''));
        $lng = trim((string) ($options['local_lng'] ?? ''));
        if ('' !== $lat && '' !== $lng) {
            $entity['geo'] = array(
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float) $lat,
                'longitude' => (float) $lng,
            );
        }

        // Price range.
        $price_range = trim((string) ($options['local_price_range'] ?? ''));
        if ('' !== $price_range) {
            $entity['priceRange'] = $price_range;
        }

        // Logo.
        $logo_id = get_theme_mod('custom_logo');
        if ($logo_id) {
            $logo_url = wp_get_attachment_image_url($logo_id, 'full');
            if ($logo_url) {
                $entity['logo'] = $logo_url;
                $entity['image'] = $logo_url;
            }
        }

        // Opening hours.
        $day_map = array(
            'mon' => 'Monday',
            'tue' => 'Tuesday',
            'wed' => 'Wednesday',
            'thu' => 'Thursday',
            'fri' => 'Friday',
            'sat' => 'Saturday',
            'sun' => 'Sunday',
        );
        $hours_specs = array();
        foreach ($day_map as $dk => $dl) {
            $hours = trim((string) ($options['local_hours_' . $dk] ?? ''));
            if ('' !== $hours && preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $hours, $m)) {
                $hours_specs[] = array(
                    '@type'     => 'OpeningHoursSpecification',
                    'dayOfWeek' => $dl,
                    'opens'     => $m[1],
                    'closes'    => $m[2],
                );
            }
        }
        if (! empty($hours_specs)) {
            $entity['openingHoursSpecification'] = $hours_specs;
        }

        return $entity;
    }

    /**
     * Render [ai_seo_map] shortcode — Google Maps embed from Local SEO coordinates.
     */
    public function render_map_shortcode(array $atts): string
    {
        $options = $this->settings->get();
        $atts = shortcode_atts(array(
            'width'  => '100%',
            'height' => '400',
            'zoom'   => '15',
        ), $atts, 'ai_seo_map');

        $lat = trim((string) ($options['local_lat'] ?? ''));
        $lng = trim((string) ($options['local_lng'] ?? ''));

        if ('' === $lat || '' === $lng) {
            // Fallback: use address for a search query.
            $parts = array_filter(array(
                $options['local_street'] ?? '',
                $options['local_city'] ?? '',
                $options['local_state'] ?? '',
                $options['local_country'] ?? '',
            ));
            if (empty($parts)) {
                return '<!-- SEO Captain: No location data configured for map. -->';
            }
            $query = urlencode(implode(', ', $parts));
            $src = 'https://maps.google.com/maps?q=' . $query . '&output=embed';
        } else {
            $src = 'https://maps.google.com/maps?q=' . urlencode($lat . ',' . $lng) . '&z=' . (int) $atts['zoom'] . '&output=embed';
        }

        $w = esc_attr($atts['width']);
        $h = esc_attr($atts['height']);

        return '<div class="ai-seo-map-wrap" style="max-width:100%;overflow:hidden;"><iframe src="' . esc_url($src) . '" width="' . $w . '" height="' . $h . '" style="border:0;max-width:100%;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>';
    }
}
