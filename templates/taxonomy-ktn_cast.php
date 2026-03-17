<?php
/**
 * Taxonomy Template for Cast (Actor)
 */
get_header();

$term = get_queried_object();
$actor_name = $term->name;
$actor_img = "https://via.placeholder.com/300x450?text=No+Photo";
$bio = '';
$known_for_department = '';
$gender = 0;
$birthday = '';
$place_of_birth = '';
$also_known_as = array();
$known_credits_count = 0;
$acting_credits = array();
$socials = array();

// TMDB Info
$token = get_option('ktn_tmdb_bearer_token');
$default_language = get_option('ktn_default_language', 'en-US');
$person_data = null;

if ($token) {
    // 1. Find Person ID by Name
    $cache_key_search = 'ktn_person_search_' . md5($actor_name . $default_language);
    $person_id = get_transient($cache_key_search);

    if (!$person_id) {
        $search_url = "https://api.themoviedb.org/3/search/person?query=" . urlencode($actor_name) . "&language={$default_language}&page=1";
        $response = wp_remote_get($search_url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'),
            'timeout' => 10
        ));
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($data['results'])) {
                $person_id = $data['results'][0]['id'];
                set_transient($cache_key_search, $person_id, 30 * DAY_IN_SECONDS);
            }
        }
    }

    // 2. Fetch Person Details & Credits
    if ($person_id) {
        $cache_key_details = 'ktn_person_details_' . $person_id . '_' . $default_language;
        $person_data = get_transient($cache_key_details);

        if (!$person_data) {
            $details_url = "https://api.themoviedb.org/3/person/{$person_id}?append_to_response=combined_credits,external_ids&language={$default_language}";
            $response = wp_remote_get($details_url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'),
                'timeout' => 15
            ));
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $person_data = json_decode(wp_remote_retrieve_body($response), true);
                set_transient($cache_key_details, $person_data, 7 * DAY_IN_SECONDS);
            }
        }
    }
}

// Map the TMDB data if available
if ($person_data) {
    if (!empty($person_data['profile_path'])) {
        $actor_img = "https://image.tmdb.org/t/p/h632" . $person_data['profile_path'];
    }
    $bio = $person_data['biography'] ?? '';
    $known_for_department = $person_data['known_for_department'] ?? '';
    $gender = $person_data['gender'] ?? 0;
    $birthday = $person_data['birthday'] ?? '';
    $place_of_birth = $person_data['place_of_birth'] ?? '';
    $also_known_as = $person_data['also_known_as'] ?? array();

    if (!empty($person_data['combined_credits']['cast'])) {
        $acting_credits = $person_data['combined_credits']['cast'];
        $known_credits_count = count($acting_credits);

        // Sort credits by release date descending
        usort($acting_credits, function ($a, $b) {
            $date_a = $a['release_date'] ?? $a['first_air_date'] ?? '';
            $date_b = $b['release_date'] ?? $b['first_air_date'] ?? '';
            if (empty($date_a) && empty($date_b))
                return 0;
            if (empty($date_a))
                return -1; // Keep empty dates at top
            if (empty($date_b))
                return 1;
            return strtotime($date_b) - strtotime($date_a);
        });
    }

    if (!empty($person_data['external_ids'])) {
        $socials = $person_data['external_ids'];
    }
}
else {
    // Fallback: look up in local db for profile image
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
    if ($media_query->have_posts()) {
        foreach ($media_query->posts as $p) {
            $cast_json = get_post_meta($p->ID, '_movie_cast', true);
            if ($cast_json) {
                $cast_arr = json_decode($cast_json, true);
                if (!empty($cast_arr)) {
                    foreach ($cast_arr as $actor) {
                        if ($actor['name'] === $actor_name && !empty($actor['profile_path'])) {
                            $actor_img = "https://image.tmdb.org/t/p/h632" . $actor['profile_path'];
                            break 2;
                        }
                    }
                }
            }
        }
    }
}

// Convert Gender
$gender_text = '-';
if ($gender === 1)
    $gender_text = 'Female';
elseif ($gender === 2)
    $gender_text = 'Male';
elseif ($gender === 3)
    $gender_text = 'Non-binary';

// Calculate Age
$age_text = '';
if ($birthday) {
    try {
        $birthDate = new DateTime($birthday);
        $now = new DateTime();
        $age = $now->diff($birthDate)->y;
        $age_text = " ({$age} years old)";
        $birthday = date('F j, Y', strtotime($birthday));
    }
    catch (Exception $e) {
    // fail silently if datetime parsing fails
    }
}

// Check local posts for "Known For" grid
$args = array(
    'post_type' => array('movie', 'tv_show'),
    'tax_query' => array(
            array(
            'taxonomy' => 'ktn_cast',
            'field' => 'term_id',
            'terms' => $term->term_id,
        ),
    ),
    'posts_per_page' => 8,
);
$local_media_query = new WP_Query($args);

