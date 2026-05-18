<?php

namespace AI_SEO_Captain;

/**
 * Broken Link & Media Scanner.
 *
 * Phase 1: Zero-cost DB + filesystem checks (no HTTP requests).
 *   - Verifies internal links in post_content resolve to published posts.
 *   - Verifies media files referenced in post_content exist on disk.
 *   - Verifies featured images exist on disk.
 *
 * Phase 2 (optional): Batched HEAD requests for deeper validation.
 *
 * Results are stored in the redirects table (type = 'broken_link').
 */
class Broken_Link_Scanner
{
    /** @var string DB table (shared with Redirects). */
    private string $table;

    /** @var string Option key for scan state/progress. */
    private const STATE_OPTION = 'ai_seo_captain_broken_scan_state';

    /** @var int Max URLs to check per cron tick (Phase 2). */
    private const BATCH_SIZE = 20;

    /** @var int Max posts to process per cron tick (Phase 1). */
    private const POSTS_PER_TICK = 50;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ai_seo_captain_redirects';
    }

    /**
     * Register AJAX handlers.
     */
    public function register_hooks(): void
    {
        add_action('wp_ajax_aisc_broken_scan_start', array($this, 'ajax_scan_start'));
        add_action('wp_ajax_aisc_broken_scan_status', array($this, 'ajax_scan_status'));

        // Cron hook for background scanning.
        add_action('ai_seo_captain_broken_link_scan', array($this, 'cron_scan_tick'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Full Scan (Phase 1) — DB + Filesystem
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Run Phase 1 scan on ALL published content. Returns summary.
     *
     * This is the "Scan Now" path — runs synchronously up to a time limit.
     *
     * @param int $time_limit Max seconds before stopping (0 = unlimited).
     * @return array{broken_media: int, broken_links: int, scanned_posts: int, complete: bool}
     */
    public function run_full_scan(int $time_limit = 30): array
    {
        $start = time();
        $state = $this->get_state();

        // If starting fresh, clear old broken_link entries and reset offset.
        if (empty($state['running'])) {
            $this->clear_broken_links();
            $state = array(
                'running'       => true,
                'offset'        => 0,
                'broken_media'  => 0,
                'broken_links'  => 0,
                'scanned_posts' => 0,
                'total_posts'   => $this->count_published_posts(),
                'started_at'    => current_time('mysql', true),
            );
        }

        global $wpdb;

        while (true) {
            // Time limit check.
            if ($time_limit > 0 && (time() - $start) >= $time_limit) {
                $state['running'] = true;
                $this->save_state($state);
                return $this->state_to_result($state, false);
            }

            // Fetch next batch of posts.
            $posts = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_content, post_type FROM {$wpdb->posts}
                 WHERE post_status = 'publish' AND post_type IN ('post', 'page', 'product')
                 ORDER BY ID ASC LIMIT %d OFFSET %d",
                self::POSTS_PER_TICK,
                $state['offset']
            ));

            if (empty($posts)) {
                // Scan complete.
                $state['running'] = false;
                $state['completed_at'] = current_time('mysql', true);
                $this->save_state($state);
                return $this->state_to_result($state, true);
            }

            foreach ($posts as $post) {
                $results = $this->scan_post($post);
                $state['broken_media'] += $results['broken_media'];
                $state['broken_links'] += $results['broken_links'];
                $state['scanned_posts']++;
            }

            $state['offset'] += self::POSTS_PER_TICK;
        }
    }

    /**
     * Cron tick: process one batch then yield.
     */
    public function cron_scan_tick(): void
    {
        $state = $this->get_state();

        if (empty($state['running'])) {
            // Start a new scan.
            $this->clear_broken_links();
            $state = array(
                'running'       => true,
                'offset'        => 0,
                'broken_media'  => 0,
                'broken_links'  => 0,
                'scanned_posts' => 0,
                'total_posts'   => $this->count_published_posts(),
                'started_at'    => current_time('mysql', true),
            );
        }

        global $wpdb;

        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_content, post_type FROM {$wpdb->posts}
             WHERE post_status = 'publish' AND post_type IN ('post', 'page', 'product')
             ORDER BY ID ASC LIMIT %d OFFSET %d",
            self::POSTS_PER_TICK,
            $state['offset']
        ));

        if (empty($posts)) {
            $state['running'] = false;
            $state['completed_at'] = current_time('mysql', true);
            $this->save_state($state);
            return;
        }

        foreach ($posts as $post) {
            $results = $this->scan_post($post);
            $state['broken_media'] += $results['broken_media'];
            $state['broken_links'] += $results['broken_links'];
            $state['scanned_posts']++;
        }

        $state['offset'] += self::POSTS_PER_TICK;

        // If there are more posts, schedule next tick in 1 minute.
        if ($state['scanned_posts'] < $state['total_posts']) {
            if (! wp_next_scheduled('ai_seo_captain_broken_link_scan')) {
                wp_schedule_single_event(time() + 60, 'ai_seo_captain_broken_link_scan');
            }
        } else {
            $state['running'] = false;
            $state['completed_at'] = current_time('mysql', true);
        }

        $this->save_state($state);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Per-post scanning logic
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Scan a single post for broken media and internal links.
     *
     * @return array{broken_media: int, broken_links: int}
     */
    private function scan_post(object $post): array
    {
        $broken_media = 0;
        $broken_links = 0;
        $content = $post->post_content;
        $post_id = (int) $post->ID;

        // 1. Check featured image.
        $thumb_id = (int) get_post_meta($post_id, '_thumbnail_id', true);
        if ($thumb_id > 0) {
            $file = get_attached_file($thumb_id);
            if (! $file || ! file_exists($file)) {
                $this->record_broken('broken_media', get_permalink($post_id), sprintf(
                    'Featured image (ID %d) file missing on disk.',
                    $thumb_id
                ), $post_id);
                $broken_media++;
            }
        }

        // 2. Extract image/media src attributes from content.
        $media_urls = $this->extract_media_urls($content);
        foreach ($media_urls as $url) {
            if (! $this->is_internal_url($url)) {
                continue; // Skip external.
            }
            if (! $this->media_file_exists($url)) {
                $this->record_broken('broken_media', $url, sprintf(
                    'Media file not found. Referenced in post ID %d.',
                    $post_id
                ), $post_id);
                $broken_media++;
            }
        }

        // 3. Extract internal links (href) and verify they resolve.
        $internal_links = $this->extract_internal_links($content);
        foreach ($internal_links as $url) {
            if (! $this->internal_link_resolves($url)) {
                $this->record_broken('broken_link', $url, sprintf(
                    'Internal link does not resolve to any published content. Referenced in post ID %d.',
                    $post_id
                ), $post_id);
                $broken_links++;
            }
        }

        return array('broken_media' => $broken_media, 'broken_links' => $broken_links);
    }

    /**
     * Extract all image/video/document URLs from content.
     */
    private function extract_media_urls(string $content): array
    {
        $urls = array();

        // Match src="..." and href="..." for media extensions.
        $media_extensions = 'jpe?g|png|gif|webp|svg|mp4|webm|ogg|mp3|wav|pdf|doc[x]?|xls[x]?|ppt[x]?';

        if (preg_match_all(
            '/(?:src|href)\s*=\s*["\']([^"\']+\.(?:' . $media_extensions . '))(?:\?[^"\']*)?["\']/i',
            $content,
            $matches
        )) {
            $urls = $matches[1];
        }

        // Also check wp:image block JSON attributes.
        if (preg_match_all('/"url"\s*:\s*"([^"]+\.(?:' . $media_extensions . '))(?:\?[^"]*)?"/i', $content, $matches2)) {
            $urls = array_merge($urls, $matches2[1]);
        }

        return array_unique($urls);
    }

    /**
     * Extract internal <a href="..."> links from content (non-media, non-anchor).
     */
    private function extract_internal_links(string $content): array
    {
        $links = array();
        $home = home_url();

        if (preg_match_all('/href\s*=\s*["\']([^"\'#]+)["\']/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                // Skip media URLs (handled separately).
                if (preg_match('/\.(jpe?g|png|gif|webp|svg|mp4|webm|ogg|mp3|wav|pdf|docx?|xlsx?|pptx?)(\?.*)?$/i', $url)) {
                    continue;
                }
                // Skip external URLs.
                if (! $this->is_internal_url($url)) {
                    continue;
                }
                // Skip mailto:, tel:, javascript:, #anchors.
                if (preg_match('/^(mailto:|tel:|javascript:)/i', $url)) {
                    continue;
                }
                $links[] = $url;
            }
        }

        return array_unique($links);
    }

    /**
     * Check if a URL is internal (same host as site).
     */
    private function is_internal_url(string $url): bool
    {
        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $url_host = wp_parse_url($url, PHP_URL_HOST);

        // Relative URLs are internal.
        if (null === $url_host || '' === $url_host) {
            return true;
        }

        return strcasecmp($home_host, $url_host) === 0;
    }

    /**
     * Check if a media file exists on disk (by URL → filesystem path).
     */
    private function media_file_exists(string $url): bool
    {
        $path = $this->url_to_filepath($url);

        if (null === $path) {
            return true; // Can't determine path — skip (don't false-positive).
        }

        return file_exists($path);
    }

    /**
     * Convert a media URL to a filesystem path.
     */
    private function url_to_filepath(string $url): ?string
    {
        $uploads = wp_get_upload_dir();
        $upload_url = $uploads['baseurl'];
        $upload_dir = $uploads['basedir'];

        // Handle both full URLs and relative URLs.
        $path_part = null;

        if (0 === strpos($url, $upload_url)) {
            // Full URL inside uploads.
            $path_part = substr($url, strlen($upload_url));
        } elseif (0 === strpos($url, '/wp-content/uploads/')) {
            // Relative path.
            $path_part = substr($url, strlen('/wp-content/uploads/'));
        } else {
            // URL might be in theme/plugin directory.
            $content_url = content_url();
            $content_dir = WP_CONTENT_DIR;

            if (0 === strpos($url, $content_url)) {
                $relative = substr($url, strlen($content_url));
                return $content_dir . $relative;
            }

            // Can't map — skip.
            return null;
        }

        if (null === $path_part) {
            return null;
        }

        // Remove query string if present.
        $path_part = strtok($path_part, '?');

        return $upload_dir . $path_part;
    }

    /**
     * Check if an internal link resolves to published content.
     */
    private function internal_link_resolves(string $url): bool
    {
        // Normalize to a path.
        $path = wp_parse_url($url, PHP_URL_PATH);

        if (empty($path) || '/' === $path) {
            return true; // Homepage always resolves.
        }

        // Try url_to_postid — WordPress's built-in URL resolver.
        $post_id = url_to_postid($url);
        if ($post_id > 0) {
            $status = get_post_status($post_id);
            return in_array($status, array('publish', 'private'), true);
        }

        // Check if it's a category/tag/term archive.
        $path = trailingslashit(ltrim($path, '/'));

        // Check common WordPress paths that always resolve.
        $skip_paths = array('wp-admin/', 'wp-login.php', 'wp-content/', 'feed/', 'author/');
        foreach ($skip_paths as $sp) {
            if (0 === strpos($path, $sp)) {
                return true; // System paths — not broken links.
            }
        }

        // Try term resolution.
        $parts = explode('/', trim($path, '/'));
        if (! empty($parts)) {
            $slug = end($parts);
            $term = get_term_by('slug', $slug, 'category');
            if ($term) {
                return true;
            }
            $term = get_term_by('slug', $slug, 'post_tag');
            if ($term) {
                return true;
            }
            // WooCommerce product category.
            if (taxonomy_exists('product_cat')) {
                $term = get_term_by('slug', $slug, 'product_cat');
                if ($term) {
                    return true;
                }
            }
        }

        // Could not resolve — mark as potentially broken.
        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Recording results
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record a broken link/media in the redirects table.
     */
    private function record_broken(string $type, string $url, string $note, int $source_post_id): void
    {
        global $wpdb;

        // Avoid duplicates.
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE source_url = %s AND type = %s LIMIT 1",
            $url,
            $type
        ));

        if ($existing) {
            // Update hit count and note.
            $wpdb->update(
                $this->table,
                array(
                    'hit_count' => 1,
                    'last_hit'  => current_time('mysql', true),
                    'target_url' => $note,
                ),
                array('id' => (int) $existing),
                array('%d', '%s', '%s'),
                array('%d')
            );
            return;
        }

        $wpdb->insert(
            $this->table,
            array(
                'source_url'  => $url,
                'target_url'  => $note, // Reuse target_url field for the note/description.
                'status_code' => 404,
                'type'        => $type, // 'broken_media' or 'broken_link'
                'hit_count'   => 1,
                'last_hit'    => current_time('mysql', true),
                'created_at'  => current_time('mysql', true),
            ),
            array('%s', '%s', '%d', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Clear all broken_link and broken_media entries.
     */
    public function clear_broken_links(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$this->table} WHERE type IN ('broken_link', 'broken_media')");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // State management
    // ─────────────────────────────────────────────────────────────────────────

    public function get_state(): array
    {
        return get_option(self::STATE_OPTION, array());
    }

    private function save_state(array $state): void
    {
        update_option(self::STATE_OPTION, $state, false);
    }

    private function count_published_posts(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_status = 'publish' AND post_type IN ('post', 'page', 'product')"
        );
    }

    private function state_to_result(array $state, bool $complete): array
    {
        return array(
            'broken_media'  => $state['broken_media'] ?? 0,
            'broken_links'  => $state['broken_links'] ?? 0,
            'scanned_posts' => $state['scanned_posts'] ?? 0,
            'total_posts'   => $state['total_posts'] ?? 0,
            'complete'      => $complete,
        );
    }

    /**
     * Get all broken entries for display.
     *
     * @return array<int, object>
     */
    public function get_broken_entries(int $limit = 200): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE type IN ('broken_link', 'broken_media') ORDER BY type ASC, last_hit DESC LIMIT %d",
            $limit
        ));
    }

    /**
     * Get count of broken entries by type.
     */
    public function get_broken_counts(): array
    {
        global $wpdb;

        $media = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} WHERE type = 'broken_media'"
        );
        $links = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} WHERE type = 'broken_link'"
        );

        return array('media' => $media, 'links' => $links, 'total' => $media + $links);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX handlers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * AJAX: Start a full scan (or resume an interrupted one).
     */
    public function ajax_scan_start(): void
    {
        check_ajax_referer('ai_seo_captain_nonce', '_nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized.', 'ai-seo-captain')));
        }

        // Reset state to start fresh.
        delete_option(self::STATE_OPTION);

        $result = $this->run_full_scan(25); // 25-second time limit per request.

        if ($result['complete']) {
            wp_send_json_success(array(
                'message'  => sprintf(
                    __('Scan complete. Scanned %d posts. Found %d broken media, %d broken links.', 'ai-seo-captain'),
                    $result['scanned_posts'],
                    $result['broken_media'],
                    $result['broken_links']
                ),
                'complete' => true,
                'result'   => $result,
            ));
        } else {
            // Schedule background continuation.
            if (! wp_next_scheduled('ai_seo_captain_broken_link_scan')) {
                wp_schedule_single_event(time() + 10, 'ai_seo_captain_broken_link_scan');
            }

            wp_send_json_success(array(
                'message'  => sprintf(
                    __('Scanning… %d/%d posts processed so far. Continuing in background.', 'ai-seo-captain'),
                    $result['scanned_posts'],
                    $result['total_posts']
                ),
                'complete' => false,
                'result'   => $result,
            ));
        }
    }

    /**
     * AJAX: Get current scan status.
     */
    public function ajax_scan_status(): void
    {
        check_ajax_referer('ai_seo_captain_nonce', '_nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized.', 'ai-seo-captain')));
        }

        $state = $this->get_state();
        $counts = $this->get_broken_counts();

        wp_send_json_success(array(
            'running'       => ! empty($state['running']),
            'scanned_posts' => $state['scanned_posts'] ?? 0,
            'total_posts'   => $state['total_posts'] ?? 0,
            'broken_media'  => $counts['media'],
            'broken_links'  => $counts['links'],
            'completed_at'  => $state['completed_at'] ?? null,
            'started_at'    => $state['started_at'] ?? null,
        ));
    }
}
