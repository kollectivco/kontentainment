<?php
if (!defined('ABSPATH')) {
    exit;
}

global $ktn_is_box_office_shortcode;
$is_shortcode = !empty($ktn_is_box_office_shortcode);

if (!$is_shortcode) {
    get_header();
}

// Enqueue styles
wp_enqueue_style('ktn-box-office-css', KTN_PLUGIN_URL . 'assets/css/kontentainment-box-office.css', array(), KTN_PLUGIN_VERSION);

// Enqueue Chart.js from CDN
wp_enqueue_script('ktn-chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.2', true);

// Fetch Scraped Box Office Data
$data = Ktn_Box_Office_Scraper::fetch_box_office_data();

// Format numbers in Arabic if needed
function ktn_bo_format_egp($str) {
    if (empty($str)) return '—';
    // Translate "EGP" to "ج.م"
    $formatted = str_ireplace('EGP', 'ج.م', $str);
    return ktn_translate_digits($formatted);
}

function ktn_bo_translate($str) {
    if (empty($str)) return '';
    $months = array(
        'January' => 'يناير', 'February' => 'فبراير', 'March' => 'مارس', 'April' => 'أبريل',
        'May' => 'مايو', 'June' => 'يونيو', 'July' => 'يوليو', 'August' => 'أغسطس',
        'September' => 'سبتمبر', 'October' => 'أكتوبر', 'November' => 'نوفمبر', 'December' => 'ديسمبر',
        'Jan' => 'يناير', 'Feb' => 'فبراير', 'Mar' => 'مارس', 'Apr' => 'أبريل',
        'Jun' => 'يونيو', 'Jul' => 'يوليو', 'Aug' => 'أغسطس',
        'Sep' => 'سبتمبر', 'Oct' => 'أكتوبر', 'Nov' => 'نوفمبر', 'Dec' => 'ديسمبر'
    );
    foreach ($months as $en => $ar) {
        $str = str_ireplace($en, $ar, $str);
    }
    return ktn_translate_digits($str);
}
?>

