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

})(jQuery);
