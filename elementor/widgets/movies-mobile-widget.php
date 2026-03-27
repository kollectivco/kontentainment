<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once KTN_PLUGIN_DIR . 'elementor/base-widget.php';

class KTN_Movies_Mobile_Widget extends KTN_Elementor_Base_Widget {

    public function get_name() {
        return 'ktn-movies-mobile-widget';
    }

    public function get_title() {
        return esc_html__('KTN Movies Mobile', 'kontentainment');
    }

    public function get_icon() {
        return 'eicon-mobile';
    }

    public function get_script_depends() {
        return ['ktn-mobile-slider'];
    }

    protected function register_controls() {
        $this->start_controls_section('section_query', [
            'label' => esc_html__('Query', 'kontentainment'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('source', [
            'label' => esc_html__('Source', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'latest',
            'options' => [
                'latest'      => esc_html__('Latest Movies', 'kontentainment'),
                'now_playing' => esc_html__('Now Playing', 'kontentainment'),
                'coming_soon' => esc_html__('Coming Soon', 'kontentainment'),
                'manual'      => esc_html__('Manual Selection', 'kontentainment'),
                'by_area'     => esc_html__('Movies by Area', 'kontentainment'),
                'by_cinema'   => esc_html__('Movies by Cinema', 'kontentainment'),
            ],
        ]);

        $this->add_control('manual_ids', [
            'label' => esc_html__('Movie IDs (comma separated)', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'condition' => ['source' => 'manual'],
        ]);

        // Areas dropdown
        $areas = get_terms(['taxonomy' => 'cinema_area', 'hide_empty' => false]);
        $area_options = [];
        if (!is_wp_error($areas) && !empty($areas)) {
            foreach ($areas as $area) {
                $area_options[$area->slug] = $area->name;
            }
        }
        $this->add_control('area_slug', [
            'label' => esc_html__('Select Area', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $area_options,
            'condition' => ['source' => 'by_area'],
        ]);

        // Cinema dropdown
        $cinemas = get_posts(['post_type' => 'ktn_cinema', 'posts_per_page' => -1]);
        $cinema_options = [];
        if (!empty($cinemas)) {
            foreach ($cinemas as $cinema) {
                $cinema_options[$cinema->ID] = $cinema->post_title;
            }
        }
        $this->add_control('cinema_id', [
            'label' => esc_html__('Select Cinema', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $cinema_options,
            'condition' => ['source' => 'by_cinema'],
        ]);

        $this->add_control('posts_per_page', [
            'label' => esc_html__('Movies Count', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 10,
            'condition' => ['source!' => 'manual'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => esc_html__('Card & Slider Style', 'kontentainment'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('slides_per_view', [
            'label' => esc_html__('Slides per View', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 2.2,
            'min' => 1,
            'max' => 5,
            'step' => 0.1,
        ]);

        $this->add_control('gap', [
            'label' => esc_html__('Gap (px)', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 14,
        ]);

        $this->add_control('poster_ratio', [
            'label' => esc_html__('Poster Aspect Ratio', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => '2/3',
            'options' => [
                '2/3' => 'Tall (2:3)',
                '3/4' => 'Standard (3:4)',
                '1/1' => 'Square (1:1)',
                '16/9' => 'Wide (16:9)',
            ],
            'selectors' => [
                '{{WRAPPER}} .ktn-mobile-card-poster' => 'aspect-ratio: {{VALUE}};',
            ],
        ]);

        $this->add_control('card_radius', [
            'label' => esc_html__('Card Border Radius', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem', '%'],
            'range' => [
                'px' => ['min' => 0, 'max' => 50],
            ],
            'default' => [
                'unit' => 'px',
                'size' => 20,
            ],
            'selectors' => [
                '{{WRAPPER}} .ktn-mobile-movie-card' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('line_clamp', [
            'label' => esc_html__('Title Line Clamp', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 2,
            'min' => 1,
            'max' => 3,
            'selectors' => [
                '{{WRAPPER}} .ktn-mobile-card-title' => '-webkit-line-clamp: {{VALUE}};',
            ],
        ]);

        $this->add_control('autoplay', [
            'label' => esc_html__('Autoplay', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'no',
        ]);

        $this->add_control('loop', [
            'label' => esc_html__('Loop', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'no',
        ]);

        $this->add_control('dots', [
            'label' => esc_html__('Show Dots', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'no',
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        $args = [
            'post_type' => 'movie',
            'post_status' => 'publish',
            'posts_per_page' => $settings['posts_per_page'] ? $settings['posts_per_page'] : 10,
        ];

        global $wpdb;
        $today = date('Y-m-d');

        switch ($settings['source']) {
            case 'now_playing':
                $now_playing_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT matched_movie_id FROM {$wpdb->prefix}ktn_showtimes WHERE matched_movie_id IS NOT NULL AND (show_date >= %s OR show_date = 'Today')",
                    $today
                ));
                $args['post__in'] = !empty($now_playing_ids) ? $now_playing_ids : [0];
                break;

            case 'coming_soon':
                $now_playing_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT matched_movie_id FROM {$wpdb->prefix}ktn_showtimes WHERE matched_movie_id IS NOT NULL AND (show_date >= %s OR show_date = 'Today')",
                    $today
                ));
                if (!empty($now_playing_ids)) {
                    $args['post__not_in'] = $now_playing_ids;
                }
                $args['meta_query'] = [
                    'relation' => 'OR',
                    ['key' => '_movie_release_date', 'value' => $today, 'compare' => '>', 'type' => 'DATE'],
                    ['key' => '_movie_status', 'value' => 'Upcoming', 'compare' => '=']
                ];
                $args['orderby'] = 'meta_value';
                $args['meta_key'] = '_movie_release_date';
                $args['order'] = 'ASC';
                break;

            case 'manual':
                if (!empty($settings['manual_ids'])) {
                    $args['post__in'] = array_map('intval', explode(',', $settings['manual_ids']));
                    $args['orderby'] = 'post__in';
                    $args['posts_per_page'] = -1;
                }
                break;

            case 'by_area':
                if (!empty($settings['area_slug'])) {
                    $cinemas = get_posts([
                        'post_type' => 'ktn_cinema',
                        'posts_per_page' => -1,
                        'fields' => 'ids',
                        'tax_query' => [[
                            'taxonomy' => 'cinema_area',
                            'field' => 'slug',
                            'terms' => $settings['area_slug']
                        ]]
                    ]);
                    if (!empty($cinemas)) {
                        $ids_str = implode(',', array_map('intval', $cinemas));
                        $area_movie_ids = $wpdb->get_col($wpdb->prepare(
                            "SELECT DISTINCT matched_movie_id FROM {$wpdb->prefix}ktn_showtimes WHERE matched_movie_id IS NOT NULL AND cinema_id IN ($ids_str) AND (show_date >= %s OR show_date = 'Today')",
                            $today
                        ));
                        $args['post__in'] = !empty($area_movie_ids) ? $area_movie_ids : [0];
                    } else {
                        $args['post__in'] = [0];
                    }
                }
                break;

            case 'by_cinema':
                if (!empty($settings['cinema_id'])) {
                    $cinema_movie_ids = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT matched_movie_id FROM {$wpdb->prefix}ktn_showtimes WHERE matched_movie_id IS NOT NULL AND cinema_id = %d AND (show_date >= %s OR show_date = 'Today')",
                        $settings['cinema_id'],
                        $today
                    ));
                    $args['post__in'] = !empty($cinema_movie_ids) ? $cinema_movie_ids : [0];
                }
                break;
        }

        $query = new \WP_Query($args);

        $slider_options = [
            'slidesPerView' => $settings['slides_per_view'],
            'spaceBetween'  => $settings['gap'],
            'loop'          => ($settings['loop'] === 'yes'),
            'autoplay'      => ($settings['autoplay'] === 'yes') ? ['delay' => 3000] : false,
            'pagination'    => ($settings['dots'] === 'yes') ? ['el' => '.swiper-pagination', 'clickable' => true] : false,
        ];

        echo '<div class="ktn-mobile-slider-wrapper ktn-movies-mobile-slider" data-settings=\'' . json_encode($slider_options) . '\'>';
        if ($query->have_posts()) {
            echo '<div class="swiper-container ktn-mobile-swiper">';
            echo '<div class="swiper-wrapper">';
            while ($query->have_posts()) {
                $query->the_post();
                echo '<div class="swiper-slide">';
                $this->render_mobile_card(get_the_ID(), $settings);
                echo '</div>';
            }
            echo '</div>';
            if ($settings['dots'] === 'yes') {
                echo '<div class="swiper-pagination"></div>';
            }
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p class="ktn-mobile-empty">' . esc_html__('No movies found.', 'kontentainment') . '</p>';
        }
        echo '</div>';
    }

    private function render_mobile_card($post_id, $settings) {
        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);
        $poster_url = has_post_thumbnail($post_id) ? get_the_post_thumbnail_url($post_id, 'large') : '';
        
        if (!$poster_url) {
            $poster_path = get_post_meta($post_id, '_movie_poster_path', true);
            $poster_url = $poster_path ? "https://image.tmdb.org/t/p/w500" . $poster_path : KTN_PLUGIN_URL . 'assets/img/no-poster.jpg';
        }

        ?>
        <div class="ktn-mobile-movie-card ktn-premium-poster-card">
            <a href="<?php echo esc_url($permalink); ?>" class="ktn-mobile-card-link">
                <div class="ktn-mobile-card-poster">
                    <img src="<?php echo esc_url($poster_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                    <div class="ktn-mobile-card-overlay">
                        <h4 class="ktn-mobile-card-title"><?php echo esc_html($title); ?></h4>
                    </div>
                </div>
            </a>
        </div>
        <?php
    }
}
