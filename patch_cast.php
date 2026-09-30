<?php
$content = file_get_contents('/Users/appleworld/.gemini/antigravity/scratch/kontentainment/templates/taxonomy-ktn_cast.php');

$split_str = '<div class="ktn-cast-container"';
$parts = explode($split_str, $content);
$php_top = $parts[0];

$new_html = <<<'HTML'
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
HTML;

file_put_contents('/Users/appleworld/.gemini/antigravity/scratch/kontentainment/templates/taxonomy-ktn_cast.php', $php_top . $new_html);
