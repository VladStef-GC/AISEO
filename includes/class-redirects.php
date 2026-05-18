<?php

namespace AI_SEO_Captain;

/**
 * Redirect Manager + 404 Monitor.
 *
 * Handles:
 * - Custom 301/302/307 redirects (admin UI).
 * - Automatic 404 logging for monitoring broken links.
 * - Redirect execution on template_redirect hook.
 */
class Redirects
{
    /** @var Settings */
    private $settings;

    /** @var string DB table name (with prefix). */
    private $table;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        global $wpdb;
        $this->table = $wpdb->prefix . 'ai_seo_captain_redirects';

        // Front-end: execute redirects and log 404s.
        add_action('template_redirect', array($this, 'handle_request'), 1);

        // Admin AJAX handlers.
        add_action('wp_ajax_ai_seo_captain_add_redirect', array($this, 'ajax_add_redirect'));
        add_action('wp_ajax_ai_seo_captain_delete_redirect', array($this, 'ajax_delete_redirect'));
        add_action('wp_ajax_ai_seo_captain_clear_404s', array($this, 'ajax_clear_404s'));
    }

    /**
     * Execute redirects or log 404s on every front-end request.
     */
    public function handle_request(): void
    {
        $request_path = $this->get_request_path();

        // Look for a matching redirect.
        $redirect = $this->find_redirect($request_path);

        if (null !== $redirect && 'redirect' === $redirect->type && '' !== $redirect->target_url) {
            $this->bump_hit_count((int) $redirect->id);
            wp_redirect(esc_url_raw($redirect->target_url), (int) $redirect->status_code);
            exit;
        }

        // Log 404s.
        if (is_404()) {
            // Don't log the homepage or site root as 404 (can happen with bots/prefetch).
            $site_path = wp_parse_url(home_url(), PHP_URL_PATH);
            $is_homepage = ($request_path === '/' || $request_path === trailingslashit($site_path));
            if (! $is_homepage) {
                $this->log_404($request_path);
            }
        }
    }

    /**
     * Get the current request path (without query string).
     */
    private function get_request_path(): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $path = wp_parse_url($request_uri, PHP_URL_PATH);

        return is_string($path) ? trailingslashit($path) : '/';
    }

    /**
     * Find a redirect matching the given path.
     */
    private function find_redirect(string $path): ?object
    {
        global $wpdb;

        // Try exact match first (with and without trailing slash).
        $paths = array($path, untrailingslashit($path));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE type = 'redirect' AND (source_url = %s OR source_url = %s) LIMIT 1",
                $paths[0],
                $paths[1]
            )
        );

        return $row ?: null;
    }

    /**
     * Log a 404 hit — insert new or increment existing.
     */
    private function log_404(string $path): void
    {
        global $wpdb;

        // Check if we already have this 404 logged.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE type = '404' AND source_url = %s LIMIT 1",
                $path
            )
        );

        if ($existing) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->table} SET hit_count = hit_count + 1, last_hit = %s WHERE id = %d",
                    current_time('mysql', true),
                    (int) $existing
                )
            );
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            $this->table,
            array(
                'source_url'  => $path,
                'target_url'  => '',
                'status_code' => 404,
                'type'        => '404',
                'hit_count'   => 1,
                'last_hit'    => current_time('mysql', true),
                'created_at'  => current_time('mysql', true),
            ),
            array('%s', '%s', '%d', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Bump hit count for a redirect.
     */
    private function bump_hit_count(int $id): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table} SET hit_count = hit_count + 1, last_hit = %s WHERE id = %d",
                current_time('mysql', true),
                $id
            )
        );
    }

    // -------------------------------------------------------------------------
    // Admin data methods.
    // -------------------------------------------------------------------------

    /**
     * Get all redirects (type = 'redirect').
     *
     * @return array<int, object>
     */
    public function get_redirects(int $limit = 200, int $offset = 0): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE type = 'redirect' ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * Get all 404 entries.
     *
     * @return array<int, object>
     */
    public function get_404s(int $limit = 200, int $offset = 0): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE type = '404' ORDER BY hit_count DESC, last_hit DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * Add a new redirect.
     */
    public function add_redirect(string $source, string $target, int $status_code = 301): bool
    {
        global $wpdb;

        $source = trailingslashit('/' . ltrim($source, '/'));
        $target = trim($target);

        if ('' === $source || '' === $target) {
            return false;
        }

        // Prevent redirect loops — source must not equal target path.
        $target_path = wp_parse_url($target, PHP_URL_PATH);
        if (is_string($target_path) && trailingslashit($target_path) === $source) {
            return false;
        }

        // Delete any existing entry for this source.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($this->table, array('source_url' => $source), array('%s'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->insert(
            $this->table,
            array(
                'source_url'  => $source,
                'target_url'  => $target,
                'status_code' => in_array($status_code, array(301, 302, 307), true) ? $status_code : 301,
                'type'        => 'redirect',
                'hit_count'   => 0,
                'last_hit'    => null,
                'created_at'  => current_time('mysql', true),
            ),
            array('%s', '%s', '%d', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Delete a redirect or 404 entry by ID.
     */
    public function delete_entry(int $id): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->delete($this->table, array('id' => $id), array('%d'));
    }

    /**
     * Clear all 404 entries.
     */
    public function clear_404s(): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->query("DELETE FROM {$this->table} WHERE type = '404'");
    }

    /**
     * Convert a 404 entry into a redirect.
     */
    public function convert_404_to_redirect(int $id, string $target_url, int $status_code = 301): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->update(
            $this->table,
            array(
                'target_url'  => $target_url,
                'status_code' => in_array($status_code, array(301, 302, 307), true) ? $status_code : 301,
                'type'        => 'redirect',
                'hit_count'   => 0,
            ),
            array('id' => $id),
            array('%s', '%d', '%s', '%d'),
            array('%d')
        );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers.
    // -------------------------------------------------------------------------

    public function ajax_add_redirect(): void
    {
        check_ajax_referer('ai_seo_captain_nonce', '_nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $source = isset($_POST['source_url']) ? sanitize_text_field(wp_unslash($_POST['source_url'])) : '';
        $target = isset($_POST['target_url']) ? esc_url_raw(wp_unslash($_POST['target_url'])) : '';
        $status = isset($_POST['status_code']) ? (int) $_POST['status_code'] : 301;

        if ('' === $source || '' === $target) {
            wp_send_json_error('Source and target URLs are required.');
        }

        $result = $this->add_redirect($source, $target, $status);

        if ($result) {
            wp_send_json_success(array('message' => 'Redirect added.'));
        } else {
            wp_send_json_error('Failed to add redirect. Check for redirect loops.');
        }
    }

    public function ajax_delete_redirect(): void
    {
        check_ajax_referer('ai_seo_captain_nonce', '_nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            wp_send_json_error('Invalid ID.');
        }

        $this->delete_entry($id);
        wp_send_json_success(array('message' => 'Entry deleted.'));
    }

    public function ajax_clear_404s(): void
    {
        check_ajax_referer('ai_seo_captain_nonce', '_nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $count = $this->clear_404s();
        wp_send_json_success(array('message' => sprintf('Cleared %d 404 entries.', $count)));
    }

    /**
     * Render the Redirects admin page content.
     */
    public function render_admin_page(): void
    {
        wp_localize_script('ai-seo-page-redirects', 'aiscRedirects', array(
            'nonce'     => wp_create_nonce('ai_seo_captain_nonce'),
            'pluginUrl' => AI_SEO_CAPTAIN_URL,
        ));

        $redirects = $this->get_redirects();
        $errors_404 = $this->get_404s();
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'redirects';
?>
        <div class="wrap">
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
                <img src="<?php echo esc_url(AI_SEO_CAPTAIN_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="SEO Captain" style="width:40px;height:40px;" />
                <h1 style="margin:0;">Redirects &amp; 404 Monitor</h1>
            </div>

            <nav class="nav-tab-wrapper" style="margin-bottom:16px;">
                <a href="<?php echo esc_url(add_query_arg('tab', 'redirects')); ?>" class="nav-tab <?php echo 'redirects' === $active_tab ? 'nav-tab-active' : ''; ?>">Redirects (<?php echo count($redirects); ?>)</a>
                <a href="<?php echo esc_url(add_query_arg('tab', '404s')); ?>" class="nav-tab <?php echo '404s' === $active_tab ? 'nav-tab-active' : ''; ?>">404 Monitor (<?php echo count($errors_404); ?>)</a>
                <?php
                $scanner = Plugin::instance()->get_broken_link_scanner();
                $broken_counts = $scanner ? $scanner->get_broken_counts() : array('total' => 0);
                ?>
                <a href="<?php echo esc_url(add_query_arg('tab', 'broken_links')); ?>" class="nav-tab <?php echo 'broken_links' === $active_tab ? 'nav-tab-active' : ''; ?>">Broken Links (<?php echo (int) $broken_counts['total']; ?>)</a>
            </nav>

            <?php if ('redirects' === $active_tab) : ?>
                <div id="ai-seo-redirects-add" style="margin-bottom:20px; padding:16px; background:#fff; border:1px solid #ccd0d4;">
                    <h3 style="margin-top:0;">Add redirect</h3>
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th style="width:120px;"><label for="ai-seo-redir-source">Source path</label></th>
                            <td><input id="ai-seo-redir-source" type="text" class="regular-text" placeholder="/old-page/" /></td>
                        </tr>
                        <tr>
                            <th><label for="ai-seo-redir-target">Target URL</label></th>
                            <td><input id="ai-seo-redir-target" type="url" class="regular-text" placeholder="<?php echo esc_attr(home_url('/new-page/')); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="ai-seo-redir-status">Type</label></th>
                            <td>
                                <select id="ai-seo-redir-status">
                                    <option value="301">301 — Permanent</option>
                                    <option value="302">302 — Temporary</option>
                                    <option value="307">307 — Temporary (strict)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p><button type="button" class="button button-primary" id="ai-seo-redir-add-btn">Add redirect</button></p>
                </div>

                <?php if (! empty($redirects)) : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Source</th>
                                <th>Target</th>
                                <th>Type</th>
                                <th>Hits</th>
                                <th>Last hit</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($redirects as $r) : ?>
                                <tr data-id="<?php echo (int) $r->id; ?>">
                                    <td><code><?php echo esc_html($r->source_url); ?></code></td>
                                    <td><?php echo esc_html($r->target_url); ?></td>
                                    <td><?php echo (int) $r->status_code; ?></td>
                                    <td><?php echo (int) $r->hit_count; ?></td>
                                    <td><?php echo $r->last_hit ? esc_html($r->last_hit) : '—'; ?></td>
                                    <td><button type="button" class="button button-link-delete ai-seo-redir-delete" data-id="<?php echo (int) $r->id; ?>">Delete</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>No redirects configured yet.</p>
                <?php endif; ?>

            <?php elseif ('404s' === $active_tab) : ?>
                <?php if (! empty($errors_404)) : ?>
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:0 0 16px;">
                        <button type="button" class="button" id="ai-seo-clear-404s">Clear all 404s</button>
                        <div style="flex:1;min-width:200px;max-width:360px;">
                            <input type="text" id="aisc-404-search" placeholder="Search 404 URLs…" style="width:100%;padding:6px 10px;font-size:13px;border:1px solid #8c8f94;border-radius:4px;" />
                        </div>
                    </div>
                    <table class="widefat striped ai-seo-sortable" id="ai-seo-404-table">
                        <thead>
                            <tr>
                                <th class="ai-seo-sort" data-col="0">URL <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                                <th style="width:80px;" class="ai-seo-sort" data-col="1">Hits <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                                <th style="width:150px;" class="ai-seo-sort" data-col="2">Last hit <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                                <th style="width:80px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($errors_404 as $e) : ?>
                                <tr data-id="<?php echo (int) $e->id; ?>">
                                    <td data-sort-value="<?php echo esc_attr(strtolower($e->source_url)); ?>"><code><?php echo esc_html($e->source_url); ?></code></td>
                                    <td data-sort-value="<?php echo (int) $e->hit_count; ?>"><?php echo (int) $e->hit_count; ?></td>
                                    <td data-sort-value="<?php echo esc_attr($e->last_hit ?? ''); ?>"><?php echo $e->last_hit ? esc_html($e->last_hit) : '—'; ?></td>
                                    <td><button type="button" class="button button-link-delete ai-seo-redir-delete" data-id="<?php echo (int) $e->id; ?>">Dismiss</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>No 404 errors recorded yet.</p>
                <?php endif; ?>

            <?php elseif ('broken_links' === $active_tab) : ?>
                <?php
                $scan_state = $scanner ? $scanner->get_state() : array();
                $broken_entries = $scanner ? $scanner->get_broken_entries() : array();
                $is_running = ! empty($scan_state['running']);
                ?>
                <div style="margin-bottom:20px; padding:16px; background:#fff; border:1px solid #ccd0d4;">
                    <h3 style="margin-top:0;">Broken Link & Media Scanner</h3>
                    <p style="color:#555;">Scans all published posts, pages, and products for broken internal links and missing media files. Uses only database and filesystem checks — zero HTTP requests, no performance impact.</p>

                    <div style="display:flex;align-items:center;gap:12px;margin-top:12px;">
                        <button type="button" class="button button-primary" id="ai-seo-broken-scan-btn" <?php echo $is_running ? 'disabled' : ''; ?>>
                            <?php echo $is_running ? 'Scanning…' : 'Scan Now'; ?>
                        </button>
                        <span id="ai-seo-broken-scan-status" style="color:#666;font-style:italic;">
                            <?php if ($is_running) : ?>
                                Scanning… <?php echo (int) ($scan_state['scanned_posts'] ?? 0); ?>/<?php echo (int) ($scan_state['total_posts'] ?? 0); ?> posts processed.
                            <?php elseif (! empty($scan_state['completed_at'])) : ?>
                                Last scan: <?php echo esc_html($scan_state['completed_at']); ?> UTC
                            <?php else : ?>
                                No scan run yet.
                            <?php endif; ?>
                        </span>
                    </div>
                    <div id="ai-seo-broken-scan-progress" style="margin-top:10px;display:<?php echo $is_running ? 'block' : 'none'; ?>;">
                        <div style="background:#e0e0e0;border-radius:4px;height:8px;width:100%;max-width:400px;">
                            <div id="ai-seo-broken-scan-bar" style="background:#0073aa;height:100%;border-radius:4px;width:<?php
                                                                                                                            $pct = (! empty($scan_state['total_posts']) && $scan_state['total_posts'] > 0) ? round(($scan_state['scanned_posts'] / $scan_state['total_posts']) * 100) : 0;
                                                                                                                            echo (int) $pct;
                                                                                                                            ?>%;transition:width 0.3s;"></div>
                        </div>
                    </div>
                </div>

                <?php if (! empty($broken_entries)) : ?>
                    <?php
                    // Classify entries by type for filter buttons.
                    $type_counts = array('all' => 0, 'image' => 0, 'document' => 0, 'video' => 0, 'link' => 0, 'css' => 0, 'js' => 0, 'other' => 0);
                    foreach ($broken_entries as $entry) {
                        $type_counts['all']++;
                        $cat = self::classify_url_type($entry->source_url, $entry->type);
                        $type_counts[$cat] = ($type_counts[$cat] ?? 0) + 1;
                    }
                    ?>
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:0 0 16px;">
                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                            <button type="button" class="button button-primary aisc-broken-filter" data-filter="all">All (<?php echo $type_counts['all']; ?>)</button>
                            <?php if ($type_counts['image'] > 0) : ?><button type="button" class="button aisc-broken-filter" data-filter="image">Images (<?php echo $type_counts['image']; ?>)</button><?php endif; ?>
                            <?php if ($type_counts['document'] > 0) : ?><button type="button" class="button aisc-broken-filter" data-filter="document">Documents (<?php echo $type_counts['document']; ?>)</button><?php endif; ?>
                            <?php if ($type_counts['video'] > 0) : ?><button type="button" class="button aisc-broken-filter" data-filter="video">Video (<?php echo $type_counts['video']; ?>)</button><?php endif; ?>
                            <?php if ($type_counts['link'] > 0) : ?><button type="button" class="button aisc-broken-filter" data-filter="link">Links (<?php echo $type_counts['link']; ?>)</button><?php endif; ?>
                            <?php if ($type_counts['css'] > 0) : ?><button type="button" class="button aisc-broken-filter" data-filter="css">CSS (<?php echo $type_counts['css']; ?>)</button><?php endif; ?>
                            <?php if ($type_counts['js'] > 0) : ?><button type="button" class="button aisc-broken-filter" data-filter="js">JS (<?php echo $type_counts['js']; ?>)</button><?php endif; ?>
                            <?php if ($type_counts['other'] > 0) : ?><button type="button" class="button aisc-broken-filter" data-filter="other">Other (<?php echo $type_counts['other']; ?>)</button><?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:200px;max-width:360px;">
                            <input type="text" id="aisc-broken-search" placeholder="Search broken URLs…" style="width:100%;padding:6px 10px;font-size:13px;border:1px solid #8c8f94;border-radius:4px;" />
                        </div>
                    </div>

                    <table class="widefat striped ai-seo-sortable" id="ai-seo-broken-table">
                        <thead>
                            <tr>
                                <th style="width:90px;" class="ai-seo-sort" data-col="0">Type <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                                <th class="ai-seo-sort" data-col="1">URL <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                                <th style="width:200px;">Referenced in</th>
                                <th style="width:60px;" class="ai-seo-sort" data-col="3">Hits <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                                <th style="width:30%;">Details</th>
                                <th style="width:130px;" class="ai-seo-sort" data-col="5">Detected <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($broken_entries as $entry) :
                                $url_category = self::classify_url_type($entry->source_url, $entry->type);
                                $referenced_in = self::extract_referenced_post($entry->target_url);
                            ?>
                                <tr data-type-filter="<?php echo esc_attr($url_category); ?>">
                                    <td data-sort-value="<?php echo esc_attr($url_category); ?>">
                                        <?php echo self::render_type_badge($url_category); // phpcs:ignore ?>
                                    </td>
                                    <td data-sort-value="<?php echo esc_attr(strtolower($entry->source_url)); ?>"><code style="word-break:break-all;font-size:12px;"><?php echo esc_html($entry->source_url); ?></code></td>
                                    <td>
                                        <?php if ($referenced_in['id'] > 0) : ?>
                                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $referenced_in['id'] . '&action=edit')); ?>" style="font-size:12px;"><?php echo esc_html($referenced_in['title']); ?></a>
                                        <?php elseif (! empty($referenced_in['label'])) : ?>
                                            <span style="font-size:12px;color:#555;"><?php echo esc_html($referenced_in['label']); ?></span>
                                        <?php else : ?>
                                            <span style="font-size:12px;color:#999;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-sort-value="<?php echo (int) $entry->hit_count; ?>"><?php echo (int) $entry->hit_count; ?></td>
                                    <td style="color:#555;font-size:12px;"><?php echo esc_html($entry->target_url); ?></td>
                                    <td data-sort-value="<?php echo esc_attr($entry->last_hit ?? ''); ?>"><?php echo $entry->last_hit ? esc_html($entry->last_hit) : '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="aisc-broken-pagination" class="tablenav bottom" style="margin-top:16px;text-align:center;display:none;">
                        <div class="tablenav-pages" style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:8px 18px;box-shadow:0 1px 3px rgba(0,0,0,.08);float:none;"></div>
                    </div>
                <?php else : ?>
                    <p style="color:#555;">No broken links or missing media detected. Run a scan to check your content.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
<?php
    }

    /**
     * Classify a URL into a category for filtering.
     */
    private static function classify_url_type(string $url, string $db_type): string
    {
        if ('broken_link' === $db_type) {
            return 'link';
        }

        $url_lower = strtolower($url);

        if (preg_match('/\.(jpe?g|png|gif|webp|svg|ico|bmp|tiff?)(\?|$)/i', $url_lower)) {
            return 'image';
        }
        if (preg_match('/\.(mp4|webm|ogg|avi|mov|wmv|flv)(\?|$)/i', $url_lower)) {
            return 'video';
        }
        if (preg_match('/\.(pdf|docx?|xlsx?|pptx?|txt|csv|rtf)(\?|$)/i', $url_lower)) {
            return 'document';
        }
        if (preg_match('/\.css(\?|$)/i', $url_lower)) {
            return 'css';
        }
        if (preg_match('/\.js(\?|$)/i', $url_lower)) {
            return 'js';
        }

        return 'other';
    }

    /**
     * Render a colored badge for the URL type.
     */
    private static function render_type_badge(string $category): string
    {
        $badges = array(
            'image'    => array('#d63638', '📷 Image'),
            'video'    => array('#8e44ad', '🎬 Video'),
            'document' => array('#2271b1', '📄 Doc'),
            'link'     => array('#dba617', '🔗 Link'),
            'css'      => array('#00796b', '🎨 CSS'),
            'js'       => array('#e65100', '⚡ JS'),
            'other'    => array('#555', '📦 Other'),
        );

        $badge = $badges[$category] ?? $badges['other'];
        return '<span style="color:' . $badge[0] . ';font-weight:500;font-size:12px;white-space:nowrap;">' . $badge[1] . '</span>';
    }

    /**
     * Extract referenced post info from the detail/note text.
     */
    private static function extract_referenced_post(string $note): array
    {
        // Try to extract "post ID NNN" from the note.
        if (preg_match('/post\s*ID\s*(\d+)/i', $note, $m)) {
            $post_id = (int) $m[1];
            $title = get_the_title($post_id);
            if ($title) {
                return array('id' => $post_id, 'title' => $title, 'label' => '');
            }
        }

        // Try to extract navigation menu reference.
        if (preg_match('/Navigation menu "([^"]+)"/i', $note, $m)) {
            return array('id' => 0, 'title' => '', 'label' => 'Menu: ' . $m[1]);
        }

        // 404 Monitor cross-reference.
        if (stripos($note, '404 Monitor') !== false) {
            return array('id' => 0, 'title' => '', 'label' => '404 Monitor');
        }

        return array('id' => 0, 'title' => '', 'label' => '');
    }
}
