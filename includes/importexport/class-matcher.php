<?php

namespace AI_SEO_Captain\ImportExport;

/**
 * Matches exported posts/terms to local posts/terms using slug+type priority.
 */
class Matcher
{
    /** Match confidence levels. */
    public const MATCH_STRONG  = 'strong';
    public const MATCH_FUZZY   = 'fuzzy';
    public const MATCH_NONE    = 'none';

    /**
     * Match exported posts to local posts.
     *
     * @param array $export_posts Array of exported post entries with match_key.
     * @return array {strong: [], fuzzy: [], orphaned: []}
     */
    public function match_posts(array $export_posts): array
    {
        $result = array(
            'strong'   => array(),
            'fuzzy'    => array(),
            'orphaned' => array(),
        );

        if (empty($export_posts)) {
            return $result;
        }

        // Build lookup maps for local posts.
        $local_by_slug  = $this->build_local_post_slug_map();
        $local_by_title = $this->build_local_post_title_map();

        foreach ($export_posts as $index => $entry) {
            $key       = $entry['match_key'] ?? array();
            $slug      = $key['slug'] ?? '';
            $post_type = $key['post_type'] ?? '';
            $title     = $key['title'] ?? '';
            $lookup    = $post_type . '::' . $slug;

            // Priority 1: slug + post_type → strong match.
            if ('' !== $slug && '' !== $post_type && isset($local_by_slug[$lookup])) {
                $local = $local_by_slug[$lookup];
                $result['strong'][] = array(
                    'export_index' => $index,
                    'export_slug'  => $slug,
                    'export_type'  => $post_type,
                    'export_title' => $title,
                    'local_id'     => $local['ID'],
                    'local_slug'   => $local['post_name'],
                    'local_title'  => $local['post_title'],
                    'confidence'   => self::MATCH_STRONG,
                );
                continue;
            }

            // Priority 2: title + post_type → fuzzy match.
            $title_lookup = $post_type . '::' . mb_strtolower($title);
            if ('' !== $title && '' !== $post_type && isset($local_by_title[$title_lookup])) {
                $local = $local_by_title[$title_lookup];
                $result['fuzzy'][] = array(
                    'export_index' => $index,
                    'export_slug'  => $slug,
                    'export_type'  => $post_type,
                    'export_title' => $title,
                    'local_id'     => $local['ID'],
                    'local_slug'   => $local['post_name'],
                    'local_title'  => $local['post_title'],
                    'confidence'   => self::MATCH_FUZZY,
                );
                continue;
            }

            // No match.
            $result['orphaned'][] = array(
                'export_index' => $index,
                'export_slug'  => $slug,
                'export_type'  => $post_type,
                'export_title' => $title,
                'confidence'   => self::MATCH_NONE,
            );
        }

        return $result;
    }

