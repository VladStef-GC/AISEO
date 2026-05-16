/**
 * SEO Captain — Shared admin scripts
 *
 * Accordion toggle and sortable table helpers.
 */
(function ($) {
    'use strict';

    /* ── Accordion toggle ────────────────────────────────────────────── */
    $(document).on('click', '.ai-seo-accordion-header', function () {
        $(this).closest('.ai-seo-accordion-section').toggleClass('is-open');
    });

    /* ── Sortable table headers ──────────────────────────────────────── */
    $(document).on('click', '.ai-seo-sortable .ai-seo-sort', function () {
        var th = $(this),
            table = th.closest('table'),
            col = parseInt(th.data('col'), 10),
            tbody = table.find('tbody'),
            rows = tbody.find('tr').get(),
            asc = th.data('sort-dir') !== 'asc';

        th.data('sort-dir', asc ? 'asc' : 'desc');

        table.find('.ai-seo-sort-icon')
            .removeClass('dashicons-arrow-up dashicons-arrow-down')
            .addClass('dashicons-sort');
        th.find('.ai-seo-sort-icon')
            .removeClass('dashicons-sort')
            .addClass(asc ? 'dashicons-arrow-up' : 'dashicons-arrow-down');

        rows.sort(function (a, b) {
            var aVal = $(a).find('td').eq(col).attr('data-sort-value') || $(a).find('td').eq(col).text().trim().toLowerCase();
            var bVal = $(b).find('td').eq(col).attr('data-sort-value') || $(b).find('td').eq(col).text().trim().toLowerCase();
            if (aVal < bVal) return asc ? -1 : 1;
            if (aVal > bVal) return asc ? 1 : -1;
            return 0;
        });

        $.each(rows, function (i, row) { tbody.append(row); });
    });

})(jQuery);
