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

?>
<div class="ktn-media-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">

    <?php if ($backdrop_url): ?>
    <div class="ktn-media-backdrop"
        style="background-image: url('<?php echo esc_url($backdrop_url); ?>'); height: 400px; background-size: cover; background-position: center; border-radius: 12px; margin-bottom: -150px; z-index: 1; position:relative;">
        <div
            style="background: linear-gradient(to top, #fff 0%, transparent 100%); width: 100%; height: 100%; position: absolute; bottom: 0;">
        </div>
    </div>
    <?php
endif; ?>

    <header class="ktn-media-header"
        style="position: relative; z-index: 2; display: flex; flex-wrap: wrap; gap: 30px; padding: 0 20px;">
        <?php if ($poster_url): ?>
        <img src="<?php echo esc_url($poster_url); ?>" alt="<?php the_title_attribute(); ?>"
            style="width: 250px; height: 375px; border-radius: 12px; object-fit: cover; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
        <?php
endif; ?>

        <div class="ktn-media-info" style="flex: 1 1 300px; padding-top: 100px;">
            <h1 style="margin: 0; font-size: 2.5em; line-height: 1.1;">
                <?php the_title(); ?> <span style="color: #666; font-size: 0.7em;">(
                    <?php echo esc_html(substr($release_date, 0, 4)); ?>)
                </span>
            </h1>

            <?php if ($tagline): ?>
            <h3 style="font-style: italic; color: #555; margin-top: 5px;">
                <?php echo esc_html($tagline); ?>
            </h3>
            <?php
endif; ?>

            <div class="ktn-media-meta"
                style="margin: 20px 0; display: flex; gap: 15px; font-size: 0.9em; flex-wrap: wrap;">
                <span class="type" style="background: #eee; padding: 4px 10px; border-radius: 15px;"><strong>
                        <?php echo $post_type === 'tv_show' ? 'TV Show' : 'Movie'; ?>
                    </strong></span>
                <span class="rating"
                    style="background: #f5c518; color: #000; padding: 4px 10px; border-radius: 15px; font-weight: bold;">&#9733;
                    <?php echo esc_html($rating); ?>/10
                </span>
                <span class="runtime" style="background: #eee; padding: 4px 10px; border-radius: 15px;">
                    <?php echo esc_html($runtime); ?> min
                </span>
                <?php if ($certification): ?><span class="cert"
                    style="border: 1px solid #333; padding: 4px 10px; border-radius: 4px;">
                    <?php echo esc_html($certification); ?>
                </span>
                <?php
endif; ?>
            </div>

            <?php if (!empty($genres) && !is_wp_error($genres)): ?>
            <div class="ktn-media-genres" style="margin-bottom: 20px;">
                <?php foreach ($genres as $g): ?>
                <span
                    style="display: inline-block; border: 1px solid #ccc; padding: 3px 10px; border-radius: 20px; font-size: 0.85em; margin-right: 5px;">
                    <?php echo esc_html($g); ?>
                </span>
                <?php
    endforeach; ?>
            </div>
            <?php
endif; ?>

            <div style="font-size: 1.1em; line-height: 1.6;">
                <p><strong>Overview:</strong><br>
                    <?php echo wp_kses_post($overview); ?>
                </p>
            </div>

            <div style="margin-top: 20px; font-size: 0.95em; line-height: 1.5; color: #444;">
                <?php if ($director): ?>
                <p style="margin-bottom: 5px;"><strong>Director:</strong>
                    <strong>
                        <?php echo esc_html($director); ?>
                    </strong>
                </p>
                <?php
endif; ?>
                <?php if (!empty($writers) && is_array($writers)): ?>
                <p style="margin-top: 0;"><strong>Writers:</strong>
                    <strong>
                        <?php echo esc_html(implode(', ', $writers)); ?>
                    </strong>
                </p>
                <?php
