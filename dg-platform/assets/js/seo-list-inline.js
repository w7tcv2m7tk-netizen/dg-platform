(function ($) {

    'use strict';



    var saveTimer = null;

    var $status = null;



    function statusEl() {

        if (!$status || !$status.length) {

            $status = $('#dg-seo-inline-status');

        }

        return $status;

    }



    function setStatus(msg, isError) {

        var $el = statusEl();

        if (!$el.length) {

            return;

        }

        $el.text(msg || '').toggleClass('is-error', !!isError);

        if (msg && !isError) {

            setTimeout(function () {

                if ($el.text() === msg) {

                    $el.text('');

                }

            }, 2500);

        }

    }



    function markSaved($el) {

        $el.css('border-color', '#059669');

        setTimeout(function () {

            $el.css('border-color', '');

        }, 1200);

    }



    function saveField($el) {

        if (typeof dgSeoListInline === 'undefined') {

            return;

        }



        var postId = $el.data('post-id');

        var field = $el.data('field');

        var value = $el.val();



        setStatus('Saving…');



        $.post(dgSeoListInline.ajaxUrl, {

            action: 'dg_save_seo_inline',

            nonce: dgSeoListInline.nonce,

            post_id: postId,

            field: field,

            value: value

        }).done(function (resp) {

            if (resp && resp.success) {

                markSaved($el);

                setStatus('Saved.');

            } else {

                setStatus((resp && resp.data) ? resp.data : 'Save failed — refresh and try again.', true);

            }

        }).fail(function () {

            setStatus('Could not reach the server. Check you are still logged in.', true);

        });

    }



    $(document).on('blur', '.dg-seo-inline', function () {

        saveField($(this));

    });



    $(document).on('change', '.dg-seo-inline-robots', function () {

        saveField($(this));

    });



    $(document).on('keydown', '.dg-seo-inline', function (e) {

        if (e.key === 'Enter' && this.tagName !== 'TEXTAREA') {

            e.preventDefault();

            $(this).blur();

        }

    });



    $(document).on('input', '.dg-seo-inline', function () {

        var $el = $(this);

        if ($el.is('select')) {

            return;

        }

        clearTimeout(saveTimer);

        saveTimer = setTimeout(function () {

            saveField($el);

        }, 800);

    });



    function indexNowConfig() {

        if (typeof dgSeoListInline !== 'undefined' && dgSeoListInline.indexNowNonce) {

            return { ajaxUrl: dgSeoListInline.ajaxUrl, nonce: dgSeoListInline.indexNowNonce };

        }

        if (typeof dgSeoAudit !== 'undefined' && dgSeoAudit.indexNowNonce) {

            return { ajaxUrl: dgSeoAudit.ajaxUrl, nonce: dgSeoAudit.indexNowNonce };

        }

        return null;

    }



    function setIndexNowStatus(msg, isError) {

        var $el = $('#dg-seo-indexnow-status');

        if (!$el.length) {

            $el = $('#dg-seo-inline-status');

        }

        if ($el.length) {

            $el.text(msg || '').toggleClass('is-error', !!isError);

        }

    }



    $(document).on('click', '.dg-seo-indexnow-btn', function (e) {

        e.preventDefault();

        var cfg = indexNowConfig();

        if (!cfg) {

            return;

        }

        var $btn = $(this);

        if ($btn.prop('disabled')) {

            return;

        }

        var postId = $btn.data('post-id') || 0;

        var bulk = $btn.data('bulk') || '';

        var label = $btn.text();

        $btn.prop('disabled', true).text(bulk ? 'Indexing…' : '…');

        setIndexNowStatus('Submitting to IndexNow…');



        $.post(cfg.ajaxUrl, {

            action: 'dg_seo_indexnow',

            nonce: cfg.nonce,

            post_id: postId,

            bulk: bulk

        }).done(function (resp) {

            if (resp && resp.success) {

                setIndexNowStatus(resp.data && resp.data.message ? resp.data.message : 'Submitted.');

                if (resp.data && resp.data.last_label && postId) {

                    var $cell = $btn.closest('td');

                    var $last = $cell.find('.dg-seo-indexnow-last');

                    if ($last.length) {

                        $last.text(resp.data.last_label);

                    } else {

                        $btn.after('<div class="dg-seo-indexnow-last">' + resp.data.last_label + '</div>');

                    }

                    $('.dg-seo-indexnow-toolbar-last').text('Indexed ' + resp.data.last_label);

                }

            } else {

                setIndexNowStatus((resp && resp.data && resp.data.message) ? resp.data.message : 'IndexNow failed.', true);

            }

        }).fail(function () {

            setIndexNowStatus('Could not reach the server.', true);

        }).always(function () {

            $btn.prop('disabled', false).text(label);

        });

    });

}(jQuery));

