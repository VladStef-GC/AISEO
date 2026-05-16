<?php

namespace AI_SEO_Captain\ImportExport;

use AI_SEO_Captain\Settings;

/**
 * Processes import in chunks via AJAX.
 *
 * Import modes:
 *  - force:     Delete all plugin meta for matched posts, then write export values.
 *  - update:    Only write export values where the local field is empty.
 *  - overwrite: Write all export values (existing values are replaced), but don't delete extra fields.
 *
 * ABSOLUTE RULE: Never create or delete any post/page/term. Only update matched items.
 */
class Importer
{
    public const MODE_FORCE     = 'force';
    public const MODE_UPDATE    = 'update';
    public const MODE_OVERWRITE = 'overwrite';

    /** Post meta keys managed by the plugin (for force-mode cleanup). */
    private const ALL_POST_META_KEYS = array(
        '_ai_seo_captain_meta_title',
        '_ai_seo_captain_meta_description',
        '_ai_seo_captain_focus_keyphrase',
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
        '_ai_seo_captain_page_audit',
        '_ai_seo_captain_audit_skip',
    );

    /** Term meta keys managed by the plugin. */
    private const ALL_TERM_META_KEYS = array(
        '_ai_seo_captain_seo_title',
        '_ai_seo_captain_meta_description',
        '_ai_seo_captain_canonical',
        '_ai_seo_captain_noindex',
    );

    private Settings $settings;
    private Url_Rewriter $url_rewriter;
    private string $mode;

    public function __construct(Settings $settings, Url_Rewriter $url_rewriter, string $mode)
    {
        $this->settings     = $settings;
        $this->url_rewriter = $url_rewriter;
        $this->mode         = in_array($mode, array(self::MODE_FORCE, self::MODE_UPDATE, self::MODE_OVERWRITE), true) ? $mode : self::MODE_UPDATE;
    }

    // ------------------------------------------------------------------
    //  Settings import
    // ------------------------------------------------------------------

