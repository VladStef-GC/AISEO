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

        // Ensure cron jobs are scheduled (covers existing installs upgrading).
        $this->cron_manager->schedule_all();

        // --- Incremental content index hooks (must fire on admin, frontend & cron) ---
        add_action('save_post', array($this, 'handle_index_save_post'), 50, 2);
        add_action('delete_post', array($this, 'handle_index_delete_post'), 10);
        add_action('trashed_post', array($this, 'handle_index_delete_post'), 10);
        add_action('untrashed_post', array($this, 'handle_index_upsert_post'), 10);

        // WooCommerce integration — boots only when WC is active AND enabled in settings.
        $wc_options = $this->settings->get();
        add_action('plugins_loaded', static function () use ($wc_options) {
            WooCommerce_Integration::maybe_boot($wc_options);
        }, 20);

        if ($this->sitemap->needs_flush()) {
            add_action('init', 'flush_rewrite_rules', 99);
        }

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
}
