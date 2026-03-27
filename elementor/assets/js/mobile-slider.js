jQuery(window).on('elementor/frontend/init', function () {
    elementorFrontend.hooks.addAction('frontend/element_ready/ktn-movies-mobile-widget.default', function ($scope) {
        const $wrapper = $scope.find('.ktn-mobile-slider-wrapper');
        const $container = $scope.find('.ktn-mobile-swiper'); // Changed from .swiper-container to match original
        const settings = $wrapper.data('settings');

        if (!$container.length || !settings) return;

        // Stable Swiper Config
        const swiperOptions = {
            slidesPerView: settings.slidesPerView || 2.2,
            spaceBetween: settings.spaceBetween || 14,
            loop: !!settings.loop,
            speed: settings.speed || 500,
            centeredSlides: !!settings.centeredSlides,
            freeMode: !!settings.freeMode,
            observer: true,
            observeParents: true,
            watchOverflow: true,
            grabCursor: true,
            threshold: 5,
            touchStartPreventDefault: false, // Better for WebView scrolling
            pagination: settings.pagination ? {
                el: settings.pagination.el,
                clickable: true
            } : false,
            autoplay: settings.autoplay ? {
                delay: settings.autoplay.delay || 3000,
                disableOnInteraction: false,
                pauseOnMouseEnter: false
            } : false
        };

        // Elementor provides Swiper as a global or via elementorFrontend.utils.swiper
        if (typeof Swiper !== 'undefined') {
            const swiperInstance = new Swiper($container[0], swiperOptions);
            // Re-init on window resize to ensure stability in WebViews
            jQuery(window).on('resize', function() {
                if (swiperInstance && swiperInstance.update) {
                    swiperInstance.update();
                }
            });
        } else if (elementorFrontend.utils && elementorFrontend.utils.swiper) {
            new elementorFrontend.utils.swiper($container[0], swiperOptions).then(function (swiperInstance) {
                // Swiper initialized
                // Re-init on window resize to ensure stability in WebViews
                jQuery(window).on('resize', function() {
                    if (swiperInstance && swiperInstance.update) {
                        swiperInstance.update();
                    }
                });
            });
        }
    });
});
