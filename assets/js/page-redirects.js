/**
 * SEO Captain — Redirects page scripts
 */
(function ($) {
    'use strict';

    var nonce = aiscRedirects.nonce;

    $('#ai-seo-redir-add-btn').on('click', function () {
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'ai_seo_captain_add_redirect',
            _nonce: nonce,
            source_url: $('#ai-seo-redir-source').val(),
            target_url: $('#ai-seo-redir-target').val(),
            status_code: $('#ai-seo-redir-status').val()
        }, function (resp) {
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.data || 'Error adding redirect.');
                btn.prop('disabled', false);
            }
        });
    });

    $(document).on('click', '.ai-seo-redir-delete', function () {
        var row = $(this).closest('tr');
        $.post(ajaxurl, {
            action: 'ai_seo_captain_delete_redirect',
            _nonce: nonce,
            id: $(this).data('id')
        }, function (resp) {
            if (resp.success) row.fadeOut(200, function () {
                row.remove();
            });
        });
    });

    $('#ai-seo-clear-404s').on('click', function () {
        if (!confirm('Clear all 404 entries?')) return;
        $.post(ajaxurl, {
            action: 'ai_seo_captain_clear_404s',
            _nonce: nonce
        }, function (resp) {
            if (resp.success) location.reload();
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Chain Flattening — Fix All button
    // ─────────────────────────────────────────────────────────────────────────
    $('#aisc-fix-chains-btn').on('click', function () {
        var btn = $(this);
        if (!confirm('This will flatten all redirect chains so every redirect points directly to the final destination. Continue?')) {
            return;
        }
        btn.prop('disabled', true).text('Fixing…');
        $.post(ajaxurl, {
            action: 'ai_seo_captain_fix_chains',
            _nonce: nonce
        }, function (resp) {
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.data || 'Error fixing chains.');
                btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools" style="margin-top:4px;"></span> Fix All');
            }
        }).fail(function () {
            alert('Network error. Please try again.');
            btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools" style="margin-top:4px;"></span> Fix All');
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Broken Link Scanner
    // ─────────────────────────────────────────────────────────────────────────
    var $scanBtn = $('#ai-seo-broken-scan-btn');
    var $scanStatus = $('#ai-seo-broken-scan-status');
    var $scanProgress = $('#ai-seo-broken-scan-progress');
    var $scanBar = $('#ai-seo-broken-scan-bar');
    var scanPollTimer = null;

    $scanBtn.on('click', function () {
        $scanBtn.prop('disabled', true).text('Scanning…');
        $scanStatus.text('Starting scan…');
        $scanProgress.show();
        $scanBar.css('width', '0%');

        $.post(ajaxurl, {
            action: 'aisc_broken_scan_start',
            _nonce: nonce
        }, function (resp) {
            if (resp.success) {
                if (resp.data.complete) {
                    scanComplete(resp.data);
                } else {
                    updateProgress(resp.data.result);
                    startPolling();
                }
            } else {
                $scanBtn.prop('disabled', false).text('Scan Now');
                $scanStatus.text('Error: ' + (resp.data && resp.data.message || 'Unknown error'));
            }
        }).fail(function () {
            $scanBtn.prop('disabled', false).text('Scan Now');
            $scanStatus.text('Request failed. Please try again.');
        });
    });

    function startPolling() {
        if (scanPollTimer) clearInterval(scanPollTimer);
        scanPollTimer = setInterval(function () {
            $.post(ajaxurl, {
                action: 'aisc_broken_scan_status',
                _nonce: nonce
            }, function (resp) {
                if (resp.success) {
                    if (!resp.data.running) {
                        clearInterval(scanPollTimer);
                        scanPollTimer = null;
                        scanComplete(resp.data);
                    } else {
                        updateProgress(resp.data);
                    }
                }
            });
        }, 3000);
    }

    function updateProgress(data) {
        var pct = data.total_posts > 0 ? Math.round((data.scanned_posts / data.total_posts) * 100) : 0;
        $scanBar.css('width', pct + '%');
        $scanStatus.text('Scanning… ' + data.scanned_posts + '/' + data.total_posts + ' posts processed.');
    }

    function scanComplete(data) {
        $scanBtn.prop('disabled', false).text('Scan Now');
        $scanProgress.hide();
        var r = data.result || data;
        var media = r.broken_media || 0;
        var links = r.broken_links || 0;
        var total = media + links;
        var severity = total > 0 ? 'is-warning' : 'is-success';
        var icon = total > 0 ? 'seo-captain-side-d.svg' : 'seo-captain-side-ok-d.svg';
        var title = total > 0 ? 'Issues Found' : 'All Clear';
        var text = total > 0
            ? 'Scan complete. Found <strong>' + media + '</strong> broken media and <strong>' + links + '</strong> broken links.'
            : 'Scan complete — no broken links or missing media detected.';

        $scanStatus.text('');
        showScanBanner(severity, icon, title, text);
        // Reload after short delay to refresh the results table.
        setTimeout(function () { location.reload(); }, 2500);
    }

    function showScanBanner(severity, icon, title, text) {
        // Remove any previous banner.
        $('#ai-seo-broken-scan-banner').remove();

        var iconUrl = (aiscRedirects.pluginUrl || '') + 'assets/img/' + icon;
        var html = '<div id="ai-seo-broken-scan-banner" class="ai-seo-captain-notice ' + severity + '" style="margin-top:12px;">'
            + '<img src="' + iconUrl + '" alt="" class="ai-seo-captain-notice__icon" />'
            + '<div class="ai-seo-captain-notice__body">'
            + '<strong class="ai-seo-captain-notice__title">' + title + '</strong> '
            + '<span class="ai-seo-captain-notice__text">' + text + '</span>'
            + '</div></div>';

        // Insert after the scan progress bar (below Scan Now button area).
        $('#ai-seo-broken-scan-progress').after(html);
    }

    // If scan is already running on page load, start polling.
    if ($scanBtn.prop('disabled') && $scanBtn.text().indexOf('Scanning') !== -1) {
        startPolling();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Broken Links: Type filter, search, pagination
    // ─────────────────────────────────────────────────────────────────────────
    var BROKEN_PER_PAGE = 30;
    var brokenCurrentPage = 1;
    var brokenActiveFilter = 'all';

    // Type filter buttons.
    $(document).on('click', '.aisc-broken-filter', function () {
        $('.aisc-broken-filter').removeClass('button-primary');
        $(this).addClass('button-primary');
        brokenActiveFilter = $(this).data('filter');
        brokenCurrentPage = 1;
        applyBrokenFilters();
    });

    // Real-time search.
    $('#aisc-broken-search').on('input', function () {
        brokenCurrentPage = 1;
        applyBrokenFilters();
    });

    function applyBrokenFilters() {
        var search = ($('#aisc-broken-search').val() || '').toLowerCase();
        var $rows = $('#ai-seo-broken-table tbody tr');
        var visible = [];

        $rows.each(function () {
            var $row = $(this);
            var matchFilter = (brokenActiveFilter === 'all' || $row.data('type-filter') === brokenActiveFilter);
            var matchSearch = !search || $row.text().toLowerCase().indexOf(search) !== -1;

            if (matchFilter && matchSearch) {
                visible.push($row);
            }
            $row.hide();
        });

        // Pagination.
        var totalPages = Math.ceil(visible.length / BROKEN_PER_PAGE);
        if (brokenCurrentPage > totalPages) brokenCurrentPage = totalPages || 1;
        var start = (brokenCurrentPage - 1) * BROKEN_PER_PAGE;
        var end = start + BROKEN_PER_PAGE;

        for (var i = start; i < end && i < visible.length; i++) {
            visible[i].show();
        }

        renderBrokenPagination(totalPages, visible.length);
    }

    function renderBrokenPagination(totalPages, totalItems) {
        var $wrap = $('#aisc-broken-pagination');
        var $pages = $wrap.find('.tablenav-pages');

        if (totalPages <= 1) {
            $wrap.hide();
            return;
        }

        $wrap.show();
        var html = '<span style="color:#50575e;font-size:13px;">Page ' + brokenCurrentPage + ' of ' + totalPages + ' (' + totalItems + ' items)</span> ';

        if (brokenCurrentPage > 1) {
            html += '<a href="#" class="aisc-broken-page" data-page="' + (brokenCurrentPage - 1) + '">&laquo; Prev</a> ';
        }
        for (var p = 1; p <= totalPages; p++) {
            if (p === brokenCurrentPage) {
                html += '<strong style="padding:4px 8px;">' + p + '</strong> ';
            } else {
                html += '<a href="#" class="aisc-broken-page" data-page="' + p + '" style="padding:4px 8px;">' + p + '</a> ';
            }
        }
        if (brokenCurrentPage < totalPages) {
            html += '<a href="#" class="aisc-broken-page" data-page="' + (brokenCurrentPage + 1) + '">Next &raquo;</a>';
        }
        $pages.html(html);
    }

    $(document).on('click', '.aisc-broken-page', function (e) {
        e.preventDefault();
        brokenCurrentPage = parseInt($(this).data('page'), 10);
        applyBrokenFilters();
        $('#ai-seo-broken-table')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // Initialize pagination on page load.
    if ($('#ai-seo-broken-table').length) {
        applyBrokenFilters();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 404 Monitor: Real-time search
    // ─────────────────────────────────────────────────────────────────────────
    $('#aisc-404-search').on('input', function () {
        var search = ($(this).val() || '').toLowerCase();
        $('#ai-seo-404-table tbody tr').each(function () {
            var $row = $(this);
            var match = !search || $row.text().toLowerCase().indexOf(search) !== -1;
            $row.toggle(match);
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // URL Editor Tab
    // ─────────────────────────────────────────────────────────────────────────
    var $urlTable = $('#aisc-url-editor-table');
    if ($urlTable.length) {
        var urlNonce = $('#aisc-url-editor-nonce').val();
        var $applyBtn = $('#aisc-url-apply-btn');
        var $statusSpan = $('#aisc-url-apply-status');
        var $modal = $('#aisc-url-confirm-modal');
        var slugRegex = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

        // ── Slug validation ──────────────────────────────────────────
        function validateSlug(input, autoFix) {
            var $input = $(input);
            var val = $input.val().trim();
            var $hint = $input.siblings('.aisc-url-validation');
            var original = $input.data('original');
            var $row = $input.closest('tr');
            var pathPrefix = $row.data('path-prefix') || '/';

            if (val === '' || val === original) {
                $hint.hide();
                $input.css('border-color', '');
                return true; // empty = no change
            }

            // Auto-fix only on blur — don't modify while the user is still typing.
            if (autoFix) {
                var fixed = val.toLowerCase().replace(/[\s_]+/g, '-').replace(/[^a-z0-9-]/g, '').replace(/-{2,}/g, '-').replace(/^-|-$/g, '');
                if (fixed !== val) {
                    $input.val(fixed);
                    val = fixed;
                }
            } else {
                // During typing: only lowercase, don't strip trailing hyphens.
                var softFixed = val.toLowerCase().replace(/[\s_]+/g, '-').replace(/[^a-z0-9-]/g, '').replace(/-{2,}/g, '-').replace(/^-/, '');
                if (softFixed !== val) {
                    $input.val(softFixed);
                    val = softFixed;
                }
            }

            if (val === '') {
                $hint.text('Slug cannot be empty.').css('color', '#d63638').show();
                $input.css('border-color', '#d63638');
                return false;
            }

            if (!slugRegex.test(val)) {
                // During typing, allow trailing hyphen (user hasn't finished).
                if (!autoFix && /^[a-z0-9]+(?:-[a-z0-9]*)*$/.test(val)) {
                    $hint.text('…').css('color', '#999').show();
                    $input.css('border-color', '#dba617');
                    return false;
                }
                $hint.text('Only lowercase letters, numbers, and hyphens.').css('color', '#d63638').show();
                $input.css('border-color', '#d63638');
                return false;
            }

            if (val === original) {
                $hint.hide();
                $input.css('border-color', '');
                return true;
            }

            // Check for same-level duplicates: compare full path (path_prefix + slug)
            // against existing slugs AND other pending changes at the same level.
            var fullPath = pathPrefix + val;
            var isDuplicate = false;
            $urlTable.find('tbody tr').each(function () {
                var $otherRow = $(this);
                var $otherInput = $otherRow.find('.aisc-url-new-slug');
                if ($otherInput[0] === input) return;

                var otherPrefix = $otherRow.data('path-prefix') || '/';
                var otherNewVal = $otherInput.val().trim();
                var otherOriginal = $otherInput.data('original');
                // The effective slug for this other row is the new value if set, otherwise the original.
                var effectiveSlug = (otherNewVal !== '' && otherNewVal !== otherOriginal) ? otherNewVal : otherOriginal;
                var otherFullPath = otherPrefix + effectiveSlug;

                if (otherFullPath === fullPath) {
                    isDuplicate = true;
                    return false;
                }
            });

            if (isDuplicate) {
                $hint.text('Duplicate — a page already exists at ' + fullPath).css('color', '#d63638').show();
                $input.css('border-color', '#d63638');
                return false;
            }

            $hint.text('✓ Valid').css('color', '#00a32a').show();
            $input.css('border-color', '#00a32a');
            return true;
        }

        $urlTable.on('input', '.aisc-url-new-slug', function () {
            validateSlug(this, false);
            updateApplyButton();
        });

        // On blur: apply full auto-fix (strip trailing hyphens, etc.).
        $urlTable.on('blur', '.aisc-url-new-slug', function () {
            validateSlug(this, true);
            updateApplyButton();
        });

        // Re-evaluate Apply button when any row checkbox changes.
        $urlTable.on('change', '.aisc-url-row-check', function () {
            updateApplyButton();
        });

        // ── Enable/disable Apply button ──────────────────────────────
        // A row is "actionable" only when: checkbox is checked AND slug is valid & changed.
        function getChangedRows() {
            var rows = [];
            $urlTable.find('tbody tr').each(function () {
                var $row = $(this);
                // Row must have its checkbox checked.
                if (!$row.find('.aisc-url-row-check').is(':checked')) return;

                var $input = $row.find('.aisc-url-new-slug');
                var val = $input.val().trim();
                var original = $input.data('original');
                // Must have a new, different, valid slug.
                if (val === '' || val === original || !slugRegex.test(val)) return;
                // Must pass full validation (same-level dupe check).
                if (!validateSlug($input[0])) return;

                rows.push({
                    post_id: $row.data('post-id'),
                    old_slug: $row.data('original-slug'),
                    new_slug: val,
                    auto_redirect: $row.find('.aisc-url-auto-redirect').is(':checked'),
                    update_refs: $row.find('.aisc-url-update-refs').is(':checked'),
                    path_prefix: $row.data('path-prefix'),
                    title: $row.find('td:eq(1) strong').text()
                });
            });
            return rows;
        }

        function updateApplyButton() {
            var changes = getChangedRows();
            $applyBtn.prop('disabled', changes.length === 0);
            if (changes.length > 0) {
                $statusSpan.css('color', '').text(changes.length + ' change(s) ready');
            } else {
                $statusSpan.text('');
            }
        }

        // ── Search filter ────────────────────────────────────────────
        var urlSearchTerm = '';
        $('#aisc-url-editor-search').on('input', function () {
            urlSearchTerm = ($(this).val() || '').toLowerCase();
            urlPaginationCurrentPage = 1;
            urlPaginationRender();
        });

        // ── Confirmation modal ───────────────────────────────────────
        $applyBtn.on('click', function () {
            var changes = getChangedRows();
            if (changes.length === 0) return;

            $('#aisc-url-confirm-count').text(changes.length);
            var listHtml = '';
            for (var i = 0; i < changes.length; i++) {
                var c = changes[i];
                listHtml += '<div style="margin-bottom:6px;"><strong>' + $('<span>').text(c.title).html() + '</strong>: '
                    + '<code>' + $('<span>').text(c.old_slug).html() + '</code> → <code>' + $('<span>').text(c.new_slug).html() + '</code>'
                    + (c.auto_redirect ? ' <span style="color:#00a32a;">+ redirect</span>' : '')
                    + (c.update_refs ? ' <span style="color:#2271b1;">+ update refs</span>' : '')
                    + '</div>';
            }
            $('#aisc-url-confirm-list').html(listHtml);
            $modal.css('display', 'flex');
        });

        $('#aisc-url-confirm-cancel').on('click', function () {
            $modal.hide();
        });

        $modal.on('click', function (e) {
            if (e.target === this) $modal.hide();
        });

        // ── Apply changes via AJAX ───────────────────────────────────
        $('#aisc-url-confirm-apply').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Applying…');

            var changes = getChangedRows();

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'ai_seo_captain_bulk_url_change',
                    _nonce: urlNonce,
                    changes: JSON.stringify(changes)
                },
                dataType: 'json'
            }).done(function (resp) {
                $modal.hide();
                if (resp.success) {
                    $statusSpan.css('color', '#00a32a').text(resp.data.message);

                    // Update the table with new slugs.
                    if (resp.data.updated) {
                        var totalRefsUpdated = 0;
                        for (var i = 0; i < resp.data.updated.length; i++) {
                            var u = resp.data.updated[i];
                            var $row = $urlTable.find('tr[data-post-id="' + u.post_id + '"]');
                            $row.attr('data-original-slug', u.new_slug);
                            $row.find('td:eq(2) code').text(u.new_slug);
                            var $inp = $row.find('.aisc-url-new-slug');
                            $inp.val('').data('original', u.new_slug).attr('placeholder', u.new_slug).css('border-color', '');
                            $inp.siblings('.aisc-url-validation').hide();
                            $row.find('.aisc-url-row-check').prop('checked', false);
                            $row.find('.aisc-url-update-refs').prop('checked', false);
                            // Update permalink display.
                            var parsed = $row.data('path-prefix') + u.new_slug + '/';
                            $row.find('td:eq(1) a').text(parsed).attr('href', u.permalink);
                            // Tally reference updates.
                            if (u.refs_updated) {
                                totalRefsUpdated += (u.refs_updated.posts || 0) + (u.refs_updated.postmeta || 0) + (u.refs_updated.options || 0);
                            }
                        }
                        if (totalRefsUpdated > 0) {
                            $statusSpan.append(' | ' + totalRefsUpdated + ' reference(s) updated');
                        }
                    }

                    if (resp.data.errors && resp.data.errors.length > 0) {
                        $statusSpan.append(' (' + resp.data.errors.length + ' error(s))');
                    }

                    // ── Search Engine Notification Banner ──────────────
                    var $banner = $('#aisc-url-notify-banner');
                    $banner.remove(); // Clear any previous banner.

                    var inStatus = (resp.data.indexnow && resp.data.indexnow.status) || 'none';
                    var severity = inStatus === 'success' ? 'is-success' : 'is-info';
                    var iconFile = inStatus === 'success' ? 'seo-captain-side-ok-d.svg' : 'seo-captain-side-d.svg';
                    var iconUrl  = (aiscRedirects.pluginUrl || '') + 'assets/img/' + iconFile;

                    var bodyHtml = '';

                    // IndexNow result line.
                    if (resp.data.indexnow) {
                        var inr = resp.data.indexnow;
                        var inIcon = inr.status === 'success' ? '✅' : (inr.status === 'error' ? '❌' : '⚠️');
                        bodyHtml += '<p style="margin:4px 0;">' + inIcon + ' <strong>IndexNow</strong> (Bing, Yandex): ' + $('<span>').text(inr.message).html() + '</p>';
                    } else {
                        bodyHtml += '<p style="margin:4px 0;">⚠️ <strong>IndexNow</strong>: Not available — enable it in <em>Settings → IndexNow</em>.</p>';
                    }

                    // Google Search Console link.
                    bodyHtml += '<p style="margin:6px 0 4px;">🔍 <strong>Google</strong>: Submit your updated URLs in ';
                    bodyHtml += '<a href="https://search.google.com/search-console/inspect" target="_blank" rel="noopener">Google Search Console → URL Inspection</a>.</p>';
                    bodyHtml += '<p style="margin:2px 0 0;font-size:11px;color:#787c82;">💡 You need your site verified in <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console</a>. ';
                    bodyHtml += 'Google does not support IndexNow — re-crawl requests must go through URL Inspection. Without it, Google discovers changes via regular crawl (typically days).</p>';

                    var bannerHtml = '<div id="aisc-url-notify-banner" class="ai-seo-captain-notice ' + severity + '" style="margin-top:12px;">'
                        + '<img src="' + iconUrl + '" alt="" class="ai-seo-captain-notice__icon" />'
                        + '<div class="ai-seo-captain-notice__body">'
                        + '<strong class="ai-seo-captain-notice__title">Search Engine Notifications</strong>'
                        + '<span class="ai-seo-captain-notice__text">' + bodyHtml + '</span>'
                        + '</div></div>';

                    $statusSpan.closest('div').after(bannerHtml);

                    updateApplyButton();
                } else {
                    $statusSpan.css('color', '#d63638').text(resp.data.message || 'Error applying changes.');
                }
                $btn.prop('disabled', false).text('Apply Changes');
            }).fail(function () {
                $modal.hide();
                $statusSpan.css('color', '#d63638').text('Network error. Please try again.');
                $btn.prop('disabled', false).text('Apply Changes');
            });
        });

        // ── Preview references (on-demand per row) ─────────────────
        $urlTable.on('click', '.aisc-url-preview-refs', function () {
            var $btn = $(this);
            var $row = $btn.closest('tr');
            var pathPrefix = $row.data('path-prefix') || '/';
            var slug = $row.data('original-slug');
            var sourcePath = pathPrefix + slug + '/';
            var title = $row.find('td:eq(1) strong').text();

            // Prevent double-clicks.
            if ($btn.hasClass('aisc-loading')) return;
            $btn.addClass('aisc-loading');
            $btn.find('.dashicons').removeClass('dashicons-search').addClass('dashicons-update spin');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'ai_seo_captain_preview_refs',
                    _nonce: urlNonce,
                    source_path: sourcePath
                },
                dataType: 'json'
            }).done(function (resp) {
                $btn.removeClass('aisc-loading');
                $btn.find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-search');

                if (!resp.success) {
                    alert(resp.data.message || 'Error searching references.');
                    return;
                }

                var refs = resp.data.references || [];
                var total = resp.data.total || 0;
                var $refModal = $('#aisc-url-refs-modal');

                $('#aisc-refs-modal-title').text('References to: ' + title);
                $('#aisc-refs-modal-path').text(sourcePath);

                var bodyHtml = '';
                if (total === 0) {
                    bodyHtml = '<p style="color:#50575e;text-align:center;padding:20px 0;">No references found in the database. Safe to change without updating references.</p>';
                } else {
                    bodyHtml = '<p style="margin-bottom:12px;color:#1d2327;"><strong>' + total + '</strong> location(s) reference this URL:</p>';
                    for (var i = 0; i < refs.length; i++) {
                        var ref = refs[i];
                        var tableBadge = ref.table === 'posts' ? '📄' : (ref.table === 'postmeta' ? '🔧' : '⚙️');
                        bodyHtml += '<div style="border:1px solid #e0e0e0;border-radius:4px;padding:10px 12px;margin-bottom:8px;background:#f9f9f9;">';
                        bodyHtml += '<div style="font-weight:500;margin-bottom:4px;">' + tableBadge + ' ' + $('<span>').text(ref.label).html();
                        if (ref.count > 1) bodyHtml += ' <span style="color:#50575e;font-size:11px;">(' + ref.count + ' occurrences)</span>';
                        bodyHtml += '</div>';
                        if (ref.snippets && ref.snippets.length) {
                            for (var s = 0; s < ref.snippets.length; s++) {
                                bodyHtml += '<code style="display:block;font-size:11px;background:#fff;padding:4px 8px;border-radius:3px;margin-top:4px;word-break:break-all;color:#50575e;">'
                                    + $('<span>').text(ref.snippets[s]).html() + '</code>';
                            }
                        }
                        bodyHtml += '</div>';
                    }
                }
                $('#aisc-refs-modal-body').html(bodyHtml);
                $refModal.css('display', 'flex');

            }).fail(function () {
                $btn.removeClass('aisc-loading');
                $btn.find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-search');
                alert('Network error while searching references.');
            });
        });

        // Close refs modal.
        $('#aisc-refs-modal-close').on('click', function () {
            $('#aisc-url-refs-modal').hide();
        });
        $('#aisc-url-refs-modal').on('click', function (e) {
            if (e.target === this) $(this).hide();
        });

        // ── Pagination (30 rows / page) ──────────────────────────────
        var urlPaginationCurrentPage = 1;
        var urlPerPage = 30;
        var $urlPagination = $('#aisc-url-editor-pagination');

        var urlPaginationRender = function () {
            var allRows = $urlTable.find('tbody tr').toArray();

            // Apply search filter first — mark matched rows.
            var matchedRows = [];
            for (var r = 0; r < allRows.length; r++) {
                var isMatch = !urlSearchTerm || allRows[r].textContent.toLowerCase().indexOf(urlSearchTerm) !== -1;
                if (isMatch) {
                    matchedRows.push(allRows[r]);
                }
                allRows[r].style.display = 'none'; // hide all initially
            }

            var total = matchedRows.length;
            var totalPages = Math.ceil(total / urlPerPage);
            if (totalPages < 1) totalPages = 1;
            if (urlPaginationCurrentPage > totalPages) urlPaginationCurrentPage = totalPages;

            var start = (urlPaginationCurrentPage - 1) * urlPerPage;
            var end = start + urlPerPage;

            // Show only the current page of matched rows.
            var vi = 0;
            for (var i = start; i < end && i < matchedRows.length; i++) {
                matchedRows[i].style.display = '';
                matchedRows[i].classList.remove('alternate');
                if (vi % 2 === 0) matchedRows[i].classList.add('alternate');
                vi++;
            }

            if (totalPages <= 1) { $urlPagination.html(''); return; }
            var html = '';
            if (urlPaginationCurrentPage > 1) html += '<a class="prev page-numbers" href="#" data-page="' + (urlPaginationCurrentPage - 1) + '">&laquo; Previous</a> ';
            for (var p = 1; p <= totalPages; p++) {
                if (p === urlPaginationCurrentPage) {
                    html += '<span aria-current="page" class="page-numbers current">' + p + '</span> ';
                } else if (p <= 2 || p > totalPages - 2 || Math.abs(p - urlPaginationCurrentPage) <= 1) {
                    html += '<a class="page-numbers" href="#" data-page="' + p + '">' + p + '</a> ';
                } else if (p === 3 && urlPaginationCurrentPage > 4) {
                    html += '<span class="page-numbers dots">&hellip;</span> ';
                } else if (p === totalPages - 2 && urlPaginationCurrentPage < totalPages - 3) {
                    html += '<span class="page-numbers dots">&hellip;</span> ';
                }
            }
            if (urlPaginationCurrentPage < totalPages) html += '<a class="next page-numbers" href="#" data-page="' + (urlPaginationCurrentPage + 1) + '">Next &raquo;</a>';
            $urlPagination.html(html);
        };

        $urlPagination.on('click', 'a[data-page]', function (e) {
            e.preventDefault();
            urlPaginationCurrentPage = parseInt($(this).data('page'), 10);
            urlPaginationRender();
            $urlTable[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        // Reset pagination on sort.
        $urlTable.on('click', '.ai-seo-sort', function () {
            urlPaginationCurrentPage = 1;
            setTimeout(urlPaginationRender, 20);
        });

        urlPaginationRender();
    }

})(jQuery);
