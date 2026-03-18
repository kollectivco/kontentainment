<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $post;
$post_id = $post->ID;
$cinema_name = get_the_title($post_id);
$source_url = get_post_meta($post_id, '_ktn_cinema_url', true);

global $wpdb;
$table_showtimes = $wpdb->prefix . 'ktn_showtimes';

$suppress = $wpdb->suppress_errors(true);
$showtimes = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_showtimes WHERE cinema_id = %d ORDER BY show_date ASC, movie_title_scraped ASC, show_time ASC",
    $post_id
));
$wpdb->suppress_errors($suppress);

wp_enqueue_style('ktn-showtimes-css', KTN_PLUGIN_URL . 'assets/css/kontentainment-showtimes.css', array(), '1.1.9');
wp_enqueue_script('ktn-showtimes-js', KTN_PLUGIN_URL . 'assets/js/kontentainment-showtimes.js', array('jquery'), '1.1.9', true);

// Group by date -> movie title
$grouped_by_date = array();
if (!empty($showtimes) && !is_wp_error($showtimes)) {
    foreach ($showtimes as $st) {
        $grouped_by_date[$st->show_date][$st->movie_title_scraped][] = $st;
    }
}
$unique_dates = array_keys($grouped_by_date);
?>

<div class="ktn-cinema-page">
    <div class="ktn-cinema-hero">
        <h1>
            <?php echo esc_html($cinema_name); ?>
        </h1>
        <?php if ($source_url): ?>
        <p><a href="<?php echo esc_url($source_url); ?>" target="_blank"
                style="color: #6b7280; text-decoration: none;">Information Source &rarr;</a></p>
        <?php
endif; ?>
    </div>

    <?php if (empty($grouped_by_date)): ?>
    <p>No showtimes found for this cinema currently.</p>
    <?php
else: ?>
    <div class="ktn-st-header">
        <h2 class="ktn-st-title" style="display:none;">Dates</h2>
        <div class="ktn-st-dates" style="width:100%;">
            <?php $is_first = true;
    foreach ($unique_dates as $index => $date): ?>
            <button class="ktn-st-date-btn <?php echo $is_first ? 'active' : ''; ?>"
                data-date-target="date-<?php echo esc_attr(md5($date)); ?>">
                <?php echo esc_html($date); ?>
            </button>
            <?php $is_first = false; ?>
            <?php
    endforeach; ?>
        </div>
    </div>

    <div class="ktn-st-content">
        <?php $is_first = true;
    foreach ($grouped_by_date as $date_str => $movies): ?>
        <div class="ktn-st-date-group <?php echo $is_first ? 'active' : ''; ?>"
            id="date-<?php echo esc_attr(md5($date_str)); ?>">

            <?php foreach ($movies as $movie_title => $times):
            $movie_record = $times[0];
            $matched_id = $movie_record->matched_movie_id;

            $poster_url = '';
            $permalink = '';
            $genres = '';

            if ($matched_id && get_post_type($matched_id) === 'movie') {
                $permalink = get_permalink($matched_id);
                if (has_post_thumbnail($matched_id)) {
                    $poster_url = get_the_post_thumbnail_url($matched_id, 'medium');
                }
            // Optional: Get genres if available (depends on how genres are stored, assuming 'category' or 'post_tag' or standard WP logic)
            // We will keep it minimal
            }
?>
            <div class="ktn-movie-block">
                <?php if ($poster_url): ?>
                <div class="ktn-movie-poster">
                    <a href="<?php echo esc_url($permalink); ?>">
                        <img src="<?php echo esc_url($poster_url); ?>" alt="<?php echo esc_attr($movie_title); ?>">
                    </a>
                </div>
                <?php
            endif; ?>

                <div class="ktn-movie-info">
                    <h3>
                        <?php if ($permalink): ?>
                        <a href="<?php echo esc_url($permalink); ?>">
                            <?php echo esc_html($movie_title); ?>
                        </a>
                        <?php
            else: ?>
                        <?php echo esc_html($movie_title); ?>
                        <?php
            endif; ?>
                    </h3>

                    <div class="ktn-st-chips-wrapper" style="margin-top: 15px;">
                        <?php foreach ($times as $t): ?>
                        <div class="ktn-st-chip">
                            <span class="ktn-st-time">
                                <?php echo esc_html($t->show_time); ?>
                            </span>
                            <?php if ($t->experience || $t->price_text): ?>
                            <span class="ktn-st-meta">
                                <?php echo esc_html(trim($t->experience . ' ' . $t->price_text)); ?>
                            </span>
                            <?php
                endif; ?>
                        </div>
                        <?php
            endforeach; ?>
                    </div>
                </div>
            </div>
            <?php
        endforeach; ?>

        </div>
        <?php $is_first = false; ?>
        <?php
    endforeach; ?>
    </div>
    <?php
endif; ?>
</div>

<?php
get_footer();
?>