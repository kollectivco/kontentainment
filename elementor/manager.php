<?php
if (!defined('ABSPATH')) {
    exit;
}

final class KTN_Elementor_Manager {

    private static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_action('elementor/frontend/after_enqueue_styles', [$this, 'enqueue_styles']);
        add_action('elementor/elements/categories_registered', [$this, 'add_elementor_widget_categories']);
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
    }

    public function enqueue_styles() {
        wp_enqueue_style('ktn-elementor-widgets', KTN_PLUGIN_URL . 'elementor/assets/css/elementor-widgets.css', [], KTN_PLUGIN_VERSION);
    }

    public function add_elementor_widget_categories($elements_manager) {
        $elements_manager->add_category(
            'kontentainment-widgets',
            [
                'title' => esc_html__('Movies & Cinemas', 'kontentainment'),
                'icon' => 'fa fa-film',
            ]
        );
    }

    public function register_widgets($widgets_manager) {
        require_once KTN_PLUGIN_DIR . 'elementor/widgets/movies-widget.php';
        require_once KTN_PLUGIN_DIR . 'elementor/widgets/cinemas-widget.php';
        require_once KTN_PLUGIN_DIR . 'elementor/widgets/showtimes-widget.php';
        require_once KTN_PLUGIN_DIR . 'elementor/widgets/areas-widget.php';
        require_once KTN_PLUGIN_DIR . 'elementor/widgets/movie-single-widget.php';
        require_once KTN_PLUGIN_DIR . 'elementor/widgets/cinema-single-widget.php';
        require_once KTN_PLUGIN_DIR . 'elementor/widgets/watch-providers-widget.php';

        $widgets_manager->register(new \KTN_Movies_Widget());
        $widgets_manager->register(new \KTN_Cinemas_Widget());
        $widgets_manager->register(new \KTN_Showtimes_Widget());
        $widgets_manager->register(new \KTN_Areas_Widget());
        $widgets_manager->register(new \KTN_Movie_Single_Widget());
        $widgets_manager->register(new \KTN_Cinema_Single_Widget());
        $widgets_manager->register(new \KTN_Watch_Providers_Widget());
    }
}

// Initialize the Elementor integration
add_action('plugins_loaded', function() {
    if (did_action('elementor/loaded')) {
        KTN_Elementor_Manager::instance();
    }
});
