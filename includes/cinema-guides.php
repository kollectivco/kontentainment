<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cinema Guides Page Logic
 */
class Ktn_Cinema_Guides
{

    public function __construct()
    {
        add_shortcode('ktn_cinema_guides', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_ktn_filter_guides', array($this, 'ajax_filter_handler'));
        add_action('wp_ajax_nopriv_ktn_filter_guides', array($this, 'ajax_filter_handler'));

        // Auto-create page
        add_action('init', array($this, 'maybe_create_page'));
    }

    public function maybe_create_page()
    {
        if (get_option('ktn_guides_page_created')) return;

        $page_slug = 'cinema-guides';
        $query = new WP_Query(array('pagename' => $page_slug, 'post_type' => 'page'));
        
        if (!$query->have_posts()) {
            wp_insert_post(array(
                'post_title'   => 'Cinema Guides',
                'post_name'    => $page_slug,
                'post_content' => '[ktn_cinema_guides]',
                'post_status'  => 'publish',
                'post_type'    => 'page'
            ));
        }
        update_option('ktn_guides_page_created', 1);
    }

    public function enqueue_assets()
    {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ktn_cinema_guides')) {
            wp_enqueue_style('ktn-cinema-guides', KTN_PLUGIN_URL . 'assets/css/cinema-guides.css', array(), KTN_PLUGIN_VERSION);
            wp_enqueue_script('ktn-cinema-guides', KTN_PLUGIN_URL . 'assets/js/cinema-guides.js', array('jquery'), KTN_PLUGIN_VERSION, true);
            
            wp_localize_script('ktn-cinema-guides', 'ktn_guides', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ktn_guides_nonce')
            ));
        }
    }

    public function render_shortcode()
    {
        ob_start();
        ?>
        <div id="ktn-guides-root" class="ktn-guides-container">
            <header class="ktn-guides-header">
                <h1><?php _e('Cinema Guides', 'kontentainment'); ?></h1>
                <p class="ktn-subtitle"><?php _e('Explore the latest movies and top-rated cinemas near you.', 'kontentainment'); ?></p>
            </header>

            <nav class="ktn-guides-tabs">
                <button class="ktn-tab-btn active" data-tab="movies"><?php _e('Movies', 'kontentainment'); ?></button>
                <button class="ktn-tab-btn" data-tab="cinemas"><?php _e('Cinemas', 'kontentainment'); ?></button>
            </nav>

            <div class="ktn-guides-content">
                <!-- MOVIES TAB -->
                <div id="tab-movies" class="ktn-tab-panel active">
                    <div class="ktn-filter-row">
                        <div class="ktn-sub-tabs" id="movie-lang-filters">
                            <button class="ktn-sub-tab active" data-lang="all"><?php _e('All Movies', 'kontentainment'); ?></button>
                            <button class="ktn-sub-tab" data-lang="en"><?php _e('English Movies', 'kontentainment'); ?></button>
                            <button class="ktn-sub-tab" data-lang="ar"><?php _e('Arabic Movies', 'kontentainment'); ?></button>
                        </div>
                        
                        <div class="ktn-search-box">
                            <input type="text" id="movie-search" placeholder="<?php _e('Type Your Movie Name', 'kontentainment'); ?>">
                            <span class="dashicons dashicons-search"></span>
                        </div>

                        <div class="ktn-dropdown-wrapper">
                            <select id="movie-genre" class="ktn-select">
                                <option value=""><?php _e('All Genres', 'kontentainment'); ?></option>
                                <?php
                                $genres = get_terms(array('taxonomy' => 'ktn_genre', 'hide_empty' => true));
                                foreach ($genres as $genre) {
                                    echo '<option value="' . esc_attr($genre->slug) . '">' . esc_html($genre->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div id="movie-results" class="ktn-results-grid">
                        <?php echo $this->get_movies_html(); ?>
                    </div>
                </div>

                <!-- CINEMAS TAB -->
                <div id="tab-cinemas" class="ktn-tab-panel">
                    <div class="ktn-filter-row">
                        <div class="ktn-search-box">
                            <input type="text" id="cinema-search" placeholder="<?php _e('Type Your Cinema Name', 'kontentainment'); ?>">
                            <span class="dashicons dashicons-search"></span>
                        </div>

                        <div class="ktn-dropdown-wrapper">
                            <select id="cinema-city" class="ktn-select">
                                <option value=""><?php _e('All Governorates', 'kontentainment'); ?></option>
                                <?php
                                global $wpdb;
                                $cities = $wpdb->get_col("SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = '_ktn_cinema_city' AND meta_value != '' ORDER BY meta_value ASC");
                                foreach ($cities as $city) {
                                    echo '<option value="' . esc_attr($city) . '">' . esc_html($city) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="ktn-dropdown-wrapper">
                            <select id="cinema-area" class="ktn-select">
                                <option value=""><?php _e('All Areas', 'kontentainment'); ?></option>
                                <?php
                                $areas = $wpdb->get_col("SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = '_ktn_cinema_area' AND meta_value != '' ORDER BY meta_value ASC");
                                foreach ($areas as $area) {
                                    echo '<option value="' . esc_attr($area) . '">' . esc_html($area) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div id="cinema-results" class="ktn-results-grid">
                        <!-- Loaded via AJAX or initial call -->
                        <?php echo $this->get_cinemas_html(); ?>
                    </div>
                </div>
            </div>

            <div class="ktn-guides-loader" style="display:none;">
                <div class="ktn-spinner"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function get_movies_html($filters = array())
    {
        $args = array(
            'post_type' => 'movie',
            'post_status' => 'publish',
            'posts_per_page' => 12,
            'meta_query' => array('relation' => 'AND'),
            'tax_query' => array()
        );

        if (!empty($filters['search'])) {
            $args['s'] = $filters['search'];
        }

        if (!empty($filters['lang']) && $filters['lang'] !== 'all') {
            $args['meta_query'][] = array(
                'key' => '_movie_original_language',
                'value' => $filters['lang'],
                'compare' => '='
            );
        }

        if (!empty($filters['genre'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'ktn_genre',
                'field'    => 'slug',
                'terms'    => $filters['genre']
            );
        }

        $query = new WP_Query($args);
        $html = '';

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                
                $html .= Ktn_Card_System::render_movie_card($id, array(
                    'show_rating' => true,
                    'show_year'   => true,
                    'show_genre'  => true
                ));
            }
            wp_reset_postdata();
        } else {
            $html = '<div class="ktn-no-results">' . __('No movies found matching your criteria.', 'kontentainment') . '</div>';
        }

        return $html;
    }

    public function get_cinemas_html($filters = array())
    {
        $args = array(
            'post_type' => 'ktn_cinema',
            'post_status' => 'publish',
            'posts_per_page' => 12,
            'meta_query' => array('relation' => 'AND')
        );

        if (!empty($filters['search'])) {
            $args['s'] = $filters['search'];
        }

        if (!empty($filters['city'])) {
            $args['meta_query'][] = array(
                'key' => '_ktn_cinema_city',
                'value' => $filters['city'],
                'compare' => '='
            );
        }

        if (!empty($filters['area'])) {
            $args['meta_query'][] = array(
                'key' => '_ktn_cinema_area',
                'value' => $filters['area'],
                'compare' => '='
            );
        }

        $query = new WP_Query($args);
        $html = '';

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $html .= Ktn_Card_System::render_cinema_card($id, array(
                    'show_rating'   => true,
                    'show_location' => true,
                    'show_cta'      => true
                ));
            }
            wp_reset_postdata();
        } else {
            $html = '<div class="ktn-no-results">' . __('No cinemas found in this location.', 'kontentainment') . '</div>';
        }

        return $html;
    }

    private function get_genres_csv($post_id)
    {
        $terms = get_the_terms($post_id, 'ktn_genre');
        if (is_wp_error($terms) || empty($terms)) return '';
        return implode(', ', wp_list_pluck($terms, 'name'));
    }

    public function ajax_filter_handler()
    {
        check_ajax_referer('ktn_guides_nonce', 'nonce');

        $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'movies';
        $filters = array();

        if ($tab === 'movies') {
            $filters['search'] = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            $filters['lang']   = isset($_POST['lang']) ? sanitize_text_field($_POST['lang']) : 'all';
            $filters['genre']  = isset($_POST['genre']) ? sanitize_text_field($_POST['genre']) : '';
            $resp = $this->get_movies_html($filters);
        } else {
            $filters['search'] = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            $filters['city']   = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
            $filters['area']   = isset($_POST['area']) ? sanitize_text_field($_POST['area']) : '';
            $resp = $this->get_cinemas_html($filters);
        }

        wp_send_json_success(array('html' => $resp));
    }
}

new Ktn_Cinema_Guides();
