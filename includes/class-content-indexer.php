<?php

namespace AI_SEO_Captain;

class Content_Indexer
{
    private const META_TITLE_KEY = '_ai_seo_captain_meta_title';

    private const META_DESCRIPTION_KEY = '_ai_seo_captain_meta_description';

    private const SOCIAL_TITLE_META_KEY = '_ai_seo_captain_social_title';

    private const SOCIAL_DESCRIPTION_META_KEY = '_ai_seo_captain_social_description';

    private const SOCIAL_IMAGE_META_KEY = '_ai_seo_captain_social_image';

    private const CANONICAL_URL_META_KEY = '_ai_seo_captain_canonical_url';

    private const ROBOTS_DIRECTIVES_META_KEY = '_ai_seo_captain_robots_directives';

    private const SCHEMA_TYPE_META_KEY = '_ai_seo_captain_schema_type';

    private const FRONTEND_ENABLE_META_KEY = '_ai_seo_captain_frontend_enabled';

    private const APPROVED_MESSAGE_META_KEY = '_ai_seo_captain_approved_message_id';

    public function get_summary(): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';

        return array(
            'total_items' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"),
            'last_sync'   => $wpdb->get_var("SELECT MAX(indexed_at) FROM {$table_name}"),
        );
    }

    public function get_published_page_count(): int
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
                'publish'
            )
        );
    }

    /**
     * Get all indexed pages as a flat array with parent info for tree building.
     *
     * @return array<int, array{id: int, title: string, slug: string, post_type: string, parent_id: int, permalink: string, status: string}>
     */
    public function get_all_indexed_pages(): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';

        $rows = $wpdb->get_results(
            "SELECT object_id, title, slug, post_type, parent_id, permalink, status
             FROM {$table_name}
             ORDER BY post_type ASC, parent_id ASC, title ASC",
            ARRAY_A
        );

        if (! is_array($rows)) {
            return array();
        }

        $result = array();
        foreach ($rows as $row) {
            $result[] = array(
                'id'        => (int) $row['object_id'],
                'title'     => (string) $row['title'],
                'slug'      => (string) $row['slug'],
                'post_type' => (string) $row['post_type'],
                'parent_id' => (int) $row['parent_id'],
                'permalink' => (string) $row['permalink'],
                'status'    => (string) $row['status'],
            );
        }

        return $result;
    }

    public function get_audit_summary(): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';
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

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';
        $postmeta_table = $wpdb->postmeta;
        $limit = max(1, $limit);

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

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';
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

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';
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

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';
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

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';
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

        $table_name  = $wpdb->prefix . 'ai_seo_captain_content_index';
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

        // Fetch siblings (same parent, same post_type, excluding current). Capped at 20; keyphrase-bearing pages first.
        $siblings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT idx.object_id, idx.title, idx.slug, idx.permalink, idx.status, idx.parent_id,
                        COALESCE(pm_kp.meta_value, '')    AS focus_keyphrase,
                        COALESCE(pm_title.meta_value, '') AS seo_title,
                        COALESCE(pm_desc.meta_value, '')  AS meta_description
                 FROM {$table_name} idx
                 LEFT JOIN {$postmeta} pm_kp    ON pm_kp.post_id    = idx.object_id AND pm_kp.meta_key    = '_ai_seo_captain_focus_keyphrase'
                 LEFT JOIN {$postmeta} pm_title ON pm_title.post_id = idx.object_id AND pm_title.meta_key = %s
                 LEFT JOIN {$postmeta} pm_desc  ON pm_desc.post_id  = idx.object_id AND pm_desc.meta_key  = %s
                 WHERE idx.object_type = %s
                   AND idx.post_type   = %s
                   AND idx.parent_id   = %d
                   AND idx.object_id  != %d
                   AND idx.status      = %s
                 ORDER BY CASE WHEN COALESCE(pm_kp.meta_value, '') != '' THEN 0 ELSE 1 END, idx.title ASC
                 LIMIT 20",
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

        // Fetch children of the current page. Capped at 20; keyphrase-bearing pages first.
        $children = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT idx.object_id, idx.title, idx.slug, idx.permalink, idx.status, idx.parent_id,
                        COALESCE(pm_kp.meta_value, '')    AS focus_keyphrase,
                        COALESCE(pm_title.meta_value, '') AS seo_title,
                        COALESCE(pm_desc.meta_value, '')  AS meta_description
                 FROM {$table_name} idx
                 LEFT JOIN {$postmeta} pm_kp    ON pm_kp.post_id    = idx.object_id AND pm_kp.meta_key    = '_ai_seo_captain_focus_keyphrase'
                 LEFT JOIN {$postmeta} pm_title ON pm_title.post_id = idx.object_id AND pm_title.meta_key = %s
                 LEFT JOIN {$postmeta} pm_desc  ON pm_desc.post_id  = idx.object_id AND pm_desc.meta_key  = %s
                 WHERE idx.object_type = %s
                   AND idx.post_type   = %s
                   AND idx.parent_id   = %d
                   AND idx.status      = %s
                 ORDER BY CASE WHEN COALESCE(pm_kp.meta_value, '') != '' THEN 0 ELSE 1 END, idx.title ASC
                 LIMIT 20",
                self::META_TITLE_KEY,
                self::META_DESCRIPTION_KEY,
                'post',
                $post_type,
                $post_id,
                'publish'
            ),
            ARRAY_A
        );

        // Build a human-readable position string. Use actual total counts (queries above are capped to 20).
        $total_sibling_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name}
                 WHERE object_type = %s AND post_type = %s AND parent_id = %d AND object_id != %d AND status = %s",
                'post',
                $post_type,
                $parent_id,
                $post_id,
                'publish'
            )
        );
        $total_child_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name}
                 WHERE object_type = %s AND post_type = %s AND parent_id = %d AND status = %s",
                'post',
                $post_type,
                $post_id,
                'publish'
            )
        );
        $sibling_count = is_array($siblings) ? count($siblings) : 0;
        $child_count   = is_array($children) ? count($children) : 0;

        $position_parts = array();
        if (is_array($parent)) {
            $position_parts[] = 'Child of "' . $parent['title'] . '"';
            $level_label = ($total_sibling_count + 1) . ' page(s) at this level';
            if ($total_sibling_count > $sibling_count) {
                $level_label .= ' (showing top ' . $sibling_count . ')';
            }
            $position_parts[] = $level_label;
        } else {
            $position_parts[] = 'Top-level page';
        }
        if ($total_child_count > 0) {
            $child_label = $total_child_count . ' child page(s) below';
            if ($total_child_count > $child_count) {
                $child_label .= ' (showing top ' . $child_count . ')';
            }
            $position_parts[] = $child_label;
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

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';
        $postmeta   = $wpdb->postmeta;

        // Get the current page's focus keyphrase.
        $focus_keyphrase = trim((string) get_post_meta($post_id, '_ai_seo_captain_focus_keyphrase', true));

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
                 WHERE pm_kp.meta_key = '_ai_seo_captain_focus_keyphrase'
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
     * Find pages that are topically related to the given post based on keyword overlap
     * in titles, keyphrases, and meta descriptions — regardless of hierarchy.
     *
     * @param int   $post_id     The current post ID.
     * @param array $exclude_ids Post IDs to exclude (e.g., siblings already included).
     * @param bool  $deep        When true, also fetch a truncated excerpt of each page's body content.
     * @param int   $limit       Maximum pages to return.
     * @return array List of topically related pages with metadata (and optionally content excerpt).
     */
    public function get_topically_related_pages(int $post_id, array $exclude_ids = array(), bool $deep = false, int $limit = 10): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';
        $postmeta   = $wpdb->postmeta;

        // Collect keyword sources: page title, focus keyphrase, meta description.
        $post_title       = (string) get_the_title($post_id);
        $focus_keyphrase  = trim((string) get_post_meta($post_id, '_ai_seo_captain_focus_keyphrase', true));
        $meta_description = trim((string) get_post_meta($post_id, self::META_DESCRIPTION_KEY, true));

        // Extract keywords: split all sources into words, filter short/stop words.
        $raw_text = $post_title . ' ' . $focus_keyphrase . ' ' . $meta_description;
        $words    = preg_split('/[\s\-_\/\|,;:\.!?\(\)\[\]]+/', strtolower($raw_text), -1, PREG_SPLIT_NO_EMPTY);
        $words    = array_unique($words);

        // Remove common stop words and very short words.
        $stop_words = array(
            'the',
            'a',
            'an',
            'and',
            'or',
            'but',
            'is',
            'in',
            'on',
            'at',
            'to',
            'for',
            'of',
            'with',
            'by',
            'from',
            'as',
            'into',
            'that',
            'this',
            'it',
            'are',
            'was',
            'were',
            'be',
            'been',
            'has',
            'have',
            'had',
            'do',
            'does',
            'did',
            'will',
            'would',
            'could',
            'should',
            'may',
            'might',
            'can',
            'not',
            'no',
            'so',
            'if',
            'then',
            'than',
            'too',
            'very',
            'just',
            'about',
            'up',
            'out',
            'our',
            'your',
            'my',
            'we',
            'you',
            'he',
            'she',
            'they',
            'its',
            'his',
            'her',
            'their',
            'all',
            'each',
            'how',
            'what',
            'which',
            'who',
            'when',
            'where',
            'why',
            'any',
            'some',
            'more',
        );

        $keywords = array();
        foreach ($words as $word) {
            if (strlen($word) >= 3 && ! in_array($word, $stop_words, true)) {
                $keywords[] = $word;
            }
        }

        if (empty($keywords)) {
            return array();
        }

        // Keep the keyphrase as a whole phrase for matching too.
        $phrases = array();
        if ('' !== $focus_keyphrase) {
            $phrases[] = strtolower($focus_keyphrase);
        }

        // Build exclusion list.
        $exclude_ids[] = $post_id;
        $exclude_ids   = array_unique(array_filter(array_map('intval', $exclude_ids)));
        $exclude_in    = implode(',', $exclude_ids);

        // Build LIKE conditions: match any keyword in title, keyphrase, or SEO description.
        $like_conditions = array();
        $like_params     = array();

        // Prioritize full keyphrase matching, then individual keywords (take top keywords only).
        $search_terms = array_merge($phrases, array_slice($keywords, 0, 8));
        $search_terms = array_unique($search_terms);

        foreach ($search_terms as $term) {
            $escaped = '%' . $wpdb->esc_like($term) . '%';
            $like_conditions[] = 'LOWER(idx.title) LIKE %s';
            $like_params[]     = $escaped;
            $like_conditions[] = 'LOWER(COALESCE(pm_kp.meta_value, \'\')) LIKE %s';
            $like_params[]     = $escaped;
            $like_conditions[] = 'LOWER(COALESCE(pm_desc.meta_value, \'\')) LIKE %s';
            $like_params[]     = $escaped;
        }

        if (empty($like_conditions)) {
            return array();
        }

        $where_likes = '(' . implode(' OR ', $like_conditions) . ')';

        // Build a relevance score: count how many search terms match.
        $score_parts  = array();
        $score_params = array();
        foreach ($search_terms as $term) {
            $escaped = '%' . $wpdb->esc_like($term) . '%';
            $score_parts[]  = 'CASE WHEN LOWER(idx.title) LIKE %s THEN 2 ELSE 0 END';
            $score_params[] = $escaped;
            $score_parts[]  = 'CASE WHEN LOWER(COALESCE(pm_kp.meta_value, \'\')) LIKE %s THEN 3 ELSE 0 END';
            $score_params[] = $escaped;
            $score_parts[]  = 'CASE WHEN LOWER(COALESCE(pm_desc.meta_value, \'\')) LIKE %s THEN 1 ELSE 0 END';
            $score_params[] = $escaped;
        }

        $score_expr = '(' . implode(' + ', $score_parts) . ')';

        $sql = "SELECT idx.object_id, idx.title, idx.slug, idx.permalink, idx.post_type, idx.status, idx.parent_id,
                       COALESCE(pm_kp.meta_value, '')    AS focus_keyphrase,
                       COALESCE(pm_title.meta_value, '') AS seo_title,
                       COALESCE(pm_desc.meta_value, '')  AS meta_description,
                       {$score_expr} AS relevance_score
                FROM {$table_name} idx
                LEFT JOIN {$postmeta} pm_kp    ON pm_kp.post_id    = idx.object_id AND pm_kp.meta_key = '_ai_seo_captain_focus_keyphrase'
                LEFT JOIN {$postmeta} pm_title ON pm_title.post_id = idx.object_id AND pm_title.meta_key = %s
                LEFT JOIN {$postmeta} pm_desc  ON pm_desc.post_id  = idx.object_id AND pm_desc.meta_key  = %s
                WHERE idx.object_type = %s
                  AND idx.status      = %s
                  AND idx.object_id NOT IN ({$exclude_in})
                  AND {$where_likes}
                ORDER BY relevance_score DESC, idx.title ASC
                LIMIT %d";

        $all_params = array_merge($score_params, array(self::META_TITLE_KEY, self::META_DESCRIPTION_KEY, 'post', 'publish'), $like_params, array($limit));

        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $all_params), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            ARRAY_A
        );

        if (! is_array($results)) {
            return array();
        }

        // If deep mode, fetch truncated body content for each matched page.
        if ($deep && ! empty($results)) {
            foreach ($results as &$row) {
                $related_post = get_post((int) $row['object_id']);
                if ($related_post instanceof \WP_Post) {
                    $full_content    = Content_Helper::get_content($related_post);
                    $row['excerpt_content'] = $this->truncate_text($full_content, 300);
                } else {
                    $row['excerpt_content'] = '';
                }
            }
            unset($row);
        }

        return $results;
    }

    /**
     * Truncate text to a maximum character length, breaking at word boundary.
     */
    private function truncate_text(string $text, int $max_chars): string
    {
        $text = trim(wp_strip_all_tags($text));
        if (mb_strlen($text) <= $max_chars) {
            return $text;
        }
        $truncated = mb_substr($text, 0, $max_chars);
        $last_space = strrpos($truncated, ' ');
        if (false !== $last_space && $last_space > ($max_chars * 0.7)) {
            $truncated = substr($truncated, 0, $last_space);
        }
        return $truncated . '…';
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

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';
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

        // Fetch all published pages with their keyphrase and SEO meta.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT idx.object_id, idx.title, idx.slug, idx.parent_id, idx.post_type,
                        COALESCE(pm_kp.meta_value, '') AS focus_keyphrase,
                        COALESCE(pm_title.meta_value, '') AS seo_title,
                        COALESCE(pm_desc.meta_value, '') AS seo_description
                 FROM {$table_name} idx
                 LEFT JOIN {$postmeta} pm_kp ON pm_kp.post_id = idx.object_id AND pm_kp.meta_key = '_ai_seo_captain_focus_keyphrase'
                 LEFT JOIN {$postmeta} pm_title ON pm_title.post_id = idx.object_id AND pm_title.meta_key = %s
                 LEFT JOIN {$postmeta} pm_desc ON pm_desc.post_id = idx.object_id AND pm_desc.meta_key = %s
                 WHERE idx.object_type = %s AND idx.status = %s
                 ORDER BY idx.parent_id ASC, idx.title ASC",
                self::META_TITLE_KEY,
                self::META_DESCRIPTION_KEY,
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
                 LEFT JOIN {$postmeta_table} pm_kp    ON pm_kp.post_id    = idx.object_id AND pm_kp.meta_key    = '_ai_seo_captain_focus_keyphrase'
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

        $line = $indent . $slug . ' "' . $row['title'] . '"' . $kp_label . $marker;

        // Append SEO meta title and description when available.
        $seo_title = trim((string) ($row['seo_title'] ?? ''));
        $seo_desc  = trim((string) ($row['seo_description'] ?? ''));

        if ('' !== $seo_title) {
            $line .= "\n" . $indent . '  SEO title: "' . $seo_title . '"';
        }
        if ('' !== $seo_desc) {
            $line .= "\n" . $indent . '  SEO desc: "' . $seo_desc . '"';
        }

        return $line;
    }

    public function sync(): int
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';
        $post_types = get_post_types(
            array(
                'public' => true,
            ),
            'names'
        );

        unset($post_types['attachment']);

        $statuses = array('publish', 'draft', 'private', 'pending', 'future');
        $total_count = 0;
        $batch_size = 100;

        $wpdb->query('START TRANSACTION');
        $wpdb->query("DELETE FROM {$table_name}");

        foreach ($post_types as $post_type) {
            $page = 1;

            do {
                $items = get_posts(
                    array(
                        'post_type'      => $post_type,
                        'post_status'    => $statuses,
                        'posts_per_page' => $batch_size,
                        'paged'          => $page,
                        'orderby'        => 'menu_order title',
                        'order'          => 'ASC',
                    )
                );

                foreach ($items as $item) {
                    $wpdb->insert(
                        $table_name,
                        array(
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
                        ),
                        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
                    );
                    ++$total_count;
                }

                // Free memory between batches.
                wp_cache_flush();
                ++$page;
            } while (count($items) === $batch_size);
        }

        $wpdb->query('COMMIT');

        return $total_count;
    }

    /**
     * Upsert a single post into the content index.
     *
     * Called automatically on save_post / untrashed_post hooks.
     * Uses REPLACE INTO (atomic upsert) on the UNIQUE KEY (object_type, object_id).
     */
    public function upsert_post(int $post_id): bool
    {
        global $wpdb;

        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            return false;
        }

        if (! $this->is_indexable_post($post)) {
            // Post is not indexable (wrong type, attachment, etc.) — remove if present.
            $this->delete_from_index($post_id);
            return false;
        }

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';

        $result = $wpdb->replace(
            $table_name,
            array(
                'object_id'    => (int) $post->ID,
                'object_type'  => 'post',
                'post_type'    => (string) $post->post_type,
                'status'       => (string) $post->post_status,
                'title'        => (string) $post->post_title,
                'slug'         => (string) $post->post_name,
                'permalink'    => (string) get_permalink($post),
                'parent_id'    => (int) $post->post_parent,
                'excerpt'      => wp_trim_words(wp_strip_all_tags(Content_Helper::get_content($post)), 120, '...'),
                'content_hash' => md5((string) $post->post_title . '|' . Content_Helper::get_content($post)),
                'modified_gmt' => $post->post_modified_gmt,
                'indexed_at'   => current_time('mysql', true),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );

        return false !== $result;
    }

    /**
     * Remove a single post from the content index.
     *
     * Called automatically on delete_post / trashed_post hooks.
     */
    public function delete_from_index(int $post_id): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';

        $deleted = $wpdb->delete(
            $table_name,
            array(
                'object_id'   => $post_id,
                'object_type' => 'post',
            ),
            array('%d', '%s')
        );

        return false !== $deleted;
    }

    /**
     * Verify index integrity: remove orphaned entries, add missing posts.
     *
     * Used by the daily cron health check and available for manual repair.
     *
     * @return array{removed: int, added: int, total: int}
     */
    public function verify_index_integrity(): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_captain_content_index';
        $removed = 0;
        $added   = 0;

        // 1. Remove entries for posts that no longer exist or are not indexable.
        // Use a LEFT JOIN to find orphaned index entries in a single query.
        $post_types = get_post_types(array('public' => true), 'names');
        unset($post_types['attachment']);
        $statuses = array('publish', 'draft', 'private', 'pending', 'future');

        $type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));

        // Find indexed IDs that either don't exist in wp_posts or have wrong type/status.
        $orphan_query = $wpdb->prepare(
            "SELECT ci.object_id FROM {$table_name} ci
             LEFT JOIN {$wpdb->posts} p ON p.ID = ci.object_id
             WHERE ci.object_type = 'post'
             AND (p.ID IS NULL OR p.post_type NOT IN ({$type_placeholders}) OR p.post_status NOT IN ({$status_placeholders}))",
            array_merge(array_values($post_types), $statuses)
        );

        $orphan_ids = $wpdb->get_col($orphan_query);
        if (! empty($orphan_ids)) {
            $id_list = implode(',', array_map('intval', $orphan_ids));
            $wpdb->query("DELETE FROM {$table_name} WHERE object_type = 'post' AND object_id IN ({$id_list})");
            $removed = count($orphan_ids);
        }

        // 2. Add entries for posts that are missing from the index (single query).
        $missing_query = $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             LEFT JOIN {$table_name} ci ON ci.object_id = p.ID AND ci.object_type = 'post'
             WHERE p.post_type IN ({$type_placeholders})
             AND p.post_status IN ({$status_placeholders})
             AND ci.object_id IS NULL",
            array_merge(array_values($post_types), $statuses)
        );

        $missing_ids = $wpdb->get_col($missing_query);
        if (! empty($missing_ids)) {
            foreach ($missing_ids as $pid) {
                $this->upsert_post((int) $pid);
                ++$added;
            }
        }

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        return array(
            'removed' => $removed,
            'added'   => $added,
            'total'   => $total,
        );
    }

    /**
     * Check if a post is eligible for the content index.
     */
    private function is_indexable_post(\WP_Post $post): bool
    {
        // Must be a public post type (excludes attachment).
        $public_types = get_post_types(array('public' => true), 'names');
        unset($public_types['attachment']);

        if (! isset($public_types[$post->post_type])) {
            return false;
        }

        // Must be in an indexable status.
        $allowed_statuses = array('publish', 'draft', 'private', 'pending', 'future');
        if (! in_array($post->post_status, $allowed_statuses, true)) {
            return false;
        }

        return true;
    }
}
