jQuery(window).on('elementor/frontend/init', function () {
    elementorFrontend.hooks.addAction('frontend/element_ready/ktn-movies-mobile-widget.default', function ($scope) {
        var $slider = $scope.find('.ktn-mobile-swiper');
        if (!$slider.length) return;

        var settings = $scope.find('.ktn-mobile-slider-wrapper').data('settings');
        
        // Elementor provides Swiper as a global or via elementorFrontend.utils.swiper
        if (typeof Swiper !== 'undefined') {
            new Swiper($slider[0], settings);
        } else if (elementorFrontend.utils && elementorFrontend.utils.swiper) {
            new elementorFrontend.utils.swiper($slider[0], settings).then(function (swiperInstance) {
                // Swiper initialized
            });
        }
    });
});
