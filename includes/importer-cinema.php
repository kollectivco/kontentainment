<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cinema Data Importer and Sync Engine
 */
class Ktn_Cinema_Importer
{

    public static function normalizeTitle($title) {
        $title = strtolower(trim($title));
        $title = preg_replace('/[^\w\s-]/u', '', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        return trim($title);
    }

    public static function matchMovieTitle($scraped_title) {
        global $wpdb;

        $norm_scraped = self::normalizeTitle($scraped_title);
        if (empty($norm_scraped)) return null;

        // Try exact match on post title
        $exact = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND post_title = %s LIMIT 1", $scraped_title));
        if ($exact) return $exact;

        // Try exact match on original title meta
        $meta_exact = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_movie_original_title' AND meta_value = %s LIMIT 1", $scraped_title));
        if ($meta_exact) return $meta_exact;

        // Try normalized title comparison
        $all_movies = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish'");
        foreach ($all_movies as $movie) {
            if (self::normalizeTitle($movie->post_title) === $norm_scraped) return $movie->ID;
            $orig = get_post_meta($movie->ID, '_movie_original_title', true);
            if ($orig && self::normalizeTitle($orig) === $norm_scraped) return $movie->ID;
        }

        return null;
    }

    public static function syncCinema($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktn_showtimes';

        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $wpdb->query("CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                cinema_id bigint(20) NOT NULL,
                cinema_name varchar(255) NOT NULL,
                source_url text NOT NULL,
                movie_title_scraped varchar(255) NOT NULL,
                matched_movie_id bigint(20) DEFAULT NULL,
                show_date varchar(100) NOT NULL,
                show_time varchar(100) NOT NULL,
                experience varchar(100) DEFAULT 'Standard',
                price_text varchar(100) DEFAULT '',
                source_type varchar(100) DEFAULT 'elcinema_theater',
                scraped_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY cinema_id (cinema_id),
                KEY matched_movie_id (matched_movie_id)
            ) $charset_collate;");
        }

        // Get source config
        $source_url = get_post_meta($post_id, '_ktn_cinema_url', true);
        $source_type = get_post_meta($post_id, '_ktn_cinema_type', true) ?: 'elcinema_theater';
        $status = get_post_meta($post_id, '_ktn_cinema_status', true) ?: 'active';

        if (!$source_url || !filter_var($source_url, FILTER_VALIDATE_URL)) {
             return new WP_Error('missing_url', __('Missing or invalid Source URL.', 'kontentainment'));
        }
        if ($status === 'inactive') return new WP_Error('inactive_cinema', __('Cinema is set to inactive.', 'kontentainment'));

        // Perform Fetch
        $sync_data = Ktn_Cinema_Scraper::fetch_from_source($post_id, $source_url, $source_type);
        if (is_wp_error($sync_data)) {
            update_post_meta($post_id, '_ktn_last_error', $sync_data->get_error_message());
            return $sync_data;
        }

        $metadata = $sync_data['metadata'] ?? array();
        $showtimes = $sync_data['showtimes'] ?? array();

        // --- Autofill Cinema Meta ---
        $current_post = get_post($post_id);
        $best_name = !empty($metadata['english_name']) ? $metadata['english_name'] : (!empty($metadata['name']) ? $metadata['name'] : '');
        
        // Auto-update title if it's draft or empty
        if ((empty($current_post->post_title) || stripos($current_post->post_title, 'Auto Draft') !== false) && $best_name) {
             wp_update_post(array('ID' => $post_id, 'post_title' => sanitize_text_field($best_name)));
        }

        $meta_fields = [
            'theater_id' => '_ktn_cinema_theater_id',
            'arabic_name' => '_ktn_cinema_arabic_name',
            'english_name' => '_ktn_cinema_english_name',
            'logo' => '_ktn_cinema_logo',
            'rating' => '_ktn_cinema_rating',
            'address' => '_ktn_cinema_address',
            'area' => '_ktn_cinema_area',
            'city' => '_ktn_cinema_city',
            'country' => '_ktn_cinema_country',
            'phone' => '_ktn_cinema_phone',
            'notes' => '_ktn_cinema_notes',
            'maps_url' => '_ktn_cinema_maps_url'
        ];

        foreach ($meta_fields as $key => $meta_key) {
             if (!empty($metadata[$key])) {
                  $val = ($key === 'logo' || $key === 'maps_url') ? esc_url_raw($metadata[$key]) : sanitize_text_field($metadata[$key]);
                  update_post_meta($post_id, $meta_key, $val);
             }
        }

        // --- Hierarchy Taxonomy Mapping (City/Area) ---
        $city = !empty($metadata['city']) ? sanitize_text_field($metadata['city']) : '';
        $area = !empty($metadata['area']) ? sanitize_text_field($metadata['area']) : '';
        
        if ($city) {
             $city_term = wp_insert_term($city, 'cinema_location', array('parent' => 0));
             $city_id = is_wp_error($city_term) ? $city_term->get_error_data() : $city_term['term_id'];
             $term_ids = array((int)$city_id);

             if ($area) {
                  $area_term = wp_insert_term($area, 'cinema_location', array('parent' => $city_id));
                  $area_id = is_wp_error($area_term) ? $area_term->get_error_data() : $area_term['term_id'];
                  if ($area_id) $term_ids[] = (int)$area_id;
             }
             wp_set_object_terms($post_id, $term_ids, 'cinema_location', false);
        }

        // --- Save Showtimes to Database ---
        $wpdb->delete($table_name, array('cinema_id' => $post_id));
        $added = 0;
        foreach ($showtimes as $row) {
             $movie_id = self::matchMovieTitle($row['movie_title']);
             $wpdb->insert($table_name, array(
                 'cinema_id' => $post_id,
                 'cinema_name' => $best_name ?: $current_post->post_title,
                 'source_url' => $row['source_url'],
                 'movie_title_scraped' => $row['movie_title'],
                 'matched_movie_id' => $movie_id,
                 'show_date' => $row['show_date'],
                 'show_time' => $row['show_time'],
                 'experience' => $row['experience'],
                 'price_text' => $row['price_text'],
                 'source_type' => $source_type,
                 'scraped_at' => current_time('mysql'),
                 'updated_at' => current_time('mysql')
             ));
             $added++;
        }

        update_post_meta($post_id, '_ktn_cinema_last_sync', current_time('mysql'));
        update_post_meta($post_id, '_ktn_last_error', sprintf(__('Success: Synced %d showtimes.', 'kontentainment'), $added));
        return $added;
    }

    public static function syncAllCinemas($auto_sync_only = false) {
        $args = array('post_type' => 'ktn_cinema', 'posts_per_page' => -1, 'post_status' => 'publish');
        $args['meta_query'] = array('relation' => 'AND', array('key' => '_ktn_cinema_status', 'value' => 'active', 'compare' => '='));
        if ($auto_sync_only) $args['meta_query'][] = array('key' => '_ktn_cinema_auto_sync', 'value' => 'yes', 'compare' => '=');
        
        $cinemas = get_posts($args);
        $total = 0;
        foreach ($cinemas as $cinema) {
             $res = self::syncCinema($cinema->ID);
             if (!is_wp_error($res)) $total += $res;
        }
        return $total;
    }
}