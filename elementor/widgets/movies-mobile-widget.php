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
        // CONTENT TAB - QUERY
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

        $this->add_control('posts_per_page', [
            'label' => esc_html__('Number of Movies', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 8,
            'min' => 1,
            'max' => 50,
            'condition' => ['source!' => 'manual'],
        ]);

        $this->add_control('manual_ids', [
            'label' => esc_html__('Manual Movie IDs (comma separated)', 'kontentainment'),
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

        $this->end_controls_section();

        // CONTENT TAB - BEHAVIOR
        $this->start_controls_section('section_behavior', [
            'label' => esc_html__('Behavior', 'kontentainment'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('enable_link', [
            'label' => esc_html__('Enable Card Link', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('open_new_tab', [
            'label' => esc_html__('Open in New Tab', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'no',
            'condition' => ['enable_link' => 'yes'],
        ]);

        $this->end_controls_section();

        // STYLE TAB - SLIDER
        $this->start_controls_section('section_layout_slider', [
            'label' => esc_html__('Slider Behavior', 'kontentainment'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('slides_per_view', [
            'label' => esc_html__('Slides per View', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 2.1,
            'min' => 1,
            'max' => 5,
            'step' => 0.05,
        ]);

        $this->add_control('space_between', [
            'label' => esc_html__('Space Between (px)', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 14,
        ]);

        $this->add_control('loop', [
            'label' => esc_html__('Loop', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'no',
        ]);

        $this->add_control('autoplay', [
            'label' => esc_html__('Autoplay', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('autoplay_delay', [
            'label' => esc_html__('Autoplay Delay (ms)', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 4000,
            'condition' => ['autoplay' => 'yes'],
        ]);

        $this->add_control('transition_speed', [
            'label' => esc_html__('Transition Speed (ms)', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 600,
        ]);

        $this->add_control('centered_slides', [
            'label' => esc_html__('Centered Slides', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'no',
        ]);

        $this->end_controls_section();

        // STYLE TAB - CARD
        $this->start_controls_section('section_layout_card', [
            'label' => esc_html__('Card & Overlay', 'kontentainment'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('card_radius_dim', [
            'label' => esc_html__('Card Border Radius', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'default' => [
                'top' => '24',
                'right' => '24',
                'bottom' => '24',
                'left' => '24',
                'unit' => 'px',
                'isLinked' => true,
            ],
            'selectors' => [
                '{{WRAPPER}} .ktn-mobile-movie-card, {{WRAPPER}} .ktn-mobile-card-poster' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('overlay_strength', [
            'label' => esc_html__('Overlay Strength', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'range' => [
                'px' => ['min' => 0, 'max' => 1, 'step' => 0.1],
            ],
            'default' => ['size' => 0.9],
            'selectors' => [
                '{{WRAPPER}} .ktn-mobile-card-overlay' => 'background: linear-gradient(to top, rgba(0,0,0,{{SIZE}}) 0%, rgba(0,0,0,calc({{SIZE}} / 2)) 70%, transparent 100%);',
            ],
        ]);

        $this->add_control('title_alignment', [
            'label' => esc_html__('Title Alignment', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::CHOOSE,
            'options' => [
                'flex-start' => [
                    'title' => esc_html__('Left', 'kontentainment'),
                    'icon' => 'eicon-text-align-left',
                ],
                'center' => [
                    'title' => esc_html__('Center', 'kontentainment'),
                    'icon' => 'eicon-text-align-center',
                ],
            ],
            'default' => 'flex-start',
            'selectors' => [
                '{{WRAPPER}} .ktn-mobile-card-overlay' => 'align-items: {{VALUE}};',
                '{{WRAPPER}} .ktn-mobile-card-title' => 'text-align: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        // STYLE TAB - TITLE
        $this->start_controls_section('section_style_title', [
            'label' => esc_html__('Title Style', 'kontentainment'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('title_color', [
            'label' => esc_html__('Color', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => [
                '{{WRAPPER}} .ktn-mobile-card-title' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
            'name' => 'title_typography',
            'selector' => '{{WRAPPER}} .ktn-mobile-card-title',
        ]);

        $this->add_group_control(\Elementor\Group_Control_Text_Shadow::get_type(), [
            'name' => 'title_shadow',
            'selector' => '{{WRAPPER}} .ktn-mobile-card-title',
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        $args = [
            'post_type' => 'movie',
            'post_status' => 'publish',
            'posts_per_page' => (int) $settings['posts_per_page'],
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
            'slidesPerView' => (float) $settings['slides_per_view'],
            'spaceBetween'  => (int) $settings['space_between'],
            'loop'          => ($settings['loop'] === 'yes') ? true : false,
            'speed'         => (int) $settings['transition_speed'],
            'centeredSlides'=> ($settings['centered_slides'] === 'yes'),
            'autoplay'      => ($settings['autoplay'] === 'yes') ? [
                'delay' => (int) $settings['autoplay_delay'],
                'disableOnInteraction' => false,
                'pauseOnMouseEnter' => false
            ] : false,
            'observer' => true,
            'observeParents' => true,
            'watchOverflow' => true,
        ];

        echo '<div class="ktn-mobile-slider-wrapper ktn-movies-mobile-slider" data-settings=\'' . json_encode($slider_options) . '\'>';
        if ($query->have_posts()) {
            echo '<div class="ktn-mobile-swiper swiper">';
            echo '<div class="swiper-wrapper">';
            while ($query->have_posts()) {
                $query->the_post();
                echo '<div class="swiper-slide">';
                $this->render_mobile_card(get_the_ID(), $settings);
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p class="ktn-mobile-empty">' . esc_html__('No movies available.', 'kontentainment') . '</p>';
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

        $target = ($settings['open_new_tab'] === 'yes') ? '_blank' : '_self';

        ?>
        <div class="ktn-mobile-movie-card ktn-premium-poster-card">
            <?php if ($settings['enable_link'] === 'yes'): ?>
                <a href="<?php echo esc_url($permalink); ?>" target="<?php echo $target; ?>" class="ktn-mobile-card-link">
            <?php else: ?>
                <div class="ktn-mobile-card-link">
            <?php endif; ?>

                <div class="ktn-mobile-card-poster" style="background-image: url('<?php echo esc_url($poster_url); ?>');">
                    <img src="<?php echo esc_url($poster_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                    <div class="ktn-mobile-card-overlay">
                        <h4 class="ktn-mobile-card-title"><?php echo esc_html($title); ?></h4>
                    </div>
                </div>

            <?php if ($settings['enable_link'] === 'yes'): ?>
                </a>
            <?php else: ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
