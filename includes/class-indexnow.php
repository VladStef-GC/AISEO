<?php

namespace AI_SEO_Captain;

class IndexNow
{
    private const LOG_OPTION_NAME = 'ai_seo_captain_indexnow_log';

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->ensure_key();

        add_action('template_redirect', array($this, 'maybe_output_key_file'), 0);
        add_action('save_post', array($this, 'handle_post_save'), 30, 3);
        add_action('before_delete_post', array($this, 'handle_post_delete'), 10, 1);
        add_action('trashed_post', array($this, 'handle_post_trash'), 10, 1);
    }

    public function maybe_output_key_file(): void
    {
        $key = $this->get_key();

        if ('' === $key) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $request_path = wp_parse_url($request_uri, PHP_URL_PATH);

        if (! is_string($request_path) || '' === $request_path) {
            return;
        }

        $expected_path = untrailingslashit((string) wp_parse_url($this->get_key_url(), PHP_URL_PATH));
        $request_path = untrailingslashit($request_path);

        if ($request_path !== $expected_path) {
            return;
        }

        nocache_headers();
        status_header(200);
        header('Content-Type: text/plain; charset=' . get_option('blog_charset'));
        echo $key;
        exit;
    }

    public function handle_post_save(int $post_id, \WP_Post $post, bool $update): void
    {
        $options = $this->settings->get();

        if (empty($options['indexnow_enabled']) || empty($options['indexnow_auto_submit'])) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if ('publish' !== $post->post_status) {
            return;
        }

        if (! $this->is_supported_post_type((string) $post->post_type)) {
            return;
        }

        $this->submit_post($post_id, $update ? 'post_update' : 'post_publish');
    }

    /**
     * Notify IndexNow before a post is permanently deleted.
     *
     * Fires on `before_delete_post` so we can still get the permalink.
     */
    public function handle_post_delete(int $post_id): void
    {
        $this->maybe_submit_removed_post($post_id, 'post_delete');
    }

    /**
     * Notify IndexNow when a post is trashed.
     */
    public function handle_post_trash(int $post_id): void
    {
        $this->maybe_submit_removed_post($post_id, 'post_trash');
    }

    /**
     * Submit a post URL to IndexNow when it is being removed/trashed.
     *
     * Only submits if the post was published and of a supported type.
     */
    private function maybe_submit_removed_post(int $post_id, string $reason): void
    {
        $options = $this->settings->get();

        if (empty($options['indexnow_enabled']) || empty($options['indexnow_auto_submit'])) {
            return;
        }

        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            return;
        }

        // For trashed posts, WP changes status to 'trash' before firing the hook,
        // but we still want to notify IndexNow. Check the original status for trash.
        $status = $post->post_status;
        if ('trash' === $status) {
            // The post was just trashed — it was previously published (or other).
            // We can't know the previous status reliably here, so submit anyway.
            // IndexNow will just inform the search engine to re-check the URL.
            $status = 'publish';
        }

        if ('publish' !== $status) {
            return;
        }

        if (! $this->is_supported_post_type((string) $post->post_type)) {
            return;
        }

        // Get permalink before it's gone.
        $permalink = get_permalink($post_id);
        if (! $permalink) {
            return;
        }

        $this->submit_urls(array((string) $permalink), $reason);
    }

    public function submit_post(int $post_id, string $reason = 'manual'): array
    {
        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            return $this->build_result('skipped', 'missing_post', 'The post could not be loaded.');
        }

        if ('publish' !== $post->post_status) {
            return $this->build_result('skipped', 'not_published', 'Only published content can be submitted to IndexNow.');
        }

        if (! $this->is_supported_post_type((string) $post->post_type)) {
            return $this->build_result('skipped', 'unsupported_post_type', 'This content type is not supported for IndexNow submission.');
        }

        return $this->submit_urls(array((string) get_permalink($post_id)), $reason);
    }

    public function submit_urls(array $urls, string $reason = 'manual'): array
    {
        $options = $this->settings->get();
        $clean_urls = array_values(array_unique(array_filter(array_map('esc_url_raw', $urls))));

        if (empty($options['indexnow_enabled'])) {
            return $this->log_and_return($clean_urls, $reason, $this->build_result('skipped', 'disabled', 'IndexNow is disabled in settings.'));
        }

        if (empty($clean_urls)) {
            return $this->log_and_return($clean_urls, $reason, $this->build_result('skipped', 'empty_url_list', 'No valid URLs were available for IndexNow submission.'));
        }

        $key = $this->get_key();

        if ('' === $key) {
            return $this->log_and_return($clean_urls, $reason, $this->build_result('skipped', 'missing_key', 'IndexNow key is missing.'));
        }

        $home_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);

        if ($this->is_local_host($home_host)) {
            return $this->log_and_return($clean_urls, $reason, $this->build_result('skipped', 'local_environment', 'IndexNow submissions are skipped on localhost or private local hosts.'));
        }

        $response = wp_remote_post(
            'https://api.indexnow.org/indexnow',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                ),
                'body' => wp_json_encode(
                    array(
                        'host' => $home_host,
                        'key' => $key,
                        'keyLocation' => $this->get_key_url(),
                        'urlList' => $clean_urls,
                    )
                ),
            )
        );

        if (is_wp_error($response)) {
            return $this->log_and_return($clean_urls, $reason, $this->build_result('error', 'request_error', $response->get_error_message()));
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);

        if ($status_code < 200 || $status_code >= 300) {
            $message = trim((string) wp_remote_retrieve_body($response));
            if ('' === $message) {
                $message = 'IndexNow returned HTTP ' . $status_code . '.';
            }

            return $this->log_and_return($clean_urls, $reason, $this->build_result('error', 'http_' . $status_code, $message));
        }

        return $this->log_and_return($clean_urls, $reason, $this->build_result('success', 'submitted', 'IndexNow accepted the submitted URLs.'));
    }

    public function get_key(): string
    {
        $options = $this->settings->get();

        return isset($options['indexnow_key']) ? trim((string) $options['indexnow_key']) : '';
    }

    public function get_key_url(): string
    {
        $key = $this->get_key();

        if ('' === $key) {
            return '';
        }

        return home_url('/' . rawurlencode($key) . '.txt');
    }

    public function get_log(int $limit = 10): array
    {
        $entries = get_option(self::LOG_OPTION_NAME, array());

        if (! is_array($entries)) {
            return array();
        }

        return array_slice($entries, 0, max(1, min(20, $limit)));
    }

    private function log_and_return(array $urls, string $reason, array $result): array
    {
        $entries = get_option(self::LOG_OPTION_NAME, array());

        if (! is_array($entries)) {
            $entries = array();
        }

        array_unshift(
            $entries,
            array(
                'created_at' => current_time('mysql', true),
                'reason' => $reason,
                'status' => $result['status'],
                'code' => $result['code'],
                'message' => $result['message'],
                'url_count' => count($urls),
                'urls' => $urls,
            )
        );

        update_option(self::LOG_OPTION_NAME, array_slice($entries, 0, 20), false);

        return $result;
    }

    private function ensure_key(): void
    {
        $options = get_option(Settings::OPTION_NAME, array());

        if (! is_array($options)) {
            $options = Settings::defaults();
        }

        if (! empty($options['indexnow_key'])) {
            return;
        }

        $options = wp_parse_args($options, Settings::defaults());
        $options['indexnow_key'] = wp_generate_password(32, false, false);
        update_option(Settings::OPTION_NAME, $options);
    }

    private function build_result(string $status, string $code, string $message): array
    {
        return array(
            'status' => $status,
            'code' => $code,
            'message' => $message,
        );
    }

    private function is_local_host(string $host): bool
    {
        $host = strtolower(trim($host));

        return '' === $host
            || 'localhost' === $host
            || '127.0.0.1' === $host
            || '::1' === $host
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test');
    }

    private function is_supported_post_type(string $post_type): bool
    {
        $supported_post_types = get_post_types(
            array(
                'public' => true,
            ),
            'names'
        );

        unset($supported_post_types['attachment']);

        return isset($supported_post_types[$post_type]);
    }
}
