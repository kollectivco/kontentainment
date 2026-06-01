<?php
if (!defined('ABSPATH')) {
    exit;
}

$box_office = Ktn_Box_Office_Scraper::fetch_box_office_data();

$date           = $box_office['date']         ?? date('j M Y');
$daily_arabic   = $box_office['daily_arabic'] ?? array();
$daily_foreign  = $box_office['daily_foreign'] ?? array();
$daily          = $box_office['daily']         ?? array();
$weekly         = $box_office['weekly']        ?? array();

// Fallback: if typed lists empty, split merged list by 'type' key
if (empty($daily_arabic) && empty($daily_foreign) && !empty($daily)) {
    foreach ($daily as $d) {
        if (($d['type'] ?? 'arabic') === 'foreign') {
            $daily_foreign[] = $d;
        } else {
            $daily_arabic[] = $d;
        }
    }
}

$is_ar = (get_locale() === 'ar' || strpos(get_locale(), 'ar') === 0);

// Enqueue stylesheet
wp_enqueue_style('ktn-box-office', KTN_PLUGIN_URL . 'assets/css/kontentainment-box-office.css', array(), KTN_PLUGIN_VERSION);
?>

<div class="ktn-bo-wrapper">

    <!-- Header -->
    <header class="ktn-bo-header-container">
        <h1 class="ktn-bo-title">
            <span class="gold-text"><?php echo $is_ar ? 'شباك التذاكر الاسبوعي واليومي' : __('Weekly &amp; Daily Box Office', 'kontentainment'); ?></span>
            <span class="date-text"><?php echo esc_html(ktn_translate_digits($date)); ?></span>
        </h1>
        <div class="trend-legend">
            <span class="legend-title"><?php echo $is_ar ? 'المؤشر:' : __('Trend:', 'kontentainment'); ?></span>
            <span class="trend-up">▲ <?php echo $is_ar ? 'صعود' : __('Up', 'kontentainment'); ?></span>
            <span class="trend-down">▼ <?php echo $is_ar ? 'هبوط' : __('Down', 'kontentainment'); ?></span>
        </div>
    </header>

    <!-- Period Tabs: Weekly / Daily -->
    <div class="ktn-bo-period-tabs" id="ktn-bo-period-tabs">
        <button class="ktn-bo-period-tab active" data-period="weekly">
            <?php echo $is_ar ? 'الأسبوعي' : __('Weekly', 'kontentainment'); ?>
        </button>
        <button class="ktn-bo-period-tab" data-period="daily">
            <?php echo $is_ar ? 'اليومي' : __('Daily', 'kontentainment'); ?>
        </button>
    </div>

    <!-- ===================== WEEKLY PANEL ===================== -->
    <div class="ktn-bo-panel" id="ktn-bo-panel-weekly">
        <?php if (!empty($weekly)): ?>
            <section class="ktn-bo-section-header">
                <h2 class="ktn-section-title">
                    <span class="gold-text"><?php echo $is_ar ? 'الإيرادات الأسبوعية' : __('Weekly Rankings', 'kontentainment'); ?></span>
                </h2>
            </section>

            <div class="ktn-bo-grid-weekly">
                <?php foreach ($weekly as $w):
                    $movie_link = ktn_get_movie_link_by_title($w['title']);
                    // Weekly list is mixed; use original title as-is (no forced translation)
                    $display_title = esc_html($w['title']);
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
                        <h3 class="movie-title" title="<?php echo esc_attr($w['title']); ?>"><?php echo $display_title; ?></h3>
                    </a>

                    <div class="card-info">
                        <div class="card-metrics">
                            <div class="card-metric gross">
                                <span class="icon">💰</span>
                                <div class="meta">
                                    <span class="val"><?php echo esc_html(ktn_translate_digits($w['weekly_gross'])); ?></span>
                                    <span class="lbl"><?php echo $is_ar ? 'إيرادات الأسبوع' : __('Weekly Gross', 'kontentainment'); ?></span>
                                </div>
                            </div>
                            <div class="card-metric total">
                                <span class="icon">📈</span>
                                <div class="meta">
                                    <span class="val"><?php echo esc_html(ktn_translate_digits($w['total_revenue'])); ?></span>
                                    <span class="lbl"><?php echo $is_ar ? 'إجمالي الإيرادات' : __('Total Gross', 'kontentainment'); ?></span>
                                </div>
                            </div>
                            <?php if (!empty($w['admissions'])): ?>
                            <div class="card-metric">
                                <span class="icon">🎟️</span>
                                <div class="meta">
                                    <span class="val"><?php echo esc_html(ktn_translate_digits($w['admissions'])); ?></span>
                                    <span class="lbl"><?php echo $is_ar ? 'عدد التذاكر' : __('Admissions', 'kontentainment'); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="ktn-bo-error">
                <h3><?php echo $is_ar ? 'لا توجد بيانات أسبوعية.' : __('No weekly data available.', 'kontentainment'); ?></h3>
            </div>
        <?php endif; ?>
    </div><!-- /weekly panel -->

    <!-- ===================== DAILY PANEL ===================== -->
    <div class="ktn-bo-panel" id="ktn-bo-panel-daily" style="display:none;">

        <!-- Daily Language Sub-Tabs -->
        <div class="ktn-bo-lang-tabs" id="ktn-bo-lang-tabs">
            <button class="ktn-bo-lang-tab active" data-lang="arabic">
                <?php echo $is_ar ? 'الأفلام العربية' : __('Arabic Films', 'kontentainment'); ?>
            </button>
            <button class="ktn-bo-lang-tab" data-lang="foreign">
                <?php echo $is_ar ? 'الأفلام الأجنبية' : __('Foreign Films', 'kontentainment'); ?>
            </button>
        </div>

        <!-- Daily Arabic Sub-Panel -->
        <div class="ktn-bo-lang-panel" id="ktn-bo-lang-arabic">
            <?php if (!empty($daily_arabic)): ?>
                <section class="ktn-bo-section-header">
                    <h2 class="ktn-section-title">
                        <span class="gold-text"><?php echo $is_ar ? 'الأفلام العربية — اليومي' : __('Arabic Films — Daily', 'kontentainment'); ?></span>
                    </h2>
                </section>
                <?php echo ktn_bo_render_daily_table($daily_arabic, $is_ar, 'arabic'); ?>
            <?php else: ?>
                <div class="ktn-bo-error"><h3><?php echo $is_ar ? 'لا توجد بيانات للأفلام العربية.' : __('No Arabic films data.', 'kontentainment'); ?></h3></div>
            <?php endif; ?>
        </div>

        <!-- Daily Foreign Sub-Panel -->
        <div class="ktn-bo-lang-panel" id="ktn-bo-lang-foreign" style="display:none;">
            <?php if (!empty($daily_foreign)): ?>
                <section class="ktn-bo-section-header">
                    <h2 class="ktn-section-title">
                        <span class="gold-text"><?php echo $is_ar ? 'الأفلام الأجنبية — اليومي' : __('Foreign Films — Daily', 'kontentainment'); ?></span>
                    </h2>
                </section>
                <?php echo ktn_bo_render_daily_table($daily_foreign, $is_ar, 'foreign'); ?>
            <?php else: ?>
                <div class="ktn-bo-error"><h3><?php echo $is_ar ? 'لا توجد بيانات للأفلام الأجنبية.' : __('No foreign films data.', 'kontentainment'); ?></h3></div>
            <?php endif; ?>
        </div>

    </div><!-- /daily panel -->

    <?php if (empty($weekly) && empty($daily_arabic) && empty($daily_foreign)): ?>
        <div class="ktn-bo-error">
            <span class="dashicons dashicons-warning" style="font-size: 48px; width: 48px; height: 48px; color: var(--ktn-bo-gold);"></span>
            <h3 style="margin-top:15px;"><?php echo $is_ar ? 'لا توجد بيانات شباك تذاكر حالية.' : __('No Box Office data available at the moment.', 'kontentainment'); ?></h3>
            <p><?php echo $is_ar ? 'يرجى مراجعة الإعدادات أو المحاولة مرة أخرى لاحقاً.' : __('Please check back later or refresh settings.', 'kontentainment'); ?></p>
        </div>
    <?php endif; ?>

