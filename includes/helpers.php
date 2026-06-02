<?php
if (!defined('ABSPATH')) {
    exit;
}

add_filter('single_template', 'ktn_load_single_template');
function ktn_load_single_template($template)
{
    global $post;

    if (in_array($post->post_type, array('movie', 'tv_show'))) {
        $custom_template = KTN_PLUGIN_DIR . 'templates/single-media.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    if ($post->post_type === 'ktn_cinema') {
        $custom_template = KTN_PLUGIN_DIR . 'templates/single-cinema.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    return $template;
}

add_filter('template_include', 'ktn_load_custom_templates');
function ktn_load_custom_templates($template)
{
    if (is_tax('ktn_cast')) {
        $custom_template = KTN_PLUGIN_DIR . 'templates/taxonomy-ktn_cast.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    if (is_tax('cinema_area')) {
        $custom_template = KTN_PLUGIN_DIR . 'templates/taxonomy-cinema_area.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    if (is_post_type_archive('ktn_cinema')) {
        $custom_template = KTN_PLUGIN_DIR . 'templates/archive-cinema.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    if (get_query_var('movies_status') === 'now-playing') {
        $custom_template = KTN_PLUGIN_DIR . 'templates/page-now-playing.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    if (get_query_var('movies_status') === 'coming-soon') {
        $custom_template = KTN_PLUGIN_DIR . 'templates/page-coming-soon.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    if (get_query_var('movies_status') === 'box-office') {
        $custom_template = KTN_PLUGIN_DIR . 'templates/page-box-office.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    return $template;
}

function ktn_sideload_image($url, $post_id, $set_as_thumbnail = false)
{
    if (!function_exists('media_handle_sideload')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $tmp = download_url($url);
    if (is_wp_error($tmp)) {
        return $tmp;
    }

    $file_array = array(
        'name' => basename(parse_url($url, PHP_URL_PATH)),
        'tmp_name' => $tmp
    );

    $id = media_handle_sideload($file_array, $post_id);

    if (is_wp_error($id)) {
        @unlink($file_array['tmp_name']);
        return $id;
    }

    if ($set_as_thumbnail) {
        set_post_thumbnail($post_id, $id);
    }

    return $id;
}

/**
 * Resolve the correct Arabic title for an Arabic movie on the frontend.
 */
function ktn_get_arabic_movie_title($post_id, $default_title) {
    $arabic_title = get_post_meta($post_id, '_movie_title_arabic', true);
    if (!empty($arabic_title)) {
        return $arabic_title;
    }

    $map = array(
        'asad' => 'أسد',
        'ezma' => 'أزمة',
        'bershama' => 'برشامة',
        'el kalam ala eh?!' => 'الكلام على إيه',
        'el kalam ala eh' => 'الكلام على إيه',
        'el kalam !?ala eh' => 'الكلام على إيه',
        'welad rizk 3' => 'ولاد رزق ٣',
        'welad rizk' => 'ولاد رزق ٣',
        '7 dogs' => 'الكلاب السبعة',
        'dogs 7' => 'الكلاب السبعة'
    );
    
    $clean_title = strtolower(trim($default_title));
    if (isset($map[$clean_title])) {
        $arabic_title = $map[$clean_title];
        update_post_meta($post_id, '_movie_title_arabic', $arabic_title);
        return $arabic_title;
    }

    // Also look up map in values
    foreach ($map as $eng => $ar) {
        if (trim(strtolower($ar)) === $clean_title) {
            $arabic_title = $ar;
            update_post_meta($post_id, '_movie_title_arabic', $arabic_title);
            return $arabic_title;
        }
    }

    // Query TMDB in Arabic dynamically if there is a TMDB ID
    $tmdb_id = get_post_meta($post_id, '_movie_tmdb_id', true);
    $token = get_option('ktn_tmdb_bearer_token');
    if ($tmdb_id && $token) {
        $post = get_post($post_id);
        $type = ($post && $post->post_type === 'tv_show') ? 'tv' : 'movie';
        if (function_exists('ktn_get_tmdb_media_details')) {
            $details = ktn_get_tmdb_media_details($tmdb_id, $type, $token, 'ar');
            if (!is_wp_error($details)) {
                $resolved = ($type === 'tv') ? ($details['name'] ?? '') : ($details['title'] ?? '');
                if (!empty($resolved)) {
                    $arabic_title = $resolved;
                    update_post_meta($post_id, '_movie_title_arabic', $arabic_title);
                    return $arabic_title;
                }
            }
        }
    }

    // Dynamic Google Translate API fallback
    if (function_exists('ktn_translate_text_free')) {
        $translated = ktn_translate_text_free($default_title);
        if (!empty($translated) && $translated !== $default_title) {
            $arabic_title = $translated;
            update_post_meta($post_id, '_movie_title_arabic', $arabic_title);
            return $arabic_title;
        }
    }

    return $default_title;
}

/**
 * Dynamically replace the title of cinemas with their Arabic names on the frontend.
 */
add_filter('the_title', 'ktn_translate_frontend_post_titles', 10, 2);
function ktn_translate_frontend_post_titles($title, $post_id = 0)
{
    if (is_admin()) {
        return $title;
    }
    if (!$post_id) {
        return $title;
    }
    $post = get_post($post_id);
    if (!$post) {
        return $title;
    }
    if ($post->post_type === 'ktn_cinema') {
        $arabic_name = get_post_meta($post_id, '_ktn_cinema_arabic_name', true) ?: get_post_meta($post_id, 'arabic_name', true);
        if (!empty($arabic_name)) {
            return $arabic_name;
        }
    }
    if ($post->post_type === 'movie' || $post->post_type === 'tv_show') {
        // If the website is viewed in Arabic and a custom/scraped Arabic title exists, use it!
        if (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) {
            $arabic_title = get_post_meta($post_id, '_movie_title_arabic', true);
            if (!empty($arabic_title)) {
                return $arabic_title;
            }
        }

        $original_lang = get_post_meta($post_id, '_movie_original_language', true);
        if ($original_lang === 'ar') {
            return ktn_get_arabic_movie_title($post_id, $title);
        }
        $original_title = get_post_meta($post_id, '_movie_original_title', true);
        if (!empty($original_title)) {
            return $original_title;
        }
    }
    return $title;
}

/**
 * Translate text from English to Arabic using Google's free Translate API.
 */
function ktn_translate_text_free($text, $sl = 'en', $tl = 'ar')
{
    if (empty($text)) {
        return '';
    }
    
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=" . urlencode($sl) . "&tl=" . urlencode($tl) . "&dt=t&q=" . urlencode($text);
    
    $response = wp_remote_get($url, array('timeout' => 15));
    if (is_wp_error($response)) {
        return $text;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (is_array($data) && isset($data[0])) {
        $translated = '';
        foreach ($data[0] as $sentence) {
            if (isset($sentence[0])) {
                $translated .= $sentence[0];
            }
        }
        return trim($translated);
    }
    
    return $text;
}

/**
 * Retrieve the movie overview, translated to Arabic if it is an Arabic movie.
 */
function ktn_get_translated_movie_overview($post_id)
{
    $overview = get_post_meta($post_id, '_movie_overview', true);
    $original_lang = get_post_meta($post_id, '_movie_original_language', true);
    
    if ($original_lang !== 'ar') {
        return $overview;
    }
    
    $translated = get_post_meta($post_id, '_movie_overview_arabic', true);
    if (!empty($translated)) {
        return $translated;
    }
    
    if (is_admin()) {
        return $overview;
    }
    
    $translated = ktn_translate_text_free($overview);
    if (!empty($translated)) {
        update_post_meta($post_id, '_movie_overview_arabic', $translated);
        return $translated;
    }
    
    return $overview;
}

/**
 * Retrieve the movie cast JSON, with names/characters translated to Arabic if it is an Arabic movie.
 */
function ktn_get_translated_movie_cast($post_id)
{
    $cast_json = get_post_meta($post_id, '_movie_cast', true);
    $original_lang = get_post_meta($post_id, '_movie_original_language', true);
    
    if ($original_lang !== 'ar' || empty($cast_json)) {
        return $cast_json;
    }
    
    $translated_json = get_post_meta($post_id, '_movie_cast_arabic', true);
    if (!empty($translated_json) && strpos($translated_json, '"u06') === false && strpos($translated_json, '"english_name"') !== false) {
        return $translated_json;
    }
    
    if (is_admin()) {
        return $cast_json;
    }
    
    $cast = json_decode($cast_json, true);
    if (is_array($cast)) {
        $lines = array();
        foreach ($cast as $actor) {
            $lines[] = isset($actor['name']) ? trim($actor['name']) : '';
            $lines[] = isset($actor['character']) ? trim($actor['character']) : '';
        }
        
        $bulk_text = implode("\n", $lines);
        $translated_bulk = ktn_translate_text_free($bulk_text);
        $translated_lines = preg_split('/\r\n|\r|\n/', $translated_bulk);
        
        $idx = 0;
        foreach ($cast as $key => $actor) {
            if (isset($actor['name'])) {
                $cast[$key]['english_name'] = $actor['name'];
                $cast[$key]['name'] = (!empty($translated_lines[$idx])) ? trim($translated_lines[$idx]) : $actor['name'];
                $idx++;
            }
            if (isset($actor['character'])) {
                $cast[$key]['character'] = (!empty($translated_lines[$idx])) ? trim($translated_lines[$idx]) : $actor['character'];
                $idx++;
            }
        }
        $translated_json = wp_json_encode($cast, JSON_UNESCAPED_UNICODE);
        update_post_meta($post_id, '_movie_cast_arabic', wp_slash($translated_json));
        return $translated_json;
    }
    
    return $cast_json;
}

/**
 * Retrieve the movie director, translated to Arabic if it is an Arabic movie.
 */
function ktn_get_translated_movie_director($post_id)
{
    $director = get_post_meta($post_id, '_movie_director', true);
    $original_lang = get_post_meta($post_id, '_movie_original_language', true);
    
    if ($original_lang !== 'ar' || empty($director)) {
        return $director;
    }
    
    $translated = get_post_meta($post_id, '_movie_director_arabic', true);
    if (!empty($translated)) {
        return $translated;
    }
    
    if (is_admin()) {
        return $director;
    }
    
    $translated = ktn_translate_text_free($director);
    if (!empty($translated)) {
        update_post_meta($post_id, '_movie_director_arabic', $translated);
        return $translated;
    }
    
    return $director;
}

/**
 * Retrieve the movie writers array, translated to Arabic if it is an Arabic movie.
 */
function ktn_get_translated_movie_writers($post_id)
{
    $writers = get_post_meta($post_id, '_movie_writers', true);
    $original_lang = get_post_meta($post_id, '_movie_original_language', true);
    
    if ($original_lang !== 'ar' || empty($writers) || !is_array($writers)) {
        return $writers;
    }
    
    $translated_json = get_post_meta($post_id, '_movie_writers_arabic', true);
    if (!empty($translated_json) && strpos($translated_json, '"u06') === false) {
        return json_decode($translated_json, true);
    }
    
    if (is_admin()) {
        return $writers;
    }
    
    $bulk_text = implode("\n", $writers);
    $translated_bulk = ktn_translate_text_free($bulk_text);
    $translated_lines = preg_split('/\r\n|\r|\n/', $translated_bulk);
    
    $translated_writers = array();
    foreach ($writers as $idx => $writer) {
        $translated_writers[] = (!empty($translated_lines[$idx])) ? trim($translated_lines[$idx]) : $writer;
    }
    
    update_post_meta($post_id, '_movie_writers_arabic', wp_slash(wp_json_encode($translated_writers, JSON_UNESCAPED_UNICODE)));
    return $translated_writers;
}

/**
 * Retrieve the cinema address, translated to Arabic on the frontend.
 */
function ktn_get_translated_cinema_address($post_id)
{
    $address = get_post_meta($post_id, '_ktn_cinema_address', true) ?: get_post_meta($post_id, 'address', true);
    if (empty($address)) {
        return '';
    }
    
    $translated = get_post_meta($post_id, '_ktn_cinema_address_arabic', true);
    if (!empty($translated)) {
        return $translated;
    }
    
    if (is_admin()) {
        return $address;
    }
    
    $translated = ktn_translate_text_free($address);
    if (!empty($translated)) {
        update_post_meta($post_id, '_ktn_cinema_address_arabic', $translated);
        return $translated;
    }
    
    return $address;
}

/**
 * Retrieve the cinema notes, translated to Arabic on the frontend.
 */
function ktn_get_translated_cinema_notes($post_id)
{
    $notes = get_post_meta($post_id, '_ktn_cinema_notes', true) ?: get_post_meta($post_id, 'notes', true);
    if (empty($notes)) {
        return '';
    }
    
    $translated = get_post_meta($post_id, '_ktn_cinema_notes_arabic', true);
    if (!empty($translated)) {
        return $translated;
    }
    
    if (is_admin()) {
        return $notes;
    }
    
    $translated = ktn_translate_text_free($notes);
    if (!empty($translated)) {
        update_post_meta($post_id, '_ktn_cinema_notes_arabic', $translated);
        return $translated;
    }
    
    return $notes;
}

/**
 * Translate standard English digits to Eastern Arabic digits on the frontend.
 */
function ktn_translate_digits($text)
{
    if (empty($text)) {
        return '';
    }
    if (is_admin()) {
        return $text;
    }
    $en_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    $ar_digits = array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩');
    return str_replace($en_digits, $ar_digits, $text);
}

/**
 * Format standard showtimes into Arabic format with Eastern Arabic digits and correct AM/PM localization.
 */
function ktn_format_show_time_arabic($time_str)
{
    if (empty($time_str)) {
        return '';
    }
    if (is_admin()) {
        return $time_str;
    }
    
    // Normalize time string
    $normalized = strtoupper(trim($time_str));
    
    // Detect AM/PM
    $is_pm = (strpos($normalized, 'PM') !== false);
    $is_am = (strpos($normalized, 'AM') !== false);
    
    // Remove AM/PM modifiers
    $clean_time = trim(str_replace(array('AM', 'PM'), '', $normalized));
    
    if (empty($clean_time)) {
        return ktn_translate_digits($time_str);
    }
    
    // Translate digits of the time (e.g. 11:00 to ١١:٠٠)
    $arabic_digits = ktn_translate_digits($clean_time);
    
    if ($is_pm) {
        return $arabic_digits . ' مساءً';
    } elseif ($is_am) {
        return $arabic_digits . ' صباحاً';
    }
    
    return $arabic_digits;
}

/**
 * Dynamically translate the cast term titles on the frontend.
 */
add_filter('single_term_title', 'ktn_translate_cast_term_title');
function ktn_translate_cast_term_title($title)
{
    if (is_admin()) {
        return $title;
    }
    if (is_tax('ktn_cast')) {
        $term = get_queried_object();
        if ($term && is_a($term, 'WP_Term')) {
            $arabic_name = get_term_meta($term->term_id, '_ktn_cast_arabic_name', true);
            if (!empty($arabic_name)) {
                return $arabic_name;
            }
        }
    }
    return $title;
}

add_filter('get_the_archive_title', 'ktn_translate_cast_archive_title');
function ktn_translate_cast_archive_title($title)
{
    if (is_admin()) {
        return $title;
    }
    if (is_tax('ktn_cast')) {
        $term = get_queried_object();
        if ($term && is_a($term, 'WP_Term')) {
            $arabic_name = get_term_meta($term->term_id, '_ktn_cast_arabic_name', true);
            if (!empty($arabic_name)) {
                return $arabic_name;
            }
        }
    }
    return $title;
}

/**
 * Shortcode for Box Office page
 */
add_shortcode('ktn_box_office', 'ktn_box_office_shortcode_handler');
function ktn_box_office_shortcode_handler() {
    ob_start();
    $custom_template = KTN_PLUGIN_DIR . 'templates/page-box-office.php';
    if (file_exists($custom_template)) {
        include $custom_template;
    }
    return ob_get_clean();
}

/**
 * Get local movie post ID by scraped title
 */
function ktn_get_movie_id_by_title($title) {
    if (empty($title)) {
        return 0;
    }

    $title = trim($title);

    // 0a. Check persistent manual matches option first
    $manual_matches = get_option('ktn_manual_movie_matches', array());
    if (isset($manual_matches[$title])) {
        return intval($manual_matches[$title]);
    }

    // 0b. Check transliteration map dictionary
    global $wpdb;
    $map = array(
        'asad' => 'أسد',
        'ezma' => 'أزمة',
        'bershama' => 'برشامة',
        'el kalam ala eh?!' => 'الكلام على إيه',
        'el kalam ala eh' => 'الكلام على إيه',
        'el kalam !?ala eh' => 'الكلام على إيه',
        '7 dogs' => 'الكلاب السبعة',
        'dogs 7' => 'الكلاب السبعة',
        'dogs' => 'الكلاب السبعة'
    );
    $clean_title = strtolower(trim($title));
    if (isset($map[$clean_title])) {
        $mapped_title = $map[$clean_title];
        $mapped_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND post_title = %s LIMIT 1", $mapped_title));
        if ($mapped_id) return intval($mapped_id);

        $like_mapped = '%' . $wpdb->esc_like($mapped_title) . '%';
        $mapped_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND post_title LIKE %s LIMIT 1", $like_mapped));
        if ($mapped_id) return intval($mapped_id);
    }

    // 1. Exact match on post title
    $query = new WP_Query(array(
        'post_type'      => 'movie',
        'title'          => $title,
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    ));

    if ($query->have_posts()) {
        $id = $query->posts[0]->ID;
        wp_reset_postdata();
        return $id;
    }
    wp_reset_postdata();

    // 2. Exact match on meta value (_movie_original_title)
    $post_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_movie_original_title' AND meta_value = %s LIMIT 1",
        $title
    ));

    if ($post_id) {
        return intval($post_id);
    }

    // 3. Partial Title Match
    $like_title = '%' . $wpdb->esc_like($title) . '%';
    $post_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND post_title LIKE %s LIMIT 1",
        $like_title
    ));

    if ($post_id) {
        return intval($post_id);
    }

    return 0;
}

