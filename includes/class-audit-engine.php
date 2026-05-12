<?php

namespace AI_SEO_Keeper;

class Audit_Engine
{
    private const META_TITLE_KEY = '_ai_seo_keeper_meta_title';

    private const META_DESCRIPTION_KEY = '_ai_seo_keeper_meta_description';

    private const FRONTEND_ENABLE_META_KEY = '_ai_seo_keeper_frontend_enabled';

    private const APPROVED_MESSAGE_META_KEY = '_ai_seo_keeper_approved_message_id';

    private Content_Indexer $content_indexer;

    public function __construct(Content_Indexer $content_indexer)
    {
        $this->content_indexer = $content_indexer;
    }

    public function get_report(int $priorityLimit = 12): array
    {
        $summary = $this->content_indexer->get_audit_summary();

        return array(
            'summary' => $summary,
            'readiness' => $this->build_readiness($summary),
            'priority_rows' => $this->content_indexer->get_audit_rows($priorityLimit),
            'rollout_candidates' => $this->get_rollout_candidates($priorityLimit),
            'draft_candidates' => $this->get_unapproved_draft_candidates(8),
            'duplicate_post_titles' => $this->get_duplicate_post_titles(6),
            'duplicate_ai_titles' => $this->get_duplicate_ai_titles(6),
            'thin_content_rows' => $this->get_thin_content_rows(8),
            'orphaned_content' => $this->get_orphaned_content(12),
        );
    }

    private function build_readiness(array $summary): array
    {
        $published = max(0, (int) ($summary['published_items'] ?? 0));

        if (0 === $published) {
            return array(
                'score' => 0,
                'label' => 'Not started',
                'draft_coverage' => 0,
                'approval_coverage' => 0,
                'frontend_coverage' => 0,
            );
        }

        $title_coverage = max(0, $published - (int) ($summary['missing_title_drafts'] ?? 0)) / $published;
        $description_coverage = max(0, $published - (int) ($summary['missing_description_drafts'] ?? 0)) / $published;
        $draft_coverage = ($title_coverage + $description_coverage) / 2;
        $approval_coverage = min(1, max(0, (int) ($summary['approved_items'] ?? 0) / $published));
        $frontend_coverage = min(1, max(0, (int) ($summary['frontend_ready_items'] ?? 0) / $published));

        $score = (int) round(($draft_coverage * 0.5 + $approval_coverage * 0.3 + $frontend_coverage * 0.2) * 100);

        if ($score >= 80) {
            $label = 'Strong';
        } elseif ($score >= 55) {
            $label = 'Building';
        } elseif ($score >= 30) {
            $label = 'Early';
        } else {
            $label = 'Starting';
        }

        return array(
            'score' => $score,
            'label' => $label,
            'draft_coverage' => (int) round($draft_coverage * 100),
            'approval_coverage' => (int) round($approval_coverage * 100),
            'frontend_coverage' => (int) round($frontend_coverage * 100),
        );
    }

    private function get_duplicate_post_titles(int $limit): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $limit = max(1, $limit);

        $sql = $wpdb->prepare(
            "SELECT MIN(title) AS label, GROUP_CONCAT(object_id ORDER BY object_id ASC) AS ids, COUNT(*) AS total_count
            FROM {$table_name}
            WHERE object_type = %s
                AND status = %s
                AND TRIM(title) <> ''
            GROUP BY LOWER(TRIM(title))
            HAVING COUNT(*) > 1
            ORDER BY total_count DESC, label ASC
            LIMIT %d",
            'post',
            'publish',
            $limit
        );

