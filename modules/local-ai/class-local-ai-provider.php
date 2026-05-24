<?php

/**
 * Local AI Provider — LM Studio / Ollama API client.
 *
 * Handles model discovery, chat completions, vision requests, and connection testing
 * via OpenAI-compatible endpoints exposed by LM Studio or Ollama.
 *
 * @package AI_SEO_Captain\Modules\LocalAI
 */

namespace AI_SEO_Captain\Modules\LocalAI;

class Local_AI_Provider
{

    /** @var string Base URL for the local AI server (e.g. http://localhost:1234/v1). */
    private $base_url;

    /** @var string Optional API key. */
    private $api_key;

    /** @var string Chat model ID. */
    private $model;

    /** @var string Vision model ID (empty if not configured). */
    private $vision_model;

    /** @var int Context window size in tokens. */
    private $context_window;

    /** @var int Request timeout in seconds. */
    private $timeout;

    /**
     * Create a new Local AI Provider instance.
     *
     * @param array $config {
     *     @type string $base_url       API base URL. Default 'http://localhost:1234/v1'.
     *     @type string $api_key        Optional API key.
     *     @type string $model          Chat model name.
     *     @type string $vision_model   Vision model name.
     *     @type int    $context_window Context window in tokens. Default 4096.
     *     @type int    $timeout        Timeout in seconds. Default 300.
     * }
     */
    public function __construct(array $config = array())
    {
        $this->base_url       = self::normalize_base_url($config['base_url'] ?? 'http://localhost:1234/v1');
        $this->api_key        = $config['api_key'] ?? '';
        $this->model          = $config['model'] ?? '';
        $this->vision_model   = $config['vision_model'] ?? '';
        $this->context_window = (int) ($config['context_window'] ?? 4096);
        $this->timeout        = (int) ($config['timeout'] ?? 3600);
    }

    /**
     * Build from saved plugin options.
     *
     * @return self
     */
    public static function from_options()
    {
        $options = get_option('ai_seo_captain_options', array());

        return new self(array(
            'base_url'       => $options['local_base_url'] ?? 'http://localhost:1234/v1',
            'api_key'        => $options['local_api_key'] ?? '',
            'model'          => $options['local_model'] ?? '',
            'vision_model'   => $options['local_vision_model'] ?? '',
            'context_window' => $options['context_window'] ?? 128000,
            'timeout'        => max(3600, (int) ($options['local_timeout'] ?? 3600)),
        ));
    }

