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
                'phase'         => 'content', // content → menus → attachments → done
                'offset'        => 0,
                'broken_media'  => 0,
                'broken_links'  => 0,
                'scanned_posts' => 0,
                'total_posts'   => $this->count_scannable_items(),
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

            $phase = $state['phase'] ?? 'content';

            if ('content' === $phase) {
                // Phase 1: Scan post_content of published content.
                $posts = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_content, post_type FROM {$wpdb->posts}
                     WHERE post_status = 'publish' AND post_type IN ('post', 'page', 'product')
                     ORDER BY ID ASC LIMIT %d OFFSET %d",
                    self::POSTS_PER_TICK,
                    $state['offset']
                ));

                if (empty($posts)) {
                    // Move to next phase.
                    $state['phase'] = 'menus';
                    $state['offset'] = 0;
                    continue;
                }

                foreach ($posts as $post) {
                    $results = $this->scan_post($post);
                    $state['broken_media'] += $results['broken_media'];
                    $state['broken_links'] += $results['broken_links'];
                    $state['scanned_posts']++;
                }

                $state['offset'] += self::POSTS_PER_TICK;
            } elseif ('menus' === $phase) {
                // Phase 2: Verify all navigation menu links resolve.
                $results = $this->scan_nav_menus();
                $state['broken_links'] += $results['broken_links'];
                $state['scanned_posts'] += $results['scanned'];
                $state['phase'] = 'attachments';
                $state['offset'] = 0;
            } elseif ('attachments' === $phase) {
                // Phase 3: Verify attachment files exist on disk.
                $attachments = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment'
                     ORDER BY ID ASC LIMIT %d OFFSET %d",
                    self::POSTS_PER_TICK,
                    $state['offset']
                ));

                if (empty($attachments)) {
                    // Move to trashed content phase.
                    $state['phase'] = 'trashed';
                    $state['offset'] = 0;
                    continue;
                }

                foreach ($attachments as $att) {
                    $file = get_attached_file($att->ID);
                    if ($file && ! file_exists($file)) {
                        $url = wp_get_attachment_url($att->ID);
                        $this->record_broken('broken_media', $url ?: "(attachment #{$att->ID})", sprintf(
                            'Media Library file missing from disk: %s',
                            $file
                        ), $att->ID);
                        $state['broken_media']++;
                    }
                    $state['scanned_posts']++;
                }

                $state['offset'] += self::POSTS_PER_TICK;
            } elseif ('trashed' === $phase) {
                // Phase 4: Find trashed/draft posts that were once published (SEO broken links).
                $results = $this->scan_trashed_content();
                $state['broken_links'] += $results['broken_links'];
                $state['scanned_posts'] += $results['scanned'];
                // Phase 5: Cross-reference 404 Monitor entries with filesystem.
                $state['phase'] = 'verify_404s';
            } elseif ('verify_404s' === $phase) {
                // Phase 5: Check 404 Monitor entries for media files that don't exist on disk.
                $results = $this->verify_404_media();
                $state['broken_media'] += $results['broken_media'];
                $state['scanned_posts'] += $results['scanned'];
                // All phases complete.
                $state['running'] = false;
                $state['phase'] = 'done';
                $state['completed_at'] = current_time('mysql', true);
                $this->save_state($state);
                return $this->state_to_result($state, true);
            } else {
                // Unknown phase — mark complete.
                $state['running'] = false;
                $state['completed_at'] = current_time('mysql', true);
                $this->save_state($state);
                return $this->state_to_result($state, true);
            }
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
                'phase'         => 'content',
                'offset'        => 0,
                'broken_media'  => 0,
                'broken_links'  => 0,
                'scanned_posts' => 0,
                'total_posts'   => $this->count_scannable_items(),
                'started_at'    => current_time('mysql', true),
            );
        }

        // Run with a 15-second time limit per tick.
        $start = time();
        global $wpdb;

        $phase = $state['phase'] ?? 'content';

        if ('content' === $phase) {
            $posts = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_content, post_type FROM {$wpdb->posts}
                 WHERE post_status = 'publish' AND post_type IN ('post', 'page', 'product')
                 ORDER BY ID ASC LIMIT %d OFFSET %d",
                self::POSTS_PER_TICK,
                $state['offset']
            ));

            if (empty($posts)) {
                $state['phase'] = 'menus';
                $state['offset'] = 0;
            } else {
                foreach ($posts as $post) {
                    $results = $this->scan_post($post);
                    $state['broken_media'] += $results['broken_media'];
                    $state['broken_links'] += $results['broken_links'];
                    $state['scanned_posts']++;
                }
                $state['offset'] += self::POSTS_PER_TICK;
            }
        } elseif ('menus' === $phase) {
            $results = $this->scan_nav_menus();
            $state['broken_links'] += $results['broken_links'];
            $state['scanned_posts'] += $results['scanned'];
            $state['phase'] = 'attachments';
            $state['offset'] = 0;
        } elseif ('attachments' === $phase) {
            $attachments = $wpdb->get_results($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment'
                 ORDER BY ID ASC LIMIT %d OFFSET %d",
                self::POSTS_PER_TICK,
                $state['offset']
            ));

            if (empty($attachments)) {
                $state['phase'] = 'trashed';
                $state['offset'] = 0;
            } else {
                foreach ($attachments as $att) {
                    $file = get_attached_file($att->ID);
                    if ($file && ! file_exists($file)) {
                        $url = wp_get_attachment_url($att->ID);
                        $this->record_broken('broken_media', $url ?: "(attachment #{$att->ID})", sprintf(
                            'Media Library file missing from disk: %s',
                            $file
                        ), $att->ID);
                        $state['broken_media']++;
                    }
                    $state['scanned_posts']++;
                }
                $state['offset'] += self::POSTS_PER_TICK;
            }
        } elseif ('trashed' === $phase) {
            $results = $this->scan_trashed_content();
            $state['broken_links'] += $results['broken_links'];
            $state['scanned_posts'] += $results['scanned'];
            $state['phase'] = 'verify_404s';
        } elseif ('verify_404s' === $phase) {
            $results = $this->verify_404_media();
            $state['broken_media'] += $results['broken_media'];
            $state['scanned_posts'] += $results['scanned'];
            $state['running'] = false;
            $state['phase'] = 'done';
            $state['completed_at'] = current_time('mysql', true);
            $this->save_state($state);
            return;
        } else {
            $state['running'] = false;
            $state['completed_at'] = current_time('mysql', true);
            $this->save_state($state);
            return;
        }

        // If still running, schedule next tick.
        if (! empty($state['running']) && $state['phase'] !== 'done') {
            if (! wp_next_scheduled('ai_seo_captain_broken_link_scan')) {
                wp_schedule_single_event(time() + 60, 'ai_seo_captain_broken_link_scan');
            }
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
     * Scan navigation menu items for broken links.
     *
     * @return array{broken_links: int, scanned: int}
     */
    private function scan_nav_menus(): array
    {
        global $wpdb;
        $broken_links = 0;
        $scanned = 0;

        // Get all nav menu items.
        $menu_items = $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type = 'nav_menu_item' AND post_status = 'publish'"
        );

        foreach ($menu_items as $item) {
            $scanned++;
            $type = get_post_meta($item->ID, '_menu_item_type', true);
            $object_id = (int) get_post_meta($item->ID, '_menu_item_object_id', true);
            $url = get_post_meta($item->ID, '_menu_item_url', true);

            if ('post_type' === $type && $object_id > 0) {
                // Menu links to a post/page — verify it's published.
                $status = get_post_status($object_id);
                if (! $status || ! in_array($status, array('publish', 'private'), true)) {
                    $label = $item->post_title ?: "(menu item #{$item->ID})";
                    $this->record_broken('broken_link', get_permalink($object_id) ?: "post_id:$object_id", sprintf(
                        'Navigation menu "%s" links to unpublished/deleted content (post ID %d, status: %s).',
                        $label,
                        $object_id,
                        $status ?: 'deleted'
                    ), $item->ID);
                    $broken_links++;
                }
            } elseif ('taxonomy' === $type && $object_id > 0) {
                // Menu links to a term — verify it exists.
                $term = get_term($object_id);
                if (! $term || is_wp_error($term)) {
                    $label = $item->post_title ?: "(menu item #{$item->ID})";
                    $this->record_broken('broken_link', "term_id:$object_id", sprintf(
                        'Navigation menu "%s" links to deleted taxonomy term (ID %d).',
                        $label,
                        $object_id
                    ), $item->ID);
                    $broken_links++;
                }
            } elseif ('custom' === $type && ! empty($url)) {
                // Custom URL — verify if internal and resolves.
                if ('#' === $url || empty(trim($url))) {
                    continue;
                }
                if ($this->is_internal_url($url) && ! $this->internal_link_resolves($url)) {
                    $label = $item->post_title ?: "(menu item #{$item->ID})";
                    $this->record_broken('broken_link', $url, sprintf(
                        'Navigation menu "%s" custom link does not resolve.',
                        $label
                    ), $item->ID);
                    $broken_links++;
                }
            }
        }

        return array('broken_links' => $broken_links, 'scanned' => $scanned);
    }

    /**
     * Scan for trashed/draft posts that were previously published.
     * These represent SEO-damaging broken links (Google still has them indexed).
     *
     * @return array{broken_links: int, scanned: int}
     */
    private function scan_trashed_content(): array
    {
        global $wpdb;
        $broken_links = 0;
        $scanned = 0;

        // Find posts/pages in trash or draft that have a slug (were once published).
        $trashed = $wpdb->get_results(
            "SELECT ID, post_name, post_type, post_status, post_title FROM {$wpdb->posts}
             WHERE post_status IN ('trash', 'draft')
               AND post_type IN ('post', 'page', 'product')
               AND post_name != ''
             ORDER BY ID ASC"
        );

        foreach ($trashed as $post) {
            $scanned++;
            $slug = str_replace('__trashed', '', $post->post_name);

            // Build the URL this post would have had when published.
            $url = home_url('/' . $slug . '/');

            $this->record_broken('broken_link', $url, sprintf(
                '%s "%s" is in %s (post ID %d). Was likely indexed by search engines — consider restoring or adding a redirect.',
                ucfirst($post->post_type),
                $post->post_title,
                $post->post_status,
                $post->ID
            ), (int) $post->ID);
            $broken_links++;
        }

        return array('broken_links' => $broken_links, 'scanned' => $scanned);
    }

    /**
     * Cross-reference 404 Monitor entries: check if recorded 404 media URLs
     * are files that genuinely don't exist on disk.
     *
     * @return array{broken_media: int, scanned: int}
     */
    private function verify_404_media(): array
    {
        global $wpdb;
        $broken_media = 0;
        $scanned = 0;

        // Get media-related 404 entries from the monitor.
        $media_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'webm', 'pdf');
        $like_clauses = array();
        foreach ($media_extensions as $ext) {
            $like_clauses[] = $wpdb->prepare("source_url LIKE %s", '%.' . $ext . '%');
        }

        $where = implode(' OR ', $like_clauses);
        $entries_404 = $wpdb->get_results(
            "SELECT id, source_url, hit_count FROM {$this->table}
             WHERE type = '404' AND ($where)
             ORDER BY hit_count DESC LIMIT 100"
        );

        foreach ($entries_404 as $entry) {
            $scanned++;
            $url = rtrim($entry->source_url, '/'); // Strip trailing slash from media URLs.

            // Try to map this 404 URL to a filesystem path.
            $path = $this->url_to_filepath($url);
            if (null !== $path && ! file_exists($path)) {
                $this->record_broken('broken_media', $url, sprintf(
                    'File confirmed missing on disk. Hit %d times in 404 Monitor. Path: %s',
                    (int) $entry->hit_count,
                    $path
                ), 0);
                $broken_media++;
            } elseif (null === $path) {
                // Can't map to filesystem — try ABSPATH resolution.
                $site_path = wp_parse_url(home_url(), PHP_URL_PATH);
                $request_path = $url;
                if ($site_path && '/' !== $site_path) {
                    // Strip site subdirectory to get filesystem-relative path.
                    $site_path = trailingslashit($site_path);
                    if (0 === strpos($request_path, $site_path)) {
                        $request_path = substr($request_path, strlen($site_path));
                    }
                } else {
                    $request_path = ltrim($request_path, '/');
                }

                $abs_path = ABSPATH . $request_path;
                // Remove trailing slash for file check.
                $abs_path = rtrim($abs_path, '/\\');

                if (! file_exists($abs_path)) {
                    $this->record_broken('broken_media', $url, sprintf(
                        'File confirmed missing. Hit %d times in 404 Monitor. Expected at: %s',
                        (int) $entry->hit_count,
                        $abs_path
                    ), 0);
                    $broken_media++;
                }
            }
        }

        return array('broken_media' => $broken_media, 'scanned' => $scanned);
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
            // Full URL inside uploads (absolute).
            $path_part = substr($url, strlen($upload_url));
        } elseif (0 === strpos($url, '/wp-content/uploads/')) {
            // Relative path without subdirectory prefix.
            $path_part = substr($url, strlen('/wp-content/uploads/'));
        } else {
            // Handle subdirectory installs (e.g. /greencoders/wp-content/uploads/...).
            $site_path = wp_parse_url(home_url(), PHP_URL_PATH);
            if ($site_path && '/' !== $site_path) {
                $site_path = trailingslashit($site_path);
                $sub_upload_path = $site_path . 'wp-content/uploads/';
                $sub_content_path = $site_path . 'wp-content/';

                if (0 === strpos($url, $sub_upload_path)) {
                    $path_part = substr($url, strlen($sub_upload_path));
                } elseif (0 === strpos($url, $sub_content_path)) {
                    $relative = substr($url, strlen($sub_content_path));
                    return WP_CONTENT_DIR . '/' . $relative;
                }
            }

            // URL might be in theme/plugin directory (full URL).
            if (null === $path_part) {
                $content_url = content_url();
                $content_dir = WP_CONTENT_DIR;

                if (0 === strpos($url, $content_url)) {
                    $relative = substr($url, strlen($content_url));
                    return $content_dir . $relative;
                }

                // Can't map — skip.
                return null;
            }
        }

        if (null === $path_part) {
            return null;
        }

        // Remove query string if present.
        $path_part = strtok($path_part, '?');

        return $upload_dir . '/' . ltrim($path_part, '/');
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

        // Strip site subdirectory prefix (e.g. /greencoders/) if present.
        $site_path = wp_parse_url(home_url(), PHP_URL_PATH);
        if ($site_path && '/' !== $site_path) {
            $site_path = trailingslashit($site_path);
            if (0 === strpos($path, $site_path)) {
                $path = '/' . substr($path, strlen($site_path));
            }
            // Homepage with subdirectory (e.g. /greencoders/ itself).
            if ($path === rtrim($site_path, '/') || $path === $site_path) {
                return true;
            }
        }

        if ('/' === $path || empty($path)) {
            return true; // Homepage.
        }

        // Try url_to_postid — WordPress's built-in URL resolver.
        // Pass the full URL for best results in subdirectory installs.
        $full_url = 0 === strpos($url, 'http') ? $url : home_url($path);
        $post_id = url_to_postid($full_url);
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

        // Pull the real hit count from 404 Monitor for this URL (if it exists there).
        $real_hits = $this->get_404_hit_count($url);

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
                    'hit_count'  => $real_hits,
                    'last_hit'   => current_time('mysql', true),
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
                'hit_count'   => $real_hits,
                'last_hit'    => current_time('mysql', true),
                'created_at'  => current_time('mysql', true),
            ),
            array('%s', '%s', '%d', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Look up the hit count from 404 Monitor for a given URL.
     *
     * Checks both the exact URL and common variants (with/without trailing slash,
     * with/without site subdirectory prefix).
     */
    private function get_404_hit_count(string $url): int
    {
        global $wpdb;

        // Normalise: try the URL as-is, with trailing slash, and without.
        $variants = array(
            $url,
            rtrim($url, '/'),
            trailingslashit($url),
        );

        // Also try with/without site subdirectory prefix.
        $site_path = wp_parse_url(home_url(), PHP_URL_PATH);
        if ($site_path && '/' !== $site_path) {
            $site_path = trailingslashit($site_path);
            $bare = ltrim($url, '/');
            if (0 === strpos('/' . $bare, $site_path)) {
                // URL has the subdirectory — add variant without it.
                $without = '/' . substr('/' . $bare, strlen($site_path));
                $variants[] = $without;
                $variants[] = rtrim($without, '/');
            } else {
                // URL doesn't have subdirectory — add variant with it.
                $with = $site_path . ltrim($bare, '/');
                $variants[] = $with;
                $variants[] = trailingslashit($with);
            }
        }

        $variants = array_unique($variants);
        $placeholders = implode(',', array_fill(0, count($variants), '%s'));

        $hit_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT hit_count FROM {$this->table} WHERE type = '404' AND source_url IN ($placeholders) ORDER BY hit_count DESC LIMIT 1",
            ...$variants
        ));

        return max(1, $hit_count);
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

    private function count_scannable_items(): int
    {
        global $wpdb;
        // Posts + pages + products + nav menu items + attachments.
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE (post_status = 'publish' AND post_type IN ('post', 'page', 'product', 'nav_menu_item'))
                OR post_type = 'attachment'"
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
