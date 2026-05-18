<?php

namespace AI_SEO_Captain;

final class Plugin
{
    private static ?Plugin $instance = null;

    private ?Settings $settings = null;

    private ?Content_Indexer $content_indexer = null;

    private ?AI_Generator $ai_generator = null;

    private ?History_Store $history_store = null;

    private ?IndexNow $indexnow = null;

    private $frontend = null;

    private $discovery = null;

    private ?Sitemap $sitemap = null;

    private ?Redirects $redirects = null;

    private ?Admin $admin = null;

    private ?Cron_Manager $cron_manager = null;

    private ?Cache\Cache_Manager $cache_manager = null;

    private ?Broken_Link_Scanner $broken_link_scanner = null;

    public static function instance(): Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        $this->settings = new Settings();
        $this->history_store   = new History_Store();
        $this->content_indexer = new Content_Indexer();
        $this->indexnow        = new IndexNow($this->settings);
        $this->discovery       = new Discovery();
        $this->sitemap         = new Sitemap($this->settings);
        $this->redirects       = new Redirects($this->settings);
        $this->cron_manager    = new Cron_Manager($this->settings, $this->content_indexer);
        $this->broken_link_scanner = new Broken_Link_Scanner();
        $this->broken_link_scanner->register_hooks();

        // Cache system — boot after sitemap so preloader can access it.
        $this->cache_manager = new Cache\Cache_Manager($this->settings);
        $this->cache_manager->boot();

        // Ensure cron jobs are scheduled (safety net — runs once daily via transient).
        if (false === get_transient('ai_seo_captain_cron_check')) {
            $this->cron_manager->schedule_all();
            set_transient('ai_seo_captain_cron_check', '1', DAY_IN_SECONDS);
        }

        // --- Incremental content index hooks (must fire on admin, frontend & cron) ---
        add_action('save_post', array($this, 'handle_index_save_post'), 50, 2);
        add_action('delete_post', array($this, 'handle_index_delete_post'), 10);
        add_action('trashed_post', array($this, 'handle_index_delete_post'), 10);
        add_action('untrashed_post', array($this, 'handle_index_upsert_post'), 10);

        // WooCommerce integration — boots only when WC is active AND enabled in settings.
        // Uses 'init' to guarantee WooCommerce has fully loaded (WC boots on plugins_loaded).
        $wc_options = $this->settings->get();
        add_action('init', static function () use ($wc_options) {
            WooCommerce_Integration::maybe_boot($wc_options);
        }, 0);

        if ($this->sitemap->needs_flush()) {
            add_action('init', 'flush_rewrite_rules', 99);
        }

        // Admin bar menu — must fire on both admin and frontend for logged-in admins.
        add_action('admin_bar_menu', array($this, 'register_admin_bar_menu'), 100);

        // Inline JS for admin bar cache purge — fires on admin and frontend.
        add_action('wp_footer', array($this, 'print_adminbar_purge_script'), 99);
        add_action('admin_footer', array($this, 'print_adminbar_purge_script'), 99);

        if (is_admin()) {
            $this->ai_generator    = new AI_Generator($this->settings, $this->content_indexer);
            $this->admin           = new Admin($this->settings, $this->content_indexer, $this->ai_generator, $this->history_store, $this->indexnow);
            return;
        }

        $this->frontend = new Frontend($this->settings, $this->history_store);
    }

    /**
     * Incremental index: upsert on save_post (skip autosaves and revisions).
     */
    public function handle_index_save_post(int $post_id, \WP_Post $post): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $this->content_indexer->upsert_post($post_id);
    }

    /**
     * Incremental index: remove on delete or trash.
     */
    public function handle_index_delete_post(int $post_id): void
    {
        $this->content_indexer->delete_from_index($post_id);
    }

    /**
     * Incremental index: re-add on untrash.
     */
    public function handle_index_upsert_post(int $post_id): void
    {
        $this->content_indexer->upsert_post($post_id);
    }

    /**
     * Get the Redirects instance.
     */
    public function get_redirects(): ?Redirects
    {
        return $this->redirects;
    }

    /**
     * Get the Cron Manager instance.
     */
    public function get_cron_manager(): ?Cron_Manager
    {
        return $this->cron_manager;
    }

    /**
     * Get the Broken Link Scanner instance.
     */
    public function get_broken_link_scanner(): ?Broken_Link_Scanner
    {
        return $this->broken_link_scanner;
    }

    /**
     * Get the Cache Manager instance.
     */
    public function get_cache_manager(): ?Cache\Cache_Manager
    {
        return $this->cache_manager;
    }

    /**
     * Print a small inline script so admin bar cache purge works on every page.
     */
    public function print_adminbar_purge_script(): void
    {
        if (! current_user_can('manage_options') || ! is_admin_bar_showing()) {
            return;
        }
?>
        <script>
            (function() {
                var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
                var nonce = '<?php echo esc_js(wp_create_nonce('aisc_cache_nonce')); ?>';

                document.addEventListener('click', function(e) {
                    var link = e.target.closest('.aisc-purge-all-trigger a, #wp-admin-bar-ai-seo-captain-purge-all a');
                    if (link) {
                        e.preventDefault();
                        if (!confirm('Purge all cache?')) return;
                        fetch(ajaxUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'action=aisc_purge_cache&nonce=' + nonce
                        }).then(function(r) {
                            return r.json();
                        }).then(function(d) {
                            if (d.success) alert(d.data.message || 'Cache purged.');
                        });
                        return;
                    }

                    link = e.target.closest('.aisc-purge-this-trigger a, #wp-admin-bar-ai-seo-captain-purge-this a');
                    if (link) {
                        e.preventDefault();
                        var wrap = link.closest('[data-url]');
                        var url = wrap ? wrap.getAttribute('data-url') : window.location.href;
                        fetch(ajaxUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'action=aisc_purge_this_url&nonce=' + nonce + '&url=' + encodeURIComponent(url)
                        }).then(function(r) {
                            return r.json();
                        }).then(function(d) {
                            if (d.success) alert(d.data.message || 'Page cache purged.');
                        });
                    }
                });
            })();
        </script>
