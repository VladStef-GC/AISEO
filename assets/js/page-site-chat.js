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
        var $grid = $('#aisc-lists-grid');
        if (!$grid.length) return;

        var html = '';

        // "Full Site" card — shown when all pages were processed via "Process All Pages".
        var s2All = !!conf.step2AllDone;
        var s3All = !!conf.step3AllDone;
        if (s2All || s3All) {
            var fsBoth = s2All && s3All;
            var fsClass = fsBoth ? 'is-complete' : 'is-partial';
            var fsIcon = fsBoth ? '✓' : '◐';
            var fsText;
            if (fsBoth) {
                fsText = 'Ready — Both steps complete';
            } else {
                var fsMissing = [];
                if (!s2All) fsMissing.push('Metadata');
                if (!s3All) fsMissing.push('Audit');
                fsText = 'Incomplete — Missing: ' + fsMissing.join(', ');
            }
            var fsAnalysis = conf.fullSiteAnalysisType || '';
            var fsAnalysisBadge = '';
            if (fsAnalysis) {
                var fsBadgeColor = fsAnalysis === 'deep' ? '#2271b1' : '#787c82';
                var fsBadgeLabel = fsAnalysis === 'deep' ? 'Deep Analysis' : 'Standard Analysis';
                fsAnalysisBadge = '<span class="aisc-list-card__analysis" style="display:inline-block;font-size:11px;background:' + fsBadgeColor + ';color:#fff;padding:1px 6px;border-radius:3px;margin-left:6px;">' + fsBadgeLabel + '</span>';
            }
            html += '<div class="aisc-list-card ' + fsClass + ' aisc-list-card--full-site" data-run-id="0">' +
                '<div class="aisc-list-card__header">' +
                '<span class="aisc-list-card__status">' + fsIcon + '</span>' +
                '<strong class="aisc-list-card__name">Full Site</strong>' + fsAnalysisBadge +
                '</div>' +
                '<div class="aisc-list-card__stats">' +
                '<span>' + pageCount + ' pages</span>' +
                '<span class="aisc-list-card__sep">&middot;</span>' +
                '<span class="' + (s2All ? 'aisc-stat-done' : 'aisc-stat-missing') + '">Metadata: ' + (s2All ? 'Done ✓' : 'Pending') + '</span>' +
                '<span class="aisc-list-card__sep">&middot;</span>' +
                '<span class="' + (s3All ? 'aisc-stat-done' : 'aisc-stat-missing') + '">Audit: ' + (s3All ? 'Done ✓' : 'Pending') + '</span>' +
                '</div>' +
                '<div class="aisc-list-card__status-text">' + fsText + '</div>' +
                '</div>';
        }

        if (runs.length === 0 && !html) return;

        for (var i = 0; i < runs.length; i++) {
            var r = runs[i];
            var pc = parseInt(r.page_count, 10) || 0;
            var steps = (r.completed_steps || '').split(',');
            var metaDone = steps.indexOf('metadata') !== -1;
            // Audit step may be stored as 'audit', 'audit:deep', or 'audit:standard'.
            var auditDone = false;
            var analysisType = r.analysis_type || '';
            for (var j = 0; j < steps.length; j++) {
                if (steps[j].split(':')[0] === 'audit') {
                    auditDone = true;
                    if (!analysisType && steps[j].indexOf(':') !== -1) {
                        analysisType = steps[j].split(':')[1];
                    }
                    break;
                }
            }
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

            // Analysis type badge.
            var analysisBadge = '';
            if (analysisType) {
                var badgeColor = analysisType === 'deep' ? '#2271b1' : '#787c82';
                var badgeLabel = analysisType === 'deep' ? 'Deep Analysis' : 'Standard Analysis';
                analysisBadge = '<span class="aisc-list-card__analysis" style="display:inline-block;font-size:11px;background:' + badgeColor + ';color:#fff;padding:1px 6px;border-radius:3px;margin-left:6px;">' + badgeLabel + '</span>';
            }

            html += '<div class="aisc-list-card ' + statusClass + '" data-run-id="' + parseInt(r.id, 10) + '">' +
                '<div class="aisc-list-card__header">' +
                '<span class="aisc-list-card__status">' + statusIcon + '</span>' +
                '<strong class="aisc-list-card__name">' + $('<span>').text(r.name).html() + '</strong>' + analysisBadge +
                '</div>' +
                '<div class="aisc-list-card__stats">' +
                '<span>' + pc + ' pages</span>' +
                '<span class="aisc-list-card__sep">&middot;</span>' +
                '<span class="' + (metaDone ? 'aisc-stat-done' : 'aisc-stat-missing') + '">Metadata: ' + (metaDone ? 'Done ✓' : 'Pending') + '</span>' +
                '<span class="aisc-list-card__sep">&middot;</span>' +
                '<span class="' + (auditDone ? 'aisc-stat-done' : 'aisc-stat-missing') + '">Audit: ' + (auditDone ? 'Done ✓' : 'Pending') + '</span>' +
                '</div>' +
                '<div class="aisc-list-card__status-text">' + statusText + '</div>' +
                '<div class="aisc-list-card__date">Created: ' + (r.created_at || '').substring(0, 10) + '</div>' +
                '</div>';
        }
        $grid.html(html);

        // Build map: runId → page IDs for card click → focus selection.
        var runPageMap = {};
        for (var ri = 0; ri < runs.length; ri++) {
            var rr = runs[ri];
            var pids = rr.page_ids;
            if (typeof pids === 'string') { try { pids = JSON.parse(pids); } catch (e) { pids = []; } }
            runPageMap[parseInt(rr.id, 10)] = pids || [];
        }
        // "Full Site" card (run_id=0) → all audited page IDs.
        var allAuditedIds = [];
        var ap = conf.auditedPages || [];
        for (var ai = 0; ai < ap.length; ai++) { allAuditedIds.push(parseInt(ap[ai].id, 10)); }
        runPageMap[0] = allAuditedIds;

        // Card click → select that card (radio) and check its pages in Focus picker.
        $grid.on('click', '.aisc-list-card', function () {
            var $card = $(this);
            var wasActive = $card.hasClass('is-active');
            $grid.find('.aisc-list-card').removeClass('is-active');
            if (wasActive) {
                // Deselect: uncheck all focus pages.
                $('#ai-seo-focus-list input[type="checkbox"]').prop('checked', false).first().trigger('change');
                return;
            }
            $card.addClass('is-active');
            var runId = parseInt($card.data('run-id'), 10);
            var ids = runPageMap[runId] || [];
            selectFocusPages(ids);
        });

        // Auto-select first card on load.
        var $firstCard = $grid.find('.aisc-list-card').first();
        if ($firstCard.length) {
            $firstCard.addClass('is-active');
            // Defer focus page selection until picker is rendered (below).
            window._aisc_autoSelectRunId = parseInt($firstCard.data('run-id'), 10);
        }
    })();

    // Helper: check specific page IDs in the Focus picker (uncheck rest).
    function selectFocusPages(ids) {
        var idSet = {};
        for (var i = 0; i < ids.length; i++) { idSet[ids[i]] = true; }
        $('#ai-seo-focus-list input[type="checkbox"]').each(function () {
            $(this).prop('checked', !!idSet[parseInt($(this).val(), 10)]);
        });
        var count = $('#ai-seo-focus-list input:checked').length;
        $focusCount.text(count + (count === 1 ? ' page selected' : ' pages selected'));
    }

    // --- Block all interaction when plugin prerequisites are not met ---
    if (!isReady) {
        $send.prop('disabled', true);
        $input.prop('disabled', true).attr('placeholder', 'Please complete site indexing and audit before using AI Captain.');
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
            $list.html('<div class="aisc-focus-selector__empty">No audited pages yet. Run a Full SEO Audit from the Setup Wizard first.</div>');
            return;
        }
        var html = '';
        for (var i = 0; i < auditedPages.length; i++) {
            var p = auditedPages[i];
            var listLabels = (p.lists && p.lists.length > 0) ? p.lists.join(', ') : 'Full Site';
            html += '<label class="aisc-focus-selector__row">' +
                '<input type="checkbox" value="' + parseInt(p.id, 10) + '" />' +
                '<span class="aisc-focus-selector__title">' + $('<span>').text(p.title).html() + '</span>' +
                '<span class="aisc-focus-selector__list-label">' + $('<span>').text(listLabels).html() + '</span>' +
                '</label>';
        }
        $list.html(html);
    })();

    // Auto-select pages from the first card (deferred until picker is rendered).
    if (typeof window._aisc_autoSelectRunId !== 'undefined') {
        var autoRunId = window._aisc_autoSelectRunId;
        delete window._aisc_autoSelectRunId;
        // Build the map again here since runPageMap is scoped inside renderListsGrid.
        var autoIds = [];
        var autoRuns = conf.runs || [];
        if (autoRunId === 0) {
            for (var ai2 = 0; ai2 < auditedPages.length; ai2++) { autoIds.push(parseInt(auditedPages[ai2].id, 10)); }
        } else {
            for (var ari = 0; ari < autoRuns.length; ari++) {
                if (parseInt(autoRuns[ari].id, 10) === autoRunId) {
                    var pids = autoRuns[ari].page_ids;
                    if (typeof pids === 'string') { try { pids = JSON.parse(pids); } catch (e) { pids = []; } }
                    autoIds = pids || [];
                    break;
                }
            }
        }
        if (autoIds.length > 0) {
            selectFocusPages(autoIds);
        }
    }

    // Search filter for focus pages
    $('#ai-seo-focus-search').on('input', function () {
        var term = $(this).val().toLowerCase();
        $('#ai-seo-focus-list .aisc-focus-selector__row').each(function () {
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

        // Check if too many focus pages are selected for the model.
        if (focusIds.length > maxPages && maxPages > 0) {
            setStatus('You selected ' + focusIds.length + ' pages but the model can handle up to ' + formatNumber(maxPages) + '. Please reduce your selection.', true);
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
