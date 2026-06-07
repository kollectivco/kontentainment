(function($) {
    'use strict';

    $(function() {
        const root = $('#ktn-guides-root');
        let currentTab = 'movies';
        let xhr;

        // Tab Switching
        $('.ktn-tab-btn').on('click', function() {
            const tab = $(this).data('tab');
            if (tab === currentTab) return;

            $('.ktn-tab-btn').removeClass('active');
            $(this).addClass('active');

            $('.ktn-tab-panel').removeClass('active');
            $('#tab-' + tab).addClass('active');

            currentTab = tab;
            applyFilters();
        });

        // Filter Handlers
        $('.ktn-sub-tab').on('click', function() {
            $(this).siblings().removeClass('active');
            $(this).addClass('active');
            applyFilters();
        });

        $('.ktn-select').on('change', function() {
            const id = $(this).attr('id');
            if (id === 'cinema-city') {
                updateAreas($(this).val(), '#cinema-area');
            } else if (id === 'movie-city') {
                updateAreas($(this).val(), '#movie-area');
            } else {
                applyFilters();
            }
        });

        let searchTimer;
        $('#movie-search, #cinema-search').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyFilters, 300);
        });

        function updateAreas(citySlug, targetAreaSelectId) {
            const areaSelect = $(targetAreaSelectId);
            const isRtl = ($('html').attr('dir') === 'rtl' || $('html').attr('lang') === 'ar');
            
            if (!citySlug) {
                const selectAreaText = isRtl ? 'اختر المنطقة' : 'Select Area';
                areaSelect.html('<option value="">' + selectAreaText + '</option>').prop('disabled', true);
                applyFilters();
                return;
            }

            $.ajax({
                url: ktn_guides.ajax_url,
                type: 'POST',
                data: {
                    action: 'ktn_get_child_locations',
                    parent_slug: citySlug,
                    nonce: ktn_guides.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const allAreasText = isRtl ? 'كل المناطق' : 'All Areas';
                        let html = '<option value="">' + allAreasText + '</option>';
                        $.each(response.data, function(i, area) {
                            html += '<option value="' + area.slug + '">' + area.name + '</option>';
                        });
                        areaSelect.html(html).prop('disabled', false);
                    }
                    applyFilters();
                }
            });
        }

        function applyFilters() {
            const currentPanel = $('#tab-' + currentTab);
            let loader = currentPanel.find('.ktn-guides-loader');
            
            if (loader.length === 0) {
                loader = $('<div class="ktn-guides-loader"><div class="ktn-spinner"></div></div>');
                currentPanel.append(loader);
            }
            
            loader.fadeIn(150);

            if (xhr && xhr.readyState !== 4) {
                xhr.abort();
            }

            const data = {
                action: 'ktn_filter_guides',
                tab: currentTab,
                nonce: ktn_guides.nonce
            };

            if (currentTab === 'movies') {
                data.lang = $('.ktn-sub-tab.active').data('lang');
                data.search = $('#movie-search').val();
                data.genre = $('#movie-genre').val();
                data.city = $('#movie-city').val();
                data.area = $('#movie-area').val();
            } else {
                data.search = $('#cinema-search').val();
                data.city = $('#cinema-city').val();
                data.area = $('#cinema-area').val();
            }

            xhr = $.ajax({
                url: ktn_guides.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    loader.fadeOut(150);
                    if (response.success) {
                        const target = currentTab === 'movies' ? '#movie-results' : '#cinema-results';
                        $(target).html(response.data.html);
                    }
                },
                error: function(x, t, m) {
                    if (t !== 'abort') {
                        loader.fadeOut(150);
                    }
                }
            });
        }
    });

    // ============================================================
    // FULL-WIDTH ENFORCER
    // Walk up the DOM tree from our container and force every
    // parent element to be full-width. This is the nuclear option
    // that works regardless of theme class names.
    // ============================================================
    function enforceFullWidth() {
        var el = document.getElementById('ktn-guides-root');
        if (!el) return;

        var node = el.parentElement;
        var depth = 0;

        while (node && node !== document.body && depth < 15) {
            var tag = node.tagName.toLowerCase();
            // Skip html/body - we only want content wrappers
            if (tag !== 'html' && tag !== 'body') {
                var computed = window.getComputedStyle(node);
                var mw = parseInt(computed.maxWidth, 10);
                var w  = parseInt(computed.width, 10);

                // If this element has a max-width less than full viewport, break it open
                if ((mw > 0 && mw < window.innerWidth - 20) ||
                    (w  > 0 && w  < window.innerWidth - 20)) {
                    node.style.setProperty('max-width', '100%', 'important');
                    node.style.setProperty('width',     '100%', 'important');
                    node.style.setProperty('padding-left',  '0', 'important');
                    node.style.setProperty('padding-right', '0', 'important');
                    node.style.setProperty('margin-left',   '0', 'important');
                    node.style.setProperty('margin-right',  '0', 'important');
                    node.style.setProperty('float', 'none', 'important');
                }
            }
            node = node.parentElement;
            depth++;
        }

        // Restore padding on our own container
        el.style.setProperty('padding-left',  '20px', 'important');
        el.style.setProperty('padding-right', '20px', 'important');
    }

    // Run immediately and after DOM is ready
    enforceFullWidth();
    $(document).ready(function () { enforceFullWidth(); });
    $(window).on('load', function () { enforceFullWidth(); });

})(jQuery);
