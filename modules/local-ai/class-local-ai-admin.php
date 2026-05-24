<?php

/**
 * Local AI Admin — Admin page, AJAX handlers, and asset management.
 *
 * Registers the "Local AI" submenu page under SEO Captain,
 * handles model discovery, connection testing, test chat, and settings saving.
 *
 * @package AI_SEO_Captain\Modules\LocalAI
 */

namespace AI_SEO_Captain\Modules\LocalAI;

class Local_AI_Admin
{

    /** @var string Option name (shared with core plugin). */
    private const OPTION_NAME = 'ai_seo_captain_options';

    /** @var string Nonce action for all AJAX calls. */
    private const NONCE_ACTION = 'ai_seo_captain_local_ai';

    /**
     * Register hooks.
     */
    public function register()
    {
        add_action('admin_menu', array($this, 'register_menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // AJAX handlers.
        add_action('wp_ajax_local_ai_discover_models', array($this, 'ajax_discover_models'));
        add_action('wp_ajax_local_ai_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_local_ai_check_vision', array($this, 'ajax_check_vision'));
        add_action('wp_ajax_local_ai_chat', array($this, 'ajax_chat'));
        add_action('wp_ajax_local_ai_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_local_ai_disconnect', array($this, 'ajax_disconnect'));
        add_action('wp_ajax_local_ai_heartbeat', array($this, 'ajax_heartbeat'));
        add_action('wp_ajax_local_ai_generate_image_seo', array($this, 'ajax_generate_image_seo'));

        // Admin bar status indicator.
        add_action('admin_bar_menu', array($this, 'admin_bar_status'), 101);

        // Heartbeat script — fires on ALL admin pages to keep status fresh.
        add_action('admin_footer', array($this, 'heartbeat_script'));
    }

    /**
     * Register the admin submenu page.
     */
    public function register_menu()
    {
        add_submenu_page(
            'ai-seo-captain',
            'Local AI',
            'Local AI',
            'manage_options',
            'ai-seo-captain-local-ai',
            array($this, 'render_page')
        );
    }

    /**
     * Enqueue CSS and JS on the Local AI admin page.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_assets($hook_suffix)
    {
        if (false === strpos($hook_suffix, 'ai-seo-captain-local-ai')) {
            return;
        }

        $module_url = plugin_dir_url(__FILE__);
        $module_dir = plugin_dir_path(__FILE__);
        $ver        = filemtime($module_dir . 'local-ai.js');

        wp_enqueue_style(
            'ai-seo-local-ai',
            $module_url . 'local-ai.css',
            array(),
            filemtime($module_dir . 'local-ai.css')
        );

        wp_enqueue_script(
            'ai-seo-local-ai',
            $module_url . 'local-ai.js',
            array('jquery'),
            $ver,
            true
        );

        // Get saved settings to populate the form.
        $options = get_option(self::OPTION_NAME, array());

        wp_localize_script('ai-seo-local-ai', 'localAiConfig', array(
            'ajaxurl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce(self::NONCE_ACTION),
            'baseUrl'        => $options['local_base_url'] ?? '',
            'model'          => $options['local_model'] ?? '',
            'visionModel'    => $options['local_vision_model'] ?? '',
            'apiKey'         => '' !== ($options['local_api_key'] ?? '') ? '••••••••' : '',
            'contextWindow'  => (int) ($options['context_window'] ?? 128000),
            'timeout'        => (int) ($options['local_timeout'] ?? 120),
            'opRequirements' => Local_AI_Provider::OPERATION_REQUIREMENTS,
        ));
    }

    /**
     * Render the Local AI admin page.
     */
    public function render_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'ai-seo-captain'));
        }

        $view = __DIR__ . '/view-local-ai.php';
        if (file_exists($view)) {
            include $view;
        }
    }

    /**
     * AJAX: Discover models from the local AI server.
     */
    public function ajax_discover_models()
    {
        $this->verify_ajax_request();

        $base_url = $this->sanitize_base_url($_POST['base_url'] ?? '');
        $api_key  = sanitize_text_field($_POST['api_key'] ?? '');

        if ('' === $base_url) {
            wp_send_json_error(array('error' => 'Please enter a valid server URL.'));
        }

        $provider = new Local_AI_Provider(array(
            'base_url' => $base_url,
            'api_key'  => $api_key,
        ));

        $result = $provider->discover_models();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Test connection to the local AI server.
     */
    public function ajax_test_connection()
    {
        set_time_limit(300);
        $this->verify_ajax_request();

        $config = $this->get_ajax_config();
        $provider = new Local_AI_Provider($config);
        $result   = $provider->test_connection();

        if ($result['success']) {
            // Update admin bar status transient on successful connection test.
            set_transient('ai_seo_captain_local_ai_status', array(
                'connected' => true,
                'model'     => $result['model'] ?? $config['model'],
                'context'   => $result['context_window'] ?? $config['context_window'],
                'time'      => time(),
            ), 5 * MINUTE_IN_SECONDS);

            // ── JSON capability test ──
            // Verify the model can produce valid JSON (critical for audits/metadata).
            $result['json_test'] = $this->test_json_capability($provider);

            // ── Real-world content estimation ──
            // Count actual site pages and estimate prompt size so the user
            // sees whether their context window is realistic for their site.
            $ctx_window = (int) ($config['context_window'] ?? 32768);
            $result['site_content_estimate'] = $this->estimate_site_content_tokens($ctx_window);

            wp_send_json_success($result);
        } else {
            delete_transient('ai_seo_captain_local_ai_status');
            wp_send_json_error($result);
        }
    }

    /**
     * Test whether the model can produce valid JSON output.
     *
     * Sends a small prompt asking for a JSON response and verifies it parses.
     * This catches models that consistently produce malformed JSON.
     *
     * @param Local_AI_Provider $provider The configured provider.
     * @return bool True if JSON test passed.
     */
    private function test_json_capability(Local_AI_Provider $provider): bool
    {
        $result = $provider->chat(
            array(
                array(
                    'role'    => 'system',
                    'content' => 'You are a JSON API. Respond ONLY with valid JSON, no extra text.',
                ),
                array(
                    'role'    => 'user',
                    'content' => 'Return a JSON object with exactly these fields: "status" (string "ok"), "score" (integer 85), "issues" (array with one string "test issue"). Nothing else.',
                ),
            ),
            '', // use configured model
            0.1,
            256
        );

        if (! $result['success'] || empty($result['content'])) {
            return false;
        }

        $raw = $result['content'];

        // Strip markdown code fences if present.
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $raw, $m)) {
            $raw = $m[1];
        }

        // Extract JSON object.
        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');

        if (false === $start || false === $end) {
            return false;
        }

        $json = substr($raw, $start, ($end - $start) + 1);
        $decoded = json_decode($json, true);

        return is_array($decoded) && isset($decoded['status']);
    }

    /**
     * Estimate real-world token requirements for this site's pages.
     *
     * Counts published pages, estimates SEO metadata overhead from siblings,
     * and samples the largest page to give an honest assessment of whether
     * the configured context window is sufficient.
     *
     * @param int $context_window Configured context window in tokens.
     * @return array{page_count: int, estimated_max_tokens: int, fits: bool, warning: string}
     */
    private function estimate_site_content_tokens(int $context_window): array
    {
        $post_types = get_post_types(array('public' => true), 'names');
        $pages = get_posts(array(
            'post_type'      => array_values($post_types),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));
        $page_count = count($pages);

        // Each sibling in the SEO context adds ~300 chars (title + slug + keyphrase + seo_title + desc + keywords + social).
        // System prompt + task instructions: ~3,000 chars.
        // Content cap for metadata: 20,000 chars. Page audit: full content.
        $chars_per_sibling = 300;
        $system_overhead   = 3000;
        $content_cap       = 20000; // Step 2 metadata cap
        $chars_per_token   = 3.5;   // Conservative estimate

        // Worst case: a page with ALL other pages as siblings.
        $sibling_chars   = max(0, $page_count - 1) * $chars_per_sibling;
        $total_chars     = $system_overhead + $sibling_chars + $content_cap;
        $estimated_tokens = (int) ceil($total_chars / $chars_per_token);

        // Also sample the largest published page for audit estimation.
        $largest_content = 0;
        if ($page_count > 0 && $page_count <= 200) {
            // Sample up to 10 largest pages.
            $sample = get_posts(array(
                'post_type'      => array_values($post_types),
                'post_status'    => 'publish',
                'orderby'        => 'content_length',
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ));
            foreach ($sample as $pid) {
                $len = mb_strlen(get_post_field('post_content', $pid));
                if ($len > $largest_content) {
                    $largest_content = $len;
                }
            }
        }

        $audit_tokens = (int) ceil(($system_overhead + $sibling_chars + $largest_content) / $chars_per_token);
        $input_budget = (int) ($context_window * 0.6);

        $fits_metadata = $estimated_tokens <= $input_budget;
        $fits_audit    = $audit_tokens <= $input_budget;

        $warning = '';
        if (! $fits_metadata) {
            $warning = sprintf(
                'Your site has %d pages. Metadata generation needs ~%s tokens per page (including sibling SEO data), but your %s-token context window only fits ~%s tokens of input. The compressor will auto-truncate sibling data to fit, but results may lack full site context.',
                $page_count,
                number_format($estimated_tokens),
                number_format($context_window),
                number_format($input_budget)
            );
        } elseif (! $fits_audit) {
            $warning = sprintf(
                'Metadata generation should work, but page audits for your largest pages (~%s tokens with %d siblings) may require content compression.',
                number_format($audit_tokens),
                $page_count - 1
            );
        }

        return array(
            'page_count'          => $page_count,
            'estimated_max_tokens' => max($estimated_tokens, $audit_tokens),
            'fits_metadata'       => $fits_metadata,
            'fits_audit'          => $fits_audit,
            'warning'             => $warning,
        );
    }

    /**
     * AJAX: Probe whether a model supports vision (image input).
     *
     * Sends a tiny 1x1 pixel image to the model. If it responds, vision is supported.
     * This is the only way to detect vision capability with 100% accuracy.
     */
    public function ajax_check_vision()
    {
        $this->verify_ajax_request();

        $model = sanitize_text_field($_POST['model'] ?? '');

        if ('' === $model) {
            wp_send_json_error(array('error' => 'No model specified.'));
        }

        $config          = $this->get_ajax_config();
        $config['model'] = $model;
        $provider        = new Local_AI_Provider($config);
        $result          = $provider->probe_vision($model);

        if ($result['success']) {
            wp_send_json_success(array('vision' => $result['vision']));
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Send a chat message to the local AI server (test chat).
     */
    public function ajax_chat()
    {
        set_time_limit(300);

        $this->verify_ajax_request();

        $message = sanitize_textarea_field($_POST['message'] ?? '');

        if ('' === $message) {
            wp_send_json_error(array('error' => 'Message cannot be empty.'));
        }

        $config   = $this->get_ajax_config();
        $provider = new Local_AI_Provider($config);

        $messages = array(
            array(
                'role'    => 'system',
                'content' => 'You are a helpful AI assistant running locally via LM Studio. Keep responses concise.',
            ),
            array(
                'role'    => 'user',
                'content' => $message,
            ),
        );

        $result = $provider->chat($messages);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Save Local AI settings.
     *
     * Supports partial saves — only updates fields present in POST data.
     * This allows auto-saving connection details without wiping model selections.
     */
    public function ajax_save_settings()
    {
        $this->verify_ajax_request();

        $options = get_option(self::OPTION_NAME, array());

        // Only update fields that are explicitly sent.
        if (isset($_POST['base_url'])) {
            $options['local_base_url'] = $this->sanitize_base_url($_POST['base_url']);
        }
        if (isset($_POST['model'])) {
            $options['local_model'] = sanitize_text_field($_POST['model']);
        }
        if (isset($_POST['vision_model'])) {
            $options['local_vision_model'] = sanitize_text_field($_POST['vision_model']);
        }
        if (isset($_POST['context_window'])) {
            $options['context_window'] = max(32000, (int) $_POST['context_window']);
        }
        if (isset($_POST['timeout'])) {
            $options['local_timeout'] = max(10, min(600, (int) $_POST['timeout']));
        }

        // Only update API key if a real value was sent (not the masked placeholder).
        if (isset($_POST['api_key'])) {
            $api_key = $_POST['api_key'];
            if ('' !== $api_key && '••••••••' !== $api_key) {
                $options['local_api_key'] = sanitize_text_field($api_key);
            }
        }

        // Bypass Settings::sanitize() filter — it enforces a 32K minimum
        // context window and rebuilds the options array from defaults.
        remove_all_filters('sanitize_option_' . self::OPTION_NAME);
        update_option(self::OPTION_NAME, $options);

        // Store connection status for admin bar indicator.
        $model = $options['local_model'] ?? '';
        if ('' !== $model) {
            set_transient('ai_seo_captain_local_ai_status', array(
                'connected' => true,
                'model'     => $model,
                'context'   => $options['context_window'] ?? 128000,
                'time'      => time(),
            ), 5 * MINUTE_IN_SECONDS);
        }

        wp_send_json_success(array('message' => 'Settings saved successfully.'));
    }

    /**
     * AJAX: Disconnect — clear Local AI settings and status.
     */
    public function ajax_disconnect()
    {
        $this->verify_ajax_request();

        $options = get_option(self::OPTION_NAME, array());

        unset(
            $options['local_base_url'],
            $options['local_model'],
            $options['local_vision_model'],
            $options['local_api_key'],
            $options['local_context_window'],
            $options['local_timeout']
        );

        // If the active provider was 'local', reset to avoid broken state.
        if ('local' === ($options['provider'] ?? '')) {
            unset($options['provider']);
        }

        remove_all_filters('sanitize_option_' . self::OPTION_NAME);
        update_option(self::OPTION_NAME, $options);
        delete_transient('ai_seo_captain_local_ai_status');

        wp_send_json_success(array('message' => 'Local AI disconnected. Settings cleared.'));
    }

    /**
     * Show Local AI connection status in the WordPress admin bar.
     *
     * @param \WP_Admin_Bar $admin_bar Admin bar instance.
     */
    public function admin_bar_status($admin_bar)
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $options = get_option(self::OPTION_NAME, array());
        $model   = $options['local_model'] ?? '';

        // Only show if Local AI has been configured.
        if ('' === $model) {
            return;
        }

        $status  = get_transient('ai_seo_captain_local_ai_status');
        $is_live = is_array($status) && ! empty($status['connected']);

        if ($is_live) {
            $label = '🟢 Local AI: Running';
            $title = sprintf('Connected to %s (%s tokens)', $status['model'], number_format($status['context']));
        } else {
            $label = '🔴 Local AI: Offline';
            $title = 'Local AI server not responding. Click to check settings.';
        }

        $admin_bar->add_node(array(
            'id'     => 'ai-seo-captain-local-ai-status',
            'parent' => 'ai-seo-captain',
            'title'  => $label,
            'href'   => admin_url('admin.php?page=ai-seo-captain-local-ai'),
            'meta'   => array('title' => $title),
        ));
    }

    /**
     * AJAX: Lightweight heartbeat — ping the Local AI server.
     *
     * Called in the background on admin page loads to keep the admin bar
     * status indicator fresh. Uses a 5-second timeout so it doesn't
     * block anything.
     */
    public function ajax_heartbeat()
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'Permission denied.'), 403);
        }

        if (! check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(array('error' => 'Security check failed.'), 403);
        }

        $options  = get_option(self::OPTION_NAME, array());
        $base_url = $options['local_base_url'] ?? '';
        $model    = $options['local_model'] ?? '';

        if ('' === $base_url || '' === $model) {
            wp_send_json_error(array('status' => 'not_configured'));
        }

        $provider = new Local_AI_Provider(array(
            'base_url' => $base_url,
            'api_key'  => $options['local_api_key'] ?? '',
            'timeout'  => 5, // Short timeout for heartbeat.
        ));

        $result = $provider->discover_models();

        if ($result['success']) {
            $status_data = array(
                'connected' => true,
                'model'     => $model,
                'context'   => (int) ($options['context_window'] ?? 128000),
                'time'      => time(),
            );
            set_transient('ai_seo_captain_local_ai_status', $status_data, 5 * MINUTE_IN_SECONDS);

            wp_send_json_success(array(
                'status' => 'online',
                'label'  => '🟢 Local AI: Running',
                'tip'    => sprintf('Connected to %s (%s tokens)', $model, number_format((int) ($options['context_window'] ?? 128000))),
                'model'  => $model,
            ));
        } else {
            delete_transient('ai_seo_captain_local_ai_status');

            wp_send_json_success(array(
                'status' => 'offline',
                'label'  => '🔴 Local AI: Offline',
                'tip'    => 'Local AI server not responding. Click to check settings.',
            ));
        }
    }

    /**
     * Output a tiny inline heartbeat script on all admin pages.
     *
     * Pings the Local AI server in the background to keep the admin bar
     * status indicator accurate. Throttled: only pings if the last check
     * was more than 2 minutes ago (stored in sessionStorage).
     */
    public function heartbeat_script()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $options = get_option(self::OPTION_NAME, array());
        $model   = $options['local_model'] ?? '';

        // Only output if Local AI is configured.
        if ('' === $model) {
            return;
        }

        $nonce    = wp_create_nonce(self::NONCE_ACTION);
        $ajax_url = admin_url('admin-ajax.php');