endif; ?>
            </div>
        </div>
    </header>

    <section class="ktn-media-credits" style="margin-top: 40px;">
        <h2 style="margin-bottom: 20px; font-size: 2em; font-weight: bold;">Cast</h2>
        <?php if ($cast_json):
    $cast = json_decode($cast_json, true);
    if (!empty($cast)): ?>
        <div style="display: flex; gap: 20px; overflow-x: auto; padding-bottom: 25px; scroll-snap-type: x mandatory;">
            <?php foreach ($cast as $actor):
            $term_link = get_term_link($actor['name'], 'ktn_cast');
            $actor_url = is_wp_error($term_link) ? '#' : esc_url($term_link);
            $actor_img = $actor['profile_path'] ? "https://image.tmdb.org/t/p/w185" . $actor['profile_path'] : "https://via.placeholder.com/185x278?text=No+Photo";
?>
            <a href="<?php echo $actor_url; ?>"
                style="scroll-snap-align: start; text-decoration: none; flex: 0 0 160px; background: #fff; border: 1px solid #e3e3e3; border-radius: 12px; overflow: hidden; color: inherit; display: block; box-shadow: 0 4px 10px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s;">
                <img src="<?php echo esc_url($actor_img); ?>" alt="<?php echo esc_attr($actor['name']); ?>"
                    style="width: 100%; height: 240px; object-fit: cover; display: block;">
                <div style="padding: 15px 12px;">
                    <strong
                        style="display: block; font-size: 1.05em; margin-bottom: 5px; color: #222; line-height: 1.2;">
                        <?php echo esc_html($actor['name']); ?>
                    </strong>
                    <span style="display: block; font-size: 0.85em; color: #777; line-height: 1.3;">
                        <?php echo esc_html($actor['character']); ?>
                    </span>
                </div>
            </a>
            <?php
        endforeach; ?>
        </div>
        <?php
    endif;
endif; ?>
    </section>

        <?php
// --- Redesigned Cinema Showtimes Section ---
global $wpdb;
$table_showtimes = $wpdb->prefix . 'ktn_showtimes';

$suppress = $wpdb->suppress_errors(true);
$today = date('Y-m-d');
$showtimes = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_showtimes 
     WHERE matched_movie_id = %d 
     AND (show_date >= %s OR show_date = 'Today')
     ORDER BY show_date ASC, cinema_name ASC, show_time ASC",
    $post_id,
    $today
));
$wpdb->suppress_errors($suppress);