<div class="ktn-bo-wrapper">
    <?php if (is_wp_error($data)): ?>
        <div class="ktn-bo-error">
            <span class="dashicons dashicons-warning" style="font-size: 40px; width: 40px; height: 40px; color: #ef4444; margin-bottom: 12px;"></span>
            <p><?php echo esc_html__('عذراً، حدث خطأ أثناء جلب بيانات شباك التذاكر. يرجى المحاولة مرة أخرى لاحقاً.', 'kontentainment'); ?></p>
            <p class="error-detail" style="font-size: 11px; opacity: 0.6; margin-top: 5px;"><?php echo esc_html($data->get_error_message()); ?></p>
        </div>
    <?php else: 
        $date_display = $data['date'] ?? date('j M Y');
        $daily = $data['daily'] ?? array();
        $weekly = $data['weekly'] ?? array();
        $charts = $data['charts'] ?? array();
    ?>
        
        <!-- Header -->
        <div class="ktn-bo-header-container">
            <div class="ktn-bo-header-left">
                <h1 class="ktn-bo-title">
                    <span class="gold-text"><?php esc_html_e('شباك التذاكر – ', 'kontentainment'); ?></span>
                    <span class="date-text"><?php echo esc_html(ktn_bo_translate($date_display)); ?></span>
                </h1>
            </div>
            <div class="ktn-bo-header-right">
                <div class="trend-legend">
                    <span class="legend-title"><?php esc_html_e('مقارنة بالأسبوع الماضي:', 'kontentainment'); ?></span>
                    <span class="trend-up">▲ <?php esc_html_e('صعود', 'kontentainment'); ?></span>
                    <span class="trend-down">▼ <?php esc_html_e('هبوط', 'kontentainment'); ?></span>
                </div>
            </div>
        </div>

        <!-- Section 1: Daily Table -->
        <div class="ktn-bo-card-panel">
            <div class="ktn-bo-table-wrap">
                <!-- Desktop Table -->
                <table class="ktn-bo-table">
                    <thead>
                        <tr>
                            <th class="col-rank">#</th>
                            <th class="col-poster"></th>
                            <th class="col-movie"><?php esc_html_e('الفيلم', 'kontentainment'); ?></th>
                            <th><?php esc_html_e('إيرادات اليوم', 'kontentainment'); ?></th>
                            <th><?php esc_html_e('تذاكر اليوم', 'kontentainment'); ?></th>
                            <th class="col-cinemas"><?php esc_html_e('السينمات', 'kontentainment'); ?></th>
                            <th class="col-trend"><?php esc_html_e('المؤشر', 'kontentainment'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daily)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #9ca3af;">
                                    <?php esc_html_e('لا توجد بيانات متاحة لليوم.', 'kontentainment'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daily as $item): 
                                $tr_class = 'trend-' . ($item['trend_class'] ?? 'same');
                                $trend_arrow = '—';
                                if ($item['trend_class'] === 'up') $trend_arrow = '▲';
                                if ($item['trend_class'] === 'down') $trend_arrow = '▼';
                                
                                // Fetch local movie link & Arabic display title
                                $movie_link = ktn_get_movie_link_by_title($item['title']);
                                $display_title = ktn_get_movie_display_title($item['title']);
                            ?>
                                <tr>
                                    <td class="col-rank rank-<?php echo esc_attr($item['rank']); ?>"><?php echo esc_html(ktn_bo_translate($item['rank'])); ?></td>
                                    <td class="col-poster">
                                        <?php if ($movie_link !== '#'): ?>
                                            <a href="<?php echo esc_url($movie_link); ?>" class="bo-movie-click-wrap">
                                        <?php endif; ?>
                                        <?php if (!empty($item['poster'])): ?>
                                            <img src="<?php echo esc_url($item['poster']); ?>" alt="<?php echo esc_attr($display_title); ?> Poster" class="bo-thumb">
                                        <?php else: ?>
                                            <div class="bo-no-thumb"><span class="dashicons dashicons-format-image"></span></div>
                                        <?php endif; ?>
                                        <?php if ($movie_link !== '#'): ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-movie">
                                        <?php if ($movie_link !== '#'): ?>
                                            <a href="<?php echo esc_url($movie_link); ?>" class="movie-name-link">
                                        <?php endif; ?>
                                        <div class="movie-name"><?php echo esc_html($display_title); ?></div>
                                        <?php if ($movie_link !== '#'): ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="revenue-cell"><?php echo esc_html(ktn_bo_format_egp($item['revenue'])); ?></td>
                                    <td><?php echo esc_html(ktn_bo_translate($item['tickets'])); ?></td>
                                    <td class="col-cinemas font-gold"><?php echo esc_html(ktn_bo_translate($item['cinemas'])); ?></td>
                                    <td class="col-trend <?php echo esc_attr($tr_class); ?>">
                                        <span class="trend-arrow"><?php echo esc_html($trend_arrow); ?></span>
                                        <span class="trend-percent"><?php echo esc_html(ktn_bo_translate(str_replace(['▲', '▼', '—'], '', $item['trend_text']))); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Mobile Responsive Cards -->
                <div class="ktn-bo-mobile-cards">
                    <?php foreach ($daily as $item): 
                        $tr_class = 'trend-' . ($item['trend_class'] ?? 'same');
                        $trend_arrow = '—';
                        if ($item['trend_class'] === 'up') $trend_arrow = '▲';
                        if ($item['trend_class'] === 'down') $trend_arrow = '▼';
                        
                        $movie_link = ktn_get_movie_link_by_title($item['title']);
                        $display_title = ktn_get_movie_display_title($item['title']);
                    ?>
                        <div class="ktn-bo-mob-card">
                            <div class="mob-card-header">
                                <div class="mob-rank rank-<?php echo esc_attr($item['rank']); ?>"><?php echo esc_html(ktn_bo_translate($item['rank'])); ?></div>
                                <?php if ($movie_link !== '#'): ?>
                                    <a href="<?php echo esc_url($movie_link); ?>" class="mob-movie-click-wrap" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
                                <?php endif; ?>
                                <?php if (!empty($item['poster'])): ?>
                                    <img src="<?php echo esc_url($item['poster']); ?>" alt="<?php echo esc_attr($display_title); ?>" class="mob-thumb">
                                <?php endif; ?>
                                <div class="mob-title"><?php echo esc_html($display_title); ?></div>
                                <?php if ($movie_link !== '#'): ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="mob-metrics">
                                <div class="mob-metric">
                                    <span class="lbl"><?php esc_html_e('إيرادات اليوم', 'kontentainment'); ?></span>
                                    <span class="val revenue"><?php echo esc_html(ktn_bo_format_egp($item['revenue'])); ?></span>
                                </div>
                                <div class="mob-metric">
                                    <span class="lbl"><?php esc_html_e('تذاكر اليوم', 'kontentainment'); ?></span>
                                    <span class="val"><?php echo esc_html(ktn_bo_translate($item['tickets'])); ?></span>
                                </div>
                                <div class="mob-metric">
                                    <span class="lbl"><?php esc_html_e('السينمات', 'kontentainment'); ?></span>
                                    <span class="val font-gold"><?php echo esc_html(ktn_bo_translate($item['cinemas'])); ?></span>
                                </div>
                                <div class="mob-metric">
                                    <span class="lbl"><?php esc_html_e('المؤشر', 'kontentainment'); ?></span>
                                    <span class="val <?php echo esc_attr($tr_class); ?>">
                                        <?php echo esc_html($trend_arrow); ?> <?php echo esc_html(ktn_bo_translate(str_replace(['▲', '▼', '—'], '', $item['trend_text']))); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Section 2: Top Highest Grossing Cards This Week -->
        <div class="ktn-bo-section-header">
            <h2 class="ktn-section-title">
                <span class="gold-text"><?php esc_html_e('الأفلام الأعلى إيراداً', 'kontentainment'); ?></span>
                <span><?php esc_html_e(' هذا الأسبوع', 'kontentainment'); ?></span>
            </h2>
        </div>

        <div class="ktn-bo-grid-weekly">
            <?php if (empty($weekly)): ?>
                <p style="grid-column: 1/-1; text-align: center; color: #9ca3af;"><?php esc_html_e('لا توجد بيانات متاحة لهدا الأسبوع.', 'kontentainment'); ?></p>
            <?php else: ?>
                <?php foreach ($weekly as $item): 
                    $movie_link = ktn_get_movie_link_by_title($item['title']);
                    $display_title = ktn_get_movie_display_title($item['title']);
                ?>
                    <div class="ktn-bo-weekly-card">
                        <div class="card-rank"><?php echo esc_html(ktn_bo_translate($item['rank'])); ?></div>
                        
                        <?php if ($movie_link !== '#'): ?>
                            <a href="<?php echo esc_url($movie_link); ?>" class="weekly-card-click-wrap" style="text-decoration: none; color: inherit; display: block;">
                        <?php endif; ?>
                        
                        <div class="card-media">
                            <?php if (!empty($item['poster'])): ?>
                                <img src="<?php echo esc_url($item['poster']); ?>" alt="<?php echo esc_attr($display_title); ?> Poster">
                            <?php else: ?>
                                <div class="no-poster-wrap"><span class="dashicons dashicons-video-alt3" style="font-size: 60px; width: 60px; height: 60px;"></span></div>
                            <?php endif; ?>
                        </div>
                        <div class="card-info">
                            <h3 class="movie-title"><?php echo esc_html($display_title); ?></h3>
                            
                        <?php if ($movie_link !== '#'): ?>
                            </a>
                        <?php endif; ?>
                            
                            <div class="card-metrics">
                                <div class="card-metric gross">
                                    <span class="icon">💰</span>
                                    <div class="meta">
                                        <span class="val"><?php echo esc_html(ktn_bo_format_egp($item['weekly_gross'])); ?></span>
                                        <span class="lbl"><?php esc_html_e('إيرادات الأسبوع', 'kontentainment'); ?></span>
                                    </div>
                                </div>
                                <div class="card-metric total">
                                    <span class="icon">🎬</span>
                                    <div class="meta">
                                        <span class="val"><?php echo esc_html(ktn_bo_format_egp($item['total_revenue'])); ?></span>
                                        <span class="lbl"><?php esc_html_e('إجمالي الإيرادات', 'kontentainment'); ?></span>
                                    </div>
                                </div>
                                <div class="card-metric adm">
                                    <span class="icon">🎟️</span>
                                    <div class="meta">
                                        <span class="val"><?php echo esc_html(ktn_bo_translate($item['admissions'])); ?></span>
                                        <span class="lbl"><?php esc_html_e('حضور الأسبوع', 'kontentainment'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Section 3: Market Insights ChartJS -->
        <div class="ktn-bo-section-header">
            <h2 class="ktn-section-title">
                <span class="gold-text"><?php esc_html_e('تحليلات وإحصائيات', 'kontentainment'); ?></span>
                <span><?php esc_html_e(' السوق', 'kontentainment'); ?></span>
            </h2>
        </div>

        <div class="ktn-bo-charts-grid">
            <div class="ktn-bo-chart-card">
                <h3 class="chart-title"><?php esc_html_e('مقارنة إيرادات الأسبوع (ج.م)', 'kontentainment'); ?></h3>
                <div class="chart-container">
                    <canvas id="ktn_bo_bar_chart"></canvas>
                </div>
            </div>
            <div class="ktn-bo-chart-card">
                <h3 class="chart-title"><?php esc_html_e('حجم إيرادات السوق الأسبوعية', 'kontentainment'); ?></h3>
                <div class="chart-container">
                    <canvas id="ktn_bo_line_chart"></canvas>
                </div>
            </div>
        </div>

        <!-- Script logic for charts -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. ChartJS Initialization in Light Mode
            if (typeof Chart !== 'undefined') {
                const isMobile = window.innerWidth <= 768;
                const gridColor = 'rgba(0, 0, 0, 0.06)';
                const textColor = '#475569';
                const tickFont = { family: 'Cairo, sans-serif', size: isMobile ? 10 : 12 };

                // Handle Y axis abbreviation (24,500,000 -> 24.5M)
                function formatY(value) {
                    if (value >= 1e6) return (value / 1e6).toFixed(1) + 'M';
                    if (value >= 1e3) return (value / 1e3).toFixed(0) + 'K';
                    return value;
                }

                // JS helper to translate standard digits to Arabic Eastern numerals
                function ktnBoTranslateDigits(str) {
                    if (str === undefined || str === null) return '';
                    str = str.toString();
                    const en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                    const ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                    for (let i = 0; i < 10; i++) {
                        str = str.replaceAll(en[i], ar[i]);
                    }
                    return str;
                }

                // JS helper to translate months and digits in labels
                function ktnBoTranslateFullText(str) {
                    if (!str) return '';
                    str = str.toString();
                    const months = {
                        'January': 'يناير', 'February': 'فبراير', 'March': 'مارس', 'April': 'أبريل',
                        'May': 'مايو', 'June': 'يونيو', 'July': 'يوليو', 'August': 'أغسطس',
                        'September': 'سبتمبر', 'October': 'أكتوبر', 'November': 'نوفمبر', 'December': 'ديسمبر',
                        'Jan': 'يناير', 'Feb': 'فبراير', 'Mar': 'مارس', 'Apr': 'أبريل',
                        'Jun': 'يونيو', 'Jul': 'يوليو', 'Aug': 'أغسطس',
                        'Sep': 'سبتمبر', 'Oct': 'أكتوبر', 'Nov': 'نوفمبر', 'Dec': 'ديسمبر'
                    };
                    for (let [enM, arM] of Object.entries(months)) {
                        str = str.replace(new RegExp(enM, 'ig'), arM);
                    }
                    return ktnBoTranslateDigits(str);
                }

                // Fetch & Translate labels in PHP
                <?php
                $bar_labels = array();
                if (!empty($charts['top_labels'])) {
                    foreach ($charts['top_labels'] as $lbl) {
                        $bar_labels[] = ktn_get_movie_display_title($lbl);
                    }
                }
                $trend_labels = array();
                if (!empty($charts['trend_labels'])) {
                    foreach ($charts['trend_labels'] as $lbl) {
                        $trend_labels[] = ktn_bo_translate($lbl);
                    }
                }
                ?>

                const barLabels = <?php echo json_encode($bar_labels); ?>;
                const barData = <?php echo json_encode($charts['top_vals'] ?? array()); ?>;

                if (barLabels.length > 0) {
                    const ctxBar = document.getElementById('ktn_bo_bar_chart').getContext('2d');
                    new Chart(ctxBar, {
                        type: 'bar',
                        data: {
                            labels: barLabels,
                            datasets: [{
                                label: '<?php echo esc_js(__('إيرادات الأسبوع (ج.م)', 'kontentainment')); ?>',
                                data: barData,
                                backgroundColor: 'rgba(59, 130, 246, 0.85)',
                                borderColor: 'rgba(59, 130, 246, 1)',
                                borderWidth: 1,
                                borderRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        title: function(context) {
                                            return ktnBoTranslateFullText(context[0].label);
                                        },
                                        label: function(context) {
                                            return ' ' + ktnBoTranslateFullText(Number(context.parsed.y).toLocaleString()) + ' ج.م';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { color: gridColor },
                                    ticks: { 
                                        color: textColor, 
                                        font: tickFont,
                                        callback: function(val, index) {
                                            const label = this.getLabelForValue(val);
                                            return ktnBoTranslateFullText(label);
                                        }
                                    }
                                },
                                y: {
                                    grid: { color: gridColor },
                                    ticks: { 
                                        color: textColor, 
                                        font: tickFont, 
                                        callback: function(value) {
                                            return ktnBoTranslateDigits(formatY(value));
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                const lineLabels = <?php echo json_encode($trend_labels); ?>;
                const lineData = <?php echo json_encode($charts['trend_vals'] ?? array()); ?>;

                if (lineLabels.length > 0) {
                    const ctxLine = document.getElementById('ktn_bo_line_chart').getContext('2d');
                    new Chart(ctxLine, {
                        type: 'line',
                        data: {
                            labels: lineLabels,
                            datasets: [{
                                label: '<?php echo esc_js(__('إجمالي إيرادات السوق', 'kontentainment')); ?>',
                                data: lineData,
                                borderColor: 'rgba(16, 185, 129, 1)',
                                backgroundColor: 'rgba(16, 185, 129, 0.08)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                pointRadius: 4,
                                pointHoverRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        title: function(context) {
                                            return ktnBoTranslateFullText(context[0].label);
                                        },
                                        label: function(context) {
                                            return ' ' + ktnBoTranslateFullText(Number(context.parsed.y).toLocaleString()) + ' ج.م';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { color: gridColor },
                                    ticks: { 
                                        color: textColor, 
                                        font: tickFont,
                                        callback: function(val, index) {
                                            const label = this.getLabelForValue(val);
                                            return ktnBoTranslateFullText(label);
                                        }
                                    }
                                },
                                y: {
                                    grid: { color: gridColor },
                                    ticks: { 
                                        color: textColor, 
                                        font: tickFont, 
                                        callback: function(value) {
                                            return ktnBoTranslateDigits(formatY(value));
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }
        });
        </script>

    <?php endif; ?>
</div>

<?php
if (!$is_shortcode) {
    get_footer();
}