// SVG Icons
$fb_icon = '<svg fill="currentColor" width="24" height="24" viewBox="0 0 24 24"><path d="M12 2.04c-5.5 0-10 4.48-10 10.02 0 5 3.66 9.15 8.44 9.9v-7H7.9v-2.9h2.54V9.67c0-2.5 1.48-3.9 3.75-3.9 1.1 0 2.22.2 2.22.2v2.46h-1.25c-1.23 0-1.6.76-1.6 1.54v1.84h2.75l-.44 2.9h-2.3v7C18.34 21.2 22 17.06 22 12.06c0-5.54-4.5-10.02-10-10.02z"/></svg>';
$ig_icon = '<svg fill="currentColor" width="24" height="24" viewBox="0 0 24 24"><path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2zm-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.4 5.6 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.6 18.4 4 16.4 4H7.6zm4.4 3.5a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9zm0 2a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zm5.3-1.4a1.1 1.1 0 1 1-2.2 0 1.1 1.1 0 0 1 2.2 0z"/></svg>';
$tw_icon = '<svg fill="currentColor" width="24" height="24" viewBox="0 0 24 24"><path d="M22.46 6c-.77.35-1.6.58-2.46.69.88-.53 1.56-1.37 1.88-2.38-.83.5-1.75.85-2.72 1.05C18.37 4.5 17.26 4 16 4c-2.35 0-4.27 1.92-4.27 4.29 0 .34.04.67.11.98C8.28 9.09 5.11 7.38 3 4.79c-.37.63-.58 1.37-.58 2.15 0 1.49.75 2.81 1.91 3.56-.71 0-1.37-.2-1.95-.5v.03c0 2.08 1.48 3.82 3.44 4.21a4.22 4.22 0 0 1-1.93.07 4.28 4.28 0 0 0 4 2.98 8.52 8.52 0 0 1-5.33 1.84c-.34 0-.68-.02-1.02-.06C3.44 20.29 5.7 21 8.12 21 16 21 20.33 14.46 20.33 8.79c0-.19 0-.37-.01-.56.84-.6 1.56-1.36 2.14-2.23z"/></svg>';
?>

<div class="ktn-cast-container"
    style="max-width: 1200px; margin: 40px auto; padding: 20px; color: #000; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <div style="display: flex; flex-wrap: wrap; gap: 40px;">
        <!-- Left Sidebar: Photo & Personal Info -->
        <div style="flex: 0 0 300px;">
            <img src="<?php echo esc_url($actor_img); ?>" alt="<?php echo esc_attr($actor_name); ?>"
                style="width: 100%; border-radius: 8px; display: block; margin-bottom: 25px;">

            <?php if (!empty($socials)): ?>
            <div style="display: flex; gap: 15px; margin-bottom: 30px;">
                <?php if (!empty($socials['facebook_id'])): ?>
                <a href="https://facebook.com/<?php echo esc_attr($socials['facebook_id']); ?>" target="_blank"
                    style="color: #333;">
                    <?php echo $fb_icon; ?>
                </a>
                <?php
    endif; ?>
                <?php if (!empty($socials['instagram_id'])): ?>
                <a href="https://instagram.com/<?php echo esc_attr($socials['instagram_id']); ?>" target="_blank"
                    style="color: #333;">
                    <?php echo $ig_icon; ?>
                </a>
                <?php
    endif; ?>
                <?php if (!empty($socials['twitter_id'])): ?>
                <a href="https://twitter.com/<?php echo esc_attr($socials['twitter_id']); ?>" target="_blank"
                    style="color: #333;">
                    <?php echo $tw_icon; ?>
                </a>
                <?php
    endif; ?>
            </div>
            <?php
endif; ?>

            <h3 style="font-size: 1.4em; font-weight: bold; margin-bottom: 15px;">Personal Info</h3>

            <div style="margin-bottom: 20px;">
                <strong style="display:block; font-size: 1em;">Known For</strong>
                <span style="font-size: 0.95em; color: #444;">
                    <?php echo esc_html($known_for_department ? $known_for_department : 'Acting'); ?>
                </span>
            </div>
            <div style="margin-bottom: 20px;">
                <strong style="display:block; font-size: 1em;">Known Credits</strong>
                <span style="font-size: 0.95em; color: #444;">
                    <?php echo esc_html($known_credits_count); ?>
                </span>
            </div>
            <div style="margin-bottom: 20px;">
                <strong style="display:block; font-size: 1em;">Gender</strong>
                <span style="font-size: 0.95em; color: #444;">
                    <?php echo esc_html($gender_text); ?>
                </span>
            </div>
            <div style="margin-bottom: 20px;">
                <strong style="display:block; font-size: 1em;">Birthday</strong>
                <span style="font-size: 0.95em; color: #444;">
                    <?php echo esc_html($birthday); ?>
                    <?php echo esc_html($age_text); ?>
                </span>
            </div>
            <div style="margin-bottom: 20px;">
                <strong style="display:block; font-size: 1em;">Place of Birth</strong>
                <span style="font-size: 0.95em; color: #444;">
                    <?php echo esc_html($place_of_birth ? $place_of_birth : '-'); ?>
                </span>
            </div>

            <?php if (!empty($also_known_as)): ?>
            <div style="margin-bottom: 20px;">
                <strong style="display:block; font-size: 1em; margin-bottom: 5px;">Also Known As</strong>
                <?php foreach (array_slice($also_known_as, 0, 5) as $aka): ?>
                <span style="display:block; font-size: 0.9em; color: #444; margin-bottom: 4px;">
                    <?php echo esc_html($aka); ?>
                </span>
                <?php
    endforeach; ?>
            </div>
            <?php
