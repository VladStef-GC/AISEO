<?php

namespace AI_SEO_Keeper;

class Settings
{
    public const OPTION_NAME = 'ai_seo_keeper_options';

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
            'api_key'              => '',
            'system_prompt'        => 'You are the SEO copilot for this WordPress site. Suggest clear, differentiated, search-intent-aware metadata and explain tradeoffs briefly.',
            'google_tracking_code' => '',
            'bing_tracking_code'   => '',
            'editor_chat_enabled'  => 1,
            'frontend_output_enabled' => 0,
            'frontend_override_conflicts' => 0,
            'search_appearance_auto_enabled' => 1,
            'search_title_separator' => '|',
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
            'sitemap_include_tags' => 1,
            'indexnow_enabled' => 0,
            'indexnow_auto_submit' => 1,
            'indexnow_key' => '',
            'audit_skip_patterns' => '',
        );

        foreach (self::FEATURE_FLAGS as $feature_key => $label) {
            $defaults['feature_' . $feature_key] = 1;
        }

        return $defaults;
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

        $output['provider']             = isset($input['provider']) ? sanitize_key($input['provider']) : $current['provider'];
        $output['model']                = isset($input['model']) ? sanitize_text_field($input['model']) : $current['model'];
        $output['api_key']              = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : $current['api_key'];
        $output['system_prompt']        = isset($input['system_prompt']) ? sanitize_textarea_field($input['system_prompt']) : $current['system_prompt'];
        $output['google_tracking_code'] = isset($input['google_tracking_code']) ? sanitize_text_field($input['google_tracking_code']) : '';
        $output['bing_tracking_code']   = isset($input['bing_tracking_code']) ? sanitize_text_field($input['bing_tracking_code']) : '';
        $output['editor_chat_enabled']  = empty($input['editor_chat_enabled']) ? 0 : 1;
        $output['frontend_output_enabled'] = empty($input['frontend_output_enabled']) ? 0 : 1;
        $output['frontend_override_conflicts'] = empty($input['frontend_override_conflicts']) ? 0 : 1;
        $output['search_appearance_auto_enabled'] = empty($input['search_appearance_auto_enabled']) ? 0 : 1;
        $output['search_title_separator'] = isset($input['search_title_separator']) ? $this->sanitize_separator((string) $input['search_title_separator']) : $current['search_title_separator'];
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
        $output['indexnow_enabled'] = empty($input['indexnow_enabled']) ? 0 : 1;
        $output['indexnow_auto_submit'] = empty($input['indexnow_auto_submit']) ? 0 : 1;
        $output['indexnow_key'] = isset($input['indexnow_key']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $input['indexnow_key']) : $current['indexnow_key'];
        $output['audit_skip_patterns'] = isset($input['audit_skip_patterns']) ? sanitize_textarea_field($input['audit_skip_patterns']) : $current['audit_skip_patterns'];

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
}
