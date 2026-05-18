<?php

namespace AI_SEO_Captain;

/**
 * Manages all plugin cron jobs: registration, execution, logging, pause/resume.
 *
 * Each job runs through a wrapper that handles locking, error capture, and
 * logging so every scheduled task is observable from the admin UI.
 */
class Cron_Manager
{
    /** Option key for per-job metadata (paused state, last result, error count). */
    private const JOBS_OPTION = 'ai_seo_captain_cron_jobs';

    /** Option key for execution log entries (capped at 100). */
    private const LOG_OPTION = 'ai_seo_captain_cron_log';

    /** Transient prefix used for single-execution locks. */
    private const LOCK_PREFIX = 'aisc_cron_lock_';

    /** Maximum entries in the execution log. */
    private const MAX_LOG_ENTRIES = 100;

    /** Maximum lock duration in seconds (safety net). */
    private const LOCK_TTL = 600;

    private Settings $settings;

    private Content_Indexer $indexer;

    /**
     * Static job definitions.
     *
     * @var array<string, array{schedule: string, label: string, description: string, callback: string}>
     */
    private array $jobs = array(
        'ai_seo_captain_index_health' => array(
            'schedule'    => 'daily',
            'label'       => 'Index Health Check',
            'description' => 'Verifies content index integrity: removes orphaned entries for deleted posts and adds missing entries for new posts that were not captured by real-time hooks.',
            'callback'    => 'run_index_health',
        ),
        'ai_seo_captain_stale_cleanup' => array(
            'schedule'    => 'weekly',
            'label'       => 'Stale Data Cleanup',
            'description' => 'Prunes old IndexNow submission logs, expired content edit plans, and orphaned conversation threads to keep the database lean.',
            'callback'    => 'run_stale_cleanup',
        ),
        'ai_seo_captain_sitemap_ping' => array(
            'schedule'    => 'twicedaily',
            'label'       => 'Sitemap Ping',
            'description' => 'Pings Google and Bing to re-crawl the XML sitemap. Runs twice daily to ensure search engines are aware of recent content changes.',
            'callback'    => 'run_sitemap_ping',
        ),
    );

    public function __construct(Settings $settings, Content_Indexer $indexer)
    {
        $this->settings = $settings;
        $this->indexer  = $indexer;

        // Register action hooks for each job.
        foreach ($this->jobs as $hook => $config) {
            add_action($hook, array($this, 'execute_job'));
        }
    }

    // -------------------------------------------------------------------------
    // Scheduling
    // -------------------------------------------------------------------------

    /**
     * Schedule all cron jobs. Called on plugin activation.
     */
    public function schedule_all(): void
    {
        foreach ($this->jobs as $hook => $config) {
            if (! wp_next_scheduled($hook)) {
                wp_schedule_event(time(), $config['schedule'], $hook);
            }
        }
    }

