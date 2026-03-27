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

    public static function syncCinema($post_id, $refresh_meta = true, $showtimes_only = false) {
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
             return array('success' => false, 'added' => 0, 'message' => __('Missing or invalid Source URL.', 'kontentainment'));
        }
        if ($status === 'inactive') return array('success' => false, 'added' => 0, 'message' => __('Cinema is set to inactive.', 'kontentainment'));

        // Perform Fetch
        $sync_data = Ktn_Cinema_Scraper::fetch_from_source($post_id, $source_url, $source_type);
        if (is_wp_error($sync_data)) {
            update_post_meta($post_id, '_ktn_last_error', $sync_data->get_error_message());
            return array('success' => false, 'added' => 0, 'message' => $sync_data->get_error_message());
        }

        $metadata = $sync_data['metadata'] ?? array();
        $showtimes = $sync_data['showtimes'] ?? array();

        // --- Autofill Cinema Meta (Skip if showtimes_only) ---
        $best_name = !empty($metadata['english_name']) ? $metadata['english_name'] : (!empty($metadata['name']) ? $metadata['name'] : '');
        $current_post = get_post($post_id);

        if (!$showtimes_only) {
            // Update title and slug if needed
            $needs_title_update = ($best_name && ($current_post->post_title === 'Auto Draft' || $current_post->post_title === 'Processing...' || empty($current_post->post_title) || $current_post->post_title === 'Untitled'));
            $needs_slug_update = ($current_post->post_name === 'processing' || $current_post->post_name === 'auto-draft');

            if ($needs_title_update || $needs_slug_update) {
                 $update_args = array('ID' => $post_id);
                 if ($best_name) {
                     $update_args['post_title'] = sanitize_text_field($best_name);
                     $update_args['post_name'] = ''; 
                 }
                 wp_update_post($update_args);
            }

            if ($refresh_meta) {
                $meta_fields = [
                    'theater_id' => '_ktn_cinema_theater_id',
                    'arabic_name' => '_ktn_cinema_arabic_name',
                    'english_name' => '_ktn_cinema_english_name',
                    'logo' => '_ktn_cinema_logo',
                    'cover_image' => '_ktn_cinema_cover_image',
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
                          $val = ($key === 'logo' || $key === 'maps_url' || $key === 'cover_image') ? esc_url_raw($metadata[$key]) : sanitize_text_field($metadata[$key]);
                          update_post_meta($post_id, $meta_key, $val);
                     }
                }

                if (!empty($metadata['notes'])) {
                     wp_update_post(array('ID' => $post_id, 'post_content' => wp_kses_post($metadata['notes'])));
                }
            }

            // --- Hierarchy Taxonomy Mapping (City/Area) ---
            if ($refresh_meta) {
                $existing_terms = wp_get_object_terms($post_id, 'cinema_location', array('fields' => 'ids'));
                if (empty($existing_terms) || is_wp_error($existing_terms)) {
                    $city = !empty($metadata['city']) ? sanitize_text_field($metadata['city']) : '';
                    $area = !empty($metadata['area']) ? sanitize_text_field($metadata['area']) : '';
                    
                    if ($city) {
                        $city_term = wp_insert_term($city, 'cinema_location', array('parent' => 0));
                        if (is_wp_error($city_term)) {
                            $existing_city = get_term_by('name', $city, 'cinema_location');
                            $city_id = $existing_city ? $existing_city->term_id : 0;
                        } else {
                            $city_id = $city_term['term_id'];
                        }

                        if ($city_id) {
                            $term_ids = array((int)$city_id);
                            if ($area) {
                                $area_term = wp_insert_term($area, 'cinema_location', array('parent' => $city_id));
                                if (is_wp_error($area_term)) {
                                    $existing_area = get_terms(array(
                                        'taxonomy' => 'cinema_location',
                                        'name' => $area,
                                        'parent' => $city_id,
                                        'hide_empty' => false,
                                        'number' => 1
                                    ));
                                    $area_id = !empty($existing_area) ? $existing_area[0]->term_id : 0;
                                } else {
                                    $area_id = $area_term['term_id'];
                                }
                                if ($area_id) $term_ids[] = (int)$area_id;
                            }
                            wp_set_object_terms($post_id, $term_ids, 'cinema_location', false);
                        }
                    }
                }
            }
        }

        // --- Save Showtimes to Database ---
        $wpdb->delete($table_name, array('cinema_id' => $post_id));
        $added = 0;
        $newly_imported = 0;
        $matched_count = 0;
        $processed_movies = array();

        foreach ($showtimes as $row) {
             $movie_id = self::matchMovieTitle($row['movie_title']);
             
             // Auto-import if missing and TMDB enabled
             if (!$movie_id && function_exists('ktn_search_tmdb_movie_by_title')) {
                 $search_res = ktn_search_tmdb_movie_by_title($row['movie_title']);
                 if (!is_wp_error($search_res)) {
                     // Check again if we already have this TMDB ID locally to avoid double import
                     $existing_tmdb = new WP_Query(array(
                         'post_type' => 'movie',
                         'meta_key' => '_movie_tmdb_id',
                         'meta_value' => $search_res['id'],
                         'posts_per_page' => 1,
                         'fields' => 'ids'
                     ));
                     
                     if ($existing_tmdb->have_posts()) {
                         $movie_id = $existing_tmdb->posts[0];
                     } else {
                         // Create empty movie post first
                         $new_post_id = wp_insert_post(array(
                             'post_title' => $search_res['title'],
                             'post_type'  => 'movie',
                             'post_status' => 'publish'
                         ));
                         
                         if (!is_wp_error($new_post_id)) {
                             $token = get_option('ktn_tmdb_bearer_token');
                             $lang = get_option('ktn_default_language', 'en-US');
                             $details = ktn_get_tmdb_media_details($search_res['id'], 'movie', $token, $lang);
                             if (!is_wp_error($details)) {
                                 ktn_process_and_save_data($new_post_id, $details, $details['external_ids']['imdb_id'] ?? '', 'movie');
                                 $movie_id = $new_post_id;
                                 $newly_imported++;
                             }
                         }
                     }
                 }
             }

             if ($movie_id && !isset($processed_movies[$movie_id])) {
                 $matched_count++;
                 $processed_movies[$movie_id] = true;
             }

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
        
        $result = array(
            'success' => true, 
            'added' => $added, 
            'matched' => $matched_count, 
            'imported' => $newly_imported
        );

        if ($added > 0) {
            $result['message'] = sprintf(__('Success: Synced %d showtimes. Matched %d movies (%d newly imported).', 'kontentainment'), $added, $matched_count, $newly_imported);
        } elseif (!$refresh_meta || $showtimes_only) {
            $result['message'] = __('Success: Manual sync complete.', 'kontentainment');
        } else {
            $result['success'] = false;
            $result['message'] = __('Failed: No useful data extracted from source.', 'kontentainment');
        }

        update_post_meta($post_id, '_ktn_last_error', $result['message']);
        return $result;
    }

    public static function syncAllCinemas($auto_sync_only = false) {
        $args = array('post_type' => 'ktn_cinema', 'posts_per_page' => -1, 'post_status' => 'publish');
        $args['meta_query'] = array('relation' => 'AND', array('key' => '_ktn_cinema_status', 'value' => 'active', 'compare' => '='));
        if ($auto_sync_only) $args['meta_query'][] = array('key' => '_ktn_cinema_auto_sync', 'value' => 'yes', 'compare' => '=');
        
        $cinemas = get_posts($args);
        $total = 0;
        foreach ($cinemas as $cinema) {
             $res = self::syncCinema($cinema->ID);
             if (is_array($res) && isset($res['added'])) {
                 $total += $res['added'];
             }
        }
        return $total;
    }
}