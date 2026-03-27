(function($) {
    $(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/ktn-movies-mobile-widget.default', function($scope) {
            const $wrapper = $scope.find('.ktn-mobile-slider-wrapper');
            const $container = $scope.find('.ktn-mobile-swiper');
            const settings = $wrapper.data('settings');

            if (!$container.length || !settings) return;

            // Stable Swiper Config (Strictly NO Pagination)
            const swiperOptions = {
                slidesPerView: settings.slidesPerView || 2.1,
                spaceBetween: settings.spaceBetween || 14,
                loop: !!settings.loop,
                speed: settings.speed || 600,
                centeredSlides: !!settings.centeredSlides,
                observer: true,
                observeParents: true,
                watchOverflow: true,
                grabCursor: true,
                threshold: 5,
                touchStartPreventDefault: false,
                autoplay: settings.autoplay ? {
                    delay: settings.autoplay.delay || 4000,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: false
                } : false
            };

            // Initialize using Elementor's swiper utility
            if (elementorFrontend.utils && elementorFrontend.utils.swiper) {
                new elementorFrontend.utils.swiper($container[0], swiperOptions).then(function(swiperInstance) {
                    // Force update on window resize (WebView stability)
                    $(window).on('resize', function() {
                        if (swiperInstance && swiperInstance.update) {
                            swiperInstance.update();
                        }
                    });
                });
            } else if (typeof Swiper !== 'undefined') {
                const swiperInstance = new Swiper($container[0], swiperOptions);
                $(window).on('resize', function() {
                    if (swiperInstance && swiperInstance.update) {
                        swiperInstance.update();
                    }
                });
            }
        });
    });
})(jQuery);
