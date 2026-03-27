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
        return esc_html__('Kueue Movies', 'kontentainment');
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
            'default' => 'latest',
            'options' => [
                'latest'      => esc_html__('Latest Movies', 'kontentainment'),
                'now_playing' => esc_html__('Now Playing', 'kontentainment'),
                'coming_soon' => esc_html__('Coming Soon', 'kontentainment'),
                'manual'      => esc_html__('Manual Selection', 'kontentainment'),
                'area'        => esc_html__('Movies by Area', 'kontentainment'),
                'cinema'      => esc_html__('Movies by Cinema', 'kontentainment'),
            ],
        ]);

        $this->add_control('manual_ids', [
            'label' => esc_html__('Movie IDs (comma separated)', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'condition' => ['source' => 'manual'],
        ]);

        // Areas dropdown (dynamically populated)
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
            'condition' => ['source' => 'area'],
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
            'condition' => ['source' => 'cinema'],
        ]);

        $this->add_control('posts_per_page', [
            'label' => esc_html__('Movies Count', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 8,
            'condition' => ['source!' => 'manual'],
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
            'show_poster' => 'Show Poster',
            'show_title'  => 'Show Title',
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
                "SELECT DISTINCT matched_movie_id FROM {$wpdb->prefix}ktn_showtimes WHERE matched_movie_id IS NOT NULL AND (show_date >= %s OR show_date = 'Today')",
                $today
            ));
            if (!empty($now_playing_ids)) {
                $args['post__in'] = $now_playing_ids;
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
            // Find cinemas in the area, then get showtimes for those cinemas
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

        echo '<div class="ktn-elementor-movies-wrapper ktn-skin-' . esc_attr($settings['layout_skin']) . '">';
        if ($query->have_posts()) {
            echo '<div class="ktn-elementor-grid">';
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                echo Ktn_Card_System::render_movie_card($post_id, array(
                    'show_rating'  => ($settings['show_rating'] === 'yes'),
                    'show_year'    => ($settings['show_date'] === 'yes'),
                    'show_genre'   => ($settings['show_genres'] === 'yes'),
                    'show_excerpt' => ($settings['show_excerpt'] === 'yes'),
                    'show_cta'     => ($settings['show_cta'] === 'yes')
                ));
            }
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p class="ktn-elem-empty">No movies found matching criteria.</p>';
        }
        echo '</div>';
    }
}
