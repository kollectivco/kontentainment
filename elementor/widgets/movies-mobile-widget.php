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

        $this->start_controls_section('section_visibility', [
            'label' => esc_html__('Content Visibility', 'kontentainment'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('show_poster', [
            'label' => esc_html__('Show Poster', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_title', [
            'label' => esc_html__('Show Title', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_rating', [
            'label' => esc_html__('Show Rating', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_genres', [
            'label' => esc_html__('Show Genres', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_year', [
            'label' => esc_html__('Show Release Year', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_cta', [
            'label' => esc_html__('Show CTA Button', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
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

        echo '<div class="ktn-mobile-movies-container">';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $this->render_mobile_card(get_the_ID(), $settings);
            }
            wp_reset_postdata();
        } else {
            echo '<p class="ktn-mobile-empty">' . esc_html__('No movies found.', 'kontentainment') . '</p>';
        }
        echo '</div>';
    }

    private function render_mobile_card($post_id, $settings) {
        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);
        $poster_url = has_post_thumbnail($post_id) ? get_the_post_thumbnail_url($post_id, 'medium') : '';
        
        if (!$poster_url) {
            $poster_path = get_post_meta($post_id, '_movie_poster_path', true);
            $poster_url = $poster_path ? "https://image.tmdb.org/t/p/w300" . $poster_path : KTN_PLUGIN_URL . 'assets/img/no-poster.jpg';
        }

        $rating = get_post_meta($post_id, '_movie_vote_average', true);
        $release_date = get_post_meta($post_id, '_movie_release_date', true);
        $year = $release_date ? date('Y', strtotime($release_date)) : '';
        
        $genres = [];
        $terms = get_the_terms($post_id, 'ktn_genre');
        if ($terms && !is_wp_error($terms)) {
            $genres = wp_list_pluck($terms, 'name');
        }
        $genre_text = !empty($genres) ? implode(', ', array_slice($genres, 0, 2)) : '';

        ?>
        <div class="ktn-mobile-movie-card">
            <a href="<?php echo esc_url($permalink); ?>" class="ktn-mobile-card-link">
                <?php if ($settings['show_poster'] === 'yes'): ?>
                    <div class="ktn-mobile-card-poster">
                        <img src="<?php echo esc_url($poster_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                        <?php if ($settings['show_rating'] === 'yes' && $rating): ?>
                            <div class="ktn-mobile-card-rating">
                                <span class="dashicons dashicons-star-filled"></span>
                                <?php echo number_format($rating, 1); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="ktn-mobile-card-details">
                    <?php if ($settings['show_title'] === 'yes'): ?>
                        <h4 class="ktn-mobile-card-title"><?php echo esc_html($title); ?></h4>
                    <?php endif; ?>

                    <div class="ktn-mobile-card-meta">
                        <?php if ($settings['show_genres'] === 'yes' && $genre_text): ?>
                            <span class="ktn-mobile-card-genre"><?php echo esc_html($genre_text); ?></span>
                        <?php endif; ?>
                        
                        <?php if ($settings['show_year'] === 'yes' && $year): ?>
                            <span class="ktn-mobile-card-year"><?php echo esc_html($year); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($settings['show_cta'] === 'yes'): ?>
                        <div class="ktn-mobile-card-action">
                            <span class="ktn-mobile-btn"><?php _e('Book Now', 'kontentainment'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </a>
        </div>
        <?php
    }
}
