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
    add_meta_box('ktn_cinema_location', __('Location & Contact', 'kontentainment'), 'ktn_cinema_location_callback', 'ktn_cinema', 'normal', 'high');
    add_meta_box('ktn_cinema_showtimes', __('Current Showtimes', 'kontentainment'), 'ktn_cinema_showtimes_callback', 'ktn_cinema', 'normal', 'default');

    // Side Area
    add_meta_box('ktn_cinema_source', __('Source & Sync Settings', 'kontentainment'), 'ktn_cinema_source_callback', 'ktn_cinema', 'side', 'high');
    add_meta_box('ktn_cinema_branding', __('Branding & Media', 'kontentainment'), 'ktn_cinema_branding_callback', 'ktn_cinema', 'side', 'default');
    add_meta_box('ktn_cinema_stats', __('Cinema Statistics', 'kontentainment'), 'ktn_cinema_stats_callback', 'ktn_cinema', 'side', 'low');
}

/**
 * Enqueue Admin Scripts for Media Uploader
 */
add_action('admin_enqueue_scripts', 'ktn_cinema_admin_enqueue_scripts');
function ktn_cinema_admin_enqueue_scripts($hook) {
    global $post_type;
    if ($post_type === 'ktn_cinema') {
        wp_enqueue_media();
        wp_enqueue_style('ktn-admin-styles', KTN_PLUGIN_URL . 'assets/css/admin.css', array(), KTN_PLUGIN_VERSION);
        wp_enqueue_script('ktn-cinema-admin', KTN_PLUGIN_URL . 'assets/js/cinema-admin.js', array('jquery'), KTN_PLUGIN_VERSION, true);
        
        // Localize for dynamic Area selection
        wp_localize_script('ktn-cinema-admin', 'ktn_cinema_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ktn_admin_nonce')
        ));
    }
}

/**
 * Basic Information Callback
 */
