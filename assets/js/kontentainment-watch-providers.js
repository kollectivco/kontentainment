/**
 * Kontentainment Watch Providers Interactivity
 */

jQuery(document).ready(function($) {
    $('.ktn-wp-select').on('change', function() {
        const region = $(this).val();
        const $container = $(this).closest('.ktn-watch-providers-section');
        
        $container.find('.ktn-wp-region-panel').removeClass('active');
        $container.find('.ktn-wp-region-panel[data-region="' + region + '"]').addClass('active');
    });
});