?>
        <script>
            (function() {
                var THROTTLE_MS = 120000; // 2 minutes
                var key = 'localAiHeartbeat';
                var last = parseInt(sessionStorage.getItem(key) || '0', 10);
                var now = Date.now();

                if (now - last < THROTTLE_MS) return;
                sessionStorage.setItem(key, now.toString());

                var xhr = new XMLHttpRequest();
                xhr.open('POST', <?php echo wp_json_encode($ajax_url); ?>, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status !== 200) return;
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        var data = resp.data || {};
                        var node = document.querySelector('#wp-admin-bar-ai-seo-captain-local-ai-status .ab-item');
                        if (node && data.label) {
                            node.textContent = data.label;
                            node.title = data.tip || '';
                        }
                    } catch (e) {}
                };
                xhr.send('action=local_ai_heartbeat&nonce=' + encodeURIComponent(<?php echo wp_json_encode($nonce); ?>));
            })();
        </script>
<?php
    }

    /**
     * Verify AJAX nonce and user capability.
     */
    private function verify_ajax_request()
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'Permission denied.'), 403);
        }

        if (! check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(array('error' => 'Security check failed. Please refresh the page.'), 403);
        }
    }

    /**
     * Build provider config from AJAX POST data.
     *
     * @return array
     */
    private function get_ajax_config()
    {
        $options = get_option(self::OPTION_NAME, array());

        // Use POST values if provided, else fall back to saved settings.
        $base_url = $this->sanitize_base_url($_POST['base_url'] ?? '');
        $api_key  = sanitize_text_field($_POST['api_key'] ?? '');

        // If the masked placeholder was sent, use the saved key.
        if ('••••••••' === $api_key || '' === $api_key) {
            $api_key = $options['local_api_key'] ?? '';
        }

        return array(
            'base_url'       => $base_url ?: ($options['local_base_url'] ?? 'http://localhost:1234/v1'),
            'api_key'        => $api_key,
            'model'          => sanitize_text_field($_POST['model'] ?? ($options['local_model'] ?? '')),
            'vision_model'   => sanitize_text_field($_POST['vision_model'] ?? ($options['local_vision_model'] ?? '')),
            'context_window' => max(32000, (int) ($_POST['context_window'] ?? ($options['context_window'] ?? 128000))),
            'timeout'        => max(10, (int) ($_POST['timeout'] ?? ($options['local_timeout'] ?? 120))),
        );
    }

    /**
     * Sanitize and validate a base URL.
     *
     * @param string $url Raw URL input.
     * @return string Sanitized URL or empty string if invalid.
     */
    private function sanitize_base_url($url)
    {
        $url = esc_url_raw(trim($url));

        // Must be http or https.
        if ('' !== $url && ! preg_match('#^https?://#', $url)) {
            return '';
        }

        return rtrim($url, '/');
    }

    /**
     * AJAX: Generate image SEO metadata using the local AI vision pipeline.
     *
     * Expects POST: attachment_id, page_id (optional), _nonce.
     * Returns: {alt_text, title, caption, decorative, method}
     */
    public function ajax_generate_image_seo()
    {
        set_time_limit(300);
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'Permission denied.'), 403);
        }

        if (! check_ajax_referer('ai_seo_captain_nonce', '_nonce', false)) {
            wp_send_json_error(array('error' => 'Security check failed.'), 403);
        }

        $attachment_id = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
        $page_id       = isset($_POST['page_id']) ? (int) $_POST['page_id'] : 0;

        if ($attachment_id <= 0) {
            wp_send_json_error(array('error' => 'Invalid attachment ID.'));
        }

        // Ensure Local AI is configured.
        $options = get_option(self::OPTION_NAME, array());
        if (empty($options['local_model'])) {
            wp_send_json_error(array('error' => 'No local AI model configured. Set up Local AI first.'));
        }

        $image_seo = new Local_AI_Image_SEO();
        $result    = $image_seo->generate($attachment_id, $page_id);

        if (empty($result['success'])) {
            wp_send_json_error(array('error' => $result['error'] ?? 'Image SEO generation failed.'));
        }

        // Auto-save the alt text if generation succeeded.
        if (isset($result['alt_text'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $result['alt_text']);
        }

        // Save title and caption to the attachment post.
        $update_data = array('ID' => $attachment_id);
        if (! empty($result['title'])) {
            $update_data['post_title'] = $result['title'];
        }
        if (isset($result['caption'])) {
            $update_data['post_excerpt'] = $result['caption'];
        }
        if (count($update_data) > 1) {
            wp_update_post($update_data);
        }

        wp_send_json_success(array(
            'alt_text'   => $result['alt_text'] ?? '',
            'title'      => $result['title'] ?? '',
            'caption'    => $result['caption'] ?? '',
            'decorative' => ! empty($result['decorative']),
            'method'     => $result['method'] ?? 'unknown',
        ));
    }
}
