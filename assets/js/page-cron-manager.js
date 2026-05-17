/**
 * page-cron-manager.js — Scheduled Tasks page controls.
 *
 * Handles Pause / Resume / Run Now AJAX actions for cron jobs.
 */
(function ($) {
    'use strict';

    if (typeof aiscCronNonce === 'undefined' || typeof aiscAjaxUrl === 'undefined') {
        return;
    }

    var $table = $('#aisc-cron-jobs-table');

    // ---------------------------------------------------------------
    //  Toast notification
    // ---------------------------------------------------------------
    var toastTimer = null;

    function showToast(message, type) {
        var $existing = $('.aisc-cron-toast');
        if ($existing.length) {
            $existing.remove();
        }

        var $toast = $('<div class="aisc-cron-toast aisc-cron-toast--' + (type || 'success') + '">')
            .text(message)
            .appendTo('body');

        // Trigger reflow then show.
        $toast[0].offsetHeight; // eslint-disable-line no-unused-expressions
        $toast.addClass('is-visible');

        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            $toast.removeClass('is-visible');
            setTimeout(function () { $toast.remove(); }, 300);
        }, 4000);
    }

    // ---------------------------------------------------------------
    //  Button click handler
    // ---------------------------------------------------------------
    $table.on('click', '.aisc-cron-btn', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var action = $btn.data('action');
        var hook = $btn.data('hook');

        if (!action || !hook || $btn.prop('disabled')) {
            return;
        }

        // Map action to AJAX action name.
        var ajaxAction = '';
        switch (action) {
            case 'pause':
                ajaxAction = 'ai_seo_captain_cron_pause';
                break;
            case 'resume':
                ajaxAction = 'ai_seo_captain_cron_resume';
                break;
            case 'run_now':
                ajaxAction = 'ai_seo_captain_cron_run_now';
                break;
            default:
                return;
        }

        // Loading state.
        $btn.prop('disabled', true).addClass('is-loading');
        var originalHTML = $btn.html();

        $.ajax({
            url: aiscAjaxUrl,
            method: 'POST',
            data: {
                action: ajaxAction,
                nonce: aiscCronNonce,
                hook: hook
            },
            dataType: 'json'
        })
            .done(function (resp) {
                if (resp && resp.success) {
                    showToast(resp.data.message || 'Done.', 'success');
                    // Reload the page to reflect new state.
                    setTimeout(function () {
                        window.location.reload();
                    }, 800);
                } else {
                    var msg = (resp && resp.data && resp.data.message) || 'Request failed.';
                    showToast(msg, 'error');
                    $btn.prop('disabled', false).removeClass('is-loading').html(originalHTML);
                }
            })
            .fail(function () {
                showToast('Network error. Please try again.', 'error');
                $btn.prop('disabled', false).removeClass('is-loading').html(originalHTML);
            });
    });

})(jQuery);
