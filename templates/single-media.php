<?php
/**
 * Single Media Template (Movie or TV Show)
 */
get_header();

$post_id = get_the_ID();
$post_type = get_post_type();
$tagline = get_post_meta($post_id, '_movie_tagline', true);
$overview = get_post_meta($post_id, '_movie_overview', true);
$release_date = get_post_meta($post_id, '_movie_release_date', true);
$runtime = get_post_meta($post_id, '_movie_runtime', true);
$imdb_id = get_post_meta($post_id, '_movie_imdb_id', true);
$tmdb_id = get_post_meta($post_id, '_movie_tmdb_id', true);
$rating = get_post_meta($post_id, '_movie_vote_average', true);
$director = get_post_meta($post_id, '_movie_director', true);
$writers = get_post_meta($post_id, '_movie_writers', true);
$cast_json = get_post_meta($post_id, '_movie_cast', true);
$trailer_url = get_post_meta($post_id, '_movie_trailer_url', true);
$certification = get_post_meta($post_id, '_movie_release_certification', true);
$poster_path = get_post_meta($post_id, '_movie_poster_path', true);
$backdrop_path = get_post_meta($post_id, '_movie_backdrop_path', true);

$genres = wp_get_post_terms($post_id, 'ktn_genre', array('fields' => 'names'));

$poster_url = $poster_path ? "https://image.tmdb.org/t/p/w500" . $poster_path : '';
$backdrop_url = $backdrop_path ? "https://image.tmdb.org/t/p/w1280" . $backdrop_path : '';

// Enqueue styles
wp_enqueue_style('ktn-single-movie', KTN_PLUGIN_URL . 'assets/css/kontentainment-single-movie.css', array(), KTN_PLUGIN_VERSION);
?>

