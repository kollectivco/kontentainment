<?php
if (!defined('ABSPATH')) {
    exit;
}

$box_office = Ktn_Box_Office_Scraper::fetch_box_office_data();

$date = $box_office['date'] ?? date('j M Y');
$daily = $box_office['daily'] ?? array();
$weekly = $box_office['weekly'] ?? array();
$news = $box_office['news'] ?? array();
$all_time = $box_office['all_time'] ?? array();

// Enqueue stylesheet
wp_enqueue_style('ktn-box-office', KTN_PLUGIN_URL . 'assets/css/kontentainment-box-office.css', array(), KTN_PLUGIN_VERSION);
?>

<div class="ktn-bo-wrapper">

    <!-- Header -->
    <header class="ktn-bo-header-container">
        <h1 class="ktn-bo-title">
            <span class="gold-text"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'شباك التذاكر الاسبوعي واليومي' : __('Weekly & Daily Box Office', 'kontentainment'); ?></span>
            <span class="date-text"><?php echo esc_html(ktn_translate_digits($date)); ?></span>
        </h1>
        <div class="trend-legend">
            <span class="legend-title"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'المؤشر:' : __('Trend:', 'kontentainment'); ?></span>
            <span class="trend-up">▲ <?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'صعود' : __('Up', 'kontentainment'); ?></span>
            <span class="trend-down">▼ <?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'هبوط' : __('Down', 'kontentainment'); ?></span>
        </div>
    </header>

    <!-- Weekly Section -->
    <?php if (!empty($weekly)): ?>
    <section class="ktn-bo-section-header">
        <h2 class="ktn-section-title">
            <span class="gold-text"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'الإيرادات الأسبوعية' : __('Weekly Rankings', 'kontentainment'); ?></span>
        </h2>
    </section>
    
    <div class="ktn-bo-grid-weekly">
        <?php foreach ($weekly as $w): 
            $movie_link = ktn_get_movie_link_by_title($w['title']);
            $display_title = ktn_get_movie_display_title($w['title']);
        ?>
        <div class="ktn-bo-weekly-card">
            <div class="card-rank"><?php echo esc_html($w['rank']); ?></div>
            
            <a href="<?php echo esc_url($movie_link); ?>" class="weekly-card-click-wrap">
                <div class="card-media">
                    <?php if (!empty($w['poster'])): ?>
                        <img src="<?php echo esc_url($w['poster']); ?>" alt="<?php echo esc_attr($w['title']); ?>">
                    <?php else: ?>
                        <div class="no-poster-wrap">
                            <span class="dashicons dashicons-video-alt3" style="font-size:40px; width:40px; height:40px;"></span>
                        </div>
                    <?php endif; ?>
                </div>
                <h3 class="movie-title" title="<?php echo esc_attr($display_title); ?>"><?php echo esc_html($display_title); ?></h3>
            </a>

            <div class="card-info">
                <div class="card-metrics">
                    <div class="card-metric gross">
                        <span class="icon">💰</span>
                        <div class="meta">
                            <span class="val"><?php echo esc_html(ktn_translate_digits($w['weekly_gross'])); ?></span>
                            <span class="lbl"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'إيرادات الأسبوع' : __('Weekly Gross', 'kontentainment'); ?></span>
                        </div>
                    </div>
                    <div class="card-metric total">
                        <span class="icon">📈</span>
                        <div class="meta">
                            <span class="val"><?php echo esc_html(ktn_translate_digits($w['total_revenue'])); ?></span>
                            <span class="lbl"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'إجمالي الإيرادات' : __('Total Gross', 'kontentainment'); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($w['admissions'])): ?>
                    <div class="card-metric">
                        <span class="icon">🎟️</span>
                        <div class="meta">
                            <span class="val"><?php echo esc_html(ktn_translate_digits($w['admissions'])); ?></span>
                            <span class="lbl"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'عدد التذاكر' : __('Admissions', 'kontentainment'); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Daily Section -->
    <?php if (!empty($daily)): ?>
    <section class="ktn-bo-section-header">
        <h2 class="ktn-section-title">
            <span class="gold-text"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'الإيرادات اليومية للأفلام' : __('Daily Rankings', 'kontentainment'); ?></span>
        </h2>
    </section>

    <div class="ktn-bo-card-panel">
        <!-- Desktop Table View -->
        <div class="ktn-bo-table-wrap">
            <table class="ktn-bo-table">
                <thead>
                    <tr>
                        <th class="col-rank"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'الترتيب' : __('Rank', 'kontentainment'); ?></th>
                        <th class="col-poster"></th>
                        <th style="text-align:right;"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'الفيلم' : __('Movie', 'kontentainment'); ?></th>
                        <th><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'إيرادات اليوم' : __('Today Revenue', 'kontentainment'); ?></th>
                        <th><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'تذاكر اليوم' : __('Tickets Today', 'kontentainment'); ?></th>
                        <th><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'السينمات' : __('Cinemas', 'kontentainment'); ?></th>
                        <th class="col-trend"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'المؤشر' : __('Trend', 'kontentainment'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daily as $d): 
                        $movie_link = ktn_get_movie_link_by_title($d['title']);
                        $display_title = ktn_get_movie_display_title($d['title']);
                        $rank_cls = ($d['rank'] <= 3) ? 'rank-' . $d['rank'] : '';
                        $trend_cls = 'trend-' . ($d['trend_class'] ?? 'same');
                        $trend_icon = ($d['trend_class'] === 'up') ? '▲' : (($d['trend_class'] === 'down') ? '▼' : '—');
                    ?>
                    <tr>
                        <td class="col-rank <?php echo $rank_cls; ?>"><?php echo esc_html($d['rank']); ?></td>
                        <td class="col-poster">
                            <a href="<?php echo esc_url($movie_link); ?>" class="bo-movie-click-wrap">
                                <?php if (!empty($d['poster'])): ?>
                                    <img src="<?php echo esc_url($d['poster']); ?>" alt="<?php echo esc_attr($d['title']); ?>" class="bo-thumb">
                                <?php else: ?>
                                    <div class="bo-no-thumb">
                                        <span class="dashicons dashicons-video-alt3"></span>
                                    </div>
                                <?php endif; ?>
                            </a>
                        </td>
                        <td class="col-movie">
                            <a href="<?php echo esc_url($movie_link); ?>" class="movie-name-link">
                                <span class="movie-name"><?php echo esc_html($display_title); ?></span>
                            </a>
                        </td>
                        <td class="revenue-cell"><?php echo esc_html(ktn_translate_digits($d['revenue'])); ?></td>
                        <td class="font-gold"><?php echo esc_html(ktn_translate_digits($d['tickets'])); ?></td>
                        <td><?php echo esc_html(ktn_translate_digits($d['cinemas'])); ?></td>
                        <td class="col-trend <?php echo $trend_cls; ?>">
                            <span class="trend-percent">
                                <span class="trend-arrow"><?php echo $trend_icon; ?></span>
                                <?php echo esc_html(ktn_translate_digits($d['trend_text'])); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile List View -->
        <div class="ktn-bo-mobile-cards">
            <?php foreach ($daily as $d): 
                $movie_link = ktn_get_movie_link_by_title($d['title']);
                $display_title = ktn_get_movie_display_title($d['title']);
                $rank_cls = ($d['rank'] <= 3) ? 'rank-' . $d['rank'] : '';
                $trend_cls = 'trend-' . ($d['trend_class'] ?? 'same');
                $trend_icon = ($d['trend_class'] === 'up') ? '▲' : (($d['trend_class'] === 'down') ? '▼' : '—');
            ?>
            <div class="ktn-bo-mob-card">
                <div class="mob-card-header">
                    <span class="mob-rank"><?php echo esc_html($d['rank']); ?></span>
                    <a href="<?php echo esc_url($movie_link); ?>" class="mob-movie-click-wrap">
                        <?php if (!empty($d['poster'])): ?>
                            <img src="<?php echo esc_url($d['poster']); ?>" alt="<?php echo esc_attr($d['title']); ?>" class="mob-thumb">
                        <?php endif; ?>
                    </a>
                    <a href="<?php echo esc_url($movie_link); ?>" class="mob-movie-click-wrap">
                        <span class="mob-title"><?php echo esc_html($display_title); ?></span>
                    </a>
                </div>
                <div class="mob-metrics">
                    <div class="mob-metric">
                        <span class="lbl"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'إيرادات اليوم' : __('Today Revenue', 'kontentainment'); ?></span>
                        <span class="val revenue"><?php echo esc_html(ktn_translate_digits($d['revenue'])); ?></span>
                    </div>
                    <div class="mob-metric">
                        <span class="lbl"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'تذاكر اليوم' : __('Tickets Today', 'kontentainment'); ?></span>
                        <span class="val font-gold"><?php echo esc_html(ktn_translate_digits($d['tickets'])); ?></span>
                    </div>
                    <div class="mob-metric">
                        <span class="lbl"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'السينمات' : __('Cinemas', 'kontentainment'); ?></span>
                        <span class="val"><?php echo esc_html(ktn_translate_digits($d['cinemas'])); ?></span>
                    </div>
                    <div class="mob-metric">
                        <span class="lbl"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'المؤشر' : __('Trend', 'kontentainment'); ?></span>
                        <span class="val <?php echo $trend_cls; ?>">
                            <?php echo $trend_icon; ?> <?php echo esc_html(ktn_translate_digits($d['trend_text'])); ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($weekly) && empty($daily)): ?>
        <div class="ktn-bo-error">
            <span class="dashicons dashicons-warning" style="font-size: 48px; width: 48px; height: 48px; color: var(--ktn-bo-gold);"></span>
            <h3 style="margin-top:15px;"><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'لا توجد بيانات شباك تذاكر حالية.' : __('No Box Office data available at the moment.', 'kontentainment'); ?></h3>
            <p><?php echo (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0) ? 'يرجى مراجعة الإعدادات أو المحاولة مرة أخرى لاحقاً.' : __('Please check back later or refresh settings.', 'kontentainment'); ?></p>
        </div>
    <?php endif; ?>

</div>