    /**
     * Unschedule all cron jobs. Called on plugin deactivation.
     */
    public function unschedule_all(): void
    {
        foreach ($this->jobs as $hook => $config) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    /**
     * Universal job executor. Determines which job fired via current_action().
     */
    public function execute_job(): void
    {
        $hook = current_action();

        if (! isset($this->jobs[$hook])) {
            return;
        }

        // Check if job is paused.
        $meta = $this->get_jobs_meta();
        if (! empty($meta[$hook]['paused'])) {
            return;
        }

        // Acquire lock (prevent overlap).
        $lock_key = self::LOCK_PREFIX . $hook;
        if (false !== get_transient($lock_key)) {
            $this->log_execution($hook, 'skipped', 'Previous execution still running (locked).', 0);
            return;
        }
        set_transient($lock_key, time(), self::LOCK_TTL);

        $start = microtime(true);
        $status  = 'success';
        $message = '';

        try {
            $callback = $this->jobs[$hook]['callback'];
            $result   = $this->$callback();
            $message  = is_string($result) ? $result : wp_json_encode($result);
        } catch (\Throwable $e) {
            $status  = 'error';
            $message = $e->getMessage();
        }

        $duration = round(microtime(true) - $start, 3);

        // Release lock.
        delete_transient($lock_key);

        // Update per-job metadata.
        $meta[$hook] = array_merge(
            $meta[$hook] ?? array(),
            array(
                'last_run'      => current_time('mysql', true),
                'last_status'   => $status,
                'last_message'  => mb_substr($message, 0, 500),
                'last_duration' => $duration,
                'error_count'   => 'error' === $status
                    ? (($meta[$hook]['error_count'] ?? 0) + 1)
                    : 0,
            )
        );
        update_option(self::JOBS_OPTION, $meta, false);

        $this->log_execution($hook, $status, $message, $duration);
    }

    // -------------------------------------------------------------------------
    // Job implementations
    // -------------------------------------------------------------------------

    /**
     * Index Health Check — verify and repair the content index.
     */
    private function run_index_health(): string
    {
        $result = $this->indexer->verify_index_integrity();

        return sprintf(
            'Removed %d orphaned entries, added %d missing entries. Index total: %d.',
            $result['removed'],
            $result['added'],
            $result['total']
        );
    }

    /**
     * Stale Data Cleanup — prune old logs and orphaned data.
     */
    private function run_stale_cleanup(): string
    {
        global $wpdb;

        $cleaned = 0;

        // 1. Prune IndexNow log entries older than 30 days.
        $indexnow_log = get_option('ai_seo_captain_indexnow_log', array());
        if (is_array($indexnow_log) && ! empty($indexnow_log)) {
            $cutoff = gmdate('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS));
            $before = count($indexnow_log);
            $indexnow_log = array_filter(
                $indexnow_log,
                static function (array $entry) use ($cutoff): bool {
                    return isset($entry['created_at']) && $entry['created_at'] >= $cutoff;
                }
            );
            $pruned = $before - count($indexnow_log);
            if ($pruned > 0) {
                update_option('ai_seo_captain_indexnow_log', array_values($indexnow_log), false);
                $cleaned += $pruned;
            }
        }

        // 2. Prune own cron execution log (already capped, but enforce).
        $cron_log = get_option(self::LOG_OPTION, array());
        if (is_array($cron_log) && count($cron_log) > self::MAX_LOG_ENTRIES) {
            $cron_log = array_slice($cron_log, 0, self::MAX_LOG_ENTRIES);
            update_option(self::LOG_OPTION, $cron_log, false);
        }

        // 3. Remove orphaned conversations (post no longer exists).
        $conv_table = $wpdb->prefix . 'ai_seo_captain_conversations';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$conv_table}'") === $conv_table) {
            $orphaned = $wpdb->query(
                "DELETE c FROM {$conv_table} c
                 LEFT JOIN {$wpdb->posts} p ON p.ID = c.object_id
                 WHERE c.object_type = 'post_chat' AND c.object_id > 0 AND p.ID IS NULL"
            );
            $cleaned += (int) $orphaned;
        }

        return sprintf('Cleaned %d stale entries.', $cleaned);
    }

    /**
     * Sitemap Ping — notify Bing about sitemap updates.
     *
     * Note: Google deprecated their ping endpoint in 2023. Google now discovers
     * sitemaps exclusively via robots.txt and Search Console.
     */
    private function run_sitemap_ping(): string
    {
        $options = $this->settings->get();

        if (empty($options['sitemap_enabled'])) {
            return 'Sitemap is disabled. Ping skipped.';
        }

        $sitemap_url = home_url('/sitemap.xml');
        $results = array();

        // Bing (still supports sitemap ping).
        $bing_url = 'https://www.bing.com/ping?sitemap=' . rawurlencode($sitemap_url);
        $bing = wp_remote_get($bing_url, array('timeout' => 15, 'sslverify' => true));
        $results['bing'] = is_wp_error($bing)
            ? 'error: ' . $bing->get_error_message()
            : 'HTTP ' . wp_remote_retrieve_response_code($bing);

        return sprintf('Bing: %s', $results['bing']);
    }

    // -------------------------------------------------------------------------
    // Controls (pause / resume / run now)
    // -------------------------------------------------------------------------

    /**
     * Pause a scheduled job. It remains scheduled but the executor skips it.
     */
    public function pause_job(string $hook): bool
    {
        if (! isset($this->jobs[$hook])) {
            return false;
        }

        $meta = $this->get_jobs_meta();
        $meta[$hook] = array_merge($meta[$hook] ?? array(), array('paused' => true));
        update_option(self::JOBS_OPTION, $meta, false);

        return true;
    }

    /**
     * Resume a paused job.
     */
    public function resume_job(string $hook): bool
    {
        if (! isset($this->jobs[$hook])) {
            return false;
        }

        $meta = $this->get_jobs_meta();
        $meta[$hook] = array_merge($meta[$hook] ?? array(), array('paused' => false));
        update_option(self::JOBS_OPTION, $meta, false);

        return true;
    }

