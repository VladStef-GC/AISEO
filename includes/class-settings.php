<?php

namespace AI_SEO_Keeper;

class Settings
{
    public const OPTION_NAME = 'ai_seo_keeper_options';

    /**
     * Curated text-only models for plugin workflows that require JSON output.
     *
     * This excludes realtime/audio/image-only models by design.
     */
    private const PROVIDER_MODELS = array(
        'openai' => array(
            'gpt-5.5' => array('label' => 'GPT-5.5', 'tier' => 'stable'),
            'gpt-5.4' => array('label' => 'GPT-5.4', 'tier' => 'stable'),
            'gpt-5.4-mini' => array('label' => 'GPT-5.4 Mini', 'tier' => 'stable'),
            'gpt-5.4-nano' => array('label' => 'GPT-5.4 Nano', 'tier' => 'stable'),
            'o3' => array('label' => 'o3', 'tier' => 'stable'),
            'o4-mini' => array('label' => 'o4-mini', 'tier' => 'stable'),
            'gpt-4.1' => array('label' => 'GPT-4.1', 'tier' => 'stable'),
            'gpt-4.1-mini' => array('label' => 'GPT-4.1 Mini', 'tier' => 'stable'),
            'gpt-4.1-nano' => array('label' => 'GPT-4.1 Nano', 'tier' => 'stable'),
        ),
        'google' => array(
            'gemini-3.1-pro-preview' => array('label' => 'Gemini 3.1 Pro', 'tier' => 'preview'),
            'gemini-3-flash-preview' => array('label' => 'Gemini 3 Flash', 'tier' => 'preview'),
            'gemini-3.1-flash-lite' => array('label' => 'Gemini 3.1 Flash-Lite', 'tier' => 'stable'),
            'gemini-3.1-flash-lite-preview' => array('label' => 'Gemini 3.1 Flash-Lite', 'tier' => 'preview'),
            'gemini-2.5-pro' => array('label' => 'Gemini 2.5 Pro', 'tier' => 'stable'),
            'gemini-2.5-flash' => array('label' => 'Gemini 2.5 Flash', 'tier' => 'stable'),
            'gemini-2.5-flash-lite' => array('label' => 'Gemini 2.5 Flash-Lite', 'tier' => 'stable'),
        ),
    );

    public const FEATURE_FLAGS = array(
        'meta_title'       => 'Meta Title',
        'meta_description' => 'Meta Description',
        'open_graph'       => 'Open Graph',
        'twitter_cards'    => 'Twitter Cards',
        'canonical'        => 'Canonical URL',
        'robots'           => 'Robots Directives',
        'schema'           => 'Schema Output',
    );

    public function __construct()
    {
        add_action('admin_init', array($this, 'register'));
    }

