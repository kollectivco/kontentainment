<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Metaboxes for Movies and TV Shows
 */
add_action('add_meta_boxes', 'ktn_add_movie_details_metaboxes');
function ktn_add_movie_details_metaboxes()
{
    // BASIC INFO
    add_meta_box(
        'ktn_movie_basic_info',
        __('Basic Movie Info', 'kontentainment'),
        'ktn_movie_basic_info_html',
        array('movie', 'tv_show'),
        'normal',
        'high'
    );

    // IDS & RATINGS
    add_meta_box(
        'ktn_movie_ratings',
        __('IDs / Ratings / Popularity', 'kontentainment'),
        'ktn_movie_ratings_html',
        array('movie', 'tv_show'),
        'side',
        'default'
    );

    // PEOPLE (DIrector, Writers)
    add_meta_box(
        'ktn_movie_people',
        __('People (Director, Writers)', 'kontentainment'),
        'ktn_movie_people_html',
        array('movie', 'tv_show'),
        'normal',
        'default'
    );

    // MEDIA
    add_meta_box(
        'ktn_movie_media',
        __('Media (Poster, Backdrop, Trailer)', 'kontentainment'),
        'ktn_movie_media_html',
        array('movie', 'tv_show'),
        'normal',
        'default'
    );

    // EXTRA DETAILS (Production, Languages, Keywords)
    add_meta_box(
        'ktn_movie_extra_details',
        __('Extra Details', 'kontentainment'),
        'ktn_movie_extra_details_html',
        array('movie', 'tv_show'),
        'normal',
        'low'
    );
}

/**
 * Display HTML for Basic Info Metabox
 */
function ktn_movie_basic_info_html($post)
{
    $movie_title    = $post->post_title;
    $original_title = get_post_meta($post->ID, '_movie_original_title', true);
    $tagline        = get_post_meta($post->ID, '_movie_tagline', true);
    $release_date   = get_post_meta($post->ID, '_movie_release_date', true);
    $runtime        = get_post_meta($post->ID, '_movie_runtime', true);
    $status         = get_post_meta($post->ID, '_movie_status', true);
    $lang           = get_post_meta($post->ID, '_movie_original_language', true);
    $cert           = get_post_meta($post->ID, '_movie_release_certification', true);

    wp_nonce_field('ktn_save_movie_meta', 'ktn_movie_meta_nonce');
    ?>
    <div class="ktn-admin-form-row">
        <label><strong><?php esc_html_e('Movie Title:', 'kontentainment'); ?></strong></label>
        <input type="text" name="ktn_movie_title" value="<?php echo esc_attr($movie_title); ?>" class="widefat" placeholder="Main display title" />
    </div>

    <div class="ktn-admin-form-row" style="margin-top: 15px;">
        <label><strong><?php esc_html_e('Original Title:', 'kontentainment'); ?></strong></label>
        <input type="text" name="ktn_original_title" value="<?php echo esc_attr($original_title); ?>" class="widefat" />
    </div>

    <div class="ktn-admin-form-row" style="margin-top: 15px;">
        <label><strong><?php esc_html_e('Tagline:', 'kontentainment'); ?></strong></label>
        <input type="text" name="ktn_tagline" value="<?php echo esc_attr($tagline); ?>" class="widefat" />
    </div>

    <div style="display: flex; gap: 20px; margin-top: 15px;">
        <div style="flex: 1;">
            <label><strong><?php esc_html_e('Release Date:', 'kontentainment'); ?></strong></label><br>
            <input type="date" name="ktn_release_date" value="<?php echo esc_attr($release_date); ?>" class="widefat" />
        </div>
        <div style="flex: 1;">
            <label><strong><?php esc_html_e('Runtime (min):', 'kontentainment'); ?></strong></label><br>
            <input type="number" name="ktn_runtime" value="<?php echo esc_attr($runtime); ?>" class="widefat" />
        </div>
    </div>

    <div style="display: flex; gap: 20px; margin-top: 15px;">
        <div style="flex: 1;">
            <label><strong><?php esc_html_e('Status:', 'kontentainment'); ?></strong></label><br>
            <input type="text" name="ktn_status" value="<?php echo esc_attr($status); ?>" class="widefat" />
        </div>
        <div style="flex: 1;">
            <label><strong><?php esc_html_e('Original Language:', 'kontentainment'); ?></strong></label><br>
            <input type="text" name="ktn_original_language" value="<?php echo esc_attr($lang); ?>" class="widefat" />
        </div>
        <div style="flex: 1;">
            <label><strong><?php esc_html_e('Certification:', 'kontentainment'); ?></strong></label><br>
            <input type="text" name="ktn_certification" value="<?php echo esc_attr($cert); ?>" class="widefat" />
        </div>
    </div>
    
    <p class="description"><?php esc_html_e('These fields are auto-filled during import but can be manually edited.', 'kontentainment'); ?></p>
    <?php
}

