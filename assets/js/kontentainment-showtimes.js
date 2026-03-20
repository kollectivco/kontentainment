jQuery(document).ready(function ($) {
    // Universal Tab Switcher for Movie and Cinema pages
    $('.ktn-st-date-btn, .ktn-date-tab-btn').on('click', function (e) {
        e.preventDefault();

        // Get target container (supports both single cinema and single movie structures)
        var container = $(this).closest('.ktn-media-showtimes-section, .ktn-cinema-page-wrapper, .ktn-modern-showtimes');
        
        // Update active tab state
        container.find('.ktn-st-date-btn, .ktn-date-tab-btn').removeClass('active');
        $(this).addClass('active');

        // Update active content panel
        var targetId = $(this).data('date-target');
        container.find('.ktn-st-date-group, .ktn-date-panel').removeClass('active');
        $('#' + targetId).addClass('active');
    });

    // Load More Cinemas Toggle (Per Date Group)
    $(document).on('click', '.ktn-st-load-more-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $container = $btn.closest('.ktn-st-cinemas-list');
        var $hiddenCinemas = $container.find('.ktn-cinema-card-hidden');
        var count = $btn.data('hidden-count');

        if ($btn.hasClass('active')) {
            $hiddenCinemas.hide();
            $btn.removeClass('active').find('.ktn-btn-text').text('+' + count + ' More Cinemas');
        } else {
            $hiddenCinemas.show();
            $btn.addClass('active').find('.ktn-btn-text').text('Show Less Cinemas');
        }
    });
});
