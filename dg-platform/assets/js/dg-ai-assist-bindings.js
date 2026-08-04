(function ($) {
    'use strict';

    function showModal(title, bodyHtml) {
        var $existing = $('#dg-ai-modal');
        if (!$existing.length) {
            $('body').append(
                '<div id="dg-ai-modal" class="dg-ai-modal" style="display:none;">' +
                '<div class="dg-ai-modal-backdrop"></div>' +
                '<div class="dg-ai-modal-panel">' +
                '<button type="button" class="dg-ai-modal-close" aria-label="Close">&times;</button>' +
                '<h2 class="dg-ai-modal-title"></h2>' +
                '<div class="dg-ai-modal-body"></div>' +
                '</div></div>'
            );
            $existing = $('#dg-ai-modal');
            $existing.on('click', '.dg-ai-modal-close, .dg-ai-modal-backdrop', function () {
                $existing.hide();
            });
        }
        $existing.find('.dg-ai-modal-title').text(title || 'AI suggestion');
        $existing.find('.dg-ai-modal-body').html(bodyHtml || '');
        $existing.show();
    }

    function esc(s) {
        return $('<div>').text(s || '').html();
    }

    function initBindings() {
        $(document).on('click', '.dg-ai-btn', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var task = $btn.data('aiTask');
            var $status = $btn.siblings('.dg-ai-status').first();
            if (!$status.length) {
                $status = $btn.closest('.dg-ai-actions, p, td, .dg-panel').find('.dg-ai-status').first();
            }

            var payload = {};
            if ($btn.data('aiPostId')) {
                payload.post_id = $btn.data('aiPostId');
            }
            if ($btn.data('aiContactId')) {
                payload.contact_id = $btn.data('aiContactId');
            }
            if ($btn.data('aiAuditId')) {
                payload.audit_id = $btn.data('aiAuditId');
            }
            if ($btn.data('aiChannel')) {
                payload.channel = $btn.data('aiChannel');
            }
            if ($btn.data('aiPurpose')) {
                payload.purpose = $btn.data('aiPurpose');
            }
            if ($btn.data('aiTrigger')) {
                payload.trigger = $btn.data('aiTrigger');
            }
            if ($btn.data('aiGoal')) {
                payload.goal = $btn.data('aiGoal');
            }
            if ($btn.data('aiRecommendation')) {
                payload.recommendation = $btn.data('aiRecommendation');
            }
            if ($btn.data('aiOpenai')) {
                payload.openai_score = $btn.data('aiOpenai');
            }
            if ($btn.data('aiGemini')) {
                payload.gemini_score = $btn.data('aiGemini');
            }
            if ($btn.data('aiTechnical')) {
                payload.technical_score = $btn.data('aiTechnical');
            }
            if ($btn.data('aiTopic')) {
                payload.topic = $('#' + $btn.data('aiTopic')).val();
            }
            if ($btn.data('aiLink')) {
                payload.link_url = $('#' + $btn.data('aiLink')).val();
            }
            if ($btn.data('aiPlatforms')) {
                var platforms = [];
                $($btn.data('aiPlatforms')).each(function () {
                    if (this.checked) {
                        platforms.push(this.value);
                    }
                });
                payload.platforms = platforms;
            }
            if ($btn.data('aiContext')) {
                try {
                    payload.context = JSON.parse($btn.attr('data-ai-context') || '{}');
                } catch (err) {
                    payload.context = {};
                }
            }

            $btn.prop('disabled', true);
            dgAiRequest(task, payload, $status).done(function (data) {
                var target = $btn.data('aiTarget');
                if (target) {
                    $(target).val(data.content || data.body || data.description || data.title || '').trigger('input');
                }
                if ($btn.data('aiTargetTitle') && data.title) {
                    $('#' + $btn.data('aiTargetTitle')).val(data.title).trigger('input');
                }
                if ($btn.data('aiTargetSubject') && data.subject) {
                    $('#' + $btn.data('aiTargetSubject')).val(data.subject);
                }
                if ($btn.data('aiTargetKeyword') && data.focus_keyword) {
                    $('#' + $btn.data('aiTargetKeyword')).val(data.focus_keyword);
                }
                if ($btn.data('aiTargetExcerpt') && data.excerpt) {
                    $('#' + $btn.data('aiTargetExcerpt')).val(data.excerpt);
                }
                if ($btn.data('aiApplySeo') || task === 'seo_optimize' || task === 'seo_suburb') {
                    if ($('#dg_audit_focus_keyword').length) {
                        $('#dg_audit_focus_keyword').val(data.focus_keyword || '');
                        $('#dg_audit_title').val(data.title || '').trigger('input');
                        $('#dg_audit_description').val(data.description || '').trigger('input');
                        $('#dg_audit_og_title').val(data.og_title || '');
                        $('#dg_audit_og_description').val(data.og_description || '');
                        if (data.robots) {
                            $('#dg_audit_robots').val(data.robots);
                        }
                    } else if ($btn.data('aiModal') || task === 'seo_optimize' || task === 'seo_suburb') {
                        var seoHtml = '<p><strong>Keyword:</strong> ' + esc(data.focus_keyword) + '</p>';
                        seoHtml += '<p><strong>Title:</strong> ' + esc(data.title) + '</p>';
                        seoHtml += '<p><strong>Description:</strong> ' + esc(data.description) + '</p>';
                        seoHtml += '<p><strong>Robots:</strong> ' + esc(data.robots) + '</p>';
                        if (data.rationale) {
                            seoHtml += '<p class="description">' + esc(data.rationale) + '</p>';
                        }
                        showModal($btn.data('aiModalTitle') || 'SEO suggestions', seoHtml);
                        return;
                    }
                }

                if ($btn.data('aiModal') || task === 'visibility_fix' || task === 'audit_executive_summary' || task === 'automation_suggest' || task === 'reports_narrative') {
                    var html = '';
                    if (data.executive_summary) {
                        html += '<p>' + esc(data.executive_summary) + '</p>';
                    }
                    if (data.narrative) {
                        html += '<p>' + esc(data.narrative).replace(/\n/g, '</p><p>') + '</p>';
                    }
                    if (data.content && task === 'visibility_fix') {
                        html += '<pre style="white-space:pre-wrap;background:#f6f7f7;padding:12px;border-radius:6px;">' + esc(data.content) + '</pre>';
                    }
                    if (data.apply_target) {
                        html += '<p><strong>Apply in:</strong> ' + esc(data.apply_target) + '</p>';
                    }
                    ['key_wins', 'priority_actions', 'highlights', 'priorities', 'steps'].forEach(function (key) {
                        if (data[key] && data[key].length) {
                            html += '<ul>';
                            data[key].forEach(function (item) {
                                if (typeof item === 'object') {
                                    html += '<li>' + esc(item.action || item.config_note || JSON.stringify(item)) + '</li>';
                                } else {
                                    html += '<li>' + esc(item) + '</li>';
                                }
                            });
                            html += '</ul>';
                        }
                    });
                    if (data.name && task === 'automation_suggest') {
                        html = '<p><strong>' + esc(data.name) + '</strong></p><p>' + esc(data.description) + '</p>' + html;
                    }
                    if (data.rationale) {
                        html += '<p class="description">' + esc(data.rationale) + '</p>';
                    }
                    showModal($btn.data('aiModalTitle') || 'AI suggestion', html);
                }

                if ($('#dg-ai-narrative-output').length && data.narrative) {
                    $('#dg-ai-narrative-output').html('<p>' + esc(data.narrative).replace(/\n/g, '</p><p>') + '</p>');
                }
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });
    }

    $(initBindings);
}(jQuery));
