<?php

namespace AI_SEO_Captain\Admin;

use AI_SEO_Captain\Plugin;
use AI_SEO_Captain\Settings;
use AI_SEO_Captain\Admin as AdminBase;
use AI_SEO_Captain\ImportExport\Exporter;
use AI_SEO_Captain\ImportExport\Matcher;
use AI_SEO_Captain\ImportExport\Importer;
use AI_SEO_Captain\ImportExport\Url_Rewriter;

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
    //  Export (v2 — uses Exporter class)
    // ------------------------------------------------------------------

    public function handle_export(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('ai_seo_captain_export');

        $sections = array();
        if (! empty($_POST['export_settings'])) {
            $sections[] = 'settings';
        }
        if (! empty($_POST['export_seo_meta'])) {
            $sections[] = 'seo_meta_posts';
        }
        if (! empty($_POST['export_seo_terms'])) {
            $sections[] = 'seo_meta_terms';
        }
        if (! empty($_POST['export_audits'])) {
            $sections[] = 'audits';
        }
        if (! empty($_POST['export_redirects'])) {
            $sections[] = 'redirects';
        }
        if (! empty($_POST['export_four_oh_four'])) {
            $sections[] = 'four_oh_four';
        }
        if (! empty($_POST['export_runs'])) {
            $sections[] = 'runs';
        }
        if (! empty($_POST['export_chat_history'])) {
            $sections[] = 'chat_history';
        }

        if (empty($sections)) {
            $sections = array('settings', 'seo_meta_posts', 'seo_meta_terms', 'audits', 'redirects');
        }

        $exporter = new Exporter($this->settings);
        $data     = $exporter->build($sections);
        $domain   = $data['source_domain'] ?? 'site';
        $json     = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="ai-seo-captain-export-' . sanitize_file_name($domain) . '-' . gmdate('Y-m-d') . '.json"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    // ------------------------------------------------------------------
    //  Import — AJAX handlers (v2 multi-step flow)
    // ------------------------------------------------------------------

    /**
     * AJAX: Validate uploaded import file and store in transient.
     */
    public function ajax_import_validate(): void
    {
        check_ajax_referer('ai_seo_captain_import_v2', '_nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        if (empty($_FILES['import_file']['tmp_name']) || 0 !== (int) $_FILES['import_file']['error']) {
            wp_send_json_error(array('message' => 'No file uploaded or upload error.'));
        }

        // File extension check.
        $filename = sanitize_file_name($_FILES['import_file']['name'] ?? '');
        if ('.json' !== strtolower(substr($filename, -5))) {
            wp_send_json_error(array('message' => 'Invalid file type. Only .json files are accepted.'));
        }

        // File size check (10 MB limit).
        $max_size = 10 * 1024 * 1024;
        if ($_FILES['import_file']['size'] > $max_size) {
            wp_send_json_error(array('message' => 'File is too large. Maximum allowed size is 10 MB.'));
        }

        $json = file_get_contents($_FILES['import_file']['tmp_name']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $data = json_decode($json, true);

        if (! is_array($data) || 'ai-seo-captain' !== ($data['plugin'] ?? '')) {
            wp_send_json_error(array('message' => 'Invalid file. Must be an SEO Captain export.'));
        }

        // Store in transient (1 hour TTL).
        $transient_key = 'aisc_import_' . get_current_user_id();
        set_transient($transient_key, $data, HOUR_IN_SECONDS);

        wp_send_json_success(array(
            'source_domain'     => $data['source_domain'] ?? '',
            'plugin_version'    => $data['plugin_version'] ?? $data['version'] ?? '',
            'format_version'    => $data['format_version'] ?? '1.0',
            'exported_at'       => $data['exported_at'] ?? '',
            'sections_included' => $data['sections_included'] ?? array_keys($data['data'] ?? array()),
            'counts'            => $data['counts'] ?? array(),
            'current_domain'    => wp_parse_url(home_url(), PHP_URL_HOST),
        ));
    }

    /**
     * AJAX: Run matching algorithm and return results.
     */
    public function ajax_import_match(): void
    {
        check_ajax_referer('ai_seo_captain_import_v2', '_nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $data = get_transient('aisc_import_' . get_current_user_id());
        if (! is_array($data)) {
            wp_send_json_error(array('message' => 'Import session expired. Please upload the file again.'));
        }

        $matcher      = new Matcher();
        $post_matches = $matcher->match_posts($data['data']['seo_meta_posts'] ?? array());
        $term_matches = $matcher->match_terms($data['data']['seo_meta_terms'] ?? array());

        // Also match audit entries to posts.
        $audit_posts  = array();
        $audit_data   = $data['data']['audits']['audit_meta'] ?? array();
        if (! empty($audit_data)) {
            // Convert audit match_keys to the same format as seo_meta_posts for matching.
            $audit_as_posts = array();
            foreach ($audit_data as $entry) {
                $audit_as_posts[] = array('match_key' => $entry['match_key'] ?? array());
            }
            $audit_posts = $matcher->match_posts($audit_as_posts);
        }

        // Store match results in transient.
        $match_data = array(
            'post_matches'  => $post_matches,
            'term_matches'  => $term_matches,
            'audit_matches' => $audit_posts,
        );
        set_transient('aisc_import_matches_' . get_current_user_id(), $match_data, HOUR_IN_SECONDS);

        wp_send_json_success(array(
            'posts' => array(
                'strong'   => $post_matches['strong'],
                'fuzzy'    => $post_matches['fuzzy'],
                'orphaned' => $post_matches['orphaned'],
                'total'    => count($post_matches['strong']) + count($post_matches['fuzzy']) + count($post_matches['orphaned']),
            ),
            'terms' => array(
                'strong'   => $term_matches['strong'],
                'fuzzy'    => $term_matches['fuzzy'],
                'orphaned' => $term_matches['orphaned'],
                'total'    => count($term_matches['strong']) + count($term_matches['fuzzy']) + count($term_matches['orphaned']),
            ),
        ));
    }

    /**
     * AJAX: Process import chunk.
     */
    public function ajax_import_process(): void
    {
        check_ajax_referer('ai_seo_captain_import_v2', '_nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $uid  = get_current_user_id();
        $data = get_transient('aisc_import_' . $uid);
        if (! is_array($data)) {
            wp_send_json_error(array('message' => 'Import session expired.'));
        }

        $match_data = get_transient('aisc_import_matches_' . $uid);
        if (! is_array($match_data)) {
            wp_send_json_error(array('message' => 'Match data expired. Please re-run matching.'));
        }

        $mode             = sanitize_key($_POST['mode'] ?? 'update');
        $valid_modes      = array('update', 'overwrite', 'force');
        if (! in_array($mode, $valid_modes, true)) {
            wp_send_json_error(array('message' => 'Invalid import mode.'));
        }
        $sections         = isset($_POST['sections']) ? array_map('sanitize_key', (array) $_POST['sections']) : array();
        $step             = sanitize_key($_POST['step'] ?? '');
        $approved_fuzzy   = isset($_POST['approved_fuzzy']) ? array_map('intval', (array) $_POST['approved_fuzzy']) : array();
        $rewrite_urls     = ! empty($_POST['rewrite_urls']);

        // Build URL rewriter.
        $source_domain = $data['source_domain'] ?? '';
        $target_domain = wp_parse_url(home_url(), PHP_URL_HOST);
        $url_rewriter  = new Url_Rewriter($source_domain, $target_domain, $rewrite_urls);

        $importer = new Importer($this->settings, $url_rewriter, $mode);

        // Build the active match list (strong + approved fuzzy).
        $active_post_matches = $match_data['post_matches']['strong'] ?? array();
        foreach ($match_data['post_matches']['fuzzy'] ?? array() as $fuzzy) {
            if (in_array((int) $fuzzy['export_index'], $approved_fuzzy, true)) {
                $active_post_matches[] = $fuzzy;
            }
        }

        $active_term_matches = $match_data['term_matches']['strong'] ?? array();
        $approved_fuzzy_terms = isset($_POST['approved_fuzzy_terms']) ? array_map('intval', (array) $_POST['approved_fuzzy_terms']) : array();
        foreach ($match_data['term_matches']['fuzzy'] ?? array() as $fuzzy) {
            if (in_array((int) $fuzzy['export_index'], $approved_fuzzy_terms, true)) {
                $active_term_matches[] = $fuzzy;
            }
        }

        $result = array('step' => $step, 'done' => false, 'log' => array());

        switch ($step) {
            case 'settings':
                if (in_array('settings', $sections, true) && ! empty($data['data']['settings'])) {
                    $r = $importer->import_settings($data['data']['settings']);
                    $result['log'][] = 'Settings: ' . $r['imported'] . ' imported, ' . $r['skipped'] . ' skipped';
                }
                $result['next_step'] = 'seo_meta_posts';
                break;

            case 'seo_meta_posts':
                if (in_array('seo_meta_posts', $sections, true) && ! empty($data['data']['seo_meta_posts'])) {
                    $offset    = (int) ($_POST['offset'] ?? 0);
                    $chunk_size = 20;
                    $chunk     = array_slice($active_post_matches, $offset, $chunk_size);

                    if (! empty($chunk)) {
                        $r = $importer->import_post_meta_chunk($data['data']['seo_meta_posts'], $chunk);
                        $result['log']       = array_merge($result['log'], $r['log']);
                        $result['progress']  = min($offset + $chunk_size, count($active_post_matches));
                        $result['total']     = count($active_post_matches);

                        if ($offset + $chunk_size < count($active_post_matches)) {
                            $result['next_step']   = 'seo_meta_posts';
                            $result['next_offset'] = $offset + $chunk_size;
                            break;
                        }
                    }
                }
                $result['next_step'] = 'seo_meta_terms';
                break;

            case 'seo_meta_terms':
                if (in_array('seo_meta_terms', $sections, true) && ! empty($data['data']['seo_meta_terms'])) {
                    $r = $importer->import_term_meta_chunk($data['data']['seo_meta_terms'], $active_term_matches);
                    $result['log'] = array_merge($result['log'], $r['log']);
                }
                $result['next_step'] = 'audits';
                break;

            case 'audits':
                if (in_array('audits', $sections, true) && ! empty($data['data']['audits'])) {
                    $r = $importer->import_audits($data['data']['audits'], $active_post_matches);
                    $result['log'][] = 'Audits: ' . $r['imported'] . ' imported, ' . $r['skipped'] . ' skipped';
                }
                $result['next_step'] = 'redirects';
                break;

            case 'redirects':
                if (in_array('redirects', $sections, true) && ! empty($data['data']['redirects'])) {
                    $r = $importer->import_redirects($data['data']['redirects']);
                    $result['log'][] = 'Redirects: ' . $r['imported'] . ' imported, ' . $r['skipped'] . ' skipped';
                }
                $result['next_step'] = 'four_oh_four';
                break;

            case 'four_oh_four':
                if (in_array('four_oh_four', $sections, true) && ! empty($data['data']['four_oh_four'])) {
                    $r = $importer->import_404s($data['data']['four_oh_four']);
                    $result['log'][] = '404 Log: ' . $r['imported'] . ' imported, ' . $r['skipped'] . ' skipped';
                }
                $result['next_step'] = 'runs';
                break;

            case 'runs':
                if (in_array('runs', $sections, true) && ! empty($data['data']['runs'])) {
                    $r = $importer->import_runs($data['data']['runs']);
                    $result['log'][] = 'Runs: ' . $r['imported'] . ' imported';
                }
                $result['next_step'] = 'chat_history';
                break;

            case 'chat_history':
                if (in_array('chat_history', $sections, true) && ! empty($data['data']['chat_history'])) {
                    $r = $importer->import_chat_history($data['data']['chat_history'], $active_post_matches);
                    $result['log'][] = 'Chat: ' . $r['conversations'] . ' conversations, ' . $r['messages'] . ' messages';
                }
                $result['done'] = true;
                // Clean up transients.
                delete_transient('aisc_import_' . $uid);
                delete_transient('aisc_import_matches_' . $uid);
                break;

            default:
                $result['next_step'] = 'settings';
                break;
        }

        wp_send_json_success($result);
    }

    // ------------------------------------------------------------------
    //  Yoast migration
    // ------------------------------------------------------------------

    public function handle_import_yoast(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You are not allowed to do that.');
        }

        check_admin_referer('ai_seo_captain_import_yoast_metadata');

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
            $message .= ' ' . sprintf('%d existing SEO Captain field(s) were left unchanged.', $result['skipped_existing']);
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
            'keywords'           => AdminBase::KEYWORDS_META_KEY,
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
