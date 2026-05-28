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