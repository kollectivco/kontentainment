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

    // Show More Times Toggle
    $('.ktn-show-more-times').on('click', function (e) {
        var $btn = $(this);
        var $container = $btn.closest('.ktn-cinema-card-times');
        var $hiddenChips = $container.find('.ktn-chip-hidden');
        var count = $btn.data('hidden-count');

        if ($btn.hasClass('active')) {
            $hiddenChips.hide();
            $btn.removeClass('active').text('+' + count + ' More Times');
        } else {
            $hiddenChips.css('display', 'inline-flex');
            $btn.addClass('active').text('Show Less');
        }
    });
});
