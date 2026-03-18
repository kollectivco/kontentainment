<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Cinema Meta Boxes
 */
add_action('add_meta_boxes', 'ktn_cinema_register_all_meta_boxes');
function ktn_cinema_register_all_meta_boxes()
{
    // Main Area
    add_meta_box('ktn_cinema_basic_info', __('Basic Information', 'kontentainment'), 'ktn_cinema_basic_info_callback', 'ktn_cinema', 'normal', 'high');
    add_meta_box('ktn_cinema_location', __('Location Information', 'kontentainment'), 'ktn_cinema_location_callback', 'ktn_cinema', 'normal', 'high');
    add_meta_box('ktn_cinema_showtimes', __('Current Showtimes', 'kontentainment'), 'ktn_cinema_showtimes_callback', 'ktn_cinema', 'normal', 'default');

    // Side Area
    add_meta_box('ktn_cinema_source', __('Source & Sync Settings', 'kontentainment'), 'ktn_cinema_source_callback', 'ktn_cinema', 'side', 'high');
    add_meta_box('ktn_cinema_branding', __('Branding & Media', 'kontentainment'), 'ktn_cinema_branding_callback', 'ktn_cinema', 'side', 'default');
    add_meta_box('ktn_cinema_stats', __('Cinema Statistics', 'kontentainment'), 'ktn_cinema_stats_callback', 'ktn_cinema', 'side', 'low');
}

/**
 * Basic Information Callback
 */
