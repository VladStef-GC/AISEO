/**
 * SEO Captain — Keywords page scripts
 */
(function () {
    'use strict';

    /* ---------- Client-side table pagination ---------- */
    function initPagination(tableId, paginationId, perPage) {
        var table = document.getElementById(tableId);
        var paginationEl = document.getElementById(paginationId);
        if (!table || !paginationEl) return;

        var tbody = table.querySelector('tbody');
        if (!tbody) return;

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

            // Re-stripe visible rows
            var visibleIdx = 0;
            for (var j = start; j < end && j < rows.length; j++) {
                rows[j].classList.remove('alternate');
                if (visibleIdx % 2 === 0) rows[j].classList.add('alternate');
                visibleIdx++;
            }

            renderPaginationLinks(totalPages, total);
        }

        function renderPaginationLinks(totalPages, total) {
            if (totalPages <= 1) {
                paginationEl.innerHTML = '';
                return;
            }

            var html = '';

            // Previous
            if (currentPage > 1) {
                html += '<a class="prev page-numbers" href="#" data-page="' + (currentPage - 1) + '">&laquo; Previous</a> ';
            }

            // Page numbers
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

            // Next
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

        // Re-render page 1 after column sorting
        table.addEventListener('click', function (e) {
            if (e.target.closest('.ai-seo-sort')) {
                currentPage = 1;
                setTimeout(render, 20);
            }
        });

        render();
    }

    initPagination('ai-seo-keywords-table', 'aisc-keywords-pagination', 30);
})();
