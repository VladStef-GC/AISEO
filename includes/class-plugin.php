<?php

namespace AI_SEO_Keeper;

require_once __DIR__ . '/class-settings.php';
require_once __DIR__ . '/class-content-indexer.php';
require_once __DIR__ . '/class-ai-generator.php';
require_once __DIR__ . '/class-history-store.php';
require_once __DIR__ . '/class-indexnow.php';
require_once __DIR__ . '/class-frontend.php';
require_once __DIR__ . '/class-discovery.php';
require_once __DIR__ . '/class-sitemap.php';
require_once __DIR__ . '/class-admin.php';

final class Plugin
{
    private static ?Plugin $instance = null;

    private ?Settings $settings = null;

    private ?Content_Indexer $content_indexer = null;

    private $ai_generator = null;

    private $history_store = null;

    private $indexnow = null;

    private $frontend = null;

    private $discovery = null;

    private ?Sitemap $sitemap = null;

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
}