function ktn_cinema_basic_info_callback($post)
{
    wp_nonce_field('ktn_cinema_save_nonce', 'ktn_cinema_nonce');

    $arabic_name = get_post_meta($post->ID, '_ktn_cinema_arabic_name', true);
    $english_name = get_post_meta($post->ID, '_ktn_cinema_english_name', true);

    echo '<div class="ktn-admin-field-group">';
    echo '<p><label><strong>' . __('Arabic Name', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_arabic_name" value="' . esc_attr($arabic_name) . '" class="large-text"></p>';
    
    echo '<p><label><strong>' . __('English Name', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_english_name" value="' . esc_attr($english_name) . '" class="large-text"></p>';
    echo '</div>';
}

/**
 * Location Information Callback
 */
function ktn_cinema_location_callback($post)
{
    $address = get_post_meta($post->ID, '_ktn_cinema_address', true);
    $city = get_post_meta($post->ID, '_ktn_cinema_city', true);
    $country = get_post_meta($post->ID, '_ktn_cinema_country', true);
    $maps_url = get_post_meta($post->ID, '_ktn_cinema_maps_url', true);
    $lat = get_post_meta($post->ID, '_ktn_cinema_latitude', true);
    $lng = get_post_meta($post->ID, '_ktn_cinema_longitude', true);

    echo '<div class="ktn-admin-grid-fields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
    
    echo '<p style="grid-column: span 2;"><label><strong>' . __('Full Address', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_address" value="' . esc_attr($address) . '" class="large-text"></p>';

    echo '<p><label><strong>' . __('City', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_city" value="' . esc_attr($city) . '" class="regular-text"></p>';

    echo '<p><label><strong>' . __('Country', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_country" value="' . esc_attr($country) . '" class="regular-text"></p>';

    echo '<p style="grid-column: span 2;"><label><strong>' . __('Google Maps / Directions URL', 'kontentainment') . '</strong></label><br>';
    echo '<input type="url" name="ktn_cinema_maps_url" value="' . esc_attr($maps_url) . '" class="large-text"></p>';

    echo '<p><label><strong>' . __('Latitude', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_latitude" value="' . esc_attr($lat) . '" class="regular-text"></p>';

    echo '<p><label><strong>' . __('Longitude', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_longitude" value="' . esc_attr($lng) . '" class="regular-text"></p>';

    echo '</div>';
}

/**
 * Branding & Media Callback
 */
function ktn_cinema_branding_callback($post)
{
    $logo = get_post_meta($post->ID, '_ktn_cinema_logo', true);
    $cover = get_post_meta($post->ID, '_ktn_cinema_cover_image', true);

    echo '<div class="ktn-admin-side-fields">';
    
    echo '<p><label><strong>' . __('Cinema Logo URL', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_logo" value="' . esc_attr($logo) . '" class="large-text">';
    if ($logo) echo '<img src="'.esc_url($logo).'" style="max-width:100%; height:auto; display:block; margin-top:10px; border:1px solid #ddd;">';
    echo '</p>';

    echo '<p><label><strong>' . __('Cover Image URL', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_cover_image" value="' . esc_attr($cover) . '" class="large-text">';
    if ($cover) echo '<img src="'.esc_url($cover).'" style="max-width:100%; height:auto; display:block; margin-top:10px; border:1px solid #ddd;">';
    echo '</p>';

    echo '</div>';
}

/**
 * Source & Sync Settings Callback
 */
function ktn_cinema_source_callback($post)
{
    $url = get_post_meta($post->ID, '_ktn_cinema_url', true);
    $type = get_post_meta($post->ID, '_ktn_cinema_type', true) ?: 'elcinema_theater';
    $status = get_post_meta($post->ID, '_ktn_cinema_status', true) ?: 'active';
    $last_sync = get_post_meta($post->ID, '_ktn_cinema_last_sync', true);

    echo '<div class="ktn-admin-side-fields">';
    
    echo '<p><label><strong>' . __('Source URL', 'kontentainment') . '</strong></label><br>';
    echo '<input type="url" name="ktn_cinema_url" value="' . esc_attr($url) . '" class="large-text" required></p>';

    echo '<p><label><strong>' . __('Source Type', 'kontentainment') . '</strong></label><br>';
    echo '<select name="ktn_cinema_type" class="postbox">';
    echo '<option value="elcinema_theater" ' . selected($type, 'elcinema_theater', false) . '>elCinema Theater</option>';
    echo '</select></p>';

    echo '<p><label><strong>' . __('Sync Status', 'kontentainment') . '</strong></label><br>';
    echo '<select name="ktn_cinema_status" class="postbox">';
    echo '<option value="active" ' . selected($status, 'active', false) . '>Active</option>';
    echo '<option value="inactive" ' . selected($status, 'inactive', false) . '>Inactive</option>';
    echo '</select></p>';

    echo '<hr>';
    echo '<p><strong>' . __('Last Synced At:', 'kontentainment') . '</strong><br>';
    echo ($last_sync ? '<code>' . esc_html($last_sync) . '</code>' : '<span style="color:red;">' . __('Never compiled', 'kontentainment') . '</span>') . '</p>';

    echo '<p><a href="' . wp_nonce_url(admin_url('post.php?post=' . $post->ID . '&action=edit&ktn_action=sync_cinema'), 'ktn_sync_cinema_' . $post->ID) . '" class="button button-primary button-large" style="width:100%; text-align:center;">' . __('Sync Now', 'kontentainment') . '</a></p>';
    echo '<p class="description">' . __('Note: Update post before syncing.', 'kontentainment') . '</p>';

    echo '</div>';
}

/**
 * Statistics Callback
 */
function ktn_cinema_stats_callback($post)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'ktn_showtimes';

    $count_rows = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE cinema_id = %d", $post->ID));
    $count_dates = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT show_date) FROM $table_name WHERE cinema_id = %d", $post->ID));
    $count_matched = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT matched_movie_id) FROM $table_name WHERE cinema_id = %d AND matched_movie_id IS NOT NULL", $post->ID));

    echo '<ul style="margin:0; padding:0; list-style:none;">';
    echo '<li><strong>' . __('Total Showtime Rows:', 'kontentainment') . '</strong> ' . intval($count_rows) . '</li>';
    echo '<li><strong>' . __('Available Dates:', 'kontentainment') . '</strong> ' . intval($count_dates) . '</li>';
    echo '<li><strong>' . __('Matched Movies:', 'kontentainment') . '</strong> ' . intval($count_matched) . '</li>';
    echo '</ul>';
}

/**
 * Showtimes Data Callback (MAIN VIEW)
 */
function ktn_cinema_showtimes_callback($post)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'ktn_showtimes';

    $showtimes = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE cinema_id = %d ORDER BY show_date ASC, movie_title_scraped ASC",
        $post->ID
    ));

    if (empty($showtimes)) {
        echo '<p>' . __('No showtimes found for this cinema. Try syncing first.', 'kontentainment') . '</p>';
        return;
    }

    // Group by Date, then Movie
    $grouped = array();
    foreach ($showtimes as $st) {
        $grouped[$st->show_date][$st->movie_title_scraped][] = $st;
    }

    echo '<div class="ktn-admin-showtimes-list" style="background:#f9f9f9; padding:15px; border:1px solid #ddd; border-radius:4px;">';

    foreach ($grouped as $date => $movies) {
        $date_label = date('l, d F Y', strtotime($date));
        echo '<div class="ktn-date-group" style="margin-bottom:30px; border-bottom:2px solid #eee; padding-bottom:10px;">';
        echo '<h2 style="margin:0 0 15px 0; color:#2271b1; border-left:4px solid #2271b1; padding-left:10px;">' . esc_html($date_label) . '</h2>';

        foreach ($movies as $movie_title => $times) {
            $matched_id = $times[0]->matched_movie_id;
            $matched_post = $matched_id ? get_post($matched_id) : null;

            echo '<div class="ktn-movie-row" style="margin-bottom:20px; background:#fff; padding:15px; border:1px solid #e5e5e5; border-radius:4px;">';
            echo '<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">';
            echo '<div>';
            echo '<h3 style="margin:0; font-size:16px;">' . esc_html($movie_title) . '</h3>';
            
            if ($matched_post) {
                echo '<p style="margin:5px 0; font-size:13px; color:#555;">';
                echo '<span class="dashicons dashicons-yes" style="color:green; font-size:18px;"></span> ';
                echo '<strong>' . __('Matched to:', 'kontentainment') . '</strong> ';
                echo '<a href="' . get_edit_post_link($matched_id) . '" target="_blank">' . esc_html($matched_post->post_title) . '</a> (ID: #'.$matched_id.')';
                echo '</p>';
            } else {
                echo '<p style="margin:5px 0; font-size:13px; color:#d63638;">';
                echo '<span class="dashicons dashicons-no" style="color:#d63638; font-size:18px;"></span> ';
                echo '<strong>' . __('Unmatched', 'kontentainment') . '</strong>';
                echo '</p>';
            }
            echo '</div>';

            // Status Badge
            $badge_bg = $matched_id ? '#dcfce7' : '#fee2e2';
            $badge_color = $matched_id ? '#166534' : '#991b1b';
            echo '<span style="background:'.$badge_bg.'; color:'.$badge_color.'; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700; text-transform:uppercase;">';
            echo $matched_id ? __('Matched', 'kontentainment') : __('Unmatched', 'kontentainment');
            echo '</span>';
            echo '</div>';

            // Times grid
            echo '<div class="ktn-times-grid" style="display:flex; flex-wrap:wrap; gap:10px;">';
            foreach ($times as $t) {
                echo '<div style="background:#f0f0f1; padding:5px 10px; border-radius:4px; font-size:12px; border:1px solid #dcdcde;">';
                echo '<strong>' . esc_html($t->show_time) . '</strong>';
                if ($t->experience && $t->experience !== 'Standard') {
                    echo ' <span style="color:#2271b1; font-weight:600;">[' . esc_html($t->experience) . ']</span>';
                }
                if ($t->price_text) {
                    echo ' <span style="color:#666; font-style:italic;">(' . esc_html($t->price_text) . ')</span>';
                }
                echo '</div>';
            }
            echo '</div>'; 

            echo '</div>'; // ktn-movie-row
        }

        echo '</div>'; // ktn-date-group
    }

    echo '</div>'; // ktn-admin-showtimes-list
}

/**
 * Save Post Meta Logic
 */
add_action('save_post_ktn_cinema', 'ktn_cinema_save_all_meta_data');
function ktn_cinema_save_all_meta_data($post_id)
{
    if (!isset($_POST['ktn_cinema_nonce'])) return;
    if (!wp_verify_nonce($_POST['ktn_cinema_nonce'], 'ktn_cinema_save_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $fields = [
        'ktn_cinema_url' => '_ktn_cinema_url',
        'ktn_cinema_type' => '_ktn_cinema_type',
        'ktn_cinema_status' => '_ktn_cinema_status',
        'ktn_cinema_arabic_name' => '_ktn_cinema_arabic_name',
        'ktn_cinema_english_name' => '_ktn_cinema_english_name',
        'ktn_cinema_logo' => '_ktn_cinema_logo',
        'ktn_cinema_cover_image' => '_ktn_cinema_cover_image',
        'ktn_cinema_address' => '_ktn_cinema_address',
        'ktn_cinema_city' => '_ktn_cinema_city',
        'ktn_cinema_country' => '_ktn_cinema_country',
        'ktn_cinema_maps_url' => '_ktn_cinema_maps_url',
        'ktn_cinema_latitude' => '_ktn_cinema_latitude',
        'ktn_cinema_longitude' => '_ktn_cinema_longitude',
    ];

    foreach ($fields as $post_key => $meta_key) {
        if (isset($_POST[$post_key])) {
            $val = ($post_key === 'ktn_cinema_url' || $post_key === 'ktn_cinema_maps_url') ? esc_url_raw($_POST[$post_key]) : sanitize_text_field($_POST[$post_key]);
            update_post_meta($post_id, $meta_key, $val);
        }
    }
}

/**
 * Handle Sync Action (existing logic preserved)
 */
add_action('admin_init', 'ktn_handle_cinema_sync_action');
function ktn_handle_cinema_sync_action()
{
    if (isset($_GET['ktn_action']) && $_GET['ktn_action'] === 'sync_cinema' && isset($_GET['post'])) {
        $post_id = intval($_GET['post']);
        if (check_admin_referer('ktn_sync_cinema_' . $post_id)) {
            $result = Ktn_Cinema_Importer::syncCinema($post_id);
            if ($result !== false) {
                add_settings_error('ktn_cinema', 'sync_success', sprintf(__('Successfully synced %d showtimes.', 'kontentainment'), $result), 'success');
            } else {
                add_settings_error('ktn_cinema', 'sync_fail', __('Failed to sync showtimes.', 'kontentainment'), 'error');
            }
        }
    }

    if (isset($_GET['ktn_action']) && $_GET['ktn_action'] === 'sync_all_cinemas') {
        if (check_admin_referer('ktn_sync_all_cinemas')) {
            $result = Ktn_Cinema_Importer::syncAllCinemas();
            add_settings_error('ktn_cinema', 'sync_success', sprintf(__('Successfully synced %d showtimes across active cinemas.', 'kontentainment'), $result), 'success');
        }
    }
}

add_action('admin_notices', 'ktn_cinema_admin_notices');
function ktn_cinema_admin_notices()
{
    settings_errors('ktn_cinema');
}

/**
 * Columns Logic (existing logic preserved)
 */
add_filter('manage_ktn_cinema_posts_columns', 'ktn_cinema_columns');
function ktn_cinema_columns($columns)
{
    $columns['ktn_status'] = __('Status', 'kontentainment');
    $columns['ktn_last_sync'] = __('Last Sync', 'kontentainment');
    $columns['ktn_sync_action'] = __('Action', 'kontentainment');
    return $columns;
}

add_action('manage_ktn_cinema_posts_custom_column', 'ktn_cinema_custom_column', 10, 2);
function ktn_cinema_custom_column($column, $post_id)
{
    if ($column === 'ktn_status') {
        echo esc_html(ucfirst(get_post_meta($post_id, '_ktn_cinema_status', true)));
    }
    if ($column === 'ktn_last_sync') {
        $last = get_post_meta($post_id, '_ktn_cinema_last_sync', true);
        echo $last ? '<code>' . $last . '</code>' : __('Never', 'kontentainment');
    }
    if ($column === 'ktn_sync_action') {
        $url = wp_nonce_url(admin_url('post.php?post=' . $post_id . '&action=edit&ktn_action=sync_cinema'), 'ktn_sync_cinema_' . $post_id);
        echo '<a href="' . $url . '" class="button">' . __('Sync', 'kontentainment') . '</a>';
    }
}

add_action('manage_posts_extra_tablenav', 'ktn_cinema_sync_all_button');
function ktn_cinema_sync_all_button($which)
{
    global $typenow;
    if ($typenow == 'ktn_cinema' && $which == 'top') {
        $sync_all_url = wp_nonce_url(admin_url('edit.php?post_type=ktn_cinema&ktn_action=sync_all_cinemas'), 'ktn_sync_all_cinemas');
        echo '<div class="alignleft actions">';
        echo '<a href="' . esc_url($sync_all_url) . '" class="button button-primary">' . __('Sync All Active Cinemas', 'kontentainment') . '</a>';
        echo '</div>';
    }
}