    /**
     * Import plugin settings.
     *
     * @return array{imported: int, skipped: int}
     */
    public function import_settings(array $export_settings): array
    {
        $options      = $export_settings['options'] ?? array();
        $wizard_flags = $export_settings['wizard_flags'] ?? array();
        $imported     = 0;
        $skipped      = 0;

        if (! empty($options) && is_array($options)) {
            $current = $this->settings->get();

            // Never import API keys.
            unset($options['api_key'], $options['google_api_key']);

            if (self::MODE_FORCE === $this->mode || self::MODE_OVERWRITE === $this->mode) {
                // Export values win.
                $merged = wp_parse_args($options, $current);
                // Preserve local API keys.
                $merged['api_key']        = $current['api_key'] ?? '';
                $merged['google_api_key'] = $current['google_api_key'] ?? '';
                update_option(Settings::OPTION_NAME, $merged);
                $imported++;
            } else {
                // Update mode: only fill in empty/missing keys.
                $changed = false;
                foreach ($options as $key => $value) {
                    if (! isset($current[$key]) || '' === $current[$key]) {
                        $current[$key] = $value;
                        $changed       = true;
                    }
                }
                if ($changed) {
                    update_option(Settings::OPTION_NAME, $current);
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }

        // Wizard flags.
        if (! empty($wizard_flags) && is_array($wizard_flags)) {
            foreach ($wizard_flags as $flag_key => $flag_value) {
                $option_name = 'ai_seo_captain_' . sanitize_key($flag_key);
                if (self::MODE_UPDATE === $this->mode) {
                    if (! get_option($option_name)) {
                        update_option($option_name, $flag_value);
                    }
                } else {
                    update_option($option_name, $flag_value);
                }
            }
        }

        return array('imported' => $imported, 'skipped' => $skipped);
    }

    // ------------------------------------------------------------------
    //  Post SEO meta import (chunked)
    // ------------------------------------------------------------------

    /**
     * Import SEO meta for a chunk of matched posts.
     *
     * @param array $export_posts   Full exported seo_meta_posts array.
     * @param array $matched_items  Array of match entries (from Matcher) to process this chunk.
     * @return array{imported: int, skipped: int, fields: int, log: array}
     */
    public function import_post_meta_chunk(array $export_posts, array $matched_items): array
    {
        $imported = 0;
        $skipped  = 0;
        $fields   = 0;
        $log      = array();

        foreach ($matched_items as $match) {
            $export_index = $match['export_index'];
            $local_id     = (int) $match['local_id'];

            if (! isset($export_posts[$export_index])) {
                $log[] = array('slug' => $match['export_slug'] ?? '?', 'status' => 'error', 'msg' => 'Export entry not found');
                continue;
            }

            $export_meta = $export_posts[$export_index]['meta'] ?? array();
            if (empty($export_meta)) {
                $skipped++;
                $log[] = array('slug' => $match['export_slug'], 'status' => 'skipped', 'msg' => 'No meta data');
                continue;
            }

            // URL rewriting.
            $export_meta = $this->url_rewriter->rewrite_post_meta($export_meta);

            $fields_written = $this->write_post_meta($local_id, $export_meta);
            $fields        += $fields_written;
            $imported++;

            $log[] = array(
                'slug'   => $match['export_slug'],
                'status' => 'imported',
                'msg'    => $fields_written . ' fields',
            );
        }

        return array('imported' => $imported, 'skipped' => $skipped, 'fields' => $fields, 'log' => $log);
    }

    /**
     * Write meta values to a post based on the current mode.
     */
    private function write_post_meta(int $post_id, array $meta): int
    {
        $written = 0;

        if (self::MODE_FORCE === $this->mode) {
            // Delete ALL plugin meta keys first.
            foreach (self::ALL_POST_META_KEYS as $key) {
                delete_post_meta($post_id, $key);
            }
        }

        foreach ($meta as $key => $value) {
            // Only allow known plugin keys.
            if (0 !== strpos($key, '_ai_seo_captain_')) {
                continue;
            }

            if (self::MODE_UPDATE === $this->mode) {
                $existing = get_post_meta($post_id, $key, true);
                if ('' !== trim((string) $existing)) {
                    continue; // Don't overwrite existing data.
                }
            }

            update_post_meta($post_id, sanitize_key($key), wp_kses_post($value));
            $written++;
        }

        return $written;
    }

    // ------------------------------------------------------------------
    //  Term SEO meta import (chunked)
    // ------------------------------------------------------------------

    /**
     * Import SEO meta for a chunk of matched terms.
     *
     * @param array $export_terms   Full exported seo_meta_terms array.
     * @param array $matched_items  Array of match entries to process this chunk.
     * @return array{imported: int, skipped: int, fields: int, log: array}
     */
    public function import_term_meta_chunk(array $export_terms, array $matched_items): array
    {
        $imported = 0;
        $skipped  = 0;
        $fields   = 0;
        $log      = array();

        foreach ($matched_items as $match) {
            $export_index = $match['export_index'];
            $local_tid    = (int) $match['local_term_id'];

            if (! isset($export_terms[$export_index])) {
                $log[] = array('slug' => $match['export_slug'] ?? '?', 'status' => 'error', 'msg' => 'Export entry not found');
                continue;
            }

            $export_meta = $export_terms[$export_index]['meta'] ?? array();
            if (empty($export_meta)) {
                $skipped++;
                continue;
            }

            $fields_written = $this->write_term_meta($local_tid, $export_meta);
            $fields        += $fields_written;
            $imported++;

            $log[] = array(
                'slug'   => $match['export_slug'],
                'status' => 'imported',
                'msg'    => $fields_written . ' fields',
            );
        }

        return array('imported' => $imported, 'skipped' => $skipped, 'fields' => $fields, 'log' => $log);
    }

    private function write_term_meta(int $term_id, array $meta): int
    {
        $written = 0;

        if (self::MODE_FORCE === $this->mode) {
            foreach (self::ALL_TERM_META_KEYS as $key) {
                delete_term_meta($term_id, $key);
            }
        }

        foreach ($meta as $key => $value) {
            if (0 !== strpos($key, '_ai_seo_captain_')) {
                continue;
            }

            if (self::MODE_UPDATE === $this->mode) {
                $existing = get_term_meta($term_id, $key, true);
                if ('' !== trim((string) $existing)) {
                    continue;
                }
            }

            update_term_meta($term_id, sanitize_key($key), sanitize_text_field($value));
            $written++;
        }

        return $written;
    }

    // ------------------------------------------------------------------
    //  Audit data import
    // ------------------------------------------------------------------

    /**
     * Import audit meta for matched posts.
     *
     * @param array $audit_data     The audits section from export (has audit_meta and content_index).
     * @param array $post_matches   Post match results from Matcher (strong + user-approved fuzzy).
     * @return array{imported: int, skipped: int, log: array}
     */
    public function import_audits(array $audit_data, array $post_matches): array
    {
        $imported = 0;
        $skipped  = 0;
        $log      = array();

        // Build a slug→local_id map from the matched posts.
        $slug_map = array();
        foreach ($post_matches as $match) {
            $key            = ($match['export_type'] ?? '') . '::' . ($match['export_slug'] ?? '');
            $slug_map[$key] = (int) $match['local_id'];
        }

        // Import audit meta.
        $audit_meta_entries = $audit_data['audit_meta'] ?? array();
        foreach ($audit_meta_entries as $entry) {
            $mkey     = $entry['match_key'] ?? array();
            $lookup   = ($mkey['post_type'] ?? '') . '::' . ($mkey['slug'] ?? '');
            $local_id = $slug_map[$lookup] ?? 0;

            if (0 === $local_id) {
                $skipped++;
                continue;
            }

            $meta = $entry['meta'] ?? array();
            $this->write_post_meta($local_id, $meta);
            $imported++;
        }

        // Import content index entries.
        $content_index = $audit_data['content_index'] ?? array();
        if (! empty($content_index)) {
            $ci_result = $this->import_content_index($content_index, $slug_map);
            $log[]     = array('slug' => 'content_index', 'status' => 'imported', 'msg' => $ci_result . ' entries');
        }

        return array('imported' => $imported, 'skipped' => $skipped, 'log' => $log);
    }

    private function import_content_index(array $entries, array $slug_map): int
    {
        global $wpdb;
        $table   = $wpdb->prefix . 'ai_seo_captain_content_index';
        $written = 0;

        foreach ($entries as $entry) {
            $mkey     = $entry['match_key'] ?? array();
            $lookup   = ($mkey['post_type'] ?? '') . '::' . ($mkey['slug'] ?? '');
            $local_id = $slug_map[$lookup] ?? 0;

            if (0 === $local_id) {
                continue;
            }

            $permalink = $this->url_rewriter->rewrite_permalink($entry['permalink'] ?? '');

            if (self::MODE_UPDATE === $this->mode) {
                // Only insert if no entry exists.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE object_type = %s AND object_id = %d",
                        $entry['object_type'] ?? 'post',
                        $local_id
                    )
                );
                if ($exists) {
                    continue;
                }
            }

            if (self::MODE_FORCE === $this->mode || self::MODE_OVERWRITE === $this->mode) {
                // Delete existing entry for this object.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->delete(
                    $table,
                    array('object_type' => $entry['object_type'] ?? 'post', 'object_id' => $local_id),
                    array('%s', '%d')
                );
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert(
                $table,
                array(
                    'object_id'    => $local_id,
                    'object_type'  => $entry['object_type'] ?? 'post',
                    'post_type'    => $mkey['post_type'] ?? 'post',
                    'status'       => $entry['status'] ?? 'publish',
                    'title'        => $entry['title'] ?? '',
                    'slug'         => $entry['slug'] ?? '',
                    'permalink'    => $permalink,
                    'parent_id'    => (int) ($entry['parent_id'] ?? 0),
                    'excerpt'      => $entry['excerpt'] ?? '',
                    'content_hash' => $entry['content_hash'] ?? '',
                    'modified_gmt' => $entry['modified_gmt'] ?? current_time('mysql', true),
                    'indexed_at'   => $entry['indexed_at'] ?? current_time('mysql', true),
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
            );
            $written++;
        }

        return $written;
    }

    // ------------------------------------------------------------------
    //  Redirects import
    // ------------------------------------------------------------------

    /**
     * @return array{imported: int, skipped: int}
     */
    public function import_redirects(array $redirects): array
    {
        global $wpdb;
        $table    = $wpdb->prefix . 'ai_seo_captain_redirects';
        $imported = 0;
        $skipped  = 0;

        if (self::MODE_FORCE === $this->mode) {
            // Clear existing redirects (not 404 entries).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete($table, array('type' => 'redirect'), array('%s'));
        }

        foreach ($redirects as $r) {
            $source = sanitize_text_field($r['source_url'] ?? '');
            $target = esc_url_raw($this->url_rewriter->rewrite_redirect_target($r['target_url'] ?? ''));

            if ('' === $source || '' === $target) {
                $skipped++;
                continue;
            }

            // Normalize source.
            $source = trailingslashit('/' . ltrim($source, '/'));

            if (self::MODE_UPDATE === $this->mode) {
                // Only add if source URL doesn't already exist.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE source_url = %s AND type = 'redirect'",
                        $source
                    )
                );
                if ($exists) {
                    $skipped++;
                    continue;
                }
            } elseif (self::MODE_OVERWRITE === $this->mode) {
                // Delete existing entry with same source to replace it.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->delete($table, array('source_url' => $source, 'type' => 'redirect'), array('%s', '%s'));
            }

            $status_code = (int) ($r['status_code'] ?? 301);
            if (! in_array($status_code, array(301, 302, 307), true)) {
                $status_code = 301;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert(
                $table,
                array(
                    'source_url'  => $source,
                    'target_url'  => $target,
                    'status_code' => $status_code,
                    'type'        => 'redirect',
                    'hit_count'   => (int) ($r['hit_count'] ?? 0),
                    'last_hit'    => $r['last_hit'] ?? null,
                    'created_at'  => $r['created_at'] ?? current_time('mysql', true),
                ),
                array('%s', '%s', '%d', '%s', '%d', '%s', '%s')
            );
            $imported++;
        }

        return array('imported' => $imported, 'skipped' => $skipped);
    }