/**
 * Display HTML for IDs & Ratings Metabox
 */
function ktn_movie_ratings_html($post)
{
    $imdb_id    = get_post_meta($post->ID, '_movie_imdb_id', true);
    $tmdb_id    = get_post_meta($post->ID, '_movie_tmdb_id', true);
    $rating     = get_post_meta($post->ID, '_movie_vote_average', true);
    $vote_count = get_post_meta($post->ID, '_movie_vote_count', true);
    $popularity = get_post_meta($post->ID, '_movie_popularity', true);

    ?>
    <p>
        <label><strong><?php esc_html_e('IMDb ID:', 'kontentainment'); ?></strong></label><br>
        <input type="text" value="<?php echo esc_attr($imdb_id); ?>" class="widefat" readonly />
    </p>
    <p>
        <label><strong><?php esc_html_e('TMDB ID:', 'kontentainment'); ?></strong></label><br>
        <input type="text" value="<?php echo esc_attr($tmdb_id); ?>" class="widefat" readonly />
    </p>
    <hr>
    <p>
        <label><strong><?php esc_html_e('Vote Average:', 'kontentainment'); ?></strong></label><br>
        <input type="text" name="ktn_vote_average" value="<?php echo esc_attr($rating); ?>" class="widefat" />
    </p>
    <p>
        <label><strong><?php esc_html_e('Vote Count:', 'kontentainment'); ?></strong></label><br>
        <input type="number" name="ktn_vote_count" value="<?php echo esc_attr($vote_count); ?>" class="widefat" />
    </p>
    <p>
        <label><strong><?php esc_html_e('Popularity:', 'kontentainment'); ?></strong></label><br>
        <input type="text" name="ktn_popularity" value="<?php echo esc_attr($popularity); ?>" class="widefat" />
    </p>
    <?php
}

/**
 * Display HTML for People Metabox
 */
