<?php
/**
 * Taxonomy Template for Cast (Actor)
 */
get_header();

$term = get_queried_object();

// We don't have the actor's profile picture or bio saved natively in term meta.
// However, we can look up the first movie they are in, and extract their profile picture from _movie_cast.
$actor_img = "https://via.placeholder.com/300x450?text=No+Photo";
$actor_name = $term->name;

$args = array(
    'post_type' => array('movie', 'tv_show'),
    'tax_query' => array(
            array(
            'taxonomy' => 'ktn_cast',
            'field' => 'term_id',
            'terms' => $term->term_id,
        ),
    ),
    'posts_per_page' => -1,
);

$media_query = new WP_Query($args);

// Try to find the profile picture
if ($media_query->have_posts()) {
    foreach ($media_query->posts as $p) {
        $cast_json = get_post_meta($p->ID, '_movie_cast', true);
        if ($cast_json) {
            $cast_arr = json_decode($cast_json, true);
            if (!empty($cast_arr)) {
                foreach ($cast_arr as $actor) {
                    if ($actor['name'] === $actor_name && !empty($actor['profile_path'])) {
                        $actor_img = "https://image.tmdb.org/t/p/h632" . $actor['profile_path'];
                        break 2; // break both loops
                    }
                }
            }
        }
    }
}
?>
<div class="ktn-cast-container" style="max-width: 1200px; margin: 40px auto; padding: 20px;">

    <div style="display: flex; flex-wrap: wrap; gap: 40px;">
        <!-- Left Sidebar: Photo -->
        <div style="flex: 0 0 300px;">
            <img src="<?php echo esc_url($actor_img); ?>" alt="<?php echo esc_attr($actor_name); ?>"
                style="width: 100%; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); display: block;">
        </div>

        <!-- Right Content: Name & Media -->
        <div style="flex: 1 1 500px;">
            <h1 style="margin-top: 0; font-size: 3em; font-weight: bold; margin-bottom: 30px;">
                <?php echo esc_html($actor_name); ?>
            </h1>

            <h2 style="font-size: 1.8em; margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                Known For</h2>

            <?php if ($media_query->have_posts()): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px;">
                <?php while ($media_query->have_posts()):
        $media_query->the_post();
        $poster_path = get_post_meta(get_the_ID(), '_movie_poster_path', true);
        $poster_url = $poster_path ? "https://image.tmdb.org/t/p/w500" . $poster_path : "https://via.placeholder.com/500x750?text=No+Poster";
?>
                <a href="<?php the_permalink(); ?>" class="ktn-related-card"
                    style="text-decoration: none; color: inherit; display: block; position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s;">
                    <img src="<?php echo esc_url($poster_url); ?>" alt="<?php the_title_attribute(); ?>"
                        style="width: 100%; aspect-ratio: 2/3; object-fit: cover; display: block;">
                    <div
                        style="position: absolute; bottom: 0; left: 0; right: 0; padding: 40px 10px 10px; background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, transparent 100%); display: flex; align-items: flex-end; justify-content: center;">
                        <strong
                            style="color: #fff; font-size: 1em; text-align: center; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                            <?php the_title(); ?>
                        </strong>
                    </div>
                </a>
                <?php
    endwhile;
    wp_reset_postdata(); ?>
            </div>
            <?php
else: ?>
            <p>No titles found for this actor.</p>
            <?php
endif; ?>
        </div>
    </div>

</div>
<?php get_footer(); ?>