    public static function defaults(): array
    {
        $defaults = array(
            'provider'             => 'openai',
            'model'                => 'gpt-4.1-mini',
            'custom_model_enabled' => 0,
            'custom_model_id'      => '',
            'ai_temperature'       => 0.3,
            'api_key'              => '',
            'system_prompt'        => 'You are the SEO copilot for this WordPress site. Suggest clear, differentiated, search-intent-aware metadata and explain tradeoffs briefly.',
            'google_tracking_code' => '',
            'bing_tracking_code'   => '',
            'editor_chat_enabled'  => 1,
            'frontend_output_enabled' => 0,
            'frontend_override_conflicts' => 0,
            'search_appearance_auto_enabled' => 1,
            'search_title_separator' => '|',
            'site_brand'            => '',
            'search_title_template_post' => '%%title%% %%sep%% %%sitename%%',
            'search_title_template_page' => '%%title%% %%sep%% %%sitename%%',
            'search_title_template_default' => '%%title%% %%sep%% %%sitename%%',
            'search_title_template_category' => '%%term_title%% %%sep%% %%sitename%%',
            'search_title_template_tag' => '%%term_title%% %%sep%% %%sitename%%',
            'search_title_template_author' => '%%author%% %%sep%% %%sitename%%',
            'search_title_template_date' => '%%date%% %%sep%% %%sitename%%',
            'search_title_template_search' => 'Search results for "%%searchphrase%%" %%sep%% %%sitename%%',
            'search_title_template_archive' => '%%archive_title%% %%sep%% %%sitename%%',
            'search_title_template_404' => 'Page not found %%sep%% %%sitename%%',
            'noindex_categories' => 0,
            'noindex_tags' => 0,
            'noindex_author_archives' => 0,
            'noindex_date_archives' => 1,
            'noindex_search_results' => 1,
            'noindex_format_archives' => 1,
            'sitemap_enabled' => 1,
            'sitemap_include_posts' => 1,
            'sitemap_include_pages' => 1,
            'sitemap_include_categories' => 1,
            'sitemap_include_tags'            => 1,
            'sitemap_include_wc_products'    => 1,
            'sitemap_include_wc_product_cat' => 1,
            'sitemap_include_wc_product_tag' => 1,
            'wc_integration_enabled'         => 0,
            'wc_schema_enrichment_enabled'   => 1,
            'wc_ai_context_enabled'          => 1,
            'indexnow_enabled' => 0,
            'indexnow_auto_submit' => 1,
            'indexnow_key' => '',
            'audit_skip_patterns' => '',
            'social_facebook'     => '',
            'social_twitter'      => '',
            'social_instagram'    => '',
            'social_linkedin'     => '',
            'social_youtube'      => '',
            'social_pinterest'    => '',
            'robots_txt_custom'   => '',

            // Local SEO / Business Schema.
            'local_seo_enabled'      => 0,
            'local_business_type'    => 'LocalBusiness',
            'local_business_name'    => '',
            'local_street'           => '',
            'local_city'             => '',
            'local_state'            => '',
            'local_zip'              => '',
            'local_country'          => '',
            'local_phone'            => '',
            'local_email'            => '',
            'local_lat'              => '',
            'local_lng'              => '',
            'local_hours_mon'        => '',
            'local_hours_tue'        => '',
            'local_hours_wed'        => '',
            'local_hours_thu'        => '',
            'local_hours_fri'        => '',
            'local_hours_sat'        => '',
            'local_hours_sun'        => '',
            'local_price_range'      => '',

            // RSS Feed Optimization.
            'rss_before_content'     => '',
            'rss_after_content'      => '',
            'rss_featured_image'     => 0,
            'rss_publication_delay'  => 0,

            // Crawl Budget Optimization.
            'crawl_disable_author_archives'  => 0,
            'crawl_disable_date_archives'    => 0,
            'crawl_disable_attachment_pages' => 0,
            'crawl_disable_search_indexing'  => 0,
            'crawl_disable_format_archives'  => 0,
            'crawl_remove_wp_version'        => 0,
            'crawl_remove_shortlink'         => 0,
            'crawl_remove_rsd_link'          => 0,
            'crawl_remove_feed_links'        => 0,
        );

        foreach (self::FEATURE_FLAGS as $feature_key => $label) {
            $defaults['feature_' . $feature_key] = 1;
        }

        return $defaults;
    }

    public static function get_supported_providers(): array
    {
        return array_keys(self::PROVIDER_MODELS);
    }

    public static function get_models_for_provider(string $provider): array
    {
        $provider = sanitize_key($provider);

        if (! isset(self::PROVIDER_MODELS[$provider]) || ! is_array(self::PROVIDER_MODELS[$provider])) {
            return array();
        }

        $labels = array();

        foreach (self::PROVIDER_MODELS[$provider] as $model_id => $meta) {
            if (! is_array($meta) || empty($meta['label'])) {
                continue;
            }

            $labels[$model_id] = (string) $meta['label'];
        }

        return $labels;
    }

    public static function get_model_catalog_for_provider(string $provider): array
    {
        $provider = sanitize_key($provider);

        return isset(self::PROVIDER_MODELS[$provider]) && is_array(self::PROVIDER_MODELS[$provider])
            ? self::PROVIDER_MODELS[$provider]
            : array();
    }

    public static function get_default_model_for_provider(string $provider): string
    {
        $provider = sanitize_key($provider);

        $preferred_defaults = array(
            'openai' => 'gpt-4.1-mini',
            'google' => 'gemini-2.5-flash',
        );

        if (isset($preferred_defaults[$provider])) {
            return $preferred_defaults[$provider];
        }

        $models = self::get_models_for_provider($provider);

        if (! empty($models)) {
            $keys = array_keys($models);

            return (string) $keys[0];
        }

        $defaults = self::defaults();

        return (string) ($defaults['model'] ?? 'gpt-4.1-mini');
    }