endif; ?>
        </div>

        <!-- Right Content -->
        <div style="flex: 1 1 500px; max-width: 820px;">
            <h1 style="margin-top: 0; font-size: 2.2em; font-weight: bold; margin-bottom: 20px;">
                <?php echo esc_html($actor_name); ?>
            </h1>

            <?php if ($bio): ?>
            <div style="margin-bottom: 40px;">
                <h2 style="font-size: 1.3em; font-weight: bold; margin-bottom: 12px;">Biography</h2>
                <div style="font-size: 1em; line-height: 1.6; color: #000;">
                    <?php echo wp_kses_post(nl2br($bio)); ?>
                </div>
            </div>
            <?php
endif; ?>

            <?php if ($local_media_query->have_posts()): ?>
            <div style="margin-bottom: 40px;">
                <h2 style="font-size: 1.3em; font-weight: bold; margin-bottom: 15px;">Known For</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 15px;">
                    <?php while ($local_media_query->have_posts()):
        $local_media_query->the_post();
        $poster_path = get_post_meta(get_the_ID(), '_movie_poster_path', true);
        $poster_url = $poster_path ? "https://image.tmdb.org/t/p/w500" . $poster_path : "https://via.placeholder.com/500x750?text=No+Poster";
?>
                    <a href="<?php the_permalink(); ?>" class="ktn-related-card"
                        style="text-decoration: none; color: inherit; display: block; position: relative; border-radius: 8px; overflow: hidden; background: #fff; text-align: center;">
                        <img src="<?php echo esc_url($poster_url); ?>" alt="<?php the_title_attribute(); ?>"
                            style="width: 100%; aspect-ratio: 2/3; object-fit: cover; display: block; border-radius: 8px;">
                        <div style="padding: 10px 0;">
                            <span style="color: #000; font-size: 0.9em; line-height: 1.2; display: block;">
                                <?php the_title(); ?>
                            </span>
                        </div>
                    </a>
                    <?php
    endwhile;
    wp_reset_postdata(); ?>
                </div>
            </div>
            <?php
endif; ?>

            <?php if (!empty($acting_credits)): ?>
            <div>
                <h2 style="font-size: 1.3em; font-weight: bold; margin-bottom: 15px;">Acting</h2>
                <div
                    style="border: 1px solid #e3e3e3; border-radius: 8px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 40px;">
                    <?php
    foreach ($acting_credits as $credit):
        $release_date = $credit['release_date'] ?? $credit['first_air_date'] ?? '';
        $year = $release_date ? substr($release_date, 0, 4) : '—';
        $title = $credit['title'] ?? $credit['name'] ?? '';
        $character = $credit['character'] ?? '';
?>
                    <div
                        style="display: flex; padding: 15px 20px; border-bottom: 1px solid #f0f0f0; align-items: flex-start;">
                        <div style="flex: 0 0 50px; color: #000; font-size: 1em; margin-top: 1px;">
                            <?php echo esc_html($year); ?>
                        </div>
                        <div style="flex: 0 0 30px; display: flex; justify-content: center; align-items: center;">
                            <span
                                style="display: block; width: 10px; height: 10px; border-radius: 50%; border: 2px solid #000; margin-top: 5px;"></span>
                        </div>
                        <div style="flex: 1; padding-left: 10px;">
                            <strong style="color: #000; font-size: 1em; display: block; margin-bottom: 2px;">
                                <?php echo esc_html($title); ?>
                            </strong>
                            <?php if ($character): ?>
                            <span style="color: #666; font-size: 0.95em; display: block;">
                                as
                                <?php echo esc_html($character); ?>
                            </span>
                            <?php
        endif; ?>
                            <?php if (isset($credit['episode_count']) && $credit['episode_count'] > 0): ?>
                            <span style="color: #999; font-size: 0.85em; display: inline-block; margin-top: 2px;">
                                <?php echo esc_html($credit['episode_count']); ?> episode
                                <?php echo ($credit['episode_count'] > 1 ? 's' : ''); ?>
                            </span>
                            <?php
        endif; ?>
                        </div>
                    </div>
                    <?php
    endforeach; ?>
                </div>
            </div>
            <?php
endif; ?>

        </div>
    </div>

</div>
<?php get_footer(); ?>