</div><!-- /ktn-bo-wrapper -->

<script>
(function() {
    // ── Period Tabs: Weekly / Daily ──
    var periodTabs = document.querySelectorAll('.ktn-bo-period-tab');
    var weeklyPanel = document.getElementById('ktn-bo-panel-weekly');
    var dailyPanel  = document.getElementById('ktn-bo-panel-daily');

    periodTabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            periodTabs.forEach(function(t) { t.classList.remove('active'); });
            tab.classList.add('active');
            var period = tab.getAttribute('data-period');
            if (period === 'weekly') {
                weeklyPanel.style.display = '';
                dailyPanel.style.display  = 'none';
            } else {
                weeklyPanel.style.display = 'none';
                dailyPanel.style.display  = '';
            }
        });
    });

    // ── Language Sub-Tabs: Arabic / Foreign ──
    var langTabs = document.querySelectorAll('.ktn-bo-lang-tab');
    var langPanels = document.querySelectorAll('.ktn-bo-lang-panel');

    langTabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            langTabs.forEach(function(t) { t.classList.remove('active'); });
            tab.classList.add('active');
            var lang = tab.getAttribute('data-lang');
            langPanels.forEach(function(panel) {
                panel.style.display = (panel.id === 'ktn-bo-lang-' + lang) ? '' : 'none';
            });
        });
    });
})();
</script>