    public static function sanitize_provider_model(string $provider, string $model): string
    {
        $model = sanitize_text_field($model);
        $models = self::get_models_for_provider($provider);

        if (isset($models[$model])) {
            return $model;
        }

        return self::get_default_model_for_provider($provider);
    }

    public static function sanitize_custom_model_id(string $model): string
    {
        $model = sanitize_text_field($model);
        $model = preg_replace('/[^a-zA-Z0-9._:-]/', '', $model);

        if (! is_string($model)) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return trim(mb_substr($model, 0, 120));
        }

        return trim(substr($model, 0, 120));
    }

    public function register(): void
    {
        register_setting(
            'ai_seo_keeper_settings',
            self::OPTION_NAME,
            array($this, 'sanitize')
        );
    }

    public function get(): array
    {
        return wp_parse_args(get_option(self::OPTION_NAME, array()), self::defaults());
    }

    public function sanitize(array $input): array
    {
        $current = $this->get();
        $output  = self::defaults();

        $raw_provider = isset($input['provider']) ? sanitize_key($input['provider']) : (string) $current['provider'];
        $supported_providers = self::get_supported_providers();
        $output['provider'] = in_array($raw_provider, $supported_providers, true) ? $raw_provider : (string) $current['provider'];

        $raw_model = isset($input['model']) ? sanitize_text_field($input['model']) : (string) $current['model'];
        $output['custom_model_enabled'] = empty($input['custom_model_enabled']) ? 0 : 1;
        $output['custom_model_id'] = isset($input['custom_model_id']) ? self::sanitize_custom_model_id((string) $input['custom_model_id']) : (string) ($current['custom_model_id'] ?? '');

        if (! empty($output['custom_model_enabled']) && '' !== $output['custom_model_id']) {
            $output['model'] = $output['custom_model_id'];
        } else {
            $output['custom_model_enabled'] = 0;
            $output['model'] = self::sanitize_provider_model($output['provider'], $raw_model);
        }
        $output['ai_temperature'] = isset($input['ai_temperature'])
            ? $this->sanitize_temperature((string) $input['ai_temperature'])
            : $this->sanitize_temperature((string) ($current['ai_temperature'] ?? '0.3'));
        $output['api_key']              = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : $current['api_key'];
        $output['system_prompt']        = isset($input['system_prompt']) ? sanitize_textarea_field($input['system_prompt']) : $current['system_prompt'];
        $output['google_tracking_code'] = isset($input['google_tracking_code']) ? sanitize_text_field($input['google_tracking_code']) : '';
        $output['bing_tracking_code']   = isset($input['bing_tracking_code']) ? sanitize_text_field($input['bing_tracking_code']) : '';
        $output['editor_chat_enabled']  = empty($input['editor_chat_enabled']) ? 0 : 1;
        $output['frontend_output_enabled'] = empty($input['frontend_output_enabled']) ? 0 : 1;
        $output['frontend_override_conflicts'] = empty($input['frontend_override_conflicts']) ? 0 : 1;
        $output['search_appearance_auto_enabled'] = empty($input['search_appearance_auto_enabled']) ? 0 : 1;
        $output['search_title_separator'] = isset($input['search_title_separator']) ? $this->sanitize_separator((string) $input['search_title_separator']) : $current['search_title_separator'];
        $output['site_brand'] = isset($input['site_brand']) ? sanitize_text_field(wp_unslash($input['site_brand'])) : $current['site_brand'];
        $output['search_title_template_post'] = isset($input['search_title_template_post']) ? $this->sanitize_template((string) $input['search_title_template_post']) : $current['search_title_template_post'];
        $output['search_title_template_page'] = isset($input['search_title_template_page']) ? $this->sanitize_template((string) $input['search_title_template_page']) : $current['search_title_template_page'];
        $output['search_title_template_default'] = isset($input['search_title_template_default']) ? $this->sanitize_template((string) $input['search_title_template_default']) : $current['search_title_template_default'];
        $output['search_title_template_category'] = isset($input['search_title_template_category']) ? $this->sanitize_template((string) $input['search_title_template_category']) : $current['search_title_template_category'];
        $output['search_title_template_tag'] = isset($input['search_title_template_tag']) ? $this->sanitize_template((string) $input['search_title_template_tag']) : $current['search_title_template_tag'];
        $output['search_title_template_author'] = isset($input['search_title_template_author']) ? $this->sanitize_template((string) $input['search_title_template_author']) : $current['search_title_template_author'];
        $output['search_title_template_date'] = isset($input['search_title_template_date']) ? $this->sanitize_template((string) $input['search_title_template_date']) : $current['search_title_template_date'];
        $output['search_title_template_search'] = isset($input['search_title_template_search']) ? $this->sanitize_template((string) $input['search_title_template_search']) : $current['search_title_template_search'];
        $output['search_title_template_archive'] = isset($input['search_title_template_archive']) ? $this->sanitize_template((string) $input['search_title_template_archive']) : $current['search_title_template_archive'];
        $output['search_title_template_404'] = isset($input['search_title_template_404']) ? $this->sanitize_template((string) $input['search_title_template_404']) : $current['search_title_template_404'];
        $output['noindex_categories'] = empty($input['noindex_categories']) ? 0 : 1;
        $output['noindex_tags'] = empty($input['noindex_tags']) ? 0 : 1;
        $output['noindex_author_archives'] = empty($input['noindex_author_archives']) ? 0 : 1;
        $output['noindex_date_archives'] = empty($input['noindex_date_archives']) ? 0 : 1;
        $output['noindex_search_results'] = empty($input['noindex_search_results']) ? 0 : 1;
        $output['noindex_format_archives'] = empty($input['noindex_format_archives']) ? 0 : 1;
        $output['sitemap_enabled'] = empty($input['sitemap_enabled']) ? 0 : 1;
        $output['sitemap_include_posts'] = empty($input['sitemap_include_posts']) ? 0 : 1;
        $output['sitemap_include_pages'] = empty($input['sitemap_include_pages']) ? 0 : 1;
        $output['sitemap_include_categories'] = empty($input['sitemap_include_categories']) ? 0 : 1;
        $output['sitemap_include_tags'] = empty($input['sitemap_include_tags']) ? 0 : 1;
        $output['sitemap_include_wc_products']    = empty($input['sitemap_include_wc_products'])    ? 0 : 1;
        $output['sitemap_include_wc_product_cat'] = empty($input['sitemap_include_wc_product_cat']) ? 0 : 1;
        $output['sitemap_include_wc_product_tag'] = empty($input['sitemap_include_wc_product_tag']) ? 0 : 1;
        $output['wc_integration_enabled']         = empty($input['wc_integration_enabled'])         ? 0 : 1;
        $output['wc_schema_enrichment_enabled']   = empty($input['wc_schema_enrichment_enabled'])   ? 0 : 1;
        $output['wc_ai_context_enabled']          = empty($input['wc_ai_context_enabled'])          ? 0 : 1;
        $output['indexnow_enabled'] = empty($input['indexnow_enabled']) ? 0 : 1;
        $output['indexnow_auto_submit'] = empty($input['indexnow_auto_submit']) ? 0 : 1;
        $output['indexnow_key'] = isset($input['indexnow_key']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $input['indexnow_key']) : $current['indexnow_key'];
        $output['audit_skip_patterns'] = isset($input['audit_skip_patterns']) ? sanitize_textarea_field($input['audit_skip_patterns']) : $current['audit_skip_patterns'];

        // Social profiles — sanitize as URLs.
        foreach (array('social_facebook', 'social_twitter', 'social_instagram', 'social_linkedin', 'social_youtube', 'social_pinterest') as $social_key) {
            $output[$social_key] = isset($input[$social_key]) ? esc_url_raw(trim((string) $input[$social_key])) : $current[$social_key];
        }

        // Robots.txt custom rules.
        $output['robots_txt_custom'] = isset($input['robots_txt_custom']) ? sanitize_textarea_field($input['robots_txt_custom']) : $current['robots_txt_custom'];

        // Local SEO / Business Schema.
        $output['local_seo_enabled'] = empty($input['local_seo_enabled']) ? 0 : 1;
        $valid_business_types = array('LocalBusiness', 'Store', 'Restaurant', 'HealthAndBeautyBusiness', 'LegalService', 'FinancialService', 'EducationalOrganization', 'LodgingBusiness', 'SportsActivityLocation', 'EntertainmentBusiness', 'HomeAndConstructionBusiness', 'AutomotiveBusiness', 'MedicalBusiness', 'ProfessionalService', 'RealEstateAgent');
        $output['local_business_type'] = isset($input['local_business_type']) && in_array($input['local_business_type'], $valid_business_types, true) ? $input['local_business_type'] : $current['local_business_type'];
        foreach (array('local_business_name', 'local_street', 'local_city', 'local_state', 'local_zip', 'local_country', 'local_phone', 'local_email', 'local_price_range') as $lk) {
            $output[$lk] = isset($input[$lk]) ? sanitize_text_field(wp_unslash($input[$lk])) : $current[$lk];
        }
        $output['local_lat'] = isset($input['local_lat']) ? sanitize_text_field($input['local_lat']) : $current['local_lat'];
        $output['local_lng'] = isset($input['local_lng']) ? sanitize_text_field($input['local_lng']) : $current['local_lng'];
        foreach (array('local_hours_mon', 'local_hours_tue', 'local_hours_wed', 'local_hours_thu', 'local_hours_fri', 'local_hours_sat', 'local_hours_sun') as $hk) {
            $output[$hk] = isset($input[$hk]) ? sanitize_text_field($input[$hk]) : $current[$hk];
        }

        // RSS Feed Optimization.
        $output['rss_before_content'] = isset($input['rss_before_content']) ? wp_kses_post($input['rss_before_content']) : $current['rss_before_content'];
        $output['rss_after_content'] = isset($input['rss_after_content']) ? wp_kses_post($input['rss_after_content']) : $current['rss_after_content'];
        $output['rss_featured_image'] = empty($input['rss_featured_image']) ? 0 : 1;
        $output['rss_publication_delay'] = isset($input['rss_publication_delay']) ? max(0, (int) $input['rss_publication_delay']) : $current['rss_publication_delay'];

        // Crawl Budget Optimization.
        foreach (array('crawl_disable_author_archives', 'crawl_disable_date_archives', 'crawl_disable_attachment_pages', 'crawl_disable_search_indexing', 'crawl_disable_format_archives', 'crawl_remove_wp_version', 'crawl_remove_shortlink', 'crawl_remove_rsd_link', 'crawl_remove_feed_links') as $ck) {
            $output[$ck] = empty($input[$ck]) ? 0 : 1;
        }

        foreach (self::FEATURE_FLAGS as $feature_key => $label) {
            $output['feature_' . $feature_key] = empty($input['feature_' . $feature_key]) ? 0 : 1;
        }

        return $output;
    }

    private function sanitize_separator(string $separator): string
    {
        $separator = trim(sanitize_text_field($separator));

        if ('' === $separator) {
            return '|';
        }

        $separator = function_exists('mb_substr') ? mb_substr($separator, 0, 3) : substr($separator, 0, 3);

        return trim($separator);
    }

    private function sanitize_template(string $template): string
    {
        $template = trim(sanitize_text_field($template));

        if ('' === $template) {
            return '%%title%% %%sep%% %%sitename%%';
        }

        return $template;
    }

    private function sanitize_temperature(string $temperature): float
    {
        $temperature = trim($temperature);

        if ('' === $temperature) {
            return 0.3;
        }

        $numeric = is_numeric($temperature) ? (float) $temperature : 0.3;
        $numeric = max(0.0, min(2.0, $numeric));

        return round($numeric, 1);
    }

    /**
     * Get the effective site brand name.
     * Falls back to the WordPress site name when the setting is empty.
     */
    public function get_site_brand(): string
    {
        $options = $this->get();
        $brand = trim((string) ($options['site_brand'] ?? ''));

        if ('' === $brand) {
            $brand = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        }

        return $brand;
    }

    /**
     * Build the branding suffix string (e.g. " | Green Coders").
     * Returns empty string when brand is empty.
     */
    public function get_branding_suffix(): string
    {
        $brand = $this->get_site_brand();

        if ('' === $brand) {
            return '';
        }

        $options = $this->get();
        $separator = trim((string) ($options['search_title_separator'] ?? '|'));

        return ' ' . $separator . ' ' . $brand;
    }

    /**
     * Get the maximum character budget for the page-specific part of the title.
     * Subtracts the branding suffix length from 60.
     */
    public function get_title_part_max_length(int $total_max = 60): int
    {
        $suffix = $this->get_branding_suffix();
        $suffix_len = function_exists('mb_strlen') ? mb_strlen($suffix) : strlen($suffix);

        return max(10, $total_max - $suffix_len);
    }
}
