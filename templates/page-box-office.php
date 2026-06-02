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
$weekly_arabic  = $box_office['weekly_arabic'] ?? array();
$weekly_foreign = $box_office['weekly_foreign'] ?? array();

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
        
        <!-- Weekly Language Sub-Tabs -->
        <div class="ktn-bo-lang-tabs" id="ktn-bo-lang-tabs-weekly">
            <button class="ktn-bo-lang-tab-weekly active" data-lang="arabic">
                <?php echo $is_ar ? 'الأفلام العربية' : __('Arabic Films', 'kontentainment'); ?>
            </button>
            <button class="ktn-bo-lang-tab-weekly" data-lang="foreign">
                <?php echo $is_ar ? 'الأفلام الأجنبية' : __('Foreign Films', 'kontentainment'); ?>
            </button>
        </div>

        <!-- Weekly Arabic Sub-Panel -->
        <div class="ktn-bo-lang-panel-weekly" id="ktn-bo-lang-weekly-arabic">
            <?php if (!empty($weekly_arabic)): ?>
                <section class="ktn-bo-section-header">
                    <h2 class="ktn-section-title">
                        <span class="gold-text"><?php echo $is_ar ? 'الإيرادات الأسبوعية — عربي' : __('Weekly Rankings — Arabic', 'kontentainment'); ?></span>
                    </h2>
                </section>
                <?php echo ktn_bo_render_weekly_grid($weekly_arabic, $is_ar, 'arabic'); ?>
            <?php elseif (!empty($weekly)): // fallback for old cached data ?>
                <section class="ktn-bo-section-header">
                    <h2 class="ktn-section-title">
                        <span class="gold-text"><?php echo $is_ar ? 'الإيرادات الأسبوعية' : __('Weekly Rankings', 'kontentainment'); ?></span>
                    </h2>
                </section>
                <?php echo ktn_bo_render_weekly_grid($weekly, $is_ar, 'arabic'); ?>
            <?php else: ?>
                <div class="ktn-bo-error">
                    <h3><?php echo $is_ar ? 'لا توجد بيانات أسبوعية عربية.' : __('No Arabic weekly data.', 'kontentainment'); ?></h3>
                </div>
            <?php endif; ?>
        </div>

        <!-- Weekly Foreign Sub-Panel -->
        <div class="ktn-bo-lang-panel-weekly" id="ktn-bo-lang-weekly-foreign" style="display:none;">
            <?php if (!empty($weekly_foreign)): ?>
                <section class="ktn-bo-section-header">
                    <h2 class="ktn-section-title">
                        <span class="gold-text"><?php echo $is_ar ? 'الإيرادات الأسبوعية — أجنبي' : __('Weekly Rankings — Foreign', 'kontentainment'); ?></span>
                    </h2>
                </section>
                <?php echo ktn_bo_render_weekly_grid($weekly_foreign, $is_ar, 'foreign'); ?>
            <?php else: ?>
                <div class="ktn-bo-error">
                    <h3><?php echo $is_ar ? 'لا توجد بيانات أسبوعية أجنبية.' : __('No foreign weekly data.', 'kontentainment'); ?></h3>
                </div>
            <?php endif; ?>
        </div>

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

    // ── Language Sub-Tabs: Arabic / Foreign (Daily) ──
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

    // ── Language Sub-Tabs: Arabic / Foreign (Weekly) ──
    var weeklyLangTabs = document.querySelectorAll('.ktn-bo-lang-tab-weekly');
    var weeklyLangPanels = document.querySelectorAll('.ktn-bo-lang-panel-weekly');

    weeklyLangTabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            weeklyLangTabs.forEach(function(t) { t.classList.remove('active'); });
            tab.classList.add('active');
            var lang = tab.getAttribute('data-lang');
            weeklyLangPanels.forEach(function(panel) {
                panel.style.display = (panel.id === 'ktn-bo-lang-weekly-' + lang) ? '' : 'none';
            });
        });
    });
})();
</script>

<?php
/**
 * Helper: render daily table for a given movie list (dark cinema-track.com style)
 * For 'foreign' type: display title as-is (no Arabic translation)
 * For 'arabic' type: use ktn_get_movie_display_title()
 */
