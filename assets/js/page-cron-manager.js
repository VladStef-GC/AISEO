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

    // ---------------------------------------------------------------
    //  Execution Log — client-side pagination (30 rows / page)
    // ---------------------------------------------------------------
    (function () {
        var table = document.getElementById('aisc-cron-log-table');
        var paginationEl = document.getElementById('aisc-cron-log-pagination');
        if (!table || !paginationEl) return;

        var tbody = table.querySelector('tbody');
        if (!tbody) return;

        var perPage = 30;
        var currentPage = 1;

        function getRows() {
            return Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        }

        function render() {
            var rows = getRows();
            var total = rows.length;
            var totalPages = Math.ceil(total / perPage);
            if (totalPages < 1) totalPages = 1;
            if (currentPage > totalPages) currentPage = totalPages;

            var start = (currentPage - 1) * perPage;
            var end = start + perPage;

            for (var i = 0; i < rows.length; i++) {
                rows[i].style.display = (i >= start && i < end) ? '' : 'none';
            }

            // Re-stripe visible rows.
            var visibleIdx = 0;
            for (var j = start; j < end && j < rows.length; j++) {
                rows[j].classList.remove('alternate');
                if (visibleIdx % 2 === 0) rows[j].classList.add('alternate');
                visibleIdx++;
            }

            renderLinks(totalPages);
        }

        function renderLinks(totalPages) {
            if (totalPages <= 1) { paginationEl.innerHTML = ''; return; }

            var html = '';
            if (currentPage > 1) {
                html += '<a class="prev page-numbers" href="#" data-page="' + (currentPage - 1) + '">&laquo; Previous</a> ';
            }
            for (var p = 1; p <= totalPages; p++) {
                if (p === currentPage) {
                    html += '<span aria-current="page" class="page-numbers current">' + p + '</span> ';
                } else if (p <= 2 || p > totalPages - 2 || Math.abs(p - currentPage) <= 1) {
                    html += '<a class="page-numbers" href="#" data-page="' + p + '">' + p + '</a> ';
                } else if (p === 3 && currentPage > 4) {
                    html += '<span class="page-numbers dots">&hellip;</span> ';
                } else if (p === totalPages - 2 && currentPage < totalPages - 3) {
                    html += '<span class="page-numbers dots">&hellip;</span> ';
                }
            }
            if (currentPage < totalPages) {
                html += '<a class="next page-numbers" href="#" data-page="' + (currentPage + 1) + '">Next &raquo;</a>';
            }
            paginationEl.innerHTML = html;
        }

        paginationEl.addEventListener('click', function (e) {
            var target = e.target.closest('a[data-page]');
            if (!target) return;
            e.preventDefault();
            currentPage = parseInt(target.getAttribute('data-page'), 10);
            render();
            table.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        // Reset to page 1 after column sort.
        table.addEventListener('click', function (e) {
            if (e.target.closest('.ai-seo-sort')) {
                currentPage = 1;
                setTimeout(render, 20);
            }
        });

        render();
    })();

})(jQuery);
