/**
 * Site-wide AI Chat — frontend JavaScript.
 */
jQuery(function ($) {
    'use strict';

    var $input = $('#ai-seo-site-chat-input');
    var $send = $('#ai-seo-site-chat-send');
    var $clear = $('#ai-seo-site-chat-clear');
    var $status = $('#ai-seo-site-chat-status');
    var $shell = $('#ai-seo-site-chat-shell');
    var $focusInput = $('#ai-seo-focus-pages-input');
    var $focusCount = $('#ai-seo-focus-count');
    var $capacityInfo = $('#ai-seo-capacity-info');
    var $capacityBadge = $('#ai-seo-capacity-badge');
    var $focusToggle = $('#ai-seo-focus-pages-toggle');
    var busy = false;

    // --- Model capacity display ---
    var conf = window.aiSeoSiteChat || {};
    var pageCount = parseInt(conf.pageCount, 10) || 0;
    var maxPages = parseInt(conf.maxPages, 10) || 0;
    var activeModel = conf.activeModel || '';
    var contextWindow = parseInt(conf.contextWindow, 10) || 0;
    var needsFocus = !!conf.needsFocus;

    function formatNumber(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function updateCapacityDisplay() {
        if (!activeModel) return;

        var ctxLabel = contextWindow >= 1000000
            ? (contextWindow / 1000000).toFixed(1).replace(/\.0$/, '') + 'M'
            : Math.round(contextWindow / 1000) + 'K';

        if (needsFocus) {
            $capacityInfo.html(
                '<strong style="color:#b32d2e;">⚠ Your site has ' + formatNumber(pageCount) +
                ' pages but <code>' + activeModel + '</code> (' + ctxLabel +
                ' tokens) can analyze up to <strong>' + formatNumber(maxPages) +
                '</strong> pages at once.</strong><br>' +
                'Paste specific page URLs below to use Focus Mode, or switch to a model with a larger context window in Settings.'
            ).css({ background: '#fef7f1', border: '1px solid #f0b849' });
            $capacityBadge.html(' <span style="color:#b32d2e;font-weight:normal;font-size:12px;">⚠ required</span>');
            $focusToggle.attr('open', '');
        } else {
            $capacityInfo.html(
                'Model: <code>' + activeModel + '</code> (' + ctxLabel + ' tokens) — can analyze up to <strong>' +
                formatNumber(maxPages) + '</strong> pages. Your site has <strong>' +
                formatNumber(pageCount) + '</strong> pages. ✓ Full site mode available.'
            ).css({ background: '#f0f6fc', border: '1px solid #c3c4c7' });
            $capacityBadge.text('');
        }
    }

    updateCapacityDisplay();

    // --- Focus pages line counter ---
    function getFocusUrls() {
        var text = $.trim($focusInput.val());
        if (!text) return [];
        return text.split(/\n+/).map(function (l) { return $.trim(l); }).filter(function (l) { return l.length > 0; });
    }

    $focusInput.on('input', function () {
        var urls = getFocusUrls();
        var n = urls.length;
        $focusCount.text(n + (n === 1 ? ' page selected' : ' pages selected'));
    });

    function setStatus(text, isError) {
        $status.text(text).css('color', isError ? '#dc3232' : '#646970');
    }

    function scrollToBottom() {
        $shell[0].scrollTop = $shell[0].scrollHeight;
    }

    // --- Send message ---
    $send.on('click', function () {
        if (busy) return;

        var message = $.trim($input.val());
        if (!message) {
            setStatus('Please enter a question.', true);
            return;
        }

        // Check if focus mode is required but no pages are provided.
        var focusUrls = getFocusUrls();
        if (needsFocus && focusUrls.length === 0) {
            setStatus('Your site exceeds the model limit. Add page URLs in Focus Pages below, or switch to a larger model.', true);
            $focusToggle.attr('open', '');
            $focusInput.focus();
            return;
        }

        busy = true;
        $send.prop('disabled', true);
        setStatus('AI is thinking…', false);

        var ajaxData = {
            action: conf.chatAction,
            nonce: conf.nonce,
            message: message
        };

        // Send focus pages if any are entered.
        if (focusUrls.length > 0) {
            ajaxData.focus_pages = focusUrls.join('\n');
        }

        $.ajax({
            url: conf.ajaxUrl,
            method: 'POST',
            data: ajaxData,
            timeout: 120000
        })
            .done(function (response) {
                if (response.success && response.data && response.data.chatHtml) {
                    $shell.html(response.data.chatHtml);
                    $input.val('');
                    setStatus('', false);
                    scrollToBottom();
                } else {
                    setStatus(response.data && response.data.message ? response.data.message : 'Unexpected response.', true);
                }
            })
            .fail(function (xhr) {
                var msg = 'Request failed.';
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.data && resp.data.message) msg = resp.data.message;
                } catch (e) { /* ignore */ }
                setStatus(msg, true);
            })
            .always(function () {
                busy = false;
                $send.prop('disabled', false);
            });
    });

    // --- Send on Ctrl+Enter ---
    $input.on('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            $send.trigger('click');
        }
    });

    // --- Clear chat ---
    $clear.on('click', function () {
        if (busy) return;
        if (!confirm('Clear all site chat history?')) return;

        busy = true;
        setStatus('Clearing…', false);

        $.ajax({
            url: conf.ajaxUrl,
            method: 'POST',
            data: {
                action: conf.clearAction,
                nonce: conf.nonce
            }
        })
            .done(function (response) {
                if (response.success && response.data && response.data.chatHtml !== undefined) {
                    $shell.html(response.data.chatHtml);
                    setStatus('Chat cleared.', false);
                }
            })
            .fail(function () {
                setStatus('Failed to clear chat.', true);
            })
            .always(function () {
                busy = false;
            });
    });
});
