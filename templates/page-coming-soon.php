<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();
wp_enqueue_style('ktn-archives-css', KTN_PLUGIN_URL . 'assets/css/kontentainment-archives.css', array(), KTN_PLUGIN_VERSION);

global $wpdb;
$today = date('Y-m-d');
$now_playing_ids = $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT matched_movie_id FROM {$wpdb->prefix}ktn_showtimes WHERE matched_movie_id IS NOT NULL AND (show_date >= %s OR show_date = 'Today')",
    $today
));

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$args = array(
    'post_type' => 'movie',
    'post_status' => 'publish',
    'paged' => $paged,
    'posts_per_page' => 20,
    'meta_query' => array(
        'relation' => 'OR',
        array(
            'key' => '_movie_release_date',
            'value' => $today,
            'compare' => '>',
            'type' => 'DATE'
        ),
        array(
            'key' => '_movie_status',
            'value' => 'Upcoming',
            'compare' => '='
        )
    ),
    'orderby' => 'meta_value',
    'meta_key' => '_movie_release_date',
    'order' => 'ASC'
);

if (!empty($now_playing_ids)) {
    $args['post__not_in'] = $now_playing_ids;
}

$coming_soon_query = new WP_Query($args);
?>

<div class="ktn-archive-wrapper">
    <div class="ktn-archive-header">
        <h1 class="ktn-archive-title"><?php esc_html_e('Coming Soon', 'kontentainment'); ?></h1>
        <p class="ktn-archive-description">Discover movies releasing soon.</p>
    </div>

    <?php if ($coming_soon_query->have_posts()): ?>
        <div class="ktn-archive-grid">
            <?php while ($coming_soon_query->have_posts()): $coming_soon_query->the_post(); 
                $post_id = get_the_ID();
                $poster_url = has_post_thumbnail() ? get_the_post_thumbnail_url($post_id, 'medium_large') : '';
                if (!$poster_url) {
                    $poster_path = get_post_meta($post_id, '_movie_poster_path', true);
                    if ($poster_path) {
                        $poster_url = "https://image.tmdb.org/t/p/w500" . $poster_path;
                    }
                }
                
                $release_date = get_post_meta($post_id, '_movie_release_date', true);
                $trailer_url = get_post_meta($post_id, '_movie_trailer_url', true);
                $tagline = get_post_meta($post_id, '_movie_tagline', true);
                
                $terms = get_the_terms($post_id, 'category');
                if (!$terms || is_wp_error($terms)) {
                    $terms = get_the_terms($post_id, 'movie_genre');
                    if (!$terms || is_wp_error($terms)) {
                        $terms = get_the_terms($post_id, 'ktn_genre');
                    }
                }
                $genres_str = '';
                if ($terms && !is_wp_error($terms)) {
                    $genres = wp_list_pluck($terms, 'name');
                    $genres_str = implode(', ', array_slice($genres, 0, 3));
                }
            ?>
            <div class="ktn-archive-card ktn-movie-card">
                <?php if ($release_date): ?>
                    <div class="ktn-badge" style="background:#111; color:#fff;">
                        <?php echo esc_html(date('M j, Y', strtotime($release_date))); ?>
                    </div>
                <?php endif; ?>
                
                <div class="ktn-card-image-wrap">
                    <?php if ($poster_url): ?>
                        <img src="<?php echo esc_url($poster_url); ?>" alt="<?php the_title_attribute(); ?> Poster">
                    <?php else: ?>
                        <div style="font-size: 1.5rem; color: #ccc;">No Poster</div>
                    <?php endif; ?>
                </div>

                <div class="ktn-card-content">
                    <h2 class="ktn-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    
                    <div class="ktn-card-meta">
                        <?php if ($genres_str): ?>
                            <span class="ktn-card-meta-item">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                                <?php echo esc_html($genres_str); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($trailer_url): ?>
                        <a href="<?php echo esc_url($trailer_url); ?>" target="_blank" class="ktn-trailer-link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="vertical-align: middle; margin-right: 5px;"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            Watch Trailer
                        </a>
                    <?php endif; ?>

                    <?php if ($tagline): ?>
                        <p class="ktn-card-excerpt"><?php echo esc_html(wp_trim_words($tagline, 15, '...')); ?></p>
                    <?php else: ?>
                        <p class="ktn-card-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 15, '...'); ?></p>
                    <?php endif; ?>
                    
                    <div class="ktn-card-action">
                        <a href="<?php the_permalink(); ?>">View Details</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <?php 
        $big = 999999999;
        echo '<div style="margin-top:40px; text-align:center;">';
        echo paginate_links(array(
            'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format' => '?paged=%#%',
            'current' => max(1, get_query_var('paged')),
            'total' => $coming_soon_query->max_num_pages,
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
        ));
        echo '</div>';
        wp_reset_postdata();
        ?>
    <?php else: ?>
        <p style="text-align: center; color: #777;">No movies are in the coming soon list.</p>
    <?php endif; ?>

</div>

<?php 
get_footer();
