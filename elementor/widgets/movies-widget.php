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

        $query = new \WP_Query($args);

        echo '<div class="ktn-elementor-movies-wrapper ktn-skin-' . esc_attr($settings['layout_skin']) . '">';
        if ($query->have_posts()) {
            echo '<div class="ktn-elementor-grid">';
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $title = get_the_title();
                $permalink = get_permalink();
                
                $poster_url = has_post_thumbnail() ? get_the_post_thumbnail_url($post_id, 'medium_large') : '';
                if (!$poster_url) {
                    $poster_path = get_post_meta($post_id, '_movie_poster_path', true);
                    if ($poster_path) $poster_url = "https://image.tmdb.org/t/p/w500" . $poster_path;
                }
                
                $rating = get_post_meta($post_id, '_movie_vote_average', true);
                $release_date = get_post_meta($post_id, '_movie_release_date', true);
                $tagline = get_post_meta($post_id, '_movie_tagline', true);
                
                $genes_str = '';
                $terms = get_the_terms($post_id, 'movie_genre') ?: get_the_terms($post_id, 'ktn_genre');
                if ($terms && !is_wp_error($terms)) {
                    $genes_str = implode(', ', array_slice(wp_list_pluck($terms, 'name'), 0, 2));
                }

                ?>
                <div class="ktn-elem-movie-card">
                    <?php if ($settings['show_poster'] === 'yes'): ?>
                        <div class="ktn-elem-poster">
                            <?php if ($settings['show_rating'] === 'yes' && $rating): ?>
                                <span class="ktn-elem-badge"><?php echo esc_html(round($rating, 1)); ?> ⭐️</span>
                            <?php endif; ?>
                            <a href="<?php echo esc_url($permalink); ?>">
                                <?php if ($poster_url): ?>
                                    <img src="<?php echo esc_url($poster_url); ?>" alt="<?php echo esc_attr($title); ?>">
                                <?php else: ?>
                                    <div class="ktn-elem-no-poster">No Poster</div>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="ktn-elem-content">
                        <?php if ($settings['show_title'] === 'yes'): ?>
                            <h3 class="ktn-elem-title"><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a></h3>
                        <?php endif; ?>

                        <div class="ktn-elem-meta">
                            <?php if ($settings['show_genres'] === 'yes' && $genes_str): ?>
                                <span class="ktn-meta-genre"><?php echo esc_html($genes_str); ?></span>
                            <?php endif; ?>
                            <?php if ($settings['show_date'] === 'yes' && $release_date): ?>
                                <span class="ktn-meta-date"><?php echo esc_html(date('Y', strtotime($release_date))); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($settings['show_excerpt'] === 'yes' && $tagline): ?>
                            <div class="ktn-elem-excerpt"><?php echo esc_html(wp_trim_words($tagline, 12, '...')); ?></div>
                        <?php endif; ?>

                        <?php if ($settings['show_cta'] === 'yes'): ?>
                            <div class="ktn-elem-actions">
                                <a href="<?php echo esc_url($permalink); ?>" class="ktn-btn-primary">View Details</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p class="ktn-elem-empty">No movies found matching criteria.</p>';
        }
        echo '</div>';
    }
}
