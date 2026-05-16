/**
 * SEO Captain — Setup Wizard page scripts
 *
 * All PHP data is passed via wp_localize_script as the global `aiscWizard` object.
 */
(function ($) {
    'use strict';

    var nonce = aiscWizard.nonce;
    var ajaxUrl = aiscWizard.ajaxUrl;
    var publishedIds = aiscWizard.publishedIds;
    var skippedIds = aiscWizard.skippedIds;
    var runsData = aiscWizard.runsData;
    var step2AllDone = !!aiscWizard.step2AllDone;
    var step3AllDone = !!aiscWizard.step3AllDone;
    var hasWooProducts = !!aiscWizard.hasWooProducts;

    // ── Helpers ──────────────────────────────────────────────────

    function unlockStep(num) {
        $('#aisc-step-' + num).removeClass('locked');
        $('#aisc-s' + num + '-badge').removeClass('pending').addClass('active');
        var btnId = num === 2 ? '#aisc-btn-generate' : '#aisc-btn-audit';
        $(btnId).prop('disabled', false);
    }

    function markStepDone(num) {
        $('#aisc-s' + num + '-badge').removeClass('active pending').addClass('done').text('\u2713');
    }

    function esc(str) {
        return $('<span>').text(str).html();
    }

    function formatTime(seconds) {
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        return (m > 0 ? m + 'm ' : '') + s + 's';
    }

    function showError(prefix, msg) {
        $(prefix + '-error').html('<strong>Error:</strong> ' + esc(msg) +
            ' <br><small style="color:#787c82;">Check your AI provider API key and quota in <a href="' + esc(aiscWizard.settingsUrl) + '">Settings</a>. ' +
            'Common causes: invalid API key, rate limit exceeded, provider outage, or network timeout.</small>'
        ).show();
    }

    // ── Timer helper ──────────────────────────────────────────────

    function createTimer(displayEl) {
        var startTime = null;
        var interval = null;
        return {
            start: function () {
                startTime = Date.now();
                var self = this;
                interval = setInterval(function () {
                    self.update();
                }, 1000);
            },
            pause: function () {
                if (interval) clearInterval(interval);
            },
            resume: function () {
                var self = this;
                interval = setInterval(function () {
                    self.update();
                }, 1000);
            },
            stop: function () {
                if (interval) clearInterval(interval);
            },
            update: function () {
                if (!startTime) return;
                var elapsed = Math.floor((Date.now() - startTime) / 1000);
                $(displayEl).text('\u23F1 ' + formatTime(elapsed));
            },
            getText: function () {
                if (!startTime) return '';
                return formatTime(Math.floor((Date.now() - startTime) / 1000));
            }
        };
    }

    // ── Batch processor (reusable for Steps 2 and 3) ──────────────

    /**
     * Dynamically add a run badge to the runs summary under a step.
     */
    function addRunBadge(stepPrefix, runId, name, isDone) {
        var containerId = stepPrefix + '-runs';
        var $container = $('#' + containerId);
        if ($container.length === 0) {
            var titleLabel = (stepPrefix === 'aisc-s2') ? 'Lists (Metadata)' : 'Lists (Audit)';
            $container = $('<div class="aisc-runs-summary" id="' + containerId + '">' +
                '<h4 class="aisc-runs-title"><span class="dashicons dashicons-list-view"></span> ' + esc(titleLabel) + '</h4>' +
                '</div>');
            $('#' + stepPrefix + '-error').after($container);
        }
        $container.find('.aisc-run-badge[data-run-id="' + runId + '"]').remove();
        var statusClass = isDone ? 'is-complete' : 'is-pending';
        var statusLabel = isDone ? 'Done' : 'Pending';
        var checkmark = isDone ? ' <span class="dashicons dashicons-yes-alt"></span>' : '';
        var badge = $('<div class="aisc-run-badge ' + statusClass + '" data-run-id="' + runId + '">' +
            '<strong>' + esc(name) + '</strong> ' +
            '<span>' + statusLabel + '</span>' +
            checkmark +
            ' <button type="button" class="aisc-run-delete" data-run-id="' + runId + '" title="Delete list">&times;</button>' +
            '</div>');
        $container.append(badge);
    }

    function markRunBadgeDone(stepPrefix, runId) {
        var $badge = $('#' + stepPrefix + '-runs .aisc-run-badge[data-run-id="' + runId + '"]');
        if ($badge.length) {
            $badge.removeClass('is-pending is-partial').addClass('is-complete');
            $badge.find('span').first().text('Done');
            if (!$badge.find('.dashicons-yes-alt').length) {
                $badge.find('.aisc-run-delete').before(' <span class="dashicons dashicons-yes-alt"></span> ');
            }
        }
        for (var i = 0; i < runsData.length; i++) {
            if (parseInt(runsData[i].id, 10) === runId) {
                var step = (stepPrefix === 'aisc-s2') ? 'metadata' : 'audit';
                var steps = (runsData[i].completed_steps || '').split(',').filter(Boolean);
                if (steps.indexOf(step) === -1) steps.push(step);
                runsData[i].completed_steps = steps.join(',');
                break;
            }
        }
    }

    var LARGE_SITE_THRESHOLD = parseInt(aiscWizard.maxPages, 10) || 500;

    /**
     * Show a proper modal for large-site operations.
     */
    function confirmLargeOperation(operationName, pageCount, secondsPerPage, stepType, onConfirm) {
        var fullSiteDone = (stepType === 'metadata') ? step2AllDone : step3AllDone;
        var hasExistingRuns = runsData && runsData.length > 0;

        var estMinutes = Math.ceil((pageCount * secondsPerPage) / 60);
        var timeStr = estMinutes > 120 ?
            '~' + (estMinutes / 60).toFixed(1) + ' hours' :
            '~' + estMinutes + ' minutes';

        // Build "Redo" section items.
        var redoHtml = '';
        if (fullSiteDone || hasExistingRuns) {
            redoHtml += '<div class="aisc-modal__section-label">Redo:</div>';
            redoHtml += '<div class="aisc-modal__redo-list">';
            if (fullSiteDone) {
                redoHtml += '<button type="button" class="aisc-modal__redo-item" data-action="redo-full">' +
                    '<span class="dashicons dashicons-admin-site-alt3"></span>' +
                    '<span class="aisc-modal__redo-info">' +
                    '<strong>Full Site</strong>' +
                    '<small>' + publishedIds.length + ' pages &middot; Re-run ' + esc(operationName) + '</small>' +
                    '</span>' +
                    '<span class="aisc-modal__redo-badge is-complete">&#10003; Done</span>' +
                    '</button>';
            }
            if (hasExistingRuns) {
                for (var r = 0; r < runsData.length; r++) {
                    var run = runsData[r];
                    var steps = (run.completed_steps || '').split(',');
                    var stepDone = steps.indexOf(stepType) !== -1;
                    var statusClass = stepDone ? 'is-complete' : 'is-pending';
                    var statusLabel = stepDone ? '&#10003; Done' : 'Pending';
                    redoHtml += '<button type="button" class="aisc-modal__redo-item" data-action="redo-run" data-run-id="' + parseInt(run.id, 10) + '">' + '<span class="dashicons dashicons-list-view"></span>' + '<span class="aisc-modal__redo-info">' + '<strong>' + esc(run.name) + '</strong>' + '<small>' + parseInt(run.page_count, 10) + ' pages &middot; Re-run ' + esc(operationName) + '</small>' + '</span>' + '<span class="aisc-modal__redo-badge ' + statusClass + '">' + statusLabel + '</span>' + '</button>';
                }
            }
            redoHtml += '</div>';
        }

        // Build the modal.
        var $overlay = $('<div class="aisc-modal-overlay"></div>');
        var $modal = $(
            '<div class="aisc-modal">' +
            '<div class="aisc-modal__header">' +
            '<h2>' + esc(operationName) + '</h2>' +
            '<button type="button" class="aisc-modal__close" title="Close">&times;</button>' +
            '</div>' +
            '<div class="aisc-modal__body">' +
            '<div class="aisc-modal__info-banner">' +
            '<span class="dashicons dashicons-info-outline"></span>' +
            '<div>' +
            '<strong>Your site has ' + pageCount.toLocaleString() + ' pages</strong><br>' +
            'Estimated: ~' + pageCount.toLocaleString() + ' API calls &middot; ' + timeStr +
            '</div>' +
            '</div>' +
            '<p class="aisc-modal__prompt">How would you like to proceed?</p>' +
            redoHtml +
            '<div class="aisc-modal__section-label">New:</div>' +
            '<div class="aisc-modal__options">' +
            '<button type="button" class="aisc-modal__option-btn is-primary" data-action="all">' +
            '<span class="dashicons dashicons-admin-site-alt3"></span>' +
            '<span><strong>Process All Pages</strong><br><small>Run ' + esc(operationName) + ' on all ' + pageCount.toLocaleString() + ' pages</small></span>' +
            '</button>' +
            '<button type="button" class="aisc-modal__option-btn is-secondary" data-action="list">' +
            '<span class="dashicons dashicons-list-view"></span>' +
            '<span><strong>Create a List</strong><br><small>Select specific pages and save as a reusable list</small></span>' +
            '</button>' +
            '</div>' +
            '<div class="aisc-modal__list-panel" style="display:none;">' +
            '<div class="aisc-modal__list-header">' +
            '<label>List Name: <input type="text" class="aisc-modal__list-name" placeholder="e.g. Priority Pages, Blog Posts..." maxlength="100" /></label>' +
            '</div>' +
            '<div class="aisc-modal__search-bar">' +
            '<input type="text" class="aisc-modal__search" placeholder="Search pages..." />' +
            '<div class="aisc-modal__filters">' +
            '<button type="button" class="button aisc-modal__filter is-active" data-filter="all">All</button>' +
            '<button type="button" class="button aisc-modal__filter" data-filter="page">Pages</button>' +
            '<button type="button" class="button aisc-modal__filter" data-filter="post">Posts</button>' +
            (hasWooProducts ? '<button type="button" class="button aisc-modal__filter" data-filter="product">Products</button>' : '') +
            '</div>' +
            '<div class="aisc-modal__bulk">' +
            '<button type="button" class="button-link aisc-modal__select-all">Select All</button>' +
            ' | <button type="button" class="button-link aisc-modal__deselect-all">Deselect All</button>' +
            ' <span class="aisc-modal__count">0 selected</span>' +
            '</div>' +
            '</div>' +
            '<div class="aisc-modal__page-list"><div class="aisc-modal__loading">Loading pages...</div></div>' +
            '<div class="aisc-modal__list-footer">' +
            '<button type="button" class="button aisc-modal__back">&larr; Back</button>' +
            '<button type="button" class="button button-primary aisc-modal__create-list" disabled>Create List &amp; Process</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>'
        );

        $('body').append($overlay).append($modal);

        setTimeout(function () {
            $overlay.addClass('is-visible');
            $modal.addClass('is-visible');
        }, 10);

        function closeModal() {
            $overlay.removeClass('is-visible');
            $modal.removeClass('is-visible');
            setTimeout(function () {
                $overlay.remove();
                $modal.remove();
            }, 200);
        }

        $overlay.on('click', closeModal);
        $modal.find('.aisc-modal__close').on('click', closeModal);

        // ── Redo: Full site ──
        $modal.on('click', '[data-action="redo-full"]', function () {
            closeModal();
            onConfirm({
                ids: publishedIds,
                runId: null
            });
        });

        // ── Redo: Existing list ──
        $modal.on('click', '[data-action="redo-run"]', function () {
            var redoRunId = parseInt($(this).data('run-id'), 10);
            for (var i = 0; i < runsData.length; i++) {
                if (parseInt(runsData[i].id, 10) === redoRunId) {
                    var pageIds = runsData[i].page_ids;
                    if (typeof pageIds === 'string') {
                        pageIds = JSON.parse(pageIds);
                    }
                    closeModal();
                    onConfirm({
                        ids: pageIds,
                        runId: redoRunId
                    });
                    return;
                }
            }
        });

        // ── New: Process All ──
        $modal.find('[data-action="all"]').on('click', function () {
            closeModal();
            onConfirm({
                ids: publishedIds,
                runId: null
            });
        });

        // ── New: Create a List ──
        var allPages = [];
        var loadedPages = false;

        $modal.find('[data-action="list"]').on('click', function () {
            $modal.find('.aisc-modal__options').slideUp(200);
            $modal.find('.aisc-modal__redo-list').slideUp(200);
            $modal.find('.aisc-modal__section-label').slideUp(200);
            $modal.find('.aisc-modal__prompt').slideUp(200);
            $modal.find('.aisc-modal__list-panel').slideDown(300);
            $modal.find('.aisc-modal__list-name').focus();

            if (!loadedPages) {
                loadedPages = true;
                $.post(ajaxUrl, {
                    action: 'ai_seo_captain_get_pages_for_selector',
                    nonce: nonce
                }, function (response) {
                    if (response.success) {
                        allPages = response.data.pages;
                        renderPageList(allPages);
                    } else {
                        $modal.find('.aisc-modal__page-list').html('<div class="aisc-modal__loading" style="color:#d63638;">Failed to load pages.</div>');
                    }
                });
            }
        });

        function renderPageList(pages) {
            var $list = $modal.find('.aisc-modal__page-list');
            if (pages.length === 0) {
                $list.html('<div class="aisc-modal__loading">No pages match your filter.</div>');
                return;
            }
            var html = '';
            for (var i = 0; i < pages.length; i++) {
                var p = pages[i];
                var auditBadge = parseInt(p.has_audit, 10) ? '<span class="aisc-modal__badge is-good">Audited</span>' : '<span class="aisc-modal__badge is-pending">Not audited</span>';
                html += '<label class="aisc-modal__page-row" data-type="' + esc(p.post_type) + '">' + '<input type="checkbox" value="' + parseInt(p.id, 10) + '" /> ' + '<span class="aisc-modal__page-title">' + esc(p.title) + '</span>' + '<span class="aisc-modal__page-meta">' + esc(p.post_type) + ' &middot; /' + esc(p.slug) + '</span>' +
                    auditBadge + '</label>';
            }
            $list.html(html);
        }

        // Search filter
        $modal.on('input', '.aisc-modal__search', function () {
            var term = $(this).val().toLowerCase();
            $modal.find('.aisc-modal__page-row').each(function () {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(term) !== -1);
            });
        });

        // Post type filter
        $modal.on('click', '.aisc-modal__filter', function () {
            $modal.find('.aisc-modal__filter').removeClass('is-active');
            $(this).addClass('is-active');
            var filter = $(this).data('filter');
            $modal.find('.aisc-modal__page-row').each(function () {
                if (filter === 'all') {
                    $(this).show();
                } else {
                    $(this).toggle($(this).data('type') === filter);
                }
            });
        });

        // Select / Deselect all
        $modal.on('click', '.aisc-modal__select-all', function () {
            $modal.find('.aisc-modal__page-row:visible input[type="checkbox"]').prop('checked', true).trigger('change');
        });
        $modal.on('click', '.aisc-modal__deselect-all', function () {
            $modal.find('.aisc-modal__page-row input[type="checkbox"]').prop('checked', false).trigger('change');
        });

        // Count selected
        $modal.on('change', 'input[type="checkbox"]', function () {
            var count = $modal.find('.aisc-modal__page-row input:checked').length;
            $modal.find('.aisc-modal__count').text(count + ' selected');
            var hasName = $.trim($modal.find('.aisc-modal__list-name').val()).length > 0;
            $modal.find('.aisc-modal__create-list').prop('disabled', count === 0 || !hasName);
        });

        // List name validation
        $modal.on('input', '.aisc-modal__list-name', function () {
            var count = $modal.find('.aisc-modal__page-row input:checked').length;
            var hasName = $.trim($(this).val()).length > 0;
            $modal.find('.aisc-modal__create-list').prop('disabled', count === 0 || !hasName);
        });

        // Back button
        $modal.on('click', '.aisc-modal__back', function () {
            $modal.find('.aisc-modal__list-panel').slideUp(200);
            $modal.find('.aisc-modal__options').slideDown(300);
            $modal.find('.aisc-modal__redo-list').slideDown(300);
            $modal.find('.aisc-modal__section-label').slideDown(300);
            $modal.find('.aisc-modal__prompt').slideDown(300);
        });

        // Create List & Process
        $modal.on('click', '.aisc-modal__create-list', function () {
            var $btn = $(this);
            var name = $.trim($modal.find('.aisc-modal__list-name').val());
            var selectedIds = [];
            $modal.find('.aisc-modal__page-row input:checked').each(function () {
                selectedIds.push(parseInt($(this).val(), 10));
            });

            if (!name || selectedIds.length === 0) return;

            $btn.prop('disabled', true).text('Creating...');

            $.post(ajaxUrl, {
                action: 'ai_seo_captain_create_run',
                nonce: nonce,
                name: name,
                page_ids: JSON.stringify(selectedIds)
            }, function (response) {
                if (response.success) {
                    var rd = response.data;
                    runsData.push({
                        id: rd.run_id,
                        name: rd.name,
                        page_count: rd.page_count,
                        page_ids: rd.page_ids,
                        completed_steps: ''
                    });
                    addRunBadge('aisc-s2', rd.run_id, rd.name, false);
                    addRunBadge('aisc-s3', rd.run_id, rd.name, false);
                    closeModal();
                    onConfirm({
                        ids: selectedIds,
                        runId: rd.run_id
                    });
                } else {
                    $btn.prop('disabled', false).text('Create List & Process');
                    alert(response.data.message || 'Error creating list.');
                }
            }).fail(function () {
                $btn.prop('disabled', false).text('Create List & Process');
                alert('Network error. Please try again.');
            });
        });
    }

    function BatchProcessor(config) {
        this.ids = config.ids;
        this.ajaxAction = config.ajaxAction;
        this.prefix = config.prefix;
        this.btnStart = config.btnStart;
        this.btnPause = config.btnPause;
        this.btnStop = config.btnStop;
        this.onItem = config.onItem;
        this.onDone = config.onDone;
        this.onError = config.onError;
        this.timer = createTimer(config.timerEl);

        this.current = 0;
        this.stats = {
            processed: 0,
            skipped: 0,
            cached: 0,
            errors: 0
        };
        this.state = 'idle';
        this.consecutiveErrors = 0;
    }

    BatchProcessor.prototype.start = function () {
        var self = this;
        this.state = 'running';
        this.timer.start();

        $(this.prefix + '-progress').show();
        $(this.prefix + '-paused').hide();
        $(this.prefix + '-stopped').hide();
        $(this.prefix + '-error').hide();
        $(this.btnStart).prop('disabled', true).text('Processing...');
        $(this.btnPause).show();
        $(this.btnStop).show();

        $(this.btnPause).off('click').on('click', function () {
            if (self.state === 'running') {
                self.state = 'paused';
                self.timer.pause();
                $(self.btnPause).html('&#9654; Resume');
                $(self.prefix + '-paused-info').text(
                    self.stats.processed + ' processed, ' + self.stats.skipped + ' skipped, ' +
                    self.stats.errors + ' errors so far.'
                );
                $(self.prefix + '-paused').show();
                $(self.prefix + '-status').text('Paused at ' + self.current + ' of ' + self.ids.length);
            } else if (self.state === 'paused') {
                self.state = 'running';
                self.timer.resume();
                $(self.btnPause).html('&#10074;&#10074; Pause');
                $(self.prefix + '-paused').hide();
                self.processNext();
            }
        });

        $(this.btnStop).off('click').on('click', function () {
            self.state = 'stopped';
            self.timer.stop();
            $(self.btnPause).hide();
            $(self.btnStop).hide();
            $(self.prefix + '-stopped-info').text(
                self.current + ' of ' + self.ids.length + ' pages processed. ' +
                self.stats.processed + ' new, ' + self.stats.skipped + ' skipped, ' +
                self.stats.errors + ' errors.'
            );
            $(self.prefix + '-stopped').show();
            $(self.btnStart).prop('disabled', false).text('Continue');
        });

        this.processNext();
    };

    BatchProcessor.prototype.processNext = function () {
        if (this.state === 'paused' || this.state === 'stopped') return;

        if (this.current >= this.ids.length) {
            this.finish();
            return;
        }

        if (this.consecutiveErrors >= 5) {
            this.timer.stop();
            $(this.btnPause).hide();
            $(this.btnStop).hide();
            showError(this.prefix,
                '5 consecutive API errors. Processing stopped. Last ' +
                this.stats.errors + ' pages failed. Please check your API key and provider status.'
            );
            $(this.btnStart).prop('disabled', false).text('Retry');
            this.state = 'stopped';
            return;
        }

        var self = this;
        var postId = this.ids[this.current];
        var pct = Math.round(((this.current + 1) / this.ids.length) * 100);
        $(this.prefix + '-bar').css('width', pct + '%');
        $(this.prefix + '-status').text('Processing ' + (this.current + 1) + ' of ' + this.ids.length + '...');
        $(this.prefix + '-counts').text(
            '\u2713 ' + this.stats.processed + ' \u23ED ' + this.stats.skipped +
            (this.stats.cached > 0 ? ' \uD83D\uDCCB ' + this.stats.cached : '') +
            ' \u2717 ' + this.stats.errors
        );

        $.post(ajaxUrl, {
            action: this.ajaxAction,
            nonce: nonce,
            post_id: postId
        }, function (response) {
            self.consecutiveErrors = 0;
            if (response.success) {
                if (response.data.skipped) {
                    self.stats.skipped++;
                } else if (response.data.cached) {
                    self.stats.cached++;
                } else {
                    self.stats.processed++;
                }
                self.onItem(response, postId);
            } else {
                self.stats.errors++;
                self.consecutiveErrors++;
                var msg = response.data && response.data.message ? response.data.message : 'Unknown error';
                var title = response.data && response.data.title ? response.data.title : 'Post #' + postId;
                self.onError(postId, title, msg);
            }
            self.current++;
            self.processNext();
        }).fail(function (jqXHR, textStatus) {
            self.stats.errors++;
            self.consecutiveErrors++;
            var detail = textStatus === 'timeout' ? 'Request timed out' : 'Network error (' + textStatus + ')';
            self.onError(postId, 'Post #' + postId, detail);
            self.current++;
            self.processNext();
        });
    };

    BatchProcessor.prototype.finish = function () {
        this.state = 'done';
        this.timer.stop();
        $(this.prefix + '-bar').css('width', '100%');
        $(this.btnPause).hide();
        $(this.btnStop).hide();
        this.onDone(this.stats);
    };

    // ── STEP 1: Index ─────────────────────────────────────────────

    if (aiscWizard.hasIndex) {
        markStepDone(1);
        $('#aisc-s1-done').show();
        $('#aisc-s1-result').text(aiscWizard.totalItems + ' pages indexed.');
        unlockStep(2);
        $('#aisc-skip-section').show();
    }
    if (aiscWizard.hasIndex && aiscWizard.hasMetadata) {
        if (step2AllDone) {
            markStepDone(2);
            $('#aisc-s2-done').show();
            $('#aisc-s2-result').text('All ' + aiscWizard.totalPages + ' pages have metadata.');
            $('#aisc-btn-generate').text('Re-Generate All');
        }
        unlockStep(3);
    }
    if (step3AllDone) {
        markStepDone(3);
        $('#aisc-s3-done').show();
        $('#aisc-s3-result').text('All ' + aiscWizard.totalPages + ' pages audited.');
    }

    // ── Delete list handler (delegated) ───────────────────────
    $(document).on('click', '.aisc-run-delete', function (e) {
        e.stopPropagation();
        var btn = $(this);
        var runId = parseInt(btn.data('run-id'), 10);
        if (!confirm('Delete this list? The pages and their SEO data will NOT be affected.')) return;
        btn.prop('disabled', true).text('\u2026');
        $.post(ajaxUrl, {
            action: 'ai_seo_captain_delete_run',
            nonce: nonce,
            run_id: runId
        }, function (response) {
            if (response.success) {
                $('.aisc-run-badge[data-run-id="' + runId + '"]').fadeOut(300, function () {
                    $(this).remove();
                });
                runsData = runsData.filter(function (r) {
                    return parseInt(r.id, 10) !== runId;
                });
            } else {
                btn.prop('disabled', false).text('\u00D7');
            }
        }).fail(function () {
            btn.prop('disabled', false).text('\u00D7');
        });
    });

    $('#aisc-btn-index').on('click', function () {
        var btn = $(this);
        btn.prop('disabled', true).text('Indexing...');
        $('#aisc-s1-progress').show();
        $('#aisc-s1-done').hide();
        $('#aisc-s1-error').hide();
        $('#aisc-s1-bar').css('width', '50%');
        $('#aisc-s1-status').text('Scanning pages...');

        $.post(ajaxUrl, {
            action: aiscWizard.ajaxIndexAction,
            nonce: nonce
        }, function (response) {
            $('#aisc-s1-bar').css('width', '100%');
            if (response.success) {
                publishedIds = response.data.publishedIds || [];
                $('#aisc-s1-status').text('Done.');
                $('#aisc-s1-done').show();
                $('#aisc-s1-result').text(response.data.count + ' pages indexed.');
                markStepDone(1);
                unlockStep(2);
                $('#aisc-skip-section').show();
            } else {
                showError('#aisc-s1', response.data && response.data.message ? response.data.message : 'Unknown error');
                btn.prop('disabled', false).text('Retry Indexing');
            }
        }).fail(function (jqXHR, textStatus) {
            showError('#aisc-s1', 'Network error (' + textStatus + '). Please check your connection and try again.');
            btn.prop('disabled', false).text('Retry Indexing');
        });
    });

    // ── STEP 2: Bulk Generate ────────────────────────────────────

    var s2processor = null;

    $('#aisc-btn-generate').on('click', function () {
        var self = this;
        confirmLargeOperation('SEO Metadata Generation', publishedIds.length, 3, 'metadata', function (result) {
            var idsToUse = result.ids;
            var s2RunId = result.runId;
            $('#aisc-s2-log').show();
            $('#aisc-s2-done').hide();
            $('#aisc-s2-stopped').hide();
            $('#aisc-s2-paused').hide();

            s2processor = new BatchProcessor({
                ids: idsToUse,
                ajaxAction: aiscWizard.ajaxBulkGenerateAction,
                prefix: '#aisc-s2',
                btnStart: '#aisc-btn-generate',
                btnPause: '#aisc-btn-s2-pause',
                btnStop: '#aisc-btn-s2-stop',
                timerEl: '#aisc-s2-elapsed',
                onItem: function (response) {
                    var d = response.data;
                    if (d.skipped) {
                        $('#aisc-s2-log').prepend('<div class="aisc-log-entry" style="color:#50575e;">\u23ED <strong>' + esc(d.title) + '</strong> \u2014 skipped (already has metadata)</div>');
                    } else {
                        $('#aisc-s2-log').prepend('<div class="aisc-log-entry" style="color:#00a32a;">\u2713 <strong>' + esc(d.title) + '</strong> \u2014 ' + esc(d.seo_title) + '</div>');
                    }
                },
                onDone: function (stats) {
                    $('#aisc-s2-status').text('Done in ' + s2processor.timer.getText() + '.');
                    $('#aisc-s2-done').show();
                    $('#aisc-s2-result').text(stats.processed + ' generated, ' + stats.skipped + ' skipped, ' + stats.errors + ' errors.');
                    $('#aisc-btn-generate').prop('disabled', false).text('Re-Generate All');
                    markStepDone(2);
                    unlockStep(3);
                    if (s2RunId) {
                        markRunBadgeDone('aisc-s2', s2RunId);
                        $.post(ajaxUrl, {
                            action: 'ai_seo_captain_mark_run_step',
                            nonce: nonce,
                            run_id: s2RunId,
                            step: 'metadata'
                        });
                    }
                },
                onError: function (postId, title, msg) {
                    $('#aisc-s2-log').prepend('<div class="aisc-log-entry" style="color:#d63638;">\u2717 <strong>' + esc(title) + '</strong> \u2014 ' + esc(msg) + '</div>');
                }
            });

            s2processor.start();
        });
    });

    // ── STEP 3: Page Audits ──────────────────────────────────────

    var s3processor = null;
    var allAudits = aiscWizard.existingAudits || [];

    function scoreColor(score) {
        return score >= 70 ? '#00a32a' : (score >= 40 ? '#dba617' : '#d63638');
    }

    function scoreBadge(score) {
        return '<span style="display:inline-block;min-width:36px;text-align:center;padding:2px 6px;border-radius:3px;color:#fff;font-weight:700;font-size:13px;background:' + scoreColor(score) + ';">' + score + '</span>';
    }

    function renderAuditCard(d) {
        var isSkipped = d.audit_skipped || skippedIds.indexOf(d.post_id) !== -1;
        var cachedTag = d.cached ? ' <span style="font-size:11px;color:#787c82;font-weight:400;">(cached)</span>' : '';
        var skipBadge = isSkipped ? ' <span class="aisc-skip-badge" style="font-size:11px;background:#f0c33c;color:#3c2300;padding:1px 6px;border-radius:3px;font-weight:600;">SKIPPED</span>' : '';
        var skipBtnLabel = isSkipped ? '&#9654; Unskip' : '&#128683; Skip';
        var skipBtnColor = isSkipped ? '#2271b1' : '#b32d2e';
        var html = '<div class="aisc-audit-card" data-score="' + d.score + '" data-title="' + esc(d.title) + '" data-issues="' + (d.issues ? d.issues.length : 0) + '" data-postid="' + d.post_id + '" data-skipped="' + (isSkipped ? '1' : '0') + '"' + (isSkipped ? ' style="opacity:0.6;"' : '') + '>';
        html += '<div class="aisc-audit-header">';
        html += '<div><strong style="font-size:14px;">' + esc(d.title) + '</strong>' + cachedTag + skipBadge;
        html += ' <a href="' + esc(d.permalink) + '" target="_blank" style="font-size:12px;margin-left:6px;">View \u2197</a></div>';
        html += '<div style="display:flex;align-items:center;gap:10px;">';
        html += '<button type="button" class="aisc-skip-toggle button-link" data-postid="' + d.post_id + '" style="font-size:12px;color:' + skipBtnColor + ';cursor:pointer;white-space:nowrap;">' + skipBtnLabel + '</button>';
        html += '<div class="aisc-audit-score" style="color:' + scoreColor(d.score) + ';">' + d.score + '<span style="font-size:13px;font-weight:400;">/100</span></div>';
        html += '</div></div>';
        if (d.summary) html += '<p style="margin:8px 0 4px;color:#50575e;font-size:13px;">' + esc(d.summary) + '</p>';
        html += '<p style="margin:4px 0;font-size:12px;color:#787c82;">';
        if (d.heading_structure) html += 'Headings: ' + esc(d.heading_structure) + ' \u00B7 ';
        html += 'Words: ' + d.word_count + ' \u00B7 Missing alt: ' + d.missing_alt_tags + '</p>';
        if (d.issues && d.issues.length > 0) {
            html += '<details style="margin-top:8px;"><summary style="cursor:pointer;font-weight:600;color:#d63638;font-size:13px;">Issues(' + d.issues.length + ')</summary><ul style="margin:4px 0 0 16px;padding:0;">';
            for (var i = 0; i < d.issues.length; i++) html += '<li style="font-size:13px;margin:2px 0;">' + esc(d.issues[i]) + '</li>';
            html += '</ul></details>';
        }
        if (d.suggestions && d.suggestions.length > 0) {
            html += '<details style="margin-top:6px;"><summary style="cursor:pointer;font-weight:600;color:#2271b1;font-size:13px;">Suggestions(' + d.suggestions.length + ')</summary><ul style="margin:4px 0 0 16px;padding:0;">';
            for (var i = 0; i < d.suggestions.length; i++) html += '<li style="font-size:13px;margin:2px 0;">' + esc(d.suggestions[i]) + '</li>';
            html += '</ul></details>';
        }
        html += '</div>';
        return html;
    }

    function addOrUpdateAudit(d) {
        var found = false;
        for (var i = 0; i < allAudits.length; i++) {
            if (allAudits[i].post_id === d.post_id) {
                allAudits[i] = d;
                found = true;
                break;
            }
        }
        if (!found) allAudits.push(d);
    }

    function refreshSummaryTab() {
        if (allAudits.length === 0) {
            $('#aisc-s3-tabs').hide();
            return;
        }
        $('#aisc-s3-tabs').show();

        var sorted = allAudits.slice().sort(function (a, b) {
            return b.score - a.score;
        });
        var totalIssues = 0;
        var good = 0,
            warning = 0,
            critical = 0,
            totalScore = 0;
        for (var i = 0; i < sorted.length; i++) {
            totalScore += sorted[i].score;
            totalIssues += (sorted[i].issues ? sorted[i].issues.length : 0);
            if (sorted[i].score >= 70) good++;
            else if (sorted[i].score >= 40) warning++;
            else critical++;
        }
        var avg = Math.round(totalScore / sorted.length);

        $('#aisc-tab-summary-count').text('(' + sorted.length + ' pages)');
        $('#aisc-tab-details-count').text('(' + sorted.length + ' pages)');

        $('#aisc-score-summary').html(
            '<div style="background:#f0f6fc;border:1px solid #72aee6;border-radius:6px;padding:14px 20px;text-align:center;min-width:120px;">' +
            '<div style="font-size:28px;font-weight:700;color:' + scoreColor(avg) + ';">' + avg + '</div>' +
            '<div style="font-size:12px;color:#50575e;">Average Score</div></div>' +
            '<div style="background:#edf8f1;border:1px solid #00a32a;border-radius:6px;padding:14px 20px;text-align:center;min-width:120px;">' +
            '<div style="font-size:28px;font-weight:700;color:#00a32a;">' + good + '</div>' +
            '<div style="font-size:12px;color:#50575e;">Good (70+)</div></div>' +
            '<div style="background:#fef8e7;border:1px solid #dba617;border-radius:6px;padding:14px 20px;text-align:center;min-width:120px;">' +
            '<div style="font-size:28px;font-weight:700;color:#dba617;">' + warning + '</div>' +
            '<div style="font-size:12px;color:#50575e;">Needs Work (40-69)</div></div>' +
            '<div style="background:#fcf0f1;border:1px solid #d63638;border-radius:6px;padding:14px 20px;text-align:center;min-width:120px;">' +
            '<div style="font-size:28px;font-weight:700;color:#d63638;">' + critical + '</div>' +
            '<div style="font-size:12px;color:#50575e;">Critical (&lt;40)</div></div>' +
            '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:14px 20px;text-align:center;min-width:120px;">' +
            '<div style="font-size:28px;font-weight:700;color:#50575e;">' + totalIssues + '</div>' +
            '<div style="font-size:12px;color:#50575e;">Total Issues</div></div>'
        );

        // Top 10
        var top10 = sorted.slice(0, 10);
        var top10html = '';
        for (var i = 0; i < top10.length; i++) {
            top10html += '<tr><td>' + (i + 1) + '</td><td><a href="' + esc(top10[i].permalink) + '" target="_blank">' + esc(top10[i].title) + '</a></td>' + '<td style="text-align:center;">' + scoreBadge(top10[i].score) + '</td>' + '<td style="text-align:center;">' + (top10[i].issues ? top10[i].issues.length : 0) + '</td></tr>';
        }
        $('#aisc-top10-table tbody').html(top10html);

        // Bottom 10
        var bottom10 = sorted.slice(-10).reverse();
        var bottom10html = '';
        for (var i = 0; i < bottom10.length; i++) {
            bottom10html += '<tr><td>' + (i + 1) + '</td><td><a href="' + esc(bottom10[i].permalink) + '" target="_blank">' + esc(bottom10[i].title) + '</a></td>' + '<td style="text-align:center;">' + scoreBadge(bottom10[i].score) + '</td>' + '<td style="text-align:center;">' + (bottom10[i].issues ? bottom10[i].issues.length : 0) + '</td></tr>';
        }
        $('#aisc-bottom10-table tbody').html(bottom10html);
    }

    function refreshDetailsTab() {
        var sortOrder = $('#aisc-sort-order').val();
        var filterVal = $('#aisc-score-filter').val();

        var filtered = allAudits.slice();

        if (filterVal === 'critical') filtered = filtered.filter(function (d) {
            return d.score < 40;
        });
        else if (filterVal === 'warning') filtered = filtered.filter(function (d) {
            return d.score >= 40 && d.score < 70;
        });
        else if (filterVal === 'good') filtered = filtered.filter(function (d) {
            return d.score >= 70;
        });
        else if (filterVal === 'skipped') filtered = filtered.filter(function (d) {
            return d.audit_skipped || skippedIds.indexOf(d.post_id) !== -1;
        });
        else if (filterVal === 'not-skipped') filtered = filtered.filter(function (d) {
            return !d.audit_skipped && skippedIds.indexOf(d.post_id) === -1;
        });

        if (sortOrder === 'score-asc') filtered.sort(function (a, b) {
            return a.score - b.score;
        });
        else if (sortOrder === 'score-desc') filtered.sort(function (a, b) {
            return b.score - a.score;
        });
        else if (sortOrder === 'title-asc') filtered.sort(function (a, b) {
            return a.title.localeCompare(b.title);
        });
        else if (sortOrder === 'issues-desc') filtered.sort(function (a, b) {
            return (b.issues ? b.issues.length : 0) - (a.issues ? a.issues.length : 0);
        });

        var html = '';
        if (filtered.length === 0) {
            html = '<p style="color:#787c82;font-style:italic;">No pages match the current filter.</p>';
        } else {
            for (var i = 0; i < filtered.length; i++) {
                html += renderAuditCard(filtered[i]);
            }
        }
        $('#aisc-s3-results').html(html);
    }

    // Sort/filter change handlers
    $('#aisc-sort-order, #aisc-score-filter').on('change', function () {
        refreshDetailsTab();
    });

    // ── Skip toggle (delegated) ───────────────────────────────
    $(document).on('click', '.aisc-skip-toggle', function (e) {
        e.preventDefault();
        var btn = $(this);
        var postId = parseInt(btn.data('postid'), 10);
        btn.prop('disabled', true).text('\u2026');
        $.post(ajaxUrl, {
            action: aiscWizard.ajaxToggleSkipAction,
            nonce: nonce,
            post_id: postId
        }).done(function (res) {
            if (res.success) {
                if (res.data.skipped) {
                    if (skippedIds.indexOf(postId) === -1) skippedIds.push(postId);
                } else {
                    skippedIds = skippedIds.filter(function (id) {
                        return id !== postId;
                    });
                }
                for (var i = 0; i < allAudits.length; i++) {
                    if (allAudits[i].post_id === postId) {
                        allAudits[i].audit_skipped = res.data.skipped;
                        break;
                    }
                }
                refreshDetailsTab();
                refreshSkipTab();
            }
        }).always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Save skip patterns ────────────────────────────────────
    $('#aisc-btn-save-patterns').on('click', function () {
        var btn = $(this);
        var patterns = $('#aisc-skip-patterns').val();
        btn.prop('disabled', true).text('Saving\u2026');
        $('#aisc-patterns-feedback').hide();
        $.post(ajaxUrl, {
            action: aiscWizard.ajaxSaveSkipPatternsAction,
            nonce: nonce,
            patterns: patterns
        }).done(function (res) {
            if (res.success) {
                var msg = 'Saved! ' + res.data.matched_count + ' page(s) match current patterns.';
                $('#aisc-patterns-feedback').text(msg).show();
            }
        }).always(function () {
            btn.prop('disabled', false).text('Save Patterns');
        });
    });

    // ── Refresh Skip tab ────────────────────────────────────────
    function refreshSkipTab() {
        var list = '';
        var skipCount = 0;
        for (var i = 0; i < allAudits.length; i++) {
            if (allAudits[i].audit_skipped || skippedIds.indexOf(allAudits[i].post_id) !== -1) {
                skipCount++;
                list += '<div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid #f0f0f0;">';
                list += '<span>' + esc(allAudits[i].title) + '</span>';
                list += '<button type="button" class="aisc-skip-toggle button-link" data-postid="' + allAudits[i].post_id + '" style="font-size:12px;color:#2271b1;">&#9654; Unskip</button>';
                list += '</div>';
            }
        }
        if (skipCount === 0) list = '<em>No individually skipped pages.</em>';
        $('#aisc-skipped-pages-list').html(list);
        $('#aisc-tab-skip-count').text('(' + skipCount + ' skipped)');
    }
    refreshSkipTab();

    // Pre-load existing audits on page load
    if (allAudits.length > 0) {
        refreshSummaryTab();
        refreshDetailsTab();
    }

    $('#aisc-btn-audit').on('click', function () {
        var btn = $(this);
        var isRerun = btn.text().indexOf('Re-Run') !== -1;

        var idsForCount = publishedIds.filter(function (id) {
            return skippedIds.indexOf(id) === -1;
        });
        if (!isRerun && allAudits.length > 0) {
            var auditedCheck = {};
            for (var c = 0; c < allAudits.length; c++) {
                auditedCheck[allAudits[c].post_id] = true;
            }
            idsForCount = idsForCount.filter(function (id) {
                return !auditedCheck[id];
            });
        }

        confirmLargeOperation('Full SEO Audit', idsForCount.length, 5, 'audit', function (result) {
            var idsFromModal = result.ids;
            var s3RunId = result.runId;
            $('#aisc-s3-done').hide();
            $('#aisc-s3-stopped').hide();
            $('#aisc-s3-paused').hide();

            var idsToProcess;
            if (idsFromModal.length < publishedIds.length) {
                idsToProcess = idsFromModal;
            } else {
                idsToProcess = publishedIds.filter(function (id) {
                    return skippedIds.indexOf(id) === -1;
                });
                if (!isRerun && allAudits.length > 0) {
                    var auditedIds = {};
                    for (var i = 0; i < allAudits.length; i++) {
                        auditedIds[allAudits[i].post_id] = true;
                    }
                    idsToProcess = idsToProcess.filter(function (id) {
                        return !auditedIds[id];
                    });
                }
            }

            if (idsToProcess.length === 0) {
                $('#aisc-s3-done').show();
                $('#aisc-s3-result').text('All ' + publishedIds.length + ' pages already audited. Click "Re-Run Audits" to refresh all scores.');
                btn.prop('disabled', false).text('Re-Run Audits');
                markStepDone(3);
                if (s3RunId) {
                    markRunBadgeDone('aisc-s3', s3RunId);
                    $.post(ajaxUrl, {
                        action: 'ai_seo_captain_mark_run_step',
                        nonce: nonce,
                        run_id: s3RunId,
                        step: 'audit'
                    });
                }
                return;
            }

            s3processor = new BatchProcessor({
                ids: idsToProcess,
                ajaxAction: aiscWizard.ajaxPageAuditAction,
                prefix: '#aisc-s3',
                btnStart: '#aisc-btn-audit',
                btnPause: '#aisc-btn-s3-pause',
                btnStop: '#aisc-btn-s3-stop',
                timerEl: '#aisc-s3-elapsed',
                onItem: function (response) {
                    addOrUpdateAudit(response.data);
                    refreshSummaryTab();
                    refreshDetailsTab();
                },
                onDone: function (stats) {
                    var total = stats.processed + stats.cached;
                    $('#aisc-s3-status').text('Done in ' + s3processor.timer.getText() + '.');
                    $('#aisc-s3-done').show();
                    $('#aisc-s3-result').text(total + ' pages audited' +
                        (stats.cached > 0 ? ' (' + stats.cached + ' from cache)' : '') +
                        ', ' + stats.errors + ' errors. Total: ' + allAudits.length + ' pages.');
                    btn.prop('disabled', false).text('Re-Run Audits');
                    markStepDone(3);
                    refreshSummaryTab();
                    refreshDetailsTab();
                    if (s3RunId) {
                        markRunBadgeDone('aisc-s3', s3RunId);
                        $.post(ajaxUrl, {
                            action: 'ai_seo_captain_mark_run_step',
                            nonce: nonce,
                            run_id: s3RunId,
                            step: 'audit'
                        });
                    }
                },
                onError: function (postId, title, msg) {
                    $('#aisc-s3-results').prepend(
                        '<div style="border:1px solid #d63638;padding:12px;margin-bottom:12px;background:#fcf0f1;border-radius:4px;">' +
                        '<strong>' + esc(title) + '</strong> \u2014 <span style="color:#d63638;">' + esc(msg) + '</span></div>'
                    );
                }
            });

            s3processor.start();
        });
    });

    // ── Data Management: Clear SEO Data ──────────────────────────

    $('.aisc-clear-data-btn').on('click', function () {
        var $btn = $(this);
        var scope = $btn.data('scope');
        var labels = {
            metadata: 'all AI-generated SEO titles and descriptions',
            audits: 'all page audit data',
            all: 'ALL SEO data (metadata, audits, and lists)'
        };
        var warningColors = {
            metadata: '#dba617',
            audits: '#dba617',
            all: '#d63638'
        };

        var $confirmOverlay = $('<div class="aisc-modal-overlay"></div>');
        var $confirmModal = $(
            '<div class="aisc-modal" style="max-width:480px;">' +
            '<div class="aisc-modal__header" style="background:' + warningColors[scope] + ';color:#fff;">' +
            '<h2 style="color:#fff;"><span class="dashicons dashicons-warning" style="margin-right:6px;"></span> Confirm Deletion</h2>' +
            '<button type="button" class="aisc-modal__close" title="Close" style="color:#fff;">&times;</button>' +
            '</div>' +
            '<div class="aisc-modal__body" style="padding:24px;">' +
            '<p style="font-size:14px;margin:0 0 12px;">You are about to permanently delete:</p>' +
            '<p style="font-size:15px;font-weight:700;color:' + warningColors[scope] + ';margin:0 0 16px;">' + labels[scope] + '</p>' +
            '<p style="font-size:13px;color:#787c82;margin:0 0 20px;">This action cannot be undone. You will need to re-run the affected wizard steps to regenerate this data.</p>' +
            '<div style="display:flex;gap:10px;justify-content:flex-end;">' +
            '<button type="button" class="button aisc-confirm-cancel">Cancel</button>' +
            '<button type="button" class="button aisc-confirm-proceed" style="background:' + warningColors[scope] + ';border-color:' + warningColors[scope] + ';color:#fff;">Yes, Delete</button>' +
            '</div>' +
            '</div>' +
            '</div>'
        );

        $('body').append($confirmOverlay).append($confirmModal);
        setTimeout(function () {
            $confirmOverlay.addClass('is-visible');
            $confirmModal.addClass('is-visible');
        }, 10);

        function closeConfirm() {
            $confirmOverlay.removeClass('is-visible');
            $confirmModal.removeClass('is-visible');
            setTimeout(function () {
                $confirmOverlay.remove();
                $confirmModal.remove();
            }, 200);
        }

        $confirmOverlay.on('click', closeConfirm);
        $confirmModal.find('.aisc-modal__close, .aisc-confirm-cancel').on('click', closeConfirm);

        $confirmModal.find('.aisc-confirm-proceed').on('click', function () {
            closeConfirm();
            $btn.prop('disabled', true).text('Clearing...');
            $.post(ajaxUrl, {
                action: 'ai_seo_captain_clear_seo_data',
                nonce: nonce,
                scope: scope
            }, function (response) {
                if (response.success) {
                    $('#aisc-clear-feedback').text('\u2713 ' + response.data.message).show();
                    setTimeout(function () {
                        location.reload();
                    }, 1200);
                } else {
                    $('#aisc-clear-feedback').text('\u2717 ' + (response.data.message || 'Error')).css('color', '#d63638').show();
                    $btn.prop('disabled', false);
                }
            }).fail(function () {
                $('#aisc-clear-feedback').text('\u2717 Network error.').css('color', '#d63638').show();
                $btn.prop('disabled', false);
            });
        });
    });

})(jQuery);
