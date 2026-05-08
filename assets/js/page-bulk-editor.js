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

})(jQuery);