function ktn_movie_people_html($post)
{
    $director = get_post_meta($post->ID, '_movie_director', true);
    $writers  = get_post_meta($post->ID, '_movie_writers', true);
    if (is_array($writers)) {
        $writers = implode(', ', $writers);
    }
    
    // Cast is complex, usually stored as JSON
    $cast_json = get_post_meta($post->ID, '_movie_cast', true);
    $cast = json_decode($cast_json, true);

    ?>
    <div class="ktn-admin-form-row">
        <label><strong><?php esc_html_e('Director:', 'kontentainment'); ?></strong></label>
        <input type="text" name="ktn_director" value="<?php echo esc_attr($director); ?>" class="widefat" />
    </div>

    <div class="ktn-admin-form-row" style="margin-top: 15px;">
        <label><strong><?php esc_html_e('Writers (Comma Separated):', 'kontentainment'); ?></strong></label>
        <input type="text" name="ktn_writers" value="<?php echo esc_attr($writers); ?>" class="widefat" />
    </div>

    <div style="margin-top: 15px;">
        <label><strong><?php esc_html_e('Cast Members:', 'kontentainment'); ?></strong></label>
        <div style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; margin-top: 5px; border-radius: 4px;">
            <?php if (!empty($cast) && is_array($cast)): ?>
                <table style="width: 100%; text-align: left;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'kontentainment'); ?></th>
                            <th><?php esc_html_e('Character', 'kontentainment'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($cast as $actor): ?>
                            <tr>
                                <td><?php echo esc_html($actor['name']); ?></td>
                                <td><?php echo esc_html($actor['character']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php esc_html_e('No cast data found. Refresh from TMDB to update.', 'kontentainment'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Display HTML for Media Metabox
 */
function ktn_movie_media_html($post)
{
    $poster_path   = get_post_meta($post->ID, '_movie_poster_path', true);
    $backdrop_path = get_post_meta($post->ID, '_movie_backdrop_path', true);
    $trailer_url   = get_post_meta($post->ID, '_movie_trailer_url', true);
    $youtube_key   = get_post_meta($post->ID, '_movie_trailer_youtube_key', true);

    $poster_url = $poster_path ? "https://image.tmdb.org/t/p/w185" . $poster_path : '';
    $backdrop_url = $backdrop_path ? "https://image.tmdb.org/t/p/w500" . $backdrop_path : '';

    ?>
    <div style="display: flex; gap: 30px;">
        <div style="flex: 0 0 185px;">
            <label><strong><?php esc_html_e('Poster:', 'kontentainment'); ?></strong></label><br>
            <?php if ($poster_url): ?>
                <img src="<?php echo esc_url($poster_url); ?>" style="width: 100%; border-radius: 4px; border: 1px solid #ddd;" />
            <?php endif; ?>
            <input type="text" name="ktn_poster_path" value="<?php echo esc_attr($poster_path); ?>" class="widefat" style="margin-top: 5px;" />
        </div>
        <div style="flex: 1;">
            <label><strong><?php esc_html_e('Backdrop:', 'kontentainment'); ?></strong></label><br>
            <?php if ($backdrop_url): ?>
                <img src="<?php echo esc_url($backdrop_url); ?>" style="width: 100%; border-radius: 4px; border: 1px solid #ddd;" />
            <?php endif; ?>
            <input type="text" name="ktn_backdrop_path" value="<?php echo esc_attr($backdrop_path); ?>" class="widefat" style="margin-top: 5px;" />
            
            <div style="margin-top: 20px;">
                <label><strong><?php esc_html_e('Trailer Link / YouTube ID:', 'kontentainment'); ?></strong></label>
                <div style="display: flex; gap: 10px; margin-top: 5px;">
                    <div style="flex: 2;">
                        <input type="text" name="ktn_trailer_url" value="<?php echo esc_attr($trailer_url); ?>" class="widefat" placeholder="Full YouTube URL" />
                    </div>
                    <div style="flex: 1;">
                        <input type="text" name="ktn_trailer_youtube_key" value="<?php echo esc_attr($youtube_key); ?>" class="widefat" placeholder="YouTube Key" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Display HTML for Extra Details Metabox
 */
function ktn_movie_extra_details_html($post)
{
    $companies = get_post_meta($post->ID, '_movie_production_companies', true);
    $countries = get_post_meta($post->ID, '_movie_production_countries', true);
    $languages = get_post_meta($post->ID, '_movie_spoken_languages', true);
    $keywords  = get_post_meta($post->ID, '_movie_keywords', true);

    if (is_array($companies)) $companies = implode(', ', $companies);
    if (is_array($countries)) $countries = implode(', ', $countries);
    if (is_array($languages)) $languages = implode(', ', $languages);
    if (is_array($keywords)) $keywords = implode(', ', $keywords);

    ?>
    <div class="ktn-admin-form-row">
        <label><strong><?php esc_html_e('Production Companies:', 'kontentainment'); ?></strong></label>
        <input type="text" name="ktn_production_companies" value="<?php echo esc_attr($companies); ?>" class="widefat" />
    </div>

    <div class="ktn-admin-form-row" style="margin-top: 15px;">
        <label><strong><?php esc_html_e('Production Countries:', 'kontentainment'); ?></strong></label>
        <input type="text" name="ktn_production_countries" value="<?php echo esc_attr($countries); ?>" class="widefat" />
    </div>

    <div class="ktn-admin-form-row" style="margin-top: 15px;">
        <label><strong><?php esc_html_e('Spoken Languages:', 'kontentainment'); ?></strong></label>
        <input type="text" name="ktn_spoken_languages" value="<?php echo esc_attr($languages); ?>" class="widefat" />
    </div>

    <div class="ktn-admin-form-row" style="margin-top: 15px;">
        <label><strong><?php esc_html_e('Keywords:', 'kontentainment'); ?></strong></label>
        <input type="text" name="ktn_keywords" value="<?php echo esc_attr($keywords); ?>" class="widefat" />
    </div>
    <?php
}

/**
 * Save Movie Metadata
 */
add_action('save_post', 'ktn_save_movie_details');
function ktn_save_movie_details($post_id)
{
    if (!isset($_POST['ktn_movie_meta_nonce'])) {
        return;
    }
    if (!wp_verify_nonce($_POST['ktn_movie_meta_nonce'], 'ktn_save_movie_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (isset($_POST['post_type']) && !in_array($_POST['post_type'], array('movie', 'tv_show'))) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Sync Movie Title with check to avoid infinite loops
    if (isset($_POST['ktn_movie_title'])) {
        $new_title = sanitize_text_field($_POST['ktn_movie_title']);
        $current_post = get_post($post_id);
        if ($current_post && $current_post->post_title !== $new_title && !empty($new_title)) {
            // Unhook to avoid recursion during wp_update_post
            remove_action('save_post', 'ktn_save_movie_details');
            wp_update_post(array(
                'ID'         => $post_id,
                'post_title' => $new_title,
                'post_name'  => sanitize_title($new_title)
            ));
            add_action('save_post', 'ktn_save_movie_details');
        }
    }

    // Basic fields
    if (isset($_POST['ktn_original_title'])) {
        update_post_meta($post_id, '_movie_original_title', sanitize_text_field($_POST['ktn_original_title']));
    }
    if (isset($_POST['ktn_tagline'])) {
        update_post_meta($post_id, '_movie_tagline', sanitize_text_field($_POST['ktn_tagline']));
    }
    if (isset($_POST['ktn_release_date'])) {
        update_post_meta($post_id, '_movie_release_date', sanitize_text_field($_POST['ktn_release_date']));
    }
    if (isset($_POST['ktn_runtime'])) {
        update_post_meta($post_id, '_movie_runtime', absint($_POST['ktn_runtime']));
    }
    if (isset($_POST['ktn_status'])) {
        update_post_meta($post_id, '_movie_status', sanitize_text_field($_POST['ktn_status']));
    }
    if (isset($_POST['ktn_original_language'])) {
        update_post_meta($post_id, '_movie_original_language', sanitize_text_field($_POST['ktn_original_language']));
    }
    if (isset($_POST['ktn_certification'])) {
        update_post_meta($post_id, '_movie_release_certification', sanitize_text_field($_POST['ktn_certification']));
    }

    // Ratings
    if (isset($_POST['ktn_vote_average'])) {
        update_post_meta($post_id, '_movie_vote_average', floatval($_POST['ktn_vote_average']));
    }
    if (isset($_POST['ktn_vote_count'])) {
        update_post_meta($post_id, '_movie_vote_count', absint($_POST['ktn_vote_count']));
    }
    if (isset($_POST['ktn_popularity'])) {
        update_post_meta($post_id, '_movie_popularity', floatval($_POST['ktn_popularity']));
    }

    // People
    if (isset($_POST['ktn_director'])) {
        update_post_meta($post_id, '_movie_director', sanitize_text_field($_POST['ktn_director']));
    }
    if (isset($_POST['ktn_writers'])) {
        $writers = array_map('sanitize_text_field', explode(',', $_POST['ktn_writers']));
        update_post_meta($post_id, '_movie_writers', array_map('trim', $writers));
    }

    // Media
    if (isset($_POST['ktn_poster_path'])) {
        update_post_meta($post_id, '_movie_poster_path', sanitize_text_field($_POST['ktn_poster_path']));
    }
    if (isset($_POST['ktn_backdrop_path'])) {
        update_post_meta($post_id, '_movie_backdrop_path', sanitize_text_field($_POST['ktn_backdrop_path']));
    }
    if (isset($_POST['ktn_trailer_url'])) {
        update_post_meta($post_id, '_movie_trailer_url', esc_url_raw($_POST['ktn_trailer_url']));
    }
    if (isset($_POST['ktn_trailer_youtube_key'])) {
        update_post_meta($post_id, '_movie_trailer_youtube_key', sanitize_text_field($_POST['ktn_trailer_youtube_key']));
    }

    // Extras
    if (isset($_POST['ktn_production_companies'])) {
        $data = array_map('trim', explode(',', $_POST['ktn_production_companies']));
        update_post_meta($post_id, '_movie_production_companies', array_map('sanitize_text_field', $data));
    }
    if (isset($_POST['ktn_production_countries'])) {
        $data = array_map('trim', explode(',', $_POST['ktn_production_countries']));
        update_post_meta($post_id, '_movie_production_countries', array_map('sanitize_text_field', $data));
    }
    if (isset($_POST['ktn_spoken_languages'])) {
        $data = array_map('trim', explode(',', $_POST['ktn_spoken_languages']));
        update_post_meta($post_id, '_movie_spoken_languages', array_map('sanitize_text_field', $data));
    }
    if (isset($_POST['ktn_keywords'])) {
        $data = array_map('trim', explode(',', $_POST['ktn_keywords']));
        update_post_meta($post_id, '_movie_keywords', array_map('sanitize_text_field', $data));
    }
}
