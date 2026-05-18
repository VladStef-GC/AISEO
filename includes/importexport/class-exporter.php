<?php

namespace AI_SEO_Captain\ImportExport;

use AI_SEO_Captain\Settings;

/**
 * Builds the export JSON data from all plugin sources.
 */
class Exporter
{
    /** Post meta keys for SEO Metadata section. */
    private const SEO_META_KEYS = array(
        '_ai_seo_captain_meta_title',
        '_ai_seo_captain_meta_description',
        '_ai_seo_captain_focus_keyphrase',
        '_ai_seo_captain_keywords',
        '_ai_seo_captain_social_title',
        '_ai_seo_captain_social_description',
        '_ai_seo_captain_social_image',
        '_ai_seo_captain_schema_type',
        '_ai_seo_captain_canonical_url',
        '_ai_seo_captain_robots_directives',
        '_ai_seo_captain_frontend_enabled',
        '_ai_seo_captain_cornerstone',
        '_ai_seo_captain_hreflang',
        '_ai_seo_captain_title_branding_off',
    );

    /** Post meta keys for Audit section. */
    private const AUDIT_META_KEYS = array(
        '_ai_seo_captain_page_audit',
        '_ai_seo_captain_audit_skip',
    );

    /** Term meta keys. */
    private const TERM_META_KEYS = array(
        '_ai_seo_captain_seo_title',
        '_ai_seo_captain_meta_description',
        '_ai_seo_captain_canonical',
        '_ai_seo_captain_noindex',
    );

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Build the full export data array.
     *
     * @param array $sections Which sections to include (keys: settings, seo_meta_posts, seo_meta_terms, audits, redirects, four_oh_four, runs, chat_history).
     * @return array The export data structure.
     */
    public function build(array $sections): array
    {
        $data = array(
            'plugin'            => 'ai-seo-captain',
            'format_version'    => '2.0',
            'plugin_version'    => defined('AI_SEO_CAPTAIN_VERSION') ? AI_SEO_CAPTAIN_VERSION : '1.0.0',
            'exported_at'       => gmdate('c'),
            'source_domain'     => wp_parse_url(home_url(), PHP_URL_HOST),
            'sections_included' => array_values($sections),
            'counts'            => array(),
            'data'              => array(),
        );

        $counts = array();

        if (in_array('settings', $sections, true)) {
            $data['data']['settings'] = $this->export_settings();
        }

        if (in_array('seo_meta_posts', $sections, true)) {
            $posts                     = $this->export_seo_meta_posts();
            $data['data']['seo_meta_posts'] = $posts;
            $counts['posts']           = count($posts);
        }

        if (in_array('seo_meta_terms', $sections, true)) {
            $terms                     = $this->export_seo_meta_terms();
            $data['data']['seo_meta_terms'] = $terms;
            $counts['terms']           = count($terms);
        }

        if (in_array('audits', $sections, true)) {
            $audits                = $this->export_audits();
            $data['data']['audits'] = $audits;
            $counts['content_index_entries'] = count($audits['content_index'] ?? array());
        }

        if (in_array('redirects', $sections, true)) {
            $redirects                 = $this->export_redirects();
            $data['data']['redirects'] = $redirects;
            $counts['redirects']       = count($redirects);
        }

        if (in_array('four_oh_four', $sections, true)) {
            $four_oh_four                  = $this->export_404s();
            $data['data']['four_oh_four']  = $four_oh_four;
            $counts['four_oh_four']        = count($four_oh_four);
        }

        if (in_array('runs', $sections, true)) {
            $runs                 = $this->export_runs();
            $data['data']['runs'] = $runs;
            $counts['runs']       = count($runs);
        }

        if (in_array('chat_history', $sections, true)) {
            $chat                         = $this->export_chat_history();
            $data['data']['chat_history'] = $chat;
            $counts['conversations']      = count($chat['conversations'] ?? array());
            $counts['messages']           = count($chat['messages'] ?? array());
        }

        $data['counts'] = $counts;

        return $data;
    }

    // ------------------------------------------------------------------
    //  Section exporters
    // ------------------------------------------------------------------

    private function export_settings(): array
    {
        $options = $this->settings->get();

        // Never export API keys.
        unset($options['api_key'], $options['google_api_key']);

        $wizard_flags = array(
            'step2_all_done' => (bool) get_option('ai_seo_captain_step2_all_done', false),
            'step3_all_done' => (bool) get_option('ai_seo_captain_step3_all_done', false),
        );

        return array(
            'options'      => $options,
            'wizard_flags' => $wizard_flags,
        );
    }