if (!function_exists('ktn_bo_render_daily_table')) {
    function ktn_bo_render_daily_table($movies, $is_ar, $type = 'arabic') {
        if (empty($movies)) return '';
        ob_start();
        ?>
        <div class="ktn-bo-daily-wrap">

            <!-- Section Header -->
            <div class="ktn-bo-daily-header">
                <div class="ktn-bo-daily-trend-legend">
                    <span class="ktn-bo-daily-trend-legend-title"><?php echo $is_ar ? 'الترند' : 'Trend'; ?></span>
                    <span class="ktn-bo-daily-trend-note"><?php echo $is_ar ? '(مقارنة بنفس اليوم من الأسبوع الماضي)' : '(vs same day last week)'; ?></span>
                </div>
            </div>

            <!-- Desktop Table -->
            <div class="ktn-bo-daily-table-wrap">
                <table class="ktn-bo-daily-table">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th class="col-poster-h"></th>
                            <th class="col-title-h"><?php echo $is_ar ? 'فيلم' : 'Movie'; ?></th>
                            <th><?php echo $is_ar ? 'إيرادات اليوم' : 'Today Revenue'; ?></th>
                            <th><?php echo $is_ar ? 'عدد تذاكر اليوم' : 'Tickets Today'; ?></th>
                            <th><?php echo $is_ar ? 'عدد السينمات' : 'Cinemas'; ?></th>
                            <th class="col-trend-h"><?php echo $is_ar ? 'الترند' : 'Trend'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movies as $i => $d):
                            $movie_link    = ktn_get_movie_link_by_title($d['title']);
                            $display_title = ($type === 'foreign')
                                ? $d['title']
                                : ktn_get_movie_display_title($d['title']);
                            $rank       = $d['rank'] ?: ($i + 1);
                            $trend_cls  = $d['trend_class'] ?? 'same';
                            $trend_icon = ($trend_cls === 'up') ? '▲' : (($trend_cls === 'down') ? '▼' : '—');
                            $trend_val  = ktn_translate_digits($d['trend_text'] ?? '—');
                        ?>
                        <tr class="ktn-bo-daily-row">
                            <td class="col-num">
                                <span class="ktn-bo-rank-num"><?php echo esc_html($rank); ?></span>
                            </td>
                            <td class="col-poster-td">
                                <a href="<?php echo esc_url($movie_link); ?>">
                                    <?php if (!empty($d['poster'])): ?>
                                        <img src="<?php echo esc_url($d['poster']); ?>" alt="<?php echo esc_attr($display_title); ?>" class="ktn-bo-poster-thumb">
                                    <?php else: ?>
                                        <div class="ktn-bo-no-poster"></div>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td class="col-title-td">
                                <a href="<?php echo esc_url($movie_link); ?>" class="ktn-bo-movie-name"><?php echo esc_html($display_title); ?></a>
                            </td>
                            <td class="col-revenue"><?php echo esc_html(ktn_translate_digits($d['revenue'])); ?></td>
                            <td class="col-tickets"><?php echo esc_html(ktn_translate_digits($d['tickets'])); ?></td>
                            <td class="col-cinemas"><?php echo esc_html(ktn_translate_digits($d['cinemas'])); ?></td>
                            <td class="col-trend col-trend-<?php echo esc_attr($trend_cls); ?>">
                                <span class="ktn-trend-arrow"><?php echo $trend_icon; ?></span>
                                <?php echo esc_html($trend_val); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="ktn-bo-daily-mob-list">
                <?php foreach ($movies as $i => $d):
                    $movie_link    = ktn_get_movie_link_by_title($d['title']);
                    $display_title = ($type === 'foreign')
                        ? $d['title']
                        : ktn_get_movie_display_title($d['title']);
                    $rank       = $d['rank'] ?: ($i + 1);
                    $trend_cls  = $d['trend_class'] ?? 'same';
                    $trend_icon = ($trend_cls === 'up') ? '▲' : (($trend_cls === 'down') ? '▼' : '—');
                    $trend_val  = ktn_translate_digits($d['trend_text'] ?? '');
                ?>
                <div class="ktn-bo-daily-mob-row">
                    <span class="ktn-bo-mob-rank"><?php echo esc_html($rank); ?></span>
                    <a href="<?php echo esc_url($movie_link); ?>">
                        <?php if (!empty($d['poster'])): ?>
                            <img src="<?php echo esc_url($d['poster']); ?>" alt="<?php echo esc_attr($display_title); ?>" class="ktn-bo-mob-poster">
                        <?php endif; ?>
                    </a>
                    <div class="ktn-bo-mob-info">
                        <a href="<?php echo esc_url($movie_link); ?>" class="ktn-bo-mob-name"><?php echo esc_html($display_title); ?></a>
                        <div class="ktn-bo-mob-stats">
                            <span class="ktn-bo-mob-revenue"><?php echo esc_html(ktn_translate_digits($d['revenue'])); ?></span>
                            <span class="ktn-bo-mob-tickets"><?php echo esc_html(ktn_translate_digits($d['tickets'])); ?> <?php echo $is_ar ? 'تذكرة' : 'tickets'; ?></span>
                            <span class="ktn-bo-mob-cinemas ktn-orange"><?php echo esc_html(ktn_translate_digits($d['cinemas'])); ?> <?php echo $is_ar ? 'سينما' : 'cinemas'; ?></span>
                        </div>
                    </div>
                    <span class="ktn-bo-mob-trend ktn-trend-<?php echo esc_attr($trend_cls); ?>">
                        <?php echo $trend_icon; ?> <?php echo esc_html($trend_val); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * Helper: render weekly grid for a given movie list
 */
if (!function_exists('ktn_bo_render_weekly_grid')) {
    function ktn_bo_render_weekly_grid($movies, $is_ar, $type = 'arabic') {
        if (empty($movies)) return '';
        ob_start();
        ?>
        <div class="ktn-bo-grid-weekly">
            <?php foreach ($movies as $w):
                $movie_link = ktn_get_movie_link_by_title($w['title']);
                $display_title = ($type === 'foreign') 
                    ? $w['title'] 
                    : ktn_get_movie_display_title($w['title']);
            ?>
            <div class="ktn-bo-weekly-card">
                <div class="card-rank"><?php echo esc_html($w['rank']); ?></div>
                <div class="ktn-bo-weekly-inner">
                    <div class="weekly-info">
                        <a href="<?php echo esc_url($movie_link); ?>" class="weekly-card-click-wrap">
                            <h3 class="movie-title" title="<?php echo esc_attr($w['title']); ?>"><?php echo esc_html($display_title); ?></h3>
                        </a>
                        <div class="card-metrics">
                            <div class="card-metric gross">
                                <div class="meta">
                                    <span class="val"><?php echo esc_html(ktn_translate_digits($w['weekly_gross'])); ?> EGP</span>
                                    <span class="lbl"><?php echo $is_ar ? 'الإيرادات الأسبوعية' : __('Weekly Gross', 'kontentainment'); ?></span>
                                </div>
                                <span class="icon">💰</span>
                            </div>
                            <div class="card-metric total">
                                <div class="meta">
                                    <span class="val"><?php echo esc_html(ktn_translate_digits($w['total_revenue'])); ?> EGP</span>
                                    <span class="lbl"><?php echo $is_ar ? 'إجمالي الإيرادات' : __('Total Gross', 'kontentainment'); ?></span>
                                </div>
                                <span class="icon">🎬</span>
                            </div>
                            <?php if (!empty($w['admissions'])): ?>
                            <div class="card-metric tickets">
                                <div class="meta">
                                    <span class="val"><?php echo esc_html(ktn_translate_digits($w['admissions'])); ?></span>
                                    <span class="lbl"><?php echo $is_ar ? 'عدد التذاكر الأسبوعية' : __('Weekly Admissions', 'kontentainment'); ?></span>
                                </div>
                                <span class="icon">🎟️</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="weekly-media">
                        <a href="<?php echo esc_url($movie_link); ?>" class="weekly-card-click-wrap">
                            <?php if (!empty($w['poster'])): ?>
                                <img src="<?php echo esc_url($w['poster']); ?>" alt="<?php echo esc_attr($w['title']); ?>">
                            <?php else: ?>
                                <div class="no-poster-wrap">
                                    <span class="dashicons dashicons-video-alt3"></span>
                                </div>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
