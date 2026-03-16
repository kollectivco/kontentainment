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
}