<?php

/**
 * Settings page view.
 *
 * Variables available (set by Admin::render_settings_page):
 *   $options, $indexnow_enabled, $indexnow_auto_submit,
 *   $indexnow_key, $indexnow_key_url, $settings_status,
 *   $settings_message, $yoast_import_action
 *
 * @package AI_SEO_Keeper
 */

defined('ABSPATH') || exit;

use AI_SEO_Keeper\Settings;

$supported_providers = Settings::get_supported_providers();
$provider_models = array();
$provider_labels = array(
    'openai' => 'OpenAI',
    'google' => 'Google',
);

foreach ($supported_providers as $provider_key) {
    $provider_models[$provider_key] = Settings::get_model_catalog_for_provider($provider_key);
}

$active_provider = isset($options['provider']) ? sanitize_key((string) $options['provider']) : 'openai';

if (! isset($provider_models[$active_provider])) {
    $active_provider = 'openai';
}

$active_models = $provider_models[$active_provider] ?? array();
$saved_model = (string) ($options['model'] ?? '');
$custom_model_enabled = ! empty($options['custom_model_enabled']);
$custom_model_id = (string) ($options['custom_model_id'] ?? '');

if ($custom_model_enabled && '' !== trim($custom_model_id)) {
    $active_model = $custom_model_id;
} else {
    $active_model = Settings::sanitize_provider_model($active_provider, $saved_model);
}