/**
 * Get local movie post link by scraped title
 */
function ktn_get_movie_link_by_title($title) {
    $id = ktn_get_movie_id_by_title($title);
    if ($id) {
        return get_permalink($id);
    }
    return '#';
}

/**
 * Get display title for scraped movie title, using local database lookups or dynamic translation with transient caching
 */
function ktn_get_movie_display_title($scraped_title) {
    if (empty($scraped_title)) {
        return '';
    }

    $scraped_title = trim($scraped_title);

    // Check transient cache first
    $cache_key = 'ktn_title_ar_' . md5($scraped_title);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $arabic_title = '';

    // 0a. Check persistent manual matches option first
    $manual_matches = get_option('ktn_manual_movie_matches', array());
    if (isset($manual_matches[$scraped_title])) {
        $post_id = intval($manual_matches[$scraped_title]);
        $post = get_post($post_id);
        if ($post) {
            $arabic_title = $post->post_title;
        }
    }

    // 0b. Hardcoded Egyptian/Arabic movie dictionary map
    if (empty($arabic_title)) {
        global $wpdb;
        $map = array(
            'asad' => 'أسد',
            'ezma' => 'أزمة',
            'bershama' => 'برشامة',
            'el kalam ala eh?!' => 'الكلام على إيه',
            'el kalam ala eh' => 'الكلام على إيه',
            'el kalam !?ala eh' => 'الكلام على إيه',
            '7 dogs' => 'الكلاب السبعة',
            'dogs 7' => 'الكلاب السبعة',
            'dogs' => 'الكلاب السبعة'
        );
        $clean_title = strtolower(trim($scraped_title));
        if (isset($map[$clean_title])) {
            $mapped_title = $map[$clean_title];
            $mapped_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND post_title = %s LIMIT 1", $mapped_title));
            if ($mapped_id) {
                $post = get_post($mapped_id);
                if ($post) {
                    $arabic_title = $post->post_title;
                }
            } else {
                // Fuzzy/LIKE matching for mapped title
                $like_mapped = '%' . $wpdb->esc_like($mapped_title) . '%';
                $mapped_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND post_title LIKE %s LIMIT 1", $like_mapped));
                if ($mapped_id) {
                    $post = get_post($mapped_id);
                    if ($post) {
                        $arabic_title = $post->post_title;
                    }
                } else {
                    // Fallback to dictionary term itself
                    $arabic_title = $mapped_title;
                }
            }
        }
    }

    // 1. Exact match on database post title
    if (empty($arabic_title)) {
        $query = new WP_Query(array(
            'post_type'      => 'movie',
            'title'          => $scraped_title,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'no_found_rows'  => true,
        ));

        if ($query->have_posts()) {
            $arabic_title = $query->posts[0]->post_title;
        }
        wp_reset_postdata();
    }

    // 2. Exact match on meta value (_movie_original_title)
    if (empty($arabic_title)) {
        global $wpdb;
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_movie_original_title' AND meta_value = %s LIMIT 1",
            $scraped_title
        ));

        if ($post_id) {
            $post = get_post($post_id);
            if ($post) {
                $arabic_title = $post->post_title;
            }
        }
    }

    // 3. Partial Title Match
    if (empty($arabic_title)) {
        global $wpdb;
        $like_title = '%' . $wpdb->esc_like($scraped_title) . '%';
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_status = 'publish' AND post_title LIKE %s LIMIT 1",
            $like_title
        ));

        if ($post_id) {
            $post = get_post($post_id);
            if ($post) {
                $arabic_title = $post->post_title;
            }
        }
    }

    // 4. Fallback: Dynamic Google Translation API
    if (empty($arabic_title)) {
        if (function_exists('ktn_translate_text_free')) {
            $translated = ktn_translate_text_free($scraped_title);
            if (!empty($translated) && $translated !== $scraped_title) {
                $arabic_title = $translated;
            }
        }
    }

    // If still empty or translation failed, use scraped title and translate its digits
    if (empty($arabic_title)) {
        $arabic_title = ktn_translate_digits($scraped_title);
    }

    // Cache the resolved Arabic title for 30 days
    set_transient($cache_key, $arabic_title, 30 * DAY_IN_SECONDS);

    return $arabic_title;
}