    private function export_seo_meta_posts(): array
    {
        global $wpdb;

        $meta_keys    = self::SEO_META_KEYS;
        $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title, p.post_name, p.post_type, pm.meta_key, pm.meta_value
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key IN ({$placeholders})
                   AND pm.meta_value != ''
                   AND p.post_status IN ('publish','draft','pending','private')
                 ORDER BY p.ID ASC",
                ...$meta_keys
            ),
            ARRAY_A
        );

        $grouped = array();
        foreach ($rows as $row) {
            $pid = (int) $row['ID'];
            if (! isset($grouped[$pid])) {
                $grouped[$pid] = array(
                    'match_key' => array(
                        'post_type'   => $row['post_type'],
                        'slug'        => $row['post_name'],
                        'title'       => $row['post_title'],
                        'original_id' => $pid,
                    ),
                    'meta' => array(),
                );
            }
            $grouped[$pid]['meta'][$row['meta_key']] = $row['meta_value'];
        }

        return array_values($grouped);
    }

    private function export_seo_meta_terms(): array
    {
        global $wpdb;

        $meta_keys    = self::TERM_META_KEYS;
        $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tm.meta_key, tm.meta_value
                 FROM {$wpdb->termmeta} tm
                 INNER JOIN {$wpdb->terms} t ON t.term_id = tm.term_id
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
                 WHERE tm.meta_key IN ({$placeholders})
                   AND tm.meta_value != ''
                 ORDER BY t.term_id ASC",
                ...$meta_keys
            ),
            ARRAY_A
        );

        $grouped = array();
        foreach ($rows as $row) {
            $tid = (int) $row['term_id'];
            if (! isset($grouped[$tid])) {
                $grouped[$tid] = array(
                    'match_key' => array(
                        'taxonomy'         => $row['taxonomy'],
                        'slug'             => $row['slug'],
                        'name'             => $row['name'],
                        'original_term_id' => $tid,
                    ),
                    'meta' => array(),
                );
            }
            $grouped[$tid]['meta'][$row['meta_key']] = $row['meta_value'];
        }

        return array_values($grouped);
    }

    private function export_audits(): array
    {
        global $wpdb;

        // Audit meta per post.
        $audit_keys   = self::AUDIT_META_KEYS;
        $placeholders = implode(',', array_fill(0, count($audit_keys), '%s'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_name, p.post_type, pm.meta_key, pm.meta_value
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key IN ({$placeholders})
                   AND pm.meta_value != ''
                   AND p.post_status IN ('publish','draft','pending','private')
                 ORDER BY p.ID ASC",
                ...$audit_keys
            ),
            ARRAY_A
        );

        $audit_meta = array();
        foreach ($rows as $row) {
            $pid = (int) $row['ID'];
            if (! isset($audit_meta[$pid])) {
                $audit_meta[$pid] = array(
                    'match_key' => array(
                        'post_type' => $row['post_type'],
                        'slug'      => $row['post_name'],
                    ),
                    'meta' => array(),
                );
            }
            $audit_meta[$pid]['meta'][$row['meta_key']] = $row['meta_value'];
        }

        // Content index table.
        $table = $wpdb->prefix . 'ai_seo_captain_content_index';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $index_rows = $wpdb->get_results(
            "SELECT ci.*, p.post_name, p.post_type
             FROM {$table} ci
             INNER JOIN {$wpdb->posts} p ON p.ID = ci.object_id AND ci.object_type = 'post'
             ORDER BY ci.id ASC",
            ARRAY_A
        );

        $content_index = array();
        foreach ($index_rows as $row) {
            $content_index[] = array(
                'match_key' => array(
                    'post_type' => $row['post_type'],
                    'slug'      => $row['post_name'],
                ),
                'object_type'  => $row['object_type'],
                'status'       => $row['status'],
                'title'        => $row['title'],
                'slug'         => $row['slug'],
                'permalink'    => $row['permalink'],
                'parent_id'    => (int) $row['parent_id'],
                'excerpt'      => $row['excerpt'],
                'content_hash' => $row['content_hash'],
                'modified_gmt' => $row['modified_gmt'],
                'indexed_at'   => $row['indexed_at'],
            );
        }

        return array(
            'audit_meta'    => array_values($audit_meta),
            'content_index' => $content_index,
        );
    }

    private function export_redirects(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_seo_captain_redirects';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT source_url, target_url, status_code, hit_count, last_hit, created_at
             FROM {$table}
             WHERE type = 'redirect'
             ORDER BY source_url ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    private function export_404s(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_seo_captain_redirects';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT source_url, hit_count, last_hit, created_at
             FROM {$table}
             WHERE type = '404'
             ORDER BY hit_count DESC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    private function export_runs(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_seo_captain_runs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT name, description, page_ids, page_count, completed_steps, status, created_at, updated_at
             FROM {$table}
             ORDER BY id ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    private function export_chat_history(): array
    {
        global $wpdb;
        $conv_table = $wpdb->prefix . 'ai_seo_captain_conversations';
        $msg_table  = $wpdb->prefix . 'ai_seo_captain_messages';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $conversations = $wpdb->get_results(
            "SELECT id, object_id, object_type, title, created_at, updated_at
             FROM {$conv_table}
             ORDER BY id ASC",
            ARRAY_A
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $messages = $wpdb->get_results(
            "SELECT conversation_id, role, content, created_at
             FROM {$msg_table}
             ORDER BY id ASC",
            ARRAY_A
        );

        return array(
            'conversations' => is_array($conversations) ? $conversations : array(),
            'messages'      => is_array($messages) ? $messages : array(),
        );
    }
}