<?php
    }

    /**
     * Admin bar menu — fires on both admin and frontend for logged-in users.
     */
    public function register_admin_bar_menu(\WP_Admin_Bar $admin_bar): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Parent node
        $icon_url = AI_SEO_CAPTAIN_URL . 'assets/img/ai-seo-captain-wp.png';
        $admin_bar->add_node(array(
            'id'    => 'ai-seo-captain',
            'title' => '<img src="' . esc_url($icon_url) . '" style="height:22px;width:22px;vertical-align:middle;margin-right:6px;padding:0;border:0;" alt="" />' . 'SEO Captain',
            'href'  => admin_url('admin.php?page=ai-seo-captain'),
            'meta'  => array('title' => 'SEO Captain'),
        ));

        // Child: AI Captain Chat (always available)
        $admin_bar->add_node(array(
            'id'     => 'ai-seo-captain-ai-captain',
            'parent' => 'ai-seo-captain',
            'title'  => 'AI Captain Chat',
            'href'   => admin_url('admin.php?page=ai-seo-captain-site-chat'),
            'meta'   => array('title' => 'Open AI Captain (site-wide chat)'),
        ));

        // Child: AI Commander Chat – always visible, disabled when not on a post editor
        $commander_available = false;
        $commander_href      = '#';
        $current_post_id     = 0;

        if (! is_admin() && is_singular()) {
            $current_post_id = get_queried_object_id();
            if ($current_post_id) {
                $commander_href      = admin_url('post.php?post=' . $current_post_id . '&action=edit#ai-seo-captain-chat');
                $commander_available = true;
            }
        } elseif (is_admin()) {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen && 'post' === $screen->base) {
                global $post;
                if (! empty($post->ID)) {
                    $current_post_id     = (int) $post->ID;
                    $commander_href      = '#ai-seo-captain-chat';
                    $commander_available = true;
                }
            }
        }

        $admin_bar->add_node(array(
            'id'     => 'ai-seo-captain-ai-commander',
            'parent' => 'ai-seo-captain',
            'title'  => $commander_available
                ? 'AI Commander Chat'
                : '<span style="opacity:.5;cursor:default;">AI Commander Chat</span>',
            'href'   => $commander_available ? $commander_href : false,
            'meta'   => array(
                'title' => $commander_available
                    ? 'Open AI Commander (page-level chat)'
                    : 'AI Commander is only available when editing a page/post',
                'class' => $commander_available ? '' : 'aisc-adminbar-disabled',
            ),
        ));

        // ── Cache items ──────────────────────────────────────────────
        $admin_bar->add_node(array(
            'id'     => 'ai-seo-captain-purge-all',
            'parent' => 'ai-seo-captain',
            'title'  => __('Purge All Cache', 'ai-seo-captain'),
            'href'   => '#',
            'meta'   => array('class' => 'aisc-purge-all-trigger'),
        ));

        // Purge This Page – enabled on singular frontend OR when editing a specific post
        $purge_page_available = false;
        $purge_url            = '';

        if (! is_admin() && is_singular()) {
            $purge_page_available = true;
            $purge_url            = get_permalink();
        } elseif ($current_post_id > 0) {
            // Editing a post in admin — we can purge its frontend URL
            $purge_page_available = true;
            $purge_url            = get_permalink($current_post_id);
        }

        $admin_bar->add_node(array(
            'id'     => 'ai-seo-captain-purge-this',
            'parent' => 'ai-seo-captain',
            'title'  => $purge_page_available
                ? __('Purge This Page', 'ai-seo-captain')
                : '<span style="opacity:.5;cursor:default;">' . __('Purge This Page', 'ai-seo-captain') . '</span>',
            'href'   => $purge_page_available ? '#' : false,
            'meta'   => array(
                'class'    => $purge_page_available ? 'aisc-purge-this-trigger' : 'aisc-adminbar-disabled',
                'data-url' => $purge_page_available ? esc_url($purge_url) : '',
                'title'    => $purge_page_available ? '' : 'Only available when viewing or editing a page/post',
            ),
        ));
    }
}
