(function ($) {
    'use strict';

    if (typeof dgSocialPro === 'undefined') {
        return;
    }

    var $content = $('#dg_social_content');
    var $hint = $('#dg-social-char-hint');

    function updateCharHint() {
        var text = $content.val() || '';
        var len = text.length;
        var checked = [];
        $('input[name="platforms[]"]:checked').each(function () {
            checked.push($(this).val());
        });

        if (!checked.length) {
            $hint.text(len + ' characters — select platforms to see limits.');
            return;
        }

        var warnings = [];
        checked.forEach(function (key) {
            var max = dgSocialPro.limits[key] || 1000;
            if (len > max) {
                warnings.push(key + ' (' + max + ' max)');
            }
        });

        if (warnings.length) {
            $hint.text(len + ' chars — over limit for: ' + warnings.join(', ')).css('color', '#dc2626');
        } else {
            $hint.text(len + ' characters — within all selected platform limits.').css('color', '#646970');
        }
    }

    $content.on('input', updateCharHint);
    $('input[name="platforms[]"]').on('change', updateCharHint);
    updateCharHint();

    $('#dg-social-media-picker').on('click', function (e) {
        e.preventDefault();
        var frame = wp.media({
            title: 'Select image',
            button: { text: 'Use image' },
            multiple: false,
            library: { type: 'image' },
        });
        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#dg_social_media').val(attachment.url);
        });
        frame.open();
    });

    $('#dg-social-compose-form').on('submit', function () {
        var action = $(document.activeElement).val();
        if (action === 'schedule') {
            var sched = $('#dg_social_schedule').val();
            if (!sched) {
                alert('Pick a date and time to schedule this post.');
                return false;
            }
        }
        if (action === 'publish' || action === 'schedule') {
            var platforms = $('input[name="platforms[]"]:checked').length;
            if (!platforms) {
                alert('Select at least one connected platform.');
                return false;
            }
        }
    });
})(jQuery);
