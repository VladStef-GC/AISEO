/**
 * Cache admin page — interactions.
 *
 * Handles purge/preload AJAX, status polling, progress bar, and drop-in management.
 */
jQuery(function ($) {
    'use strict';

    var data = window.aiscCacheData || {};
    var nonce = data.nonce || '';
    var ajaxUrl = data.ajaxUrl || ajaxurl;
    var pollTimer = null;

    // ── Banner helper (uses the plugin's notice pattern) ─────────
    function showBanner(message, severity) {
        severity = severity || 'is-success';
        var iconFile = (severity === 'is-success') ? 'seo-captain-side-ok-d.svg' : 'seo-captain-side-d.svg';
        var icon = (typeof AI_SEO_KEEPER_URL !== 'undefined')
            ? AI_SEO_KEEPER_URL + 'assets/img/' + iconFile
            : '';
        var iconHtml = icon ? '<img src="' + icon + '" alt="" class="ai-seo-captain-notice__icon" />' : '';
        var title = severity === 'is-error' ? 'Error' : 'Success';

        var html = '<div class="ai-seo-captain-notice ' + severity + '">'
            + iconHtml
            + '<div class="ai-seo-captain-notice__body">'
            + '<strong class="ai-seo-captain-notice__title">' + title + '</strong>'
            + '<span class="ai-seo-captain-notice__text">' + message + '</span>'
            + '</div>'
            + '<button type="button" class="ai-seo-captain-notice__dismiss" aria-label="Dismiss">&times;</button>'
            + '</div>';

        var $area = $('#aisc-cache-banner-area');
        $area.html(html);

        // Auto-dismiss after 6 seconds.
        setTimeout(function () {
            $area.find('.ai-seo-captain-notice').fadeOut(300, function () { $(this).remove(); });
        }, 6000);
    }

    // Dismiss banner on click.
    $(document).on('click', '#aisc-cache-banner-area .ai-seo-captain-notice__dismiss', function () {
        $(this).closest('.ai-seo-captain-notice').fadeOut(200, function () { $(this).remove(); });
    });

    // ── Inline feedback (next to action buttons) ────────────────
    function showFeedback(message, isError) {
        var $el = $('#aisc-cache-action-feedback');
        $el.text(message)
            .toggleClass('is-error', !!isError)
            .addClass('is-visible');

        setTimeout(function () {
            $el.removeClass('is-visible');
        }, 4000);
    }

    // ── AJAX helper ─────────────────────────────────────────────
    function cacheAjax(action, extraData) {
        var payload = $.extend({ action: action, nonce: nonce }, extraData || {});
        return $.post(ajaxUrl, payload);
    }

    // ── Purge All Cache ─────────────────────────────────────────
    $('#aisc-purge-all-btn').on('click', function () {
        var $btn = $(this);
        if (!confirm('Purge all cached pages? This cannot be undone.')) {
            return;
        }

        $btn.prop('disabled', true);

        cacheAjax('aisc_purge_cache').done(function (res) {
            if (res.success) {
                showBanner(res.data.message, 'is-success');
                refreshStatus();
            } else {
                showBanner(res.data.message || 'Purge failed.', 'is-error');
            }
        }).fail(function () {
            showBanner('Request failed. Please try again.', 'is-error');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // ── Preload Cache ───────────────────────────────────────────
    $('#aisc-preload-btn').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);

        cacheAjax('aisc_preload_cache').done(function (res) {
            if (res.success) {
                showBanner(res.data.message, 'is-success');
                startPreloadPolling();
            } else {
                showBanner(res.data.message || 'Preload failed.', 'is-error');
                $btn.prop('disabled', false);
            }
        }).fail(function () {
            showBanner('Request failed. Please try again.', 'is-error');
            $btn.prop('disabled', false);
        });
    });

    // ── Preload Progress Polling ────────────────────────────────
    function startPreloadPolling() {
        var $bar = $('#aisc-preload-progress');
        $bar.show();

        if (pollTimer) {
            clearInterval(pollTimer);
        }

        pollTimer = setInterval(function () {
            cacheAjax('aisc_cache_status').done(function (res) {
                if (!res.success) return;

                var progress = res.data.preload_progress || {};
                var total = progress.total || 0;
                var done = progress.done || 0;
                var running = progress.running || false;
                var pct = total > 0 ? Math.round((done / total) * 100) : 0;

                $('#aisc-preload-fill').css('width', pct + '%');
                $('#aisc-preload-text').text(done + ' / ' + total);

                if (!running) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                    $('#aisc-preload-btn').prop('disabled', false);

                    setTimeout(function () {
                        $bar.fadeOut();
                    }, 2000);

                    refreshStatus();
                }
            });
        }, 3000);
    }

    // ── Refresh Dashboard Status ────────────────────────────────
    function refreshStatus() {
        cacheAjax('aisc_cache_status').done(function (res) {
            if (!res.success) return;

            var d = res.data;

            // Update cached pages count.
            $('#aisc-cached-pages').text((d.page_cache_files || 0) + ' files');
            $('#aisc-cached-size').text(formatBytes(d.page_cache_size || 0));

            // Update status badge.
            var $indicator = $('#aisc-cache-status-indicator');
            if (d.enabled) {
                $indicator.html('<span class="aisc-badge aisc-badge--active">Active</span>');
            } else {
                $indicator.html('<span class="aisc-badge aisc-badge--inactive">Inactive</span>');
            }

            // Update drop-in checks.
            $('#aisc-ac-check').text(d.advanced_cache ? '✓' : '✗');
            $('#aisc-oc-check').text(d.object_cache_dropin ? '✓' : '✗');
            $('#aisc-wpcache-check').text(d.wp_cache_constant ? '✓ Enabled' : '✗ Disabled');

            // Update accordion badges and buttons to stay in sync.
            if (d.advanced_cache) {
                $('#aisc-ac-status').removeClass('aisc-badge--inactive').addClass('aisc-badge--active').text('Installed');
                $('#aisc-install-ac').hide();
                $('#aisc-remove-ac').show();
            } else {
                $('#aisc-ac-status').removeClass('aisc-badge--active').addClass('aisc-badge--inactive').text('Not Installed');
                $('#aisc-remove-ac').hide();
                $('#aisc-install-ac').show();
            }

            if (d.htaccess_installed) {
                $('#aisc-htaccess-status').removeClass('aisc-badge--inactive').addClass('aisc-badge--active').text('Installed');
                $('#aisc-install-htaccess').hide();
                $('#aisc-remove-htaccess').show();
            } else {
                $('#aisc-htaccess-status').removeClass('aisc-badge--active').addClass('aisc-badge--inactive').text('Not Installed');
                $('#aisc-remove-htaccess').hide();
                $('#aisc-install-htaccess').show();
            }

            // Update last purge.
            if (d.last_purge && d.last_purge > 0) {
                var ago = timeSince(d.last_purge * 1000);
                $('#aisc-last-purge').text(ago + ' ago');
            }
        });
    }

    // ── Advanced Cache Drop-in ──────────────────────────────────
    $('#aisc-install-ac').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);

        cacheAjax('aisc_install_dropin', { dropin_type: 'advanced-cache' }).done(function (res) {
            if (res.success) {
                showBanner(res.data.message, 'is-success');
                $('#aisc-ac-status').removeClass('aisc-badge--inactive').addClass('aisc-badge--active').text('Installed');
                $('#aisc-ac-check').text('✓');
                $('#aisc-wpcache-check').text('✓ Enabled');
                $btn.hide();
                $('#aisc-remove-ac').show();
            } else {
                showBanner(res.data.message, 'is-error');
            }
        }).fail(function () {
            showBanner('Request failed. Please try again.', 'is-error');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $('#aisc-remove-ac').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);

        cacheAjax('aisc_remove_dropin', { dropin_type: 'advanced-cache' }).done(function (res) {
            if (res.success) {
                showBanner(res.data.message, 'is-success');
                $('#aisc-ac-status').removeClass('aisc-badge--active').addClass('aisc-badge--inactive').text('Not Installed');
                $('#aisc-ac-check').text('✗');
                $('#aisc-wpcache-check').text('✗ Disabled');
                $btn.hide();
                $('#aisc-install-ac').show();
            } else {
                showBanner(res.data.message, 'is-error');
            }
        }).fail(function () {
            showBanner('Request failed. Please try again.', 'is-error');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // ── .htaccess Management ────────────────────────────────────
    $('#aisc-install-htaccess').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);

        cacheAjax('aisc_install_htaccess').done(function (res) {
            if (res.success) {
                showBanner(res.data.message, 'is-success');
                $('#aisc-htaccess-status').removeClass('aisc-badge--inactive').addClass('aisc-badge--active').text('Installed');
                $btn.hide();
                $('#aisc-remove-htaccess').show();
            } else {
                showBanner(res.data.message, 'is-error');
            }
        }).fail(function () {
            showBanner('Request failed. Please try again.', 'is-error');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $('#aisc-remove-htaccess').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);

        cacheAjax('aisc_remove_htaccess').done(function (res) {
            if (res.success) {
                showBanner(res.data.message, 'is-success');
                $('#aisc-htaccess-status').removeClass('aisc-badge--active').addClass('aisc-badge--inactive').text('Not Installed');
                $btn.hide();
                $('#aisc-install-htaccess').show();
            } else {
                showBanner(res.data.message, 'is-error');
            }
        }).fail(function () {
            showBanner('Request failed. Please try again.', 'is-error');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // ── Restore .htaccess from backup ───────────────────────────
    $('#aisc-restore-htaccess').on('click', function () {
        if (!confirm('Restore .htaccess from the most recent backup? This will overwrite the current file.')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        cacheAjax('aisc_restore_htaccess').done(function (res) {
            if (res.success) {
                showBanner(res.data.message, 'is-success');
                refreshStatus();
            } else {
                showBanner(res.data.message, 'is-error');
            }
        }).fail(function () {
            showBanner('Request failed. Please try again.', 'is-error');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // ── Object Cache Drop-in ────────────────────────────────────
    $('#aisc-install-oc').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);

        cacheAjax('aisc_install_dropin', { dropin_type: 'object-cache' }).done(function (res) {
            if (res.success) {
                showBanner(res.data.message, 'is-success');
                $('#aisc-oc-status').removeClass('aisc-badge--inactive').addClass('aisc-badge--active').text('Installed (ours)');
                $('#aisc-oc-check').text('✓');
                $btn.hide();
                $('#aisc-remove-oc').show();
            } else {
                showBanner(res.data.message, 'is-error');
            }
        }).fail(function () {
            showBanner('Request failed. Please try again.', 'is-error');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $('#aisc-remove-oc').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);

        cacheAjax('aisc_remove_dropin', { dropin_type: 'object-cache' }).done(function (res) {
            if (res.success) {
                showBanner(res.data.message, 'is-success');
                $('#aisc-oc-status').removeClass('aisc-badge--active').addClass('aisc-badge--inactive').text('Not Installed');
                $('#aisc-oc-check').text('✗');
                $btn.hide();
                $('#aisc-install-oc').show();
            } else {
                showBanner(res.data.message, 'is-error');
            }
        }).fail(function () {
            showBanner('Request failed. Please try again.', 'is-error');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // ── Admin Bar Purge Handlers ────────────────────────────────
    $(document).on('click', '.aisc-purge-all-trigger a, #wp-admin-bar-ai-seo-captain-purge-all a', function (e) {
        e.preventDefault();
        if (!confirm('Purge all cache?')) return;

        cacheAjax('aisc_purge_cache').done(function (res) {
            if (res.success) {
                showBanner(res.data.message, 'is-success');
            }
        });
    });

    $(document).on('click', '.aisc-purge-this-trigger a, #wp-admin-bar-ai-seo-captain-purge-this a', function (e) {
        e.preventDefault();
        var url = $(this).closest('[data-url]').data('url') || window.location.href;

        cacheAjax('aisc_purge_this_url', { url: url }).done(function (res) {
            if (res.success) {
                showBanner(res.data.message, 'is-success');
            }
        });
    });

    // ── Utility Functions ───────────────────────────────────────
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function timeSince(timestamp) {
        var seconds = Math.floor((Date.now() - timestamp) / 1000);
        if (seconds < 60) return seconds + ' seconds';
        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + ' minutes';
        var hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + ' hours';
        var days = Math.floor(hours / 24);
        return days + ' days';
    }
});
