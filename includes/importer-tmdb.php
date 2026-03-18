<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_ktn_import_movie', 'ktn_ajax_import_media');
function ktn_ajax_import_media()
{
    check_ajax_referer('ktn_import_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $imdb_id = isset($_POST['imdb_id']) ? sanitize_text_field($_POST['imdb_id']) : '';

    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => __('Permission denied.', 'kontentainment')));
    }

    if (!preg_match('/^tt\d{7,}$/', $imdb_id)) {
        wp_send_json_error(array('message' => __('Invalid IMDb ID format. Must match tt1234567.', 'kontentainment')));
    }

    $token = get_option('ktn_tmdb_bearer_token');
    if (empty($token)) {
        wp_send_json_error(array('message' => __('TMDB Bearer Token missing in settings.', 'kontentainment')));
    }

    $prevent_duplicates = get_option('ktn_prevent_duplicates', 1);
    $target_post_id = $post_id;

    if ($prevent_duplicates) {
        $existing_query = new WP_Query(array(
            'post_type' => array('movie', 'tv_show'),
            'meta_key' => '_movie_imdb_id',
            'meta_value' => $imdb_id,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'post__not_in' => array($post_id),
        ));
        if ($existing_query->have_posts()) {
            $target_post_id = $existing_query->posts[0]->ID;
        }
    }

    $default_language = get_option('ktn_default_language', 'en-US');

    $tmdb_res = ktn_get_tmdb_id_by_imdb($imdb_id, $token, $default_language);
    if (is_wp_error($tmdb_res)) {
        wp_send_json_error(array('message' => $tmdb_res->get_error_message()));
    }

    $tmdb_id = $tmdb_res['id'];
    $type = $tmdb_res['type'];

    $media_data = ktn_get_tmdb_media_details($tmdb_id, $type, $token, $default_language);
    if (is_wp_error($media_data)) {
        wp_send_json_error(array('message' => $media_data->get_error_message()));
    }

    $success = ktn_process_and_save_data($target_post_id, $media_data, $imdb_id, $type);
    if (is_wp_error($success)) {
        wp_send_json_error(array('message' => $success->get_error_message()));
    }

    wp_send_json_success(array(
        'message' => __('Media imported successfully!', 'kontentainment'),
        'target_post_id' => $target_post_id,
        'redirect' => get_edit_post_link($target_post_id, 'raw')
    ));
}

function ktn_get_tmdb_id_by_imdb($imdb_id, $token, $language)
{
    $cache_key = 'ktn_tmdb_find_' . md5($imdb_id . $language);
    $cached = get_transient($cache_key);
    if (false !== $cached) {
        return $cached;
    }

    $url = "https://api.themoviedb.org/3/find/{$imdb_id}?external_source=imdb_id&language={$language}";
    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ),
        'timeout' => 20
    );

    $response = wp_remote_get($url, $args);
    if (is_wp_error($response)) {
        return new WP_Error('tmdb_api_error', __('Failed to query TMDB.', 'kontentainment'));
    }

    $code = wp_remote_retrieve_response_code($response);
    if (200 !== $code) {
        return new WP_Error('tmdb_api_error', __('Error querying TMDB. HTTP Status: ', 'kontentainment') . $code);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!empty($data['movie_results']) && isset($data['movie_results'][0]['id'])) {
        $res = array('id' => $data['movie_results'][0]['id'], 'type' => 'movie');
    }
    elseif (!empty($data['tv_results']) && isset($data['tv_results'][0]['id'])) {
        $res = array('id' => $data['tv_results'][0]['id'], 'type' => 'tv');
    }
    else {
        return new WP_Error('tmdb_not_found', __('Media not found on TMDB.', 'kontentainment'));
    }

    set_transient($cache_key, $res, 6 * HOUR_IN_SECONDS);
    return $res;
}

