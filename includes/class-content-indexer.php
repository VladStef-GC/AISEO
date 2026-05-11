<?php

namespace AI_SEO_Keeper;

class Content_Indexer
{
    private const META_TITLE_KEY = '_ai_seo_keeper_meta_title';

    private const META_DESCRIPTION_KEY = '_ai_seo_keeper_meta_description';

    private const SOCIAL_TITLE_META_KEY = '_ai_seo_keeper_social_title';

    private const SOCIAL_DESCRIPTION_META_KEY = '_ai_seo_keeper_social_description';

    private const SOCIAL_IMAGE_META_KEY = '_ai_seo_keeper_social_image';

    private const CANONICAL_URL_META_KEY = '_ai_seo_keeper_canonical_url';

    private const ROBOTS_DIRECTIVES_META_KEY = '_ai_seo_keeper_robots_directives';

    private const SCHEMA_TYPE_META_KEY = '_ai_seo_keeper_schema_type';

    private const FRONTEND_ENABLE_META_KEY = '_ai_seo_keeper_frontend_enabled';

    private const APPROVED_MESSAGE_META_KEY = '_ai_seo_keeper_approved_message_id';

    public function get_summary(): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';

        return array(
            'total_items' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"),
            'last_sync'   => $wpdb->get_var("SELECT MAX(indexed_at) FROM {$table_name}"),
        );
    }

    public function get_audit_summary(): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $postmeta_table = $wpdb->postmeta;

        $sql = $wpdb->prepare(
            "SELECT
                COUNT(*) AS total_items,
                SUM(CASE WHEN idx.status = %s THEN 1 ELSE 0 END) AS published_items,
                SUM(CASE WHEN COALESCE(pm_title.meta_value, '') = '' THEN 1 ELSE 0 END) AS missing_title_drafts,
                SUM(CASE WHEN COALESCE(pm_description.meta_value, '') = '' THEN 1 ELSE 0 END) AS missing_description_drafts,
                SUM(CASE WHEN CAST(COALESCE(pm_approved.meta_value, '0') AS UNSIGNED) > 0 THEN 1 ELSE 0 END) AS approved_items,
                SUM(CASE WHEN COALESCE(pm_frontend.meta_value, '') = '1' THEN 1 ELSE 0 END) AS frontend_enabled_items,
                SUM(CASE WHEN COALESCE(pm_frontend.meta_value, '') = '1' AND (
                    CAST(COALESCE(pm_approved.meta_value, '0') AS UNSIGNED) > 0
                    OR COALESCE(pm_title.meta_value, '') <> ''
                    OR COALESCE(pm_description.meta_value, '') <> ''
                    OR COALESCE(pm_social_title.meta_value, '') <> ''
                    OR COALESCE(pm_social_description.meta_value, '') <> ''
                    OR COALESCE(pm_social_image.meta_value, '') <> ''
                    OR COALESCE(pm_canonical.meta_value, '') <> ''
                    OR COALESCE(pm_robots.meta_value, '') <> ''
                    OR COALESCE(pm_schema.meta_value, '') <> ''
                ) THEN 1 ELSE 0 END) AS frontend_ready_items
            FROM {$table_name} idx
            LEFT JOIN {$postmeta_table} pm_title ON pm_title.post_id = idx.object_id AND pm_title.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_description ON pm_description.post_id = idx.object_id AND pm_description.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_approved ON pm_approved.post_id = idx.object_id AND pm_approved.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_frontend ON pm_frontend.post_id = idx.object_id AND pm_frontend.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_social_title ON pm_social_title.post_id = idx.object_id AND pm_social_title.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_social_description ON pm_social_description.post_id = idx.object_id AND pm_social_description.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_social_image ON pm_social_image.post_id = idx.object_id AND pm_social_image.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_canonical ON pm_canonical.post_id = idx.object_id AND pm_canonical.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_robots ON pm_robots.post_id = idx.object_id AND pm_robots.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_schema ON pm_schema.post_id = idx.object_id AND pm_schema.meta_key = %s
            WHERE idx.object_type = %s",
            'publish',
            self::META_TITLE_KEY,
            self::META_DESCRIPTION_KEY,
            self::APPROVED_MESSAGE_META_KEY,
            self::FRONTEND_ENABLE_META_KEY,
            self::SOCIAL_TITLE_META_KEY,
            self::SOCIAL_DESCRIPTION_META_KEY,
            self::SOCIAL_IMAGE_META_KEY,
            self::CANONICAL_URL_META_KEY,
            self::ROBOTS_DIRECTIVES_META_KEY,
            self::SCHEMA_TYPE_META_KEY,
            'post'
        );

        $row = $wpdb->get_row($sql, ARRAY_A);

        if (! is_array($row)) {
            return array(
                'total_items' => 0,
                'published_items' => 0,
                'missing_title_drafts' => 0,
                'missing_description_drafts' => 0,
                'approved_items' => 0,
                'frontend_enabled_items' => 0,
                'frontend_ready_items' => 0,
            );
        }

        return array(
            'total_items' => (int) $row['total_items'],
            'published_items' => (int) $row['published_items'],
            'missing_title_drafts' => (int) $row['missing_title_drafts'],
            'missing_description_drafts' => (int) $row['missing_description_drafts'],
            'approved_items' => (int) $row['approved_items'],
            'frontend_enabled_items' => (int) $row['frontend_enabled_items'],
            'frontend_ready_items' => (int) $row['frontend_ready_items'],
        );
    }

    public function get_audit_rows(int $limit = 8): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $postmeta_table = $wpdb->postmeta;
        $limit = max(1, min(20, $limit));

        $sql = $wpdb->prepare(
            "SELECT
                idx.object_id,
                idx.post_type,
                idx.status,
                idx.title,
                idx.permalink,
                idx.modified_gmt,
                CASE WHEN COALESCE(pm_title.meta_value, '') = '' THEN 0 ELSE 1 END AS has_title_draft,
                CASE WHEN COALESCE(pm_description.meta_value, '') = '' THEN 0 ELSE 1 END AS has_description_draft,
                CASE WHEN CAST(COALESCE(pm_approved.meta_value, '0') AS UNSIGNED) > 0 THEN 1 ELSE 0 END AS has_approved_suggestion,
                CASE WHEN COALESCE(pm_frontend.meta_value, '') = '1' THEN 1 ELSE 0 END AS frontend_enabled,
                CASE WHEN COALESCE(pm_frontend.meta_value, '') = '1' AND (
                    CAST(COALESCE(pm_approved.meta_value, '0') AS UNSIGNED) > 0
                    OR COALESCE(pm_title.meta_value, '') <> ''
                    OR COALESCE(pm_description.meta_value, '') <> ''
                    OR COALESCE(pm_social_title.meta_value, '') <> ''
                    OR COALESCE(pm_social_description.meta_value, '') <> ''
                    OR COALESCE(pm_social_image.meta_value, '') <> ''
                    OR COALESCE(pm_canonical.meta_value, '') <> ''
                    OR COALESCE(pm_robots.meta_value, '') <> ''
                    OR COALESCE(pm_schema.meta_value, '') <> ''
                ) THEN 1 ELSE 0 END AS frontend_ready
            FROM {$table_name} idx
            LEFT JOIN {$postmeta_table} pm_title ON pm_title.post_id = idx.object_id AND pm_title.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_description ON pm_description.post_id = idx.object_id AND pm_description.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_approved ON pm_approved.post_id = idx.object_id AND pm_approved.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_frontend ON pm_frontend.post_id = idx.object_id AND pm_frontend.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_social_title ON pm_social_title.post_id = idx.object_id AND pm_social_title.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_social_description ON pm_social_description.post_id = idx.object_id AND pm_social_description.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_social_image ON pm_social_image.post_id = idx.object_id AND pm_social_image.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_canonical ON pm_canonical.post_id = idx.object_id AND pm_canonical.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_robots ON pm_robots.post_id = idx.object_id AND pm_robots.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_schema ON pm_schema.post_id = idx.object_id AND pm_schema.meta_key = %s
            WHERE idx.object_type = %s
            ORDER BY
                CASE WHEN idx.status = %s THEN 0 ELSE 1 END,
                CASE WHEN COALESCE(pm_title.meta_value, '') = '' OR COALESCE(pm_description.meta_value, '') = '' THEN 0 ELSE 1 END,
                CASE WHEN CAST(COALESCE(pm_approved.meta_value, '0') AS UNSIGNED) > 0 THEN 1 ELSE 0 END,
                idx.modified_gmt DESC
            LIMIT %d",
            self::META_TITLE_KEY,
            self::META_DESCRIPTION_KEY,
            self::APPROVED_MESSAGE_META_KEY,
            self::FRONTEND_ENABLE_META_KEY,
            self::SOCIAL_TITLE_META_KEY,
            self::SOCIAL_DESCRIPTION_META_KEY,
            self::SOCIAL_IMAGE_META_KEY,
            self::CANONICAL_URL_META_KEY,
            self::ROBOTS_DIRECTIVES_META_KEY,
            self::SCHEMA_TYPE_META_KEY,
            'post',
            'publish',
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($rows)) {
            return array();
        }

        return array_map(
            static function (array $row): array {
                return array(
                    'object_id' => (int) $row['object_id'],
                    'post_type' => (string) $row['post_type'],
                    'status' => (string) $row['status'],
                    'title' => (string) $row['title'],
                    'permalink' => (string) $row['permalink'],
                    'modified_gmt' => (string) $row['modified_gmt'],
                    'has_title_draft' => ! empty($row['has_title_draft']),
                    'has_description_draft' => ! empty($row['has_description_draft']),
                    'has_approved_suggestion' => ! empty($row['has_approved_suggestion']),
                    'frontend_enabled' => ! empty($row['frontend_enabled']),
                    'frontend_ready' => ! empty($row['frontend_ready']),
                );
            },
            $rows
        );
    }

    public function build_site_audit_report(int $limit = 8): array
    {
        return array(
            'summary' => $this->get_audit_summary(),
            'priority_rows' => $this->get_audit_rows($limit),
            'duplicate_live_titles' => $this->get_duplicate_index_titles($limit),
            'duplicate_ai_titles' => $this->get_duplicate_meta_values(self::META_TITLE_KEY, 'AI title draft', $limit),
            'duplicate_ai_descriptions' => $this->get_duplicate_meta_values(self::META_DESCRIPTION_KEY, 'AI description draft', $limit),
            'thin_content_rows' => $this->get_thin_content_rows($limit),
            'discovery' => array(
                'llms_url' => home_url('/llms.txt'),
                'llms_full_url' => home_url('/llms-full.txt'),
                'sitemap_url' => $this->get_sitemap_url(),
            ),
        );
    }

    public function get_duplicate_index_titles(int $limit = 5): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $limit = max(1, min(20, $limit));

        $groups = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT LOWER(TRIM(title)) AS normalized_value, COUNT(*) AS duplicate_count
                FROM {$table_name}
                WHERE object_type = %s
                    AND status = %s
                    AND TRIM(title) != ''
                GROUP BY LOWER(TRIM(title))
                HAVING COUNT(*) > 1
                ORDER BY duplicate_count DESC, normalized_value ASC
                LIMIT %d",
                'post',
                'publish',
                $limit
            ),
            ARRAY_A
        );

        if (! is_array($groups)) {
            return array();
        }

        $duplicates = array();

        foreach ($groups as $group) {
            if (empty($group['normalized_value'])) {
                continue;
            }

            $entries = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT object_id, post_type, title, permalink
                    FROM {$table_name}
                    WHERE object_type = %s
                        AND status = %s
                        AND LOWER(TRIM(title)) = %s
                    ORDER BY modified_gmt DESC, object_id DESC",
                    'post',
                    'publish',
                    (string) $group['normalized_value']
                ),
                ARRAY_A
            );

            $duplicates[] = array(
                'label' => 'Live page title',
                'value' => $this->humanize_duplicate_value((string) $group['normalized_value']),
                'count' => (int) $group['duplicate_count'],
                'entries' => $this->map_duplicate_entries(is_array($entries) ? $entries : array()),
            );
        }

        return $duplicates;
    }

    public function get_duplicate_meta_values(string $meta_key, string $label, int $limit = 5): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $postmeta_table = $wpdb->postmeta;
        $limit = max(1, min(20, $limit));

        $groups = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT LOWER(TRIM(pm.meta_value)) AS normalized_value, COUNT(*) AS duplicate_count
                FROM {$table_name} idx
                INNER JOIN {$postmeta_table} pm ON pm.post_id = idx.object_id AND pm.meta_key = %s
                WHERE idx.object_type = %s
                    AND idx.status = %s
                    AND TRIM(COALESCE(pm.meta_value, '')) != ''
                GROUP BY LOWER(TRIM(pm.meta_value))
                HAVING COUNT(*) > 1
                ORDER BY duplicate_count DESC, normalized_value ASC
                LIMIT %d",
                $meta_key,
                'post',
                'publish',
                $limit
            ),
            ARRAY_A
        );

        if (! is_array($groups)) {
            return array();
        }

        $duplicates = array();

        foreach ($groups as $group) {
            if (empty($group['normalized_value'])) {
                continue;
            }

            $entries = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT idx.object_id, idx.post_type, idx.title, idx.permalink
                    FROM {$table_name} idx
                    INNER JOIN {$postmeta_table} pm ON pm.post_id = idx.object_id AND pm.meta_key = %s
                    WHERE idx.object_type = %s
                        AND idx.status = %s
                        AND LOWER(TRIM(pm.meta_value)) = %s
                    ORDER BY idx.modified_gmt DESC, idx.object_id DESC",
                    $meta_key,
                    'post',
                    'publish',
                    (string) $group['normalized_value']
                ),
                ARRAY_A
            );

            $duplicates[] = array(
                'label' => $label,
                'value' => $this->humanize_duplicate_value((string) $group['normalized_value']),
                'count' => (int) $group['duplicate_count'],
                'entries' => $this->map_duplicate_entries(is_array($entries) ? $entries : array()),
            );
        }

        return $duplicates;
    }

    public function get_thin_content_rows(int $limit = 5, int $word_threshold = 120): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $posts_table = $wpdb->posts;
        $limit = max(1, min(20, $limit));
        $word_threshold = max(40, $word_threshold);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT idx.object_id, idx.post_type, idx.title, idx.permalink, posts.post_excerpt, posts.post_content
                FROM {$table_name} idx
                INNER JOIN {$posts_table} posts ON posts.ID = idx.object_id
                WHERE idx.object_type = %s
                    AND idx.status = %s
                ORDER BY posts.post_modified_gmt DESC, posts.ID DESC",
                'post',
                'publish'
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return array();
        }

        $thin_rows = array();

        foreach ($rows as $row) {
            $source_text = trim((string) $row['post_excerpt']);

            if ('' === $source_text) {
                $source_text = (string) $row['post_content'];
            }

            // If post_content is empty, check page-builder meta fields.
            if ('' === trim($source_text)) {
                $source_text = Content_Helper::get_content((int) $row['object_id']);
            }

            $normalized = trim((string) preg_replace('/\s+/', ' ', wp_strip_all_tags($source_text)));
            $word_count = '' === $normalized ? 0 : count(preg_split('/\s+/', $normalized));

            if ($word_count >= $word_threshold) {
                continue;
            }

            $thin_rows[] = array(
                'object_id' => (int) $row['object_id'],
                'post_type' => (string) $row['post_type'],
                'title' => (string) $row['title'],
                'permalink' => (string) $row['permalink'],
                'word_count' => $word_count,
            );

            if (count($thin_rows) >= $limit) {
                break;
            }
        }

        return $thin_rows;
    }

    private function map_duplicate_entries(array $entries): array
    {
        return array_map(
            static function (array $entry): array {
                return array(
                    'object_id' => isset($entry['object_id']) ? (int) $entry['object_id'] : 0,
                    'post_type' => isset($entry['post_type']) ? (string) $entry['post_type'] : '',
                    'title' => isset($entry['title']) ? (string) $entry['title'] : '',
                    'permalink' => isset($entry['permalink']) ? (string) $entry['permalink'] : '',
                );
            },
            $entries
        );
    }

    private function humanize_duplicate_value(string $value): string
    {
        return '' !== trim($value) ? trim($value) : '(empty)';
    }

    private function get_sitemap_url(): string
    {
        $active_plugins = (array) get_option('active_plugins', array());

        if (in_array('wordpress-seo/wp-seo.php', $active_plugins, true)) {
            return home_url('/sitemap_index.xml');
        }

        return home_url('/wp-sitemap.xml');
    }

    public function get_related_entries(int $current_id, string $post_type, int $parent_id, int $limit = 5): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $limit = max(1, min(10, $limit));

        $sql = $wpdb->prepare(
            "SELECT object_id, title, slug, permalink, excerpt, status, parent_id
            FROM {$table_name}
            WHERE object_type = %s
                AND post_type = %s
                AND object_id != %d
            ORDER BY CASE WHEN parent_id = %d THEN 0 ELSE 1 END,
                CASE WHEN status = 'publish' THEN 0 ELSE 1 END,
                indexed_at DESC
            LIMIT %d",
            'post',
            $post_type,
            $current_id,
            $parent_id,
            $limit
        );

        $results = $wpdb->get_results($sql, ARRAY_A);

        return is_array($results) ? $results : array();
    }

    /**
     * Get the page hierarchy context for AI Chat: parent, siblings, children — with SEO metadata.
     *
     * @return array{parent: array|null, siblings: array, children: array, grandparent: array|null, position: string}
     */
    public function get_hierarchy_context(int $post_id): array
    {
        global $wpdb;

        $table_name  = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $postmeta    = $wpdb->postmeta;

        $current = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT object_id, post_type, parent_id, title, slug, permalink, status
                 FROM {$table_name}
                 WHERE object_type = %s AND object_id = %d",
                'post',
                $post_id
            ),
            ARRAY_A
        );

        if (! is_array($current)) {
            return array(
                'parent'       => null,
                'siblings'     => array(),
                'children'     => array(),
                'grandparent'  => null,
                'position'     => '',
            );
        }

        $parent_id = (int) $current['parent_id'];
        $post_type = (string) $current['post_type'];

        // Fetch parent with SEO meta.
        $parent = null;
        if ($parent_id > 0) {
            $parent = $this->fetch_page_with_seo_meta($table_name, $postmeta, $parent_id);
        }

        // Fetch grandparent (one level above parent).
        $grandparent = null;
        if (is_array($parent) && (int) $parent['parent_id'] > 0) {
            $grandparent = $this->fetch_page_with_seo_meta($table_name, $postmeta, (int) $parent['parent_id']);
        }

        // Fetch siblings (same parent, same post_type, excluding current).
        $siblings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT idx.object_id, idx.title, idx.slug, idx.permalink, idx.status, idx.parent_id,
                        COALESCE(pm_kp.meta_value, '')    AS focus_keyphrase,
                        COALESCE(pm_title.meta_value, '') AS seo_title,
                        COALESCE(pm_desc.meta_value, '')  AS meta_description
                 FROM {$table_name} idx
                 LEFT JOIN {$postmeta} pm_kp    ON pm_kp.post_id    = idx.object_id AND pm_kp.meta_key    = '_ai_seo_keeper_focus_keyphrase'
                 LEFT JOIN {$postmeta} pm_title ON pm_title.post_id = idx.object_id AND pm_title.meta_key = %s
                 LEFT JOIN {$postmeta} pm_desc  ON pm_desc.post_id  = idx.object_id AND pm_desc.meta_key  = %s
                 WHERE idx.object_type = %s
                   AND idx.post_type   = %s
                   AND idx.parent_id   = %d
                   AND idx.object_id  != %d
                   AND idx.status      = %s
                 ORDER BY idx.title ASC",
                self::META_TITLE_KEY,
                self::META_DESCRIPTION_KEY,
                'post',
                $post_type,
                $parent_id,
                $post_id,
                'publish'
            ),
            ARRAY_A
        );

        // Fetch children of the current page.
        $children = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT idx.object_id, idx.title, idx.slug, idx.permalink, idx.status, idx.parent_id,
                        COALESCE(pm_kp.meta_value, '')    AS focus_keyphrase,
                        COALESCE(pm_title.meta_value, '') AS seo_title,
                        COALESCE(pm_desc.meta_value, '')  AS meta_description
                 FROM {$table_name} idx
                 LEFT JOIN {$postmeta} pm_kp    ON pm_kp.post_id    = idx.object_id AND pm_kp.meta_key    = '_ai_seo_keeper_focus_keyphrase'
                 LEFT JOIN {$postmeta} pm_title ON pm_title.post_id = idx.object_id AND pm_title.meta_key = %s
                 LEFT JOIN {$postmeta} pm_desc  ON pm_desc.post_id  = idx.object_id AND pm_desc.meta_key  = %s
                 WHERE idx.object_type = %s
                   AND idx.post_type   = %s
                   AND idx.parent_id   = %d
                   AND idx.status      = %s
                 ORDER BY idx.title ASC",
                self::META_TITLE_KEY,
                self::META_DESCRIPTION_KEY,
                'post',
                $post_type,
                $post_id,
                'publish'
            ),
            ARRAY_A
        );

        // Build a human-readable position string.
        $sibling_count = is_array($siblings) ? count($siblings) : 0;
        $child_count   = is_array($children) ? count($children) : 0;

        $position_parts = array();
        if (is_array($parent)) {
            $position_parts[] = 'Child of "' . $parent['title'] . '"';
            $position_parts[] = ($sibling_count + 1) . ' page(s) at this level';
        } else {
            $position_parts[] = 'Top-level page';
        }
        if ($child_count > 0) {
            $position_parts[] = $child_count . ' child page(s) below';
        }

        return array(
            'parent'      => $parent,
            'siblings'    => is_array($siblings) ? $siblings : array(),
            'children'    => is_array($children) ? $children : array(),
            'grandparent' => $grandparent,
            'position'    => implode(' | ', $position_parts),
        );
    }

    /**
     * Get all pages that share the same focus keyphrase as the given post (cannibalization detection).
     *
     * @return array List of conflicting pages with their titles, slugs, and keyphrases.
     */
    public function get_keyphrase_conflicts(int $post_id): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $postmeta   = $wpdb->postmeta;

        // Get the current page's focus keyphrase.
        $focus_keyphrase = trim((string) get_post_meta($post_id, '_ai_seo_keeper_focus_keyphrase', true));

        if ('' === $focus_keyphrase) {
            return array();
        }

        // Find all OTHER pages that have the same focus keyphrase (case-insensitive).
        $conflicts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT idx.object_id, idx.title, idx.slug, idx.permalink, idx.post_type, idx.status,
                        pm_kp.meta_value AS focus_keyphrase,
                        COALESCE(pm_title.meta_value, '') AS seo_title,
                        COALESCE(pm_desc.meta_value, '')  AS meta_description
                 FROM {$postmeta} pm_kp
                 INNER JOIN {$table_name} idx ON idx.object_id = pm_kp.post_id AND idx.object_type = %s
                 LEFT JOIN {$postmeta} pm_title ON pm_title.post_id = idx.object_id AND pm_title.meta_key = %s
                 LEFT JOIN {$postmeta} pm_desc  ON pm_desc.post_id  = idx.object_id AND pm_desc.meta_key  = %s
                 WHERE pm_kp.meta_key = '_ai_seo_keeper_focus_keyphrase'
                   AND LOWER(TRIM(pm_kp.meta_value)) = LOWER(%s)
                   AND idx.object_id != %d
                   AND idx.status     = %s
                 ORDER BY idx.title ASC",
                'post',
                self::META_TITLE_KEY,
                self::META_DESCRIPTION_KEY,
                $focus_keyphrase,
                $post_id,
                'publish'
            ),
            ARRAY_A
        );

        return is_array($conflicts) ? $conflicts : array();
    }

    /**
     * Build a compact site tree (title + keyphrase + slug per page) for AI context.
     * For sites with more than $max_pages, only the current branch is included.
     *
     * @return string A compact text tree of the site structure.
     */
    public function get_compact_site_tree(int $current_post_id, int $max_pages = 500): string
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $postmeta   = $wpdb->postmeta;

        // Count published pages to decide full tree vs branch-only.
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE object_type = %s AND status = %s",
                'post',
                'publish'
            )
        );

        if ($total < 1) {
            return 'No published pages found.';
        }

        // Fetch all published pages with their keyphrase.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT idx.object_id, idx.title, idx.slug, idx.parent_id, idx.post_type,
                        COALESCE(pm_kp.meta_value, '') AS focus_keyphrase
                 FROM {$table_name} idx
                 LEFT JOIN {$postmeta} pm_kp ON pm_kp.post_id = idx.object_id AND pm_kp.meta_key = '_ai_seo_keeper_focus_keyphrase'
                 WHERE idx.object_type = %s AND idx.status = %s
                 ORDER BY idx.parent_id ASC, idx.title ASC",
                'post',
                'publish'
            ),
            ARRAY_A
        );

        if (! is_array($rows) || empty($rows)) {
            return 'No published pages found.';
        }

        // Index by object_id and group children by parent_id.
        $by_id       = array();
        $by_parent   = array();

        foreach ($rows as $row) {
            $oid = (int) $row['object_id'];
            $pid = (int) $row['parent_id'];
            $by_id[$oid] = $row;
            $by_parent[$pid][] = $row;
        }

        // For huge sites, limit to current branch only.
        if ($total > $max_pages) {
            return $this->build_branch_tree($by_id, $by_parent, $current_post_id);
        }

        // Full tree: start from root (parent_id = 0).
        $lines = array();
        $this->render_tree_level($by_parent, 0, 0, $current_post_id, $lines);

        return implode("\n", $lines);
    }

    /**
     * Fetch a single page from the content index with its SEO meta fields.
     */
    private function fetch_page_with_seo_meta(string $table_name, string $postmeta_table, int $object_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT idx.object_id, idx.title, idx.slug, idx.permalink, idx.status, idx.parent_id, idx.post_type,
                        COALESCE(pm_kp.meta_value, '')    AS focus_keyphrase,
                        COALESCE(pm_title.meta_value, '') AS seo_title,
                        COALESCE(pm_desc.meta_value, '')  AS meta_description
                 FROM {$table_name} idx
                 LEFT JOIN {$postmeta_table} pm_kp    ON pm_kp.post_id    = idx.object_id AND pm_kp.meta_key    = '_ai_seo_keeper_focus_keyphrase'
                 LEFT JOIN {$postmeta_table} pm_title ON pm_title.post_id = idx.object_id AND pm_title.meta_key = %s
                 LEFT JOIN {$postmeta_table} pm_desc  ON pm_desc.post_id  = idx.object_id AND pm_desc.meta_key  = %s
                 WHERE idx.object_type = %s AND idx.object_id = %d",
                self::META_TITLE_KEY,
                self::META_DESCRIPTION_KEY,
                'post',
                $object_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Build a branch-only tree (ancestors + siblings + children of the current page).
     */
    private function build_branch_tree(array $by_id, array $by_parent, int $current_id): string
    {
        // Collect ancestor chain.
        $ancestors = array();
        $walk_id   = $current_id;

        while (isset($by_id[$walk_id]) && (int) $by_id[$walk_id]['parent_id'] > 0) {
            $walk_id     = (int) $by_id[$walk_id]['parent_id'];
            $ancestors[] = $walk_id;
        }

        $ancestors = array_reverse($ancestors);

        $lines   = array();
        $lines[] = '(Large site — showing current branch only)';

        // Render ancestor chain.
        $depth = 0;
        foreach ($ancestors as $ancestor_id) {
            if (isset($by_id[$ancestor_id])) {
                $r = $by_id[$ancestor_id];
                $lines[] = $this->format_tree_node($r, $depth, $current_id);
            }
            $depth++;
        }

        // Render siblings (same parent as current).
        $parent_id = isset($by_id[$current_id]) ? (int) $by_id[$current_id]['parent_id'] : 0;
        $siblings  = isset($by_parent[$parent_id]) ? $by_parent[$parent_id] : array();

        foreach ($siblings as $sibling) {
            $sid = (int) $sibling['object_id'];
            $lines[] = $this->format_tree_node($sibling, $depth, $current_id);

            // If this is the current page, also render its children.
            if ($sid === $current_id && isset($by_parent[$sid])) {
                foreach ($by_parent[$sid] as $child) {
                    $lines[] = $this->format_tree_node($child, $depth + 1, $current_id);
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Recursively render the tree from a given parent level.
     */
    private function render_tree_level(array $by_parent, int $parent_id, int $depth, int $current_id, array &$lines): void
    {
        if (! isset($by_parent[$parent_id])) {
            return;
        }

        foreach ($by_parent[$parent_id] as $row) {
            $oid     = (int) $row['object_id'];
            $lines[] = $this->format_tree_node($row, $depth, $current_id);

            // Recurse into children.
            $this->render_tree_level($by_parent, $oid, $depth + 1, $current_id, $lines);
        }
    }

    /**
     * Format a single tree node as an indented line.
     */
    private function format_tree_node(array $row, int $depth, int $current_id): string
    {
        $indent    = str_repeat('  ', $depth);
        $marker    = (int) $row['object_id'] === $current_id ? ' ← YOU ARE HERE' : '';
        $keyphrase = trim((string) $row['focus_keyphrase']);
        $kp_label  = '' !== $keyphrase ? ' [kp: "' . $keyphrase . '"]' : '';
        $slug      = '/' . ltrim((string) $row['slug'], '/') . '/';

        return $indent . $slug . ' "' . $row['title'] . '"' . $kp_label . $marker;
    }

    public function sync(): int
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $post_types = get_post_types(
            array(
                'public' => true,
            ),
            'names'
        );

        unset($post_types['attachment']);

        $statuses = array('publish', 'draft', 'private', 'pending', 'future');
        $records  = array();

        foreach ($post_types as $post_type) {
            $items = get_posts(
                array(
                    'post_type'      => $post_type,
                    'post_status'    => $statuses,
                    'posts_per_page' => -1,
                    'orderby'        => 'menu_order title',
                    'order'          => 'ASC',
                )
            );

            foreach ($items as $item) {
                $records[] = array(
                    'object_id'    => (int) $item->ID,
                    'object_type'  => 'post',
                    'post_type'    => (string) $item->post_type,
                    'status'       => (string) $item->post_status,
                    'title'        => (string) $item->post_title,
                    'slug'         => (string) $item->post_name,
                    'permalink'    => (string) get_permalink($item),
                    'parent_id'    => (int) $item->post_parent,
                    'excerpt'      => wp_trim_words(wp_strip_all_tags(Content_Helper::get_content($item)), 120, '...'),
                    'content_hash' => md5((string) $item->post_title . '|' . Content_Helper::get_content($item)),
                    'modified_gmt' => $item->post_modified_gmt,
                    'indexed_at'   => current_time('mysql', true),
                );
            }
        }

        $wpdb->query("TRUNCATE TABLE {$table_name}");

        foreach ($records as $record) {
            $wpdb->insert(
                $table_name,
                $record,
                array(
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                )
            );
        }

        return count($records);
    }
}