    /**
     * Discover models loaded on the local AI server.
     *
     * @return array{success: bool, models?: array, error?: string}
     */
    public function discover_models()
    {
        $url = $this->base_url . '/models';

        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => $this->get_headers(),
        ));

        if (is_wp_error($response)) {
            return $this->server_connection_error($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (200 !== $code || ! is_array($data)) {
            return array(
                'success' => false,
                'error'   => $this->humanize_http_error($code, $body),
            );
        }

        $models = array();
        $raw_models = $data['data'] ?? $data['models'] ?? array();

        foreach ($raw_models as $m) {
            $id = $m['id'] ?? ($m['name'] ?? '');
            if ('' === $id) {
                continue;
            }

            $models[] = array(
                'id'             => $id,
                'name'           => $m['id'] ?? $id,
                'context_length' => (int) ($m['context_length'] ?? 0),
                'owned_by'       => $m['owned_by'] ?? 'unknown',
            );
        }

        if (empty($models)) {
            return array(
                'success' => false,
                'error'   => 'LM Studio is running but no models are loaded. Open LM Studio and load a model from the Models tab.',
            );
        }

        return array(
            'success' => true,
            'models'  => $models,
        );
    }

    /**
     * Send a chat completion request to the local AI server.
     *
     * @param array  $messages  Array of {role, content} message objects.
     * @param string $model     Model to use (defaults to configured chat model).
     * @param float  $temperature Temperature (0.0-2.0).
     * @param int    $max_tokens  Maximum tokens in response.
     *
     * @return array{success: bool, content?: string, usage?: array, latency?: float, error?: string}
     */
    public function chat(array $messages, $model = '', $temperature = 0.3, $max_tokens = null)
    {
        $model = $model ?: $this->model;

        if ('' === $model) {
            return array(
                'success' => false,
                'error'   => 'No model configured. Go to Local AI settings and select a chat model.',
            );
        }

        $url = $this->base_url . '/chat/completions';

        $payload = array(
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            // -1 = unlimited (generate until EOS or context limit).
            // Utility calls (probe_vision, test_connection) pass explicit
            // small values for speed; content generation uses -1 so the
            // model decides when it's done.
            'max_tokens'  => null !== $max_tokens ? (int) $max_tokens : -1,
        );

        $start = microtime(true);

        $response = wp_remote_post($url, array(
            'timeout' => $this->timeout,
            'headers' => $this->get_headers(),
            'body'    => wp_json_encode($payload),
        ));

        $latency = round(microtime(true) - $start, 2);

        if (is_wp_error($response)) {
            return $this->connection_error($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (200 !== $code || ! is_array($data)) {
            return array(
                'success' => false,
                'error'   => $this->humanize_http_error($code, $body),
            );
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        $finish_reason = $data['choices'][0]['finish_reason'] ?? 'stop';

        return array(
            'success'       => true,
            'content'       => $content,
            'finish_reason' => $finish_reason,
            'usage'         => $data['usage'] ?? array(),
            'model'         => $data['model'] ?? $model,
            'latency'       => $latency,
        );
    }

    /**
     * Send a vision request (image + text prompt) to the local AI server.
     *
     * @param string $image_base64  Base64-encoded image data.
     * @param string $mime_type     MIME type (e.g. image/jpeg).
     * @param string $prompt        Text prompt.
     * @param string $system_prompt Optional system prompt.
     *
     * @return array{success: bool, content?: string, usage?: array, latency?: float, error?: string}
     */
    public function vision($image_base64, $mime_type, $prompt, $system_prompt = '')
    {
        // Use dedicated vision model if set, otherwise fall back to the chat model
        // (works when the chat model itself is multimodal, e.g. Gemma 3 4B).
        $model = '' !== $this->vision_model ? $this->vision_model : $this->model;

        if ('' === $model) {
            return array(
                'success' => false,
                'error'   => 'No model configured. Select a chat model (ideally a multimodal one) in Local AI settings.',
            );
        }

        $messages = array();

        if ('' !== $system_prompt) {
            $messages[] = array(
                'role'    => 'system',
                'content' => $system_prompt,
            );
        }

        $messages[] = array(
            'role'    => 'user',
            'content' => array(
                array(
                    'type' => 'text',
                    'text' => $prompt,
                ),
                array(
                    'type'      => 'image_url',
                    'image_url' => array(
                        'url' => 'data:' . $mime_type . ';base64,' . $image_base64,
                    ),
                ),
            ),
        );

        return $this->chat($messages, $model, 0.3, 1024);
    }

    /**
     * Probe whether a model supports vision by sending a tiny 1x1 PNG test image.
     *
     * This is the only reliable way to detect vision capability — no guessing
     * from model names. If the model processes the image, it has vision.
     *
     * @param string $model_id Model to test. Defaults to the configured chat model.
     * @return array{success: bool, vision: bool, error?: string}
     */
    public function probe_vision($model_id = '')
    {
        $model_id = $model_id ?: $this->model;

        if ('' === $model_id) {
            return array('success' => false, 'vision' => false, 'error' => 'No model specified.');
        }

        // Minimal 1x1 red pixel PNG (67 bytes base64).
        $tiny_png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';

        $messages = array(
            array(
                'role'    => 'user',
                'content' => array(
                    array(
                        'type' => 'text',
                        'text' => 'What color is this pixel? Reply with one word only.',
                    ),
                    array(
                        'type'      => 'image_url',
                        'image_url' => array(
                            'url' => 'data:image/png;base64,' . $tiny_png,
                        ),
                    ),
                ),
            ),
        );

        $result = $this->chat($messages, $model_id, 0.1, 32);

        if ($result['success']) {
            return array('success' => true, 'vision' => true);
        }

        // Distinguish "no vision support" from "server down".
        $err = $result['error'] ?? '';
        $is_vision_error = (
            false !== stripos($err, 'vision')
            || false !== stripos($err, 'image')
            || false !== stripos($err, 'multimodal')
            || false !== stripos($err, 'does not support')
            || false !== stripos($err, 'invalid_request')
            || false !== stripos($err, 'content type')
            || false !== stripos($err, 'unsupported')
        );

        if ($is_vision_error) {
            return array('success' => true, 'vision' => false);
        }

        // Some other error (connection, timeout) — can't determine.
        return array('success' => false, 'vision' => false, 'error' => $err);
    }

    /**
     * Test the connection to the local AI server.
     *
     * @return array{success: bool, message?: string, model?: string, context_window?: int, latency?: float, error?: string}
     */
    public function test_connection()
    {
        // Step 1: Check if we can reach the server via model discovery.
        $discovery = $this->discover_models();

        if (! $discovery['success']) {
            return $discovery;
        }

        // Step 2: Send a simple chat request.
        $result = $this->chat(
            array(
                array(
                    'role'    => 'system',
                    'content' => 'You are a helpful assistant. Respond in one sentence.',
                ),
                array(
                    'role'    => 'user',
                    'content' => 'Say hello and confirm you are running locally.',
                ),
            ),
            $this->model,
            0.3,
            128
        );

        if (! $result['success']) {
            return $result;
        }

        // Find context length from discovered models.
        $ctx = $this->context_window;
        foreach ($discovery['models'] as $m) {
            if ($m['id'] === $this->model && $m['context_length'] > 0) {
                $ctx = $m['context_length'];
                break;
            }
        }

        return array(
            'success'        => true,
            'message'        => $result['content'],
            'model'          => $result['model'] ?? $this->model,
            'context_window' => $ctx,
            'latency'        => $result['latency'],
            'usage'          => $result['usage'],
        );
    }

    /**
     * Get the configured context window.
     *
     * @return int
     */
    public function get_context_window()
    {
        return $this->context_window;
    }

    /**
     * Minimum context window (tokens) required for each plugin operation.
     *
     * Based on real prompt measurements:
     * - generate_for_post() sends body (capped 20K chars ≈ 5,700 tokens) + SEO context + hierarchy
     * - chat_for_post() sends FULL HTML + plain text (no truncation) + conversation history
     * - generate_site_audit() sends pre-aggregated data (no body content)
     * - Site Chat tree mode: ~175 tokens per page, no body content
     * - Site Chat focus mode: ~3,000 tokens per page (full body + meta)
     */
    public const OPERATION_REQUIREMENTS = array(
        'connection_test' => array(
            'min_tokens' => 256,
            'label'      => 'Connection Test',
            'desc'       => 'Basic connectivity check',
        ),
        'site_audit' => array(
            'min_tokens' => 4000,
            'label'      => 'Site Audit',
            'desc'       => 'Aggregated site analysis (no body content)',
        ),
        'metadata_short' => array(
            'min_tokens' => 4000,
            'label'      => 'Metadata — Short Pages',
            'desc'       => 'Title & description for pages under 500 words',
        ),
        'metadata_medium' => array(
            'min_tokens' => 8000,
            'label'      => 'Metadata — Medium Pages',
            'desc'       => 'Title & description for pages up to 2,000 words',
        ),
        'metadata_long' => array(
            'min_tokens' => 12000,
            'label'      => 'Metadata — Long Pages',
            'desc'       => 'Title & description for pages over 2,000 words',
        ),
        'editor_chat_short' => array(
            'min_tokens' => 8000,
            'label'      => 'Editor AI Chat — Short Pages',
            'desc'       => 'SEO chat on pages under 500 words',
        ),
        'editor_chat_medium' => array(
            'min_tokens' => 16000,
            'label'      => 'Editor AI Chat — Medium Pages',
            'desc'       => 'SEO chat on pages up to 2,000 words (full HTML + text)',
        ),
        'editor_chat_long' => array(
            'min_tokens' => 32000,
            'label'      => 'Editor AI Chat — Long Pages',
            'desc'       => 'SEO chat on pages over 2,000 words (no truncation)',
        ),
        'site_chat_small' => array(
            'min_tokens' => 8000,
            'label'      => 'Site Chat — Small Sites',
            'desc'       => 'Strategic chat for sites under 30 pages',
        ),
        'site_chat_medium' => array(
            'min_tokens' => 16000,
            'label'      => 'Site Chat — Medium Sites',
            'desc'       => 'Strategic chat for sites up to 100 pages',
        ),
        'site_chat_large' => array(
            'min_tokens' => 32000,
            'label'      => 'Site Chat — Large Sites',
            'desc'       => 'Strategic chat for sites over 100 pages',
        ),
        'site_chat_focus' => array(
            'min_tokens' => 32000,
            'label'      => 'Site Chat — Focus Pages',
            'desc'       => 'Deep analysis with full page content',
        ),
    );

    /**
     * Assess what plugin operations this model can handle.
     *
     * Returns a structured assessment with a tier label, supported/unsupported
     * operations, and recommended model sizes for features that don't fit.
     *
     * @param int $context_window Override context window (0 = use configured).
     * @return array{tier: string, tier_label: string, context_window: int, supported: array, unsupported: array, recommendation: string}
     */
    public static function assess_capabilities($context_window)
    {
        $ctx = max(0, (int) $context_window);

        $supported   = array();
        $unsupported = array();

        foreach (self::OPERATION_REQUIREMENTS as $key => $op) {
            $entry = array(
                'key'        => $key,
                'label'      => $op['label'],
                'desc'       => $op['desc'],
                'min_tokens' => $op['min_tokens'],
            );

            if ($ctx >= $op['min_tokens']) {
                $supported[] = $entry;
            } else {
                $unsupported[] = $entry;
            }
        }

        // Determine tier.
        if ($ctx >= 32000) {
            $tier       = 'full';
            $tier_label = 'Full Feature Access';
            $recommendation = '';
        } elseif ($ctx >= 16000) {
            $tier       = 'standard';
            $tier_label = 'Standard';
            $recommendation = 'For full feature access (editor chat on long pages, site chat focus mode), use a model with 32K+ context. Recommended: Qwen 2.5 32B, Llama 3.1 70B.';
        } elseif ($ctx >= 8000) {
            $tier       = 'basic';
            $tier_label = 'Basic';
            $recommendation = 'This model can generate metadata and run site audits, but editor chat and site chat will be limited. For full features, use a model with 32K+ context.';
        } elseif ($ctx >= 4000) {
            $tier       = 'minimal';
            $tier_label = 'Minimal';
            $recommendation = 'This model can only handle short pages and site audits. Most plugin features require 16K+ context. Consider: Qwen 2.5 7B (32K), Llama 3.1 8B (128K).';
        } else {
            $tier       = 'insufficient';
            $tier_label = 'Insufficient';
            $recommendation = 'This model\'s context window is too small for SEO operations. The AI will miss most of your page content, resulting in poor-quality SEO. Minimum recommended: 8K context. Consider: Qwen 2.5 7B (32K), Gemma 3 4B (32K).';
        }

        return array(
            'tier'           => $tier,
            'tier_label'     => $tier_label,
            'context_window' => $ctx,
            'supported'      => $supported,
            'unsupported'    => $unsupported,
            'recommendation' => $recommendation,
        );
    }

    /**
     * Normalize the base URL so it always ends with /v1.
     *
     * Users may enter any of these:
     *   http://192.168.1.157:1234
     *   http://192.168.1.157:1234/
     *   http://192.168.1.157:1234/v1
     *   http://192.168.1.157:1234/v1/
     *
     * All must resolve to: http://192.168.1.157:1234/v1
     *
     * @param string $url Raw base URL.
     * @return string Normalized URL ending with /v1.
     */
    private static function normalize_base_url($url)
    {
        $url = rtrim(trim($url), '/');

        // Already ends with /v1 — good.
        if (preg_match('#/v\d+$#', $url)) {
            return $url;
        }

        return $url . '/v1';
    }

    /**
     * Get request headers.
     *
     * @return array
     */
    private function get_headers()
    {
        $headers = array(
            'Content-Type' => 'application/json',
        );

        if ('' !== $this->api_key) {
            $headers['Authorization'] = 'Bearer ' . $this->api_key;
        }

        return $headers;
    }

    /**
     * Build a user-friendly error for server-level connection issues (used by discover_models).
     *
     * These errors mean: the server is not reachable at all. Nothing about model quality.
     *
     * @param string $message WP_Error message.
     * @return array{success: bool, error: string}
     */
    private function server_connection_error($message)
    {
        $msg = $message;

        if (false !== strpos($message, 'Connection refused') || false !== strpos($message, 'cURL error 7')) {
            $msg = sprintf(
                'Cannot connect to %s. Is LM Studio / Ollama running? Start the server and try again.',
                esc_html($this->base_url)
            );
        } elseif (false !== strpos($message, 'timed out') || false !== strpos($message, 'cURL error 28')) {
            $msg = sprintf(
                'Server at %s is not responding (timed out after 15 seconds). Check that the URL is correct and the server is running.',
                esc_html($this->base_url)
            );
        } elseif (false !== strpos($message, 'Could not resolve host') || false !== strpos($message, 'cURL error 6')) {
            $msg = sprintf(
                'Cannot find server at %s. Check the IP address or hostname.',
                esc_html($this->base_url)
            );
        }

        return array(
            'success' => false,
            'error'   => $msg,
        );
    }

    /**
     * Build a user-friendly error for model-level request failures (used by chat, vision, etc.).
     *
     * @param string $message WP_Error message.
     * @return array{success: bool, error: string}
     */
    private function connection_error($message)
    {
        $msg = $message;

        if (false !== strpos($message, 'Connection refused') || false !== strpos($message, 'cURL error 7')) {
            $msg = sprintf(
                'Cannot connect to %s. Is LM Studio running? Start LM Studio and load a model, then try again.',
                esc_html($this->base_url)
            );
        } elseif (false !== strpos($message, 'timed out') || false !== strpos($message, 'cURL error 28')) {
            $msg = sprintf(
                'Request timed out after %d seconds. The model may need more RAM or the prompt is too large. Try a smaller model or increase the timeout.',
                $this->timeout
            );
        } elseif (false !== strpos($message, 'Could not resolve host') || false !== strpos($message, 'cURL error 6')) {
            $msg = sprintf(
                'Cannot reach the server at %s. Check the URL in your Local AI settings.',
                esc_html($this->base_url)
            );
        }

        return array(
            'success' => false,
            'error'   => $msg,
        );
    }

    /**
     * Convert HTTP error codes and body into user-friendly messages.
     *
     * @param int    $code HTTP status code.
     * @param string $body Response body.
     * @return string
     */
    private function humanize_http_error($code, $body)
    {
        $decoded = json_decode($body, true);
        $api_msg = '';

        if (is_array($decoded)) {
            $api_msg = $decoded['error']['message'] ?? ($decoded['error'] ?? '');
            if (is_array($api_msg)) {
                $api_msg = $api_msg['message'] ?? wp_json_encode($api_msg);
            }
        }

        // Context length exceeded.
        if (400 === $code && (false !== stripos($api_msg, 'context') || false !== stripos($api_msg, 'token'))) {
            return sprintf(
                'Content exceeds your model\'s %s-token context window. Load a model with a larger context or increase the Context Window setting.',
                number_format($this->context_window)
            );
        }

        switch ($code) {
            case 400:
                return 'Bad request: ' . ($api_msg ?: 'The server could not process this request.');
            case 401:
            case 403:
                return 'Authentication failed. Check the API key in your Local AI settings.';
            case 404:
                $model_name = $this->model ?: 'unknown';
                return sprintf(
                    'Model "%s" not found on the server. It may have been unloaded. Click Discover Models to refresh.',
                    esc_html($model_name)
                );
            case 500:
            case 502:
            case 503:
                return 'The AI server encountered an error. Check LM Studio logs for details.';
            default:
                return sprintf(
                    'Unexpected response (HTTP %d) from the AI server.%s',
                    $code,
                    $api_msg ? ' ' . $api_msg : ''
                );
        }
    }
}