$active_temperature = isset($options['ai_temperature']) ? (float) $options['ai_temperature'] : 0.3;
?>
<div class="wrap">
    <h1>AI SEO Keeper Settings</h1>
    <?php if ('' !== $settings_message) : ?>
        <div class="notice <?php echo 'success' === $settings_status ? 'notice-success' : 'notice-error'; ?> is-dismissible">
            <p><?php echo esc_html($settings_message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields('ai_seo_keeper_settings'); ?>

        <div class="ai-seo-accordion">

            <!-- Section 1: AI API Settings -->
            <div class="ai-seo-accordion-section is-open">
                <div class="ai-seo-accordion-header">
                    <h2>AI API Settings</h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="ai-seo-provider">AI provider</label></th>
                            <td>
                                <select id="ai-seo-provider" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[provider]">
                                    <?php foreach ($supported_providers as $provider_key) : ?>
                                        <option value="<?php echo esc_attr($provider_key); ?>" <?php selected($active_provider, $provider_key); ?>><?php echo esc_html($provider_labels[$provider_key] ?? ucfirst($provider_key)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-model">Model</label></th>
                            <td>
                                <select
                                    id="ai-seo-model"
                                    name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[model]"
                                    data-provider-models="<?php echo esc_attr(wp_json_encode($provider_models)); ?>">
                                    <?php foreach ($active_models as $model_id => $model_meta) : ?>
                                        <?php
                                        $model_label = isset($model_meta['label']) ? (string) $model_meta['label'] : (string) $model_id;
                                        $model_tier = isset($model_meta['tier']) ? (string) $model_meta['tier'] : 'stable';
                                        $tier_suffix = 'preview' === $model_tier ? ' [Preview]' : ' [Stable]';
                                        ?>
                                        <option value="<?php echo esc_attr($model_id); ?>" data-tier="<?php echo esc_attr($model_tier); ?>" <?php selected($active_model, $model_id); ?>><?php echo esc_html($model_label . $tier_suffix . ' (' . $model_id . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span id="ai-seo-model-tier-badge" class="ai-seo-model-tier-badge" aria-live="polite"></span>
                                <p class="description" style="margin-top:8px;">
                                    Curated text-generation models only. Image, TTS, realtime, and transcription models are intentionally excluded.
                                </p>
                                <label class="ai-seo-custom-model-toggle" style="display:block;margin-top:8px;">
                                    <input id="ai-seo-custom-model-enabled" type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[custom_model_enabled]" value="1" <?php checked($custom_model_enabled); ?> />
                                    Advanced: use custom model ID
                                </label>
                                <div id="ai-seo-custom-model-wrap" class="ai-seo-custom-model-wrap" <?php echo $custom_model_enabled ? '' : 'hidden'; ?>>
                                    <input
                                        id="ai-seo-custom-model-id"
                                        class="regular-text"
                                        type="text"
                                        name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[custom_model_id]"
                                        value="<?php echo esc_attr($custom_model_id); ?>"
                                        maxlength="120"
                                        placeholder="e.g. gpt-5.5 or gemini-2.5-flash" />
                                    <p class="description" style="margin-top:8px;">
                                        Use only when needed. If invalid or unavailable for your subscription, requests will fail until corrected.
                                    </p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-api-key">API key</label></th>
                            <td><input id="ai-seo-api-key" class="regular-text" type="password" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[api_key]" value="<?php echo esc_attr($options['api_key']); ?>" autocomplete="off" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-temperature">Temperature</label></th>
                            <td>
                                <input
                                    id="ai-seo-temperature"
                                    class="small-text"
                                    type="number"
                                    min="0"
                                    max="2"
                                    step="0.1"
                                    name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[ai_temperature]"
                                    value="<?php echo esc_attr(number_format($active_temperature, 1, '.', '')); ?>" />
                                <p class="description" style="margin-top:8px;">
                                    Lower values are more deterministic, higher values are more creative. For OpenAI o-series models, unsupported values are automatically ignored and provider defaults are used.
                                </p>
                                <p id="ai-seo-temperature-hint" class="description ai-seo-temperature-hint" hidden>
                                    Current model is an OpenAI o-series model. Custom temperature is ignored by the provider for this model family.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-system-prompt">AI instructions</label></th>
                            <td>
                                <textarea id="ai-seo-system-prompt" class="large-text" rows="5" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[system_prompt]"><?php echo esc_textarea($options['system_prompt']); ?></textarea>
                                <p class="description">Global instructions applied to page generation, page chat, and site audit requests.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Model availability test</th>
                            <td>
                                <button
                                    type="button"
                                    class="button"
                                    id="ai-seo-test-model"
                                    data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                                    data-action="ai_seo_keeper_test_model"
                                    data-nonce="<?php echo esc_attr(wp_create_nonce('ai_seo_keeper_settings_test_model')); ?>">
                                    Test selected model
                                </button>
                                <p class="description" style="margin-top:8px;">
                                    Checks whether the selected provider/model is accessible with the current API key and can return a response.
                                </p>
                                <p id="ai-seo-test-model-result" style="margin-top:8px;"></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Section 2: Settings -->
            <div class="ai-seo-accordion-section">
                <div class="ai-seo-accordion-header">
                    <h2>Settings</h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Features</th>
                            <td>
                                <fieldset>
                                    <?php foreach (Settings::FEATURE_FLAGS as $feature_key => $feature_label) : ?>
                                        <label style="display:block;margin-bottom:8px;">
                                            <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[feature_<?php echo esc_attr($feature_key); ?>]" value="1" <?php checked(! empty($options['feature_' . $feature_key])); ?> />
                                            <?php echo esc_html($feature_label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-editor-chat">Editor chat</label></th>
                            <td>
                                <label>
                                    <input id="ai-seo-editor-chat" type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[editor_chat_enabled]" value="1" <?php checked(! empty($options['editor_chat_enabled'])); ?> />
                                    Enable the page-level AI assistant in the editor.
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-frontend-output">Frontend output</label></th>
                            <td>
                                <label style="display:block;margin-bottom:8px;">
                                    <input id="ai-seo-frontend-output" type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[frontend_output_enabled]" value="1" <?php checked(! empty($options['frontend_output_enabled'])); ?> />
                                    Output approved AI metadata and saved page-level SEO fields on the frontend.
                                </label>
                                <label style="display:block;">
                                    <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[frontend_override_conflicts]" value="1" <?php checked(! empty($options['frontend_override_conflicts'])); ?> />
                                    Allow output even when another SEO plugin is active.
                                </label>
                                <p class="description">Keep the second option off unless you explicitly want AI SEO Keeper to render alongside another SEO plugin.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-search-appearance-auto">Search appearance</label></th>
                            <td>
                                <label style="display:block;margin-bottom:8px;">
                                    <input id="ai-seo-search-appearance-auto" type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[search_appearance_auto_enabled]" value="1" <?php checked(! empty($options['search_appearance_auto_enabled'])); ?> />
                                    Enable baseline SEO output for public singular content.
                                </label>
                                <p class="description" style="margin:0 0 12px;">Standalone search-appearance mode: builds titles from templates, derives meta descriptions from excerpt/content.</p>
                                <p style="margin:0 0 8px;"><strong>Tokens:</strong> <code>%%title%%</code>, <code>%%sitename%%</code>, <code>%%sep%%</code></p>
                                <p style="margin:0 0 8px;">
                                    <label for="ai-seo-search-separator"><strong>Title separator</strong></label><br />
                                    <input id="ai-seo-search-separator" class="small-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[search_title_separator]" value="<?php echo esc_attr((string) $options['search_title_separator']); ?>" maxlength="3" />
                                </p>
                                <p style="margin:0 0 8px;">
                                    <label for="ai-seo-site-brand"><strong>Site brand</strong></label><br />
                                    <input id="ai-seo-site-brand" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[site_brand]" value="<?php echo esc_attr((string) ($options['site_brand'] ?? '')); ?>" placeholder="<?php echo esc_attr(wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)); ?>" />
                                <p class="description" style="margin:4px 0 0;">The brand name appended to every page title (e.g. "Cool Service <strong><?php echo esc_html((string) $options['search_title_separator']); ?> <?php echo esc_html('' !== trim((string) ($options['site_brand'] ?? '')) ? trim((string) $options['site_brand']) : wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)); ?></strong>"). Leave empty to use your WordPress site name. Pages store only the page-specific part; the separator + brand are appended automatically.</p>
                                </p>
                                <p style="margin:0 0 8px;">
                                    <label for="ai-seo-template-post"><strong>Post title template</strong></label><br />
                                    <input id="ai-seo-template-post" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[search_title_template_post]" value="<?php echo esc_attr((string) $options['search_title_template_post']); ?>" />
                                </p>
                                <p style="margin:0 0 8px;">
                                    <label for="ai-seo-template-page"><strong>Page title template</strong></label><br />
                                    <input id="ai-seo-template-page" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[search_title_template_page]" value="<?php echo esc_attr((string) $options['search_title_template_page']); ?>" />
                                </p>
                                <p style="margin:0;">
                                    <label for="ai-seo-template-default"><strong>Fallback title template</strong></label><br />
                                    <input id="ai-seo-template-default" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[search_title_template_default]" value="<?php echo esc_attr((string) $options['search_title_template_default']); ?>" />
                                </p>
                                <hr style="margin:16px 0;" />
                                <p style="margin:0 0 8px;"><strong>Non-singular title templates</strong></p>
                                <p class="description" style="margin:0 0 12px;">Additional tokens: <code>%%term_title%%</code>, <code>%%author%%</code>, <code>%%date%%</code>, <code>%%searchphrase%%</code>, <code>%%archive_title%%</code></p>
                                <p style="margin:0 0 8px;">
                                    <label for="ai-seo-template-category"><strong>Category archives</strong></label><br />
                                    <input id="ai-seo-template-category" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[search_title_template_category]" value="<?php echo esc_attr((string) $options['search_title_template_category']); ?>" />
                                </p>
                                <p style="margin:0 0 8px;">
                                    <label for="ai-seo-template-tag"><strong>Tag archives</strong></label><br />
                                    <input id="ai-seo-template-tag" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[search_title_template_tag]" value="<?php echo esc_attr((string) $options['search_title_template_tag']); ?>" />
                                </p>
                                <p style="margin:0 0 8px;">
                                    <label for="ai-seo-template-author"><strong>Author archives</strong></label><br />
                                    <input id="ai-seo-template-author" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[search_title_template_author]" value="<?php echo esc_attr((string) $options['search_title_template_author']); ?>" />
                                </p>
                                <p style="margin:0 0 8px;">
                                    <label for="ai-seo-template-date"><strong>Date archives</strong></label><br />
                                    <input id="ai-seo-template-date" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[search_title_template_date]" value="<?php echo esc_attr((string) $options['search_title_template_date']); ?>" />
                                </p>
                                <p style="margin:0 0 8px;">
                                    <label for="ai-seo-template-search"><strong>Search results</strong></label><br />
                                    <input id="ai-seo-template-search" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[search_title_template_search]" value="<?php echo esc_attr((string) $options['search_title_template_search']); ?>" />
                                </p>
                                <p style="margin:0 0 8px;">
                                    <label for="ai-seo-template-archive"><strong>Generic / post type archives</strong></label><br />
                                    <input id="ai-seo-template-archive" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[search_title_template_archive]" value="<?php echo esc_attr((string) $options['search_title_template_archive']); ?>" />
                                </p>
                                <p style="margin:0 0 8px;">
                                    <label for="ai-seo-template-404"><strong>404 page</strong></label><br />
                                    <input id="ai-seo-template-404" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[search_title_template_404]" value="<?php echo esc_attr((string) $options['search_title_template_404']); ?>" />
                                </p>
                                <hr style="margin:16px 0;" />
                                <p style="margin:0 0 8px;"><strong>Indexing controls for non-singular pages</strong></p>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[noindex_categories]" value="1" <?php checked(! empty($options['noindex_categories'])); ?> />
                                    Set categories to <code>noindex</code>
                                </label>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[noindex_tags]" value="1" <?php checked(! empty($options['noindex_tags'])); ?> />
                                    Set tags to <code>noindex</code>
                                </label>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[noindex_author_archives]" value="1" <?php checked(! empty($options['noindex_author_archives'])); ?> />
                                    Set author archives to <code>noindex</code>
                                </label>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[noindex_date_archives]" value="1" <?php checked(! empty($options['noindex_date_archives'])); ?> />
                                    Set date archives to <code>noindex</code>
                                </label>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[noindex_search_results]" value="1" <?php checked(! empty($options['noindex_search_results'])); ?> />
                                    Set search results to <code>noindex</code>
                                </label>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[noindex_format_archives]" value="1" <?php checked(! empty($options['noindex_format_archives'])); ?> />
                                    Set format/other archives to <code>noindex</code>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">XML Sitemap</th>
                            <td>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[sitemap_enabled]" value="1" <?php checked(! empty($options['sitemap_enabled'])); ?> />
                                    Enable XML Sitemap (replaces WordPress core sitemaps).
                                </label>
                                <p class="description" style="margin:0 0 12px;">Generates <code>/sitemap_index.xml</code> with separate sitemaps per content type.</p>
                                <fieldset style="margin-left:16px;">
                                    <label style="display:block;margin-bottom:6px;">
                                        <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[sitemap_include_posts]" value="1" <?php checked(! empty($options['sitemap_include_posts'])); ?> />
                                        Include posts
                                    </label>
                                    <label style="display:block;margin-bottom:6px;">
                                        <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[sitemap_include_pages]" value="1" <?php checked(! empty($options['sitemap_include_pages'])); ?> />
                                        Include pages
                                    </label>
                                    <label style="display:block;margin-bottom:6px;">
                                        <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[sitemap_include_categories]" value="1" <?php checked(! empty($options['sitemap_include_categories'])); ?> />
                                        Include categories
                                    </label>
                                    <label style="display:block;margin-bottom:6px;">
                                        <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[sitemap_include_tags]" value="1" <?php checked(! empty($options['sitemap_include_tags'])); ?> />
                                        Include tags
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-robots-txt">Robots.txt custom rules</label></th>
                            <td>
                                <textarea id="ai-seo-robots-txt" class="large-text code" rows="5" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[robots_txt_custom]" placeholder="Disallow: /private-folder/&#10;Allow: /public/"><?php echo esc_textarea($options['robots_txt_custom'] ?? ''); ?></textarea>
                                <p class="description">Custom rules appended to robots.txt. Preview: <a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank">robots.txt</a></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-indexnow-enabled">IndexNow</label></th>
                            <td>
                                <label style="display:block;margin-bottom:8px;">
                                    <input id="ai-seo-indexnow-enabled" type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[indexnow_enabled]" value="1" <?php checked($indexnow_enabled); ?> />
                                    Enable IndexNow refresh signaling.
                                </label>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[indexnow_auto_submit]" value="1" <?php checked($indexnow_auto_submit); ?> />
                                    Auto-submit URLs when content is saved.
                                </label>
                                <p style="margin:0 0 8px;"><strong>Key:</strong> <code><?php echo esc_html($indexnow_key); ?></code></p>
                                <?php if ('' !== $indexnow_key_url) : ?>
                                    <p style="margin:0 0 8px;"><strong>Key URL:</strong> <a href="<?php echo esc_url($indexnow_key_url); ?>" target="_blank"><?php echo esc_html($indexnow_key_url); ?></a></p>
                                <?php endif; ?>
                                <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[indexnow_key]" value="<?php echo esc_attr($indexnow_key); ?>" />
                                <p class="description">On localhost, submissions are skipped. On a public site, the key file is served automatically.</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Section 3: Tracking and Social -->
            <div class="ai-seo-accordion-section">
                <div class="ai-seo-accordion-header">
                    <h2>Tracking and Social</h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="ai-seo-google-code">Google tracking or verification</label></th>
                            <td><input id="ai-seo-google-code" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[google_tracking_code]" value="<?php echo esc_attr($options['google_tracking_code']); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-bing-code">Bing tracking or verification</label></th>
                            <td><input id="ai-seo-bing-code" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[bing_tracking_code]" value="<?php echo esc_attr($options['bing_tracking_code']); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Social profiles</th>
                            <td>
                                <p class="description" style="margin:0 0 8px;">Used in Organization schema <code>sameAs</code> and for social discovery. Enter full URLs.</p>
                                <?php
                                $social_fields = array(
                                    'social_facebook'  => 'Facebook',
                                    'social_twitter'   => 'Twitter / X',
                                    'social_instagram' => 'Instagram',
                                    'social_linkedin'  => 'LinkedIn',
                                    'social_youtube'   => 'YouTube',
                                    'social_pinterest' => 'Pinterest',
                                );
                                foreach ($social_fields as $field_key => $label) : ?>
                                    <div class="aisk-field-row" style="margin-bottom:6px;">
                                        <div class="aisk-field-group aisk-w-full">
                                            <label for="ai-seo-<?php echo esc_attr($field_key); ?>"><?php echo esc_html($label); ?></label>
                                            <input id="ai-seo-<?php echo esc_attr($field_key); ?>" class="regular-text" type="url" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[<?php echo esc_attr($field_key); ?>]" value="<?php echo esc_attr($options[$field_key] ?? ''); ?>" placeholder="https://" />
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Section 4: Local SEO / Business Schema -->
            <div class="ai-seo-accordion-section">
                <div class="ai-seo-accordion-header">
                    <h2>Local SEO / Business Schema</h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <p class="description">Configure your business details to output LocalBusiness structured data. Use the <code>[ai_seo_map]</code> shortcode to embed a Google Map.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Local SEO</th>
                            <td>
                                <label><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_seo_enabled]" value="1" <?php checked(! empty($options['local_seo_enabled'])); ?> /> Output LocalBusiness schema on the front page</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-local-type">Business type</label></th>
                            <td>
                                <?php
                                $business_types = array('LocalBusiness', 'Store', 'Restaurant', 'HealthAndBeautyBusiness', 'LegalService', 'FinancialService', 'EducationalOrganization', 'LodgingBusiness', 'SportsActivityLocation', 'EntertainmentBusiness', 'HomeAndConstructionBusiness', 'AutomotiveBusiness', 'MedicalBusiness', 'ProfessionalService', 'RealEstateAgent');
                                ?>
                                <select id="ai-seo-local-type" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_business_type]">
                                    <?php foreach ($business_types as $bt) : ?>
                                        <option value="<?php echo esc_attr($bt); ?>" <?php selected($options['local_business_type'] ?? 'LocalBusiness', $bt); ?>><?php echo esc_html($bt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-local-name">Business name</label></th>
                            <td><input id="ai-seo-local-name" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_business_name]" value="<?php echo esc_attr($options['local_business_name'] ?? ''); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Address</th>
                            <td>
                                <div class="aisk-field-row">
                                    <div class="aisk-field-group aisk-w-full">
                                        <label for="ai-seo-local-street">Street</label>
                                        <input id="ai-seo-local-street" class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_street]" value="<?php echo esc_attr($options['local_street'] ?? ''); ?>" />
                                    </div>
                                </div>
                                <div class="aisk-field-row">
                                    <div class="aisk-field-group aisk-w-200">
                                        <label for="ai-seo-local-city">City</label>
                                        <input id="ai-seo-local-city" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_city]" value="<?php echo esc_attr($options['local_city'] ?? ''); ?>" />
                                    </div>
                                    <div class="aisk-field-group aisk-w-140">
                                        <label for="ai-seo-local-state">State</label>
                                        <input id="ai-seo-local-state" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_state]" value="<?php echo esc_attr($options['local_state'] ?? ''); ?>" />
                                    </div>
                                    <div class="aisk-field-group aisk-w-100">
                                        <label for="ai-seo-local-zip">Zip</label>
                                        <input id="ai-seo-local-zip" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_zip]" value="<?php echo esc_attr($options['local_zip'] ?? ''); ?>" />
                                    </div>
                                    <div class="aisk-field-group aisk-w-140">
                                        <label for="ai-seo-local-country">Country</label>
                                        <input id="ai-seo-local-country" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_country]" value="<?php echo esc_attr($options['local_country'] ?? ''); ?>" />
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Contact</th>
                            <td>
                                <div class="aisk-field-row">
                                    <div class="aisk-field-group aisk-w-200">
                                        <label for="ai-seo-local-phone">Phone</label>
                                        <input id="ai-seo-local-phone" type="tel" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_phone]" value="<?php echo esc_attr($options['local_phone'] ?? ''); ?>" placeholder="+1-555-000-0000" />
                                    </div>
                                    <div class="aisk-field-group aisk-w-200">
                                        <label for="ai-seo-local-email">Email</label>
                                        <input id="ai-seo-local-email" type="email" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_email]" value="<?php echo esc_attr($options['local_email'] ?? ''); ?>" />
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Geo coordinates</th>
                            <td>
                                <div class="aisk-field-row">
                                    <div class="aisk-field-group aisk-w-160">
                                        <label for="ai-seo-local-lat">Latitude</label>
                                        <input id="ai-seo-local-lat" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_lat]" value="<?php echo esc_attr($options['local_lat'] ?? ''); ?>" placeholder="44.4268" />
                                    </div>
                                    <div class="aisk-field-group aisk-w-160">
                                        <label for="ai-seo-local-lng">Longitude</label>
                                        <input id="ai-seo-local-lng" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_lng]" value="<?php echo esc_attr($options['local_lng'] ?? ''); ?>" placeholder="26.1025" />
                                    </div>
                                </div>
                                <p class="description">Used in schema and the <code>[ai_seo_map]</code> shortcode.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Opening hours</th>
                            <td>
                                <p class="description" style="margin: 0 0 12px;">Format: <code>09:00-17:00</code> — leave blank for closed. Use <code>00:00-23:59</code> for 24h.</p>
                                <?php
                                $days = array('mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday');
                                foreach ($days as $dk => $dl) : ?>
                                    <div class="aisk-hours-row">
                                        <span><?php echo esc_html($dl); ?></span>
                                        <input type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_hours_<?php echo $dk; ?>]" value="<?php echo esc_attr($options['local_hours_' . $dk] ?? ''); ?>" placeholder="09:00-17:00" />
                                    </div>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-local-price">Price range</label></th>
                            <td>
                                <div class="aisk-field-group aisk-w-100">
                                    <input id="ai-seo-local-price" type="text" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[local_price_range]" value="<?php echo esc_attr($options['local_price_range'] ?? ''); ?>" placeholder="$$" />
                                </div>
                                <p class="description">Use $ signs (e.g. $, $$, $$$) to indicate price level.</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Section 5: RSS Feed Optimization -->
            <div class="ai-seo-accordion-section">
                <div class="ai-seo-accordion-header">
                    <h2>RSS Feed Optimization</h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <p class="description">Control how your content appears in RSS feeds. Add branding, prevent scraping, and include featured images.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="ai-seo-rss-before">Content before feed items</label></th>
                            <td>
                                <textarea id="ai-seo-rss-before" class="large-text" rows="3" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[rss_before_content]" placeholder="Originally published on %%sitename%%"><?php echo esc_textarea($options['rss_before_content'] ?? ''); ?></textarea>
                                <p class="description">HTML allowed. Use <code>%%sitename%%</code> and <code>%%post_link%%</code> as placeholders.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-rss-after">Content after feed items</label></th>
                            <td>
                                <textarea id="ai-seo-rss-after" class="large-text" rows="3" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[rss_after_content]" placeholder='The post %%post_link%% appeared first on %%sitename%%.'><?php echo esc_textarea($options['rss_after_content'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Featured image in feed</th>
                            <td><label><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[rss_featured_image]" value="1" <?php checked(! empty($options['rss_featured_image'])); ?> /> Prepend featured image to each feed item</label></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai-seo-rss-delay">Publication delay (minutes)</label></th>
                            <td>
                                <input id="ai-seo-rss-delay" type="number" min="0" max="1440" style="width:80px;" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[rss_publication_delay]" value="<?php echo (int) ($options['rss_publication_delay'] ?? 0); ?>" />
                                <p class="description">Delay feed updates to prevent scrapers. 0 = no delay.</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Section 6: Crawl Budget Optimization -->
            <div class="ai-seo-accordion-section">
                <div class="ai-seo-accordion-header">
                    <h2>Crawl Budget Optimization</h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <p class="description">Remove unnecessary pages from search engine crawl to focus crawl budget on your most important content.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Disable archive pages</th>
                            <td>
                                <fieldset>
                                    <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[crawl_disable_author_archives]" value="1" <?php checked(! empty($options['crawl_disable_author_archives'])); ?> /> Disable author archives (redirect to homepage)</label>
                                    <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[crawl_disable_date_archives]" value="1" <?php checked(! empty($options['crawl_disable_date_archives'])); ?> /> Disable date archives (redirect to homepage)</label>
                                    <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[crawl_disable_format_archives]" value="1" <?php checked(! empty($options['crawl_disable_format_archives'])); ?> /> Disable post format archives</label>
                                    <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[crawl_disable_attachment_pages]" value="1" <?php checked(! empty($options['crawl_disable_attachment_pages'])); ?> /> Disable attachment pages (redirect to parent or file)</label>
                                    <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[crawl_disable_search_indexing]" value="1" <?php checked(! empty($options['crawl_disable_search_indexing'])); ?> /> Block search results pages from indexing</label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Clean up &lt;head&gt;</th>
                            <td>
                                <fieldset>
                                    <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[crawl_remove_wp_version]" value="1" <?php checked(! empty($options['crawl_remove_wp_version'])); ?> /> Remove WordPress version meta tag</label>
                                    <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[crawl_remove_shortlink]" value="1" <?php checked(! empty($options['crawl_remove_shortlink'])); ?> /> Remove shortlink tag</label>
                                    <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[crawl_remove_rsd_link]" value="1" <?php checked(! empty($options['crawl_remove_rsd_link'])); ?> /> Remove RSD (Really Simple Discovery) link</label>
                                    <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[crawl_remove_feed_links]" value="1" <?php checked(! empty($options['crawl_remove_feed_links'])); ?> /> Remove RSS feed links from &lt;head&gt;</label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

        </div><!-- .ai-seo-accordion -->

        <div style="max-width:960px;margin-top:20px;">
            <?php submit_button('Save settings'); ?>
        </div>
    </form>

    <div class="ai-seo-box">
        <h2>Yoast migration</h2>
        <p style="margin:0 0 12px;">Copy existing Yoast per-page metadata into AI SEO Keeper without overwriting fields already filled here.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ai_seo_keeper_import_yoast_metadata'); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr($yoast_import_action); ?>" />
            <button type="submit" class="button button-secondary">Import Yoast metadata</button>
        </form>
        <p class="description" style="margin:12px 0 0;">Imports focus keyphrase, SEO title, meta description, social fields, canonical URL, and noindex/nofollow. Existing AI SEO Keeper values are preserved.</p>
    </div>
</div>