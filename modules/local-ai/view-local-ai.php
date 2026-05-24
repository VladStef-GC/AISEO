<?php

/**
 * Local AI admin page template.
 *
 * Uses a standard HTML form POST for settings persistence (no AJAX dependency).
 * AJAX is used only for Connect/Assess/Chat testing.
 *
 * @package AI_SEO_Captain\Modules\LocalAI
 */

if (! defined('ABSPATH')) {
    exit;
}

// ─── Handle Form Save (POST) ─────────────────────────────────────────
$save_notice = '';
if (isset($_POST['local_ai_save_nonce']) && wp_verify_nonce($_POST['local_ai_save_nonce'], 'local_ai_save_settings')) {
    $options = get_option('ai_seo_captain_options', array());

    $options['local_base_url']       = esc_url_raw(trim($_POST['local_base_url'] ?? ''));
    $options['local_model']          = sanitize_text_field($_POST['local_model'] ?? '');
    $options['local_vision_model']   = sanitize_text_field($_POST['local_vision_model'] ?? '');
    $options['local_timeout']        = max(10, min(900, (int) ($_POST['local_timeout'] ?? 120)));

    // Context window — use custom value if the dropdown is set to "custom".
    $ctx_raw = $_POST['context_window'] ?? '';
    if ('custom' === $ctx_raw) {
        $ctx_val = (int) ($_POST['context_window_custom'] ?? 128000);
    } else {
        $ctx_val = (int) $ctx_raw;
    }
    $options['context_window'] = max(2048, $ctx_val);

    // Only update API key if a real value was sent.
    $api_key = $_POST['local_api_key'] ?? '';
    if ('' !== $api_key && '••••••••' !== $api_key) {
        $options['local_api_key'] = sanitize_text_field($api_key);
    }

    // Bypass the Settings::sanitize() filter which enforces a 32K minimum
    // context window and reconstructs the full options array from defaults.
    // The Local AI handler already validates all fields above.
    remove_all_filters('sanitize_option_ai_seo_captain_options');
    update_option('ai_seo_captain_options', $options);

    // Set transient for admin bar status.
    if ('' !== $options['local_model']) {
        set_transient('ai_seo_captain_local_ai_status', array(
            'connected' => true,
            'model'     => $options['local_model'],
            'context'   => (int) ($options['context_window'] ?? 128000),
            'time'      => time(),
        ), 5 * MINUTE_IN_SECONDS);
    }

    $save_notice = 'success';
}

// ─── Handle Disconnect (POST) ────────────────────────────────────────
if (isset($_POST['local_ai_disconnect_nonce']) && wp_verify_nonce($_POST['local_ai_disconnect_nonce'], 'local_ai_disconnect')) {
    $options = get_option('ai_seo_captain_options', array());
    unset(
        $options['local_base_url'],
        $options['local_model'],
        $options['local_vision_model'],
        $options['local_api_key'],
        $options['local_context_window'],
        $options['local_timeout']
    );
    if ('local' === ($options['provider'] ?? '')) {
        unset($options['provider']);
    }
    remove_all_filters('sanitize_option_ai_seo_captain_options');
    update_option('ai_seo_captain_options', $options);
    delete_transient('ai_seo_captain_local_ai_status');
    $save_notice = 'disconnected';
}

// ─── Load Saved Values ───────────────────────────────────────────────
$options = get_option('ai_seo_captain_options', array());

$saved_base_url    = $options['local_base_url'] ?? '';
$saved_model       = $options['local_model'] ?? '';
$saved_vision      = $options['local_vision_model'] ?? '';
$saved_timeout     = (int) ($options['local_timeout'] ?? 120);
$saved_ctx         = (int) ($options['context_window'] ?? 128000);
$has_api_key       = '' !== ($options['local_api_key'] ?? '');

// Pre-defined context window options (tokens).
$ctx_presets = array(
    4096   => '4K',
    8192   => '8K',
    16384  => '16K',
    32768  => '32K',
    65536  => '64K',
    131072 => '128K',
    262144 => '256K',
);
$ctx_is_preset = isset($ctx_presets[$saved_ctx]);
?>

