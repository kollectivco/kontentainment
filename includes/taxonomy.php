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

/**
 * Add Locations to Kontentainment Admin Menu
 */
add_action('admin_menu', 'ktn_add_locations_to_menu');
function ktn_add_locations_to_menu() {
    add_submenu_page(
        'edit.php?post_type=movie',
        __('Cinema Locations', 'kontentainment'),
        __('Locations', 'kontentainment'),
        'manage_categories',
        'edit-tags.php?taxonomy=cinema_location&post_type=ktn_cinema'
    );
}

/**
 * Automatically seed the initial governorates and areas.
 */
add_action('admin_init', 'ktn_seed_cinema_locations');
function ktn_seed_cinema_locations() {
    if (get_option('ktn_seeded_locations_v2')) {
        return; // Already seeded v2
    }

    $locations = [
        'cairo' => [
            'name' => 'القاهرة',
            'old_name' => 'محافظة القاهرة',
            'cities' => [
                'nasr-city' => 'مدينة نصر',
                'downtown-cairo' => ['name' => 'وسط البلد', 'old_name' => 'وسط البلد'],
                'heliopolis' => 'مصر الجديدة',
                'new-cairo' => 'القاهرة الجديدة',
                'shoubra' => 'شبرا',
                'garden-city' => 'جاردن سيتي',
                'maadi' => 'المعادي',
                'rehab-city' => 'مدينة الرحاب',
                'hadayeq-el-qobbah' => 'حدائق القبة',
                'kattameya' => 'القطامية',
                'zamalek' => 'الزمالك',
                'manial-el-roda' => 'منيل الروضة',
                'helwan' => 'حلوان',
                'rod-el-farag' => 'روض الفرج'
            ]
        ],
        'giza' => [
            'name' => 'الجيزة',
            'old_name' => 'محافظة الجيزة',
            'cities' => [
                '6th-of-october' => '6 أكتوبر',
                'dokki' => 'الدقي',
                'haram' => 'الهرم'
            ]
        ],
        'alexandria' => [
            'name' => 'الأسكندرية',
            'old_name' => 'محافظة الأسكندرية',
            'cities' => [
                'north-coast' => 'الساحل الشمالي',
                'agami' => 'عجمى',
                'downtown-alexandria' => ['name' => 'وسط البلد', 'old_name' => 'وسط البلد'],
                'montaza' => 'المنتزة',
                'moharam-bek' => 'محرم بك',
                'anfoushi' => 'الأنفوشي',
                'gleem' => 'جليم',
                'smouha' => 'سموحة',
                'mostafa-kamel' => 'مصطفى كامل',
                'roshdy' => 'رشدي'
            ]
        ]
    ];

    foreach ($locations as $gov_slug => $gov_data) {
        $gov_name = $gov_data['name'];
        $gov_old = $gov_data['old_name'];
        
        // Find existing by old name, new name, or slug
        $gov_term = term_exists($gov_old, 'cinema_location') ?: (term_exists($gov_name, 'cinema_location') ?: term_exists($gov_slug, 'cinema_location'));
        
        if (!$gov_term) {
            $gov_term = wp_insert_term($gov_name, 'cinema_location', ['slug' => $gov_slug]);
        } else {
            // Update existing term to use new name and english slug
            wp_update_term($gov_term['term_id'], 'cinema_location', [
                'name' => $gov_name,
                'slug' => $gov_slug
            ]);
        }
        
        if (!is_wp_error($gov_term) && isset($gov_term['term_id'])) {
            $parent_id = $gov_term['term_id'];
            
            foreach ($gov_data['cities'] as $city_slug => $city_val) {
                $city_name = is_array($city_val) ? $city_val['name'] : $city_val;
                $city_old = is_array($city_val) ? $city_val['old_name'] : $city_val;

                // Find existing by old name or new name or slug (under this parent)
                $city_term = term_exists($city_old, 'cinema_location', $parent_id) ?: (term_exists($city_name, 'cinema_location', $parent_id) ?: term_exists($city_slug, 'cinema_location', $parent_id));
                
                if (!$city_term) {
                    wp_insert_term($city_name, 'cinema_location', ['parent' => $parent_id, 'slug' => $city_slug]);
                } else {
                    wp_update_term($city_term['term_id'], 'cinema_location', [
                        'name' => $city_name,
                        'slug' => $city_slug
                    ]);
                }
            }
        }
    }

    update_option('ktn_seeded_locations_v2', true);
}