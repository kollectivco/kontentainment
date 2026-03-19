<?php
if (!defined('ABSPATH')) {
    exit;
}

class Ktn_Cinema_Importer
{

    public static function normalizeTitle($title)
    {
        $title = strtolower(trim($title));
        $title = preg_replace('/[^\w\s-]/u', '', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        return trim($title);
    }

    public static function matchMovieTitle($scraped_title)
    {
        global $wpdb;

        $norm_scraped = self::normalizeTitle($scraped_title);
        if (empty($norm_scraped))
            return null;

        $exact = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND post_title = %s LIMIT 1", $scraped_title));
        if ($exact)
            return $exact;

        $meta_exact = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_movie_original_title' AND meta_value = %s LIMIT 1", $scraped_title));
        if ($meta_exact)
            return $meta_exact;

        $all_movies = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish'");

        foreach ($all_movies as $movie) {
            $norm_post = self::normalizeTitle($movie->post_title);
            if ($norm_post === $norm_scraped) {
                return $movie->ID;
            }

            $orig_title = get_post_meta($movie->ID, '_movie_original_title', true);
            if ($orig_title) {
                $norm_orig = self::normalizeTitle($orig_title);
                if ($norm_orig === $norm_scraped) {
                    return $movie->ID;
                }
            }
        }

        return null;
    }

    /**
     * @return int|WP_Error Number of showtimes synced, or WP_Error on failure.
     */
    public static function syncCinema($post_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktn_showtimes';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
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
            ) $charset_collate;";
            $wpdb->query($sql);
        }

        $source_url = get_post_meta($post_id, '_ktn_cinema_url', true);
        $source_type = get_post_meta($post_id, '_ktn_cinema_type', true);
        $status = get_post_meta($post_id, '_ktn_cinema_status', true);

        if (!$source_url) {
            return new WP_Error('missing_url', __('Missing Source URL.', 'kontentainment'));
        }
        if ($status === 'inactive') {
            return new WP_Error('inactive_cinema', __('Cinema is set to inactive.', 'kontentainment'));
        }

        $sync_data = array();
        if ($source_type === 'elcinema_theater' || empty($source_type)) {
            $sync_data = Ktn_Cinema_Scraper::fetch_from_elcinema($post_id, $source_url);
        }

        if (is_wp_error($sync_data)) {
            return $sync_data;
        }

        if (empty($sync_data['showtimes'])) {
            return new WP_Error('no_results', __('Scraper successfully but no showtimes found.', 'kontentainment'));
        }

        $results = $sync_data['showtimes'];
        $metadata = $sync_data['metadata'];

        // --- Auto-fill Cinema Fields ---
        $display_title = !empty($metadata['english_name']) ? $metadata['english_name'] : $metadata['name'];
        if (empty($display_title)) $display_title = $metadata['name'];

        $current_title = get_the_title($post_id);
        if (empty($current_title) || stripos($current_title, 'Auto Draft') !== false || is_numeric($current_title)) {
             wp_update_post(array(
                 'ID' => $post_id,
                 'post_title' => sanitize_text_field($display_title)
             ));
        }

        // Mapping values to Cinema admin fields
        if (!empty($metadata['theater_id'])) {
            update_post_meta($post_id, '_ktn_cinema_theater_id', sanitize_text_field($metadata['theater_id']));
        }
        if (!empty($metadata['arabic_name'])) {
            update_post_meta($post_id, '_ktn_cinema_arabic_name', sanitize_text_field($metadata['arabic_name']));
        }
        if (!empty($metadata['english_name'])) {
            update_post_meta($post_id, '_ktn_cinema_english_name', sanitize_text_field($metadata['english_name']));
        }
        if (!empty($metadata['logo'])) {
            update_post_meta($post_id, '_ktn_cinema_logo', esc_url_raw($metadata['logo']));
        }
        if (!empty($metadata['rating'])) {
            update_post_meta($post_id, '_ktn_cinema_rating', sanitize_text_field($metadata['rating']));
        }
        if (!empty($metadata['address'])) {
            update_post_meta($post_id, '_ktn_cinema_address', sanitize_text_field($metadata['address']));
        }
        if (!empty($metadata['area'])) {
            update_post_meta($post_id, '_ktn_cinema_area', sanitize_text_field($metadata['area']));
        }
        if (!empty($metadata['phone'])) {
            update_post_meta($post_id, '_ktn_cinema_phone', sanitize_text_field($metadata['phone']));
        }
        if (!empty($metadata['city'])) {
            update_post_meta($post_id, '_ktn_cinema_city', sanitize_text_field($metadata['city']));
        }
        if (!empty($metadata['country'])) {
            update_post_meta($post_id, '_ktn_cinema_country', sanitize_text_field($metadata['country']));
        }

        // Notes / Descriptions (Policies)
        if (!empty($metadata['notes'])) {
             wp_update_post(array(
                 'ID' => $post_id,
                 'post_content' => wp_kses_post($metadata['notes'])
             ));
        }

        $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE cinema_id = %d", $post_id));

        $added = 0;
        foreach ($results as $row) {
            $matched_id = self::matchMovieTitle($row['movie_title_scraped']);
            $wpdb->insert(
                $table_name,
                array(
                'cinema_id' => $row['cinema_id'],
                'cinema_name' => $display_title,
                'source_url' => $row['source_url'],
                'movie_title_scraped' => $row['movie_title_scraped'],
                'matched_movie_id' => $matched_id ? $matched_id : null,
                'show_date' => $row['show_date'],
                'show_time' => $row['show_time'],
                'experience' => $row['experience'],
                'price_text' => $row['price_text'],
                'source_type' => $source_type,
                'scraped_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
                )
            );
            $added++;
        }

        update_post_meta($post_id, '_ktn_cinema_last_sync', current_time('mysql'));
        return $added;
    }

    public static function syncAllCinemas()
    {
        $cinemas = get_posts(array(
            'post_type' => 'ktn_cinema',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));

        $total = 0;
        foreach ($cinemas as $cinema) {
            $count = self::syncCinema($cinema->ID);
            if (!is_wp_error($count)) {
                $total += $count;
            }
        }
        return $total;
    }
}