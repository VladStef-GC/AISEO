/**
 * AI SEO Keeper — Image SEO page scripts
 */
(function ($) {
    'use strict';

    var nonce = aiSeoImages.nonce;

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
            action: 'ai_seo_keeper_save_image_alt',
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

})(jQuery);