<div class="ktn-media-container">

    <div class="ktn-media-backdrop" style="<?php echo $backdrop_url ? "background-image: url('" . esc_url($backdrop_url) . "');" : "background: #1e293b;"; ?>">
        <div class="ktn-media-backdrop-fade"></div>
    </div>
    
    <header class="ktn-media-header">
        <?php if ($poster_url): ?>
        <img src="<?php echo esc_url($poster_url); ?>" width="250" height="375" alt="<?php the_title_attribute(); ?>" class="ktn-media-poster">
        <?php else: ?>
        <div class="ktn-media-poster placeholder"></div>
        <?php endif; ?>

        <div class="ktn-media-info">
            <h1 class="ktn-media-title">
                <?php the_title(); ?> 
                <?php if ($release_date): ?>
                <span class="ktn-media-year">(<?php echo esc_html(substr($release_date, 0, 4)); ?>)</span>
                <?php endif; ?>
            </h1>

            <?php if ($tagline): ?>
            <h3 class="ktn-media-tagline"><?php echo esc_html($tagline); ?></h3>
            <?php endif; ?>

            <div class="ktn-media-meta">
                <span class="ktn-meta-badge type"><?php echo $post_type === 'tv_show' ? 'TV Show' : 'Movie'; ?></span>
                <?php if ($rating): ?>
                <span class="ktn-meta-badge rating">&#9733; <?php echo esc_html($rating); ?>/10</span>
                <?php endif; ?>
                <?php if ($runtime): ?>
                <span class="ktn-meta-badge runtime"><?php echo esc_html($runtime); ?> min</span>
                <?php endif; ?>
                <?php if ($certification): ?>
                <span class="ktn-meta-badge cert"><?php echo esc_html($certification); ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($genres) && !is_wp_error($genres)): ?>
            <div class="ktn-media-genres">
                <?php foreach ($genres as $g): ?>
                <span class="ktn-genre-pill"><?php echo esc_html($g); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="ktn-media-overview">
                <p><strong>Overview:</strong><br>
                    <?php echo wp_kses_post($overview); ?>
                </p>
            </div>

            <div class="ktn-media-credits">
                <?php if ($director): ?>
                <div class="ktn-credit-item">
                    <strong>Director:</strong>
                    <span><?php echo esc_html($director); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($writers) && is_array($writers)): ?>
                <div class="ktn-credit-item">
                    <strong>Writers:</strong>
                    <span><?php echo esc_html(implode(', ', $writers)); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ($cast_json):
        $cast = json_decode($cast_json, true);
        if (!empty($cast)): ?>
        <section class="ktn-media-credits-section" style="margin-top: 50px;">
            <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 25px;">Cast</h2>
            <div style="display: flex; gap: 20px; overflow-x: auto; padding-bottom: 25px; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;">
                <?php foreach ($cast as $actor):
                    $term_link = get_term_link($actor['name'], 'ktn_cast');
                    $actor_url = is_wp_error($term_link) ? '#' : esc_url($term_link);
                    $actor_img = $actor['profile_path'] ? "https://image.tmdb.org/t/p/w185" . $actor['profile_path'] : "https://via.placeholder.com/185x278?text=No+Photo";
                ?>
                <a href="<?php echo $actor_url; ?>" style="scroll-snap-align: start; text-decoration: none; flex: 0 0 160px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; color: inherit; display: block; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); transition: all 0.2s;">
                    <img src="<?php echo esc_url($actor_img); ?>" alt="<?php echo esc_attr($actor['name']); ?>" style="width: 100%; height: 220px; object-fit: cover; display: block;">
                    <div style="padding: 12px;">
                        <strong style="display: block; font-size: 0.95rem; margin-bottom: 4px; color: #0f172a; line-height: 1.2;"><?php echo esc_html($actor['name']); ?></strong>
                        <span style="display: block; font-size: 0.8rem; color: #64748b; line-height: 1.3;"><?php echo esc_html($actor['character']); ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif;
    endif; ?>

    <?php
    global $wpdb;
    $table_showtimes = $wpdb->prefix . 'ktn_showtimes';
    $today = date('Y-m-d');
    $showtimes = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_showtimes 
         WHERE matched_movie_id = %d 
         AND (show_date >= %s OR show_date = 'Today')
         ORDER BY show_date ASC, cinema_name ASC, show_time ASC",
        $post_id,
        $today
    ));

    if (!empty($showtimes)):
        wp_enqueue_style('ktn-showtimes-css', KTN_PLUGIN_URL . 'assets/css/kontentainment-showtimes.css', array(), KTN_PLUGIN_VERSION);
        wp_enqueue_script('ktn-showtimes-js', KTN_PLUGIN_URL . 'assets/js/kontentainment-showtimes.js', array('jquery'), KTN_PLUGIN_VERSION, true);

        $grouped_by_date = array();
        foreach ($showtimes as $st) {
            $date_key = ($st->show_date === 'Today') ? date('Y-m-d') : $st->show_date;
            $grouped_by_date[$date_key][$st->cinema_name][] = $st;
        }
        ksort($grouped_by_date);
        $unique_dates = array_keys($grouped_by_date);
    ?>
    <section class="ktn-media-showtimes-section">
        <div class="ktn-st-container">
            <div class="ktn-st-header-flex">
                <h2 class="ktn-st-section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-ticket-play"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="m9 9 5 3-5 3Z"/></svg>
                    Theater Showtimes
                </h2>
                <div class="ktn-st-date-tabs">
                    <div class="ktn-st-tabs-scroll">
                        <?php $is_first = true; foreach ($unique_dates as $date): ?>
                        <button class="ktn-st-date-btn <?php echo $is_first ? 'active' : ''; ?>" data-date-target="media-date-<?php echo esc_attr(md5($date)); ?>">
                            <?php echo esc_html(strtotime($date) ? date('D, M j', strtotime($date)) : $date); ?>
                        </button>
                        <?php $is_first = false; endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="ktn-st-main-content">
                <?php $is_first = true; foreach ($grouped_by_date as $date_str => $cinemas): ?>
                <div class="ktn-st-date-group <?php echo $is_first ? 'active' : ''; ?>" id="media-date-<?php echo esc_attr(md5($date_str)); ?>">
                    <div class="ktn-st-cinemas-list">
                        <?php 
                        $cinema_count = 0;
                        $cinema_limit = 3;
                        $total_cinemas = count($cinemas);
                        foreach ($cinemas as $cinema_name => $times): 
                            $cinema_count++;
                            $cinema_post_id = $times[0]->cinema_id;
                            $cinema_link = get_permalink($cinema_post_id);
                            $cinema_address = get_post_meta($cinema_post_id, '_ktn_cinema_address', true);
                            $cinema_city = get_post_meta($cinema_post_id, '_ktn_cinema_city', true);
                            $is_cinema_hidden = ($cinema_count > $cinema_limit);
                        ?>
                        <div class="ktn-st-cinema-card <?php echo $is_cinema_hidden ? 'ktn-cinema-card-hidden' : ''; ?>" <?php echo $is_cinema_hidden ? 'style="display:none;"' : ''; ?>>
                            <div class="ktn-cinema-card-header">
                                <div class="ktn-cinema-card-info">
                                    <h3 class="ktn-cinema-card-name">
                                        <?php if ($cinema_link): ?>
                                            <a href="<?php echo esc_url($cinema_link); ?>"><?php echo esc_html($cinema_name); ?></a>
                                        <?php else: ?>
                                            <?php echo esc_html($cinema_name); ?>
                                        <?php endif; ?>
                                    </h3>
                                    <?php if ($cinema_address || $cinema_city): ?>
                                        <div class="ktn-cinema-card-meta">
                                            <?php echo esc_html(implode(' • ', array_filter([$cinema_address, $cinema_city]))); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($cinema_link): ?>
                                    <a href="<?php echo esc_url($cinema_link); ?>" class="ktn-cinema-card-link">View Cinema &rarr;</a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="ktn-cinema-card-times">
                                <?php foreach ($times as $t): ?>
                                <div class="ktn-premium-chip">
                                    <span class="ktn-chip-time"><?php echo esc_html($t->show_time); ?></span>
                                    <?php if ($t->experience || $t->price_text): ?>
                                    <span class="ktn-chip-meta"><?php echo esc_html(trim($t->experience . ' ' . $t->price_text)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php if ($total_cinemas > $cinema_limit): ?>
                        <div class="ktn-st-load-more-cinemas-wrapper">
                            <button class="ktn-st-load-more-btn" data-hidden-count="<?php echo ($total_cinemas - $cinema_limit); ?>">
                                <span class="ktn-btn-text">+<?php echo ($total_cinemas - $cinema_limit); ?> <?php _e('More Cinemas', 'kontentainment'); ?></span>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php $is_first = false; endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($trailer_url): ?>
    <section class="ktn-media-trailer" style="margin-top: 50px;">
        <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 25px;">Trailer</h2>
        <div style="max-width: 900px; margin: 0 auto;">
            <?php
            $embed = wp_oembed_get($trailer_url);
            if ($embed) {
                echo '<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">' . str_replace('<iframe', '<iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"', $embed) . '</div>';
            } else {
                echo '<a href="' . esc_url($trailer_url) . '" target="_blank" class="button">Watch Trailer on YouTube</a>';
            }
            ?>
        </div>
    </section>
    <?php endif; ?>

    <?php
    $genre_terms = wp_get_post_terms($post_id, 'ktn_genre', array('fields' => 'ids'));
    $related_args = array(
        'post_type' => $post_type,
        'posts_per_page' => 4,
        'post__not_in' => array($post_id),
        'orderby' => 'rand'
    );
    if (!empty($genre_terms) && !is_wp_error($genre_terms)) {
        $related_args['tax_query'] = array(array('taxonomy' => 'ktn_genre', 'field' => 'term_id', 'terms' => $genre_terms));
    }
    $related_query = new WP_Query($related_args);

    if ($related_query->have_posts()):
        $section_title = $post_type === 'tv_show' ? 'Related TV Shows' : 'Related Movies';
    ?>
    <section class="ktn-related-media">
        <h2 class="ktn-related-title"><?php echo esc_html($section_title); ?></h2>
        <div class="ktn-related-grid-wrapper">
            <div class="ktn-related-grid">
                <?php while ($related_query->have_posts()): $related_query->the_post();
                    $rel_id = get_the_ID();
                    $rel_poster_path = get_post_meta($rel_id, '_movie_poster_path', true);
                    $rel_poster_url = $rel_poster_path ? "https://image.tmdb.org/t/p/w500" . $rel_poster_path : "https://via.placeholder.com/500x750?text=No+Poster";
                ?>
                <a href="<?php the_permalink(); ?>" class="ktn-related-card">
                    <img src="<?php echo esc_url($rel_poster_url); ?>" alt="<?php the_title_attribute(); ?>" class="ktn-related-poster">
                    <div class="ktn-related-info">
                        <strong class="ktn-related-name"><?php the_title(); ?></strong>
                    </div>
                </a>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

</div>

<?php get_footer(); ?>