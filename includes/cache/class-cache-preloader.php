<?php

namespace AI_SEO_Captain\Cache;

/**
 * Cache preloader: warm the cache by crawling sitemap URLs.
 */
class Cache_Preloader
{

    /** @var int URLs per cron batch. */
    private $batch_size;

    /** @var string Cron hook name. */
    private const CRON_HOOK = 'aisc_cache_preload_batch';

    /** @var string Transient key for progress. */
    private const PROGRESS_TRANSIENT = 'aisc_preload_progress';

    /** @var string Transient key for URL queue. */
    private const QUEUE_TRANSIENT = 'aisc_preload_queue';

    public function __construct(int $batch_size = 5)
    {
        $this->batch_size = max(1, $batch_size);
    }

    /**
     * Register cron hook for batched preloading.
     */
    public function register_hooks(): void
    {
        add_action(self::CRON_HOOK, array($this, 'run_scheduled_preload'));
    }

    /**
     * Crawl all URLs from the sitemap to warm page cache.
     */
    public function preload_all(): void
    {
        $urls = $this->get_sitemap_urls();

        if (empty($urls)) {
            return;
        }

        $this->queue_preload($urls);
    }

    /**
     * Crawl specific URLs (after targeted purge).
     *
     * These run immediately (non-blocking), not queued.
     */
    public function preload_urls(array $urls): void
    {
        foreach ($urls as $url) {
            wp_remote_get($url, array(
                'timeout'   => 5,
                'blocking'  => false,
                'sslverify' => false,
                'headers'   => array(
                    'X-Cache-Preload' => '1',
                ),
            ));
        }
    }

    /**
     * Get preload progress for admin UI polling.
     */
    public function get_progress(): array
    {
        $progress = get_transient(self::PROGRESS_TRANSIENT);

        if (! is_array($progress)) {
            return array(
                'total'   => 0,
                'done'    => 0,
                'running' => false,
            );
        }

        return $progress;
    }

    /**
     * Schedule the preload via wp-cron (batched).
     */
    public function schedule_preload(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time() + 5, self::CRON_HOOK);
        }
    }

    /**
     * Run a single batch of the scheduled preload.
     */
    public function run_scheduled_preload(): void
    {
        $queue = get_transient(self::QUEUE_TRANSIENT);

        if (! is_array($queue) || empty($queue)) {
            // Preload complete.
            delete_transient(self::QUEUE_TRANSIENT);
            $progress = get_transient(self::PROGRESS_TRANSIENT);
            if (is_array($progress)) {
                $progress['running'] = false;
                $progress['done']    = $progress['total'];
                set_transient(self::PROGRESS_TRANSIENT, $progress, 3600);
            }
            return;
        }

        // Process a batch.
        $batch = array_splice($queue, 0, $this->batch_size);

        foreach ($batch as $url) {
            wp_remote_get($url, array(
                'timeout'   => 10,
                'blocking'  => true,
                'sslverify' => false,
                'headers'   => array(
                    'X-Cache-Preload' => '1',
                ),
            ));
        }

        // Update progress.
        $progress = get_transient(self::PROGRESS_TRANSIENT);
        if (is_array($progress)) {
            $progress['done'] = $progress['total'] - count($queue);
            set_transient(self::PROGRESS_TRANSIENT, $progress, 3600);
        }

        // Save remaining queue.
        if (! empty($queue)) {
            set_transient(self::QUEUE_TRANSIENT, $queue, 3600);
            // Schedule next batch.
            wp_schedule_single_event(time() + 2, self::CRON_HOOK);
        } else {
            delete_transient(self::QUEUE_TRANSIENT);
            if (is_array($progress)) {
                $progress['running'] = false;
                $progress['done']    = $progress['total'];
                set_transient(self::PROGRESS_TRANSIENT, $progress, 3600);
            }
        }
    }

    /**
     * Queue URLs for batched preloading.
     */
    private function queue_preload(array $urls): void
    {
        $total = count($urls);

        set_transient(self::PROGRESS_TRANSIENT, array(
            'total'   => $total,
            'done'    => 0,
            'running' => true,
        ), 3600);

        set_transient(self::QUEUE_TRANSIENT, $urls, 3600);

        // Kick off the first batch.
        $this->schedule_preload();
    }

    /**
     * Get all URLs from the sitemap for preloading.
     */
    private function get_sitemap_urls(): array
    {
        $sitemap_url = home_url('/sitemap_index.xml');

        $response = wp_remote_get($sitemap_url, array(
            'timeout'   => 15,
            'sslverify' => false,
        ));

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $urls = array();

        // Parse sitemap index to find child sitemaps.
        $sitemap_locs = array();
        if (preg_match_all('/<loc>([^<]+)<\/loc>/i', $body, $matches)) {
            $sitemap_locs = $matches[1];
        }

        // Fetch each child sitemap and extract page URLs.
        foreach ($sitemap_locs as $child_sitemap_url) {
            $child_response = wp_remote_get($child_sitemap_url, array(
                'timeout'   => 15,
                'sslverify' => false,
            ));

            if (is_wp_error($child_response) || 200 !== wp_remote_retrieve_response_code($child_response)) {
                continue;
            }

            $child_body = wp_remote_retrieve_body($child_response);

            if (preg_match_all('/<loc>([^<]+)<\/loc>/i', $child_body, $child_matches)) {
                $urls = array_merge($urls, $child_matches[1]);
            }
        }

        return array_unique($urls);
    }
}
