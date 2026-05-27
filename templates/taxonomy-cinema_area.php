<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();
wp_enqueue_style('ktn-archives-css', KTN_PLUGIN_URL . 'assets/css/kontentainment-archives.css', array(), KTN_PLUGIN_VERSION);

$current_term = get_queried_object();
$areas = get_terms([
    'taxonomy' => 'cinema_area',
    'hide_empty' => false,
]);
?>

<div class="ktn-archive-wrapper">
    <div class="ktn-archive-header">
        <h1 class="ktn-archive-title"><?php printf(esc_html__('%s Cinemas', 'kontentainment'), esc_html($current_term->name)); ?></h1>
        <?php if ($current_term->description): ?>
            <p class="ktn-archive-description"><?php echo esc_html($current_term->description); ?></p>
        <?php else: ?>
            <p class="ktn-archive-description"><?php printf(esc_html__('Cinemas located in %s.', 'kontentainment'), esc_html($current_term->name)); ?></p>
        <?php endif; ?>
        
        <?php if (!empty($areas) && !is_wp_error($areas)): ?>
        <div class="ktn-archive-filters" style="margin-top: 20px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo esc_url(get_post_type_archive_link('ktn_cinema')); ?>" class="ktn-premium-chip" style="text-decoration: none; padding: 6px 14px; background: #f0f0f0; color: #333; border-radius: 20px; font-weight: 500; font-size: 0.9rem; transition: background 0.2s;"><?php esc_html_e('All Areas', 'kontentainment'); ?></a>
            <?php foreach ($areas as $area): ?>
                <a href="<?php echo esc_url(get_term_link($area)); ?>" class="ktn-premium-chip <?php echo ($current_term->term_id === $area->term_id) ? 'active' : ''; ?>" style="text-decoration: none; padding: 6px 14px; border-radius: 20px; font-weight: 500; font-size: 0.9rem; transition: background 0.2s; <?php echo ($current_term->term_id === $area->term_id) ? 'background: #111; color: #fff;' : 'background: #f0f0f0; color: #333;'; ?>"><?php echo esc_html($area->name); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (have_posts()): ?>
        <div class="ktn-archive-grid">
            <?php while (have_posts()): the_post(); 
                $post_id = get_the_ID();
                $logo_url = has_post_thumbnail() ? get_the_post_thumbnail_url($post_id, 'medium') : get_post_meta($post_id, '_ktn_cinema_logo', true);
                if (!$logo_url) {
                    $logo_url = get_post_meta($post_id, 'logo', true);
                }
                
                $address = get_post_meta($post_id, '_ktn_cinema_address', true) ?: get_post_meta($post_id, 'address', true);
                
                // Get number of playing movies
                global $wpdb;
                $today = date('Y-m-d');
                $playing_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT movie_title_scraped) FROM {$wpdb->prefix}ktn_showtimes WHERE cinema_id = %d AND (show_date >= %s OR show_date = 'Today')",
                    $post_id,
                    $today
                ));
            ?>
            <div class="ktn-archive-card ktn-cinema-card">
                <div class="ktn-badge"><?php echo esc_html($current_term->name); ?></div>
                
                <div class="ktn-card-image-wrap">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php the_title_attribute(); ?> Logo">
                    <?php else: ?>
                        <div style="font-size: 2rem; color: #ccc;"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/></svg></div>
                    <?php endif; ?>
                </div>

                <div class="ktn-card-content">
                    <h2 class="ktn-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    
                    <div class="ktn-card-meta">
                        <?php if ($address): ?>
                            <span class="ktn-card-meta-item" title="<?php esc_attr_e('Address', 'kontentainment'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                <?php echo esc_html(wp_trim_words($address, 5, '...')); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($playing_count > 0): ?>
                            <span class="ktn-card-meta-item" title="<?php esc_attr_e('Playing Movies', 'kontentainment'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                <?php 
                                $playing_str = sprintf(esc_html(_n('%d Movie Playing', '%d Movies Playing', $playing_count, 'kontentainment')), $playing_count);
                                echo esc_html(ktn_translate_digits($playing_str));
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="ktn-card-action">
                        <a href="<?php the_permalink(); ?>"><?php esc_html_e('View Showtimes', 'kontentainment'); ?></a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <?php 
        the_posts_pagination(array(
            'prev_text' => esc_html__('&laquo; Previous', 'kontentainment'),
            'next_text' => esc_html__('Next &raquo;', 'kontentainment'),
        )); 
        ?>
    <?php else: ?>
        <p style="text-align: center; color: #777;"><?php esc_html_e('No cinemas found in this area.', 'kontentainment'); ?></p>
    <?php endif; ?>

</div>

<?php 
get_footer();