function ktn_get_tmdb_media_details($tmdb_id, $type, $token, $language)
{
    $cache_key = 'ktn_tmdb_' . $type . '_' . md5($tmdb_id . $language);
    $cached = get_transient($cache_key);
    if (false !== $cached) {
        return $cached;
    }

    if ($type === 'tv') {
        $url = "https://api.themoviedb.org/3/tv/{$tmdb_id}?language={$language}&append_to_response=credits,videos,images,external_ids,content_ratings,keywords,recommendations";
    }
    else {
        $url = "https://api.themoviedb.org/3/movie/{$tmdb_id}?language={$language}&append_to_response=credits,videos,images,external_ids,release_dates,keywords,recommendations";
    }

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ),
        'timeout' => 20
    );

    $response = wp_remote_get($url, $args);
    if (is_wp_error($response)) {
        return new WP_Error('tmdb_api_error', __('Failed to query TMDB for details.', 'kontentainment'));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    set_transient($cache_key, $data, 6 * HOUR_IN_SECONDS);

    return $data;
}

function ktn_process_and_save_data($post_id, $data, $imdb_id, $type)
{
    $title           = $type === 'tv' ? ($data['name'] ?? '') : ($data['title'] ?? '');
    $original_title  = $type === 'tv' ? ($data['original_name'] ?? '') : ($data['original_title'] ?? '');
    $release_date    = $type === 'tv' ? ($data['first_air_date'] ?? '') : ($data['release_date'] ?? '');
    $overview        = $data['overview'] ?? '';
    $tagline         = $data['tagline'] ?? '';

    $runtime = 0;
    if ($type === 'tv' && !empty($data['episode_run_time'])) {
        $runtime = $data['episode_run_time'][0];
    }
    elseif ($type === 'movie') {
        $runtime = $data['runtime'] ?? 0;
    }

    $post_type_slug = $type === 'tv' ? 'tv_show' : 'movie';

    // Build Post Data
    $post_arr = array(
        'ID'           => $post_id,
        'post_type'    => $post_type_slug,
        'post_title'   => sanitize_text_field($title),
        'post_content' => wp_kses_post($overview),
        'post_status'  => get_post_status($post_id) ?: 'publish',
    );

    if (!empty($tagline)) {
        $post_arr['post_excerpt'] = sanitize_text_field($tagline);
    }
    else {
        $post_arr['post_excerpt'] = wp_trim_words(wp_kses_post($overview), 55);
    }

    // Update Post
    wp_update_post($post_arr);

    // Save Meta Data
    update_post_meta($post_id, '_movie_imdb_id', sanitize_text_field($imdb_id));
    update_post_meta($post_id, '_movie_tmdb_id', sanitize_text_field($data['id'] ?? ''));
    update_post_meta($post_id, '_movie_original_title', sanitize_text_field($original_title));
    update_post_meta($post_id, '_movie_tagline', sanitize_text_field($tagline));
    update_post_meta($post_id, '_movie_overview', wp_kses_post($overview));
    update_post_meta($post_id, '_movie_release_date', sanitize_text_field($release_date));
    update_post_meta($post_id, '_movie_runtime', absint($runtime));
    update_post_meta($post_id, '_movie_status', sanitize_text_field($data['status'] ?? ''));
    update_post_meta($post_id, '_movie_original_language', sanitize_text_field($data['original_language'] ?? ''));
    update_post_meta($post_id, '_movie_vote_average', floatval($data['vote_average'] ?? 0));
    update_post_meta($post_id, '_movie_vote_count', absint($data['vote_count'] ?? 0));
    update_post_meta($post_id, '_movie_popularity', floatval($data['popularity'] ?? 0));
    update_post_meta($post_id, '_movie_poster_path', sanitize_text_field($data['poster_path'] ?? ''));
    update_post_meta($post_id, '_movie_backdrop_path', sanitize_text_field($data['backdrop_path'] ?? ''));

    // Production details
    if (!empty($data['production_companies'])) {
        $companies = wp_list_pluck($data['production_companies'], 'name');
        update_post_meta($post_id, '_movie_production_companies', array_map('sanitize_text_field', $companies));
    }
    if (!empty($data['production_countries'])) {
        $countries = wp_list_pluck($data['production_countries'], 'name');
        update_post_meta($post_id, '_movie_production_countries', array_map('sanitize_text_field', $countries));
    }
    if (!empty($data['spoken_languages'])) {
        $languages = wp_list_pluck($data['spoken_languages'], 'name');
        update_post_meta($post_id, '_movie_spoken_languages', array_map('sanitize_text_field', $languages));
    }
    if (!empty($data['keywords']['keywords'])) {
        $keywords = wp_list_pluck($data['keywords']['keywords'], 'name');
        update_post_meta($post_id, '_movie_keywords', array_map('sanitize_text_field', $keywords));
    }
    elseif (!empty($data['keywords']['results'])) {
        $keywords = wp_list_pluck($data['keywords']['results'], 'name');
        update_post_meta($post_id, '_movie_keywords', array_map('sanitize_text_field', $keywords));
    }

    $director = '';
    $writers = array();
    $cast = array();
    $cast_limit = get_option('ktn_cast_limit', 10);

    if (!empty($data['credits']['crew'])) {
        foreach ($data['credits']['crew'] as $crew) {
            if ($crew['job'] === 'Director') {
                $director = $crew['name'];
            }
            if ($crew['department'] === 'Writing' || in_array($crew['job'], array('Writer', 'Screenplay', 'Story'))) {
                $writers[] = $crew['name'];
            }
        }
    }

    if (!empty($data['credits']['cast'])) {
        $cast_slice = array_slice($data['credits']['cast'], 0, $cast_limit);
        foreach ($cast_slice as $actor) {
            $cast[] = array(
                'name' => $actor['name'],
                'character' => $actor['character'],
                'profile_path' => $actor['profile_path']
            );
        }
    }

    update_post_meta($post_id, '_movie_director', sanitize_text_field($director));
    update_post_meta($post_id, '_movie_writers', array_map('sanitize_text_field', array_unique($writers)));
    update_post_meta($post_id, '_movie_cast', wp_json_encode($cast));

    if (!empty($data['videos']['results'])) {
        foreach ($data['videos']['results'] as $vid) {
            if ($vid['type'] === 'Trailer' && $vid['site'] === 'YouTube') {
                update_post_meta($post_id, '_movie_trailer_url', esc_url_raw('https://www.youtube.com/watch?v=' . $vid['key']));
                update_post_meta($post_id, '_movie_trailer_youtube_key', sanitize_text_field($vid['key']));
                break;
            }
        }
    }

    $default_region = get_option('ktn_default_region', 'US');
    $certification = '';
    if ($type === 'tv' && !empty($data['content_ratings']['results'])) {
        foreach ($data['content_ratings']['results'] as $rd) {
            if ($rd['iso_3166_1'] === $default_region) {
                $certification = $rd['rating'];
                break;
            }
        }
    }
    elseif ($type === 'movie' && !empty($data['release_dates']['results'])) {
        foreach ($data['release_dates']['results'] as $rd) {
            if ($rd['iso_3166_1'] === $default_region) {
                $certification = $rd['release_dates'][0]['certification'] ?? '';
                break;
            }
        }
    }
    update_post_meta($post_id, '_movie_release_certification', sanitize_text_field($certification));

    if (!empty($data['genres'])) {
        $genre_names = wp_list_pluck($data['genres'], 'name');
        wp_set_object_terms($post_id, $genre_names, 'ktn_genre', false);
    }

    if (!empty($cast)) {
        $cast_names = wp_list_pluck($cast, 'name');
        wp_set_object_terms($post_id, $cast_names, 'ktn_cast', false);
    }

    $download_images = get_option('ktn_download_images', 0);
    if ($download_images && !empty($data['poster_path'])) {
        $poster_url = "https://image.tmdb.org/t/p/original" . $data['poster_path'];
        $set_thumbnail = get_option('ktn_auto_set_featured_image', 0) ? true : false;
        if (!has_post_thumbnail($post_id) || !$set_thumbnail) {
            ktn_sideload_image($poster_url, $post_id, $set_thumbnail);
        }
    }

    update_post_meta($post_id, '_movie_last_imported_at', time());

    return true;
}