    /**
     * Match exported terms to local terms.
     *
     * @param array $export_terms Array of exported term entries with match_key.
     * @return array {strong: [], fuzzy: [], orphaned: []}
     */
    public function match_terms(array $export_terms): array
    {
        $result = array(
            'strong'   => array(),
            'fuzzy'    => array(),
            'orphaned' => array(),
        );

        if (empty($export_terms)) {
            return $result;
        }

        $local_by_slug = $this->build_local_term_slug_map();
        $local_by_name = $this->build_local_term_name_map();

        foreach ($export_terms as $index => $entry) {
            $key      = $entry['match_key'] ?? array();
            $slug     = $key['slug'] ?? '';
            $taxonomy = $key['taxonomy'] ?? '';
            $name     = $key['name'] ?? '';
            $lookup   = $taxonomy . '::' . $slug;

            // Priority 1: slug + taxonomy → strong.
            if ('' !== $slug && '' !== $taxonomy && isset($local_by_slug[$lookup])) {
                $local = $local_by_slug[$lookup];
                $result['strong'][] = array(
                    'export_index'    => $index,
                    'export_slug'     => $slug,
                    'export_taxonomy' => $taxonomy,
                    'export_name'     => $name,
                    'local_term_id'   => $local['term_id'],
                    'local_slug'      => $local['slug'],
                    'local_name'      => $local['name'],
                    'confidence'      => self::MATCH_STRONG,
                );
                continue;
            }

            // Priority 2: name + taxonomy → fuzzy.
            $name_lookup = $taxonomy . '::' . mb_strtolower($name);
            if ('' !== $name && '' !== $taxonomy && isset($local_by_name[$name_lookup])) {
                $local = $local_by_name[$name_lookup];
                $result['fuzzy'][] = array(
                    'export_index'    => $index,
                    'export_slug'     => $slug,
                    'export_taxonomy' => $taxonomy,
                    'export_name'     => $name,
                    'local_term_id'   => $local['term_id'],
                    'local_slug'      => $local['slug'],
                    'local_name'      => $local['name'],
                    'confidence'      => self::MATCH_FUZZY,
                );
                continue;
            }

            $result['orphaned'][] = array(
                'export_index'    => $index,
                'export_slug'     => $slug,
                'export_taxonomy' => $taxonomy,
                'export_name'     => $name,
                'confidence'      => self::MATCH_NONE,
            );
        }

        return $result;
    }

    // ------------------------------------------------------------------
    //  Local data maps
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{ID: int, post_name: string, post_title: string}>  Keyed by "post_type::slug".
     */
    private function build_local_post_slug_map(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT ID, post_name, post_title, post_type
             FROM {$wpdb->posts}
             WHERE post_status IN ('publish','draft','pending','private')
               AND post_type NOT IN ('revision','nav_menu_item','wp_template','wp_template_part','wp_navigation','wp_global_styles')
             ORDER BY ID ASC",
            ARRAY_A
        );

        $map = array();
        foreach ($rows as $row) {
            $key = $row['post_type'] . '::' . $row['post_name'];
            if (! isset($map[$key])) {
                $map[$key] = $row;
            }
        }

        return $map;
    }

    /**
     * @return array<string, array{ID: int, post_name: string, post_title: string}>  Keyed by "post_type::lowercase_title".
     */
    private function build_local_post_title_map(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT ID, post_name, post_title, post_type
             FROM {$wpdb->posts}
             WHERE post_status IN ('publish','draft','pending','private')
               AND post_type NOT IN ('revision','nav_menu_item','wp_template','wp_template_part','wp_navigation','wp_global_styles')
             ORDER BY ID ASC",
            ARRAY_A
        );

        $map = array();
        foreach ($rows as $row) {
            $key = $row['post_type'] . '::' . mb_strtolower($row['post_title']);
            if (! isset($map[$key])) {
                $map[$key] = $row;
            }
        }

        return $map;
    }

    /**
     * @return array<string, array{term_id: int, slug: string, name: string}>  Keyed by "taxonomy::slug".
     */
    private function build_local_term_slug_map(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT t.term_id, t.slug, t.name, tt.taxonomy
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
             ORDER BY t.term_id ASC",
            ARRAY_A
        );

        $map = array();
        foreach ($rows as $row) {
            $key = $row['taxonomy'] . '::' . $row['slug'];
            if (! isset($map[$key])) {
                $map[$key] = $row;
            }
        }

        return $map;
    }

    /**
     * @return array<string, array{term_id: int, slug: string, name: string}>  Keyed by "taxonomy::lowercase_name".
     */
    private function build_local_term_name_map(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT t.term_id, t.slug, t.name, tt.taxonomy
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
             ORDER BY t.term_id ASC",
            ARRAY_A
        );

        $map = array();
        foreach ($rows as $row) {
            $key = $row['taxonomy'] . '::' . mb_strtolower($row['name']);
            if (! isset($map[$key])) {
                $map[$key] = $row;
            }
        }

        return $map;
    }
}
