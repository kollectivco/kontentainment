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
        $all_time = $data['all_time'] ?? array();
        $news = $data['news'] ?? array();
    ?>
        
        <!-- Header -->
        <div class="ktn-bo-header-container">
            <div class="ktn-bo-header-left">
                <h1 class="ktn-bo-title">
                    <span class="gold-text"><?php esc_html_e('شباك التذاكر العربي – ', 'kontentainment'); ?></span>
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
                            ?>
                                <tr>
                                    <td class="col-rank rank-<?php echo esc_attr($item['rank']); ?>"><?php echo esc_html(ktn_bo_translate($item['rank'])); ?></td>
                                    <td class="col-poster">
                                        <?php if (!empty($item['poster'])): ?>
                                            <img src="<?php echo esc_url($item['poster']); ?>" alt="<?php echo esc_attr($item['title']); ?> Poster" class="bo-thumb">
                                        <?php else: ?>
                                            <div class="bo-no-thumb"><span class="dashicons dashicons-format-image"></span></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-movie">
                                        <div class="movie-name"><?php echo esc_html($item['title']); ?></div>
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
                    ?>
                        <div class="ktn-bo-mob-card">
                            <div class="mob-card-header">
                                <div class="mob-rank rank-<?php echo esc_attr($item['rank']); ?>"><?php echo esc_html(ktn_bo_translate($item['rank'])); ?></div>
                                <?php if (!empty($item['poster'])): ?>
                                    <img src="<?php echo esc_url($item['poster']); ?>" alt="<?php echo esc_attr($item['title']); ?>" class="mob-thumb">
                                <?php endif; ?>
                                <div class="mob-title"><?php echo esc_html($item['title']); ?></div>
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
                <?php foreach ($weekly as $item): ?>
                    <div class="ktn-bo-weekly-card">
                        <div class="card-rank"><?php echo esc_html(ktn_bo_translate($item['rank'])); ?></div>
                        <div class="card-media">
                            <?php if (!empty($item['poster'])): ?>
                                <img src="<?php echo esc_url($item['poster']); ?>" alt="<?php echo esc_attr($item['title']); ?> Poster">
                            <?php else: ?>
                                <div class="no-poster-wrap"><span class="dashicons dashicons-video-alt3" style="font-size: 60px; width: 60px; height: 60px;"></span></div>
                            <?php endif; ?>
                        </div>
                        <div class="card-info">
                            <h3 class="movie-title"><?php echo esc_html($item['title']); ?></h3>
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

        <!-- Section 4: Top 10 All Time Slider -->
        <div class="ktn-bo-section-header">
            <h2 class="ktn-section-title">
                <span class="gold-text"><?php esc_html_e('الأفلام الأعلى إيراداً', 'kontentainment'); ?></span>
                <span><?php esc_html_e(' على الإطلاق', 'kontentainment'); ?></span>
            </h2>
        </div>

        <div class="ktn-bo-slider-container">
            <button class="slider-arrow arrow-left" id="slide-left">&#10094;</button>
            <div class="ktn-bo-slider" id="bo-slider-wrap">
                <?php if (empty($all_time)): ?>
                    <p style="text-align: center; color: #9ca3af; width: 100%;"><?php esc_html_e('لا توجد بيانات متاحة.', 'kontentainment'); ?></p>
                <?php else: ?>
                    <?php foreach ($all_time as $index => $movie): 
                        $status_cls = 'status-' . ($movie['status_class'] ?? 'ended');
                    ?>
                        <div class="slider-item">
                            <div class="movie-poster-wrap">
                                <?php if (!empty($movie['poster'])): ?>
                                    <img src="<?php echo esc_url($movie['poster']); ?>" alt="<?php echo esc_attr($movie['title']); ?>">
                                <?php else: ?>
                                    <div class="no-poster-slide"><span class="dashicons dashicons-video-alt3" style="font-size: 60px;"></span></div>
                                <?php endif; ?>
                                <span class="movie-status-badge <?php echo esc_attr($status_cls); ?>">
                                    <?php echo esc_html(str_ireplace(['Ended', 'Now Playing'], [__('انتهى عرضه', 'kontentainment'), __('يعرض حالياً', 'kontentainment')], $movie['status'])); ?>
                                </span>
                            </div>
                            <h4 class="slider-movie-title"><?php echo esc_html($movie['title']); ?></h4>
                            <div class="slider-metrics">
                                <?php if (!empty($movie['today_revenue'])): ?>
                                    <div class="slider-highlight-item revenue">
                                        <span class="icon">💰</span>
                                        <div class="meta">
                                            <span class="val"><?php echo esc_html(ktn_bo_format_egp($movie['today_revenue'])); ?></span>
                                            <span class="lbl"><?php esc_html_e('إيرادات اليوم', 'kontentainment'); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($movie['today_tickets'])): ?>
                                    <div class="slider-highlight-item tickets">
                                        <span class="icon">🎟️</span>
                                        <div class="meta">
                                            <span class="val"><?php echo esc_html(ktn_bo_translate($movie['today_tickets'])); ?></span>
                                            <span class="lbl"><?php esc_html_e('تذاكر اليوم', 'kontentainment'); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="slider-metric">
                                    <span class="icon">🏆</span>
                                    <div class="meta">
                                        <span class="val"><?php echo esc_html(ktn_bo_format_egp($movie['total_revenue'])); ?></span>
                                        <span class="lbl"><?php esc_html_e('إجمالي الإيرادات', 'kontentainment'); ?></span>
                                    </div>
                                </div>
                                <div class="slider-metric">
                                    <span class="icon">📅</span>
                                    <div class="meta">
                                        <span class="val"><?php echo esc_html(ktn_bo_translate($movie['release_date'])); ?></span>
                                        <span class="lbl"><?php esc_html_e('تاريخ الإصدار', 'kontentainment'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button class="slider-arrow arrow-right" id="slide-right">&#10095;</button>
        </div>

        <!-- Section 5: Box Office News -->
        <?php if (!empty($news)): ?>
            <div class="ktn-bo-section-header" style="margin-top: 60px;">
                <h2 class="ktn-section-title">
                    <span class="gold-text"><?php esc_html_e('أخبار شباك', 'kontentainment'); ?></span>
                    <span><?php esc_html_e(' التذاكر', 'kontentainment'); ?></span>
                </h2>
            </div>
            <div class="ktn-bo-news-grid">
                <?php foreach ($news as $post): ?>
                    <article class="news-card">
                        <div class="news-card-inner">
                            <div class="news-poster">
                                <?php if (!empty($post['poster'])): ?>
                                    <img src="<?php echo esc_url($post['poster']); ?>" alt="<?php echo esc_attr($post['title']); ?>">
                                <?php else: ?>
                                    <div class="no-img-news"><span class="dashicons dashicons-admin-post"></span></div>
                                <?php endif; ?>
                                <span class="news-badge"><?php esc_html_e('أخبار شباك التذاكر', 'kontentainment'); ?></span>
                            </div>
                            <div class="news-body">
                                <h3 class="news-title">
                                    <a href="<?php echo esc_url($post['link']); ?>" target="_blank"><?php echo esc_html($post['title']); ?></a>
                                </h3>
                                <p class="news-excerpt"><?php echo esc_html($post['excerpt']); ?></p>
                                <div class="news-footer">
                                    <span class="news-date"><?php echo esc_html(ktn_bo_translate($post['date'])); ?></span>
                                    <a href="<?php echo esc_url($post['link']); ?>" target="_blank" class="read-more"><?php esc_html_e('اقرأ المزيد »', 'kontentainment'); ?></a>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Script logic for charts and slider -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. ChartJS Initialization
            if (typeof Chart !== 'undefined') {
                const isMobile = window.innerWidth <= 768;
                const gridColor = 'rgba(255, 255, 255, 0.05)';
                const textColor = '#9ca3af';
                const tickFont = { family: 'Cairo, sans-serif', size: isMobile ? 10 : 12 };

                // Handle Y axis abbreviation (24,500,000 -> 24.5M)
                function formatY(value) {
                    if (value >= 1e6) return (value / 1e6).toFixed(1) + 'M';
                    if (value >= 1e3) return (value / 1e3).toFixed(0) + 'K';
                    return value;
                }

                // Data values passed from PHP Scraper
                const barLabels = <?php echo json_encode($charts['top_labels'] ?? array()); ?>;
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
                                backgroundColor: 'rgba(59, 130, 246, 0.8)',
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
                                        label: function(context) {
                                            return ' ' + Number(context.parsed.y).toLocaleString() + ' ج.م';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { color: gridColor },
                                    ticks: { color: textColor, font: tickFont }
                                },
                                y: {
                                    grid: { color: gridColor },
                                    ticks: { color: textColor, font: tickFont, callback: formatY }
                                }
                            }
                        }
                    });
                }

                const lineLabels = <?php echo json_encode($charts['trend_labels'] ?? array()); ?>;
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
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
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
                                        label: function(context) {
                                            return ' ' + Number(context.parsed.y).toLocaleString() + ' ج.م';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { color: gridColor },
                                    ticks: { color: textColor, font: tickFont }
                                },
                                y: {
                                    grid: { color: gridColor },
                                    ticks: { color: textColor, font: tickFont, callback: formatY }
                                }
                            }
                        }
                    });
                }
            }

            // 2. All Time Movie Slider Drag & Arrow Functionality
            const slider = document.getElementById('bo-slider-wrap');
            const arrowLeft = document.getElementById('slide-left');
            const arrowRight = document.getElementById('slide-right');
            
            if (slider && arrowLeft && arrowRight) {
                const scrollAmount = 320; // card width + margin

                arrowLeft.addEventListener('click', function() {
                    slider.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
                });

                arrowRight.addEventListener('click', function() {
                    slider.scrollBy({ left: scrollAmount, behavior: 'smooth' });
                });

                // Horizontal drag support
                let isDown = false;
                let startX;
                let scrollLeft;

                slider.addEventListener('mousedown', (e) => {
                    isDown = true;
                    slider.classList.add('dragging');
                    startX = e.pageX - slider.offsetLeft;
                    scrollLeft = slider.scrollLeft;
                });

                slider.addEventListener('mouseleave', () => {
                    isDown = false;
                    slider.classList.remove('dragging');
                });

                slider.addEventListener('mouseup', () => {
                    isDown = false;
                    slider.classList.remove('dragging');
                });

                slider.addEventListener('mousemove', (e) => {
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - slider.offsetLeft;
                    const walk = (x - startX) * 1.5; // scroll speed multiplier
                    slider.scrollLeft = scrollLeft - walk;
                });
            }
        });
        </script>

    <?php endif; ?>
</div>

<?php
if (!$is_shortcode) {
    get_footer();
}
