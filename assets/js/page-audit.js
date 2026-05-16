/**
 * SEO Captain — Audit page scripts
 */
(function () {
    'use strict';

    /* ---------- Load More helper ---------- */
    function initLoadMore(btnId, entrySelector) {
        var btn = document.getElementById(btnId);
        if (!btn) return;
        btn.addEventListener('click', function () {
            var entries = document.querySelectorAll(entrySelector);
            var shown = 0,
                newlyShown = 0;
            for (var i = 0; i < entries.length; i++) {
                if (entries[i].style.display === 'none') {
                    if (newlyShown < 5) {
                        entries[i].style.display = '';
                        newlyShown++;
                    }
                } else {
                    shown++;
                }
            }
            if (newlyShown === 0 || shown + newlyShown >= entries.length) {
                btn.style.display = 'none';
            }
        });
    }

    initLoadMore('aisc-indexnow-loadmore', '.aisc-indexnow-entry');
    initLoadMore('aisc-siteaudits-loadmore', '.aisc-siteaudit-entry');

    /* ---------- Export to .txt helper ---------- */
    function initExport(btnId, entrySelector, filename) {
        var btn = document.getElementById(btnId);
        if (!btn) return;
        btn.addEventListener('click', function () {
            var entries = document.querySelectorAll(entrySelector);
            var lines = [];
            for (var i = 0; i < entries.length; i++) {
                lines.push('--- Entry ' + (i + 1) + ' ---');
                lines.push(entries[i].textContent.trim());
                lines.push('');
            }
            var blob = new Blob([lines.join('\n')], {
                type: 'text/plain'
            });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        });
    }

    initExport('aisc-indexnow-export', '.aisc-indexnow-entry', 'indexnow-activity.txt');
    initExport('aisc-siteaudits-export', '.aisc-siteaudit-entry', 'ai-site-audits.txt');
})();
