/**
 * AI SEO Keeper — Document SEO page scripts
 */
(function ($) {
    'use strict';

    var nonce = aiSeoDocs.nonce;

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
            action: 'ai_seo_keeper_save_doc_seo',
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

})(jQuery);
