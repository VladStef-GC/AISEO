/**
 * Local AI — Admin page JavaScript.
 *
 * Handles model discovery, connection testing, test chat, and settings save.
 *
 * @package AI_SEO_Captain\Modules\LocalAI
 */
(function ($) {
    'use strict';

    // ─── Globals ─────────────────────────────────────────────────────────
    var config = window.localAiConfig || {};
    var $status = $('#local-ai-status');

    // ─── Helpers ─────────────────────────────────────────────────────────

    function showBanner(type, text) {
        var icons = { info: 'ℹ️', success: '✅', error: '❌', warning: '⚠️' };
        $status
            .removeClass('local-ai-banner--info local-ai-banner--success local-ai-banner--error local-ai-banner--warning')
            .addClass('local-ai-banner--' + type)
            .find('.local-ai-banner__icon').text(icons[type] || 'ℹ️').end()
            .find('.local-ai-banner__text').html(text).end()
            .show();
    }

    function setSpinner($spinner, active) {
        $spinner.toggleClass('is-active', active);
    }

    function getFormValues() {
        var ctxSelect = $('#local-ai-context-window').val();
        var ctxValue = ctxSelect === 'custom'
            ? parseInt($('#local-ai-context-custom').val(), 10) || 131072
            : parseInt(ctxSelect, 10);

        return {
            base_url: $('#local-ai-base-url').val().replace(/\/+$/, ''),
            api_key: $('#local-ai-api-key').val(),
            model: $('#local-ai-model').val(),
            vision_model: $('#local-ai-vision-model').val(),
            context_window: ctxValue,
            timeout: parseInt($('#local-ai-timeout').val(), 10) || 120
        };
    }

    function formatNumber(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ─── Step 1: Connect to Server ─────────────────────────────────────
    // Pure connectivity check — is the server reachable? Are models loaded?
    // Does NOT assess model quality, context window, or vision.

    $('#local-ai-connect').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#local-ai-connect-spinner');
        var values = getFormValues();

        $btn.prop('disabled', true);
        setSpinner($spinner, true);
        showBanner('info', 'Connecting to <strong>' + escapeHtml(values.base_url) + '</strong>...');

        $.post(config.ajaxurl, {
            action: 'local_ai_discover_models',
            nonce: config.nonce,
            base_url: values.base_url,
            api_key: values.api_key
        })
            .done(function (resp) {
                if (resp.success && resp.data.models) {
                    populateModelDropdowns(resp.data.models);
                    showBanner('success', '🟢 Server is live — <strong>' + resp.data.models.length + '</strong> model(s) available. Select a model and <strong>Save Settings</strong>.');
                    enableTestModelButton();
                } else {
                    showBanner('error', resp.data ? resp.data.error : 'Unknown error.');
                    disableTestModelButton();
                }
            })
            .fail(function (xhr) {
                var msg = 'Connection failed.';
                try { msg = JSON.parse(xhr.responseText).data.error || msg; } catch (e) { }
                showBanner('error', escapeHtml(msg));
                disableTestModelButton();
            })
            .always(function () {
                $btn.prop('disabled', false);
                setSpinner($spinner, false);
            });
    });

    function enableTestModelButton() {
        $('#local-ai-test-model').prop('disabled', false);
        $('#local-ai-test-model-hint').text('');
    }

    function disableTestModelButton() {
        $('#local-ai-test-model').prop('disabled', true);
        $('#local-ai-test-model-hint').text('Connect to server first');
        $('#local-ai-capabilities').slideUp(200);
    }

    /**
     * Check if a model ID/name indicates vision (multimodal) capability.
     */
    function isVisionModel(id) {
        var lower = (id || '').toLowerCase();
        // Common vision/multimodal identifiers in model names.
        return /(-vl[-_]?|vision|llava|bakllava|minicpm-v|cogvlm|internvl|phi-3.*vision|gemma-3.*it)/.test(lower);
    }

    function populateModelDropdowns(models) {
        var $chat = $('#local-ai-model').empty().append('<option value="">— Select a model —</option>');
        var $vision = $('#local-ai-vision-model').empty().append('<option value="">— None (no vision) —</option>');
        var visionCount = 0;

        models.forEach(function (m) {
            var label = m.name || m.id;
            if (m.context_length > 0) {
                label += ' (' + formatNumber(m.context_length) + ' ctx)';
            }
            var opt = '<option value="' + escapeHtml(m.id) + '" data-ctx="' + m.context_length + '">' + escapeHtml(label) + '</option>';
            $chat.append(opt);

            // Only add vision-capable models to the vision dropdown.
            if (isVisionModel(m.id)) {
                $vision.append(opt);
                visionCount++;
            }
        });

        // If no vision models found, show a helpful message.
        if (visionCount === 0) {
            $vision.append('<option value="" disabled>No vision models found on server</option>');
        }

        // Auto-select saved model if it exists in the list.
        if (config.model) {
            $chat.val(config.model);
            autoSetContext($chat);
        }
        if (config.visionModel) {
            $vision.val(config.visionModel);
        }
    }

    // Auto-set context window when chat model changes.
    $('#local-ai-model').on('change', function () {
        autoSetContext($(this));
    });

    function autoSetContext($select) {
        var $opt = $select.find(':selected');
        var ctx = parseInt($opt.data('ctx'), 10);

        if (ctx > 0) {
            // Try to match a preset.
            var matched = false;
            $('#local-ai-context-window option').each(function () {
                if (parseInt($(this).val(), 10) === ctx) {
                    $('#local-ai-context-window').val($(this).val());
                    matched = true;
                    return false;
                }
            });

            if (!matched) {
                $('#local-ai-context-window').val('custom');
                $('#local-ai-context-custom').val(ctx).show();
            } else {
                $('#local-ai-context-custom').hide();
            }

            $('#local-ai-context-detected')
                .text('(Auto-detected: ' + formatNumber(ctx) + ' tokens)')
                .show();

            updateCapabilities(ctx);
        }
    }

    // Show/hide custom context input.
    $('#local-ai-context-window').on('change', function () {
        if ($(this).val() === 'custom') {
            $('#local-ai-context-custom').show().focus();
        } else {
            $('#local-ai-context-custom').hide();
        }
        updateCapabilities(getCurrentContextWindow());
    });

    // Also update on custom input change.
    $('#local-ai-context-custom').on('input', function () {
        updateCapabilities(parseInt($(this).val(), 10) || 0);
    });

    function getCurrentContextWindow() {
        var sel = $('#local-ai-context-window').val();
        if (sel === 'custom') {
            return parseInt($('#local-ai-context-custom').val(), 10) || 0;
        }
        return parseInt(sel, 10) || 0;
    }

    // ─── Step 2: Test Selected Model ───────────────────────────────────────
    // Combined: capability assessment + vision probe + live connection test.

    $('#local-ai-test-model').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#local-ai-test-model-spinner');
        var $result = $('#local-ai-test-result');
        var model = $('#local-ai-model').val();
        var values = getFormValues();

        if (!model) {
            showBanner('warning', 'Please select a chat model first.');
            return;
        }

        $btn.prop('disabled', true);
        setSpinner($spinner, true);
        $result.hide();
        $('#local-ai-capabilities').hide();
        showBanner('info', 'Testing <strong>' + escapeHtml(model) + '</strong>...');

        // Phase 1: Vision probe.
        $('#local-ai-test-model-hint').text('Checking vision support...');
        probeVision(model, function () {
            $('#local-ai-test-model-hint').text('Testing connection...');

            // Phase 2: Live connection test (actual chat request + JSON test).
            $.post(config.ajaxurl, $.extend({ action: 'local_ai_test_connection', nonce: config.nonce }, values))
                .done(function (resp) {
                    if (resp.success) {
                        var d = resp.data;

                        // Phase 3: Show capabilities AFTER connection is confirmed.
                        var ctx = d.context_window || getCurrentContextWindow();
                        $('#local-ai-test-model-hint').text('Evaluating capabilities...');

                        // Reveal capabilities with a brief delay so the user
                        // sees the sequential progression (connection → capabilities).
                        setTimeout(function () {
                            updateCapabilities(ctx);
                        }, 400);

                        var tierText = '';
                        setTimeout(function () {
                            tierText = $('#local-ai-cap-tier strong').text() || 'Full Feature Access';
                            var banner = '✅ <strong>' + escapeHtml(d.model) + '</strong> — ' +
                                tierText + ' — ' + formatNumber(d.context_window) + ' tokens — ' +
                                d.latency + 's response time.';
                            if (d.json_test === false) {
                                banner += '<br>⚠️ <em>JSON test failed — the model may produce malformed audit/metadata responses.</em>';
                            }
                            showBanner(d.json_test === false ? 'warning' : 'success', banner);
                        }, 500);

                        var resultHtml =
                            '<div class="local-ai-test-success">' +
                            '<p><strong>Model:</strong> ' + escapeHtml(d.model) + '</p>' +
                            '<p><strong>Context:</strong> ' + formatNumber(d.context_window) + ' tokens</p>' +
                            '<p><strong>Response time:</strong> ' + d.latency + 's</p>' +
                            '<p><strong>AI says:</strong> ' + escapeHtml(d.message) + '</p>';

                        // Show real-world content estimation warning.
                        if (d.site_content_estimate) {
                            var est = d.site_content_estimate;
                            resultHtml += '<p><strong>Site pages:</strong> ' + est.page_count +
                                ' — Estimated max prompt: ~' + formatNumber(est.estimated_max_tokens) + ' tokens</p>';
                            if (est.warning) {
                                resultHtml += '<div class="local-ai-content-warning" style="margin-top:8px;padding:8px 12px;background:#fff3cd;border-left:4px solid #ffc107;border-radius:3px;font-size:13px;">' +
                                    '⚠️ <strong>Real-world note:</strong> ' + escapeHtml(est.warning) +
                                    '<br><em>The compressor will automatically fit content to your context window, but some sibling data may be trimmed.</em>' +
                                    '</div>';
                            }
                        }

                        resultHtml += '</div>';
                        $result.html(resultHtml).slideDown(200);
                    } else {
                        showBanner('error', resp.data ? resp.data.error : 'Connection test failed.');
                        $result.html('<div class="local-ai-test-error">' + escapeHtml(resp.data ? resp.data.error : 'Unknown error') + '</div>').slideDown(200);
                    }
                })
                .fail(function () {
                    showBanner('error', 'Connection test failed. Check your server URL and model.');
                })
                .always(function () {
                    $btn.prop('disabled', false);
                    setSpinner($spinner, false);
                    $('#local-ai-test-model-hint').text('');
                });
        });
    });

    // ─── Capability Assessment ──────────────────────────────────────────

    function updateCapabilities(ctx) {
        var $panel = $('#local-ai-capabilities');
        var ops = config.opRequirements || {};
        var keys = Object.keys(ops);

        if (!ctx || keys.length === 0) {
            $panel.hide();
            return;
        }

        var supported = [];
        var unsupported = [];

        keys.forEach(function (key) {
            var op = ops[key];
            var entry = { key: key, label: op.label, desc: op.desc, min: op.min_tokens };
            if (ctx >= op.min_tokens) {
                supported.push(entry);
            } else {
                unsupported.push(entry);
            }
        });

        // Determine tier.
        var tier, tierLabel, tierClass, recommendation;
        if (ctx >= 32000) {
            tier = 'full'; tierLabel = 'Full Feature Access'; tierClass = 'tier-full';
            recommendation = '';
        } else if (ctx >= 16000) {
            tier = 'standard'; tierLabel = 'Standard'; tierClass = 'tier-standard';
            recommendation = 'For full feature access (editor chat on long pages, site chat focus mode), use a model with 32K+ context. Recommended: <strong>Qwen 2.5 32B</strong>, <strong>Llama 3.1 70B</strong>.';
        } else if (ctx >= 8000) {
            tier = 'basic'; tierLabel = 'Basic'; tierClass = 'tier-basic';
            recommendation = 'This model can generate metadata and run site audits, but editor chat and site chat will be limited. For full features, use a model with <strong>32K+ context</strong>.';
        } else if (ctx >= 4000) {
            tier = 'minimal'; tierLabel = 'Minimal'; tierClass = 'tier-minimal';
            recommendation = 'This model can only handle short pages and site audits. Most plugin features require 16K+ context. Consider: <strong>Qwen 2.5 7B</strong> (32K), <strong>Llama 3.1 8B</strong> (128K).';
        } else {
            tier = 'insufficient'; tierLabel = 'Insufficient'; tierClass = 'tier-insufficient';
            recommendation = '⛔ This model\'s context window is too small for SEO operations. The AI will miss most of your page content, producing poor-quality SEO. Minimum recommended: <strong>8K context</strong>. Consider: <strong>Qwen 2.5 7B</strong> (32K), <strong>Gemma 3 4B</strong> (32K).';
        }

        // Render tier.
        $('#local-ai-cap-tier')
            .attr('class', 'local-ai-cap-tier ' + tierClass)
            .html(
                '<strong>' + tierLabel + '</strong> — ' +
                formatNumber(ctx) + ' tokens — ' +
                supported.length + ' of ' + keys.length + ' operations supported'
            );

        // Render recommendation.
        var $rec = $('#local-ai-cap-recommendation');
        if (recommendation) {
            $rec.html(recommendation).show();
        } else {
            $rec.hide();
        }

        // Render single-column feature list with status icons.
        var $list = $('#local-ai-cap-list').empty();

        // All operations in one list — supported first, then unsupported.
        supported.forEach(function (op) {
            $list.append('<li class="cap-ok">✅ <strong>' + escapeHtml(op.label) + '</strong><br><span class="desc">' + escapeHtml(op.desc) + '</span></li>');
        });
        unsupported.forEach(function (op) {
            $list.append('<li class="cap-no">🚫 <strong>' + escapeHtml(op.label) + '</strong> <span class="cap-need">(needs ' + formatNumber(op.min) + '+ tokens)</span><br><span class="desc">' + escapeHtml(op.desc) + '</span></li>');
        });

        $panel.slideDown(200);
    }

    // ─── Vision Probe ───────────────────────────────────────────────────

    var visionCache = {}; // Cache results so we don't re-probe the same model.

    function probeVision(modelId, callback) {
        var $badge = $('#local-ai-vision-badge');
        var done = callback || function () { };

        if (!modelId) {
            $badge.hide();
            done();
            return;
        }

        // Check cache first.
        if (visionCache.hasOwnProperty(modelId)) {
            showVisionBadge(visionCache[modelId]);
            done();
            return;
        }

        $badge
            .removeClass('local-ai-badge--vision local-ai-badge--text local-ai-badge--error')
            .addClass('local-ai-badge--checking')
            .html('🔍 Checking vision capability...')
            .show();

        var values = getFormValues();

        $.post(config.ajaxurl, {
            action: 'local_ai_check_vision',
            nonce: config.nonce,
            model: modelId,
            base_url: values.base_url,
            api_key: values.api_key,
            timeout: values.timeout
        })
            .done(function (resp) {
                if (resp.success) {
                    visionCache[modelId] = resp.data.vision;
                    showVisionBadge(resp.data.vision);
                } else {
                    $badge
                        .removeClass('local-ai-badge--checking')
                        .addClass('local-ai-badge--error')
                        .html('⚠️ Could not determine vision capability')
                        .show();
                }
            })
            .fail(function () {
                $badge
                    .removeClass('local-ai-badge--checking')
                    .addClass('local-ai-badge--error')
                    .html('⚠️ Vision check failed — server may be busy')
                    .show();
            })
            .always(function () {
                done();
            });
    }

    function showVisionBadge(hasVision) {
        var $badge = $('#local-ai-vision-badge');
        $badge.removeClass('local-ai-badge--checking local-ai-badge--error');

        if (hasVision) {
            $badge
                .addClass('local-ai-badge--vision')
                .html('👁️ <strong>Vision capable</strong> — this model can analyze images (alt text, audits)')
                .show();
        } else {
            $badge
                .addClass('local-ai-badge--text')
                .html('💬 <strong>Text only</strong> — this model cannot analyze images. Select a multimodal model in Vision Model for image tasks.')
                .show();
        }
    }

    // ─── Save Settings (form POST — handled by PHP, no AJAX needed) ───
    // The form submit saves via standard POST. The JS save button is removed.
    // Context window: when dropdown is "custom", sync the custom value into context_window.
    $('#local-ai-form').on('submit', function () {
        var ctxSelect = $('#local-ai-context-window').val();
        if (ctxSelect === 'custom') {
            var customVal = parseInt($('#local-ai-context-custom').val(), 10) || 128000;
            // Ensure the select sends "custom" so PHP reads the custom input field.
            $('#local-ai-context-custom').val(customVal);
        }
    });

    // ─── Test Chat ──────────────────────────────────────────────────────

    var $chatMessages = $('#local-ai-chat-messages');
    var $chatInput = $('#local-ai-chat-input');
    var $chatSend = $('#local-ai-chat-send');
    var $chatSpinner = $('#local-ai-chat-spinner');

    function appendChatMessage(role, text, meta) {
        // Remove the empty placeholder.
        $chatMessages.find('.local-ai-chat-empty').remove();

        var cls = role === 'user' ? 'local-ai-msg--user' : 'local-ai-msg--ai';
        var icon = role === 'user' ? '👤' : '🤖';

        var html = '<div class="local-ai-msg ' + cls + '">' +
            '<span class="local-ai-msg__icon">' + icon + '</span>' +
            '<div class="local-ai-msg__body">' +
            '<div class="local-ai-msg__text">' + escapeHtml(text) + '</div>';

        if (meta) {
            html += '<div class="local-ai-msg__meta">' + escapeHtml(meta) + '</div>';
        }

        html += '</div></div>';

        $chatMessages.append(html);
        $chatMessages.scrollTop($chatMessages[0].scrollHeight);
    }

    function sendChatMessage() {
        var message = $.trim($chatInput.val());
        if (!message) return;

        var values = getFormValues();
        if (!values.model) {
            showBanner('warning', 'Please select a chat model and save settings before using test chat.');
            return;
        }

        appendChatMessage('user', message);
        $chatInput.val('').focus();
        $chatSend.prop('disabled', true);
        setSpinner($chatSpinner, true);

        $.post(config.ajaxurl, {
            action: 'local_ai_chat',
            nonce: config.nonce,
            message: message,
            base_url: values.base_url,
            api_key: values.api_key,
            model: values.model,
            context_window: values.context_window,
            timeout: values.timeout
        })
            .done(function (resp) {
                if (resp.success) {
                    var d = resp.data;
                    var meta = d.model + ' • ' + d.latency + 's';
                    if (d.usage && d.usage.total_tokens) {
                        meta += ' • ' + d.usage.total_tokens + ' tokens';
                    }
                    appendChatMessage('ai', d.content, meta);
                } else {
                    appendChatMessage('ai', '❌ Error: ' + (resp.data ? resp.data.error : 'Unknown error'));
                }
            })
            .fail(function () {
                appendChatMessage('ai', '❌ Connection failed. Is LM Studio running?');
            })
            .always(function () {
                $chatSend.prop('disabled', false);
                setSpinner($chatSpinner, false);
            });
    }

    $chatSend.on('click', sendChatMessage);
    $chatInput.on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendChatMessage();
        }
    });

    // Quick test buttons.
    $('.local-ai-quick-test').on('click', function () {
        $chatInput.val($(this).data('prompt'));
        sendChatMessage();
    });

    // ─── Init ───────────────────────────────────────────────────────────

    // Set context window dropdown to saved value.
    (function initContextDropdown() {
        var saved = config.contextWindow || 131072;
        var $select = $('#local-ai-context-window');
        var matched = false;

        $select.find('option').each(function () {
            if (parseInt($(this).val(), 10) === saved) {
                $select.val($(this).val());
                matched = true;
                return false;
            }
        });

        if (!matched && saved > 0) {
            $select.val('custom');
            $('#local-ai-context-custom').val(saved).show();
        }
    })();

    // If a saved base_url exists, auto-discover models in background to populate dropdowns.
    var savedBaseUrl = $('#local-ai-base-url').val().replace(/\/+$/, '');
    if (savedBaseUrl) {
        $.post(config.ajaxurl, {
            action: 'local_ai_discover_models',
            nonce: config.nonce,
            base_url: savedBaseUrl,
            api_key: $('#local-ai-api-key').val()
        }).done(function (resp) {
            if (resp.success && resp.data.models) {
                populateModelDropdowns(resp.data.models);
                showBanner('success', '🟢 Connected — ' + resp.data.models.length + ' model(s) available.');
                enableTestModelButton();
            }
            // Silently fail — the saved model is already in the dropdown from PHP.
        });
    }

})(jQuery);
