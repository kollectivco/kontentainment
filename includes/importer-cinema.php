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

        if (empty($scraped_title)) return null;
        $scraped_title = trim($scraped_title);

        // 0a. Check persistent manual matches option first
        $manual_matches = get_option('ktn_manual_movie_matches', array());
        if (isset($manual_matches[$scraped_title])) {
            return intval($manual_matches[$scraped_title]);
        }

        // Try normalized match from manual option
        $norm_scraped = self::normalizeTitle($scraped_title);
        if (empty($norm_scraped)) return null;

        foreach ($manual_matches as $scraped_k => $matched_id) {
            if (self::normalizeTitle($scraped_k) === $norm_scraped) {
                return intval($matched_id);
            }
        }

        // 0b. Hardcoded transliteration map dictionary (supports both English keys and Arabic values lookup)
        $map = array(
            'asad' => 'أسد',
            'ezma' => 'أزمة',
            'bershama' => 'برشامة',
            'el kalam ala eh?!' => 'الكلام على إيه',
            'el kalam ala eh' => 'الكلام على إيه',
            'el kalam !?ala eh' => 'الكلام على إيه',
            '7 dogs' => 'ولاد رزق ٣',
            'dogs 7' => 'ولاد رزق ٣',
            'dogs' => 'ولاد رزق ٣',
            'welad rizk 3' => 'ولاد رزق ٣',
            'welad rizk' => 'ولاد رزق ٣'
        );
        
        $clean_title = strtolower(trim($scraped_title));
        $norm_clean = self::normalizeTitle($clean_title);

        // Search both keys and values
        foreach ($map as $eng => $ar) {
            if (trim(strtolower($eng)) === $clean_title || 
                trim(strtolower($ar)) === $clean_title ||
                self::normalizeTitle($eng) === $norm_clean || 
                self::normalizeTitle($ar) === $norm_clean) {
                
                // We found a match! Let's search the database for a post matching either the English key or the Arabic value.
                $mapped_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND (post_title = %s OR post_title = %s) LIMIT 1", $eng, $ar));
                if ($mapped_id) {
                    return intval($mapped_id);
                }

                // Try fuzzy/LIKE match for both English key and Arabic value
                $like_eng = '%' . $wpdb->esc_like($eng) . '%';
                $like_ar = '%' . $wpdb->esc_like($ar) . '%';
                $mapped_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND (post_title LIKE %s OR post_title LIKE %s OR post_name LIKE %s) LIMIT 1", $like_ar, $like_eng, $like_eng));
                if ($mapped_id) {
                    return intval($mapped_id);
                }
                
                // Try original title metadata lookup
                $meta_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_movie_original_title' AND (meta_value = %s OR meta_value = %s) LIMIT 1", $eng, $ar));
                if ($meta_id) {
                    return intval($meta_id);
                }
            }
        }

        // 0c. Check if there is an existing match in the database showtimes table
        $db_match = $wpdb->get_var($wpdb->prepare(
            "SELECT matched_movie_id FROM {$wpdb->prefix}ktn_showtimes WHERE movie_title_scraped = %s AND matched_movie_id IS NOT NULL LIMIT 1",
            $scraped_title
        ));
        if ($db_match) {
            // Save for future option persistent storage
            $manual_matches[$scraped_title] = intval($db_match);
            update_option('ktn_manual_movie_matches', $manual_matches);
            return intval($db_match);
        }

        // 1. Try exact match on post title
        $exact = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND post_title = %s LIMIT 1", $scraped_title));
        if ($exact) return $exact;

        // 2. Try exact match on original title meta
        $meta_exact = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_movie_original_title' AND meta_value = %s LIMIT 1", $scraped_title));
        if ($meta_exact) return $meta_exact;

        // 3. Try normalized title comparison
        $all_movies = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish'");
        foreach ($all_movies as $movie) {
            if (self::normalizeTitle($movie->post_title) === $norm_scraped) return $movie->ID;
            $orig = get_post_meta($movie->ID, '_movie_original_title', true);
            if ($orig && self::normalizeTitle($orig) === $norm_scraped) return $movie->ID;
        }

        // 4. Try dynamic Google Translation API match
        if (function_exists('ktn_translate_text_free')) {
            $translated_title = ktn_translate_text_free($scraped_title);
            if (!empty($translated_title) && $translated_title !== $scraped_title) {
                // Exact match on translated title
                $translated_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND post_title = %s LIMIT 1",
                    $translated_title
                ));
                if ($translated_id) return intval($translated_id);

                // Fuzzy match on translated title
                $like_translated = '%' . $wpdb->esc_like($translated_title) . '%';
                $translated_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND post_title LIKE %s LIMIT 1",
                    $like_translated
                ));
                if ($translated_id) return intval($translated_id);
            }
        }

        return null;
    }

    public static function syncCinema($post_id, $refresh_meta = true) {
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

        // --- Autofill Cinema Meta ---
        $best_name = !empty($metadata['english_name']) ? $metadata['english_name'] : (!empty($metadata['name']) ? $metadata['name'] : '');
        $current_post = get_post($post_id);

        if ($refresh_meta) {
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

            // Hierarchy Taxonomy
            $existing_terms = wp_get_object_terms($post_id, 'cinema_location', array('fields' => 'ids'));
            if (empty($existing_terms) || is_wp_error($existing_terms)) {
                $city = !empty($metadata['city']) ? sanitize_text_field($metadata['city']) : '';
                $area = !empty($metadata['area']) ? sanitize_text_field($metadata['area']) : '';
                if ($city) {
                    $city_term = wp_insert_term($city, 'cinema_location', array('parent' => 0));
                    $city_id = !is_wp_error($city_term) ? $city_term['term_id'] : (get_term_by('name', $city, 'cinema_location')->term_id ?? 0);
                    if ($city_id) {
                        $term_ids = array((int)$city_id);
                        if ($area) {
                            $area_term = wp_insert_term($area, 'cinema_location', array('parent' => $city_id));
                            $area_id = !is_wp_error($area_term) ? $area_term['term_id'] : 0;
                            if ($area_id) $term_ids[] = (int)$area_id;
                        }
                        wp_set_object_terms($post_id, $term_ids, 'cinema_location', false);
                    }
                }
            }
        }

        // --- Save Showtimes to Database ---
        $wpdb->delete($table_name, array('cinema_id' => $post_id));
        $added = 0;
        $matched_movie_ids = array();
        $unmatched_titles = array();

        foreach ($showtimes as $row) {
             $movie_id = self::matchMovieTitle($row['movie_title']);
             if ($movie_id) {
                 $matched_movie_ids[] = $movie_id;
             } else {
                 $unmatched_titles[] = $row['movie_title'];
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
        
        $matched_count = count(array_unique($matched_movie_ids));
        $unmatched_count = count(array_unique($unmatched_titles));

        // Success Logic
        if ($added > 0) {
            $msg = sprintf(__('Success: Synced %d showtimes.', 'kontentainment'), $added);
            update_post_meta($post_id, '_ktn_last_error', $msg);
            return array(
                'success'   => true, 
                'added'     => $added, 
                'matched'   => $matched_count,
                'unmatched' => $unmatched_count,
                'message'   => $msg
            );
        } else {
            $msg = __('No showtimes found at source.', 'kontentainment');
            update_post_meta($post_id, '_ktn_last_error', $msg);
            return array('success' => true, 'added' => 0, 'matched' => 0, 'unmatched' => 0, 'message' => $msg);
        }
    }

    public static function syncAllCinemas($auto_sync_only = false, $refresh_meta = true) {
        $args = array('post_type' => 'ktn_cinema', 'posts_per_page' => -1, 'post_status' => 'publish');
        $args['meta_query'] = array('relation' => 'AND', array('key' => '_ktn_cinema_status', 'value' => 'active', 'compare' => '='));
        if ($auto_sync_only) $args['meta_query'][] = array('key' => '_ktn_cinema_auto_sync', 'value' => 'yes', 'compare' => '=');
        
        $cinemas = get_posts($args);
        $total_added = 0;
        $count = 0;
        foreach ($cinemas as $cinema) {
             $res = self::syncCinema($cinema->ID, $refresh_meta);
             if (is_array($res) && isset($res['added'])) {
                 $total_added += $res['added'];
                 $count++;
             }
        }
        return array('total_cinemas' => $count, 'total_added' => $total_added);
    }
}