<?php

namespace AI_SEO_Keeper\Admin;

use AI_SEO_Keeper\Plugin;
use AI_SEO_Keeper\Settings;
use AI_SEO_Keeper\Admin as AdminBase;

/**
 * Export, Import, and Yoast migration handlers.
 */
class Admin_Import_Export
{
    private Settings $settings;
    private AdminBase $admin;

    public function __construct(Settings $settings, AdminBase $admin)
    {
        $this->settings = $settings;
        $this->admin    = $admin;
    }

    // ------------------------------------------------------------------
    //  Page renderer
    // ------------------------------------------------------------------

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $import_status = isset($_GET['import_status']) ? sanitize_key($_GET['import_status']) : '';
        $import_msg    = isset($_GET['import_msg']) ? sanitize_text_field(wp_unslash($_GET['import_msg'])) : '';

        require dirname(__DIR__) . '/admin/view-export-import.php';
    }

    // ------------------------------------------------------------------
    //  Export
    // ------------------------------------------------------------------

    public function handle_export(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('ai_seo_keeper_export');

        $data = array(
            'plugin'      => 'ai-seo-keeper',
            'version'     => defined('AI_SEO_KEEPER_VERSION') ? AI_SEO_KEEPER_VERSION : '1.0.0',
            'exported_at' => gmdate('c'),
            'site_url'    => home_url('/'),
        );

        // Settings.
        if (! empty($_POST['export_settings'])) {
            $data['settings'] = $this->settings->get();
            // Never export API keys.
            unset($data['settings']['api_key'], $data['settings']['google_api_key']);
        }

        // Per-page SEO metadata.
        if (! empty($_POST['export_seo_meta'])) {
            global $wpdb;
            $meta_keys = array(
                '_ai_seo_keeper_meta_title',
                '_ai_seo_keeper_meta_description',
                '_ai_seo_keeper_focus_keyphrase',
                '_ai_seo_keeper_social_title',
                '_ai_seo_keeper_social_description',
                '_ai_seo_keeper_social_image',
                '_ai_seo_keeper_schema_type',
                '_ai_seo_keeper_canonical_url',
                '_ai_seo_keeper_robots_directives',
                '_ai_seo_keeper_frontend_enabled',
                '_ai_seo_keeper_cornerstone',
                '_ai_seo_keeper_hreflang',
            );
            $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT p.ID, p.post_title, p.post_name, pm.meta_key, pm.meta_value
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                    WHERE pm.meta_key IN ({$placeholders})
                        AND pm.meta_value != ''
                    ORDER BY p.ID ASC",
                    ...$meta_keys
                ),
                ARRAY_A
            );

            $seo_meta = array();
            foreach ($rows as $row) {
                $pid = (int) $row['ID'];
                if (! isset($seo_meta[$pid])) {
                    $seo_meta[$pid] = array(
                        'post_id'    => $pid,
                        'post_title' => $row['post_title'],
                        'post_slug'  => $row['post_name'],
                        'meta'       => array(),
                    );
                }
                $seo_meta[$pid]['meta'][$row['meta_key']] = $row['meta_value'];
            }
            $data['seo_meta'] = array_values($seo_meta);
        }

        // Redirects.
        if (! empty($_POST['export_redirects'])) {
            global $wpdb;
            $redirects_table = $wpdb->prefix . 'ai_seo_keeper_redirects';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $redirects = $wpdb->get_results(
                "SELECT source_url, target_url, status_code, type FROM {$redirects_table} WHERE type = 'redirect' ORDER BY source_url ASC",
                ARRAY_A
            );
            $data['redirects'] = is_array($redirects) ? $redirects : array();
        }

        $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="ai-seo-keeper-export-' . gmdate('Y-m-d') . '.json"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    // ------------------------------------------------------------------
    //  Import
    // ------------------------------------------------------------------

    public function handle_import(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('ai_seo_keeper_import');

        $redirect_url = admin_url('admin.php?page=ai-seo-keeper-export-import');

        if (empty($_FILES['import_file']['tmp_name']) || 0 !== (int) $_FILES['import_file']['error']) {
            wp_redirect(add_query_arg(array('import_status' => 'error', 'import_msg' => 'No file uploaded or upload error.'), $redirect_url));
            exit;
        }

        $json = file_get_contents($_FILES['import_file']['tmp_name']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $data = json_decode($json, true);

        if (! is_array($data) || 'ai-seo-keeper' !== ($data['plugin'] ?? '')) {
            wp_redirect(add_query_arg(array('import_status' => 'error', 'import_msg' => 'Invalid import file. Must be an AI SEO Keeper export.'), $redirect_url));
            exit;
        }

        $imported = array();

        // Import settings.
        if (! empty($data['settings']) && is_array($data['settings'])) {
            $current = $this->settings->get();
            // Preserve existing API keys — never import them.
            $data['settings']['api_key']        = $current['api_key'] ?? '';
            $data['settings']['google_api_key'] = $current['google_api_key'] ?? '';
            update_option(Settings::OPTION_NAME, wp_parse_args($data['settings'], $current));
            $imported[] = 'settings';
        }

        // Import SEO metadata.
        if (! empty($data['seo_meta']) && is_array($data['seo_meta'])) {
            $meta_count = 0;
            foreach ($data['seo_meta'] as $entry) {
                $post_id = (int) ($entry['post_id'] ?? 0);
                if ($post_id <= 0 || ! get_post($post_id)) {
                    continue;
                }
                if (! empty($entry['meta']) && is_array($entry['meta'])) {
                    foreach ($entry['meta'] as $key => $value) {
                        // Only allow known meta keys.
                        if (0 !== strpos($key, '_ai_seo_keeper_')) {
                            continue;
                        }
                        update_post_meta($post_id, sanitize_key($key), sanitize_text_field($value));
                    }
                    $meta_count++;
                }
            }
            $imported[] = "{$meta_count} pages SEO data";
        }

        // Import redirects.
        if (! empty($data['redirects']) && is_array($data['redirects'])) {
            $redir_instance = Plugin::instance()->get_redirects();
            $redir_count    = 0;
            if ($redir_instance) {
                foreach ($data['redirects'] as $r) {
                    if (! empty($r['source_url']) && ! empty($r['target_url'])) {
                        $redir_instance->add_redirect(
                            sanitize_text_field($r['source_url']),
                            esc_url_raw($r['target_url']),
                            (int) ($r['status_code'] ?? 301)
                        );
                        $redir_count++;
                    }
                }
            }
            $imported[] = "{$redir_count} redirects";
        }

        $msg = empty($imported) ? 'Nothing to import.' : 'Imported: ' . implode(', ', $imported) . '.';
        wp_redirect(add_query_arg(array('import_status' => 'success', 'import_msg' => $msg), $redirect_url));
        exit;
    }

    // ------------------------------------------------------------------
    //  Yoast migration
    // ------------------------------------------------------------------

    public function handle_import_yoast(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You are not allowed to do that.');
        }

        check_admin_referer('ai_seo_keeper_import_yoast_metadata');

        $result = array();

        try {
            $result = $this->import_yoast_metadata();
        } catch (\Throwable $throwable) {
            $this->admin->redirect_to_settings_page('error', $throwable->getMessage());
        }

        if (0 === $result['posts_detected']) {
            $this->admin->redirect_to_settings_page('success', 'No Yoast metadata was found to import.');
        }

        $message = sprintf('Yoast import finished. %d item(s) were updated and %d field(s) were copied.', $result['posts_updated'], $result['fields_imported']);

        if ($result['frontend_enabled'] > 0) {
            $message .= ' ' . sprintf('Frontend output was enabled on %d item(s).', $result['frontend_enabled']);
        }

        if ($result['skipped_existing'] > 0) {
            $message .= ' ' . sprintf('%d existing AI SEO Keeper field(s) were left unchanged.', $result['skipped_existing']);
        }

        if ($result['unsupported_advanced_robots'] > 0) {
            $message .= ' ' . sprintf('%d item(s) had advanced Yoast robots directives that were not mapped.', $result['unsupported_advanced_robots']);
        }

        $this->admin->redirect_to_settings_page('success', $message);
    }

    private function import_yoast_metadata(): array
    {
        $posts_detected              = 0;
        $posts_updated               = 0;
        $fields_imported             = 0;
        $skipped_existing            = 0;
        $frontend_enabled            = 0;
        $unsupported_advanced_robots = 0;

        $frontend_field_keys = array(
            'seo_title',
            'seo_description',
            'social_title',
            'social_description',
            'social_image',
            'canonical_url',
            'robots_directives',
        );

        $field_map = array(
            'focus_keyphrase'    => AdminBase::FOCUS_KEYPHRASE_META_KEY,
            'seo_title'          => AdminBase::META_TITLE_KEY,
            'seo_description'    => AdminBase::META_DESCRIPTION_KEY,
            'social_title'       => AdminBase::SOCIAL_TITLE_META_KEY,
            'social_description' => AdminBase::SOCIAL_DESCRIPTION_META_KEY,
            'social_image'       => AdminBase::SOCIAL_IMAGE_META_KEY,
            'canonical_url'      => AdminBase::CANONICAL_URL_META_KEY,
            'robots_directives'  => AdminBase::ROBOTS_DIRECTIVES_META_KEY,
        );

        foreach ($this->get_yoast_import_candidate_ids() as $post_id) {
            $post = get_post($post_id);

            if (! $post instanceof \WP_Post || ! $this->admin->is_supported_post_type($post->post_type)) {
                continue;
            }

            $posts_detected++;

            $yoast_title             = sanitize_text_field((string) get_post_meta($post_id, '_yoast_wpseo_title', true));
            $yoast_description       = sanitize_textarea_field((string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true));
            $yoast_social_title      = sanitize_text_field((string) get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true));
            $yoast_social_description = sanitize_textarea_field((string) get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true));
            $yoast_social_image      = esc_url_raw((string) get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true));
            $yoast_twitter_title     = sanitize_text_field((string) get_post_meta($post_id, '_yoast_wpseo_twitter-title', true));
            $yoast_twitter_description = sanitize_textarea_field((string) get_post_meta($post_id, '_yoast_wpseo_twitter-description', true));
            $yoast_twitter_image     = esc_url_raw((string) get_post_meta($post_id, '_yoast_wpseo_twitter-image', true));

            $limited_fields = SEO_Analysis::apply_editor_text_limits(
                array(
                    'seo_title'          => $yoast_title,
                    'meta_description'   => $yoast_description,
                    'social_title'       => '' !== $yoast_social_title ? $yoast_social_title : $yoast_twitter_title,
                    'social_description' => '' !== $yoast_social_description ? $yoast_social_description : $yoast_twitter_description,
                )
            );

            $import_payload = array(
                'focus_keyphrase'    => sanitize_text_field((string) get_post_meta($post_id, '_yoast_wpseo_focuskw', true)),
                'seo_title'          => $limited_fields['seo_title'],
                'seo_description'    => $limited_fields['meta_description'],
                'social_title'       => $limited_fields['social_title'],
                'social_description' => $limited_fields['social_description'],
                'social_image'       => '' !== $yoast_social_image ? $yoast_social_image : $yoast_twitter_image,
                'canonical_url'      => esc_url_raw((string) get_post_meta($post_id, '_yoast_wpseo_canonical', true)),
                'robots_directives'  => self::map_yoast_robots_directives(
                    (string) get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true),
                    (string) get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true)
                ),
            );

            $post_updated          = false;
            $imported_frontend_field = false;

            if ('' !== trim((string) get_post_meta($post_id, '_yoast_wpseo_meta-robots-adv', true))) {
                $unsupported_advanced_robots++;
            }

            foreach ($field_map as $field_key => $meta_key) {
                $value = isset($import_payload[$field_key]) ? (string) $import_payload[$field_key] : '';

                if ('' === trim($value)) {
                    continue;
                }

                $existing_value = trim((string) get_post_meta($post_id, $meta_key, true));

                if ('' !== $existing_value) {
                    $skipped_existing++;
                    continue;
                }

                update_post_meta($post_id, $meta_key, $value);
                $fields_imported++;
                $post_updated = true;

                if (in_array($field_key, $frontend_field_keys, true)) {
                    $imported_frontend_field = true;
                }
            }

            if ($imported_frontend_field && '1' !== (string) get_post_meta($post_id, AdminBase::FRONTEND_ENABLE_META_KEY, true)) {
                update_post_meta($post_id, AdminBase::FRONTEND_ENABLE_META_KEY, '1');
                $frontend_enabled++;
            }

            if ($post_updated) {
                $posts_updated++;
            }
        }

        return array(
            'posts_detected'              => $posts_detected,
            'posts_updated'               => $posts_updated,
            'fields_imported'             => $fields_imported,
            'skipped_existing'            => $skipped_existing,
            'frontend_enabled'            => $frontend_enabled,
            'unsupported_advanced_robots' => $unsupported_advanced_robots,
        );
    }

    private function get_yoast_import_candidate_ids(): array
    {
        global $wpdb;

        $supported_post_types = get_post_types(array('public' => true), 'names');
        unset($supported_post_types['attachment']);

        if (empty($supported_post_types)) {
            return array();
        }

        $yoast_meta_keys = array(
            '_yoast_wpseo_focuskw',
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc',
            '_yoast_wpseo_opengraph-title',
            '_yoast_wpseo_opengraph-description',
            '_yoast_wpseo_opengraph-image',
            '_yoast_wpseo_twitter-title',
            '_yoast_wpseo_twitter-description',
            '_yoast_wpseo_twitter-image',
            '_yoast_wpseo_canonical',
            '_yoast_wpseo_meta-robots-noindex',
            '_yoast_wpseo_meta-robots-nofollow',
            '_yoast_wpseo_meta-robots-adv',
        );

        $meta_placeholders      = implode(', ', array_fill(0, count($yoast_meta_keys), '%s'));
        $post_type_placeholders = implode(', ', array_fill(0, count($supported_post_types), '%s'));
        $query_args             = array_merge($yoast_meta_keys, array_values($supported_post_types), array('auto-draft', 'trash', 'inherit'));

        $sql = $wpdb->prepare(
            "SELECT DISTINCT pm.post_id
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} posts ON posts.ID = pm.post_id
            WHERE pm.meta_key IN ({$meta_placeholders})
                AND posts.post_type IN ({$post_type_placeholders})
                AND posts.post_status NOT IN (%s, %s, %s)
            ORDER BY pm.post_id ASC",
            $query_args
        );

        $post_ids = $wpdb->get_col($sql);

        return array_map('intval', is_array($post_ids) ? $post_ids : array());
    }

    public static function map_yoast_robots_directives(string $noindex_value, string $nofollow_value): string
    {
        $is_noindex  = '1' === trim($noindex_value);
        $is_nofollow = '1' === trim($nofollow_value);

        if ($is_noindex && $is_nofollow) {
            return 'noindex,nofollow';
        }

        if ($is_noindex) {
            return 'noindex,follow';
        }

        if ($is_nofollow) {
            return 'index,nofollow';
        }

        return '';
    }
}
