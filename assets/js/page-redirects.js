/**
 * SEO Captain — Redirects page scripts
 */
(function ($) {
    'use strict';

    var nonce = aiscRedirects.nonce;

    $('#ai-seo-redir-add-btn').on('click', function () {
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'ai_seo_captain_add_redirect',
            _nonce: nonce,
            source_url: $('#ai-seo-redir-source').val(),
            target_url: $('#ai-seo-redir-target').val(),
            status_code: $('#ai-seo-redir-status').val()
        }, function (resp) {
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.data || 'Error adding redirect.');
                btn.prop('disabled', false);
            }
        });
    });

    $(document).on('click', '.ai-seo-redir-delete', function () {
        var row = $(this).closest('tr');
        $.post(ajaxurl, {
            action: 'ai_seo_captain_delete_redirect',
            _nonce: nonce,
            id: $(this).data('id')
        }, function (resp) {
            if (resp.success) row.fadeOut(200, function () {
                row.remove();
            });
        });
    });

    $('#ai-seo-clear-404s').on('click', function () {
        if (!confirm('Clear all 404 entries?')) return;
        $.post(ajaxurl, {
            action: 'ai_seo_captain_clear_404s',
            _nonce: nonce
        }, function (resp) {
            if (resp.success) location.reload();
        });
    });
})(jQuery);
