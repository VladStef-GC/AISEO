/**
 * SEO Captain — Image SEO page scripts
 */
(function ($) {
    'use strict';

    var nonce = aiSeoImages.nonce;
    var pluginUrl = aiSeoImages.pluginUrl || '';

    // Inject keyframes for floating banner animation.
    if (!document.getElementById('aisc-banner-keyframes')) {
        var style = document.createElement('style');
        style.id = 'aisc-banner-keyframes';
        style.textContent = '@keyframes aisc-slide-in{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}';
        document.head.appendChild(style);
    }

    /**
     * Show a floating toast banner over the screen.
     */
    function showFloatingBanner(message, severity) {
        var iconFile = severity === 'is-success' ? 'seo-captain-side-ok-d.svg' : 'seo-captain-side-d.svg';
        var iconUrl = pluginUrl + 'assets/img/' + iconFile;
        var bgColor = severity === 'is-success' ? '#00a32a' : '#d63638';

        var banner = $(
            '<div class="aisc-floating-banner" style="' +
            'position:fixed;top:40px;right:24px;z-index:999999;display:flex;align-items:center;gap:12px;' +
            'background:#fff;border-left:4px solid ' + bgColor + ';box-shadow:0 4px 24px rgba(0,0,0,.15);' +
            'border-radius:6px;padding:14px 22px;max-width:420px;font-size:13px;line-height:1.4;' +
            'animation:aisc-slide-in .3s ease-out;">' +
            '<img src="' + iconUrl + '" alt="" style="width:32px;height:32px;flex-shrink:0;"/>' +
            '<span>' + message + '</span>' +
            '</div>'
        );

        // Remove any existing banner first.
        $('.aisc-floating-banner').remove();
        $('body').append(banner);

        // Auto-dismiss after 5 seconds.
        setTimeout(function () {
            banner.fadeOut(300, function () { banner.remove(); });
        }, 5000);
    }

    // Purge media cache.
    $(document).on('click', '.ai-seo-purge-media', function (e) {
        e.preventDefault();
        var link = $(this);
        var attId = link.data('att-id');

        if (!attId) {
            showFloatingBanner('No attachment ID found.', 'is-error');
            return;
        }

        link.css('pointer-events', 'none').text('Purging…');

        $.post(ajaxurl, {
            action: 'aisc_purge_media_cache',
            _nonce: nonce,
            attachment_id: attId
        }, function (resp) {
            if (resp.success) {
                showFloatingBanner(resp.data.message, 'is-success');
            } else {
                showFloatingBanner(resp.data.message || 'Purge failed.', 'is-error');
            }
        }).fail(function () {
            showFloatingBanner('Request failed. Please try again.', 'is-error');
        }).always(function () {
            link.css('pointer-events', '').text('Purge Cache');
        });
    });

    // Toggle "Used on" expanded list.
    $('#ai-seo-image-table').on('click', '.ai-seo-used-toggle', function () {
        $(this).siblings('.ai-seo-used-list').slideToggle(150);
    });

    // Enable save button when alt text changes.
    $('#ai-seo-image-table').on('input', '.ai-seo-img-alt', function () {
        var row = $(this).closest('tr');
        var changed = $(this).val() !== $(this).data('original');
        row.find('.ai-seo-img-save').prop('disabled', !changed);
    });

    // Save individual image alt text.
    $('#ai-seo-image-table').on('click', '.ai-seo-img-save', function () {
        var btn = $(this);
        var row = btn.closest('tr');
        btn.prop('disabled', true).text('Saving…');

        $.post(ajaxurl, {
            action: 'ai_seo_captain_save_image_alt',
            _nonce: nonce,
            attachment_id: row.data('att-id'),
            alt_text: row.find('.ai-seo-img-alt').val()
        }, function (resp) {
            if (resp.success) {
                btn.text('Saved ✓');
                row.find('.ai-seo-img-alt').data('original', row.find('.ai-seo-img-alt').val());
                setTimeout(function () { btn.text('Save'); }, 1500);
            } else {
                btn.text('Error').prop('disabled', false);
            }
        });
    });

    // Search filter.
    var searchInput = document.getElementById('aisc-image-search');
    var table = document.getElementById('ai-seo-image-table');
    if (searchInput && table) {
        searchInput.addEventListener('input', function () {
            var term = this.value.toLowerCase().trim();
            var rows = table.querySelectorAll('tbody tr');
            for (var i = 0; i < rows.length; i++) {
                var fileCell = rows[i].querySelector('td:nth-child(2)');
                var filename = fileCell ? fileCell.textContent.toLowerCase() : '';
                rows[i].style.display = (term === '' || filename.indexOf(term) !== -1) ? '' : 'none';
            }
        });
    }

    // ─── AI Generate Alt Text (single image) ─────────────────────────
    $('#ai-seo-image-table').on('click', '.aisc-ai-gen-alt', function () {
        var btn = $(this);
        var row = btn.closest('tr');
        var attId = btn.data('att-id') || row.data('att-id');

        if (!attId) return;

        btn.prop('disabled', true).text('⏳');

        $.post(ajaxurl, {
            action: 'local_ai_generate_image_seo',
            _nonce: nonce,
            attachment_id: attId
        }, function (resp) {
            if (resp.success && resp.data) {
                var altInput = row.find('.ai-seo-img-alt');
                var newAlt = resp.data.alt_text || '';

                altInput.val(newAlt).data('original', newAlt);
                row.find('.ai-seo-img-save').prop('disabled', true);

                var method = resp.data.method || 'ai';
                var label = resp.data.decorative ? 'Decorative ✓' : method;
                showFloatingBanner(
                    '<strong>' + (row.find('td:nth-child(2) strong').text() || 'Image') + '</strong><br>' +
                    'Alt: ' + (newAlt || '<em>(empty — decorative)</em>') + '<br>' +
                    '<span style="font-size:11px;color:#888;">Method: ' + label + '</span>',
                    'is-success'
                );
                btn.text('🤖');
            } else {
                showFloatingBanner(
                    'AI generation failed: ' + (resp.data && resp.data.error ? resp.data.error : 'Unknown error'),
                    'is-error'
                );
                btn.text('🤖');
            }
        }).fail(function () {
            showFloatingBanner('Request failed. Is your Local AI server running?', 'is-error');
            btn.text('🤖');
        }).always(function () {
            btn.prop('disabled', false);
        });
    });

    // ─── Bulk Generate Missing Alt Text ──────────────────────────────
    var bulkRunning = false;

    $('#aisc-bulk-generate-alt').on('click', function () {
        if (bulkRunning) return;

        var btn = $(this);
        var progressEl = $('#aisc-bulk-progress');

        // Collect rows with empty alt text.
        var rows = [];
        $('#ai-seo-image-table tbody tr').each(function () {
            var altInput = $(this).find('.ai-seo-img-alt');
            if (altInput.length && $.trim(altInput.val()) === '') {
                rows.push($(this));
            }
        });

        if (rows.length === 0) {
            showFloatingBanner('All visible images already have alt text.', 'is-success');
            return;
        }

        bulkRunning = true;
        btn.prop('disabled', true).text('⏳ Generating...');
        progressEl.show();

        var done = 0;
        var success = 0;
        var failed = 0;
        var total = rows.length;

        function processNext() {
            if (done >= total) {
                bulkRunning = false;
                btn.prop('disabled', false).text('🤖 AI Generate Missing Alt');
                progressEl.hide();
                showFloatingBanner(
                    'Bulk generation complete: <strong>' + success + '</strong> generated, <strong>' + failed + '</strong> failed out of ' + total + ' images.',
                    failed > 0 ? 'is-error' : 'is-success'
                );
                return;
            }

            var row = rows[done];
            var attId = row.data('att-id');
            var aiBtn = row.find('.aisc-ai-gen-alt');
            aiBtn.prop('disabled', true).text('⏳');
            progressEl.text((done + 1) + ' / ' + total);

            $.post(ajaxurl, {
                action: 'local_ai_generate_image_seo',
                _nonce: nonce,
                attachment_id: attId
            }, function (resp) {
                if (resp.success && resp.data) {
                    var altInput = row.find('.ai-seo-img-alt');
                    altInput.val(resp.data.alt_text || '').data('original', resp.data.alt_text || '');
                    row.find('.ai-seo-img-save').prop('disabled', true);
                    success++;
                } else {
                    failed++;
                }
            }).fail(function () {
                failed++;
            }).always(function () {
                aiBtn.prop('disabled', false).text('🤖');
                done++;
                // Small delay between requests to avoid overwhelming the local server.
                setTimeout(processNext, 500);
            });
        }

        processNext();
    });

})(jQuery);
