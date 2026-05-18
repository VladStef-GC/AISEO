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
        var msg = 'Scan complete. Found ' + (data.broken_media || 0) + ' broken media, ' + (data.broken_links || 0) + ' broken links.';
        $scanStatus.text(msg);
        showScanToast(msg);
        // Reload after short delay to refresh the results table.
        setTimeout(function () { location.reload(); }, 1500);
    }

    function showScanToast(message) {
        var $toast = $('<div/>').css({
            position: 'fixed', bottom: '32px', left: '50%', transform: 'translateX(-50%)',
            background: '#1d2327', color: '#fff', padding: '12px 24px', borderRadius: '6px',
            boxShadow: '0 4px 16px rgba(0,0,0,.18)', zIndex: 99999, fontSize: '14px',
            opacity: 0, transition: 'opacity .3s'
        }).text(message).appendTo('body');
        setTimeout(function () { $toast.css('opacity', 1); }, 50);
        setTimeout(function () { $toast.css('opacity', 0); setTimeout(function () { $toast.remove(); }, 400); }, 4000);
    }

    // If scan is already running on page load, start polling.
    if ($scanBtn.prop('disabled') && $scanBtn.text().indexOf('Scanning') !== -1) {
        startPolling();
    }
})(jQuery);