if (!empty($showtimes) && !is_wp_error($showtimes)):
    wp_enqueue_style('ktn-showtimes-css', KTN_PLUGIN_URL . 'assets/css/kontentainment-showtimes.css', array(), KTN_PLUGIN_VERSION);
    wp_enqueue_script('ktn-showtimes-js', KTN_PLUGIN_URL . 'assets/js/kontentainment-showtimes.js', array('jquery'), KTN_PLUGIN_VERSION, true);

    // Group by date and then cinema
    $grouped_by_date = array();
    foreach ($showtimes as $st) {
        $date_key = $st->show_date;
        if ($date_key === 'Today') {
            $date_key = date('Y-m-d');
        }
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
                
                <!-- Date Switcher -->
                <div class="ktn-st-date-tabs">
                    <div class="ktn-st-tabs-scroll">
                        <?php $is_first = true;
                        foreach ($unique_dates as $index => $date): 
                            $timestamp = strtotime($date);
                            $formatted_date = $timestamp ? date('D, M j', $timestamp) : $date;
                        ?>
                        <button class="ktn-st-date-btn <?php echo $is_first ? 'active' : ''; ?>"
                            data-date-target="media-date-<?php echo esc_attr(md5($date)); ?>">
                            <?php echo esc_html($formatted_date); ?>
                        </button>
                        <?php $is_first = false; endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="ktn-st-main-content">
                <?php $is_first = true;
                foreach ($grouped_by_date as $date_str => $cinemas): ?>
                <div class="ktn-st-date-group <?php echo $is_first ? 'active' : ''; ?>"
                    id="media-date-<?php echo esc_attr(md5($date_str)); ?>">
                    
                    <div class="ktn-st-cinemas-list">
                        <?php foreach ($cinemas as $cinema_name => $times): 
                            $cinema_post_id = $times[0]->cinema_id;
                            $cinema_link = get_permalink($cinema_post_id);
                            $cinema_address = get_post_meta($cinema_post_id, '_ktn_cinema_address', true);
                            $cinema_city = get_post_meta($cinema_post_id, '_ktn_cinema_city', true);
                        ?>
                        <div class="ktn-st-cinema-card">
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
                                <?php 
                                $count = 0;
                                $limit = 3;
                                $total = count($times);
                                foreach ($times as $t): 
                                    $count++;
                                    $is_hidden = ($count > $limit);
                                ?>
                                <div class="ktn-premium-chip <?php echo $is_hidden ? 'ktn-chip-hidden' : ''; ?>">
                                    <span class="ktn-chip-time"><?php echo esc_html($t->show_time); ?></span>
                                    <?php if ($t->experience || $t->price_text): ?>
                                    <span class="ktn-chip-meta">
                                        <?php echo esc_html(trim($t->experience . ' ' . $t->price_text)); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if ($total > $limit): ?>
                                <button class="ktn-show-more-times" data-hidden-count="<?php echo ($total - $limit); ?>">
                                    +<?php echo ($total - $limit); ?> <?php _e('More Times', 'kontentainment'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php endforeach; ?>
                    </div>
                </div>
                <?php $is_first = false; endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>


    <?php if ($trailer_url): ?>
    <section class="ktn-media-trailer" style="margin-top: 40px;">
        <h2>Trailer</h2>
        <div style="max-width: 800px;">
            <?php
    $embed = wp_oembed_get($trailer_url);
    if ($embed) {
        echo '<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; border-radius: 8px;">' . str_replace('<iframe', '<iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"', $embed) . '</div>';
    }
    else {
        echo '<a href="' . esc_url($trailer_url) . '" target="_blank" class="button">Watch Trailer on YouTube</a>';
    }
?>
        </div>
    </section>
    <?php
endif; ?>

    <?php
// --- Related Media Section ---
$genre_terms = wp_get_post_terms($post_id, 'ktn_genre', array('fields' => 'ids'));
$related_args = array(
    'post_type' => $post_type,
    'posts_per_page' => 4,
    'post__not_in' => array($post_id),
    'orderby' => 'rand'
);

if (!empty($genre_terms) && !is_wp_error($genre_terms)) {
    $related_args['tax_query'] = array(
            array(
            'taxonomy' => 'ktn_genre',
            'field' => 'term_id',
            'terms' => $genre_terms,
        )
    );
}

$related_query = new WP_Query($related_args);

if ($related_query->have_posts()):
    $section_title = $post_type === 'tv_show' ? 'Related TV Shows' : 'Related Movies';
?>
    <section class="ktn-related-media" style="margin-top: 50px;">
        <h2 style="margin-bottom: 25px; font-size: 2em; font-weight: bold;">
            <?php echo esc_html($section_title); ?>
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 25px;">
            <?php while ($related_query->have_posts()):
        $related_query->the_post();
        $rel_poster_path = get_post_meta(get_the_ID(), '_movie_poster_path', true);
        $rel_poster_url = $rel_poster_path ? "https://image.tmdb.org/t/p/w500" . $rel_poster_path : "https://via.placeholder.com/500x750?text=No+Poster";
?>
            <a href="<?php the_permalink(); ?>" class="ktn-related-card"
                style="text-decoration: none; color: inherit; display: block; position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s;">
                <img src="<?php echo esc_url($rel_poster_url); ?>" alt="<?php the_title_attribute(); ?>"
                    style="width: 100%; aspect-ratio: 2/3; object-fit: cover; display: block;">
                <div
                    style="position: absolute; bottom: 0; left: 0; right: 0; padding: 40px 15px 15px; background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, transparent 100%); display: flex; align-items: flex-end; justify-content: center;">
                    <strong
                        style="color: #fff; font-size: 1.1em; text-align: center; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                        <?php the_title(); ?>
                    </strong>
                </div>
            </a>
            <?php
    endwhile;
    wp_reset_postdata(); ?>
        </div>
    </section>
    <?php
endif; ?>

</div>
<?php get_footer(); ?>