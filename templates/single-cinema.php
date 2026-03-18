<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $post;
$post_id = $post->ID;
$cinema_name = get_the_title($post_id);
$source_url = get_post_meta($post_id, '_ktn_cinema_url', true);

// Optional metadata
$arabic_name = get_post_meta($post_id, '_ktn_cinema_arabic_name', true) ?: get_post_meta($post_id, 'arabic_name', true);
$address = get_post_meta($post_id, '_ktn_cinema_address', true) ?: get_post_meta($post_id, 'address', true);
$city = get_post_meta($post_id, '_ktn_cinema_city', true) ?: get_post_meta($post_id, 'city', true);
$area = get_post_meta($post_id, '_ktn_cinema_area', true) ?: get_post_meta($post_id, 'area', true);
$country = get_post_meta($post_id, '_ktn_cinema_country', true) ?: get_post_meta($post_id, 'country', true);
$notes = get_post_meta($post_id, '_ktn_cinema_notes', true) ?: get_post_meta($post_id, 'notes', true);
if (empty($notes) && !empty($post->post_content)) {
    $notes = $post->post_content;
}
$source_type = get_post_meta($post_id, '_ktn_cinema_type', true);
$map_link = get_post_meta($post_id, '_ktn_cinema_map_link', true) ?: get_post_meta($post_id, 'map_link', true);

// Location string
$location_parts = array_filter([$city, $area, $country]);
$location_str = implode(' • ', $location_parts);

$logo_url = '';
if (has_post_thumbnail($post_id)) {
    $logo_url = get_the_post_thumbnail_url($post_id, 'full');
} else {
    // fallback meta logo
    $logo_url = get_post_meta($post_id, '_ktn_cinema_logo', true) ?: get_post_meta($post_id, 'logo', true);
}

global $wpdb;
$table_showtimes = $wpdb->prefix . 'ktn_showtimes';

$suppress = $wpdb->suppress_errors(true);
$showtimes = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_showtimes WHERE cinema_id = %d ORDER BY show_date ASC, movie_title_scraped ASC, show_time ASC",
    $post_id
));
$wpdb->suppress_errors($suppress);

wp_enqueue_style('ktn-showtimes-css', KTN_PLUGIN_URL . 'assets/css/kontentainment-showtimes.css', array(), '1.2.0');
wp_enqueue_script('ktn-showtimes-js', KTN_PLUGIN_URL . 'assets/js/kontentainment-showtimes.js', array('jquery'), '1.2.0', true);

// Group by date -> movie title
$grouped_by_date = array();
if (!empty($showtimes) && !is_wp_error($showtimes)) {
    foreach ($showtimes as $st) {
        $grouped_by_date[$st->show_date][$st->movie_title_scraped][] = $st;
    }
}
$unique_dates = array_keys($grouped_by_date);
?>

