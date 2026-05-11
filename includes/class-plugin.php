<?php

namespace AI_SEO_Keeper;

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
        $this->indexnow        = new IndexNow($this->settings);
        $this->discovery       = new Discovery();
        $this->sitemap         = new Sitemap($this->settings);
        $this->redirects       = new Redirects($this->settings);

        // WooCommerce integration — boots only when WC is active.
        add_action('plugins_loaded', array('AI_SEO_Keeper\\WooCommerce_Integration', 'maybe_boot'), 20);

        if ($this->sitemap->needs_flush()) {
            add_action('init', 'flush_rewrite_rules', 99);
        }

        if (is_admin()) {
            $this->content_indexer = new Content_Indexer();
            $this->ai_generator    = new AI_Generator($this->settings, $this->content_indexer);
            $this->admin           = new Admin($this->settings, $this->content_indexer, $this->ai_generator, $this->history_store, $this->indexnow);
            return;
        }

        $this->frontend = new Frontend($this->settings, $this->history_store);
    }

    /**
     * Get the Redirects instance.
     */
    public function get_redirects(): ?Redirects
    {
        return $this->redirects;
    }
}
