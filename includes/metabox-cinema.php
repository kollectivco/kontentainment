<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', 'ktn_cinema_add_meta_box');
function ktn_cinema_add_meta_box()
{
    add_meta_box(
        'ktn_cinema_settings',
        __('Cinema Source Settings', 'kontentainment'),
        'ktn_cinema_meta_box_callback',
        'ktn_cinema',
        'normal',
        'high'
    );
}

function ktn_cinema_meta_box_callback($post)
{
    wp_nonce_field('ktn_cinema_meta_box_nonce', 'ktn_cinema_nonce');

    $url = get_post_meta($post->ID, '_ktn_cinema_url', true);
    $type = get_post_meta($post->ID, '_ktn_cinema_type', true);
    if (!$type)
        $type = 'elcinema_theater';
    $status = get_post_meta($post->ID, '_ktn_cinema_status', true);
    if (!$status)
        $status = 'active';

    $last_sync = get_post_meta($post->ID, '_ktn_cinema_last_sync', true);

    echo '<table class="form-table">';
    echo '<tr><th><label for="ktn_cinema_url">Source URL</label></th>';
    echo '<td><input type="url" id="ktn_cinema_url" name="ktn_cinema_url" value="' . esc_attr($url) . '" class="regular-text" required /></td></tr>';

    echo '<tr><th><label for="ktn_cinema_type">Source Type</label></th>';
    echo '<td><select id="ktn_cinema_type" name="ktn_cinema_type">';
    echo '<option value="elcinema_theater" ' . selected($type, 'elcinema_theater', false) . '>elCinema Theater</option>';
    echo '</select></td></tr>';

    echo '<tr><th><label for="ktn_cinema_status">Status</label></th>';
    echo '<td><select id="ktn_cinema_status" name="ktn_cinema_status">';
    echo '<option value="active" ' . selected($status, 'active', false) . '>Active</option>';
    echo '<option value="inactive" ' . selected($status, 'inactive', false) . '>Inactive</option>';
    echo '</select></td></tr>';

    echo '<tr><th><label>Last Synced At</label></th>';
    echo '<td>' . ($last_sync ? esc_html($last_sync) : 'Never synced') . '</td></tr>';

    // Always show sync button if it has a URL (or even if it doesn't, we can add it and it will fail softly if url is empty)
    echo '<tr><th>Action</th>';
    echo '<td>';
    echo '<a href="' . wp_nonce_url(admin_url('post.php?post=' . $post->ID . '&action=edit&ktn_action=sync_cinema'), 'ktn_sync_cinema_' . $post->ID) . '" class="button button-primary">Sync Now</a>';
    echo '&nbsp; <p class="description">Note: Remember to Update/Publish the post first before syncing.</p></td></tr>';

    echo '</table>';
}

add_action('save_post_ktn_cinema', 'ktn_cinema_save_meta_box_data');
function ktn_cinema_save_meta_box_data($post_id)
{
    if (!isset($_POST['ktn_cinema_nonce']))
        return;
    if (!wp_verify_nonce($_POST['ktn_cinema_nonce'], 'ktn_cinema_meta_box_nonce'))
        return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;

    if (isset($_POST['ktn_cinema_url'])) {
        update_post_meta($post_id, '_ktn_cinema_url', sanitize_text_field($_POST['ktn_cinema_url']));
    }
    if (isset($_POST['ktn_cinema_type'])) {
        update_post_meta($post_id, '_ktn_cinema_type', sanitize_text_field($_POST['ktn_cinema_type']));
    }
    if (isset($_POST['ktn_cinema_status'])) {
        update_post_meta($post_id, '_ktn_cinema_status', sanitize_text_field($_POST['ktn_cinema_status']));
    }
}

add_action('admin_init', 'ktn_handle_cinema_sync_action');
function ktn_handle_cinema_sync_action()
{
    if (isset($_GET['ktn_action']) && $_GET['ktn_action'] === 'sync_cinema' && isset($_GET['post'])) {
        $post_id = intval($_GET['post']);
        if (check_admin_referer('ktn_sync_cinema_' . $post_id)) {
            $result = Ktn_Cinema_Importer::syncCinema($post_id);
            if ($result !== false) {
                add_settings_error('ktn_cinema', 'sync_success', "Successfully synced $result showtimes.", 'success');
            }
            else {
                add_settings_error('ktn_cinema', 'sync_fail', "Failed to sync showtimes. Check status or URL.", 'error');
            }
        }
    }

    // Sync All Cinemas Action
    if (isset($_GET['ktn_action']) && $_GET['ktn_action'] === 'sync_all_cinemas') {
        if (check_admin_referer('ktn_sync_all_cinemas')) {
            $result = Ktn_Cinema_Importer::syncAllCinemas();
            add_settings_error('ktn_cinema', 'sync_success', "Successfully synced $result showtimes across all active cinemas.", 'success');
        }
    }
}

add_action('admin_notices', 'ktn_cinema_admin_notices');
function ktn_cinema_admin_notices()
{
    settings_errors('ktn_cinema');
}

add_filter('manage_ktn_cinema_posts_columns', 'ktn_cinema_columns');
function ktn_cinema_columns($columns)
{
    $columns['ktn_source_url'] = 'Source URL';
    $columns['ktn_status'] = 'Status';
    $columns['ktn_last_sync'] = 'Last Sync';
    $columns['ktn_sync_action'] = 'Action';
    return $columns;
}

add_action('manage_ktn_cinema_posts_custom_column', 'ktn_cinema_custom_column', 10, 2);
function ktn_cinema_custom_column($column, $post_id)
{
    if ($column === 'ktn_source_url') {
        echo esc_html(get_post_meta($post_id, '_ktn_cinema_url', true));
    }
    if ($column === 'ktn_status') {
        echo esc_html(ucfirst(get_post_meta($post_id, '_ktn_cinema_status', true)));
    }
    if ($column === 'ktn_last_sync') {
        $last = get_post_meta($post_id, '_ktn_cinema_last_sync', true);
        echo $last ? $last : 'Never';
    }
    if ($column === 'ktn_sync_action') {
        $url = wp_nonce_url(admin_url('post.php?post=' . $post_id . '&action=edit&ktn_action=sync_cinema'), 'ktn_sync_cinema_' . $post_id);
        echo '<a href="' . $url . '" class="button">Sync</a>';
    }
}

// Add Sync All button to the list view
add_action('manage_posts_extra_tablenav', 'ktn_cinema_sync_all_button');
function ktn_cinema_sync_all_button($which)
{
    global $typenow;
    if ($typenow == 'ktn_cinema' && $which == 'top') {
        $sync_all_url = wp_nonce_url(admin_url('edit.php?post_type=ktn_cinema&ktn_action=sync_all_cinemas'), 'ktn_sync_all_cinemas');
        echo '<div class="alignleft actions">';
        echo '<a href="' . esc_url($sync_all_url) . '" class="button button-primary">Sync All Active Cinemas</a>';
        echo '</div>';
    }
}