/**
 * AI SEO Keeper — Bulk Editor page scripts
 */
(function ($) {
    'use strict';

    var nonce = aiSeoBulkEditor.nonce;

    // Enable save button when content changes.
    $('#ai-seo-bulk-table').on('input', '.ai-seo-bulk-title, .ai-seo-bulk-desc', function () {
        var row = $(this).closest('tr');
        var titleChanged = row.find('.ai-seo-bulk-title').val() !== row.find('.ai-seo-bulk-title').data('original');
        var descChanged = row.find('.ai-seo-bulk-desc').val() !== row.find('.ai-seo-bulk-desc').data('original');
        row.find('.ai-seo-bulk-save').prop('disabled', !(titleChanged || descChanged));
    });

    // Save individual row.
    $('#ai-seo-bulk-table').on('click', '.ai-seo-bulk-save', function () {
        var btn = $(this);
        var row = btn.closest('tr');
        var postId = row.data('post-id');
        btn.prop('disabled', true).text('Saving…');

        $.post(ajaxurl, {
            action: 'ai_seo_keeper_bulk_save_seo',
            _nonce: nonce,
            post_id: postId,
            seo_title: row.find('.ai-seo-bulk-title').val(),
            seo_description: row.find('.ai-seo-bulk-desc').val()
        }, function (resp) {
            if (resp.success) {
                btn.text('Saved ✓');
                row.find('.ai-seo-bulk-title').data('original', row.find('.ai-seo-bulk-title').val());
                row.find('.ai-seo-bulk-desc').data('original', row.find('.ai-seo-bulk-desc').val());
                setTimeout(function () { btn.text('Save'); }, 1500);
            } else {
                btn.text('Error').prop('disabled', false);
                alert(resp.data || 'Error saving.');
            }
        });
    });

    // ── Site structure tree ──────────────────────────────────────
    (function () {
        var treeContainer = document.getElementById('aisk-site-tree');
        if (!treeContainer || typeof aiSeoBulkEditor === 'undefined' || !aiSeoBulkEditor.treeData) return;

        var treeData = aiSeoBulkEditor.treeData;
        var byParent = {};
        var byId = {};
        for (var i = 0; i < treeData.length; i++) {
            var node = treeData[i];
            byId[node.id] = node;
            var pid = node.parent_id || 0;
            if (!byParent[pid]) byParent[pid] = [];
            byParent[pid].push(node);
        }

        var typeIcons = { page: '\uD83D\uDCC4', post: '\uD83D\uDCDD', product: '\uD83D\uDED2' };

        function esc(s) {
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function buildTree(parentId, depth) {
            var children = byParent[parentId];
            if (!children || children.length === 0) return '';
            var html = '<ul style="list-style:none;margin:0;padding-left:' + (depth > 0 ? '20' : '0') + 'px;">';
            for (var i = 0; i < children.length; i++) {
                var n = children[i];
                var hasKids = byParent[n.id] && byParent[n.id].length > 0;
                var icon = typeIcons[n.post_type] || '\uD83D\uDCCE';
                var statusBadge = n.status !== 'publish' ? ' <span style="font-size:11px;color:#dba617;font-weight:600;">(' + esc(n.status) + ')</span>' : '';
                var slug = '/' + n.slug;
                html += '<li style="margin:2px 0;">';
                if (hasKids) {
                    html += '<span class="aisk-tree-toggle" style="cursor:pointer;display:inline-block;width:18px;text-align:center;font-weight:700;color:#2271b1;user-select:none;" data-expanded="1">\u2212</span>';
                } else {
                    html += '<span style="display:inline-block;width:18px;text-align:center;color:#c3c4c7;">\u00B7</span>';
                }
                html += icon + ' ';
                html += '<a href="' + esc(n.permalink) + '" target="_blank" style="text-decoration:none;color:#1d2327;">' + esc(n.title) + '</a>';
                html += ' <span style="color:#787c82;font-size:12px;">' + esc(slug) + '</span>';
                html += statusBadge;
                if (hasKids) {
                    html += '<div class="aisk-tree-children">' + buildTree(n.id, depth + 1) + '</div>';
                }
                html += '</li>';
            }
            html += '</ul>';
            return html;
        }

        var pageTree = buildTree(0, 0);
        var flatTypes = {};
        for (var j = 0; j < treeData.length; j++) {
            var nd = treeData[j];
            if (nd.post_type !== 'page' && nd.parent_id === 0) {
                if (!flatTypes[nd.post_type]) flatTypes[nd.post_type] = [];
                flatTypes[nd.post_type].push(nd);
            }
        }

        var flatHtml = '';
        for (var pt in flatTypes) {
            if (!flatTypes.hasOwnProperty(pt)) continue;
            var icon = typeIcons[pt] || '\uD83D\uDCCE';
            var label = pt.charAt(0).toUpperCase() + pt.slice(1) + 's';
            flatHtml += '<div style="margin-top:12px;">';
            flatHtml += '<span class="aisk-tree-toggle" style="cursor:pointer;display:inline-block;width:18px;text-align:center;font-weight:700;color:#2271b1;user-select:none;" data-expanded="1">\u2212</span>';
            flatHtml += '<strong>' + icon + ' ' + esc(label) + ' (' + flatTypes[pt].length + ')</strong>';
            flatHtml += '<div class="aisk-tree-children"><ul style="list-style:none;margin:0;padding-left:20px;">';
            for (var k = 0; k < flatTypes[pt].length; k++) {
                var fn = flatTypes[pt][k];
                var statusB = fn.status !== 'publish' ? ' <span style="font-size:11px;color:#dba617;font-weight:600;">(' + esc(fn.status) + ')</span>' : '';
                flatHtml += '<li style="margin:2px 0;"><span style="display:inline-block;width:18px;text-align:center;color:#c3c4c7;">\u00B7</span>' + icon + ' <a href="' + esc(fn.permalink) + '" target="_blank" style="text-decoration:none;color:#1d2327;">' + esc(fn.title) + '</a> <span style="color:#787c82;font-size:12px;">/' + esc(fn.slug) + '</span>' + statusB + '</li>';
            }
            flatHtml += '</ul></div></div>';
        }

        treeContainer.innerHTML = pageTree + flatHtml;

        treeContainer.addEventListener('click', function (e) {
            var toggle = e.target.closest('.aisk-tree-toggle');
            if (!toggle) return;
            var children = toggle.parentElement.querySelector('.aisk-tree-children');
            if (!children) return;
            var expanded = toggle.getAttribute('data-expanded') === '1';
            if (expanded) {
                children.style.display = 'none';
                toggle.textContent = '+';
                toggle.setAttribute('data-expanded', '0');
            } else {
                children.style.display = '';
                toggle.textContent = '\u2212';
                toggle.setAttribute('data-expanded', '1');
            }
        });

        var expandAll = document.getElementById('aisk-tree-expand-all');
        var collapseAll = document.getElementById('aisk-tree-collapse-all');
        if (expandAll) {
            expandAll.addEventListener('click', function () {
                var toggles = document.querySelectorAll('#aisk-site-tree .aisk-tree-toggle');
                for (var t = 0; t < toggles.length; t++) {
                    toggles[t].textContent = '\u2212';
                    toggles[t].setAttribute('data-expanded', '1');
                    var ch = toggles[t].parentElement.querySelector('.aisk-tree-children');
                    if (ch) ch.style.display = '';
                }
            });
        }
        if (collapseAll) {
            collapseAll.addEventListener('click', function () {
                var toggles = document.querySelectorAll('#aisk-site-tree .aisk-tree-toggle');
                for (var t = 0; t < toggles.length; t++) {
                    toggles[t].textContent = '+';
                    toggles[t].setAttribute('data-expanded', '0');
                    var ch = toggles[t].parentElement.querySelector('.aisk-tree-children');
                    if (ch) ch.style.display = 'none';
                }
            });
        }
    })();

    // ── Search filter ──────────────────────────────────────────────
    (function () {
        var searchInput = document.getElementById('aisk-bulk-search');
        if (!searchInput) return;
        var table = document.getElementById('ai-seo-bulk-table');
        if (!table) return;

        searchInput.addEventListener('input', function () {
            var term = this.value.toLowerCase().trim();
            var rows = table.querySelectorAll('tbody tr');
            var visibleCount = 0;
            for (var i = 0; i < rows.length; i++) {
                var titleCell = rows[i].querySelector('td:nth-child(2)');
                var title = titleCell ? titleCell.textContent.toLowerCase() : '';
                if (term === '' || title.indexOf(term) !== -1) {
                    rows[i].style.display = '';
                    visibleCount++;
                    rows[i].querySelector('.aisk-row-num').textContent = visibleCount;
                } else {
                    rows[i].style.display = 'none';
                }
            }
        });
    })();

    // ── Column toggle (persistent via localStorage) ────────────────
    (function () {
        var table = document.getElementById('ai-seo-bulk-table');
        if (!table) return;

        var storageKey = 'aisk_bulk_columns';
        var widthPresets = {
            '00': ['30%', '33%', '30%', '', ''],
            '10': ['22%', '26%', '26%', '20%', ''],
            '01': ['22%', '26%', '26%', '', '20%'],
            '11': ['18%', '22%', '22%', '16%', '16%']
        };

        function applyWidths(showKeyphrase, showKeywords) {
            var key = (showKeyphrase ? '1' : '0') + (showKeywords ? '1' : '0');
            var w = widthPresets[key];
            var titleTh = table.querySelector('.aisk-col-title');
            var seoTh = table.querySelector('.aisk-col-seotitle');
            var descTh = table.querySelector('.aisk-col-desc');
            if (titleTh) titleTh.style.width = w[0];
            if (seoTh) seoTh.style.width = w[1];
            if (descTh) descTh.style.width = w[2];
        }

        function toggleColumn(className, show) {
            var cells = table.querySelectorAll('.' + className);
            for (var i = 0; i < cells.length; i++) {
                cells[i].style.display = show ? '' : 'none';
            }
        }

        function saveState(keyphrase, keywords) {
            try {
                localStorage.setItem(storageKey, JSON.stringify({ keyphrase: keyphrase, keywords: keywords }));
            } catch (e) { /* noop */ }
        }

        function loadState() {
            try {
                var raw = localStorage.getItem(storageKey);
                return raw ? JSON.parse(raw) : {};
            } catch (e) {
                return {};
            }
        }

        function refresh() {
            var kp = keyphraseToggle ? keyphraseToggle.checked : false;
            var kw = keywordsToggle ? keywordsToggle.checked : false;
            toggleColumn('aisk-col-keyphrase', kp);
            toggleColumn('aisk-col-keywords', kw);
            applyWidths(kp, kw);
            saveState(kp, kw);
        }

        var keyphraseToggle = document.getElementById('aisk-bulk-col-keyphrase');
        var keywordsToggle = document.getElementById('aisk-bulk-col-keywords');
        var saved = loadState();

        if (keyphraseToggle && saved.keyphrase) keyphraseToggle.checked = true;
        if (keywordsToggle && saved.keywords) keywordsToggle.checked = true;
        refresh();

        if (keyphraseToggle) keyphraseToggle.addEventListener('change', refresh);
        if (keywordsToggle) keywordsToggle.addEventListener('change', refresh);
    })();

})(jQuery);
