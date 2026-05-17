<?php

namespace AI_SEO_Captain\Cache;

/**
 * Central orchestrator — coordinates all cache subsystems.
 */
class Cache_Manager
{

    /** @var \AI_SEO_Captain\Settings */
    private $settings;

    /** @var Page_Cache */
    private $page_cache;

    /** @var Cache_Invalidator */
    private $invalidator;

    /** @var Browser_Cache */
    private $browser_cache;

    /** @var Object_Cache */
    private $object_cache;

    /** @var Cache_Preloader */
    private $preloader;

    /** @var Minifier */
    private $minifier;

    /** @var Lazy_Loader */
    private $lazy_loader;

    /** @var array Cached options. */
    private $options;

    public function __construct(\AI_SEO_Captain\Settings $settings)
    {
        $this->settings = $settings;
        $this->options  = $settings->get();
    }

    /**
     * Boot: register all hooks if cache is enabled.
     * AJAX handlers are always registered so the admin UI works regardless of cache state.
     */
    public function boot(): void
    {
        // AJAX handlers must always be available (admin UI needs them even when cache is off).
        add_action('wp_ajax_aisc_purge_cache', array($this, 'ajax_purge_cache'));
        add_action('wp_ajax_aisc_preload_cache', array($this, 'ajax_preload_cache'));
        add_action('wp_ajax_aisc_cache_status', array($this, 'ajax_cache_status'));
        add_action('wp_ajax_aisc_install_dropin', array($this, 'ajax_install_dropin'));
        add_action('wp_ajax_aisc_remove_dropin', array($this, 'ajax_remove_dropin'));
        add_action('wp_ajax_aisc_install_htaccess', array($this, 'ajax_install_htaccess'));
        add_action('wp_ajax_aisc_remove_htaccess', array($this, 'ajax_remove_htaccess'));
        add_action('wp_ajax_aisc_purge_this_url', array($this, 'ajax_purge_this_url'));
        add_action('wp_ajax_aisc_purge_post_cache', array($this, 'ajax_purge_post_cache'));

        if (empty($this->options['cache_enabled'])) {
            return;
        }

        $opts = $this->options;

        // Instantiate sub-systems.
        $this->page_cache    = new Page_Cache($opts);
        $this->preloader     = new Cache_Preloader(
            isset($opts['cache_preload_batch_size']) ? (int) $opts['cache_preload_batch_size'] : 5
        );
        $this->invalidator   = new Cache_Invalidator(
            $this->page_cache,
            ! empty($opts['cache_preload_enabled']) ? $this->preloader : null,
            ! empty($opts['cache_preload_enabled'])
        );
        $this->browser_cache = new Browser_Cache($opts);
        $this->object_cache  = new Object_Cache($opts);
        $this->minifier      = new Minifier($opts);
        $this->lazy_loader   = new Lazy_Loader(
            isset($opts['cache_lazy_skip_count']) ? (int) $opts['cache_lazy_skip_count'] : 2
        );

        // Page cache — capture output on frontend.
        if (! empty($opts['cache_page_enabled']) && ! is_admin()) {
            add_action('template_redirect', array($this->page_cache, 'start_capture'), 0);
        }

        // Invalidation hooks.
        $this->invalidator->register_hooks();

        // Browser cache headers.
        if (! empty($opts['cache_browser_enabled']) && ! is_admin()) {
            add_action('send_headers', array($this->browser_cache, 'set_page_headers'));
        }

        // Preloader cron hook.
        if (! empty($opts['cache_preload_enabled'])) {
            $this->preloader->register_hooks();
        }

        // Minification.
        if (! empty($opts['cache_minify_css']) || ! empty($opts['cache_minify_js'])) {
            $this->minifier->register_hooks();
        }

        // Lazy loading.
        if (! empty($opts['cache_lazy_load'])) {
            $this->lazy_loader->register_hooks();
        }

        // GZIP compression via output buffer (PHP-level fallback).
        if (! empty($opts['cache_gzip_enabled']) && ! is_admin()) {
            add_action('template_redirect', array($this, 'maybe_start_gzip'), -1);
        }

        // Admin bar purge button.
        add_action('admin_bar_menu', array($this, 'add_admin_bar_purge'), 100);
    }

    /**
     * Start GZIP output buffering if the client accepts it.
     */
    public function maybe_start_gzip(): void
    {
        if (headers_sent()) {
            return;
        }

        if (! isset($_SERVER['HTTP_ACCEPT_ENCODING']) || false === strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
            return;
        }

        if (! function_exists('ob_gzhandler')) {
            return;
        }

        ob_start('ob_gzhandler');
    }

