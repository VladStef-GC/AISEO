/**
 * Site-wide AI Chat — frontend JavaScript.
 */
jQuery(function ($) {
    'use strict';

    var $input   = $('#ai-seo-site-chat-input');
    var $send    = $('#ai-seo-site-chat-send');
    var $clear   = $('#ai-seo-site-chat-clear');
    var $status  = $('#ai-seo-site-chat-status');
    var $shell   = $('#ai-seo-site-chat-shell');
    var busy     = false;

    function setStatus(text, isError) {
        $status.text(text).css('color', isError ? '#dc3232' : '#646970');
    }

    function scrollToBottom() {
        $shell[0].scrollTop = $shell[0].scrollHeight;
    }

    // --- Send message ---
    $send.on('click', function () {
        if (busy) return;

        var message = $.trim($input.val());
        if (!message) {
            setStatus('Please enter a question.', true);
            return;
        }

        busy = true;
        $send.prop('disabled', true);
        setStatus('AI is thinking…', false);

        $.ajax({
            url: aiSeoSiteChat.ajaxUrl,
            method: 'POST',
            data: {
                action: aiSeoSiteChat.chatAction,
                nonce: aiSeoSiteChat.nonce,
                message: message
            },
            timeout: 120000
        })
        .done(function (response) {
            if (response.success && response.data && response.data.chatHtml) {
                $shell.html(response.data.chatHtml);
                $input.val('');
                setStatus('', false);
                scrollToBottom();
            } else {
                setStatus(response.data && response.data.message ? response.data.message : 'Unexpected response.', true);
            }
        })
        .fail(function (xhr) {
            var msg = 'Request failed.';
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.data && resp.data.message) msg = resp.data.message;
            } catch (e) { /* ignore */ }
            setStatus(msg, true);
        })
        .always(function () {
            busy = false;
            $send.prop('disabled', false);
        });
    });

    // --- Send on Ctrl+Enter ---
    $input.on('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            $send.trigger('click');
        }
    });

    // --- Clear chat ---
    $clear.on('click', function () {
        if (busy) return;
        if (!confirm('Clear all site chat history?')) return;

        busy = true;
        setStatus('Clearing…', false);

        $.ajax({
            url: aiSeoSiteChat.ajaxUrl,
            method: 'POST',
            data: {
                action: aiSeoSiteChat.clearAction,
                nonce: aiSeoSiteChat.nonce
            }
        })
        .done(function (response) {
            if (response.success && response.data && response.data.chatHtml !== undefined) {
                $shell.html(response.data.chatHtml);
                setStatus('Chat cleared.', false);
            }
        })
        .fail(function () {
            setStatus('Failed to clear chat.', true);
        })
        .always(function () {
            busy = false;
        });
    });
});
