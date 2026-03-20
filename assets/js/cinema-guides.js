(function($) {
    'use strict';

    $(function() {
        const root = $('#ktn-guides-root');
        const loader = root.find('.ktn-guides-loader');
        let currentTab = 'movies';

        // Tab Switching
        $('.ktn-tab-btn').on('click', function() {
            const tab = $(this).data('tab');
            if (tab === currentTab) return;

            $('.ktn-tab-btn').removeClass('active');
            $(this).addClass('active');

            $('.ktn-tab-panel').removeClass('active');
            $('#tab-' + tab).addClass('active');

            currentTab = tab;
        });

        // Filter Handlers
        $('.ktn-sub-tab').on('click', function() {
            $(this).siblings().removeClass('active');
            $(this).addClass('active');
            applyFilters();
        });

        $('.ktn-select').on('change', function() {
            if ($(this).attr('id') === 'cinema-city') {
                updateAreas($(this).val());
            } else {
                applyFilters();
            }
        });

        let searchTimer;
        $('#movie-search, #cinema-search').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyFilters, 500);
        });

        function updateAreas(citySlug) {
            const areaSelect = $('#cinema-area');
            if (!citySlug) {
                areaSelect.html('<option value="">Select Area</option>').prop('disabled', true);
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
                        let html = '<option value="">All Areas</option>';
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
            loader.fadeIn(100);

            const data = {
                action: 'ktn_filter_guides',
                tab: currentTab,
                nonce: ktn_guides.nonce
            };

            if (currentTab === 'movies') {
                data.lang = $('.ktn-sub-tab.active').data('lang');
                data.search = $('#movie-search').val();
                data.genre = $('#movie-genre').val();
            } else {
                data.search = $('#cinema-search').val();
                data.city = $('#cinema-city').val();
                data.area = $('#cinema-area').val();
            }

            $.ajax({
                url: ktn_guides.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    loader.fadeOut(100);
                    if (response.success) {
                        const target = currentTab === 'movies' ? '#movie-results' : '#cinema-results';
                        $(target).html(response.data.html);
                    }
                }
            });
        }
    });

})(jQuery);
