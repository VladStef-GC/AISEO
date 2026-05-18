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
            $this->log_404($request_path);
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
            'nonce' => wp_create_nonce('ai_seo_captain_nonce'),
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

            <?php else : ?>
                <?php if (! empty($errors_404)) : ?>
                    <p><button type="button" class="button" id="ai-seo-clear-404s">Clear all 404s</button></p>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>URL</th>
                                <th>Hits</th>
                                <th>Last hit</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($errors_404 as $e) : ?>
                                <tr data-id="<?php echo (int) $e->id; ?>">
                                    <td><code><?php echo esc_html($e->source_url); ?></code></td>
                                    <td><?php echo (int) $e->hit_count; ?></td>
                                    <td><?php echo $e->last_hit ? esc_html($e->last_hit) : '—'; ?></td>
                                    <td><button type="button" class="button button-link-delete ai-seo-redir-delete" data-id="<?php echo (int) $e->id; ?>">Dismiss</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>No 404 errors recorded yet.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
<?php
    }
}