<div class="ktn-cinema-page-wrapper">
    <!-- Cinema Header / Hero -->
    <div class="ktn-cinema-hero-block">
        <div class="ktn-cinema-hero-inner">
            <?php if ($logo_url): ?>
            <div class="ktn-cinema-logo-container">
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($cinema_name); ?> Logo" class="ktn-cinema-logo">
            </div>
            <?php endif; ?>
            
            <div class="ktn-cinema-hero-content">
                <h1 class="ktn-cinema-main-title"><?php echo esc_html($cinema_name); ?></h1>
                
                <?php if ($arabic_name): ?>
                    <h2 class="ktn-cinema-sub-title" dir="rtl"><?php echo esc_html($arabic_name); ?></h2>
                <?php endif; ?>

                <?php if ($address || $location_str): ?>
                <div class="ktn-cinema-meta-info">
                    <?php if ($address): ?>
                        <span class="ktn-cinema-address">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?php echo esc_html($address); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($location_str): ?>
                        <span class="ktn-cinema-location"><?php echo esc_html($location_str); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="ktn-cinema-hero-actions">
                    <?php if ($map_link): ?>
                        <a href="<?php echo esc_url($map_link); ?>" target="_blank" class="ktn-cinema-btn-primary">Get Directions</a>
                    <?php endif; ?>
                    <?php if ($source_type): ?>
                        <span class="ktn-cinema-badge">Source: <?php echo esc_html(str_replace('_', ' ', ucfirst($source_type))); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($notes)): ?>
        <div class="ktn-cinema-notes-panel">
            <div class="ktn-cinema-notes-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-info"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                Cinema Notes
            </div>
            <div class="ktn-cinema-notes-content">
                <?php echo wp_kses_post(wpautop($notes)); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($grouped_by_date)): ?>
    <div class="ktn-cinema-empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-ticket"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 11v2"/><path d="M13 17v2"/></svg>
        <h3>No showtimes currently available</h3>
        <p>There are no movies playing at this cinema right now. Please check back later.</p>
    </div>
    <?php else: ?>

    <!-- Date Switcher -->
    <div class="ktn-modern-date-switcher">
        <div class="ktn-date-tabs-scroll">
            <?php $is_first = true;
            foreach ($unique_dates as $index => $date): 
                $timestamp = strtotime($date);
                $day_name = $timestamp ? date('D', $timestamp) : '';
                $day_num = $timestamp ? date('j', $timestamp) : '';
                $month_name = $timestamp ? date('M', $timestamp) : '';
            ?>
            <button class="ktn-date-tab-btn <?php echo $is_first ? 'active' : ''; ?>"
                data-date-target="date-<?php echo esc_attr(md5($date)); ?>">
                <?php if($timestamp && $day_name): ?>
                    <span class="ktn-date-day-name"><?php echo esc_html($day_name); ?></span>
                    <span class="ktn-date-day-num"><?php echo esc_html($day_num); ?></span>
                    <span class="ktn-date-month"><?php echo esc_html($month_name); ?></span>
                <?php else: ?>
                    <span class="ktn-date-full"><?php echo esc_html($date); ?></span>
                <?php endif; ?>
            </button>
            <?php $is_first = false; endforeach; ?>
        </div>
    </div>

    <!-- Movies List -->
    <div class="ktn-cinema-movies-content">
        <?php $is_first = true;
        foreach ($grouped_by_date as $date_str => $movies): ?>
        <div class="ktn-date-panel <?php echo $is_first ? 'active' : ''; ?>"
            id="date-<?php echo esc_attr(md5($date_str)); ?>">

            <div class="ktn-movie-cards-grid">
                <?php foreach ($movies as $movie_title => $times):
                    $movie_record = $times[0];
                    $matched_id = $movie_record->matched_movie_id;

                    $poster_url = '';
                    $permalink = '';
                    
                    $original_title = '';
                    $runtime = '';
                    $certification = '';
                    $genres_str = '';

                    if ($matched_id && get_post_type($matched_id) === 'movie') {
                        $permalink = get_permalink($matched_id);
                        if (has_post_thumbnail($matched_id)) {
                            $poster_url = get_the_post_thumbnail_url($matched_id, 'medium');
                        }
                        
                        $original_title = get_post_meta($matched_id, '_movie_original_title', true);
                        $runtime = get_post_meta($matched_id, '_movie_runtime', true); // or similar
                        $certification = get_post_meta($matched_id, '_movie_certification', true);
                        
                        $terms = get_the_terms($matched_id, 'category');
                        if (!$terms || is_wp_error($terms)) {
                            $terms = get_the_terms($matched_id, 'movie_genre');
                        }
                        if ($terms && !is_wp_error($terms)) {
                            $genres = wp_list_pluck($terms, 'name');
                            $genres_str = implode(', ', array_slice($genres, 0, 3));
                        }
                    }
                ?>
                <div class="ktn-modern-movie-card">
                    <div class="ktn-card-main-flex">
                        
                        <?php if ($poster_url): ?>
                        <div class="ktn-card-poster">
                            <a href="<?php echo esc_url($permalink); ?>">
                                <img src="<?php echo esc_url($poster_url); ?>" alt="<?php echo esc_attr($movie_title); ?> Poster">
                            </a>
                        </div>
                        <?php else: ?>
                        <!-- Fallback Poster -->
                        <div class="ktn-card-poster ktn-poster-fallback">
                            <?php if ($permalink): ?>
                            <a href="<?php echo esc_url($permalink); ?>">
                                <div class="ktn-fallback-inner">No Poster</div>
                            </a>
                            <?php else: ?>
                                <div class="ktn-fallback-inner">No Poster</div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <div class="ktn-card-content">
                            <h3 class="ktn-card-movie-title">
                                <?php if ($permalink): ?>
                                    <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($movie_title); ?></a>
                                <?php else: ?>
                                    <?php echo esc_html($movie_title); ?>
                                <?php endif; ?>
                            </h3>

                            <?php if ($original_title && strtolower($original_title) !== strtolower($movie_title)): ?>
                                <div class="ktn-card-orig-title"><?php echo esc_html($original_title); ?></div>
                            <?php endif; ?>

                            <div class="ktn-card-movie-meta">
                                <?php if ($certification): ?>
                                    <span class="ktn-meta-cert"><?php echo esc_html($certification); ?></span>
                                <?php endif; ?>
                                <?php if ($runtime): ?>
                                    <span class="ktn-meta-runtime"><?php echo esc_html($runtime); ?> min</span>
                                <?php endif; ?>
                                <?php if ($genres_str): ?>
                                    <span class="ktn-meta-genres"><?php echo esc_html($genres_str); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="ktn-card-showtimes">
                                <?php foreach ($times as $t): ?>
                                <div class="ktn-premium-chip">
                                    <span class="ktn-chip-time"><?php echo esc_html($t->show_time); ?></span>
                                    <?php if ($t->experience || $t->price_text): ?>
                                    <span class="ktn-chip-meta">
                                        <?php echo esc_html(trim($t->experience . ' ' . $t->price_text)); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if ($permalink): ?>
                                <a href="<?php echo esc_url($permalink); ?>" class="ktn-card-cta">View Details &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php $is_first = false; endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>