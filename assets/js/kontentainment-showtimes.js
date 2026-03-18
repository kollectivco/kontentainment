jQuery(document).ready(function ($) {
    if ($('.ktn-st-dates').length) {
        $('.ktn-st-date-btn').on('click', function (e) {
            e.preventDefault();

            // Handle active state on tabs
            var parent = $(this).closest('.ktn-modern-showtimes, .ktn-cinema-page');
            parent.find('.ktn-st-date-btn').removeClass('active');
            $(this).addClass('active');

            // Handle content display
            var targetId = $(this).data('date-target');
            parent.find('.ktn-st-date-group').removeClass('active');
            parent.find('#' + targetId).addClass('active');
        });
    }
});
