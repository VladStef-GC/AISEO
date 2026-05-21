/**
 * Google Search Console dashboard JS.
 *
 * Relies on wp_localize_script data:
 *   aiSeoCaptainGsc.ajaxurl, .nonce, .trendData
 *
 * @package AI_SEO_Captain
 */
(function () {
    'use strict';

    var cfg = window.aiSeoCaptainGsc || {};
    var nonce = cfg.nonce || '';
    var trend = cfg.trendData || [];

    /* ---- Helpers ---- */
    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return (ctx || document).querySelectorAll(sel); }

    function post(action, data, cb) {
        data._nonce = nonce;
        data.action = action;
        var fd = new FormData();
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });

        var xhr = new XMLHttpRequest();
        xhr.open('POST', cfg.ajaxurl);
        xhr.onload = function () {
            var res;
            try { res = JSON.parse(xhr.responseText); } catch (e) { res = { success: false }; }
            cb(res);
        };
        xhr.onerror = function () { cb({ success: false }); };
        xhr.send(fd);
    }

    /* ---- Format ---- */
    function fmtNum(n) { return Number(n).toLocaleString(); }
    function fmtPct(n) { return (Number(n) * 100).toFixed(1) + '%'; }
    function fmtPos(n) { return Number(n).toFixed(1); }

    /* ---- Chart (Canvas) ---- */
    var chart = null;
    var activeMetrics = { clicks: true, impressions: true };

    function drawChart(data) {
        var canvas = $('#gsc-trend-chart');
        if (!canvas || !data || !data.length) return;

        var ctx = canvas.getContext('2d');
        var W = canvas.parentElement.clientWidth - 40;
        var H = 240;
        canvas.width = W * (window.devicePixelRatio || 1);
        canvas.height = H * (window.devicePixelRatio || 1);
        canvas.style.width = W + 'px';
        canvas.style.height = H + 'px';
        ctx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1);

        var pad = { top: 20, right: 60, bottom: 40, left: 60 };
        var cw = W - pad.left - pad.right;
        var ch = H - pad.top - pad.bottom;

        var series = {
            clicks: { color: '#4285f4', values: data.map(function (d) { return +d.clicks; }) },
            impressions: { color: '#5e35b1', values: data.map(function (d) { return +d.impressions; }) }
        };

        ctx.clearRect(0, 0, W, H);

        // Draw each active series.
        Object.keys(series).forEach(function (key) {
            if (!activeMetrics[key]) return;
            var s = series[key];
            var max = Math.max.apply(null, s.values) || 1;
            var step = cw / Math.max(s.values.length - 1, 1);

            ctx.beginPath();
            ctx.strokeStyle = s.color;
            ctx.lineWidth = 2;
            s.values.forEach(function (v, i) {
                var x = pad.left + i * step;
                var y = pad.top + ch - (v / max) * ch;
                if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            });
            ctx.stroke();

            // Fill area.
            ctx.globalAlpha = 0.08;
            ctx.lineTo(pad.left + (s.values.length - 1) * step, pad.top + ch);
            ctx.lineTo(pad.left, pad.top + ch);
            ctx.closePath();
            ctx.fillStyle = s.color;
            ctx.fill();
            ctx.globalAlpha = 1;
        });

        // X axis labels (first, mid, last).
        ctx.fillStyle = '#646970';
        ctx.font = '11px -apple-system, BlinkMacSystemFont, sans-serif';
        ctx.textAlign = 'center';
        var labels = [0, Math.floor(data.length / 2), data.length - 1];
        labels.forEach(function (i) {
            if (data[i]) {
                var x = pad.left + i * (cw / Math.max(data.length - 1, 1));
                ctx.fillText(data[i].fetch_date, x, H - 8);
            }
        });
    }

    /* ---- Metric card toggle ---- */
    function initMetricCards() {
        var cards = $$('.aiseo-gsc-metric');
        cards.forEach(function (card) {
            var metric = card.getAttribute('data-metric');
            if (metric === 'clicks' || metric === 'impressions') {
                card.classList.add('active');
                card.addEventListener('click', function () {
                    activeMetrics[metric] = !activeMetrics[metric];
                    card.classList.toggle('active', activeMetrics[metric]);
                    drawChart(trend);
                });
            }
        });
    }

    /* ---- AJAX Notice Banner ---- */
    var iconBase = (cfg.pluginUrl || '') + 'assets/img/';

    function showNotice(message, type) {
        var wrap  = $('#gsc-ajax-notice');
        var msg   = $('#gsc-ajax-notice-msg');
        var title = $('#gsc-ajax-notice-title');
        var icon  = $('#gsc-ajax-notice-icon');
        if (!wrap || !msg) return;

        var isOk = (type === 'success');
        wrap.className = 'ai-seo-captain-notice ' + (isOk ? 'is-success' : 'is-error');
        if (title) title.textContent = isOk ? 'Success' : 'Error';
        if (icon)  icon.src = iconBase + (isOk ? 'seo-captain-side-ok-d.svg' : 'seo-captain-side-d.svg');
        msg.textContent = message;
        wrap.style.display = '';

        // Auto-hide success after 6s.
        if (isOk) {
            setTimeout(function () { wrap.style.display = 'none'; }, 6000);
        }
    }

    /* ---- Refresh button (date range) ---- */
    function initRefresh() {
        var btn = $('#gsc-refresh-btn');
        if (!btn) return;

        var origHTML = btn.innerHTML;

        btn.addEventListener('click', function () {
            var start = $('#gsc-start-date').value;
            var end = $('#gsc-end-date').value;
            if (!start || !end) return;

            btn.disabled = true;
            btn.innerHTML = '<span class="dashicons dashicons-image-rotate spin" style="vertical-align:middle;"></span> Loading\u2026';

            post('ai_seo_captain_gsc_data', { start_date: start, end_date: end }, function (res) {
                btn.disabled = false;
                btn.innerHTML = origHTML;

                if (!res.success) {
                    showNotice(res.data && res.data.message ? res.data.message : 'Request failed.', 'error');
                    return;
                }

                var d = res.data;
                updateOverview(d.overview);
                updateTable('queries', d.top_queries);
                updateTable('pages', d.top_pages);
                trend = d.trend;
                drawChart(trend);
                showNotice('Dashboard updated for ' + start + ' to ' + end + '.', 'success');
            });
        });
    }

    /* ---- Sync button ---- */
    function initSync() {
        var btn = $('#gsc-sync-btn');
        if (!btn) return;

        var origHTML = btn.innerHTML;

        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.innerHTML = '<span class="dashicons dashicons-update spin" style="vertical-align:middle;"></span> Syncing\u2026';

            post('ai_seo_captain_gsc_sync', { days: 28 }, function (res) {
                btn.disabled = false;
                btn.innerHTML = origHTML;
                if (res.success) {
                    showNotice(res.data.message, 'success');
                    // Refresh the dashboard data.
                    var refreshBtn = $('#gsc-refresh-btn');
                    if (refreshBtn) refreshBtn.click();
                } else {
                    showNotice(res.data && res.data.message ? res.data.message : 'Sync failed.', 'error');
                }
            });
        });
    }

    /* ---- Test Connection button ---- */
    function initTest() {
        var btn = $('#gsc-test-btn');
        if (!btn) return;

        var origHTML = btn.innerHTML;

        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.innerHTML = '<span class="dashicons dashicons-admin-plugins spin" style="vertical-align:middle;"></span> Testing\u2026';

            post('ai_seo_captain_gsc_test', {}, function (res) {
                btn.disabled = false;
                btn.innerHTML = origHTML;
                if (res.success) {
                    showNotice('\u2705 ' + res.data.message, 'success');
                } else {
                    showNotice('\u274C ' + (res.data && res.data.message ? res.data.message : 'Connection test failed.'), 'error');
                }
            });
        });
    }

    /* ---- Update helpers ---- */
    function updateOverview(ov) {
        if (!ov) return;
        var el;
        el = $('#gsc-val-clicks'); if (el) el.textContent = fmtNum(ov.clicks);
        el = $('#gsc-val-impressions'); if (el) el.textContent = fmtNum(ov.impressions);
        el = $('#gsc-val-ctr'); if (el) el.textContent = fmtPct(ov.ctr);
        el = $('#gsc-val-position'); if (el) el.textContent = fmtPos(ov.position);
    }

    function updateTable(type, rows) {
        var tbody = $('#gsc-table-' + type + ' tbody');
        if (!tbody || !rows) return;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="5">No data for this range.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (r) {
            var label = r.dimension_value;
            if (type === 'pages') {
                try { label = new URL(r.dimension_value).pathname || r.dimension_value; } catch (e) { }
            }
            return '<tr>'
                + '<td title="' + esc(r.dimension_value) + '">' + esc(label) + '</td>'
                + '<td class="num">' + fmtNum(r.clicks) + '</td>'
                + '<td class="num">' + fmtNum(r.impressions) + '</td>'
                + '<td class="num">' + fmtPct(r.ctr) + '</td>'
                + '<td class="num">' + fmtPos(r.position) + '</td>'
                + '</tr>';
        }).join('');
    }

    function esc(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* ---- Copy redirect URI ---- */
    function initCopy() {
        var btns = $$('.aiseo-copy-btn');
        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = $(btn.getAttribute('data-copy-target'));
                if (!target) return;
                var text = target.textContent.trim();
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () {
                        btn.title = 'Copied!';
                        setTimeout(function () { btn.title = 'Copy'; }, 2000);
                    });
                } else {
                    // Fallback.
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    btn.title = 'Copied!';
                    setTimeout(function () { btn.title = 'Copy'; }, 2000);
                }
            });
        });
    }

    /* ---- Boot ---- */
    document.addEventListener('DOMContentLoaded', function () {
        initMetricCards();
        initRefresh();
        initSync();
        initTest();
        initCopy();
        drawChart(trend);
        window.addEventListener('resize', function () { drawChart(trend); });
    });

})();
