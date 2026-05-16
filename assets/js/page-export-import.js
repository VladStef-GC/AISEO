/**
 * AI SEO Keeper — Export/Import page controller (v2).
 *
 * Multi-step AJAX import flow:
 *  1. Upload & validate
 *  2. Choose mode + sections
 *  3. Matching preview
 *  4. Import progress
 *  5. Report
 */
(function ($) {
    'use strict';

    if (typeof aiskImportExport === 'undefined') {
        return;
    }

    var cfg = aiskImportExport;
    var selectedFile = null;
    var validationData = null;
    var isImporting = false;

    var MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    // Section label map.
    var sectionLabels = {
        settings: 'Plugin Settings',
        seo_meta_posts: 'SEO Metadata (Posts & Pages)',
        seo_meta_terms: 'SEO Metadata (Categories & Tags)',
        audits: 'Page Audits & Content Index',
        redirects: 'Redirect Rules',
        four_oh_four: '404 Log',
        runs: 'Batch Run Lists',
        chat_history: 'AI Chat History'
    };

    // Step names for the indicator.
    var stepNames = ['upload', 'config', 'match', 'progress', 'report'];

    // ----------------------------------------------------------------
    //  Helpers
    // ----------------------------------------------------------------

    function showStep(stepId) {
        $('[id^="aisk-import-step-"]').hide();
        $('#' + stepId).show();

        // Update step indicator.
        var stepName = stepId.replace('aisk-import-step-', '');
        var activeIdx = stepNames.indexOf(stepName);
        $('#aisk-step-indicator .aisk-step').each(function (i) {
            var $s = $(this);
            $s.removeClass('active done');
            if (i < activeIdx) {
                $s.addClass('done');
            } else if (i === activeIdx) {
                $s.addClass('active');
            }
        });
        $('#aisk-step-indicator .aisk-step-line').each(function (i) {
            $(this).toggleClass('done', i < activeIdx);
        });
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    /**
     * Show an inline notice instead of alert().
     */
    function showNotice(msg, type) {
        var $el = $('#aisk-import-notice');
        $el.removeClass('aisk-notice-error aisk-notice-success')
            .addClass(type === 'success' ? 'aisk-notice-success' : 'aisk-notice-error')
            .text(msg)
            .slideDown(200);

        if (type === 'success') {
            setTimeout(function () { $el.slideUp(200); }, 6000);
        }
    }

    function hideNotice() {
        $('#aisk-import-notice').slideUp(150);
    }

    /**
     * Validate that a File is a .json under the size limit.
     */
    function validateFile(file) {
        if (!file) return 'No file selected.';
        if (!/\.json$/i.test(file.name)) return 'Only .json files are accepted.';
        if (file.size > MAX_FILE_SIZE) return 'File is too large (' + formatBytes(file.size) + '). Maximum is ' + formatBytes(MAX_FILE_SIZE) + '.';
        if (file.size === 0) return 'File is empty.';
        return '';
    }

    // ----------------------------------------------------------------
    //  Step 1: Upload & Validate
    // ----------------------------------------------------------------

    // Dropzone click.
    $('#aisk-import-dropzone').on('click', function (e) {
        if (e.target.id !== 'aisk-import-file') {
            $('#aisk-import-file')[0].click();
        }
    });

    // Drag & drop.
    $('#aisk-import-dropzone').on('dragover', function (e) {
        e.preventDefault();
        $(this).addClass('drag-over');
    }).on('dragleave drop', function () {
        $(this).removeClass('drag-over');
    }).on('drop', function (e) {
        e.preventDefault();
        var files = e.originalEvent.dataTransfer.files;
        if (!files.length) return;
        var err = validateFile(files[0]);
        if (err) {
            showNotice(err);
            return;
        }
        hideNotice();
        selectedFile = files[0];
        showFileInfo(selectedFile);
    });

    // File input change.
    $('#aisk-import-file').on('change', function () {
        if (!this.files.length) return;
        var err = validateFile(this.files[0]);
        if (err) {
            showNotice(err);
            return;
        }
        hideNotice();
        selectedFile = this.files[0];
        showFileInfo(selectedFile);
    });

    function showFileInfo(file) {
        $('#aisk-import-filename').text(file.name);
        $('#aisk-import-filesize').text(formatBytes(file.size));
        $('#aisk-import-file-info').show();
        $('#aisk-import-upload-btn').prop('disabled', false);
    }

    // Upload button.
    $('#aisk-import-upload-btn').on('click', function () {
        if (!selectedFile) return;

        var $btn = $(this);
        var $spinner = $('#aisk-import-upload-spinner');
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        hideNotice();

        var formData = new FormData();
        formData.append('action', 'ai_seo_keeper_import_validate');
        formData.append('_nonce', cfg.nonce);
        formData.append('import_file', selectedFile);

        $.ajax({
            url: cfg.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                $spinner.removeClass('is-active');
                if (res.success) {
                    validationData = res.data;
                    buildConfigStep(res.data);
                    showStep('aisk-import-step-config');
                } else {
                    showNotice(res.data && res.data.message ? res.data.message : 'Validation failed.');
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                $spinner.removeClass('is-active');
                showNotice('Upload failed. Please try again.');
                $btn.prop('disabled', false);
            }
        });
    });

    // ----------------------------------------------------------------
    //  Step 2: Config (mode, sections, domain rewrite)
    // ----------------------------------------------------------------

    function buildConfigStep(data) {
        // Summary.
        var html = '<span class="dashicons dashicons-info-outline"></span><div>' +
            '<strong>Source:</strong> ' + escHtml(data.source_domain) +
            ' &nbsp;|&nbsp; <strong>Exported:</strong> ' + escHtml(data.exported_at.substring(0, 10)) +
            ' &nbsp;|&nbsp; <strong>Version:</strong> ' + escHtml(data.plugin_version);
        if (data.counts) {
            var parts = [];
            if (data.counts.posts) parts.push(data.counts.posts + ' posts');
            if (data.counts.terms) parts.push(data.counts.terms + ' terms');
            if (data.counts.redirects) parts.push(data.counts.redirects + ' redirects');
            if (parts.length) html += '<br>' + parts.join(', ');
        }
        html += '</div>';
        $('#aisk-import-summary').html(html);

        // Section checkboxes.
        var $grid = $('#aisk-import-sections-grid').empty();
        var included = data.sections_included || [];
        var allSections = Object.keys(sectionLabels);
        for (var i = 0; i < allSections.length; i++) {
            var key = allSections[i];
            var inFile = included.indexOf(key) !== -1;
            var label = sectionLabels[key] || key;
            if (data.counts && data.counts[key]) {
                label += ' (' + data.counts[key] + ')';
            }
            var cls = inFile ? '' : ' class="aisk-disabled"';
            var chk = '<label' + cls + '>' +
                '<input type="checkbox" name="aisk_import_section" value="' + key + '"' +
                (inFile ? ' checked' : ' disabled') +
                ' /> ' + escHtml(label) +
                (!inFile ? ' <span style="color:var(--aisk-text-muted);">(not in export)</span>' : '') +
                '</label>';
            $grid.append(chk);
        }

        // Domain mismatch.
        if (data.source_domain && data.current_domain && data.source_domain !== data.current_domain) {
            $('#aisk-domain-info').html(
                'Export source: <strong>' + escHtml(data.source_domain) + '</strong><br>' +
                'Your site: <strong>' + escHtml(data.current_domain) + '</strong>'
            );
            $('#aisk-import-domain-rewrite').show();
        } else {
            $('#aisk-import-domain-rewrite').hide();
        }
    }

    // Mode card active state toggle.
    $(document).on('change', 'input[name="aisk_import_mode"]', function () {
        $('.aisk-mode-card').removeClass('active');
        $(this).closest('.aisk-mode-card').addClass('active');
    });

    $('#aisk-import-back-upload').on('click', function () {
        showStep('aisk-import-step-upload');
    });

    // ----------------------------------------------------------------
    //  Step 3: Match
    // ----------------------------------------------------------------

    $('#aisk-import-match-btn').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#aisk-import-match-spinner');
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        hideNotice();

        $.post(cfg.ajax_url, {
            action: 'ai_seo_keeper_import_match',
            _nonce: cfg.nonce
        }, function (res) {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);
            if (res.success) {
                buildMatchStep(res.data);
                showStep('aisk-import-step-match');
            } else {
                showNotice(res.data && res.data.message ? res.data.message : 'Matching failed.');
            }
        }).fail(function () {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);
            showNotice('Request failed. Please try again.');
        });
    });

    function buildMatchStep(data) {
        var posts = data.posts || {};
        var terms = data.terms || {};

        // Summary with badges.
        var summaryParts = [];
        if (posts.strong && posts.strong.length) summaryParts.push('<span class="aisk-match-badge aisk-badge-strong">' + posts.strong.length + ' strong</span>');
        if (posts.fuzzy && posts.fuzzy.length) summaryParts.push('<span class="aisk-match-badge aisk-badge-fuzzy">' + posts.fuzzy.length + ' fuzzy</span>');
        if (posts.orphaned && posts.orphaned.length) summaryParts.push('<span class="aisk-match-badge aisk-badge-missing">' + posts.orphaned.length + ' not found</span>');
        $('#aisk-match-summary').html(summaryParts.join(' '));

        // Posts table.
        var $tbody = $('#aisk-match-table-body').empty();

        (posts.strong || []).forEach(function (m) {
            $tbody.append(
                '<tr>' +
                '<td><input type="checkbox" class="aisk-match-post" data-index="' + m.export_index + '" data-confidence="strong" checked /></td>' +
                '<td>/' + escHtml(m.export_slug) + ' <span style="color:var(--aisk-text-muted);">(' + escHtml(m.export_type) + ')</span></td>' +
                '<td><span class="aisk-match-badge aisk-badge-strong">Match</span></td>' +
                '<td>/' + escHtml(m.local_slug) + ' <span style="color:var(--aisk-text-muted);">(ID ' + m.local_id + ')</span></td>' +
                '</tr>'
            );
        });

        (posts.fuzzy || []).forEach(function (m) {
            $tbody.append(
                '<tr class="aisk-row-fuzzy">' +
                '<td><input type="checkbox" class="aisk-match-post aisk-fuzzy-post" data-index="' + m.export_index + '" data-confidence="fuzzy" /></td>' +
                '<td>/' + escHtml(m.export_slug) + ' <span style="color:var(--aisk-text-muted);">(' + escHtml(m.export_type) + ')</span></td>' +
                '<td><span class="aisk-match-badge aisk-badge-fuzzy">Fuzzy</span></td>' +
                '<td>/' + escHtml(m.local_slug) + ' — "' + escHtml(m.local_title) + '" <span style="color:var(--aisk-text-muted);">(ID ' + m.local_id + ')</span></td>' +
                '</tr>'
            );
        });

        (posts.orphaned || []).forEach(function (m) {
            $tbody.append(
                '<tr class="aisk-row-orphaned">' +
                '<td></td>' +
                '<td>/' + escHtml(m.export_slug) + ' (' + escHtml(m.export_type) + ')</td>' +
                '<td><span class="aisk-match-badge aisk-badge-missing">Missing</span></td>' +
                '<td>—</td>' +
                '</tr>'
            );
        });

        // Terms table.
        if (terms.total && terms.total > 0) {
            $('#aisk-match-terms-wrap').show();
            var termParts = [];
            if (terms.strong && terms.strong.length) termParts.push('<span class="aisk-match-badge aisk-badge-strong">' + terms.strong.length + ' strong</span>');
            if (terms.fuzzy && terms.fuzzy.length) termParts.push('<span class="aisk-match-badge aisk-badge-fuzzy">' + terms.fuzzy.length + ' fuzzy</span>');
            if (terms.orphaned && terms.orphaned.length) termParts.push('<span class="aisk-match-badge aisk-badge-missing">' + terms.orphaned.length + ' not found</span>');
            $('#aisk-term-match-summary').html(termParts.join(' '));

            var $termBody = $('#aisk-term-match-table-body').empty();
            (terms.strong || []).forEach(function (m) {
                $termBody.append(
                    '<tr>' +
                    '<td><input type="checkbox" class="aisk-match-term" data-index="' + m.export_index + '" data-confidence="strong" checked /></td>' +
                    '<td>' + escHtml(m.export_name) + ' <span style="color:var(--aisk-text-muted);">(' + escHtml(m.export_taxonomy) + ')</span></td>' +
                    '<td><span class="aisk-match-badge aisk-badge-strong">Match</span></td>' +
                    '<td>' + escHtml(m.local_name) + ' <span style="color:var(--aisk-text-muted);">(ID ' + m.local_term_id + ')</span></td>' +
                    '</tr>'
                );
            });
            (terms.fuzzy || []).forEach(function (m) {
                $termBody.append(
                    '<tr class="aisk-row-fuzzy">' +
                    '<td><input type="checkbox" class="aisk-match-term aisk-fuzzy-term" data-index="' + m.export_index + '" data-confidence="fuzzy" /></td>' +
                    '<td>' + escHtml(m.export_name) + ' <span style="color:var(--aisk-text-muted);">(' + escHtml(m.export_taxonomy) + ')</span></td>' +
                    '<td><span class="aisk-match-badge aisk-badge-fuzzy">Fuzzy</span></td>' +
                    '<td>' + escHtml(m.local_name) + ' <span style="color:var(--aisk-text-muted);">(ID ' + m.local_term_id + ')</span></td>' +
                    '</tr>'
                );
            });
            (terms.orphaned || []).forEach(function (m) {
                $termBody.append(
                    '<tr class="aisk-row-orphaned">' +
                    '<td></td>' +
                    '<td>' + escHtml(m.export_name) + ' (' + escHtml(m.export_taxonomy) + ')</td>' +
                    '<td><span class="aisk-match-badge aisk-badge-missing">Missing</span></td>' +
                    '<td>—</td>' +
                    '</tr>'
                );
            });
        } else {
            $('#aisk-match-terms-wrap').hide();
        }

        // Force confirm visibility.
        var mode = $('input[name="aisk_import_mode"]:checked').val();
        if (mode === 'force') {
            var matchCount = (posts.strong ? posts.strong.length : 0);
            $('#aisk-force-msg').text(
                'This will REPLACE all existing SEO data for the ' + matchCount +
                ' matched pages. Existing values will be lost. Your other pages will NOT be affected.'
            );
            $('#aisk-force-confirm').show();
        } else {
            $('#aisk-force-confirm').hide();
        }
    }

    $('#aisk-import-back-config').on('click', function () {
        showStep('aisk-import-step-config');
    });

    // ----------------------------------------------------------------
    //  Step 4: Start Import
    // ----------------------------------------------------------------

    $('#aisk-import-start-btn').on('click', function () {
        if (isImporting) return;

        var mode = $('input[name="aisk_import_mode"]:checked').val();

        // Force confirmation.
        if (mode === 'force') {
            if ($('#aisk-force-input').val().trim().toUpperCase() !== 'FORCE') {
                showNotice('Please type FORCE to confirm.');
                return;
            }
        }

        // Collect selected sections.
        var sections = [];
        $('input[name="aisk_import_section"]:checked').each(function () {
            sections.push($(this).val());
        });

        if (!sections.length) {
            showNotice('Please select at least one section to import.');
            return;
        }

        // Collect approved fuzzy matches.
        var approvedFuzzy = [];
        $('.aisk-fuzzy-post:checked').each(function () {
            approvedFuzzy.push(parseInt($(this).data('index'), 10));
        });
        var approvedFuzzyTerms = [];
        $('.aisk-fuzzy-term:checked').each(function () {
            approvedFuzzyTerms.push(parseInt($(this).data('index'), 10));
        });

        var rewriteUrls = $('#aisk-rewrite-urls').is(':checked') ? 1 : 0;

        // Lock UI.
        isImporting = true;
        $(this).prop('disabled', true).addClass('disabled');
        hideNotice();

        // Warn on page leave.
        $(window).on('beforeunload.aiskImport', function () {
            return 'Import in progress. Are you sure you want to leave?';
        });

        showStep('aisk-import-step-progress');

        // Begin chunked processing.
        var totalSteps = sections.length;
        var completedSteps = 0;
        var allLogs = [];

        function processStep(step, offset) {
            var postData = {
                action: 'ai_seo_keeper_import_process',
                _nonce: cfg.nonce,
                mode: mode,
                sections: sections,
                step: step,
                offset: offset || 0,
                rewrite_urls: rewriteUrls,
                approved_fuzzy: approvedFuzzy,
                approved_fuzzy_terms: approvedFuzzyTerms
            };

            $.post(cfg.ajax_url, postData, function (res) {
                if (!res.success) {
                    appendLog('ERROR: ' + (res.data && res.data.message ? res.data.message : 'Unknown error'));
                    finishImport(allLogs);
                    return;
                }

                var d = res.data;

                // Append log entries.
                if (d.log && d.log.length) {
                    d.log.forEach(function (entry) {
                        if (typeof entry === 'string') {
                            appendLog('OK  ' + entry);
                            allLogs.push(entry);
                        } else {
                            var prefix = entry.status === 'imported' ? 'OK  ' : (entry.status === 'skipped' ? 'SKIP' : 'ERR ');
                            appendLog(prefix + ' /' + entry.slug + ' — ' + entry.msg);
                            allLogs.push(entry);
                        }
                    });
                }

                // Update progress.
                if (d.progress !== undefined && d.total) {
                    var pct = Math.round((d.progress / d.total) * 100);
                    updateProgress(pct, (sectionLabels[step] || step) + ': ' + d.progress + ' / ' + d.total);
                }

                if (d.done) {
                    finishImport(allLogs);
                    return;
                }

                // Continue: same step with offset, or next step.
                if (d.next_step === step && d.next_offset !== undefined) {
                    processStep(step, d.next_offset);
                } else if (d.next_step) {
                    completedSteps++;
                    updateProgress(Math.round((completedSteps / totalSteps) * 100), 'Processing: ' + (sectionLabels[d.next_step] || d.next_step));
                    processStep(d.next_step, 0);
                }
            }).fail(function () {
                appendLog('ERROR: Network error. Import may be incomplete.');
                finishImport(allLogs);
            });
        }

        updateProgress(0, 'Starting import...');
        processStep('settings', 0);
    });

    function updateProgress(pct, label) {
        $('#aisk-progress-bar').css('width', pct + '%');
        $('#aisk-progress-pct').text(pct + '%');
        $('#aisk-progress-label').text(label);
    }

    function appendLog(text) {
        var $log = $('#aisk-progress-log');
        $log.append(escHtml(text) + '\n');
        $log.scrollTop($log[0].scrollHeight);
    }

    function finishImport(logs) {
        isImporting = false;
        $(window).off('beforeunload.aiskImport');
        updateProgress(100, 'Complete!');

        // Count stats.
        var imported = 0, skipped = 0, errors = 0;
        logs.forEach(function (entry) {
            if (typeof entry !== 'string') {
                if (entry.status === 'imported') imported++;
                else if (entry.status === 'skipped') skipped++;
                else errors++;
            }
        });

        // Build report with stat cards.
        var reportHtml = '<div class="aisk-report-stats">' +
            '<div class="aisk-report-stat"><div class="aisk-report-stat-value">' + imported + '</div><div class="aisk-report-stat-label">Imported</div></div>' +
            '<div class="aisk-report-stat"><div class="aisk-report-stat-value">' + skipped + '</div><div class="aisk-report-stat-label">Skipped</div></div>' +
            '<div class="aisk-report-stat"><div class="aisk-report-stat-value">' + errors + '</div><div class="aisk-report-stat-label">Errors</div></div>' +
            '</div>';

        var stringEntries = logs.filter(function (e) { return typeof e === 'string'; });
        if (stringEntries.length) {
            reportHtml += '<ul>';
            stringEntries.forEach(function (entry) {
                reportHtml += '<li>' + escHtml(entry) + '</li>';
            });
            reportHtml += '</ul>';
        }

        $('#aisk-import-report').html(reportHtml);

        setTimeout(function () {
            showStep('aisk-import-step-report');
        }, 500);
    }

    // Done button — reload page.
    $('#aisk-import-done-btn').on('click', function () {
        window.location.reload();
    });

    // ----------------------------------------------------------------
    //  Utility
    // ----------------------------------------------------------------

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})(jQuery);
