<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'ktn_add_showtimes_page');
function ktn_add_showtimes_page()
{
    add_submenu_page(
        'edit.php?post_type=movie',
        'Showtimes',
        'Showtimes',
        'manage_options',
        'ktn-showtimes',
        'ktn_showtimes_page_html'
    );
}

function ktn_showtimes_page_html()
{
    global $wpdb;
    $table = $wpdb->prefix . 'ktn_showtimes';

    // Handle manual match submission
    if (isset($_POST['ktn_match_action']) && $_POST['ktn_match_action'] == 'match_showtime' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'ktn_match_showtime_nonce')) {
        $showtime_id = intval($_POST['showtime_id']);
        $post_id = intval($_POST['matched_movie_id']);
        // If "Apply to all" is checked, find all unmatched rows with same scraped title and update them
        $apply_all = isset($_POST['apply_to_all']) ? intval($_POST['apply_to_all']) : 0;

        if ($showtime_id && $post_id) {
            if ($apply_all) {
                $scraped_title = stripslashes($_POST['scraped_title']);
                $wpdb->update($table, array('matched_movie_id' => $post_id), array('movie_title_scraped' => $scraped_title));
                echo '<div class="notice notice-success is-dismissible"><p>All matching showtimes (' . esc_html($scraped_title) . ') mapped to Movie ID ' . $post_id . '</p></div>';
            }
            else {
                $wpdb->update($table, array('matched_movie_id' => $post_id), array('id' => $showtime_id));
                echo '<div class="notice notice-success is-dismissible"><p>Showtime successfully mapped to Movie ID ' . $post_id . '</p></div>';
            }
        }
    }

    $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';

    $where = 'WHERE 1=1';
    if ($filter == 'unmatched') {
        $where .= ' AND matched_movie_id IS NULL';
    }
    elseif ($filter == 'matched') {
        $where .= ' AND matched_movie_id IS NOT NULL';
    }

    // Pagination
    $per_page = 50;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $per_page;

    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");
    $total_pages = ceil($total_items / $per_page);

    $results = $wpdb->get_results("SELECT * FROM $table $where ORDER BY show_date ASC, show_time ASC LIMIT $per_page OFFSET $offset");

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Cinema Showtimes</h1>';

    echo '<ul class="subsubsub">';
    echo '<li class="all"><a href="?post_type=movie&page=ktn-showtimes&filter=all" class="' . ($filter == 'all' ? 'current' : '') . '">All</a> |</li>';
    echo '<li class="publish"><a href="?post_type=movie&page=ktn-showtimes&filter=matched" class="' . ($filter == 'matched' ? 'current' : '') . '">Matched</a> |</li>';
    echo '<li class="draft"><a href="?post_type=movie&page=ktn-showtimes&filter=unmatched" class="' . ($filter == 'unmatched' ? 'current' : '') . '">Unmatched</a></li>';
    echo '</ul>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>ID</th>';
    echo '<th>Cinema</th>';
    echo '<th>Scraped Title</th>';
    echo '<th>Date / Time</th>';
    echo '<th>Experience / Price</th>';
    echo '<th>Matched Movie</th>';
    echo '</tr></thead><tbody>';

    if ($results) {
        foreach ($results as $row) {
            echo '<tr>';
            echo '<td>' . $row->id . '</td>';
            echo '<td><strong>' . esc_html($row->cinema_name) . '</strong></td>';
            echo '<td>' . esc_html($row->movie_title_scraped) . '</td>';
            echo '<td>' . esc_html($row->show_date) . '<br>' . esc_html($row->show_time) . '</td>';
            echo '<td>' . esc_html($row->experience) . '<br>' . esc_html($row->price_text) . '</td>';

            echo '<td>';
            if ($row->matched_movie_id) {
                echo '<a href="' . get_edit_post_link($row->matched_movie_id) . '" target="_blank">' . get_the_title($row->matched_movie_id) . ' (' . $row->matched_movie_id . ')</a>';
            }
            else {
                echo '<span style="color:red; font-weight:bold;">Unmatched</span><br>';
                // Simple form to manual map
                echo '<form method="post" style="margin-top:5px; padding: 5px; border: 1px solid #ddd; background: #fff;">';
                wp_nonce_field('ktn_match_showtime_nonce');
                echo '<input type="hidden" name="ktn_match_action" value="match_showtime">';
                echo '<input type="hidden" name="showtime_id" value="' . $row->id . '">';
                echo '<input type="hidden" name="scraped_title" value="' . esc_attr($row->movie_title_scraped) . '">';
                echo '<input type="number" name="matched_movie_id" placeholder="Movie ID" style="width:100px;"> ';
                echo '<button type="submit" class="button button-small">Link</button><br>';
                echo '<label style="font-size:10px; margin-top:3px; display:inline-block;"><input type="checkbox" name="apply_to_all" value="1" checked> Apply to all identical titles</label>';
                echo '</form>';
            }
            echo '</td>';

            echo '</tr>';
        }
    }
    else {
        echo '<tr><td colspan="6">No showtimes found.</td></tr>';
    }

    echo '</tbody></table>';

    // Output pagination
    if ($total_pages > 1) {
        $page_links = paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $paged
        ));

        if ($page_links) {
            echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . $page_links . '</div></div>';
        }
    }

    echo '</div>';
}