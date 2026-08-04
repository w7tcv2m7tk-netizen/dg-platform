(function ($) {
    'use strict';

    if (typeof dgSeoAudit === 'undefined') {
        return;
    }

    var $root = $('#dg-seo-page-audit');
    if (!$root.length) {
        return;
    }

    var $select = $('#dg-seo-audit-page-select');
    var $form = $('#dg-seo-audit-form');
    var $status = $('#dg-seo-audit-status');

    function charCount($el, $counter, max) {
        var len = ($el.val() || $el.attr('placeholder') || '').length;
        $counter.text(len + ' / ' + max);
        $counter.toggleClass('dg-seo-count-over', len > max);
        $counter.toggleClass('dg-seo-count-warn', len > 0 && len < max * 0.6);
    }

    function bindCounters() {
        charCount($('#dg_audit_title'), $('#dg-audit-title-count'), 60);
        charCount($('#dg_audit_description'), $('#dg-audit-desc-count'), 160);
        $('#dg_audit_title, #dg_audit_description').on('input', function () {
            charCount($(this), $(this).closest('tr').find('.dg-seo-char-count'), $(this).is('#dg_audit_title') ? 60 : 160);
        });
    }

    function escapeHtml(str) {
        return $('<div>').text(str || '').html();
    }

    function renderChecks(checks) {
        var html = '';
        (checks || []).forEach(function (check) {
            html += '<li class="dg-seo-check dg-seo-check-' + escapeHtml(check.status) + '">';
            html += '<span class="dg-seo-check-icon" aria-hidden="true"></span>';
            html += '<div class="dg-seo-check-body">';
            html += '<strong>' + escapeHtml(check.label) + '</strong>';
            html += '<span class="dg-seo-check-msg">' + escapeHtml(check.message) + '</span>';
            if (check.suggestion) {
                html += '<span class="dg-seo-check-tip">' + escapeHtml(check.suggestion) + '</span>';
            }
            html += '</div></li>';
        });
        $('#dg-seo-audit-checks').html(html);
    }

    function renderAnalysis(data) {
        if (!data) {
            return;
        }

        $root.attr('data-post-id', data.post_id);
        $('#dg-seo-audit-score').text(data.score);
        $('#dg-seo-audit-grade').text(data.grade.label).css('color', data.grade.color);
        $('.dg-seo-audit-score-ring').css('--score-color', data.grade.color);
        $('#dg-seo-audit-post-title').text(data.post_title || '');

        var stats = data.stats || {};
        $('#dg-seo-audit-stats').html(
            '<li>' + (stats.word_count || 0) + ' words</li>' +
            '<li>Title: ' + (stats.title_length || 0) + ' chars</li>' +
            '<li>Description: ' + (stats.description_length || 0) + ' chars</li>' +
            '<li>' + (stats.internal_links || 0) + ' internal links</li>'
        );

        renderChecks(data.checks);

        var fields = data.fields || {};
        $('#dg_audit_focus_keyword').val(fields.focus_keyword || '');
        $('#dg_audit_title').val(fields.title || '');
        $('#dg_audit_description').val(fields.description || '');
        $('#dg_audit_og_title').val(fields.og_title || '');
        $('#dg_audit_og_description').val(fields.og_description || '');
        $('#dg_audit_og_image').val(fields.og_image || '');
        $('#dg_audit_robots').val(fields.robots || 'index,follow');

        bindCounters();
    }

    function setLoading(on) {
        $root.toggleClass('is-loading', on);
    }

    function setStatus(msg, isError) {
        $status.text(msg || '').toggleClass('is-error', !!isError);
    }

    function ajaxAnalyze(postId) {
        setLoading(true);
        setStatus('Analysing…');

        return $.post(dgSeoAudit.ajaxUrl, {
            action: 'dg_seo_analyze_page',
            nonce: dgSeoAudit.nonce,
            post_id: postId
        }).done(function (resp) {
            if (resp.success && resp.data) {
                renderAnalysis(resp.data);
                setStatus('');
            } else {
                setStatus((resp.data && resp.data.message) || 'Analysis failed.', true);
            }
        }).fail(function () {
            setStatus('Could not reach the server.', true);
        }).always(function () {
            setLoading(false);
        });
    }

    function applyAiSuggestions(data) {
        $('#dg_audit_focus_keyword').val(data.focus_keyword || '').trigger('input');
        $('#dg_audit_title').val(data.title || '').trigger('input');
        $('#dg_audit_description').val(data.description || '').trigger('input');
        $('#dg_audit_og_title').val(data.og_title || '');
        $('#dg_audit_og_description').val(data.og_description || '');
        if (data.robots) {
            $('#dg_audit_robots').val(data.robots);
        }
        bindCounters();
    }

    function setAiLoading(on) {
        $('#dg-seo-ai-optimize').prop('disabled', on).toggleClass('is-busy', on);
    }

    $('#dg-seo-ai-optimize').on('click', function () {
        if (!dgSeoAudit.hasAi) {
            window.location.href = dgSeoAudit.apiSettingsUrl || '#';
            return;
        }

        var postId = $root.attr('data-post-id') || $select.val();
        setAiLoading(true);
        setLoading(true);
        setStatus('AI is optimising this page…');

        $.post(dgSeoAudit.ajaxUrl, {
            action: 'dg_seo_ai_optimize',
            nonce: dgSeoAudit.nonce,
            post_id: postId
        }).done(function (resp) {
            if (resp.success && resp.data) {
                applyAiSuggestions(resp.data);
                var note = resp.data.rationale ? (' ' + resp.data.rationale) : '';
                var provider = resp.data.provider ? (' (' + resp.data.provider + ')') : '';
                setStatus('AI suggestions applied' + provider + '.' + note + ' Review and click Save & re-score.');
            } else {
                setStatus((resp.data && resp.data.message) || 'AI optimisation failed.', true);
            }
        }).fail(function () {
            setStatus('Could not reach the server.', true);
        }).always(function () {
            setAiLoading(false);
            setLoading(false);
        });
    });

    $select.on('change', function () {
        var postId = $(this).val();
        var url = new URL(window.location.href);
        url.searchParams.set('post_id', postId);
        url.searchParams.set('tab', 'audit');
        window.history.replaceState({}, '', url.toString());
        ajaxAnalyze(postId);
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        var postId = $root.attr('data-post-id') || $select.val();
        setLoading(true);
        setStatus('Saving…');

        var payload = {
            action: 'dg_seo_save_audit',
            nonce: dgSeoAudit.nonce,
            post_id: postId,
            focus_keyword: $('#dg_audit_focus_keyword').val(),
            title: $('#dg_audit_title').val(),
            description: $('#dg_audit_description').val(),
            og_title: $('#dg_audit_og_title').val(),
            og_description: $('#dg_audit_og_description').val(),
            og_image: $('#dg_audit_og_image').val(),
            robots: $('#dg_audit_robots').val()
        };

        $.post(dgSeoAudit.ajaxUrl, payload).done(function (resp) {
            if (resp.success && resp.data) {
                renderAnalysis(resp.data);
                setStatus('Saved — score updated.');
            } else {
                setStatus((resp.data && resp.data.message) || 'Save failed.', true);
            }
        }).fail(function () {
            setStatus('Could not save.', true);
        }).always(function () {
            setLoading(false);
        });
    });

    $(document).on('click', '.dg-seo-use-suggestion', function (e) {
        e.preventDefault();
        var target = $(this).data('target');
        var value = $(this).data('value');
        $('#' + target).val(value).trigger('input');
    });

    bindCounters();
})(jQuery);
