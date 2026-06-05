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
        add_action('wp_ajax_ai_seo_captain_bulk_url_change', array($this, 'ajax_bulk_url_change'));
        add_action('wp_ajax_ai_seo_captain_preview_refs', array($this, 'ajax_preview_refs'));
        add_action('wp_ajax_ai_seo_captain_detect_chains', array($this, 'ajax_detect_chains'));
        add_action('wp_ajax_ai_seo_captain_fix_chains', array($this, 'ajax_fix_chains'));
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
     * Add a new redirect with automatic chain flattening.
     *
     * When creating A→B, this method checks for:
     * 1. Forward chains — any existing X→A will be rewritten to X→B.
     * 2. Reverse chains — if B→C already exists, A is pointed directly to C
     *    (the final destination) instead.
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
        $target_rel = $this->url_to_source_path($target);
        if (is_string($target_rel) && $target_rel === $source) {
            return false;
        }

        // ── Chain flattening: resolve reverse chains ────────────────────
        // If the target itself is the source of another redirect (B→C),
        // point directly to the final destination to avoid B→C chain.
        $final_target = $this->resolve_final_target($target);
        if ($final_target !== $target) {
            $target = $final_target;

            // Re-check loop after resolution.
            $target_rel = $this->url_to_source_path($target);
            if (is_string($target_rel) && $target_rel === $source) {
                return false;
            }
        }

        // Delete any existing entry for this source.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($this->table, array('source_url' => $source), array('%s'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $inserted = (bool) $wpdb->insert(
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

        if (! $inserted) {
            return false;
        }

        // ── Chain flattening: resolve forward chains ────────────────────
        // Any existing redirect X→(source) now points to a redirect source
        // itself. Rewrite those to X→(target) so they skip the hop.
        $this->flatten_forward_chains($source, $target);

        return true;
    }

    /**
     * Follow the redirect chain from a target URL to find the final destination.
     *
     * Prevents infinite loops by capping at 10 hops.
     */
    private function resolve_final_target(string $target, int $max_hops = 10): string
    {
        global $wpdb;

        $current = $target;

        for ($i = 0; $i < $max_hops; $i++) {
            $variants = $this->get_source_path_variants($current);
            if (empty($variants)) {
                break;
            }

            // Try each variant to find a matching source in the redirects table.
            $next = null;
            foreach ($variants as $variant) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $found = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT target_url FROM {$this->table} WHERE source_url = %s AND type = 'redirect' LIMIT 1",
                        $variant
                    )
                );
                if (null !== $found && '' !== $found) {
                    $next = $found;
                    break;
                }
            }

            if (null === $next) {
                break;
            }

            $current = $next;
        }

        return $current;
    }

    /**
     * Convert an absolute URL or full path to the source_url format(s)
     * used in the redirects table.
     *
     * Source URLs may be stored with or without the WP install sub-directory
     * depending on how they were created. This method returns the FULL path
     * (preserving the sub-directory) since that's what handle_request() matches.
     *
     * Example: http://localhost/greencoders/about/ → /greencoders/about/
     *          /greencoders/about/                 → /greencoders/about/
     *          /about/                             → /about/
     *
     * Returns null if no valid path can be derived.
     */
    private function url_to_source_path(string $url): ?string
    {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || '' === $path) {
            return null;
        }

        return trailingslashit('/' . ltrim($path, '/'));
    }

    /**
     * Get all possible source_url variants for a given URL/path.
     *
     * Returns both the full path and the WP-base-stripped path
     * to handle sources stored in either format.
     */
    private function get_source_path_variants(string $url): array
    {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || '' === $path) {
            return array();
        }

        $full = trailingslashit('/' . ltrim($path, '/'));
        $variants = array($full);

        // Also try without the WP install sub-directory.
        $home_path = wp_parse_url(home_url(), PHP_URL_PATH);
        if (is_string($home_path) && '/' !== $home_path) {
            $home_path = rtrim($home_path, '/');
            if (0 === strpos($full, $home_path . '/')) {
                $stripped = trailingslashit(substr($full, strlen($home_path)));
                $variants[] = $stripped;
            }
        }

        return array_unique($variants);
    }

    /**
     * Rewrite any existing redirect whose target matches the given source path
     * so that it points directly to the new final target.
     */
    private function flatten_forward_chains(string $source_path, string $new_target): void
    {
        global $wpdb;

        $site_url  = home_url();
        $home_path = wp_parse_url($site_url, PHP_URL_PATH);
        $home_path = is_string($home_path) ? rtrim($home_path, '/') : '';

        // Build the absolute URL correctly — avoid doubling the WP sub-directory.
        if ('' !== $home_path && 0 === strpos($source_path, $home_path . '/')) {
            // source_path already includes the WP base (e.g., /greencoders/tai/).
            $source_abs = rtrim($site_url, '/') . substr($source_path, strlen($home_path));
        } else {
            $source_abs = rtrim($site_url, '/') . $source_path;
        }

        $source_abs_notrs  = untrailingslashit($source_abs);
        $source_path_notrs = untrailingslashit($source_path);

        $variants = array($source_path, $source_path_notrs, $source_abs, $source_abs_notrs);
        $variants = array_unique(array_filter($variants));

        foreach ($variants as $variant) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $this->table,
                array('target_url' => $new_target),
                array(
                    'target_url' => $variant,
                    'type'       => 'redirect',
                ),
                array('%s'),
                array('%s', '%s')
            );
        }
    }

    /**
     * Build a map of redirect targets → source entries for quick lookup.
     *
     * Returns array keyed by normalised target path, each value is an array of
     * ['source' => source_url, 'id' => redirect_id].
     */
    private function get_redirect_target_map(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT id, source_url, target_url FROM {$this->table} WHERE type = 'redirect'"
        );

        $map = array();

        if (empty($rows)) {
            return $map;
        }

        foreach ($rows as $row) {
            $variants = $this->get_source_path_variants($row->target_url);
            $entry = array(
                'source' => $row->source_url,
                'id'     => (int) $row->id,
            );

            foreach ($variants as $normalised) {
                if (! isset($map[$normalised])) {
                    $map[$normalised] = array();
                }
                $map[$normalised][] = $entry;
            }
        }

        return $map;
    }

    /**
     * Detect redirect chains in the database.
     *
     * Returns an array of chain descriptions, each containing:
     * - 'chain'   => array of [source, target] hops
     * - 'fix'     => the flattened destination each hop should point to
     * - 'ids'     => redirect IDs involved
     */
    public function detect_chains(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $redirects = $wpdb->get_results(
            "SELECT id, source_url, target_url FROM {$this->table} WHERE type = 'redirect'"
        );

        if (empty($redirects)) {
            return array();
        }

        // Build source→target lookup.
        $source_map = array();
        $id_map     = array();
        foreach ($redirects as $r) {
            $source_map[$r->source_url] = $r->target_url;
            $id_map[$r->source_url]     = (int) $r->id;
        }

        $chains   = array();
        $visited  = array();

        foreach ($source_map as $source => $target) {
            if (isset($visited[$source])) {
                continue;
            }

            // Check if the target resolves to a source in the table (using all path variants).
            $target_variants = $this->get_source_path_variants($target);
            $matched_target  = null;
            foreach ($target_variants as $variant) {
                if (isset($source_map[$variant])) {
                    $matched_target = $variant;
                    break;
                }
            }

            if (null === $matched_target) {
                continue;
            }

            // We found a chain. Walk it to the end.
            $hops = array(array('source' => $source, 'target' => $target, 'id' => $id_map[$source]));
            $current = $matched_target;
            $seen    = array($source => true);

            while (isset($source_map[$current]) && ! isset($seen[$current])) {
                $seen[$current] = true;
                $next_target = $source_map[$current];
                $hops[] = array('source' => $current, 'target' => $next_target, 'id' => $id_map[$current]);
                $visited[$current] = true;

                $next_variants = $this->get_source_path_variants($next_target);
                $next_matched  = null;
                foreach ($next_variants as $variant) {
                    if (isset($source_map[$variant])) {
                        $next_matched = $variant;
                        break;
                    }
                }
                if (null === $next_matched) {
                    break;
                }
                $current = $next_matched;
            }

            $final_target = $hops[count($hops) - 1]['target'];

            $chains[] = array(
                'chain' => $hops,
                'fix'   => $final_target,
                'ids'   => array_column($hops, 'id'),
            );

            $visited[$source] = true;
        }

        return $chains;
    }

    /**
     * Fix all detected redirect chains by flattening them.
     *
     * Returns the number of redirects updated.
     */
    public function fix_all_chains(): int
    {
        global $wpdb;

        $chains = $this->detect_chains();
        $fixed  = 0;

        foreach ($chains as $chain_info) {
            $final = $chain_info['fix'];

            // Update all hops except the last one (which already points to final).
            $hops = $chain_info['chain'];
            for ($i = 0, $count = count($hops) - 1; $i < $count; $i++) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $updated = $wpdb->update(
                    $this->table,
                    array('target_url' => $final),
                    array('id' => $hops[$i]['id']),
                    array('%s'),
                    array('%d')
                );
                if ($updated) {
                    $fixed++;
                }
            }
        }

        return $fixed;
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
     * Convert a 404 entry into a redirect (chain-aware).
     */
    public function convert_404_to_redirect(int $id, string $target_url, int $status_code = 301): bool
    {
        global $wpdb;

        // Resolve reverse chains: if target itself redirects elsewhere, skip the hop.
        $target_url = $this->resolve_final_target($target_url);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = (bool) $wpdb->update(
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

        if ($updated) {
            // Get the source for this entry to flatten any forward chains.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $source = $wpdb->get_var(
                $wpdb->prepare("SELECT source_url FROM {$this->table} WHERE id = %d", $id)
            );
            if ($source) {
                $this->flatten_forward_chains($source, $target_url);
            }
        }

        return $updated;
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
     * AJAX: Detect redirect chains.
     */
    public function ajax_detect_chains(): void
    {
        check_ajax_referer('ai_seo_captain_nonce', '_nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $chains = $this->detect_chains();
        wp_send_json_success(array(
            'count'  => count($chains),
            'chains' => $chains,
        ));
    }

    /**
     * AJAX: Fix all redirect chains by flattening.
     */
    public function ajax_fix_chains(): void
    {
        check_ajax_referer('ai_seo_captain_nonce', '_nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $fixed = $this->fix_all_chains();
        wp_send_json_success(array(
            'message' => sprintf('%d redirect(s) flattened.', $fixed),
            'fixed'   => $fixed,
        ));
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

        // Detect chains for the redirects tab indicator.
        $chains      = $this->detect_chains();
        $chain_count = count($chains);

        // Build a set of redirect IDs involved in chains for row highlighting.
        $chain_ids = array();
        foreach ($chains as $chain_info) {
            foreach ($chain_info['ids'] as $rid) {
                $chain_ids[$rid] = true;
            }
        }
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
                <a href="<?php echo esc_url(add_query_arg('tab', 'url_editor')); ?>" class="nav-tab <?php echo 'url_editor' === $active_tab ? 'nav-tab-active' : ''; ?>">URL Editor</a>
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

                <?php if ($chain_count > 0) : ?>
                    <div id="aisc-chain-banner" style="margin-bottom:16px;padding:12px 16px;background:#fff8e1;border:1px solid #dba617;border-left:4px solid #dba617;border-radius:4px;display:flex;align-items:center;gap:12px;">
                        <span class="dashicons dashicons-warning" style="color:#dba617;font-size:20px;"></span>
                        <div style="flex:1;">
                            <strong style="color:#7a5e00;"><?php echo (int) $chain_count; ?> redirect chain(s) detected</strong>
                            <span style="color:#50575e;font-size:12px;margin-left:8px;">Chains cause extra hops and hurt SEO. Click "Fix All" to flatten them — each redirect will point directly to the final destination.</span>
                        </div>
                        <button type="button" class="button button-primary" id="aisc-fix-chains-btn" style="white-space:nowrap;">
                            <span class="dashicons dashicons-admin-tools" style="margin-top:4px;"></span> Fix All
                        </button>
                    </div>
                <?php endif; ?>

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
                            <?php foreach ($redirects as $r) :
                                $is_chain = isset($chain_ids[(int) $r->id]);
                            ?>
                                <tr data-id="<?php echo (int) $r->id; ?>"<?php echo $is_chain ? ' style="background:#fff8e1;"' : ''; ?>>
                                    <td>
                                        <code><?php echo esc_html($r->source_url); ?></code>
                                        <?php if ($is_chain) : ?>
                                            <span title="Part of a redirect chain" style="display:inline-block;background:#dba617;color:#fff;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;margin-left:4px;vertical-align:middle;cursor:help;">CHAIN</span>
                                        <?php endif; ?>
                                    </td>
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
                            <?php elseif ('stale' === ($scan_state['phase'] ?? '')) : ?>
                                Previous scan timed out. Click Scan Now to start a fresh scan.
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
                                        <?php echo self::render_type_badge($url_category); // phpcs:ignore 
                                        ?>
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

            <?php if ('url_editor' === $active_tab) : ?>
                <?php $this->render_url_editor_tab(); ?>
            <?php endif; ?>
        </div>
<?php
    }

    // ─── URL Editor Tab ─────────────────────────────────────────────────

    /**
     * Render the URL Editor tab content.
     */
    private function render_url_editor_tab(): void
    {
        $post_types = get_post_types(array('public' => true), 'objects');
        unset($post_types['attachment']);

        $pt_filter = isset($_GET['pt']) ? sanitize_key($_GET['pt']) : '';

        $query_args = array(
            'post_type'      => '' !== $pt_filter && isset($post_types[$pt_filter]) ? $pt_filter : array_keys($post_types),
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        $query = new \WP_Query($query_args);
        $nonce = wp_create_nonce('ai_seo_captain_nonce');

        // Pre-build lookup: which published page URLs are redirect targets?
        // This lets us show an indicator so users know a page already has redirects pointing to it.
        $redirect_targets = $this->get_redirect_target_map();
?>
        <div style="margin-bottom:20px; padding:16px; background:#fff; border:1px solid #ccd0d4;">
            <h3 style="margin-top:0;">Bulk URL Editor</h3>
            <p style="color:#555;">Change page/post URL slugs in bulk. Optionally create 301 redirects from old URLs to new ones automatically. Only rows with a valid "Change to" value will be processed.</p>

            <?php
            echo \AI_SEO_Captain\Admin::render_banner(
                'is-warning',
                esc_html__('Caution', 'ai-seo-captain'),
                esc_html__('Changing URLs affects SEO and existing links. Always enable "Auto-redirect" to preserve link equity. Changes are applied immediately after confirmation.', 'ai-seo-captain')
            );
            ?>

            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:16px 0;">
                <div style="display:flex;gap:6px;">
                    <a href="<?php echo esc_url(add_query_arg(array('tab' => 'url_editor', 'pt' => ''), remove_query_arg('pt'))); ?>" class="button <?php echo '' === $pt_filter ? 'button-primary' : ''; ?>">All</a>
                    <?php foreach ($post_types as $pt_slug => $pt_obj) : ?>
                        <a href="<?php echo esc_url(add_query_arg(array('tab' => 'url_editor', 'pt' => $pt_slug))); ?>" class="button <?php echo $pt_slug === $pt_filter ? 'button-primary' : ''; ?>"><?php echo esc_html($pt_obj->labels->name); ?></a>
                    <?php endforeach; ?>
                </div>
                <div style="flex:1;min-width:200px;max-width:400px;">
                    <input type="text" id="aisc-url-editor-search" placeholder="<?php esc_attr_e('Search by title or slug…', 'ai-seo-captain'); ?>" style="width:100%;padding:6px 10px;font-size:13px;border:1px solid #8c8f94;border-radius:4px;" />
                </div>
            </div>

            <div style="margin-bottom:12px;display:flex;align-items:center;gap:12px;">
                <button type="button" class="button button-primary" id="aisc-url-apply-btn" disabled>
                    <span class="dashicons dashicons-yes-alt" style="margin-top:4px;"></span> Apply Changes
                </button>
                <span id="aisc-url-apply-status" style="color:#666;font-size:13px;"></span>
            </div>
        </div>

        <?php if ($query->have_posts()) : ?>
            <table class="widefat striped ai-seo-sortable" id="aisc-url-editor-table" style="table-layout:fixed;">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th style="width:22%;" class="ai-seo-sort" data-col="1"><?php esc_html_e('Page Title', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th style="width:16%;" class="ai-seo-sort" data-col="2"><?php esc_html_e('Current Slug', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                        <th style="width:22%;"><?php esc_html_e('Change to', 'ai-seo-captain'); ?></th>
                        <th style="width:80px;text-align:center;"><?php esc_html_e('Redirect', 'ai-seo-captain'); ?></th>
                        <th style="width:110px;text-align:center;"><?php esc_html_e('Update refs', 'ai-seo-captain'); ?></th>
                        <th style="width:8%;" class="ai-seo-sort" data-col="6"><?php esc_html_e('Type', 'ai-seo-captain'); ?> <span class="ai-seo-sort-icon dashicons dashicons-sort"></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($query->have_posts()) : $query->the_post();
                        $post_id   = get_the_ID();
                        $title     = get_the_title();
                        $slug      = get_post_field('post_name', $post_id);
                        $post_type = get_post_type($post_id);
                        $permalink = get_permalink($post_id);
                        $pt_label  = isset($post_types[$post_type]) ? $post_types[$post_type]->labels->singular_name : $post_type;

                        // Get the parent path portion (everything before the slug).
                        $parsed   = wp_parse_url($permalink, PHP_URL_PATH);
                        $path_dir = $parsed ? trailingslashit(dirname($parsed)) : '/';

                        // Check if this page has redirects pointing to it.
                        $incoming_redirects = array();
                        if ($parsed) {
                            $norm_path = trailingslashit($parsed);
                            if (isset($redirect_targets[$norm_path])) {
                                $incoming_redirects = $redirect_targets[$norm_path];
                            }
                        }
                        $has_redirects = ! empty($incoming_redirects);
                    ?>
                        <tr data-post-id="<?php echo (int) $post_id; ?>" data-original-slug="<?php echo esc_attr($slug); ?>" data-path-prefix="<?php echo esc_attr($path_dir); ?>"<?php echo $has_redirects ? ' data-has-redirects="1"' : ''; ?>>
                            <td><input type="checkbox" class="aisc-url-row-check" /></td>
                            <td data-sort-value="<?php echo esc_attr(strtolower($title)); ?>">
                                <strong><?php echo esc_html($title); ?></strong>
                                <?php if ($has_redirects) : ?>
                                    <span class="aisc-url-redirect-badge" title="<?php echo esc_attr(sprintf(
                                        /* translators: %d = number of redirects */
                                        _n('%d redirect points to this page', '%d redirects point to this page', count($incoming_redirects), 'ai-seo-captain'),
                                        count($incoming_redirects)
                                    ) . ': ' . esc_attr(implode(', ', array_column($incoming_redirects, 'source')))); ?>" style="display:inline-block;background:#dba617;color:#fff;font-size:10px;font-weight:600;padding:1px 6px;border-radius:3px;margin-left:6px;vertical-align:middle;cursor:help;">⇐ <?php echo count($incoming_redirects); ?></span>
                                <?php endif; ?>
                                <div style="margin-top:2px;">
                                    <a href="<?php echo esc_url($permalink); ?>" target="_blank" style="font-size:11px;color:#50575e;word-break:break-all;"><?php echo esc_html($parsed); ?></a>
                                </div>
                            </td>
                            <td data-sort-value="<?php echo esc_attr(strtolower($slug)); ?>">
                                <code style="font-size:12px;background:#f0f0f1;padding:2px 6px;border-radius:3px;"><?php echo esc_html($slug); ?></code>
                            </td>
                            <td>
                                <input type="text" class="regular-text aisc-url-new-slug" value="" placeholder="<?php echo esc_attr($slug); ?>" style="width:100%;font-size:12px;" data-original="<?php echo esc_attr($slug); ?>" />
                                <span class="aisc-url-validation" style="display:none;font-size:11px;margin-top:2px;"></span>
                            </td>
                            <td style="text-align:center;">
                                <input type="checkbox" class="aisc-url-auto-redirect" />
                            </td>
                            <td style="text-align:center;">
                                <input type="checkbox" class="aisc-url-update-refs" />
                                <button type="button" class="aisc-url-preview-refs" title="<?php esc_attr_e('Preview references to this URL', 'ai-seo-captain'); ?>" style="background:none;border:none;cursor:pointer;padding:2px;vertical-align:middle;color:#2271b1;font-size:14px;">
                                    <span class="dashicons dashicons-search" style="font-size:16px;width:16px;height:16px;"></span>
                                </button>
                            </td>
                            <td data-sort-value="<?php echo esc_attr(strtolower($pt_label)); ?>">
                                <span style="font-size:12px;"><?php echo esc_html($pt_label); ?></span>
                            </td>
                        </tr>
                    <?php endwhile;
                    wp_reset_postdata(); ?>
                </tbody>
            </table>
            <div id="aisc-url-editor-pagination" class="aisc-pagination" style="margin-top:16px;text-align:center;"></div>
        <?php else : ?>
            <p><?php esc_html_e('No published content found.', 'ai-seo-captain'); ?></p>
        <?php endif; ?>

        <!-- Confirmation Modal -->
        <div id="aisc-url-confirm-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:100010;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:8px;padding:24px 32px;max-width:560px;width:90%;box-shadow:0 4px 20px rgba(0,0,0,.2);">
                <h3 style="margin-top:0;color:#1d2327;">Confirm URL Changes</h3>
                <p style="color:#50575e;">You are about to change <strong id="aisc-url-confirm-count">0</strong> URL slug(s). This will:</p>
                <ul style="color:#50575e;margin-left:18px;">
                    <li>Update the page permalink in the database</li>
                    <li>Create 301 redirects from old URLs (if checked)</li>
                    <li>Update internal references across the database (if checked)</li>
                    <li>Clear relevant page caches</li>
                </ul>
                <div id="aisc-url-confirm-list" style="max-height:200px;overflow-y:auto;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:10px;margin:12px 0;font-size:12px;"></div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
                    <button type="button" class="button" id="aisc-url-confirm-cancel">Cancel</button>
                    <button type="button" class="button button-primary" id="aisc-url-confirm-apply" style="background:#d63638;border-color:#b32d2e;">Apply Changes</button>
                </div>
            </div>
        </div>
        <input type="hidden" id="aisc-url-editor-nonce" value="<?php echo esc_attr($nonce); ?>" />

        <!-- References Preview Modal -->
        <div id="aisc-url-refs-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:100020;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:8px;padding:24px 32px;max-width:820px;width:90%;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 4px 20px rgba(0,0,0,.2);">
                <h3 id="aisc-refs-modal-title" style="margin-top:0;color:#1d2327;">References</h3>
                <p style="color:#50575e;font-size:12px;margin-bottom:12px;">
                    URL path: <code id="aisc-refs-modal-path" style="font-size:11px;"></code>
                </p>
                <div id="aisc-refs-modal-body" style="overflow-y:auto;flex:1;min-height:0;"></div>
                <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                    <button type="button" class="button" id="aisc-refs-modal-close">Close</button>
                </div>
            </div>
        </div>

        <style>
            .aisc-url-preview-refs .dashicons.spin {
                animation: aisc-spin 1s linear infinite;
            }
            @keyframes aisc-spin {
                100% { transform: rotate(360deg); }
            }
        </style>
<?php
    }

    /**
     * AJAX: Apply bulk URL slug changes.
     */
    public function ajax_bulk_url_change(): void
    {
        check_ajax_referer('ai_seo_captain_nonce', '_nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }

        $raw = isset($_POST['changes']) ? wp_unslash($_POST['changes']) : '';
        $changes = json_decode($raw, true);

        if (! is_array($changes) || empty($changes)) {
            wp_send_json_error(array('message' => 'No valid changes provided.'));
        }

        $results = array();
        $errors  = array();

        foreach ($changes as $change) {
            $post_id       = isset($change['post_id']) ? (int) $change['post_id'] : 0;
            $new_slug      = isset($change['new_slug']) ? sanitize_title($change['new_slug']) : '';
            $old_slug      = isset($change['old_slug']) ? sanitize_text_field($change['old_slug']) : '';
            $auto_redirect = ! empty($change['auto_redirect']);
            $update_refs   = ! empty($change['update_refs']);
            $path_prefix   = isset($change['path_prefix']) ? sanitize_text_field($change['path_prefix']) : '/';

            if ($post_id < 1 || '' === $new_slug) {
                $errors[] = sprintf('Invalid data for post ID %d.', $post_id);
                continue;
            }

            // Verify post exists and is published.
            $post = get_post($post_id);
            if (! $post || 'publish' !== $post->post_status) {
                $errors[] = sprintf('Post ID %d not found or not published.', $post_id);
                continue;
            }

            // Skip if slug hasn't actually changed.
            if ($new_slug === $post->post_name) {
                continue;
            }

            // Generate a unique slug to avoid collisions.
            $unique_slug = wp_unique_post_slug($new_slug, $post_id, $post->post_status, $post->post_type, $post->post_parent);

            // Update the post slug.
            $update_result = wp_update_post(array(
                'ID'        => $post_id,
                'post_name' => $unique_slug,
            ), true);

            if (is_wp_error($update_result)) {
                $errors[] = sprintf('Failed to update "%s": %s', $post->post_title, $update_result->get_error_message());
                continue;
            }

            // Create redirect from old URL to new URL.
            if ($auto_redirect && '' !== $old_slug) {
                $old_path = trailingslashit($path_prefix . $old_slug);
                $new_url  = get_permalink($post_id); // Gets the fresh permalink after slug change.
                $this->add_redirect($old_path, $new_url, 301);
            }

            // Update all internal references to the old URL.
            $ref_counts = array('posts' => 0, 'postmeta' => 0, 'options' => 0);
            if ($update_refs && '' !== $old_slug) {
                $old_path = trailingslashit($path_prefix . $old_slug);
                $new_path = trailingslashit($path_prefix . $unique_slug);
                $ref_counts = $this->update_db_references($old_path, $new_path);
            }

            // Clear caches for this post.
            clean_post_cache($post_id);

            $results[] = array(
                'post_id'     => $post_id,
                'title'       => $post->post_title,
                'old_slug'    => $old_slug,
                'new_slug'    => $unique_slug,
                'permalink'   => get_permalink($post_id),
                'redirect'    => $auto_redirect,
                'refs_updated' => $ref_counts,
            );
        }

        wp_send_json_success(array(
            'message'  => sprintf('%d URL(s) updated successfully.', count($results)),
            'updated'  => $results,
            'errors'   => $errors,
        ));
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

    // ─────────────────────────────────────────────────────────────────────────
    // Reference Preview & Update — Serialization-aware URL replacement
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * AJAX: Preview all DB references to a given URL path (read-only scan).
     */
    public function ajax_preview_refs(): void
    {
        check_ajax_referer('ai_seo_captain_nonce', '_nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }

        $source_path = isset($_POST['source_path']) ? sanitize_text_field(wp_unslash($_POST['source_path'])) : '';
        if ('' === $source_path) {
            wp_send_json_error(array('message' => 'No source path provided.'));
        }

        $results = $this->search_db_references($source_path);

        wp_send_json_success(array(
            'source_path' => $source_path,
            'total'       => count($results),
            'references'  => $results,
        ));
    }

    /**
     * Search the database for all references to a URL path.
     *
     * Searches both the full absolute URL and the relative path,
     * across posts, postmeta, and options tables.
     *
     * @param string $relative_path e.g. "/greencoders/legal/terms-of-use/"
     * @param int    $limit         Max results to return.
     * @return array List of reference items.
     */
    private function search_db_references(string $relative_path, int $limit = 100): array
    {
        global $wpdb;

        // Build search variants: relative path + absolute URL.
        $site_url     = home_url();
        $absolute_url = rtrim($site_url, '/') . $relative_path;
        $no_trail_rel = untrailingslashit($relative_path);
        $no_trail_abs = untrailingslashit($absolute_url);

        // We search for any of these patterns.
        $like_patterns = array_unique(array(
            '%' . $wpdb->esc_like($relative_path) . '%',
            '%' . $wpdb->esc_like($absolute_url) . '%',
            '%' . $wpdb->esc_like($no_trail_rel) . '%',
            '%' . $wpdb->esc_like($no_trail_abs) . '%',
        ));

        $results = array();
        $count   = 0;

        // 1. Search posts.post_content ────────────────────────────────────
        $where_parts = array();
        $where_args  = array();
        foreach ($like_patterns as $pattern) {
            $where_parts[] = 'post_content LIKE %s';
            $where_args[]  = $pattern;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_type, post_content
                 FROM {$wpdb->posts}
                 WHERE post_status IN ('publish','draft','pending','private','future')
                   AND (" . implode(' OR ', $where_parts) . ")
                 LIMIT %d",
                array_merge($where_args, array($limit))
            )
        );

        if ($rows) {
            foreach ($rows as $row) {
                $snippets = $this->extract_context_snippets($row->post_content, $relative_path, $absolute_url);
                $results[] = array(
                    'table'   => 'posts',
                    'column'  => 'post_content',
                    'row_id'  => (int) $row->ID,
                    'label'   => $row->post_title . ' (' . $row->post_type . ')',
                    'count'   => count($snippets),
                    'snippets' => array_slice($snippets, 0, 5),
                );
                $count += count($snippets);
                if ($count >= $limit) {
                    break;
                }
            }
        }

        // 2. Search postmeta.meta_value ───────────────────────────────────
        if ($count < $limit) {
            $where_parts = array();
            $where_args  = array();
            foreach ($like_patterns as $pattern) {
                $where_parts[] = 'pm.meta_value LIKE %s';
                $where_args[]  = $pattern;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $meta_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value, p.post_title
                     FROM {$wpdb->postmeta} AS pm
                     LEFT JOIN {$wpdb->posts} AS p ON pm.post_id = p.ID
                     WHERE (" . implode(' OR ', $where_parts) . ")
                     LIMIT %d",
                    array_merge($where_args, array($limit - $count))
                )
            );

            if ($meta_rows) {
                foreach ($meta_rows as $row) {
                    $value    = $row->meta_value;
                    $snippets = $this->extract_context_snippets($value, $relative_path, $absolute_url);
                    $label    = ($row->post_title ?: 'Post #' . $row->post_id) . ' → meta: ' . $row->meta_key;
                    $results[] = array(
                        'table'   => 'postmeta',
                        'column'  => 'meta_value',
                        'row_id'  => (int) $row->meta_id,
                        'label'   => $label,
                        'count'   => count($snippets),
                        'snippets' => array_slice($snippets, 0, 3),
                    );
                    $count += count($snippets);
                    if ($count >= $limit) {
                        break;
                    }
                }
            }
        }

        // 3. Search options.option_value ──────────────────────────────────
        if ($count < $limit) {
            $where_parts = array();
            $where_args  = array();
            foreach ($like_patterns as $pattern) {
                $where_parts[] = 'option_value LIKE %s';
                $where_args[]  = $pattern;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $opt_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_id, option_name, option_value
                     FROM {$wpdb->options}
                     WHERE (" . implode(' OR ', $where_parts) . ")
                       AND option_name NOT LIKE '\\_transient%'
                       AND option_name NOT LIKE '%%\\_log'
                       AND option_name NOT LIKE '%%\\_log\\_%%'
                       AND option_name NOT LIKE '%%\\_cache%%'
                       AND option_name NOT LIKE '%%\\_cron%%'
                     LIMIT %d",
                    array_merge($where_args, array($limit - $count))
                )
            );

            if ($opt_rows) {
                foreach ($opt_rows as $row) {
                    $snippets = $this->extract_context_snippets($row->option_value, $relative_path, $absolute_url);
                    $results[] = array(
                        'table'   => 'options',
                        'column'  => 'option_value',
                        'row_id'  => (int) $row->option_id,
                        'label'   => 'Option: ' . $row->option_name,
                        'count'   => count($snippets),
                        'snippets' => array_slice($snippets, 0, 3),
                    );
                    $count += count($snippets);
                    if ($count >= $limit) {
                        break;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Extract the exact URL variants found in a text, with occurrence counts.
     * Returns the precise strings that will be matched by str_replace during update.
     */
    private function extract_context_snippets(string $text, string $relative_path, string $absolute_url): array
    {
        $snippets = array();
        $searches = array_unique(array(
            $absolute_url,
            untrailingslashit($absolute_url),
            $relative_path,
            untrailingslashit($relative_path),
        ));

        foreach ($searches as $needle) {
            $count = substr_count($text, $needle);
            if ($count > 0) {
                $snippets[] = $needle . '  ×' . $count;
            }
        }

        return $snippets;
    }

    /**
     * Replace all URL references in the database for a single old→new path change.
     * Serialization-aware: safely handles serialized PHP data.
     *
     * @param string $old_path Old relative path (e.g. /greencoders/legal/old-slug/).
     * @param string $new_path New relative path (e.g. /greencoders/legal/new-slug/).
     * @return array Summary: ['posts' => int, 'postmeta' => int, 'options' => int].
     */
    private function update_db_references(string $old_path, string $new_path): array
    {
        global $wpdb;

        $site_url = home_url();
        $old_abs  = rtrim($site_url, '/') . $old_path;
        $new_abs  = rtrim($site_url, '/') . $new_path;

        // Replacement pairs: search → replace (absolute first, then relative).
        $pairs = array(
            $old_abs                      => $new_abs,
            untrailingslashit($old_abs)   => untrailingslashit($new_abs),
            $old_path                     => $new_path,
            untrailingslashit($old_path)  => untrailingslashit($new_path),
        );

        $counts = array('posts' => 0, 'postmeta' => 0, 'options' => 0);

        // Build LIKE conditions for finding rows.
        $like_patterns = array();
        foreach (array_keys($pairs) as $search) {
            $like_patterns[] = '%' . $wpdb->esc_like($search) . '%';
        }

        // 1. posts.post_content ───────────────────────────────────────────
        $where_parts = array();
        $where_args  = array();
        foreach ($like_patterns as $pattern) {
            $where_parts[] = 'post_content LIKE %s';
            $where_args[]  = $pattern;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $post_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts}
                 WHERE post_status IN ('publish','draft','pending','private','future')
                   AND (" . implode(' OR ', $where_parts) . ")
                 LIMIT 500",
                $where_args
            )
        );

        if ($post_rows) {
            foreach ($post_rows as $row) {
                $new_content = $row->post_content;
                foreach ($pairs as $search => $replace) {
                    $new_content = str_replace($search, $replace, $new_content);
                }
                if ($new_content !== $row->post_content) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update($wpdb->posts, array('post_content' => $new_content), array('ID' => $row->ID));
                    clean_post_cache((int) $row->ID);
                    $counts['posts']++;
                }
            }
        }

        // 2. postmeta.meta_value (serialization-aware) ────────────────────
        $where_parts = array();
        $where_args  = array();
        foreach ($like_patterns as $pattern) {
            $where_parts[] = 'meta_value LIKE %s';
            $where_args[]  = $pattern;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $meta_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_id, meta_value FROM {$wpdb->postmeta}
                 WHERE (" . implode(' OR ', $where_parts) . ")
                 LIMIT 1000",
                $where_args
            )
        );

        if ($meta_rows) {
            foreach ($meta_rows as $row) {
                $new_value = $this->safe_replace_value($row->meta_value, $pairs);
                if ($new_value !== $row->meta_value) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update($wpdb->postmeta, array('meta_value' => $new_value), array('meta_id' => $row->meta_id));
                    $counts['postmeta']++;
                }
            }
        }

        // 3. options.option_value (serialization-aware) ───────────────────
        $where_parts = array();
        $where_args  = array();
        foreach ($like_patterns as $pattern) {
            $where_parts[] = 'option_value LIKE %s';
            $where_args[]  = $pattern;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $opt_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_id, option_name, option_value FROM {$wpdb->options}
                 WHERE (" . implode(' OR ', $where_parts) . ")
                   AND option_name NOT LIKE '\\_transient%'
                   AND option_name NOT LIKE '%%\\_log'
                   AND option_name NOT LIKE '%%\\_log\\_%%'
                   AND option_name NOT LIKE '%%\\_cache%%'
                   AND option_name NOT LIKE '%%\\_cron%%'
                 LIMIT 200",
                $where_args
            )
        );

        if ($opt_rows) {
            foreach ($opt_rows as $row) {
                $new_value = $this->safe_replace_value($row->option_value, $pairs);
                if ($new_value !== $row->option_value) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update($wpdb->options, array('option_value' => $new_value), array('option_id' => $row->option_id));
                    $counts['options']++;
                }
            }
        }

        // Flush object cache after bulk changes.
        wp_cache_flush();

        return $counts;
    }

    /**
     * Replace strings inside a value, handling serialized data safely.
     * If the value is serialized, it unserializes → replaces recursively → reserializes.
     *
     * @param string $value The raw DB value.
     * @param array  $pairs Search→replace pairs.
     * @return string The updated value.
     */
    private function safe_replace_value(string $value, array $pairs): string
    {
        if (is_serialized($value)) {
            $unserialized = @unserialize($value);
            if (false !== $unserialized || 'b:0;' === $value) {
                $replaced = $this->recursive_replace($unserialized, $pairs);
                return serialize($replaced);
            }
        }

        // Plain string — simple str_replace.
        $result = $value;
        foreach ($pairs as $search => $replace) {
            $result = str_replace($search, $replace, $result);
        }
        return $result;
    }

    /**
     * Recursively replace strings inside arrays, objects, and strings.
     *
     * @param mixed $data  The unserialized data structure.
     * @param array $pairs Search→replace pairs.
     * @return mixed The data with replacements applied.
     */
    private function recursive_replace($data, array $pairs)
    {
        if (is_string($data)) {
            // Check if this string is itself serialized (nested serialization).
            if (is_serialized($data)) {
                $nested = @unserialize($data);
                if (false !== $nested || 'b:0;' === $data) {
                    $nested = $this->recursive_replace($nested, $pairs);
                    return serialize($nested);
                }
            }
            foreach ($pairs as $search => $replace) {
                $data = str_replace($search, $replace, $data);
            }
            return $data;
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->recursive_replace($value, $pairs);
            }
            return $data;
        }

        if (is_object($data)) {
            foreach (get_object_vars($data) as $prop => $value) {
                $data->$prop = $this->recursive_replace($value, $pairs);
            }
            return $data;
        }

        return $data;
    }
}