/**
 * Check if the current page contains the Cinema Guides or Box Office shortcodes or matching slugs
 */
function ktn_is_cinema_guides_page() {
    if (is_page('cinema-guides') || is_page('cinema-guide') || is_page('دليل السينما') || get_query_var('movies_status') === 'box-office') {
        return true;
    }
    
    global $post;
    if (is_a($post, 'WP_Post')) {
        // Standard post content check
        if (has_shortcode($post->post_content, 'ktn_cinema_guides') || 
            has_shortcode($post->post_content, 'ktn_box_office') ||
            stripos($post->post_content, 'ktn_cinema_guides') !== false ||
            stripos($post->post_content, 'ktn_box_office') !== false) {
            return true;
        }


        // Elementor page builder storage check
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if (!empty($elementor_data)) {
            if (stripos($elementor_data, 'ktn_cinema_guides') !== false || 
                stripos($elementor_data, 'ktn_box_office') !== false) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Force full-width layout by adding body class and disabling active sidebars on Cinema Guides pages
 */
add_filter('body_class', 'ktn_add_body_class_for_guides');
function ktn_add_body_class_for_guides($classes) {
    if (ktn_is_cinema_guides_page()) {
        $classes[] = 'ktn-full-width-page';
    }
    return $classes;
}

add_filter('is_active_sidebar', 'ktn_disable_sidebar_on_guides', 999, 1);
function ktn_disable_sidebar_on_guides($is_active) {
    if (ktn_is_cinema_guides_page()) {
        return false;
    }
    return $is_active;
}

/**
 * Self-healing cleanup hook to automatically remove incorrect cached title for 7 Dogs
 */
add_action('init', 'ktn_cleanup_incorrect_movie_meta');
function ktn_cleanup_incorrect_movie_meta() {
    global $wpdb;
    
    // Find post ID of 7 Dogs by post ID, TMDB ID, original title, or title
    $dogs_post_id = 0;
    
    // Check by post ID first
    $post_check = get_post(243944);
    if ($post_check && $post_check->post_type === 'movie') {
        $dogs_post_id = 243944;
    } else {
        // Fallback: search by TMDB ID
        $dogs_post_id = $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_movie_tmdb_id' AND meta_value = '1316427' LIMIT 1");
    }
    
    if (!$dogs_post_id) {
        // Fallback: search by original title meta containing 'الكلاب السبعة'
        $dogs_post_id = $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_movie_original_title' AND (meta_value = 'الكلاب السبعة' OR meta_value = '7 Dogs') LIMIT 1");
    }
    
    if (!$dogs_post_id) {
        // Fallback: search by post title
        $dogs_post_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'movie' AND post_title = '7 Dogs' LIMIT 1");
    }
    
    if ($dogs_post_id) {
        $dogs_post_id = intval($dogs_post_id);
        
        // 1. Force the correct title and slug in wp_posts
        $wpdb->update(
            $wpdb->posts,
            array(
                'post_title' => '7 Dogs',
                'post_name'  => '7-dogs'
            ),
            array('ID' => $dogs_post_id)
        );
        
        // 2. Force the correct metadata
        update_post_meta($dogs_post_id, '_movie_title_arabic', 'الكلاب السبعة');
        update_post_meta($dogs_post_id, '_movie_original_title', '7 Dogs');
        update_post_meta($dogs_post_id, '_movie_original_language', 'en');
        delete_post_meta($dogs_post_id, '_movie_title_arabic_cached');
        clean_post_cache($dogs_post_id);
    }
    
    // 3. Search for any other post meta where _movie_title_arabic = 'ولاد رزق ٣'
    // but the original title contains 'dogs', '7', or is 'الكلاب السبعة' or '7 Dogs'
    $meta_results = $wpdb->get_results("
        SELECT post_id 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = '_movie_title_arabic' 
          AND meta_value = 'ولاد رزق ٣'
    ");
    
    if (!empty($meta_results)) {
        foreach ($meta_results as $row) {
            $orig = get_post_meta($row->post_id, '_movie_original_title', true);
            if (stripos($orig, 'dogs') !== false || 
                stripos($orig, '7') !== false || 
                $orig === 'الكلاب السبعة' || 
                $orig === '7 Dogs') {
                update_post_meta($row->post_id, '_movie_title_arabic', 'الكلاب السبعة');
                update_post_meta($row->post_id, '_movie_original_title', '7 Dogs');
                update_post_meta($row->post_id, '_movie_original_language', 'en');
            }
        }
    }
}