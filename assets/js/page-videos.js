/**
 * SEO Captain — Video SEO page scripts
 */
(function ($) {
    'use strict';

    var nonce = aiSeoVideos.nonce;

    // Enable save button when title or description changes.
    $('#ai-seo-video-table').on('input', '.ai-seo-vid-title, .ai-seo-vid-desc', function () {
        var row = $(this).closest('tr');
        var titleChanged = row.find('.ai-seo-vid-title').val() !== row.find('.ai-seo-vid-title').data('original');
        var descChanged = row.find('.ai-seo-vid-desc').val() !== row.find('.ai-seo-vid-desc').data('original');
        row.find('.ai-seo-vid-save').prop('disabled', !(titleChanged || descChanged));
    });

    // Save video SEO data.
    $('#ai-seo-video-table').on('click', '.ai-seo-vid-save', function () {
        var btn = $(this);
        var row = btn.closest('tr');
        btn.prop('disabled', true).text('Saving…');

        $.post(ajaxurl, {
            action: 'ai_seo_captain_save_video_seo',
            _nonce: nonce,
            video_key: row.data('video-key'),
            post_id: row.data('post-id'),
            seo_title: row.find('.ai-seo-vid-title').val(),
            seo_description: row.find('.ai-seo-vid-desc').val()
        }, function (resp) {
            if (resp.success) {
                btn.text('Saved ✓');
                row.find('.ai-seo-vid-title').data('original', row.find('.ai-seo-vid-title').val());
                row.find('.ai-seo-vid-desc').data('original', row.find('.ai-seo-vid-desc').val());
                setTimeout(function () { btn.text('Save'); }, 1500);
            } else {
                btn.text('Error').prop('disabled', false);
            }
        });
    });

    // Search filter.
    var searchInput = document.getElementById('aisc-video-search');
    var table = document.getElementById('ai-seo-video-table');
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