<?php
/**
 * Helper: render daily table (desktop + mobile) for a given movie list
 * For 'foreign' type: display title as-is (no Arabic translation)
 * For 'arabic' type: use ktn_get_movie_display_title()
 */
function ktn_bo_render_daily_table($movies, $is_ar, $type = 'arabic') {
    ob_start();
    ?>
    <div class="ktn-bo-card-panel">
        <!-- Desktop Table -->
        <div class="ktn-bo-table-wrap">
            <table class="ktn-bo-table">
                <thead>
                    <tr>
                        <th class="col-rank"><?php echo $is_ar ? 'الترتيب' : __('Rank', 'kontentainment'); ?></th>
                        <th class="col-poster"></th>
                        <th style="text-align:right;"><?php echo $is_ar ? 'الفيلم' : __('Movie', 'kontentainment'); ?></th>
                        <th><?php echo $is_ar ? 'إيرادات اليوم' : __('Today Revenue', 'kontentainment'); ?></th>
                        <th><?php echo $is_ar ? 'تذاكر اليوم' : __('Tickets Today', 'kontentainment'); ?></th>
                        <th><?php echo $is_ar ? 'السينمات' : __('Cinemas', 'kontentainment'); ?></th>
                        <th class="col-trend"><?php echo $is_ar ? 'المؤشر' : __('Trend', 'kontentainment'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movies as $d):
                        $movie_link    = ktn_get_movie_link_by_title($d['title']);
                        // Foreign: show original title; Arabic: translate/lookup
                        $display_title = ($type === 'foreign')
                            ? $d['title']
                            : ktn_get_movie_display_title($d['title']);
                        $rank_cls  = ($d['rank'] <= 3) ? 'rank-' . $d['rank'] : '';
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
                                    <div class="bo-no-thumb"><span class="dashicons dashicons-video-alt3"></span></div>
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

        <!-- Mobile Cards -->
        <div class="ktn-bo-mobile-cards">
            <?php foreach ($movies as $d):
                $movie_link    = ktn_get_movie_link_by_title($d['title']);
                $display_title = ($type === 'foreign')
                    ? $d['title']
                    : ktn_get_movie_display_title($d['title']);
                $trend_cls  = 'trend-' . ($d['trend_class'] ?? 'same');
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
                        <span class="lbl"><?php echo $is_ar ? 'إيرادات اليوم' : __('Today Revenue', 'kontentainment'); ?></span>
                        <span class="val revenue"><?php echo esc_html(ktn_translate_digits($d['revenue'])); ?></span>
                    </div>
                    <div class="mob-metric">
                        <span class="lbl"><?php echo $is_ar ? 'تذاكر اليوم' : __('Tickets Today', 'kontentainment'); ?></span>
                        <span class="val font-gold"><?php echo esc_html(ktn_translate_digits($d['tickets'])); ?></span>
                    </div>
                    <div class="mob-metric">
                        <span class="lbl"><?php echo $is_ar ? 'السينمات' : __('Cinemas', 'kontentainment'); ?></span>
                        <span class="val"><?php echo esc_html(ktn_translate_digits($d['cinemas'])); ?></span>
                    </div>
                    <div class="mob-metric">
                        <span class="lbl"><?php echo $is_ar ? 'المؤشر' : __('Trend', 'kontentainment'); ?></span>
                        <span class="val <?php echo $trend_cls; ?>"><?php echo $trend_icon; ?> <?php echo esc_html(ktn_translate_digits($d['trend_text'])); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