        return $this->map_grouped_rows($wpdb->get_results($sql, ARRAY_A));
    }

    private function get_duplicate_ai_titles(int $limit): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $postmeta_table = $wpdb->postmeta;
        $limit = max(1, $limit);

        $sql = $wpdb->prepare(
            "SELECT MIN(pm_title.meta_value) AS label, GROUP_CONCAT(idx.object_id ORDER BY idx.object_id ASC) AS ids, COUNT(*) AS total_count
            FROM {$table_name} idx
            INNER JOIN {$postmeta_table} pm_title ON pm_title.post_id = idx.object_id AND pm_title.meta_key = %s
            WHERE idx.object_type = %s
                AND idx.status = %s
                AND TRIM(pm_title.meta_value) <> ''
            GROUP BY LOWER(TRIM(pm_title.meta_value))
            HAVING COUNT(*) > 1
            ORDER BY total_count DESC, label ASC
            LIMIT %d",
            self::META_TITLE_KEY,
            'post',
            'publish',
            $limit
        );

        return $this->map_grouped_rows($wpdb->get_results($sql, ARRAY_A));
    }

    private function get_unapproved_draft_candidates(int $limit): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $postmeta_table = $wpdb->postmeta;
        $limit = max(1, $limit);

        $sql = $wpdb->prepare(
            "SELECT
                idx.object_id,
                idx.title,
                idx.permalink,
                CASE WHEN COALESCE(pm_title.meta_value, '') = '' THEN 0 ELSE 1 END AS has_title_draft,
                CASE WHEN COALESCE(pm_description.meta_value, '') = '' THEN 0 ELSE 1 END AS has_description_draft,
                CASE WHEN COALESCE(pm_frontend.meta_value, '') = '1' THEN 1 ELSE 0 END AS frontend_enabled
            FROM {$table_name} idx
            LEFT JOIN {$postmeta_table} pm_title ON pm_title.post_id = idx.object_id AND pm_title.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_description ON pm_description.post_id = idx.object_id AND pm_description.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_approved ON pm_approved.post_id = idx.object_id AND pm_approved.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_frontend ON pm_frontend.post_id = idx.object_id AND pm_frontend.meta_key = %s
            WHERE idx.object_type = %s
                AND idx.status = %s
                AND (COALESCE(pm_title.meta_value, '') <> '' OR COALESCE(pm_description.meta_value, '') <> '')
                AND CAST(COALESCE(pm_approved.meta_value, '0') AS UNSIGNED) = 0
            ORDER BY frontend_enabled DESC, idx.modified_gmt DESC
            LIMIT %d",
            self::META_TITLE_KEY,
            self::META_DESCRIPTION_KEY,
            self::APPROVED_MESSAGE_META_KEY,
            self::FRONTEND_ENABLE_META_KEY,
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
                    'title' => (string) $row['title'],
                    'permalink' => (string) $row['permalink'],
                    'has_title_draft' => ! empty($row['has_title_draft']),
                    'has_description_draft' => ! empty($row['has_description_draft']),
                    'frontend_enabled' => ! empty($row['frontend_enabled']),
                );
            },
            $rows
        );
    }

    private function get_rollout_candidates(int $limit): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $postmeta_table = $wpdb->postmeta;
        $limit = max(1, $limit);

        $sql = $wpdb->prepare(
            "SELECT
                idx.object_id,
                idx.title,
                idx.permalink,
                idx.post_type,
                idx.status,
                CASE WHEN COALESCE(pm_frontend.meta_value, '') = '1' THEN 1 ELSE 0 END AS frontend_enabled
            FROM {$table_name} idx
            INNER JOIN {$postmeta_table} pm_approved ON pm_approved.post_id = idx.object_id AND pm_approved.meta_key = %s
            LEFT JOIN {$postmeta_table} pm_frontend ON pm_frontend.post_id = idx.object_id AND pm_frontend.meta_key = %s
            WHERE idx.object_type = %s
                AND idx.status = %s
                AND CAST(COALESCE(pm_approved.meta_value, '0') AS UNSIGNED) > 0
            ORDER BY frontend_enabled ASC, idx.modified_gmt DESC, idx.title ASC
            LIMIT %d",
            self::APPROVED_MESSAGE_META_KEY,
            self::FRONTEND_ENABLE_META_KEY,
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
                    'title' => (string) $row['title'],
                    'permalink' => (string) $row['permalink'],
                    'post_type' => (string) $row['post_type'],
                    'status' => (string) $row['status'],
                    'frontend_enabled' => ! empty($row['frontend_enabled']),
                );
            },
            $rows
        );
    }

    private function get_thin_content_rows(int $limit): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $limit = max(1, $limit);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT object_id, title, permalink, post_type
                FROM {$table_name}
                WHERE object_type = %s
                    AND status = %s
                ORDER BY modified_gmt DESC",
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
            $post = get_post((int) $row['object_id']);

            if (! $post instanceof \WP_Post) {
                continue;
            }

            $content = trim(wp_strip_all_tags(Content_Helper::get_content($post)));
            $word_count = '' === $content ? 0 : count(preg_split('/\s+/', $content));

            if ($word_count > 140) {
                continue;
            }

            $thin_rows[] = array(
                'object_id' => (int) $row['object_id'],
                'title' => (string) $row['title'],
                'permalink' => (string) $row['permalink'],
                'post_type' => (string) $row['post_type'],
                'word_count' => $word_count,
            );
        }

        usort(
            $thin_rows,
            static function (array $left, array $right): int {
                if ($left['word_count'] === $right['word_count']) {
                    return strcmp($left['title'], $right['title']);
                }

                return $left['word_count'] <=> $right['word_count'];
            }
        );

        return array_slice($thin_rows, 0, $limit);
    }

    private function map_grouped_rows(array $rows): array
    {
        if (! is_array($rows)) {
            return array();
        }

        return array_map(
            static function (array $row): array {
                $ids = array_filter(array_map('intval', explode(',', (string) $row['ids'])));

                return array(
                    'label' => (string) $row['label'],
                    'ids' => array_values($ids),
                    'total_count' => (int) $row['total_count'],
                );
            },
            $rows
        );
    }

    /**
     * Build the internal link graph and return orphaned content (pages with zero inbound internal links).
     *
     * @return array{orphans: array, link_graph: array<int, int>}
     */
    public function get_orphaned_content(int $limit = 12): array
    {
        $link_graph = $this->build_internal_link_graph();
        $orphans = array();

        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';

        // Get all published posts from the index.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $all_posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT object_id, title, permalink, post_type
                FROM {$table_name}
                WHERE object_type = %s AND status = %s
                ORDER BY title ASC",
                'post',
                'publish'
            ),
            ARRAY_A
        );

        if (! is_array($all_posts)) {
            return array('orphans' => array(), 'total_orphans' => 0, 'total_pages' => 0);
        }

        // Homepage is never orphaned.
        $home_id = (int) get_option('page_on_front', 0);
        $blog_id = (int) get_option('page_for_posts', 0);

        foreach ($all_posts as $row) {
            $post_id = (int) $row['object_id'];

            // Skip home page / blog page — these are linked from the site structure itself.
            if ($post_id === $home_id || $post_id === $blog_id) {
                continue;
            }

            $inbound_count = $link_graph[$post_id] ?? 0;

            if (0 === $inbound_count) {
                $orphans[] = array(
                    'object_id' => $post_id,
                    'title' => (string) $row['title'],
                    'permalink' => (string) $row['permalink'],
                    'post_type' => (string) $row['post_type'],
                    'inbound_links' => 0,
                );
            }
        }

        $total_orphans = count($orphans);

        return array(
            'orphans' => array_slice($orphans, 0, $limit),
            'total_orphans' => $total_orphans,
            'total_pages' => count($all_posts),
        );
    }

    /**
     * Build a map of post_id → inbound internal link count by scanning all published content.
     *
     * @return array<int, int>  Map of target_post_id → count of inbound links.
     */
    public function build_internal_link_graph(): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_keeper_content_index';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $posts = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT object_id FROM {$table_name} WHERE object_type = %s AND status = %s",
                'post',
                'publish'
            )
        );

        if (! is_array($posts) || empty($posts)) {
            return array();
        }

        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $site_path = rtrim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
        $inbound_map = array(); // target_id → count

        foreach ($posts as $source_id) {
            $source_id = (int) $source_id;
            $post = get_post($source_id);

            if (! $post instanceof \WP_Post) {
                continue;
            }

            $content = Content_Helper::get_content($post);

            // Extract all href values from links.
            if (! preg_match_all('/<a\s[^>]*href=("|\')(.*?)\1/is', $content, $matches)) {
                continue;
            }

            foreach ($matches[2] as $href) {
                $href = trim($href);

                if ('' === $href || '#' === $href[0]) {
                    continue;
                }

                // Resolve relative URLs.
                if ('/' === $href[0]) {
                    $href = home_url($href);
                }

                $parsed = wp_parse_url($href);

                if (! is_array($parsed) || empty($parsed['host'])) {
                    continue;
                }

                // Only internal links.
                if (strtolower($parsed['host']) !== strtolower((string) $site_host)) {
                    continue;
                }

                // url_to_postid is expensive but accurate.
                $target_id = url_to_postid($href);

                if ($target_id > 0 && $target_id !== $source_id) {
                    if (! isset($inbound_map[$target_id])) {
                        $inbound_map[$target_id] = 0;
                    }
                    $inbound_map[$target_id]++;
                }
            }
        }

        return $inbound_map;
    }
}
