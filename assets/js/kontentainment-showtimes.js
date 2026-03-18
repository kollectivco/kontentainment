jQuery(document).ready(function ($) {
    if ($('.ktn-st-dates, .ktn-date-tabs-scroll').length) {
        $('.ktn-st-date-btn, .ktn-date-tab-btn').on('click', function (e) {
            e.preventDefault();

            // Handle active state on tabs
            var parent = $(this).closest('.ktn-modern-showtimes, .ktn-cinema-page, .ktn-cinema-page-wrapper');
            parent.find('.ktn-st-date-btn, .ktn-date-tab-btn').removeClass('active');
            $(this).addClass('active');

            // Handle content display
            var targetId = $(this).data('date-target');
            parent.find('.ktn-st-date-group, .ktn-date-panel').removeClass('active');
            parent.find('#' + targetId).addClass('active');
        });
    }
});