    /**
     * Master purge: clear everything.
     */
    public function purge_all(): void
    {
        if (null !== $this->page_cache) {
            $this->page_cache->purge_all();
        }

        if (null !== $this->object_cache) {
            $this->object_cache->flush();
        }

        update_option('aisc_cache_last_purge', time());
    }

    /**
     * Selective purge: clear specific URL.
     */
    public function purge_url(string $url): void
    {
        if (null !== $this->page_cache) {
            $this->page_cache->purge($url);
        }
    }

    /**
     * Selective purge: clear multiple URLs.
     */
    public function purge_urls(array $urls): void
    {
        foreach ($urls as $url) {
            $this->purge_url($url);
        }
    }

    /**
     * Preload after purge.
     */
    public function preload(): void
    {
        if (null !== $this->preloader) {
            $this->preloader->preload_all();
        }
    }

    /**
     * Install a drop-in file.
     *
     * @param string $type 'advanced-cache' or 'object-cache'.
     */
    public function install_dropin(string $type): bool
    {
        if ('advanced-cache' === $type) {
            return $this->install_advanced_cache_dropin();
        }

        if ('object-cache' === $type) {
            if (null === $this->object_cache) {
                $this->object_cache = new Object_Cache($this->options);
            }
            return $this->object_cache->install();
        }

        return false;
    }

    /**
     * Remove a drop-in file.
     *
     * @param string $type 'advanced-cache' or 'object-cache'.
     */
    public function remove_dropin(string $type): bool
    {
        if ('advanced-cache' === $type) {
            return $this->remove_advanced_cache_dropin();
        }

        if ('object-cache' === $type) {
            if (null === $this->object_cache) {
                $this->object_cache = new Object_Cache($this->options);
            }
            return $this->object_cache->remove();
        }

        return false;
    }

    /**
     * Get cache status for the admin UI.
     *
     * Works even when cache is disabled (subsystems not instantiated).
     */
    public function get_status(): array
    {
        // Lazy-init page cache for stats if not yet created.
        $pc = $this->page_cache;
        if (null === $pc) {
            $pc = new Page_Cache($this->options);
        }
        $page_stats = $pc->get_stats();

        $oc = null !== $this->object_cache ? $this->object_cache : new Object_Cache($this->options);
        $object_stats = $oc->get_stats();

        $advanced_cache_ours = $this->is_advanced_cache_ours();
        $object_cache_ours   = $oc->is_ours();

        $bc = null !== $this->browser_cache ? $this->browser_cache : new Browser_Cache($this->options);
        $htaccess_installed = $bc->is_htaccess_installed();

        $wp_cache_enabled = defined('WP_CACHE') && WP_CACHE;

        $preload_progress = null !== $this->preloader
            ? $this->preloader->get_progress()
            : array('total' => 0, 'done' => 0, 'running' => false);

        return array(
            'enabled'              => ! empty($this->options['cache_enabled']),
            'page_cache_files'     => $page_stats['files'],
            'page_cache_size'      => $page_stats['size'],
            'object_cache_files'   => $object_stats['files'],
            'object_cache_size'    => $object_stats['size'],
            'advanced_cache'       => $advanced_cache_ours,
            'object_cache_dropin'  => $object_cache_ours,
            'wp_cache_constant'    => $wp_cache_enabled,
            'htaccess_installed'   => $htaccess_installed,
            'last_purge'           => (int) get_option('aisc_cache_last_purge', 0),
            'preload_progress'     => $preload_progress,
        );
    }

    /**
     * Admin bar: add purge cache buttons.
     *
     * @param \WP_Admin_Bar $admin_bar
     */
    public function add_admin_bar_purge($admin_bar): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $admin_bar->add_node(array(
            'id'    => 'aisc-cache',
            'title' => '<span class="ab-icon dashicons dashicons-performance" style="font-size:16px;margin-top:3px;"></span> ' . __('SEO Captain Cache', 'ai-seo-captain'),
            'href'  => '#',
        ));

        $admin_bar->add_node(array(
            'parent' => 'aisc-cache',
            'id'     => 'aisc-purge-all',
            'title'  => __('Purge All Cache', 'ai-seo-captain'),
            'href'   => '#',
            'meta'   => array(
                'class' => 'aisc-purge-all-trigger',
            ),
        ));

