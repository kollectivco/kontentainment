<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once KTN_PLUGIN_DIR . 'elementor/base-widget.php';

class KTN_Movies_Widget extends KTN_Elementor_Base_Widget {

    public function get_name() {
        return 'ktn-movies-widget';
    }

    public function get_title() {
        return esc_html__('Kontentainment Movies', 'kontentainment');
    }

    public function get_icon() {
        return 'eicon-play';
    }

    protected function register_controls() {
        $this->start_controls_section('section_query', [
            'label' => esc_html__('Query & Layout', 'kontentainment'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('source', [
            'label' => esc_html__('Source', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'now_playing',
            'options' => [
                'now_playing' => esc_html__('Now Playing (Cinemas)', 'kontentainment'),
                'coming_soon' => esc_html__('Coming Soon', 'kontentainment'),
                'latest_movies' => esc_html__('Latest Added (All)', 'kontentainment'),
                'manual' => esc_html__('Manual Selection', 'kontentainment'),
                'area' => esc_html__('By Area', 'kontentainment'),
                'cinema' => esc_html__('By Cinema', 'kontentainment'),
            ],
        ]);

        $this->add_control('manual_ids', [
            'label' => esc_html__('Movie IDs (comma separated)', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'condition' => ['source' => 'manual'],
        ]);

        // Area dropdown
        $areas_query = get_terms(['taxonomy' => 'cinema_area', 'hide_empty' => false]);
        $area_options = [];
        if (!is_wp_error($areas_query) && !empty($areas_query)) {
            foreach ($areas_query as $a) {
                $area_options[$a->slug] = $a->name;
            }
        }
        $this->add_control('area_slug', [
            'label' => esc_html__('Select Area', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $area_options,
            'condition' => ['source' => 'area'],
        ]);

        // Cinema dropdown
        $cinemas_cache = get_transient('ktn_all_cinemas_list');
        if (false === $cinemas_cache) {
            $cinemas_query = get_posts([
                'post_type' => 'ktn_cinema', 
                'posts_per_page' => 500, // Reasonable cap
                'post_status' => 'publish',
            ]);
            $cinemas_cache = [];
            foreach ($cinemas_query as $c) {
                $cinemas_cache[$c->ID] = $c->post_title;
            }
            set_transient('ktn_all_cinemas_list', $cinemas_cache, 12 * HOUR_IN_SECONDS);
        }
        $cinema_options = !empty($cinemas_cache) ? $cinemas_cache : [];
        
        $this->add_control('cinema_id', [
            'label' => esc_html__('Select Cinema', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $cinema_options,
            'condition' => ['source' => 'cinema'],
        ]);

        $this->add_control('posts_per_page', [
            'label' => esc_html__('Movies Count', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 8,
            'condition' => ['source!' => 'manual'],
        ]);

        $this->add_control('layout_mode', [
            'label' => esc_html__('Layout Mode', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'grid',
            'options' => [
                'grid' => esc_html__('Grid', 'kontentainment'),
                'carousel' => esc_html__('Carousel Slider', 'kontentainment'),
            ],
            'separator' => 'before'
        ]);

        $skins = [
            'grid_1' => 'Grid 1 (Standard)',
            'grid_2' => 'Grid 2 (Bordered)',
            'list'   => 'List View',
            'overlay'=> 'Overlay Cards',
            'hero'   => 'Hero Display',
            'compact'=> 'Compact Layout'
        ];
        $this->add_skin_control($skins);

        $this->add_columns_control();
        
        $this->end_controls_section();

        $this->start_controls_section('section_visibility', [
            'label' => esc_html__('Content Visibility', 'kontentainment'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $visibility_controls = [
            'show_rating' => 'Show Rating',
            'show_genres' => 'Show Genres',
            'show_date'   => 'Show Release Date',
            'show_excerpt'=> 'Show Tagline/Excerpt',
            'show_cta'    => 'Show CTA Button',
        ];

        foreach ($visibility_controls as $key => $label) {
            $this->add_control($key, [
                'label' => esc_html__($label, 'kontentainment'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_on' => 'Yes',
                'label_off' => 'No',
                'return_value' => 'yes',
            ]);
        }

        $this->add_control('cta_text', [
            'label' => esc_html__('Custom CTA Text', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => __('احجز تذكرتك', 'kontentainment'),
            'condition' => ['show_cta' => 'yes']
        ]);

        $this->end_controls_section();

        // ----------------- STYLE TABS ----------------- //

        $this->start_controls_section('section_style_card', [
            'label' => esc_html__('Card Style', 'kontentainment'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('card_border_radius', [
            'label' => esc_html__('Border Radius', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => [
                'px' => ['min' => 0, 'max' => 50],
            ],
            'selectors' => [
                '{{WRAPPER}} .ktn-premium-movie-card .ktn-card-media' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), [
            'name' => 'card_box_shadow',
            'label' => esc_html__('Box Shadow', 'kontentainment'),
            'selector' => '{{WRAPPER}} .ktn-premium-movie-card .ktn-card-media',
        ]);

        $this->add_control('overlay_color', [
            'label' => esc_html__('Overlay Gradient Color', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .ktn-premium-movie-card .ktn-card-overlay' => 'background: linear-gradient(to top, {{VALUE}} 10%, rgba(0,0,0,0) 100%);',
            ],
        ]);

        $this->end_controls_section();

        // Typography Section
        $this->start_controls_section('section_style_typography', [
            'label' => esc_html__('Typography & Colors', 'kontentainment'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('title_color', [
            'label' => esc_html__('Title Color', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .ktn-premium-movie-card .ktn-card-title' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
            'name' => 'title_typography',
            'label' => esc_html__('Title Typography', 'kontentainment'),
            'selector' => '{{WRAPPER}} .ktn-premium-movie-card .ktn-card-title',
        ]);

        $this->add_control('genre_color', [
            'label' => esc_html__('Genre Color', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .ktn-premium-movie-card .ktn-card-genre' => 'color: {{VALUE}};',
            ],
            'separator' => 'before'
        ]);

        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
            'name' => 'genre_typography',
            'label' => esc_html__('Genre Typography', 'kontentainment'),
            'selector' => '{{WRAPPER}} .ktn-premium-movie-card .ktn-card-genre',
        ]);

        $this->add_control('cta_bg_color', [
            'label' => esc_html__('CTA Background', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .ktn-premium-movie-card .ktn-card-cta-btn' => 'background-color: {{VALUE}};',
            ],
            'separator' => 'before'
        ]);

        $this->add_control('cta_text_color', [
            'label' => esc_html__('CTA Text Color', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .ktn-premium-movie-card .ktn-card-cta-btn' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
            'name' => 'cta_typography',
            'label' => esc_html__('CTA Typography', 'kontentainment'),
            'selector' => '{{WRAPPER}} .ktn-premium-movie-card .ktn-card-cta-btn',
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        $args = [
            'post_type' => 'movie',
            'post_status' => 'publish',
            'posts_per_page' => $settings['posts_per_page'] ? $settings['posts_per_page'] : 8,
        ];

        global $wpdb;

        if ($settings['source'] === 'now_playing') {
            $today = date('Y-m-d');
            $now_playing_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT matched_movie_id FROM {$wpdb->prefix}ktn_showtimes WHERE matched_movie_id IS NOT NULL AND (show_date >= %s OR show_date = 'Today') ORDER BY id DESC",
                $today
            ));
            if (!empty($now_playing_ids)) {
                $args['post__in'] = $now_playing_ids;
                $args['orderby'] = 'post__in';
            } else {
                $args['post__in'] = [0];
            }
        }
        elseif ($settings['source'] === 'coming_soon') {
            $today = date('Y-m-d');
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
        }
        elseif ($settings['source'] === 'manual' && !empty($settings['manual_ids'])) {
            $ids = array_map('intval', explode(',', $settings['manual_ids']));
            $args['post__in'] = $ids;
            $args['orderby'] = 'post__in';
            $args['posts_per_page'] = -1;
        }
        elseif ($settings['source'] === 'area' && !empty($settings['area_slug'])) {
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
                $today = date('Y-m-d');
                $area_movie_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT matched_movie_id FROM {$wpdb->prefix}ktn_showtimes WHERE matched_movie_id IS NOT NULL AND cinema_id IN ($ids_str) AND (show_date >= %s OR show_date = 'Today')",
                    $today
                ));
                if (!empty($area_movie_ids)) {
                    $args['post__in'] = $area_movie_ids;
                } else {
                    $args['post__in'] = [0];
                }
            } else {
                $args['post__in'] = [0];
            }
        }
        elseif ($settings['source'] === 'cinema' && !empty($settings['cinema_id'])) {
            $today = date('Y-m-d');
            $cinema_movie_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT matched_movie_id FROM {$wpdb->prefix}ktn_showtimes WHERE matched_movie_id IS NOT NULL AND cinema_id = %d AND (show_date >= %s OR show_date = 'Today')",
                $settings['cinema_id'],
                $today
            ));
            if (!empty($cinema_movie_ids)) {
                $args['post__in'] = $cinema_movie_ids;
            } else {
                $args['post__in'] = [0];
            }
        }

        $query = new \WP_Query($args);
        $layout = isset($settings['layout_mode']) ? $settings['layout_mode'] : 'grid';

        echo '<div class="ktn-elementor-movies-wrapper ktn-skin-' . esc_attr($settings['layout_skin']) . ' layout-' . esc_attr($layout) . '">';
        if ($query->have_posts()) {
            
            if ($layout === 'carousel') {
                $uid = 'swiper-' . uniqid();
                echo '<div class="swiper-container ktn-movies-carousel" id="' . esc_attr($uid) . '">';
                echo '<div class="swiper-wrapper">';
            } else {
                echo '<div class="ktn-elementor-grid">';
            }

            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                if ($layout === 'carousel') {
                    echo '<div class="swiper-slide">';
                }

                echo Ktn_Card_System::render_movie_card($post_id, array(
                    'show_rating'  => ($settings['show_rating'] === 'yes'),
                    'show_year'    => ($settings['show_date'] === 'yes'),
                    'show_genre'   => ($settings['show_genres'] === 'yes'),
                    'show_excerpt' => ($settings['show_excerpt'] === 'yes'),
                    'show_cta'     => ($settings['show_cta'] === 'yes'),
                    'cta_text'     => $settings['cta_text']
                ));

                if ($layout === 'carousel') {
                    echo '</div>';
                }
            }
            
            if ($layout === 'carousel') {
                echo '</div>'; // end swiper-wrapper
                echo '</div>'; // end swiper-container
                
                // Pure CSS scroll snap as fallback/modern alternative to JS swiper
                // We inject minimal CSS here just for the carousel
                ?>
                <style>
                    #<?php echo $uid; ?> {
                        display: flex;
                        overflow-x: auto;
                        scroll-snap-type: x mandatory;
                        scroll-behavior: smooth;
                        -webkit-overflow-scrolling: touch;
                        gap: 20px;
                        padding-bottom: 20px;
                    }
                    #<?php echo $uid; ?>::-webkit-scrollbar {
                        height: 6px;
                    }
                    #<?php echo $uid; ?>::-webkit-scrollbar-track {
                        background: rgba(0,0,0,0.05);
                        border-radius: 10px;
                    }
                    #<?php echo $uid; ?>::-webkit-scrollbar-thumb {
                        background: rgba(0,0,0,0.2);
                        border-radius: 10px;
                    }
                    #<?php echo $uid; ?> .swiper-slide {
                        flex: 0 0 calc(100% / <?php echo $settings['columns'] ? $settings['columns'] : 4; ?> - 20px);
                        scroll-snap-align: start;
                    }
                    @media (max-width: 1024px) {
                        #<?php echo $uid; ?> .swiper-slide {
                            flex: 0 0 calc(100% / <?php echo $settings['columns_tablet'] ? $settings['columns_tablet'] : 2; ?> - 20px);
                        }
                    }
                    @media (max-width: 767px) {
                        #<?php echo $uid; ?> .swiper-slide {
                            flex: 0 0 calc(100% / <?php echo $settings['columns_mobile'] ? $settings['columns_mobile'] : 1; ?> - 20px);
                            flex: 0 0 85%; /* Slightly peek the next slide on mobile */
                        }
                    }
                </style>
                <?php
            } else {
                echo '</div>';
            }
            
            wp_reset_postdata();
        } else {
            echo '<p class="ktn-elem-empty">' . esc_html__('No movies found matching criteria.', 'kontentainment') . '</p>';
        }
        echo '</div>';
    }
}
