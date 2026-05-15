jQuery(function ($) {
    var $provider = $('#ai-seo-provider');
    var $model = $('#ai-seo-model');
    var $modelTierBadge = $('#ai-seo-model-tier-badge');
    var $customEnabled = $('#ai-seo-custom-model-enabled');
    var $customWrap = $('#ai-seo-custom-model-wrap');
    var $customModelId = $('#ai-seo-custom-model-id');
    var $temperature = $('#ai-seo-temperature');
    var $temperatureHint = $('#ai-seo-temperature-hint');
    var $testButton = $('#ai-seo-test-model');
    var $testResult = $('#ai-seo-test-model-result');

    function parseProviderModels() {
        var raw = $model.attr('data-provider-models') || '{}';

        try {
            return JSON.parse(raw);
        } catch (err) {
            return {};
        }
    }

    function renderModelsForProvider(provider, preserveValue) {
        var allModels = parseProviderModels();
        var options = allModels[provider] || {};
        var previousValue = preserveValue ? ($model.val() || '') : '';
        var firstValue = '';

        $model.empty();

        $.each(options, function (modelId, modelLabel) {
            var label = modelId;
            var tier = 'stable';

            if (modelLabel && typeof modelLabel === 'object') {
                label = modelLabel.label || modelId;
                tier = modelLabel.tier || 'stable';
            } else if (typeof modelLabel === 'string') {
                label = modelLabel;
            }

            if (!firstValue) {
                firstValue = modelId;
            }

            var tierSuffix = tier === 'preview' ? ' [Preview]' : ' [Stable]';
            var $option = $('<option></option>')
                .val(modelId)
                .text(label + tierSuffix + ' (' + modelId + ')')
                .attr('data-tier', tier);

            $model.append($option);
        });

        if (previousValue && Object.prototype.hasOwnProperty.call(options, previousValue)) {
            $model.val(previousValue);
        } else if (firstValue) {
            $model.val(firstValue);
        }

        updateTierBadge();
    }

    function updateTierBadge() {
        var $selected = $model.find('option:selected');
        var tier = ($selected.attr('data-tier') || 'stable').toLowerCase();
        var text = tier === 'preview' ? 'Preview model' : 'Stable model';

        if (!$selected.length || $customEnabled.is(':checked')) {
            $modelTierBadge.attr('hidden', true).removeClass('is-preview is-stable').text('');
            return;
        }

        $modelTierBadge
            .attr('hidden', false)
            .removeClass('is-preview is-stable')
            .addClass(tier === 'preview' ? 'is-preview' : 'is-stable')
            .text(text);
    }

    function updateCustomModeVisibility() {
        var enabled = $customEnabled.is(':checked');

        $customWrap.prop('hidden', !enabled);
        $model.prop('disabled', enabled);
        updateTierBadge();
    }

    function getSelectedModelForTest() {
        if ($customEnabled.is(':checked')) {
            return $.trim($customModelId.val() || '');
        }

        return $.trim($model.val() || '');
    }

    function getSelectedTemperatureForTest() {
        var raw = $.trim($temperature.val() || '');

        if (!raw) {
            return '0.3';
        }

        var parsed = Number(raw);

        if (!Number.isFinite(parsed)) {
            return null;
        }

        parsed = Math.max(0, Math.min(2, parsed));

        return parsed.toFixed(1);
    }

    function setTestResult(message, isSuccess) {
        $testResult
            .text(message)
            .css('color', isSuccess ? '#2e7d32' : '#b00020');
    }

    function updateTemperatureHint() {
        var provider = ($provider.val() || '').toLowerCase();
        var modelId = ($customEnabled.is(':checked') ? $customModelId.val() : $model.val()) || '';
        var isOpenAiOSeries = provider === 'openai' && /^o[1-9]/i.test($.trim(modelId));

        $temperatureHint.prop('hidden', !isOpenAiOSeries);
    }

    $provider.on('change', function () {
        renderModelsForProvider($provider.val(), false);
        updateTemperatureHint();
        setTestResult('', false);
    });

    $model.on('change', function () {
        updateTierBadge();
        updateTemperatureHint();
    });
    $customModelId.on('input', updateTemperatureHint);
    $customEnabled.on('change', function () {
        updateCustomModeVisibility();
        updateTemperatureHint();
        setTestResult('', false);
    });

    $testButton.on('click', function () {
        var ajaxUrl = $testButton.data('ajax-url');
        var action = $testButton.data('action');
        var nonce = $testButton.data('nonce');
        var provider = $provider.val();
        var model = getSelectedModelForTest();
        var temperature = getSelectedTemperatureForTest();
        var apiKey = $('#ai-seo-api-key').val();

        if (!provider || !model) {
            setTestResult('Select an AI provider and model first.', false);
            return;
        }

        if (null === temperature) {
            setTestResult('Temperature must be a valid number between 0.0 and 2.0.', false);
            return;
        }

        $testButton.prop('disabled', true).text('Testing...');
        setTestResult('Checking model availability...', true);

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: action,
                nonce: nonce,
                provider: provider,
                model: model,
                temperature: temperature,
                api_key: apiKey
            }
        }).done(function (response) {
            if (response && response.success && response.data && response.data.message) {
                setTestResult(response.data.message, true);
                return;
            }

            var errorMessage = (response && response.data && response.data.message)
                ? response.data.message
                : 'Model test failed.';

            setTestResult(errorMessage, false);
        }).fail(function (xhr) {
            var fallback = 'Model test request failed.';

            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                fallback = xhr.responseJSON.data.message;
            }

            setTestResult(fallback, false);
        }).always(function () {
            $testButton.prop('disabled', false).text('Test selected model');
        });
    });

    renderModelsForProvider($provider.val(), true);
    updateCustomModeVisibility();
    updateTemperatureHint();

    // WooCommerce panel toggle.
    var wcToggle = document.getElementById('ai-seo-wc-enabled');
    var wcPanel = document.getElementById('ai-seo-wc-options');
    if (wcToggle && wcPanel) {
        wcToggle.addEventListener('change', function () {
            wcPanel.style.opacity = this.checked ? '1' : '.5';
            wcPanel.style.pointerEvents = this.checked ? '' : 'none';
        });
    }
});