    // ------------------------------------------------------------------
    //  404 log import
    // ------------------------------------------------------------------

    /**
     * @return array{imported: int, skipped: int}
     */
    public function import_404s(array $entries): array
    {
        global $wpdb;
        $table    = $wpdb->prefix . 'ai_seo_captain_redirects';
        $imported = 0;
        $skipped  = 0;

        if (self::MODE_FORCE === $this->mode) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete($table, array('type' => '404'), array('%s'));
        }

        foreach ($entries as $entry) {
            $source = sanitize_text_field($entry['source_url'] ?? '');
            if ('' === $source) {
                $skipped++;
                continue;
            }

            $source = trailingslashit('/' . ltrim($source, '/'));

            if (self::MODE_UPDATE === $this->mode) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE source_url = %s AND type = '404'",
                        $source
                    )
                );
                if ($exists) {
                    $skipped++;
                    continue;
                }
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert(
                $table,
                array(
                    'source_url'  => $source,
                    'target_url'  => '',
                    'status_code' => 404,
                    'type'        => '404',
                    'hit_count'   => (int) ($entry['hit_count'] ?? 0),
                    'last_hit'    => $entry['last_hit'] ?? null,
                    'created_at'  => $entry['created_at'] ?? current_time('mysql', true),
                ),
                array('%s', '%s', '%d', '%s', '%d', '%s', '%s')
            );
            $imported++;
        }

        return array('imported' => $imported, 'skipped' => $skipped);
    }

    // ------------------------------------------------------------------
    //  Runs import
    // ------------------------------------------------------------------

    /**
     * @return array{imported: int, skipped: int}
     */
    public function import_runs(array $runs): array
    {
        global $wpdb;
        $table    = $wpdb->prefix . 'ai_seo_captain_runs';
        $imported = 0;

        if (self::MODE_FORCE === $this->mode) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query("TRUNCATE TABLE {$table}");
        }

        foreach ($runs as $run) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert(
                $table,
                array(
                    'user_id'         => get_current_user_id(),
                    'name'            => sanitize_text_field($run['name'] ?? ''),
                    'description'     => sanitize_textarea_field($run['description'] ?? ''),
                    'page_ids'        => sanitize_text_field($run['page_ids'] ?? ''),
                    'page_count'      => (int) ($run['page_count'] ?? 0),
                    'completed_steps' => sanitize_text_field($run['completed_steps'] ?? ''),
                    'status'          => sanitize_key($run['status'] ?? 'active'),
                    'created_at'      => $run['created_at'] ?? current_time('mysql', true),
                    'updated_at'      => $run['updated_at'] ?? current_time('mysql', true),
                ),
                array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
            );
            $imported++;
        }

        return array('imported' => $imported, 'skipped' => 0);
    }

    // ------------------------------------------------------------------
    //  Chat history import
    // ------------------------------------------------------------------

    /**
     * @return array{conversations: int, messages: int}
     */
    public function import_chat_history(array $chat_data, array $post_matches): array
    {
        global $wpdb;
        $conv_table = $wpdb->prefix . 'ai_seo_captain_conversations';
        $msg_table  = $wpdb->prefix . 'ai_seo_captain_messages';

        $conversations = $chat_data['conversations'] ?? array();
        $messages      = $chat_data['messages'] ?? array();

        if (self::MODE_FORCE === $this->mode) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query("TRUNCATE TABLE {$msg_table}");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query("TRUNCATE TABLE {$conv_table}");
        }

        // Build slug→local_id map for post remapping.
        $slug_map = array();
        foreach ($post_matches as $match) {
            $key            = ($match['export_type'] ?? '') . '::' . ($match['export_slug'] ?? '');
            $slug_map[$key] = (int) $match['local_id'];
        }

        // Map old conversation IDs to new ones.
        $conv_id_map  = array();
        $conv_count   = 0;

        foreach ($conversations as $conv) {
            $old_id = (int) ($conv['id'] ?? 0);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert(
                $conv_table,
                array(
                    'user_id'     => get_current_user_id(),
                    'object_id'   => (int) ($conv['object_id'] ?? 0),
                    'object_type' => sanitize_key($conv['object_type'] ?? 'post'),
                    'title'       => sanitize_text_field($conv['title'] ?? ''),
                    'created_at'  => $conv['created_at'] ?? current_time('mysql', true),
                    'updated_at'  => $conv['updated_at'] ?? current_time('mysql', true),
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s')
            );

            if ($wpdb->insert_id && $old_id > 0) {
                $conv_id_map[$old_id] = (int) $wpdb->insert_id;
                $conv_count++;
            }
        }

        // Insert messages with remapped conversation IDs.
        $msg_count = 0;
        foreach ($messages as $msg) {
            $old_conv_id = (int) ($msg['conversation_id'] ?? 0);
            $new_conv_id = $conv_id_map[$old_conv_id] ?? 0;

            if (0 === $new_conv_id) {
                continue; // Orphaned message — conversation wasn't imported.
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert(
                $msg_table,
                array(
                    'conversation_id' => $new_conv_id,
                    'role'            => sanitize_key($msg['role'] ?? 'user'),
                    'content'         => wp_kses_post($msg['content'] ?? ''),
                    'created_at'      => $msg['created_at'] ?? current_time('mysql', true),
                ),
                array('%d', '%s', '%s', '%s')
            );
            $msg_count++;
        }

        return array('conversations' => $conv_count, 'messages' => $msg_count);
    }
}
