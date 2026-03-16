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
        </div>
    </header>

    <section class="ktn-media-credits" style="margin-top: 40px;">
        <h2>Top Cast</h2>
        <?php if ($cast_json):
    $cast = json_decode($cast_json, true);
    if (!empty($cast)): ?>
        <div style="display: flex; gap: 15px; overflow-x: auto; padding-bottom: 20px;">
            <?php foreach ($cast as $actor):
            $term_link = get_term_link($actor['name'], 'ktn_cast');
            $actor_url = is_wp_error($term_link) ? '#' : esc_url($term_link);
            $actor_img = $actor['profile_path'] ? "https://image.tmdb.org/t/p/w185" . $actor['profile_path'] : "https://via.placeholder.com/138x175?text=No+Photo";
?>
            <a href="<?php echo $actor_url; ?>"
                style="text-decoration: none; flex: 0 0 138px; border: 1px solid #eaeaea; border-radius: 8px; overflow: hidden; color: inherit; display: block; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: transform 0.2s;">
                <img src="<?php echo esc_url($actor_img); ?>" alt="<?php echo esc_attr($actor['name']); ?>"
                    style="width: 138px; height: 175px; object-fit: cover; display: block;">
                <div style="padding: 10px;">
                    <strong style="display: block; font-size: 0.9em; margin-bottom: 4px; color: #000;">
                        <?php echo esc_html($actor['name']); ?>
                    </strong>
                    <span style="display: block; font-size: 0.8em; color: #666;">
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

        <div style="margin-top: 20px;">
            <?php if ($director): ?>
            <p><strong>Director:</strong>
                <?php echo esc_html($director); ?>
            </p>
            <?php
endif; ?>
            <?php if (!empty($writers) && is_array($writers)): ?>
            <p><strong>Writers:</strong>
                <?php echo esc_html(implode(', ', $writers)); ?>
            </p>
            <?php
endif; ?>
        </div>
    </section>

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

    <footer style="margin-top: 40px; border-top: 1px solid #ccc; padding-top: 20px; font-size: 0.8em; color: #777;">
        <p>IMDb ID:
            <?php echo esc_html($imdb_id); ?> | TMDB ID:
            <?php echo esc_html($tmdb_id); ?>
        </p>
    </footer>

</div>
<?php get_footer(); ?>