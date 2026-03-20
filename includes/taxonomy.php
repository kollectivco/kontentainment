<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'ktn_register_taxonomies');
function ktn_register_taxonomies()
{
    // Genre Taxonomy
    $genre_labels = array(
        'name' => _x('Genres', 'taxonomy general name', 'kontentainment'),
        'singular_name' => _x('Genre', 'taxonomy singular name', 'kontentainment'),
        'menu_name' => __('Genres', 'kontentainment'),
    );

    $genre_args = array(
        'hierarchical' => true,
        'labels' => $genre_labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'genre'),
    );
    register_taxonomy('ktn_genre', array('movie', 'tv_show'), $genre_args);

    // Cast Taxonomy
    $cast_labels = array(
        'name' => _x('Cast', 'taxonomy general name', 'kontentainment'),
        'singular_name' => _x('Actor', 'taxonomy singular name', 'kontentainment'),
        'menu_name' => __('Cast', 'kontentainment'),
    );

    $cast_args = array(
        'hierarchical' => false,
        'labels' => $cast_labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'cast'),
    );
    register_taxonomy('ktn_cast', array('movie', 'tv_show'), $cast_args);

    // Cinema Location Taxonomy (Hierarchical City > Area)
    $location_labels = array(
        'name' => _x('Cinema Locations', 'taxonomy general name', 'kontentainment'),
        'singular_name' => _x('Cinema Location', 'taxonomy singular name', 'kontentainment'),
        'menu_name' => __('Cinema Locations', 'kontentainment'),
    );

    $location_args = array(
        'hierarchical' => true,
        'labels' => $location_labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'location', 'with_front' => false),
    );
    // Continue supporting legacy cinema_area slug by using it as a rewrite if needed, 
    // but here we merge them for better structure.
    register_taxonomy('cinema_location', array('ktn_cinema'), $location_args);
    
    // Maintain cinema_area registration as alias for backward compatibility only if needed, 
    // but better to use a single cleaner one as requested.
}