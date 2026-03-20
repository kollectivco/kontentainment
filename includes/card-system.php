<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unified Card Rendering System for Kontentainment
 */
class Ktn_Card_System
{

    /**
     * Render a Movie Card
     * 
     * @param int|WP_Post $post Post ID or object
     * @param array $settings Display settings/toggleables
     */
    public static function render_movie_card($post, $settings = array())
    {
        $post = get_post($post);
        if (!$post || $post->post_type !== 'movie') return '';

        $post_id = $post->ID;
        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);
        
        // Poster URL logic
        $poster_url = has_post_thumbnail($post_id) ? get_the_post_thumbnail_url($post_id, 'medium_large') : '';
        if (!$poster_url) {
            $poster_path = get_post_meta($post_id, '_movie_poster_path', true);
            if ($poster_path) {
                $poster_url = "https://image.tmdb.org/t/p/w500" . $poster_path;
            } else {
                $poster_url = KTN_PLUGIN_URL . 'assets/img/no-poster.jpg';
            }
        }

        $rating = get_post_meta($post_id, '_movie_vote_average', true);
        $release_date = get_post_meta($post_id, '_movie_release_date', true);
        $year = $release_date ? date('Y', strtotime($release_date)) : '';
        $original_title = get_post_meta($post_id, '_movie_original_title', true);
        $excerpt = $post->post_excerpt;
        
        $genres = array();
        $terms = get_the_terms($post_id, 'ktn_genre');
        if ($terms && !is_wp_error($terms)) {
            $genres = wp_list_pluck($terms, 'name');
        }
        $genre_label = !empty($genres) ? $genres[0] : '';

        // Merge default settings
        $defaults = array(
            'show_rating'    => true,
            'show_year'      => true,
            'show_genre'     => true,
            'show_original'  => false,
            'show_excerpt'   => false,
            'show_cta'       => false,
            'aspect_ratio'   => '2/3',
        );
        $settings = wp_parse_args($settings, $defaults);

        ob_start();
        ?>
        <div class="ktn-premium-movie-card" data-movie-id="<?php echo esc_attr($post_id); ?>">
            <a href="<?php echo esc_url($permalink); ?>" class="ktn-card-link-wrapper">
                <div class="ktn-card-media" style="aspect-ratio: <?php echo esc_attr($settings['aspect_ratio']); ?>;">
                    <img src="<?php echo esc_url($poster_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                    
                    <?php if ($settings['show_rating'] && $rating): ?>
                        <div class="ktn-card-badge-rating">
                            <span class="dashicons dashicons-star-filled"></span>
                            <span><?php echo number_format($rating, 1); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="ktn-card-overlay">
                        <div class="ktn-card-content">
                            <?php if ($settings['show_genre'] && $genre_label): ?>
                                <span class="ktn-card-genre"><?php echo esc_html($genre_label); ?></span>
                            <?php endif; ?>
                            
                            <h3 class="ktn-card-title"><?php echo esc_html($title); ?></h3>

                            <?php if ($settings['show_original'] && $original_title && $original_title !== $title): ?>
                                <p class="ktn-card-subtitle"><?php echo esc_html($original_title); ?></p>
                            <?php endif; ?>
                            
                            <div class="ktn-card-meta">
                                <?php if ($settings['show_year'] && $year): ?>
                                    <span class="ktn-meta-year"><?php echo esc_html($year); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($settings['show_excerpt'] && $excerpt): ?>
                                <p class="ktn-card-excerpt"><?php echo wp_trim_words(esc_html($excerpt), 12); ?></p>
                            <?php endif; ?>

                            <?php if ($settings['show_cta']): ?>
                                <span class="ktn-card-cta-btn"><?php _e('View Details', 'kontentainment'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a Cinema Card
     * 
     * @param int|WP_Post $post Post ID or object
     * @param array $settings Display settings/toggleables
     */
    public static function render_cinema_card($post, $settings = array())
    {
        $post = get_post($post);
        if (!$post || $post->post_type !== 'ktn_cinema') return '';

        $post_id = $post->ID;
        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);

        $cover_url = get_post_meta($post_id, '_ktn_cinema_cover_image', true);
        if (!$cover_url) {
            $cover_url = has_post_thumbnail($post_id) ? get_the_post_thumbnail_url($post_id, 'large') : '';
        }
        if (!$cover_url) {
            $cover_url = get_post_meta($post_id, '_ktn_cinema_logo', true);
        }
        $media_url = $cover_url ? $cover_url : KTN_PLUGIN_URL . 'assets/img/no-logo.png';
        
        $city = get_post_meta($post_id, '_ktn_cinema_city', true);
        $area = get_post_meta($post_id, '_ktn_cinema_area', true);
        $rating = get_post_meta($post_id, '_ktn_cinema_rating', true);
        $address = get_post_meta($post_id, '_ktn_cinema_address', true);

        // Merge default settings
        $defaults = array(
            'show_rating'   => true,
            'show_location' => true,
            'show_address'  => false,
            'show_count'    => false,
            'movie_count'   => 0,
            'show_cta'      => true,
        );
        $settings = wp_parse_args($settings, $defaults);

        ob_start();
        ?>
        <div class="ktn-premium-cinema-card" data-cinema-id="<?php echo esc_attr($post_id); ?>">
            <div class="ktn-card-inner">
                <div class="ktn-card-media-box">
                    <a href="<?php echo esc_url($permalink); ?>">
                        <img src="<?php echo esc_url($media_url); ?>" alt="<?php echo esc_attr($title); ?>" class="ktn-cinema-logo" loading="lazy">
                    </a>
                </div>
                
                <div class="ktn-card-body">
                    <div class="ktn-card-header-row">
                        <h3 class="ktn-card-title">
                            <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
                        </h3>
                        <?php if ($settings['show_rating'] && $rating): ?>
                            <div class="ktn-card-stars">
                                <span class="dashicons dashicons-star-filled"></span>
                                <span><?php echo esc_html($rating); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($settings['show_location'] && ($area || $city)): ?>
                        <div class="ktn-card-location">
                            <span class="dashicons dashicons-location"></span>
                            <span><?php echo esc_html(trim($area . ', ' . $city, ', ')); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($settings['show_address'] && $address): ?>
                        <p class="ktn-card-address-snippet"><?php echo wp_trim_words(esc_html($address), 8); ?></p>
                    <?php endif; ?>

                    <?php if ($settings['show_count'] && $settings['movie_count'] > 0): ?>
                        <div class="ktn-card-movie-count">
                            <span class="dashicons dashicons-video-alt3"></span>
                            <span><?php printf(_n('%d Movie Playing', '%d Movies Playing', $settings['movie_count'], 'kontentainment'), $settings['movie_count']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($settings['show_cta']): ?>
                        <div class="ktn-card-footer">
                            <a href="<?php echo esc_url($permalink); ?>" class="ktn-card-cta">
                                <?php _e('View Cinema', 'kontentainment'); ?>
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