<div class="wrap local-ai-wrap">
    <h1>🖥️ Local AI — LM Studio / Ollama</h1>
    <p class="description">Connect to a local AI server for free, private SEO operations. Works with LM Studio, Ollama, or any OpenAI-compatible API.</p>

    <?php if ('success' === $save_notice) : ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>✅ Settings saved.</strong></p>
        </div>
    <?php elseif ('disconnected' === $save_notice) : ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>Local AI disconnected.</strong> All settings cleared.</p>
        </div>
    <?php endif; ?>

    <!-- Status Banner (JS updates this dynamically for Connect/Assess) -->
    <div id="local-ai-status" class="local-ai-banner local-ai-banner--info" style="display:none;">
        <span class="local-ai-banner__icon">ℹ️</span>
        <span class="local-ai-banner__text"></span>
    </div>

    <form method="POST" id="local-ai-form" autocomplete="off">
        <?php wp_nonce_field('local_ai_save_settings', 'local_ai_save_nonce'); ?>

        <!-- Section 1: Server Connection -->
        <div class="local-ai-card">
            <h2>Server Connection</h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="local-ai-base-url">Server URL</label></th>
                    <td>
                        <input type="url" id="local-ai-base-url" name="local_base_url" class="regular-text" value="<?php echo esc_attr($saved_base_url); ?>" placeholder="http://192.168.1.100:1234" autocomplete="off">
                        <p class="description">
                            LM Studio: <code>http://your-ip:1234</code> &nbsp;|&nbsp; Ollama: <code>http://your-ip:11434</code>
                            <br>Enter the server address and port. The <code>/v1</code> API path is added automatically.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="local-ai-api-key">API Key <span class="description">(optional)</span></label></th>
                    <td>
                        <input type="text" id="local-ai-api-key" name="local_api_key" class="regular-text" value="<?php echo $has_api_key ? '••••••••' : ''; ?>" placeholder="Leave empty if not required" autocomplete="new-password">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="local-ai-timeout">Timeout</label></th>
                    <td>
                        <input type="number" id="local-ai-timeout" name="local_timeout" class="small-text" value="<?php echo esc_attr($saved_timeout); ?>" min="10" max="900"> seconds
                        <p class="description">Local models can be slower than cloud. Increase if you get timeout errors. Deep analysis audits may need 600+ seconds for large models.</p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" id="local-ai-connect" class="button button-secondary">
                    🔌 Connect to Server
                </button>
                <span id="local-ai-connect-spinner" class="spinner" style="float:none;"></span>
            </p>
        </div>

        <!-- Section 2: Model Configuration (ALWAYS VISIBLE) -->
        <div id="local-ai-models-section" class="local-ai-card">
            <h2>Model Configuration</h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="local-ai-model">Chat Model</label></th>
                    <td>
                        <select id="local-ai-model" name="local_model" class="regular-text">
                            <option value="">— Select a model —</option>
                            <?php if ('' !== $saved_model) : ?>
                                <option value="<?php echo esc_attr($saved_model); ?>" selected><?php echo esc_html($saved_model); ?></option>
                            <?php endif; ?>
                        </select>
                        <div id="local-ai-vision-badge" class="local-ai-badge" style="display:none;"></div>
                        <p class="description">Primary model for SEO metadata, audits, chat, and analysis. Click <strong>Connect to Server</strong> to load available models.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="local-ai-vision-model">Vision Model <span class="description">(optional)</span></label></th>
                    <td>
                        <select id="local-ai-vision-model" name="local_vision_model" class="regular-text">
                            <option value="">— None (no vision) —</option>
                            <?php if ('' !== $saved_vision) : ?>
                                <option value="<?php echo esc_attr($saved_vision); ?>" selected><?php echo esc_html($saved_vision); ?></option>
                            <?php endif; ?>
                        </select>
                        <p class="description">Multimodal model for image alt text generation (Qwen2-VL, LLaVA, etc.). Only vision models are shown.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="local-ai-context-window">Context Window</label></th>
                    <td>
                        <select id="local-ai-context-window" name="context_window" class="regular-text">
                            <?php foreach ($ctx_presets as $val => $label) : ?>
                                <option value="<?php echo (int) $val; ?>" <?php selected($ctx_is_preset && $saved_ctx === $val); ?>><?php echo esc_html($label . ' (' . number_format($val) . ' tokens)'); ?></option>
                            <?php endforeach; ?>
                            <option value="custom" <?php selected(! $ctx_is_preset); ?>>Custom…</option>
                        </select>
                        <input type="number" id="local-ai-context-custom" name="context_window_custom"
                            value="<?php echo $ctx_is_preset ? '' : (int) $saved_ctx; ?>"
                            min="2048" step="1024" class="small-text"
                            style="width:120px;<?php echo $ctx_is_preset ? 'display:none;' : ''; ?>"
                            placeholder="e.g. 49152">
                        <span id="local-ai-context-detected" class="description" style="display:none;margin-left:6px;"></span>
                        <p class="description" style="margin-top:6px;">
                            How many tokens your model can process at once. This controls how many focus pages AI can include in Site Chat.
                        </p>
                        <div class="notice notice-warning inline" style="margin:8px 0 0;padding:8px 12px;max-width:560px;">
                            <p style="margin:0;">
                                <strong>⚠️ Critical:</strong> This value <strong>must match</strong> the context length configured in your LM Studio or Ollama server.
                                Setting it higher than the model's actual limit will cause requests to fail.
                            </p>
                            <p style="margin:4px 0 0;font-size:12px;">
                                <strong>Where to find it:</strong> In LM Studio → select your model → look for <code>n_ctx</code> or <em>Context Length</em> in the model settings panel.
                                In Ollama → run <code>ollama show &lt;model&gt;</code> and check <code>num_ctx</code>.
                            </p>
                        </div>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" id="local-ai-test-model" class="button button-secondary">
                    🔬 Test Selected Model
                </button>
                <span id="local-ai-test-model-spinner" class="spinner" style="float:none;"></span>
                <span id="local-ai-test-model-hint" class="description" style="margin-left:8px;"></span>
            </p>

            <!-- Capability Assessment (dynamic, updated by JS) -->
            <div id="local-ai-capabilities" class="local-ai-capabilities" style="display:none;">
                <div id="local-ai-cap-tier" class="local-ai-cap-tier"></div>
                <div id="local-ai-cap-recommendation" class="local-ai-cap-recommendation" style="display:none;"></div>
                <ul id="local-ai-cap-list" class="local-ai-cap-list"></ul>
            </div>

            <!-- Test Connection Result -->
            <div id="local-ai-test-result" style="display:none;"></div>
        </div>

        <!-- Save button -->
        <p class="submit">
            <input type="submit" class="button button-primary button-hero" value="💾 Save Settings">
        </p>
    </form>

    <!-- Disconnect (separate form to avoid accidental submit) -->
    <form method="POST" style="margin-top:10px;">
        <?php wp_nonce_field('local_ai_disconnect', 'local_ai_disconnect_nonce'); ?>
        <button type="submit" class="button button-link-delete" onclick="return confirm('Disconnect Local AI? This will clear all Local AI settings.');">
            ⛔ Disconnect Local AI
        </button>
    </form>

    <!-- Section 3: Test Chat -->
    <div class="local-ai-card" style="margin-top:20px;">
        <h2>💬 Test Chat</h2>
        <p class="description">Verify your local AI works before switching providers. Chat messages are not saved.</p>

        <div id="local-ai-chat-messages" class="local-ai-chat-messages">
            <div class="local-ai-chat-empty">
                Send a message to test your local AI connection.
            </div>
        </div>

        <div class="local-ai-chat-input-row">
            <input type="text" id="local-ai-chat-input" class="regular-text" placeholder="Type a message..." autocomplete="off">
            <button type="button" id="local-ai-chat-send" class="button button-primary">Send</button>
            <span id="local-ai-chat-spinner" class="spinner" style="float:none;"></span>
        </div>

        <div class="local-ai-quick-tests">
            <span class="description">Quick tests:</span>
            <button type="button" class="button button-small local-ai-quick-test" data-prompt="Generate an SEO title for a blog post about 'Best WordPress SEO Plugins 2025'. Return only the title, max 60 characters.">SEO Title</button>
            <button type="button" class="button button-small local-ai-quick-test" data-prompt="Generate a meta description for a page about 'WordPress Performance Optimization Guide'. Return only the description, max 155 characters.">Meta Description</button>
            <button type="button" class="button button-small local-ai-quick-test" data-prompt="What are the top 3 SEO mistakes for e-commerce websites? Be concise, use bullet points.">SEO Tips</button>
        </div>
    </div>

    <!-- Section 4: Help & Setup Guide -->
    <div class="local-ai-card local-ai-help">
        <h2>📖 Quick Setup Guide</h2>

        <div class="local-ai-help-columns">
            <div class="local-ai-help-col">
                <h3>LM Studio</h3>
                <ol>
                    <li>Download <a href="https://lmstudio.ai" target="_blank" rel="noopener">LM Studio</a></li>
                    <li>Go to the <strong>Models</strong> tab → search and download a model</li>
                    <li>Go to <strong>Local Server</strong> tab → click <strong>Start Server</strong></li>
                    <li>Come back here and click <strong>Connect to Server</strong></li>
                </ol>
                <p class="description">Default URL: <code>http://localhost:1234</code></p>
            </div>

            <div class="local-ai-help-col">
                <h3>Ollama</h3>
                <ol>
                    <li>Install <a href="https://ollama.ai" target="_blank" rel="noopener">Ollama</a></li>
                    <li>Run: <code>ollama pull qwen2.5:7b</code></li>
                    <li>Run: <code>ollama serve</code></li>
                    <li>Come back here and click <strong>Connect to Server</strong></li>
                </ol>
                <p class="description">Default URL: <code>http://localhost:11434</code></p>
            </div>
        </div>

        <h3>Recommended Models (128K+ context)</h3>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Model</th>
                    <th>Size</th>
                    <th>RAM</th>
                    <th>Best for</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Qwen 2.5 72B</strong></td>
                    <td>72B</td>
                    <td>48–64 GB</td>
                    <td>Best quality — full feature parity with cloud (128K context)</td>
                </tr>
                <tr>
                    <td><strong>Llama 3.1 70B</strong></td>
                    <td>70B</td>
                    <td>48–64 GB</td>
                    <td>Excellent instruction following (128K context)</td>
                </tr>
                <tr>
                    <td><strong>Qwen2.5-VL 72B</strong> (vision)</td>
                    <td>72B</td>
                    <td>48–64 GB</td>
                    <td>Image alt text + all text features (128K context)</td>
                </tr>
                <tr>
                    <td><strong>Qwen2-VL 7B</strong> (vision)</td>
                    <td>7B</td>
                    <td>8–16 GB</td>
                    <td>Lightweight vision sidecar for alt text only</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>