        // On singular views, add "Purge This Page".
        if (! is_admin() && is_singular()) {
            $admin_bar->add_node(
                array(
                    'parent' => 'aisc-cache',
                    'id'     => 'aisc-purge-this',
                    'title'  => __('Purge This Page', 'ai-seo-captain'),
                    'href'   => '#',
                    'meta'   => array(
                        'class'          => 'aisc-purge-this-trigger',
                        'data-url'       => esc_url(get_permalink()),
                    ),
                ),
            );
        }
    }

    // ───────────────────────────────────────────────────────────
    // AJAX Handlers
    // ───────────────────────────────────────────────────────────

    public function ajax_purge_cache(): void
    {
        check_ajax_referer('aisc_cache_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $this->purge_all();

        wp_send_json_success(array('message' => __('All cache purged successfully.', 'ai-seo-captain')));
    }

    public function ajax_preload_cache(): void
    {
        check_ajax_referer('aisc_cache_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $this->preload();

        wp_send_json_success(array('message' => __('Cache preload started.', 'ai-seo-captain')));
    }

    public function ajax_cache_status(): void
    {
        check_ajax_referer('aisc_cache_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        wp_send_json_success($this->get_status());
    }

    public function ajax_install_dropin(): void
    {
        check_ajax_referer('aisc_cache_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $type = isset($_POST['dropin_type']) ? sanitize_key($_POST['dropin_type']) : '';

        if (! in_array($type, array('advanced-cache', 'object-cache'), true)) {
            wp_send_json_error(array('message' => 'Invalid drop-in type.'));
        }

        $result = $this->install_dropin($type);

        if ($result) {
            wp_send_json_success(array('message' => sprintf(__('%s drop-in installed.', 'ai-seo-captain'), $type)));
        } else {
            wp_send_json_error(array('message' => sprintf(__('Failed to install %s drop-in.', 'ai-seo-captain'), $type)));
        }
    }

    public function ajax_remove_dropin(): void
    {
        check_ajax_referer('aisc_cache_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $type = isset($_POST['dropin_type']) ? sanitize_key($_POST['dropin_type']) : '';

        if (! in_array($type, array('advanced-cache', 'object-cache'), true)) {
            wp_send_json_error(array('message' => 'Invalid drop-in type.'));
        }

        $result = $this->remove_dropin($type);

        if ($result) {
            wp_send_json_success(array('message' => sprintf(__('%s drop-in removed.', 'ai-seo-captain'), $type)));
        } else {
            wp_send_json_error(array('message' => sprintf(__('Failed to remove %s drop-in.', 'ai-seo-captain'), $type)));
        }
    }

    public function ajax_install_htaccess(): void
    {
        check_ajax_referer('aisc_cache_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        if (null === $this->browser_cache) {
            $this->browser_cache = new Browser_Cache($this->options);
        }

        $result = $this->browser_cache->install_htaccess();

        if ($result) {
            wp_send_json_success(array('message' => __('.htaccess rules installed.', 'ai-seo-captain')));
        } else {
            wp_send_json_error(array('message' => __('Failed to install .htaccess rules. File may not be writable.', 'ai-seo-captain')));
        }
    }

    public function ajax_remove_htaccess(): void
    {
        check_ajax_referer('aisc_cache_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        if (null === $this->browser_cache) {
            $this->browser_cache = new Browser_Cache($this->options);
        }

        $result = $this->browser_cache->remove_htaccess();

        if ($result) {
            wp_send_json_success(array('message' => __('.htaccess rules removed.', 'ai-seo-captain')));
        } else {
            wp_send_json_error(array('message' => __('Failed to remove .htaccess rules.', 'ai-seo-captain')));
        }
    }

    public function ajax_purge_this_url(): void
    {
        check_ajax_referer('aisc_cache_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';

        if (empty($url)) {
            wp_send_json_error(array('message' => 'No URL provided.'));
        }

        $this->purge_url($url);

        wp_send_json_success(array('message' => __('Page cache purged.', 'ai-seo-captain')));
    }

    /**
     * AJAX: Force-purge all cached variants for a specific post.
     */
    public function ajax_purge_post_cache(): void
    {
        check_ajax_referer('aisc_cache_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (! $post_id || ! get_post($post_id)) {
            wp_send_json_error(array('message' => __('Invalid post.', 'ai-seo-captain')));
        }

        // Lazily create page cache if not instantiated (cache may be disabled).
        $pc = $this->page_cache;
        if (null === $pc) {
            $pc = new Page_Cache($this->options);
        }

        // Purge the main permalink.
        $permalink = get_permalink($post_id);
        if ($permalink) {
            $pc->purge($permalink);
        }

        // Purge common pagination variants (/2/, /3/, etc.).
        if ($permalink) {
            for ($i = 2; $i <= 10; $i++) {
                $pc->purge(trailingslashit($permalink) . 'page/' . $i . '/');
                $pc->purge($permalink . '?page=' . $i);
            }
        }

        // Purge the homepage (often includes excerpts of the post).
        $home_url = home_url('/');
        $pc->purge($home_url);

        // Purge feed.
        $pc->purge(get_feed_link());

        wp_send_json_success(array('message' => __('Page cache cleared — changes are live.', 'ai-seo-captain')));
    }

    // ───────────────────────────────────────────────────────────
    // Advanced-cache.php Drop-in Management
    // ───────────────────────────────────────────────────────────

    /**
     * Install the advanced-cache.php drop-in and set WP_CACHE in wp-config.php.
     */
    private function install_advanced_cache_dropin(): bool
    {
        $source  = AI_SEO_KEEPER_PATH . 'advanced-cache.php';
        $target  = WP_CONTENT_DIR . '/advanced-cache.php';

        if (! file_exists($source)) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
        $content = file_get_contents($source);

        if (false === $content) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        $written = file_put_contents($target, $content, LOCK_EX);

        if (false === $written) {
            return false;
        }

        // Ensure cache directory exists with index.php.
        $pages_dir = WP_CONTENT_DIR . '/cache/ai-seo-captain/pages/';
        if (! is_dir($pages_dir)) {
            wp_mkdir_p($pages_dir);
        }

        $index_file = WP_CONTENT_DIR . '/cache/ai-seo-captain/index.php';
        if (! file_exists($index_file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($index_file, '<?php // Silence is golden.');
        }

        // Set WP_CACHE in wp-config.php.
        $this->set_wp_cache_constant(true);

        return true;
    }

    /**
     * Remove the advanced-cache.php drop-in and unset WP_CACHE.
     */
    private function remove_advanced_cache_dropin(): bool
    {
        $target = WP_CONTENT_DIR . '/advanced-cache.php';

        if (file_exists($target) && $this->is_advanced_cache_ours()) {
            wp_delete_file($target);
        }

        $this->set_wp_cache_constant(false);

        return ! file_exists($target);
    }

    /**
     * Check if advanced-cache.php is ours.
     */
    private function is_advanced_cache_ours(): bool
    {
        $target = WP_CONTENT_DIR . '/advanced-cache.php';

        if (! file_exists($target)) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
        $content = file_get_contents($target);

        return false !== $content && false !== strpos($content, 'AI-SEO-Captain-Advanced-Cache');
    }

    /**
     * Add or remove the WP_CACHE constant in wp-config.php.
     */
    private function set_wp_cache_constant(bool $enabled): void
    {
        $config_file = ABSPATH . 'wp-config.php';

        if (! file_exists($config_file) || ! is_writable($config_file)) {
            return;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
        $config = file_get_contents($config_file);

        if (false === $config) {
            return;
        }

        // Remove existing WP_CACHE definition if present.
        $config = preg_replace("/define\s*\(\s*['\"]WP_CACHE['\"]\s*,\s*(?:true|false)\s*\)\s*;\s*(?:\/\/[^\n]*)?\n?/i", '', $config);

        if ($enabled) {
            // Insert after the opening <?php tag.
            $config = preg_replace('/^<\?php/', "<?php\ndefine('WP_CACHE', true); // Added by SEO Captain", $config, 1);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents($config_file, $config, LOCK_EX);
    }

    /**
     * Cleanup on plugin deactivation.
     */
    public static function deactivate(): void
    {
        $manager = new self(new \AI_SEO_Captain\Settings());

        // Remove advanced-cache.php drop-in.
        $manager->remove_dropin('advanced-cache');

        // Remove object-cache.php drop-in (only if ours).
        $oc = new Object_Cache(array());
        if ($oc->is_ours()) {
            $oc->remove();
        }

        // Remove .htaccess rules.
        $bc = new Browser_Cache(array());
        $bc->remove_htaccess();

        // Delete cache directory.
        $cache_dir = WP_CONTENT_DIR . '/cache/ai-seo-captain/';
        if (is_dir($cache_dir)) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($items as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    wp_delete_file($item->getPathname());
                }
            }

            @rmdir($cache_dir);
        }
    }
}
