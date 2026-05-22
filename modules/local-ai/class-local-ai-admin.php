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

        // Admin bar status indicator.
        add_action('admin_bar_menu', array($this, 'admin_bar_status'), 101);
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
            'contextWindow'  => (int) ($options['local_context_window'] ?? 131072),
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

            wp_send_json_success($result);
        } else {
            delete_transient('ai_seo_captain_local_ai_status');
            wp_send_json_error($result);
        }
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
            $options['local_context_window'] = max(512, (int) $_POST['context_window']);
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

        update_option(self::OPTION_NAME, $options);

        // Store connection status for admin bar indicator.
        $model = $options['local_model'] ?? '';
        if ('' !== $model) {
            set_transient('ai_seo_captain_local_ai_status', array(
                'connected' => true,
                'model'     => $model,
                'context'   => $options['local_context_window'] ?? 4096,
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
            'context_window' => max(512, (int) ($_POST['context_window'] ?? ($options['local_context_window'] ?? 4096))),
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
}
