<?php
/**
 * Taxonomy Template for Cast (Actor)
 */
get_header();

$term = get_queried_object();
$actor_name = $term->name;

// Determine if this is an Arabic movie artist
$is_arabic_artist = false;
if ($term && is_a($term, 'WP_Term')) {
    $local_posts = get_posts(array(
        'post_type' => array('movie', 'tv_show'),
        'tax_query' => array(
            array(
                'taxonomy' => 'ktn_cast',
                'field' => 'term_id',
                'terms' => $term->term_id,
            )
        ),
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    if (!empty($local_posts)) {
        foreach ($local_posts as $p_id) {
            $lang = get_post_meta($p_id, '_movie_original_language', true);
            if ($lang === 'ar') {
                $is_arabic_artist = true;
                break;
            }
        }
    }
}

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

// Translate cast profile fields if this is an Arabic artist
if ($is_arabic_artist && !is_admin()) {
    // Translate name
    $arabic_name = get_term_meta($term->term_id, '_ktn_cast_arabic_name', true);
    if (empty($arabic_name)) {
        $arabic_name = ktn_translate_text_free($actor_name);
        if (!empty($arabic_name)) {
            update_term_meta($term->term_id, '_ktn_cast_arabic_name', $arabic_name);
        }
    }
    if (!empty($arabic_name)) {
        $actor_name = $arabic_name;
    }

    // Translate bio
    $arabic_bio = get_term_meta($term->term_id, '_ktn_cast_arabic_bio', true);
    if (empty($arabic_bio) && !empty($bio)) {
        $arabic_bio = ktn_translate_text_free($bio);
        if (!empty($arabic_bio)) {
            update_term_meta($term->term_id, '_ktn_cast_arabic_bio', $arabic_bio);
        }
    }
    if (!empty($arabic_bio)) {
        $bio = $arabic_bio;
    }

    // Translate place of birth
    $arabic_place_of_birth = get_term_meta($term->term_id, '_ktn_cast_arabic_place_of_birth', true);
    if (empty($arabic_place_of_birth) && !empty($place_of_birth)) {
        $arabic_place_of_birth = ktn_translate_text_free($place_of_birth);
        if (!empty($arabic_place_of_birth)) {
            update_term_meta($term->term_id, '_ktn_cast_arabic_place_of_birth', $arabic_place_of_birth);
        }
    }
    if (!empty($arabic_place_of_birth)) {
        $place_of_birth = $arabic_place_of_birth;
    }
}

// Convert Gender
$gender_text = '-';
if ($gender === 1)
    $gender_text = __('Female', 'kontentainment');
elseif ($gender === 2)
    $gender_text = __('Male', 'kontentainment');
elseif ($gender === 3)
    $gender_text = __('Non-binary', 'kontentainment');

// Calculate Age
$age_text = '';
if ($birthday) {
    try {
        $birthDate = new DateTime($birthday);
        $now = new DateTime();
        $age = $now->diff($birthDate)->y;
        $age_text = sprintf(esc_html__(' (%d years old)', 'kontentainment'), $age);
        $birthday_ts = strtotime($birthday);
        $birthday = $birthday_ts ? sprintf(esc_html__('%1$s %2$d, %3$d', 'kontentainment'), __(date('F', $birthday_ts), 'kontentainment'), date('j', $birthday_ts), date('Y', $birthday_ts)) : '';
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

<style>
.ktn-actor-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Almarai"; }
.ktn-actor-layout { display: flex; flex-direction: column; gap: 40px; }
@media (min-width: 900px) { .ktn-actor-layout { flex-direction: row; } }
.ktn-actor-sidebar { flex: 0 0 300px; }
.ktn-actor-sidebar-sticky { position: sticky; top: 40px; }
.ktn-actor-photo { width: 100%; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-bottom: 25px; object-fit: cover; aspect-ratio: 2/3; }
.ktn-actor-socials { display: flex; gap: 15px; margin-bottom: 30px; justify-content: center; }
.ktn-actor-social-link { color: #0f172a; transition: all 0.2s; display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; background: #f1f5f9; border-radius: 50%; text-decoration: none; }
.ktn-actor-social-link:hover { color: #fff; transform: translateY(-3px); background: #0f172a; }
.ktn-actor-info-box { background: #f8fafc; border-radius: 16px; padding: 25px; border: 1px solid #e2e8f0; }
.ktn-actor-info-box h3 { font-size: 1.2em; margin: 0 0 20px 0; font-weight: 700; color: #0f172a; }
.ktn-actor-info-item { margin-bottom: 15px; }
.ktn-actor-info-item:last-child { margin-bottom: 0; }
.ktn-actor-info-label { display: block; font-size: 0.85em; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
.ktn-actor-info-value { display: block; font-size: 1em; color: #0f172a; font-weight: 600; line-height: 1.4; }

.ktn-actor-main { flex: 1; min-width: 0; }
.ktn-actor-name { font-size: 3em; font-weight: 900; margin: 0 0 25px 0; color: #0f172a; letter-spacing: -0.5px; line-height: 1.2; }
.ktn-actor-section-title { font-size: 1.6em; font-weight: 800; margin: 0 0 20px 0; color: #0f172a; }
.ktn-actor-bio { font-size: 1.1em; line-height: 1.8; color: #334155; margin-bottom: 50px; }

.ktn-known-for-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 20px; margin-bottom: 60px; }
.ktn-known-card { text-decoration: none; display: block; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.ktn-known-card:hover { transform: translateY(-8px); }
.ktn-known-poster { width: 100%; aspect-ratio: 2/3; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); object-fit: cover; margin-bottom: 12px; transition: box-shadow 0.3s; }
.ktn-known-card:hover .ktn-known-poster { box-shadow: 0 12px 25px rgba(0,0,0,0.15); }
.ktn-known-title { font-size: 0.95em; font-weight: 700; color: #0f172a; text-align: center; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }

.ktn-acting-timeline { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); overflow: hidden; margin-bottom: 60px; }
.ktn-acting-row { display: flex; padding: 20px 25px; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; align-items: center; gap: 25px; }
.ktn-acting-row:hover { background: #f8fafc; }
.ktn-acting-row:last-child { border-bottom: none; }
.ktn-acting-year { flex: 0 0 60px; font-weight: 800; color: #0f172a; font-size: 1.15em; text-align: center; }
.ktn-acting-dot-col { flex: 0 0 20px; display: flex; justify-content: center; }
.ktn-acting-dot { width: 14px; height: 14px; border-radius: 50%; border: 3px solid #cbd5e1; background: #fff; transition: all 0.2s; }
.ktn-acting-row:hover .ktn-acting-dot { border-color: #3b82f6; background: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
.ktn-acting-details { flex: 1; }
.ktn-acting-title { font-weight: 800; color: #0f172a; font-size: 1.15em; margin: 0 0 6px 0; }
.ktn-acting-character { color: #64748b; font-size: 1em; font-weight: 500; }
.ktn-acting-episodes { display: inline-block; padding: 4px 10px; background: #f1f5f9; border-radius: 6px; font-size: 0.85em; color: #475569; font-weight: 700; margin-top: 8px; }

/* News section styles */
.ktn-news-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px; }
.ktn-news-card { text-decoration: none; color: inherit; display: flex; flex-direction: column; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: all 0.3s; }
.ktn-news-card:hover { transform: translateY(-6px); border-color: #cbd5e1; box-shadow: 0 12px 25px rgba(0,0,0,0.06); }
.ktn-news-img-wrap { width: 100%; aspect-ratio: 16/9; background: #f8fafc; overflow: hidden; }
.ktn-news-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
.ktn-news-card:hover .ktn-news-img { transform: scale(1.05); }
.ktn-news-icon { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #cbd5e1; }
.ktn-news-content { padding: 20px; display: flex; flex-direction: column; flex: 1; }
.ktn-news-title { font-size: 1.1em; font-weight: 800; color: #0f172a; margin: 0 0 10px 0; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.ktn-news-meta { font-size: 0.85em; color: #64748b; font-weight: 600; margin-top: auto; }

/* RTL Support */
html[dir="rtl"] .ktn-actor-layout { direction: rtl; }
html[dir="rtl"] .ktn-acting-row { text-align: right; }
html[dir="rtl"] .ktn-acting-title { margin-right: 0; }
</style>

<div class="ktn-actor-container">
    <div class="ktn-actor-layout">
        
        <!-- Left Sidebar -->
        <div class="ktn-actor-sidebar">
            <div class="ktn-actor-sidebar-sticky">
                <img src="<?php echo esc_url($actor_img); ?>" alt="<?php echo esc_attr($actor_name); ?>" class="ktn-actor-photo">
                
                <?php if (!empty($socials)): ?>
                <div class="ktn-actor-socials">
                    <?php if (!empty($socials['facebook_id'])): ?>
                        <a href="https://facebook.com/<?php echo esc_attr($socials['facebook_id']); ?>" target="_blank" class="ktn-actor-social-link" title="Facebook">
                            <?php echo $fb_icon; ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socials['twitter_id'])): ?>
                        <a href="https://twitter.com/<?php echo esc_attr($socials['twitter_id']); ?>" target="_blank" class="ktn-actor-social-link" title="Twitter">
                            <?php echo $tw_icon; ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socials['instagram_id'])): ?>
                        <a href="https://instagram.com/<?php echo esc_attr($socials['instagram_id']); ?>" target="_blank" class="ktn-actor-social-link" title="Instagram">
                            <?php echo $ig_icon; ?>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="ktn-actor-info-box">
                    <h3><?php esc_html_e('Personal Info', 'kontentainment'); ?></h3>
                    
                    <div class="ktn-actor-info-item">
                        <span class="ktn-actor-info-label"><?php esc_html_e('Known For', 'kontentainment'); ?></span>
                        <span class="ktn-actor-info-value"><?php echo esc_html($known_for_department ? $known_for_department : '-'); ?></span>
                    </div>
                    
                    <div class="ktn-actor-info-item">
                        <span class="ktn-actor-info-label"><?php esc_html_e('Known Credits', 'kontentainment'); ?></span>
                        <span class="ktn-actor-info-value"><?php echo esc_html(ktn_translate_digits($known_credits_count)); ?></span>
                    </div>
                    
                    <div class="ktn-actor-info-item">
                        <span class="ktn-actor-info-label"><?php esc_html_e('Gender', 'kontentainment'); ?></span>
                        <span class="ktn-actor-info-value"><?php echo esc_html($gender_text); ?></span>
                    </div>
                    
                    <?php if ($birthday): ?>
                    <div class="ktn-actor-info-item">
                        <span class="ktn-actor-info-label"><?php esc_html_e('Birthday', 'kontentainment'); ?></span>
                        <span class="ktn-actor-info-value"><?php echo esc_html(ktn_translate_digits($birthday . $age_text)); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($place_of_birth): ?>
                    <div class="ktn-actor-info-item">
                        <span class="ktn-actor-info-label"><?php esc_html_e('Place of Birth', 'kontentainment'); ?></span>
                        <span class="ktn-actor-info-value"><?php echo esc_html($place_of_birth); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($also_known_as) && !$is_arabic_artist): ?>
                    <div class="ktn-actor-info-item">
                        <span class="ktn-actor-info-label"><?php esc_html_e('Also Known As', 'kontentainment'); ?></span>
                        <?php foreach ($also_known_as as $aka): ?>
                            <span class="ktn-actor-info-value" style="margin-bottom: 4px;"><?php echo esc_html($aka); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="ktn-actor-main">
            <h1 class="ktn-actor-name"><?php echo esc_html($actor_name); ?></h1>

            <?php if ($bio): ?>
            <div class="ktn-actor-bio">
                <h2 class="ktn-actor-section-title"><?php esc_html_e('Biography', 'kontentainment'); ?></h2>
                <?php echo wp_kses_post(nl2br($bio)); ?>
            </div>
            <?php endif; ?>

            <?php if ($local_media_query->have_posts()): ?>
            <div>
                <h2 class="ktn-actor-section-title"><?php esc_html_e('Known For', 'kontentainment'); ?></h2>
                <div class="ktn-known-for-grid">
                    <?php while ($local_media_query->have_posts()): $local_media_query->the_post();
                        $poster_path = get_post_meta(get_the_ID(), '_movie_poster_path', true);
                        $poster_url = $poster_path ? "https://image.tmdb.org/t/p/w500" . $poster_path : "https://via.placeholder.com/500x750?text=" . urlencode(__('No Poster', 'kontentainment'));
                    ?>
                    <a href="<?php the_permalink(); ?>" class="ktn-known-card">
                        <img src="<?php echo esc_url($poster_url); ?>" alt="<?php the_title_attribute(); ?>" class="ktn-known-poster">
                        <div class="ktn-known-title"><?php the_title(); ?></div>
                    </a>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($acting_credits)): ?>
            <div>
                <h2 class="ktn-actor-section-title"><?php esc_html_e('Acting', 'kontentainment'); ?></h2>
                <div class="ktn-acting-timeline">
                    <?php foreach ($acting_credits as $credit):
                        $release_date = $credit['release_date'] ?? $credit['first_air_date'] ?? '';
                        $year = $release_date ? substr($release_date, 0, 4) : '—';
                        $title = $credit['title'] ?? $credit['name'] ?? '';
                        $character = $credit['character'] ?? '';
                        $acting_credits_as_text = __('as', 'kontentainment');
                    ?>
                    <div class="ktn-acting-row">
                        <div class="ktn-acting-year"><?php echo esc_html(ktn_translate_digits($year)); ?></div>
                        <div class="ktn-acting-dot-col"><div class="ktn-acting-dot"></div></div>
                        <div class="ktn-acting-details">
                            <h4 class="ktn-acting-title"><?php echo esc_html($title); ?></h4>
                            <?php if ($character): ?>
                                <span class="ktn-acting-character">
                                    <?php echo esc_html($acting_credits_as_text); ?> <?php echo esc_html($character); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (isset($credit['episode_count']) && $credit['episode_count'] > 0): ?>
                                <br><span class="ktn-acting-episodes">
                                    <?php 
                                    $episode_str = sprintf(esc_html(_n('%d episode', '%d episodes', $credit['episode_count'], 'kontentainment')), $credit['episode_count']);
                                    echo esc_html(ktn_translate_digits($episode_str)); 
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php
            // Query related articles
            $related_posts_query = new WP_Query(array(
                'post_type' => 'post',
                'posts_per_page' => 12,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'ktn_cast',
                        'field' => 'term_id',
                        'terms' => $term->term_id
                    )
                )
            ));

            if ($related_posts_query->have_posts()):
            ?>
            <div>
                <h2 class="ktn-actor-section-title"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'أحدث الأخبار والمقالات' : 'Latest News & Articles'; ?></h2>
                <div class="ktn-news-grid">
                    <?php while ($related_posts_query->have_posts()): $related_posts_query->the_post(); ?>
                    <a href="<?php the_permalink(); ?>" class="ktn-news-card">
                        <div class="ktn-news-img-wrap">
                            <?php if (has_post_thumbnail()): ?>
                                <img src="<?php the_post_thumbnail_url('medium'); ?>" alt="<?php the_title_attribute(); ?>" class="ktn-news-img">
                            <?php else: ?>
                                <span class="dashicons dashicons-format-aside ktn-news-icon"></span>
                            <?php endif; ?>
                        </div>
                        <div class="ktn-news-content">
                            <h3 class="ktn-news-title"><?php the_title(); ?></h3>
                            <span class="ktn-news-meta"><?php echo get_the_date(); ?></span>
                        </div>
                    </a>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php get_footer(); ?>