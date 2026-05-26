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
    return $title;
}