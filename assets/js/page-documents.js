/**
 * SEO Captain — Document SEO page scripts
 */
(function ($) {
    'use strict';

    var nonce = aiSeoDocs.nonce;
    var pluginUrl = aiSeoDocs.pluginUrl || '';

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

        $('.aisc-floating-banner').remove();
        $('body').append(banner);

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
    $('#ai-seo-doc-table').on('click', '.ai-seo-used-toggle', function () {
        $(this).siblings('.ai-seo-used-list').slideToggle(150);
    });

    // Enable save button when title or description changes.
    $('#ai-seo-doc-table').on('input', '.ai-seo-doc-title, .ai-seo-doc-desc', function () {
        var row = $(this).closest('tr');
        var titleChanged = row.find('.ai-seo-doc-title').val() !== row.find('.ai-seo-doc-title').data('original');
        var descChanged = row.find('.ai-seo-doc-desc').val() !== row.find('.ai-seo-doc-desc').data('original');
        row.find('.ai-seo-doc-save').prop('disabled', !(titleChanged || descChanged));
    });

    // Save document SEO data.
    $('#ai-seo-doc-table').on('click', '.ai-seo-doc-save', function () {
        var btn = $(this);
        var row = btn.closest('tr');
        btn.prop('disabled', true).text('Saving…');

        $.post(ajaxurl, {
            action: 'ai_seo_captain_save_doc_seo',
            _nonce: nonce,
            attachment_id: row.data('att-id'),
            seo_title: row.find('.ai-seo-doc-title').val(),
            seo_description: row.find('.ai-seo-doc-desc').val()
        }, function (resp) {
            if (resp.success) {
                btn.text('Saved ✓');
                row.find('.ai-seo-doc-title').data('original', row.find('.ai-seo-doc-title').val());
                row.find('.ai-seo-doc-desc').data('original', row.find('.ai-seo-doc-desc').val());
                setTimeout(function () { btn.text('Save'); }, 1500);
            } else {
                btn.text('Error').prop('disabled', false);
            }
        });
    });

    // Search filter.
    var searchInput = document.getElementById('aisc-doc-search');
    var table = document.getElementById('ai-seo-doc-table');
    if (searchInput && table) {
        searchInput.addEventListener('input', function () {
            var term = this.value.toLowerCase().trim();
            var rows = table.querySelectorAll('tbody tr');
            for (var i = 0; i < rows.length; i++) {
                var cell = rows[i].querySelector('td:nth-child(2)');
                var text = cell ? cell.textContent.toLowerCase() : '';
                rows[i].style.display = (term === '' || text.indexOf(term) !== -1) ? '' : 'none';
            }
        });
    }

})(jQuery);
