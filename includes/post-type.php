<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'ktn_register_post_types');
function ktn_register_post_types()
{
    // Movie Post Type
    $movie_labels = array(
        'name'               => _x('Movies', 'post type general name', 'kontentainment'),
        'singular_name'      => _x('Movie', 'post type singular name', 'kontentainment'),
        'menu_name'          => _x('Kontentainment', 'admin menu', 'kontentainment'),
        'name_admin_bar'     => _x('Movie', 'add new on admin bar', 'kontentainment'),
        'add_new'            => _x('Add New Movie', 'movie', 'kontentainment'),
        'add_new_item'       => __('Add New Movie', 'kontentainment'),
        'new_item'           => __('New Movie', 'kontentainment'),
        'edit_item'          => __('Edit Movie Details', 'kontentainment'),
        'view_item'          => __('View Movie', 'kontentainment'),
        'all_items'          => __('Movies', 'kontentainment'),
        'search_items'       => __('Search Movies', 'kontentainment'),
        'not_found'          => __('No movies found.', 'kontentainment'),
        'not_found_in_trash' => __('No movies found in Trash.', 'kontentainment')
    );

    $movie_args = array(
        'labels'             => $movie_labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'movie'),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-video-alt3',
        'supports'           => array('title', 'editor', 'excerpt', 'thumbnail')
    );
    register_post_type('movie', $movie_args);

    // TV Show Post Type
    $tv_labels = array(
        'name' => _x('TV Shows', 'post type general name', 'kontentainment'),
        'singular_name' => _x('TV Show', 'post type singular name', 'kontentainment'),
        'menu_name' => _x('TV Shows', 'admin menu', 'kontentainment'),
        'name_admin_bar' => _x('TV Show', 'add new on admin bar', 'kontentainment'),
        'add_new' => _x('Add New TV Show', 'tv_show', 'kontentainment'),
        'all_items' => __('TV Shows', 'kontentainment'),
    );

    $tv_args = array(
        'labels' => $tv_labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => 'edit.php?post_type=movie',
        'query_var' => true,
        'rewrite' => array('slug' => 'tv-show'),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => false,
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail')
    );
    register_post_type('tv_show', $tv_args);

    // Cinema Sources Post Type
    $cinema_labels = array(
        'name' => _x('Cinema Sources', 'post type general name', 'kontentainment'),
        'singular_name' => _x('Cinema Source', 'post type singular name', 'kontentainment'),
        'menu_name' => _x('Cinema Sources', 'admin menu', 'kontentainment'),
        'add_new' => _x('Add New Source', 'cinema', 'kontentainment'),
        'all_items' => __('Cinema Sources', 'kontentainment'),
    );

    $cinema_args = array(
        'labels' => $cinema_labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => 'edit.php?post_type=movie',
        'rewrite' => array('slug' => 'cinema', 'with_front' => false),
        'has_archive' => 'cinemas',
        'capability_type' => 'post',
        'hierarchical' => false,
        'supports' => array('title')
    );
    register_post_type('ktn_cinema', $cinema_args);

    // Custom rewrite rules for movies statuses
    add_rewrite_rule('^movies/now-playing/?$', 'index.php?post_type=movie&movies_status=now-playing', 'top');
    add_rewrite_rule('^movies/coming-soon/?$', 'index.php?post_type=movie&movies_status=coming-soon', 'top');
}

add_filter('query_vars', 'ktn_add_query_vars');
function ktn_add_query_vars($vars) {
    $vars[] = 'movies_status';
    return $vars;
}
add_filter('manage_movie_posts_columns', 'ktn_media_columns');
add_filter('manage_tv_show_posts_columns', 'ktn_media_columns');
function ktn_media_columns($columns)
{
    $new_columns = array();
    foreach ($columns as $key => $value) {
        if ($key == 'date') {
            $new_columns['ktn_imdb_id'] = __('IMDb ID', 'kontentainment');
            $new_columns['ktn_tmdb_id'] = __('TMDB ID', 'kontentainment');
            $new_columns['ktn_status'] = __('Import Status', 'kontentainment');
        }
        $new_columns[$key] = $value;
    }
    return $new_columns;
}

add_action('manage_movie_posts_custom_column', 'ktn_media_custom_column', 10, 2);
add_action('manage_tv_show_posts_custom_column', 'ktn_media_custom_column', 10, 2);
function ktn_media_custom_column($column, $post_id)
{
    switch ($column) {
        case 'ktn_imdb_id':
            $imdb_id = get_post_meta($post_id, '_movie_imdb_id', true);
            echo esc_html($imdb_id ? $imdb_id : '-');
            break;
        case 'ktn_tmdb_id':
            $tmdb_id = get_post_meta($post_id, '_movie_tmdb_id', true);
            echo esc_html($tmdb_id ? $tmdb_id : '-');
            break;
        case 'ktn_status':
            $imported_at = get_post_meta($post_id, '_movie_last_imported_at', true);
            if ($imported_at) {
                echo '<span style="color: green;">&#10003; Imported</span>';
            }
            else {
                echo '-';
            }
            break;
    }
}