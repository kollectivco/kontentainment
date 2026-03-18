<?php
if (!defined('ABSPATH')) {
    exit;
}

class Ktn_Cinema_Importer
{

    public static function normalizeTitle($title)
    {
        $title = strtolower(trim($title));
        // Remove punctuation
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

        // 1. Exact match against post_title
        $exact = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND post_title = %s LIMIT 1", $scraped_title));
        if ($exact)
            return $exact;

        // 2. Exact match against _movie_original_title
        $meta_exact = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_movie_original_title' AND meta_value = %s LIMIT 1", $scraped_title));
        if ($meta_exact)
            return $meta_exact;

        // 3. Normalized match
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

        return null; // Unmatched
    }

    public static function syncCinema($post_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktn_showtimes';

        // FORCE TABLE CREATION (Bypass dbDelta strictness in MySQL 8)
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

        if (!$source_url || $status === 'inactive')
            return false;

        $results = array();
        if ($source_type === 'elcinema_theater' || empty($source_type)) {
            $results = Ktn_Cinema_Scraper::fetch_from_elcinema($post_id, $source_url);
        }

        if ($results === false || empty($results)) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ktn_showtimes';

        $added = 0;
        foreach ($results as $row) {
            $matched_id = self::matchMovieTitle($row['movie_title_scraped']);

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE cinema_id = %d AND movie_title_scraped = %s AND show_date = %s AND show_time = %s",
                $row['cinema_id'],
                $row['movie_title_scraped'],
                $row['show_date'],
                $row['show_time']
            ));

            if ($exists) {
                $wpdb->update(
                    $table_name,
                    array(
                    // Only update matched ID if it was unmatched or we found a new match
                    'matched_movie_id' => $matched_id ? $matched_id : null,
                    'experience' => $row['experience'],
                    'price_text' => $row['price_text'],
                    'updated_at' => current_time('mysql')
                ),
                    array('id' => $exists)
                );
            }
            else {
                $insert_res = $wpdb->insert(
                    $table_name,
                    array(
                    'cinema_id' => $row['cinema_id'],
                    'cinema_name' => get_the_title($row['cinema_id']) ?: $row['cinema_name'],
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

                if ($insert_res === false && !empty($wpdb->last_error)) {
                    update_post_meta($post_id, '_ktn_last_error', 'DB Insert Error: ' . $wpdb->last_error);
                    return false;
                }
                $added++;
            }
        }

        $prev_error = get_post_meta($post_id, '_ktn_last_error', true);
        if (strpos($prev_error, 'DB Insert Error') === false) {
            update_post_meta($post_id, '_ktn_last_error', $prev_error . " | Inserted/Updated DB rows successfully.");
        }

        update_post_meta($post_id, '_ktn_cinema_last_sync', current_time('mysql'));
        return count($results);
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
            if ($count !== false) {
                $total += $count;
            }
        }
        return $total;
    }
}