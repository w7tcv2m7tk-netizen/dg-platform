(function ($) {
    'use strict';

    window.dgAiRequest = function (task, payload, $status) {
        if (typeof dgAiAssist === 'undefined') {
            return $.Deferred().reject({ message: 'AI assist not loaded.' }).promise();
        }

        if (!dgAiAssist.hasAi) {
            window.location.href = dgAiAssist.apiSettingsUrl || '#';
            return $.Deferred().reject({ message: 'No API key.' }).promise();
        }

        if ($status && $status.length) {
            $status.text('AI is working…').removeClass('is-error');
        }

        var data = $.extend({ action: 'dg_ai_assist', nonce: dgAiAssist.nonce, task: task }, payload || {});

        return $.post(dgAiAssist.ajaxUrl, data).then(function (resp) {
            if (resp && resp.success && resp.data) {
                if ($status && $status.length) {
                    $status.text('Done — review and save.').removeClass('is-error');
                }
                return resp.data;
            }
            var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'AI request failed.';
            if ($status && $status.length) {
                $status.text(msg).addClass('is-error');
            }
            return $.Deferred().reject({ message: msg }).promise();
        }, function () {
            if ($status && $status.length) {
                $status.text('Could not reach the server.').addClass('is-error');
            }
            return $.Deferred().reject({ message: 'Network error.' }).promise();
        });
    };

    window.dgAiBindButton = function ($btn, options) {
        $btn.on('click', function (e) {
            e.preventDefault();
            var $el = $(this);
            if ($el.prop('disabled')) {
                return;
            }
            $el.prop('disabled', true).addClass('is-busy');
            dgAiRequest(options.task, options.payload ? options.payload() : {}, options.$status)
                .done(function (data) {
                    if (options.apply) {
                        options.apply(data);
                    }
                })
                .always(function () {
                    $el.prop('disabled', false).removeClass('is-busy');
                });
        });
    };
}(jQuery));
