/**
 * Cinema Guides Frontend Interactions
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        const root = $('#ktn-guides-root');
        if (!root.length) return;

        let activeTab = 'movies';
        let debounceTimer;

        // Tab Switching
        $('.ktn-tab-btn').on('click', function() {
            const tab = $(this).data('tab');
            $('.ktn-tab-btn').removeClass('active');
            $(this).addClass('active');

            $('.ktn-tab-panel').removeClass('active');
            $(`#tab-${tab}`).addClass('active');

            activeTab = tab;
            // No need to reload results immediately if they were already there, 
            // but we can if we want to ensure fresh state.
        });

        // Movie Lang Sub-tabs
        $('.ktn-sub-tab').on('click', function() {
            $('.ktn-sub-tab').removeClass('active');
            $(this).addClass('active');
            triggerFilter();
        });

        // Search & Dropdown changes
        $('#movie-search, #cinema-search').on('keyup', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                triggerFilter();
            }, 500);
        });

        $('#movie-genre, #cinema-city, #cinema-area').on('change', function() {
            triggerFilter();
        });

        function triggerFilter() {
            const loader = $('.ktn-guides-loader');
            loader.fadeIn(200);

            const data = {
                action: 'ktn_filter_guides',
                nonce: ktn_guides.nonce,
                tab: activeTab
            };

            if (activeTab === 'movies') {
                data.search = $('#movie-search').val();
                data.lang = $('.ktn-sub-tab.active').data('lang');
                data.genre = $('#movie-genre').val();
            } else {
                data.search = $('#cinema-search').val();
                data.city = $('#cinema-city').val();
                data.area = $('#cinema-area').val();
            }

            $.post(ktn_guides.ajax_url, data, function(response) {
                if (response.success) {
                    const resultsId = activeTab === 'movies' ? '#movie-results' : '#cinema-results';
                    $(resultsId).html(response.data.html);
                }
                loader.fadeOut(200);
            }).fail(function() {
                loader.fadeOut(200);
            });
        }
    });

})(jQuery);
