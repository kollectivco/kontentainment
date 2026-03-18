<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('save_post', 'ktn_save_meta_box_data');
function ktn_save_meta_box_data($post_id)
{
    if (!isset($_POST['ktn_meta_box_nonce'])) {
        return;
    }
    if (!wp_verify_nonce($_POST['ktn_meta_box_nonce'], 'ktn_save_meta_box_data')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (isset($_POST['post_type']) && in_array($_POST['post_type'], array('movie', 'tv_show'))) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }
    else {
        return;
    }

    // The movie and cinema metadata saving is now handled in their respective metabox files
    // includes/metabox-movie.php and includes/metabox-cinema.php
}