function ktn_cinema_basic_info_callback($post)
{
    wp_nonce_field('ktn_cinema_save_nonce', 'ktn_cinema_nonce');

    $arabic_name = get_post_meta($post->ID, '_ktn_cinema_arabic_name', true);
    $english_name = get_post_meta($post->ID, '_ktn_cinema_english_name', true);
    $rating = get_post_meta($post->ID, '_ktn_cinema_rating', true);

    echo '<div class="ktn-admin-field-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
    echo '<p><label><strong>' . __('Arabic Name', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_arabic_name" value="' . esc_attr($arabic_name) . '" class="large-text"></p>';
    
    echo '<p><label><strong>' . __('English Name', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_english_name" value="' . esc_attr($english_name) . '" class="large-text"></p>';
    
    echo '<p><label><strong>' . __('Rating / Stars', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_rating" value="' . esc_attr($rating) . '" class="regular-text"></p>';
    echo '</div>';
    
    echo '<p><label><strong>' . __('Notes / Policies / Description', 'kontentainment') . '</strong></label><br>';
    wp_editor($post->post_content, 'content', array('textarea_name' => 'content', 'media_buttons' => true, 'textarea_rows' => 5));
    echo '</p>';
}

/**
 * Location Information Callback
 */
function ktn_cinema_location_callback($post)
{
    $address = get_post_meta($post->ID, '_ktn_cinema_address', true);
    $area = get_post_meta($post->ID, '_ktn_cinema_area', true);
    $city = get_post_meta($post->ID, '_ktn_cinema_city', true);
    $country = get_post_meta($post->ID, '_ktn_cinema_country', true);
    $phone = get_post_meta($post->ID, '_ktn_cinema_phone', true);
    $maps_url = get_post_meta($post->ID, '_ktn_cinema_maps_url', true);
    $lat = get_post_meta($post->ID, '_ktn_cinema_latitude', true);
    $lng = get_post_meta($post->ID, '_ktn_cinema_longitude', true);

    echo '<div class="ktn-admin-grid-fields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
    
    echo '<p style="grid-column: span 2;"><label><strong>' . __('Full Address', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_address" value="' . esc_attr($address) . '" class="large-text" placeholder="e.g. 123 Street Name, Neighborhood"></p>';

    // City Dropdown
    echo '<p><label><strong>' . __('City (Governorate)', 'kontentainment') . '</strong></label><br>';
    $all_cities = get_terms(array('taxonomy' => 'cinema_location', 'parent' => 0, 'hide_empty' => false));
    echo '<select name="ktn_cinema_city" class="widefat" id="ktn-cinema-city">';
    echo '<option value="">' . __('Select City', 'kontentainment') . '</option>';
    foreach ($all_cities as $city_obj) {
        echo '<option value="' . esc_attr($city_obj->name) . '" ' . selected($city, $city_obj->name, false) . ' data-id="'.$city_obj->term_id.'">' . esc_html($city_obj->name) . '</option>';
    }
    echo '</select></p>';

    // Area Dropdown
    echo '<p><label><strong>' . __('Area (Zone)', 'kontentainment') . '</strong></label><br>';
    echo '<select name="ktn_cinema_area" class="widefat" id="ktn-cinema-area">';
    echo '<option value="">' . __('Select Area', 'kontentainment') . '</option>';
    if ($city) {
        $parent_city = get_term_by('name', $city, 'cinema_location');
        if ($parent_city) {
            $matching_areas = get_terms(array('taxonomy' => 'cinema_location', 'parent' => $parent_city->term_id, 'hide_empty' => false));
            foreach ($matching_areas as $area_obj) {
                echo '<option value="' . esc_attr($area_obj->name) . '" ' . selected($area, $area_obj->name, false) . '>' . esc_html($area_obj->name) . '</option>';
            }
        }
    }
    echo '</select></p>';

    echo '<p><label><strong>' . __('Country', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_country" value="' . esc_attr($country) . '" class="regular-text"></p>';

    echo '<p><label><strong>' . __('Phone / Contact', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_phone" value="' . esc_attr($phone) . '" class="regular-text"></p>';

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
    
    // Cinema Logo
    echo '<div class="ktn-media-field-wrapper" style="margin-bottom:20px;">';
    echo '<label><strong>' . __('Cinema Logo / Main URL', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_logo" id="ktn_cinema_logo" value="' . esc_attr($logo) . '" class="widefat" style="margin-bottom:5px;">';
    echo '<div class="ktn-media-btns" style="display:flex; gap:5px; margin-top:5px;">';
    echo '<button type="button" class="button ktn-upload-btn" data-target="#ktn_cinema_logo">' . __('Upload', 'kontentainment') . '</button>';
    if (filter_var($logo, FILTER_VALIDATE_URL) && strpos($logo, get_site_url()) === false) {
        $import_logo_url = wp_nonce_url(admin_url('post.php?post=' . $post->ID . '&action=edit&ktn_action=import_media&media_type=logo'), 'ktn_import_media_logo_' . $post->ID);
        echo '<a href="'.$import_logo_url.'" class="button ktn-import-btn">' . __('Import', 'kontentainment') . '</a>';
    }
    echo '</div>';
    echo '<div class="ktn-preview" style="margin-top:10px; border:1px solid #ddd; padding:5px; background:#fff; text-align:center;">';
    if ($logo) echo '<img src="'.esc_url($logo).'" style="max-width:100%; height:auto;">';
    else echo '<span style="font-size:11px; color:#999;">'.__('No Preview', 'kontentainment').'</span>';
    echo '</div>';
    echo '</div>';

    // Cover Image
    echo '<div class="ktn-media-field-wrapper">';
    echo '<label><strong>' . __('Cover Image URL', 'kontentainment') . '</strong></label><br>';
    echo '<input type="text" name="ktn_cinema_cover_image" id="ktn_cinema_cover_image" value="' . esc_attr($cover) . '" class="widefat" style="margin-bottom:5px;">';
    echo '<div class="ktn-media-btns" style="display:flex; gap:5px; margin-top:5px;">';
    echo '<button type="button" class="button ktn-upload-btn" data-target="#ktn_cinema_cover_image">' . __('Upload', 'kontentainment') . '</button>';
    if (filter_var($cover, FILTER_VALIDATE_URL) && strpos($cover, get_site_url()) === false) {
        $import_cover_url = wp_nonce_url(admin_url('post.php?post=' . $post->ID . '&action=edit&ktn_action=import_media&media_type=cover'), 'ktn_import_media_cover_' . $post->ID);
        echo '<a href="'.$import_cover_url.'" class="button ktn-import-btn">' . __('Import', 'kontentainment') . '</a>';
    }
    echo '</div>';
    echo '<div class="ktn-preview" style="margin-top:10px; border:1px solid #ddd; padding:5px; background:#fff; text-align:center;">';
    if ($cover) echo '<img src="'.esc_url($cover).'" style="max-width:100%; height:auto;">';
    else echo '<span style="font-size:11px; color:#999;">'.__('No Preview', 'kontentainment').'</span>';
    echo '</div>';
    echo '</div>';

    echo '</div>';
}

/**
 * Source & Sync Settings Callback
 */
function ktn_cinema_source_callback($post)
{
    $url = get_post_meta($post->ID, '_ktn_cinema_url', true);
    $source_type = get_post_meta($post->ID, '_ktn_cinema_type', true) ?: 'elcinema_theater';
    $status = get_post_meta($post->ID, '_ktn_cinema_status', true) ?: 'active';
    $auto_sync = get_post_meta($post->ID, '_ktn_cinema_auto_sync', true);
    $last_sync = get_post_meta($post->ID, '_ktn_cinema_last_sync', true);
    $last_err = get_post_meta($post->ID, '_ktn_last_error', true);

    echo '<div class="ktn-admin-side-fields">';
    
    echo '<p><label><strong>' . __('Source URL', 'kontentainment') . '</strong></label><br>';
    echo '<input type="url" name="ktn_cinema_url" value="' . esc_attr($url) . '" class="widefat" required placeholder="https://elcinema.com/..."></p>';

    echo '<p><label><strong>' . __('Source Type', 'kontentainment') . '</strong></label><br>';
    echo '<select name="ktn_cinema_type" class="widefat">';
    echo '<option value="elcinema_theater" ' . selected($source_type, 'elcinema_theater', false) . '>elCinema Theater</option>';
    echo '</select></p>';

    echo '<p><label><strong>' . __('Status', 'kontentainment') . '</strong></label><br>';
    echo '<select name="ktn_cinema_status" class="widefat">';
    echo '<option value="active" ' . selected($status, 'active', false) . '>Active</option>';
    echo '<option value="inactive" ' . selected($status, 'inactive', false) . '>Inactive</option>';
    echo '</select></p>';

    echo '<div style="background:#EBF5FF; border:1px solid #BFDBFE; padding:12px; border-radius:8px; margin:15px 0;">';
    echo '<label style="display:flex; align-items:center; cursor:pointer; font-weight:600; font-size:13px; color:#1E40AF;">';
    echo '<input type="checkbox" name="ktn_cinema_auto_sync" value="yes" ' . checked($auto_sync, 'yes', false) . ' style="margin-right:10px;">';
    _e('Auto Sync Every 2 Hours', 'kontentainment');
    echo '</label>';
    echo '</div>';

    echo '<hr style="margin:20px 0;">';
    echo '<div style="font-size:12px; margin-bottom:15px;">';
    echo '<strong>' . __('Last Sync At:', 'kontentainment') . '</strong><br>';
    echo ($last_sync ? '<code>' . esc_html($last_sync) . '</code>' : '<span style="color:#DC2626;">' . __('Never', 'kontentainment') . '</span>');
    echo '</div>';

    if ($last_err) {
        $is_success = strpos($last_err, 'Success') !== false;
        $color = $is_success ? '#059669' : '#DC2626';
        echo '<div style="font-size:11px; padding:8px; background:#fff; border-left:3px solid '.$color.'; box-shadow:0 1px 3px rgba(0,0,0,0.05);">';
        echo '<strong>' . ($is_success ? __('Result:', 'kontentainment') : __('Last Error:', 'kontentainment')) . '</strong><br>';
        echo esc_html($last_err);
        echo '</div>';
    }

    echo '<p style="margin-top:20px;"><a href="' . wp_nonce_url(admin_url('post.php?post=' . $post->ID . '&action=edit&ktn_action=sync_cinema'), 'ktn_sync_cinema_' . $post->ID) . '" class="button button-primary button-large" style="width:100%; text-align:center;">' . __('Sync Now', 'kontentainment') . '</a></p>';
    
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
    $count_matched = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT matched_movie_id) FROM $table_name WHERE cinema_id = %d AND matched_movie_id IS NOT NULL", $post->ID));

    echo '<div style="font-size:13px; line-height:1.6;">';
    echo '<div><strong>' . __('Showtimes Rows:', 'kontentainment') . '</strong> ' . intval($count_rows) . '</div>';
    echo '<div><strong>' . __('Matched Movies:', 'kontentainment') . '</strong> ' . intval($count_matched) . '</div>';
    echo '</div>';
}

/**
 * Showtimes Data View
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
        echo '<p style="padding:40px; text-align:center; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; color:#64748b;">' . __('No showtimes synced. Run "Sync Now" to import data.', 'kontentainment') . '</p>';
        return;
    }

    $grouped = array();
    foreach ($showtimes as $st) {
        $grouped[$st->show_date][$st->movie_title_scraped][] = $st;
    }

    echo '<div class="ktn-admin-showtimes-wrapper" style="max-height:600px; overflow-y:auto; padding-right:10px;">';
    foreach ($grouped as $date => $movies) {
        echo '<div style="margin-bottom:25px;">';
        echo '<h3 style="background:#f1f5f9; padding:8px 15px; border-radius:6px; font-size:14px; color:#1e293b; margin-bottom:15px; border-left:4px solid #3b82f6;">' . date('l, d F Y', strtotime($date)) . '</h3>';

        foreach ($movies as $title => $times) {
            $matched_id = $times[0]->matched_movie_id;
            echo '<div style="background:#fff; border:1px solid #e2e8f0; padding:12px; border-radius:8px; margin-bottom:12px;">';
            echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">';
            echo '<span style="font-weight:700; color:#1e293b;">' . esc_html($title) . '</span>';
            if ($matched_id) {
                echo '<span style="font-size:11px; background:#dcfce7; color:#166534; padding:2px 8px; border-radius:10px; font-weight:600;">'.__('MATCHED', 'kontentainment').'</span>';
            } else {
                echo '<span style="font-size:11px; background:#fee2e2; color:#b91c1c; padding:2px 8px; border-radius:10px; font-weight:600;">'.__('UNMATCHED', 'kontentainment').'</span>';
            }
            echo '</div>';
            echo '<div style="display:flex; flex-wrap:wrap; gap:8px;">';
            foreach ($times as $t) {
                echo '<span style="font-size:12px; background:#f8fafc; border:1px solid #f1f5f9; padding:4px 10px; border-radius:6px; color:#475569;">' . esc_html($t->show_time) . '</span>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}

/**
 * AJAX Handler for Area selection
 */
add_action('wp_ajax_ktn_get_areas_by_city', 'ktn_ajax_get_areas_by_city');
function ktn_ajax_get_areas_by_city() {
    check_ajax_referer('ktn_admin_nonce', 'nonce');
    $city_name = sanitize_text_field($_POST['city_name']);
    
    if (empty($city_name)) {
        wp_send_json_success(array());
    }

    $city_term = get_term_by('name', $city_name, 'cinema_location');
    if (!$city_term) {
        wp_send_json_success(array());
    }

    $areas = get_terms(array(
        'taxonomy' => 'cinema_location',
        'parent'   => $city_term->term_id,
        'hide_empty' => false
    ));

    $results = array();
    foreach ($areas as $a) {
        $results[] = array('name' => $a->name);
    }

    wp_send_json_success($results);
}

/**
 * Add "Sync Now" row action to Cinemas list
 */
add_filter('post_row_actions', 'ktn_cinema_row_actions', 10, 2);
function ktn_cinema_row_actions($actions, $post) {
    if ($post->post_type === 'ktn_cinema' && current_user_can('manage_options')) {
        $url = wp_nonce_url(
            admin_url('edit.php?post_type=ktn_cinema&ktn_action=sync_cinema_list&post_id=' . $post->ID),
            'ktn_sync_now_' . $post->ID
        );
        $actions['ktn_sync_now'] = '<a href="' . $url . '" style="color: #2271b1; font-weight: bold;">' . __('Sync Showtimes', 'kontentainment') . '</a>';
    }
    return $actions;
}

/**
 * Handle Actions (Sync & Import)
 */
add_action('admin_init', 'ktn_handle_cinema_admin_actions');
function ktn_handle_cinema_admin_actions()
{
    // List Table Action (Showtimes Only)
    if (isset($_GET['ktn_action']) && $_GET['ktn_action'] === 'sync_cinema_list' && isset($_GET['post_id'])) {
        $post_id = intval($_GET['post_id']);
        if (check_admin_referer('ktn_sync_now_' . $post_id)) {
            if (!current_user_can('manage_options')) return;

            $result = Ktn_Cinema_Importer::syncCinema($post_id, false, true); // No meta refresh, showtimes only
            
            $redirect_url = admin_url('edit.php?post_type=ktn_cinema');
            if ($result['success']) {
                $redirect_url = add_query_arg([
                    'ktn_sync_success' => 1,
                    'added' => $result['added'],
                    'matched' => $result['matched'],
                    'imported' => $result['imported']
                ], $redirect_url);
            } else {
                $redirect_url = add_query_arg(['ktn_sync_error' => urlencode($result['message'])], $redirect_url);
            }
            
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    // Individual Post Edit Actions
    if (!isset($_GET['post'])) return;
    $post_id = intval($_GET['post']);
    
    // Original Sync Action (Full Sync)
    if (isset($_GET['ktn_action']) && $_GET['ktn_action'] === 'sync_cinema') {
        if (check_admin_referer('ktn_sync_cinema_' . $post_id)) {
            $result = Ktn_Cinema_Importer::syncCinema($post_id, false);
            if (is_array($result) && $result['success']) {
                add_settings_error('ktn_cinema', 'sync_success', $result['message'], 'success');
            } else {
                $err = is_array($result) ? $result['message'] : __('Unknown sync error.', 'kontentainment');
                add_settings_error('ktn_cinema', 'sync_fail', $err, 'error');
            }
        }
    }

    // Media Import Action
    if (isset($_GET['ktn_action']) && $_GET['ktn_action'] === 'import_media') {
        $type = sanitize_text_field($_GET['media_type']);
        if (check_admin_referer('ktn_import_media_' . $type . '_' . $post_id)) {
            $meta_key = $type === 'logo' ? '_ktn_cinema_logo' : '_ktn_cinema_cover_image';
            $url = get_post_meta($post_id, $meta_key, true);
            if ($url) {
                $attach_id = ktn_sideload_image($url, $post_id);
                if (!is_wp_error($attach_id)) {
                    $local_url = wp_get_attachment_url($attach_id);
                    update_post_meta($post_id, $meta_key, $local_url);
                    add_settings_error('ktn_cinema', 'import_success', __('Image imported and saved to media library.', 'kontentainment'), 'success');
                } else {
                    add_settings_error('ktn_cinema', 'import_fail', __('Failed to import image: ', 'kontentainment') . $attach_id->get_error_message(), 'error');
                }
            }
        }
    }
}

/**
 * Admin Notices for Sync Results
 */
add_action('admin_notices', 'ktn_cinema_admin_notices');
function ktn_cinema_admin_notices() {
    global $pagenow;
    if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'ktn_cinema') {
        if (isset($_GET['ktn_sync_success'])) {
            $added = intval($_GET['added']);
            $matched = intval($_GET['matched']);
            $imported = intval($_GET['imported']);
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e('Cinema Sync Complete:', 'kontentainment'); ?></strong><br>
                    - <?php printf(__('Showtimes Updated: %d', 'kontentainment'), $added); ?><br>
                    - <?php printf(__('Movies Matched: %d', 'kontentainment'), $matched); ?><br>
                    - <?php printf(__('Newly Imported Movies: %d', 'kontentainment'), $imported); ?>
                </p>
            </div>
            <?php
        } elseif (isset($_GET['ktn_sync_error'])) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html(urldecode($_GET['ktn_sync_error'])); ?></p>
            </div>
            <?php
        }
    }
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
        'ktn_cinema_auto_sync' => '_ktn_cinema_auto_sync',
        'ktn_cinema_arabic_name' => '_ktn_cinema_arabic_name',
        'ktn_cinema_english_name' => '_ktn_cinema_english_name',
        'ktn_cinema_rating' => '_ktn_cinema_rating',
        'ktn_cinema_logo' => '_ktn_cinema_logo',
        'ktn_cinema_cover_image' => '_ktn_cinema_cover_image',
        'ktn_cinema_address' => '_ktn_cinema_address',
        'ktn_cinema_area' => '_ktn_cinema_area',
        'ktn_cinema_phone' => '_ktn_cinema_phone',
        'ktn_cinema_city' => '_ktn_cinema_city',
        'ktn_cinema_country' => '_ktn_cinema_country',
        'ktn_cinema_maps_url' => '_ktn_cinema_maps_url',
        'ktn_cinema_latitude' => '_ktn_cinema_latitude',
        'ktn_cinema_longitude' => '_ktn_cinema_longitude',
    ];

    foreach ($fields as $post_key => $meta_key) {
        if ($post_key === 'ktn_cinema_auto_sync') {
            update_post_meta($post_id, $meta_key, isset($_POST[$post_key]) ? 'yes' : 'no');
            continue;
        }

        if (isset($_POST[$post_key])) {
            $val = ($post_key === 'ktn_cinema_url' || $post_key === 'ktn_cinema_maps_url') ? esc_url_raw($_POST[$post_key]) : sanitize_text_field($_POST[$post_key]);
            update_post_meta($post_id, $meta_key, $val);
            
            // Sync Taxonomy (cinema_location)
            if ($post_key === 'ktn_cinema_city' || $post_key === 'ktn_cinema_area') {
                $city_val = sanitize_text_field($_POST['ktn_cinema_city']);
                $area_val = sanitize_text_field($_POST['ktn_cinema_area']);
                
                $terms_to_set = array();
                if ($city_val) $terms_to_set[] = $city_val;
                if ($area_val) $terms_to_set[] = $area_val;
                
                // Ensure terms exist and have parent relationship
                if ($city_val) {
                    $city_term = wp_insert_term($city_val, 'cinema_location');
                    if (is_wp_error($city_term) && $city_term->get_error_code() === 'term_exists') {
                        $city_id = $city_term->get_error_data();
                    } else {
                        $city_id = isset($city_term['term_id']) ? $city_term['term_id'] : 0;
                    }

                    if ($area_val) {
                        wp_insert_term($area_val, 'cinema_location', array('parent' => $city_id));
                    }
                }
                
                wp_set_object_terms($post_id, $terms_to_set, 'cinema_location', false);
            }
        }
    }
}