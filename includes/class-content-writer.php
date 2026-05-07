<?php

namespace AI_SEO_Keeper;

/**
 * Content_Writer — applies text changes to post content across page builders.
 *
 * Companion to Content_Helper (read) — this class handles write-back.
 * Uses str_replace on the raw storage format (HTML, JSON, serialized array)
 * because the AI changeset targets exact original text strings.
 */
class Content_Writer
{
    private static array $builder_meta_keys = array(
        'betheme' => 'mfn-page-items-seo',
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

    /**
     * Apply an array of text changes to a post.
     *
     * @param int   $post_id  The post to modify.
     * @param array $changes  Array of ['old' => string, 'new' => string].
     * @return array{applied: int, failed: int, details: array}
     */
    public static function apply_changes(int $post_id, array $changes): array
    {
        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            return array('applied' => 0, 'failed' => count($changes), 'details' => array('Post not found.'));
        }

        // Create a revision before making changes.
        self::create_backup($post_id);

        $builder = self::detect_builder($post_id);
        $applied = 0;
        $failed = 0;
        $details = array();

        if ('post_content' === $builder) {
            // Classic, Gutenberg, WPBakery, Divi — all stored in post_content.
            $content = (string) $post->post_content;
            foreach ($changes as $change) {
                $old = (string) ($change['old'] ?? '');
                $new = (string) ($change['new'] ?? '');
                if ('' === $old) {
                    $failed++;
                    $details[] = 'Empty search string — skipped.';
                    continue;
                }
                if (false === mb_strpos($content, $old)) {
                    $failed++;
                    $details[] = 'Text not found: "' . mb_substr($old, 0, 60) . '…"';
                    continue;
                }
                // Replace only the first occurrence.
                $pos = mb_strpos($content, $old);
                $content = mb_substr($content, 0, $pos) . $new . mb_substr($content, $pos + mb_strlen($old));
                $applied++;
                $details[] = 'Applied: "' . mb_substr($old, 0, 40) . '…" → "' . mb_substr($new, 0, 40) . '…"';
            }

            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $content,
            ));
        } else {
            // Page builder — content is in a meta key.
            $meta_key = self::$builder_meta_keys[$builder] ?? '';
            if ('' === $meta_key) {
                return array('applied' => 0, 'failed' => count($changes), 'details' => array('Unknown builder: ' . $builder));
            }

            $raw = get_post_meta($post_id, $meta_key, true);
            $content = is_string($raw) ? $raw : maybe_serialize($raw);

            foreach ($changes as $change) {
                $old = (string) ($change['old'] ?? '');
                $new = (string) ($change['new'] ?? '');
                if ('' === $old) {
                    $failed++;
                    $details[] = 'Empty search string — skipped.';
                    continue;
                }
                if (false === mb_strpos($content, $old)) {
                    $failed++;
                    $details[] = 'Text not found in builder data: "' . mb_substr($old, 0, 60) . '…"';
                    continue;
                }
                $pos = mb_strpos($content, $old);
                $content = mb_substr($content, 0, $pos) . $new . mb_substr($content, $pos + mb_strlen($old));
                $applied++;
                $details[] = 'Applied: "' . mb_substr($old, 0, 40) . '…" → "' . mb_substr($new, 0, 40) . '…"';
            }

            // For serialized arrays (BeTheme), we need to update the serialized string.
            // Since we did str_replace on the serialized/JSON string, update as raw string.
            if (is_array($raw)) {
                // BeTheme and similar: re-serialize after replacing in the serialized string.
                // This only works if the replacement doesn't change string lengths in serialized format.
                // Safer: unserialize, walk, replace, re-serialize.
                $unserialized = maybe_unserialize($content);
                if (is_array($unserialized)) {
                    update_post_meta($post_id, $meta_key, $unserialized);
                } else {
                    // Fallback: delete old, insert new raw value.
                    delete_post_meta($post_id, $meta_key);
                    $GLOBALS['wpdb']->insert(
                        $GLOBALS['wpdb']->postmeta,
                        array(
                            'post_id' => $post_id,
                            'meta_key' => $meta_key,
                            'meta_value' => $content,
                        ),
                        array('%d', '%s', '%s')
                    );
                    wp_cache_delete($post_id, 'post_meta');
                }
            } else {
                update_post_meta($post_id, $meta_key, $content);
            }
        }

        // Clean any page builder caches.
        self::flush_builder_cache($post_id, $builder);

        return array(
            'applied' => $applied,
            'failed' => $failed,
            'details' => $details,
        );
    }

    /**
     * Detect which storage the post uses.
     *
     * @return string 'post_content' or a builder key like 'betheme', 'elementor', etc.
     */
    public static function detect_builder(int $post_id): string
    {
        foreach (self::$builder_meta_keys as $builder => $meta_key) {
            $val = get_post_meta($post_id, $meta_key, true);
            if (! empty($val)) {
                return $builder;
            }
        }

        return 'post_content';
    }

    /**
     * Create a WordPress revision before editing.
     */
    private static function create_backup(int $post_id): void
    {
        // Store a copy of the current content in post meta as a safety net.
        $post = get_post($post_id);
        if (! $post) {
            return;
        }

        $backup = array(
            'post_content' => $post->post_content,
            'timestamp' => current_time('mysql'),
            'builder' => self::detect_builder($post_id),
        );

        $builder = self::detect_builder($post_id);
        if ('post_content' !== $builder) {
            $meta_key = self::$builder_meta_keys[$builder] ?? '';
            if ('' !== $meta_key) {
                $backup['builder_meta'] = get_post_meta($post_id, $meta_key, true);
            }
        }

        update_post_meta($post_id, '_ai_seo_keeper_content_backup', $backup);

        // Also trigger a WordPress revision if revisions are enabled.
        if (wp_revisions_enabled($post)) {
            wp_save_post_revision($post_id);
        }
    }

    /**
     * Flush page builder caches after content changes.
     */
    private static function flush_builder_cache(int $post_id, string $builder): void
    {
        clean_post_cache($post_id);

        if ('elementor' === $builder) {
            delete_post_meta($post_id, '_elementor_css');
        }

        if ('beaver' === $builder) {
            delete_post_meta($post_id, '_fl_builder_draft');
        }
    }

    /**
     * Restore from backup created before AI edits.
     *
     * @return bool True if restored successfully.
     */
    public static function restore_backup(int $post_id): bool
    {
        $backup = get_post_meta($post_id, '_ai_seo_keeper_content_backup', true);

        if (! is_array($backup) || empty($backup['post_content'])) {
            return false;
        }

        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $backup['post_content'],
        ));

        if (! empty($backup['builder_meta']) && ! empty($backup['builder'])) {
            $meta_key = self::$builder_meta_keys[$backup['builder']] ?? '';
            if ('' !== $meta_key) {
                update_post_meta($post_id, $meta_key, $backup['builder_meta']);
            }
        }

        delete_post_meta($post_id, '_ai_seo_keeper_content_backup');
        clean_post_cache($post_id);

        return true;
    }
}
