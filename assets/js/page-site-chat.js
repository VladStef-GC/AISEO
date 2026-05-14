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
    var isReady = !!conf.isReady;

    // --- Render Lists panel (always, even when chat is disabled) ---
    (function renderListsGrid() {
        var runs = conf.runs || [];
        var $grid = $('#aisk-lists-grid');
        if (!$grid.length) return;
        if (runs.length === 0) return;

        var html = '';
        for (var i = 0; i < runs.length; i++) {
            var r = runs[i];
            var pc = parseInt(r.page_count, 10) || 0;
            var steps = (r.completed_steps || '').split(',');
            var metaDone = steps.indexOf('metadata') !== -1;
            var auditDone = steps.indexOf('audit') !== -1;
            var bothDone = metaDone && auditDone;

            // Status reflects BOTH steps completion.
            var statusClass = bothDone ? 'is-complete' : ((metaDone || auditDone) ? 'is-partial' : 'is-pending');
            var statusIcon = bothDone ? '✓' : ((metaDone || auditDone) ? '◐' : '○');

            // Build status text showing what's missing.
            var statusText;
            if (bothDone) {
                statusText = 'Ready — Both steps complete';
            } else {
                var missing = [];
                if (!metaDone) missing.push('Metadata');
                if (!auditDone) missing.push('Audit');
                statusText = 'Incomplete — Missing: ' + missing.join(', ');
            }

            html += '<div class="aisk-list-card ' + statusClass + '" data-run-id="' + parseInt(r.id, 10) + '">' +
                '<div class="aisk-list-card__header">' +
                '<span class="aisk-list-card__status">' + statusIcon + '</span>' +
                '<strong class="aisk-list-card__name">' + $('<span>').text(r.name).html() + '</strong>' +
                '</div>' +
                '<div class="aisk-list-card__stats">' +
                '<span>' + pc + ' pages</span>' +
                '<span class="aisk-list-card__sep">&middot;</span>' +
                '<span class="' + (metaDone ? 'aisk-stat-done' : 'aisk-stat-missing') + '">Metadata: ' + (metaDone ? 'Done ✓' : 'Pending') + '</span>' +
                '<span class="aisk-list-card__sep">&middot;</span>' +
                '<span class="' + (auditDone ? 'aisk-stat-done' : 'aisk-stat-missing') + '">Audit: ' + (auditDone ? 'Done ✓' : 'Pending') + '</span>' +
                '</div>' +
                '<div class="aisk-list-card__status-text">' + statusText + '</div>' +
                '<div class="aisk-list-card__date">Created: ' + (r.created_at || '').substring(0, 10) + '</div>' +
                '</div>';
        }
        $grid.html(html);
    })();

    // --- Block all interaction when plugin prerequisites are not met ---
    if (!isReady) {
        $send.prop('disabled', true);
        $input.prop('disabled', true).attr('placeholder', 'Please complete site indexing and audit before using AI Strategist.');
        $clear.prop('disabled', true);
        return; // Stop all further JS initialization.
    }

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

    // --- Focus Pages: audited page picker (cross-list) ---
    var auditedPages = conf.auditedPages || [];

    (function renderFocusPicker() {
        var $list = $('#ai-seo-focus-list');
        if (!$list.length || auditedPages.length === 0) {
            $list.html('<div class="aisk-focus-selector__empty">No audited pages yet. Run a Full SEO Audit from the Setup Wizard first.</div>');
            return;
        }
        var html = '';
        for (var i = 0; i < auditedPages.length; i++) {
            var p = auditedPages[i];
            var listLabels = (p.lists && p.lists.length > 0) ? p.lists.join(', ') : 'Full Site';
            html += '<label class="aisk-focus-selector__row">' +
                '<input type="checkbox" value="' + parseInt(p.id, 10) + '" />' +
                '<span class="aisk-focus-selector__title">' + $('<span>').text(p.title).html() + '</span>' +
                '<span class="aisk-focus-selector__list-label">' + $('<span>').text(listLabels).html() + '</span>' +
                '</label>';
        }
        $list.html(html);
    })();

    // Search filter for focus pages
    $('#ai-seo-focus-search').on('input', function () {
        var term = $(this).val().toLowerCase();
        $('#ai-seo-focus-list .aisk-focus-selector__row').each(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(term) !== -1);
        });
    });

    // Count selected focus pages
    $('#ai-seo-focus-list').on('change', 'input[type="checkbox"]', function () {
        var count = $('#ai-seo-focus-list input:checked').length;
        $focusCount.text(count + (count === 1 ? ' page selected' : ' pages selected'));
    });

    function getSelectedFocusIds() {
        var ids = [];
        $('#ai-seo-focus-list input:checked').each(function () {
            ids.push(parseInt($(this).val(), 10));
        });
        return ids;
    }

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
        var focusIds = getSelectedFocusIds();
        if (needsFocus && focusIds.length === 0) {
            setStatus('Your site exceeds the model limit. Select pages in Focus Pages below, or switch to a larger model.', true);
            $focusToggle.attr('open', '');
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

        // Send focus page IDs if any are selected.
        if (focusIds.length > 0) {
            ajaxData.focus_page_ids = JSON.stringify(focusIds);
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
