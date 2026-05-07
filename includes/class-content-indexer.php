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
