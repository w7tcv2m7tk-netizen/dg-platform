(function ($) {
    'use strict';

    var cfg = window.dgSeoMetaBox || {};
    var titleMax = 60;
    var descMax = 160;

    function countClass(len, max) {
        if (len > max) {
            return 'dg-seo-count-over';
        }
        if (len >= max * 0.85) {
            return 'dg-seo-count-warn';
        }
        return 'dg-seo-count-ok';
    }

    function updateCounts() {
        var title = $('#dg_seo_title').val() || cfg.fallbackTitle || '';
        var desc = $('#dg_seo_description').val() || cfg.fallbackDescription || '';

        $('#dg-seo-title-count')
            .text(title.length + ' / ' + titleMax)
            .attr('class', 'dg-seo-char-count ' + countClass(title.length, titleMax));

        $('#dg-seo-desc-count')
            .text(desc.length + ' / ' + descMax)
            .attr('class', 'dg-seo-char-count ' + countClass(desc.length, descMax));
    }

    function updatePreview() {
        var title = $('#dg_seo_title').val() || cfg.fallbackTitle || cfg.postTitle || '';
        var desc = $('#dg_seo_description').val() || cfg.fallbackDescription || '';

        $('#dg-seo-preview-title').text(title);
        $('#dg-seo-preview-desc').text(desc);
        $('#dg-seo-preview-url').text(cfg.permalink || '');
    }

    function initTabs() {
        $('.dg-seo-tab').on('click', function (e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            $('.dg-seo-tab').removeClass('is-active');
            $(this).addClass('is-active');
            $('.dg-seo-panel').removeClass('is-active');
            $('.dg-seo-panel[data-panel="' + tab + '"]').addClass('is-active');
        });
    }

    $(function () {
        if (!$('#dg-seo-meta-box').length) {
            return;
        }
        initTabs();
        updateCounts();
        updatePreview();
        $('#dg_seo_title, #dg_seo_description').on('input', function () {
            updateCounts();
            updatePreview();
        });
    });
}(jQuery));
