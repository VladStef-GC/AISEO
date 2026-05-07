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

    private const PENDING_META_KEY = '_ai_seo_keeper_pending_content_changes';

    /**
     * Store approved changes as pending — they will be applied on the next
     * WordPress Update / Publish action instead of immediately.
     *
     * @param int    $post_id  The post to store pending changes for.
     * @param array  $changes  Array of ['old' => string, 'new' => string, ...].
     * @param string $summary  AI-generated summary of the changes.
     * @return void
     */
    public static function store_pending_changes(int $post_id, array $changes, string $summary = ''): void
    {
        $pending = array(
            'changes' => $changes,
            'summary' => $summary,
            'builder' => self::detect_builder($post_id),
            'approved_at' => current_time('mysql'),
        );

        update_post_meta($post_id, self::PENDING_META_KEY, $pending);
    }

    /**
     * Retrieve any pending content changes for a post.
     *
     * @return array{changes: array, summary: string, builder: string, approved_at: string}|array Empty if none.
     */
    public static function get_pending_changes(int $post_id): array
    {
        $pending = get_post_meta($post_id, self::PENDING_META_KEY, true);

        if (! is_array($pending) || empty($pending['changes'])) {
            return array();
        }

        return $pending;
    }

    /**
     * Apply stored pending changes to the actual post content.
     * Called on WordPress Update / Publish via save_post hook.
     *
     * @return array{applied: int, failed: int, details: array}|array Empty if nothing pending.
     */
    public static function apply_pending_changes(int $post_id): array
    {
        $pending = self::get_pending_changes($post_id);

        if (empty($pending)) {
            return array();
        }

        $result = self::apply_changes($post_id, $pending['changes']);

        // Clear the pending meta after applying.
        delete_post_meta($post_id, self::PENDING_META_KEY);

        return $result;
    }

    /**
     * Clear pending changes without applying them.
     */
    public static function clear_pending_changes(int $post_id): void
    {
        delete_post_meta($post_id, self::PENDING_META_KEY);
    }

    /**
     * Apply text changes to the given content string without writing to DB.
     * Used for preview rendering.
     *
     * @param string $content  The content to transform.
     * @param array  $changes  Array of ['old' => string, 'new' => string].
     * @return string Modified content.
     */
    public static function apply_changes_to_string(string $content, array $changes): string
    {
        foreach ($changes as $change) {
            $old = (string) ($change['old'] ?? '');
            $new = (string) ($change['new'] ?? '');

            if ('' === $old) {
                continue;
            }

            $pos = mb_strpos($content, $old);
            if (false === $pos) {
                continue;
            }

            $content = mb_substr($content, 0, $pos) . $new . mb_substr($content, $pos + mb_strlen($old));
        }

        return $content;
    }

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

            // BeTheme stores base64-encoded serialized arrays.
            $is_betheme = ('betheme' === $builder);
            $data = null;

            if ($is_betheme && is_string($raw)) {
                $decoded_b64 = base64_decode($raw, true);
                if (false !== $decoded_b64) {
                    $data = @unserialize($decoded_b64);
                }
            } elseif (is_array($raw)) {
                $data = $raw;
            }

            if (is_array($data)) {
                // Array storage: walk recursively to replace text.
                foreach ($changes as $change) {
                    $old = (string) ($change['old'] ?? '');
                    $new = (string) ($change['new'] ?? '');
                    if ('' === $old) {
                        $failed++;
                        $details[] = 'Empty search string — skipped.';
                        continue;
                    }

                    // Detect heading tag changes (e.g., <h6>text</h6> → <h4>text</h4>).
                    $tag_change_applied = false;
                    if (
                        $is_betheme && preg_match('/^<(h[1-6])>(.*)<\/\1>$/is', trim($old), $old_m)
                        && preg_match('/^<(h[1-6])>(.*)<\/\1>$/is', trim($new), $new_m)
                    ) {
                        $old_tag = strtolower($old_m[1]);
                        $new_tag = strtolower($new_m[1]);
                        $old_text = $old_m[2];
                        $new_text = $new_m[2];

                        // Try to find and update the heading item in the BeTheme structure.
                        $tag_change_applied = self::betheme_heading_replace($data, $old_tag, $old_text, $new_tag, $new_text);
                    }

                    if ($tag_change_applied) {
                        $applied++;
                        $details[] = 'Applied tag change: <' . ($old_m[1] ?? '') . '> → <' . ($new_m[1] ?? '') . '>';
                    } else {
                        // Standard text replacement via walk.
                        $found = false;
                        $data = self::walk_replace($data, $old, $new, $found);
                        if ($found) {
                            $applied++;
                            $details[] = 'Applied: "' . mb_substr($old, 0, 40) . '…" → "' . mb_substr($new, 0, 40) . '…"';
                        } else {
                            $failed++;
                            $details[] = 'Text not found in builder data: "' . mb_substr($old, 0, 60) . '…"';
                        }
                    }
                }

                if ($applied > 0) {
                    if ($is_betheme) {
                        // Re-serialize and base64 encode for BeTheme.
                        $serialized = serialize($data);
                        update_post_meta($post_id, $meta_key, base64_encode($serialized));
                    } else {
                        update_post_meta($post_id, $meta_key, $data);
                    }
                }
            } else {
                // String storage (JSON, shortcodes, etc.): direct str_replace.
                $content = is_string($raw) ? $raw : '';
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

                if ($applied > 0) {
                    update_post_meta($post_id, $meta_key, $content);
                }
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
     * Public wrapper for betheme_heading_replace (used by Frontend preview).
     */
    public static function betheme_heading_replace_public(array &$data, string $old_tag, string $old_text, string $new_tag, string $new_text): bool
    {
        return self::betheme_heading_replace($data, $old_tag, $old_text, $new_tag, $new_text);
    }

    /**
     * Public wrapper for walk_replace (used by Frontend preview).
     *
     * @param mixed  $data   The data structure to walk.
     * @param string $old    Text to find.
     * @param string $new    Replacement text.
     * @param bool   &$found Set to true if a replacement was made.
     * @return mixed The modified data structure.
     */
    public static function walk_replace_public($data, string $old, string $new, bool &$found)
    {
        return self::walk_replace($data, $old, $new, $found);
    }

    /**
     * Handle heading tag changes in BeTheme's structure.
     *
     * BeTheme heading items store the tag in attr['header_tag'] and text in
     * attr['title'] separately. This method finds the matching heading and
     * updates both the tag and the title content.
     *
     * @param array  &$data     The deserialized BeTheme items array.
     * @param string $old_tag   The current tag (e.g., 'h6').
     * @param string $old_text  The current heading text/HTML content.
     * @param string $new_tag   The desired new tag (e.g., 'h4').
     * @param string $new_text  The desired new text content.
     * @return bool True if a matching heading was found and updated.
     */
    private static function betheme_heading_replace(array &$data, string $old_tag, string $old_text, string $new_tag, string $new_text): bool
    {
        foreach ($data as &$item) {
            if (! is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? '';

            if ('heading' === $type && isset($item['attr']['header_tag'], $item['attr']['title'])) {
                $item_tag = strtolower((string) $item['attr']['header_tag']);
                $item_title = (string) $item['attr']['title'];

                // Match by tag AND text content (strip_tags for comparison since title may have <br/> etc).
                $old_text_clean = trim(strip_tags($old_text));
                $item_title_clean = trim(strip_tags($item_title));

                if ($item_tag === $old_tag && $item_title_clean === $old_text_clean) {
                    // Update tag.
                    $item['attr']['header_tag'] = $new_tag;

                    // Update text content if it also changed.
                    if (trim(strip_tags($new_text)) !== $old_text_clean) {
                        // Preserve any existing inline HTML (like <br/>) if only adding new text.
                        $item['attr']['title'] = $new_text;
                    }

                    return true;
                }
            }

            // Recurse into nested structures (sections → wraps → items).
            foreach (array('wraps', 'items', 'fields') as $child_key) {
                if (isset($item[$child_key]) && is_array($item[$child_key])) {
                    if (self::betheme_heading_replace($item[$child_key], $old_tag, $old_text, $new_tag, $new_text)) {
                        return true;
                    }
                }
            }
        }
        unset($item);

        return false;
    }

    /**
     * Recursively walk an array/object and replace the first occurrence of
     * a text string within any string value. Safe for serialized PHP arrays
     * (BeTheme, Beaver Builder draft data, etc.) because it operates on the
     * deserialized structure, not the serialized string.
     *
     * @param mixed  $data   The data structure to walk.
     * @param string $old    Text to find.
     * @param string $new    Replacement text.
     * @param bool   &$found Set to true if a replacement was made.
     * @return mixed The modified data structure.
     */
    private static function walk_replace($data, string $old, string $new, bool &$found)
    {
        if ($found) {
            return $data; // Only replace the first occurrence.
        }

        if (is_string($data)) {
            $pos = mb_strpos($data, $old);
            if (false !== $pos) {
                $data = mb_substr($data, 0, $pos) . $new . mb_substr($data, $pos + mb_strlen($old));
                $found = true;
            }
            return $data;
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::walk_replace($value, $old, $new, $found);
                if ($found) {
                    break;
                }
            }
            return $data;
        }

        if (is_object($data)) {
            foreach (get_object_vars($data) as $prop => $value) {
                $data->$prop = self::walk_replace($value, $old, $new, $found);
                if ($found) {
                    break;
                }
            }
            return $data;
        }

        return $data;
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

        if ('betheme' === $builder) {
            // Regenerate BeTheme's SEO helper text from the updated builder data.
            // This ensures the mfn-page-items-seo field stays in sync.
            // BeTheme's own save hooks will also regenerate this on the next page save,
            // but we update it here for immediate consistency in the admin.
            delete_post_meta($post_id, 'mfn-page-items-seo');
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
