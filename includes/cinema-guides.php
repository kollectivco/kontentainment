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

        // Dependent filter handler
        add_action('wp_ajax_ktn_get_child_locations', array($this, 'ajax_get_child_locations'));
        add_action('wp_ajax_nopriv_ktn_get_child_locations', array($this, 'ajax_get_child_locations'));

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
        if (function_exists('ktn_is_cinema_guides_page') && ktn_is_cinema_guides_page()) {
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
        // Foolproof fallback: Enqueue styles and scripts directly in case wp_enqueue_scripts was bypassed
        wp_enqueue_style('ktn-cinema-guides', KTN_PLUGIN_URL . 'assets/css/cinema-guides.css', array(), KTN_PLUGIN_VERSION);
        wp_enqueue_script('ktn-cinema-guides', KTN_PLUGIN_URL . 'assets/js/cinema-guides.js', array('jquery'), KTN_PLUGIN_VERSION, true);
        wp_localize_script('ktn-cinema-guides', 'ktn_guides', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ktn_guides_nonce')
        ));

        ob_start();
        ?>
        <div id="ktn-guides-root" class="ktn-guides-container">
            <header class="ktn-guides-header">
                <h1><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'دليل السينما' : __('Cinema Guides', 'kontentainment'); ?></h1>
                <p class="ktn-subtitle"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'اكتشف أحدث الأفلام وأفضل السينمات القريبة منك' : __('Explore the latest movies and top-rated cinemas near you.', 'kontentainment'); ?></p>
            </header>

            <nav class="ktn-guides-tabs">
                <button class="ktn-tab-btn active" data-tab="movies"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'الأفلام' : __('Movies', 'kontentainment'); ?></button>
                <button class="ktn-tab-btn" data-tab="cinemas"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'سينمات' : __('Cinemas', 'kontentainment'); ?></button>
                <button class="ktn-tab-btn" data-tab="box-office"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'بوكس أوفيس' : __('Box Office', 'kontentainment'); ?></button>
            </nav>

            <div class="ktn-guides-content">
                <!-- MOVIES TAB -->
                <div id="tab-movies" class="ktn-tab-panel active">
                    <div class="ktn-filter-row">
                        <div class="ktn-sub-tabs" id="movie-lang-filters">
                            <button class="ktn-sub-tab active" data-lang="all"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'كل الأفلام' : __('All Movies', 'kontentainment'); ?></button>
                            <button class="ktn-sub-tab" data-lang="en"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'الأفلام الأجنبية' : __('English Movies', 'kontentainment'); ?></button>
                            <button class="ktn-sub-tab" data-lang="ar"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'الأفلام العربية' : __('Arabic Movies', 'kontentainment'); ?></button>
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

                        <div class="ktn-dropdown-wrapper">
                            <select id="movie-city" class="ktn-select">
                                <option value=""><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'كل المحافظات' : __('All Governorates', 'kontentainment'); ?></option>
                                <?php
                                $cities = get_terms(array('taxonomy' => 'cinema_location', 'parent' => 0, 'hide_empty' => true));
                                foreach ($cities as $city) {
                                    echo '<option value="' . esc_attr($city->slug) . '">' . esc_html($city->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="ktn-dropdown-wrapper">
                            <select id="movie-area" class="ktn-select" disabled>
                                <option value=""><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'اختر المنطقة' : __('Select Area', 'kontentainment'); ?></option>
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
                            <input type="text" id="cinema-search" placeholder="<?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'اكتب اسم السينما' : __('Type Your Cinema Name', 'kontentainment'); ?>">
                            <span class="dashicons dashicons-search"></span>
                        </div>

                        <div class="ktn-dropdown-wrapper">
                            <select id="cinema-city" class="ktn-select">
                                <option value=""><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'كل المحافظات' : __('All Governorates', 'kontentainment'); ?></option>
                                <?php
                                $cities = get_terms(array('taxonomy' => 'cinema_location', 'parent' => 0, 'hide_empty' => true));
                                foreach ($cities as $city) {
                                    echo '<option value="' . esc_attr($city->slug) . '">' . esc_html($city->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="ktn-dropdown-wrapper">
                            <select id="cinema-area" class="ktn-select" disabled>
                                <option value=""><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'اختر المنطقة' : __('Select Area', 'kontentainment'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div id="cinema-results" class="ktn-results-grid ktn-cinema-grid">
                        <?php echo $this->get_cinemas_html(); ?>
                    </div>
                </div>

                <!-- BOX OFFICE TAB -->
                <div id="tab-box-office" class="ktn-tab-panel">
                    <?php 
                    global $ktn_is_box_office_shortcode;
                    $old_flag = $ktn_is_box_office_shortcode;
                    $ktn_is_box_office_shortcode = true;
                    include KTN_PLUGIN_DIR . 'templates/page-box-office.php';
                    $ktn_is_box_office_shortcode = $old_flag;
                    ?>
                </div>
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

        if (!empty($filters['area']) || !empty($filters['city'])) {
            global $wpdb;
            $location_slug = !empty($filters['area']) ? $filters['area'] : $filters['city'];
            
            // 1. Get all cinemas in this location
            $cinema_ids = get_posts(array(
                'post_type' => 'ktn_cinema',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'cinema_location',
                        'field' => 'slug',
                        'terms' => $location_slug
                    )
                )
            ));
            
            if (!empty($cinema_ids)) {
                $cinema_ids_str = implode(',', array_map('intval', $cinema_ids));
                $today = date('Y-m-d');
                
                // 2. Query showtimes table for movies playing in these cinemas
                $movie_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT matched_movie_id FROM {$wpdb->prefix}ktn_showtimes 
                     WHERE matched_movie_id IS NOT NULL 
                     AND cinema_id IN ($cinema_ids_str) 
                     AND (show_date >= %s OR show_date = 'Today')",
                    $today
                ));
                
                if (!empty($movie_ids)) {
                    $args['post__in'] = array_map('intval', $movie_ids);
                } else {
                    $args['post__in'] = array(0); // Force empty results
                }
            } else {
                $args['post__in'] = array(0); // Force empty results
            }
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
            'tax_query' => array('relation' => 'AND')
        );

        if (!empty($filters['search'])) {
            $args['s'] = $filters['search'];
        }

        if (!empty($filters['area'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'cinema_location',
                'field'    => 'slug',
                'terms'    => $filters['area']
            );
        } elseif (!empty($filters['city'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'cinema_location',
                'field'    => 'slug',
                'terms'    => $filters['city']
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

    public function ajax_get_child_locations()
    {
        check_ajax_referer('ktn_guides_nonce', 'nonce');
        $parent_slug = sanitize_text_field($_POST['parent_slug']);
        
        if (!$parent_slug) {
            wp_send_json_success(array());
        }

        $parent = get_term_by('slug', $parent_slug, 'cinema_location');
        if (!$parent) {
            wp_send_json_success(array());
        }

        $children = get_terms(array(
            'taxonomy' => 'cinema_location',
            'parent'   => $parent->term_id,
            'hide_empty' => true
        ));

        $results = array();
        foreach ($children as $child) {
            $results[] = array('slug' => $child->slug, 'name' => $child->name);
        }

        wp_send_json_success($results);
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
            $filters['city']   = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
            $filters['area']   = isset($_POST['area']) ? sanitize_text_field($_POST['area']) : '';
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