    /**
     * Execute a job immediately (manual trigger from admin).
     *
     * @return array{status: string, message: string, duration: float}
     */
    public function run_job_now(string $hook): array
    {
        if (! isset($this->jobs[$hook])) {
            return array('status' => 'error', 'message' => 'Unknown job.', 'duration' => 0);
        }

        $start = microtime(true);
        $status  = 'success';
        $message = '';

        try {
            $callback = $this->jobs[$hook]['callback'];
            $result   = $this->$callback();
            $message  = is_string($result) ? $result : wp_json_encode($result);
        } catch (\Throwable $e) {
            $status  = 'error';
            $message = $e->getMessage();
        }

        $duration = round(microtime(true) - $start, 3);

        $meta = $this->get_jobs_meta();
        $meta[$hook] = array_merge(
            $meta[$hook] ?? array(),
            array(
                'last_run'      => current_time('mysql', true),
                'last_status'   => $status,
                'last_message'  => mb_substr($message, 0, 500),
                'last_duration' => $duration,
                'error_count'   => 'error' === $status
                    ? (($meta[$hook]['error_count'] ?? 0) + 1)
                    : 0,
            )
        );
        update_option(self::JOBS_OPTION, $meta, false);

        $this->log_execution($hook, $status, $message, $duration, true);

        return array('status' => $status, 'message' => $message, 'duration' => $duration);
    }

    // -------------------------------------------------------------------------
    // Status & Logging
    // -------------------------------------------------------------------------

    /**
     * Get the full status of all jobs for the admin view.
     *
     * @return array<string, array{hook: string, label: string, description: string, schedule: string, schedule_display: string, paused: bool, next_run: string, last_run: string, last_status: string, last_message: string, last_duration: float, error_count: int}>
     */
    public function get_all_jobs_status(): array
    {
        $meta = $this->get_jobs_meta();
        $result = array();
        $schedules = wp_get_schedules();

        foreach ($this->jobs as $hook => $config) {
            $job_meta = $meta[$hook] ?? array();
            $next     = wp_next_scheduled($hook);

            $schedule_display = isset($schedules[$config['schedule']]['display'])
                ? $schedules[$config['schedule']]['display']
                : $config['schedule'];

            $result[$hook] = array(
                'hook'             => $hook,
                'label'            => $config['label'],
                'description'      => $config['description'],
                'schedule'         => $config['schedule'],
                'schedule_display' => $schedule_display,
                'paused'           => ! empty($job_meta['paused']),
                'next_run'         => $next ? gmdate('Y-m-d H:i:s', $next) : '',
                'last_run'         => $job_meta['last_run'] ?? '',
                'last_status'      => $job_meta['last_status'] ?? '',
                'last_message'     => $job_meta['last_message'] ?? '',
                'last_duration'    => $job_meta['last_duration'] ?? 0,
                'error_count'      => $job_meta['error_count'] ?? 0,
            );
        }

        return $result;
    }

    /**
     * Get the execution log.
     *
     * @return array<int, array{timestamp: string, hook: string, label: string, status: string, message: string, duration: float, manual: bool}>
     */
    public function get_execution_log(int $limit = 50): array
    {
        $log = get_option(self::LOG_OPTION, array());

        if (! is_array($log)) {
            return array();
        }

        return array_slice($log, 0, max(1, min(self::MAX_LOG_ENTRIES, $limit)));
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Retrieve per-job metadata from options.
     *
     * @return array<string, array<string, mixed>>
     */
    private function get_jobs_meta(): array
    {
        $meta = get_option(self::JOBS_OPTION, array());

        return is_array($meta) ? $meta : array();
    }

    /**
     * Append an entry to the execution log (capped).
     */
    private function log_execution(string $hook, string $status, string $message, float $duration, bool $manual = false): void
    {
        $log = get_option(self::LOG_OPTION, array());

        if (! is_array($log)) {
            $log = array();
        }

        array_unshift($log, array(
            'timestamp' => current_time('mysql', true),
            'hook'      => $hook,
            'label'     => $this->jobs[$hook]['label'] ?? $hook,
            'status'    => $status,
            'message'   => mb_substr($message, 0, 500),
            'duration'  => $duration,
            'manual'    => $manual,
        ));

        update_option(self::LOG_OPTION, array_slice($log, 0, self::MAX_LOG_ENTRIES), false);
    }
}
