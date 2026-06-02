<?php
if (!defined('ABSPATH')) {
    exit;
}

class Ktn_Box_Office_Scraper
{
    private static $TRANSIENT_KEY = 'ktn_box_office_data';
    private static $SOURCE_URL = 'https://cinema-track.com';

    public static function fetch_box_office_data($force_refresh = false)
    {
        $cached = get_transient(self::$TRANSIENT_KEY);
        if ($cached !== false && !$force_refresh) {
            return $cached;
        }

        // If frontend, do not hang the page. Schedule a background event and return empty.
        if (!is_admin() && !wp_doing_ajax() && !$force_refresh) {
            if (!wp_next_scheduled('ktn_async_fetch_box_office')) {
                wp_schedule_single_event(time(), 'ktn_async_fetch_box_office');
            }
            return array();
        }

        return self::scrape_remote_data();
    }

    public static function scrape_remote_data()
    {
        $args = array(
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'
            ),
            'timeout' => 5,
            'sslverify' => false
        );

        // 1. Fetch Daily Arabic Movies
        $daily_ar = array();
        $res_ar = wp_remote_get('https://cinema-track.com/ar/%d9%8a%d9%88%d9%85%d9%8a/%d8%b9%d8%b1%d8%a8%d9%8a-%d9%8a%d9%88%d9%85%d9%8a/', $args);
        if (!is_wp_error($res_ar) && wp_remote_retrieve_response_code($res_ar) === 200) {
            $html_ar = wp_remote_retrieve_body($res_ar);
            if (!empty($html_ar)) {
                $daily_ar = self::parse_daily_box_office($html_ar, 'arabic');
            }
        }

        // 2. Fetch Daily Foreign Movies
        $daily_fore = array();
        $res_fore = wp_remote_get('https://cinema-track.com/ar/%d9%8a%d9%88%d9%85%d9%8a/%d8%a3%d8%ac%d9%86%d8%a8%d9%8a-%d9%8a%d9%88%d9%85%d9%8a/', $args);
        if (!is_wp_error($res_fore) && wp_remote_retrieve_response_code($res_fore) === 200) {
            $html_fore = wp_remote_retrieve_body($res_fore);
            if (!empty($html_fore)) {
                $daily_fore = self::parse_daily_box_office($html_fore, 'foreign');
            }
        }

        // Combine daily movies consecutively (backward compat)
        $daily = array_merge($daily_ar, $daily_fore);

        // 3. Fetch Weekly Box Office (Arabic)
        $weekly_ar = array();
        $res_weekly_ar = wp_remote_get('https://cinema-track.com/ar/%d8%a3%d8%b3%d8%a8%d9%88%d8%b9%d9%8a/%d8%b9%d8%b1%d8%a8%d9%8a-%d8%a3%d8%b3%d8%a8%d9%88%d8%b9%d9%8a/', $args);
        if (!is_wp_error($res_weekly_ar) && wp_remote_retrieve_response_code($res_weekly_ar) === 200) {
            $html_weekly_ar = wp_remote_retrieve_body($res_weekly_ar);
            if (!empty($html_weekly_ar)) {
                $weekly_ar = self::parse_weekly_box_office($html_weekly_ar);
            }
        }

        // 3b. Fetch Weekly Box Office (Foreign)
        $weekly_fore = array();
        $res_weekly_fore = wp_remote_get('https://cinema-track.com/ar/%d8%a3%d8%b3%d8%a8%d9%88%d8%b9%d9%8a/%d8%a3%d8%ac%d9%86%d8%a8%d9%8a-%d8%a3%d8%b3%d8%a8%d9%88%d8%b9%d9%8a/', $args);
        if (!is_wp_error($res_weekly_fore) && wp_remote_retrieve_response_code($res_weekly_fore) === 200) {
            $html_weekly_fore = wp_remote_retrieve_body($res_weekly_fore);
            if (!empty($html_weekly_fore)) {
                $weekly_fore = self::parse_weekly_box_office($html_weekly_fore);
            }
        }

        $weekly = array_merge($weekly_ar, $weekly_fore);

        // 4. Fetch homepage for date, charts, all-time, news
        $date = date('j M Y');
        $charts = array();
        $all_time = array();
        $news = array();

        $res_home = wp_remote_get(self::$SOURCE_URL, $args);
        if (!is_wp_error($res_home) && wp_remote_retrieve_response_code($res_home) === 200) {
            $html_home = wp_remote_retrieve_body($res_home);
            if (!empty($html_home)) {
                $date = self::parse_box_office_date($html_home);
                $charts = self::parse_market_insights($html_home);
                $all_time = self::parse_all_time_box_office($html_home);
                $news = self::parse_box_office_news($html_home);
                
                // Fallbacks if subpages failed
                if (empty($daily)) {
                    $daily = self::parse_daily_box_office($html_home);
                }
                if (empty($weekly)) {
                    $weekly = self::parse_weekly_box_office($html_home);
                }
            }
        }

        $data = array(
            'date'         => $date,
            'daily'        => $daily,
            'daily_arabic'  => $daily_ar,
            'daily_foreign' => $daily_fore,
            'weekly'       => $weekly,
            'weekly_arabic' => $weekly_ar,
            'weekly_foreign'=> $weekly_fore,
            'charts'       => $charts,
            'all_time'     => $all_time,
            'news'         => $news,
            'scraped_at'   => current_time('mysql')
        );

        // Cache for 12 hours
        set_transient(self::$TRANSIENT_KEY, $data, 12 * HOUR_IN_SECONDS);
        update_option('ktn_box_office_last_synced', current_time('mysql'));

        // Sync local movies box office stats!
        self::sync_scraped_movies_stats($data);

        return $data;
    }

    private static function parse_box_office_date($html)
    {
        if (preg_match('/Arabic Box Office\s*(?:-|–|&ndash;)\s*(?:<\/span>\s*)?([^<]+)/iu', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        return date('j M Y'); // Fallback
    }

    private static function parse_daily_box_office($html, $type = 'arabic')
    {
        $daily = array();
        // Extract the table body (resilient to different table selectors on subpages)
        $tbody = '';
        if (preg_match('/<table[^>]*analytics-today-table[^>]*>.*?<tbody>(.*?)<\/tbody>/is', $html, $tableMatch)) {
            $tbody = $tableMatch[1];
        } elseif (preg_match('/<table[^>]*>.*?<tbody>(.*?)<\/tbody>/is', $html, $tableMatch)) {
            $tbody = $tableMatch[1];
        }

        if (empty($tbody)) {
            return $daily;
        }

        // Match each row
        preg_match_all('/<tr>(.*?)<\/tr>/is', $tbody, $rowMatches);

            if (!empty($rowMatches[1])) {
                foreach ($rowMatches[1] as $rowHtml) {
                    $rowHtml = trim($rowHtml);
                    // Column mapping:
                    // 1. Rank: <td>\d+</td>
                    // 2. Poster: <td class="col-poster"> ... src="([^"]+)"
                    // 3. Movie Title & Link: <td> ... class="movie-link" ... >(.*?)</a>
                    // 4. Today Revenue: <td> ... </td>
                    // 5. Tickets Today: <td> ... </td>
                    // 6. Cinemas: <td class="col-cinemas"> ... </td>
                    // 7. Trend: <td class="trend (up|down|same)"> ... </td>

                    $rank = '';
                    $poster = '';
                    $title = '';
                    $revenue = '';
                    $tickets = '';
                    $cinemas = '';
                    $trend_class = 'same';
                    $trend_text = '—';

                    if (preg_match('/<td>\s*(\d+)\s*<\/td>/is', $rowHtml, $m)) {
                        $rank = $m[1];
                    }

                    if (preg_match('/<td[^>]*class="[^"]*col-poster[^"]*"[^>]*>.*?src="([^"]+)"/is', $rowHtml, $m)) {
                        $poster = $m[1];
                    }

                    $movie_url = '';
                    if (preg_match('/(<a[^>]*class="[^"]*movie-link[^"]*"[^>]*>)(.*?)<\/a>/is', $rowHtml, $m)) {
                        $title = trim(strip_tags($m[2]));
                        if (preg_match('/href="([^"]+)"/i', $m[1], $hrefM)) {
                            $movie_url = esc_url_raw($hrefM[1]);
                        }
                    } elseif (preg_match('/<a[^>]*class="[^"]*movie-link[^"]*"[^>]*>(.*?)<\/a>/is', $rowHtml, $m)) {
                        $title = trim(strip_tags($m[1]));
                    }

                    // Extract all <td> columns to grab Today Revenue and Tickets
                    preg_match_all('/<td>(.*?)<\/td>/is', $rowHtml, $tdMatches);
                    if (isset($tdMatches[1]) && count($tdMatches[1]) >= 4) {
                        // In standard rows:
                        // Index 0: Rank (but sometimes it's index 0 of tdMatches depending on the HTML format)
                        // Let's strip tags and clean them
                        $tds = array_map('strip_tags', array_map('trim', $tdMatches[1]));
                        // Let's find index where values contain "EGP" or represent clean numbers
                        foreach ($tds as $td_val) {
                            if (strpos($td_val, 'EGP') !== false) {
                                $revenue = $td_val;
                            }
                        }
                        
                        // Tickets is usually the one after EGP or at index 3/4
                        // Let's locate column 4 (usually today's revenue) and 5 (tickets) directly
                        // If we check row format:
                        // <td>1</td> (td 0)
                        // <td class="col-poster">...</td> (not in tdMatches if class matches td but wait, let's extract all TDs)
                    }

                    // Direct extraction of column text values
                    // Today Revenue: we find something ending with EGP
                    if (preg_match('/<td>\s*([0-9,]+\s*EGP)\s*<\/td>/is', $rowHtml, $m)) {
                        $revenue = $m[1];
                    }
                    
                    // Tickets: a sequence of digits with optional comma, which is not rank and doesn't contain EGP
                    // Let's match <td> with digits (thousands format)
                    if (preg_match_all('/<td>\s*([0-9,]+)\s*<\/td>/is', $rowHtml, $mAll)) {
                        foreach ($mAll[1] as $val) {
                            if ($val !== $rank) {
                                $tickets = $val;
                            }
                        }
                    }

                    if (preg_match('/<td[^>]*class="[^"]*col-cinemas[^"]*"[^>]*>(.*?)<\/td>/is', $rowHtml, $m)) {
                        $cinemas = trim(strip_tags($m[1]));
                    }

                    if (preg_match('/<td[^>]*class="[^"]*trend\s+([^"]+)"[^>]*>(.*?)<\/td>/is', $rowHtml, $m)) {
                        $trend_class = trim($m[1]);
                        $trend_text = trim(strip_tags($m[2]));
                    }

                    if (!empty($title)) {
                        $daily[] = array(
                            'rank'        => $rank,
                            'poster'      => $poster,
                            'title'       => $title,
                            'revenue'     => $revenue,
                            'tickets'     => $tickets,
                            'cinemas'     => $cinemas,
                            'trend_class' => $trend_class,
                            'trend_text'  => $trend_text,
                            'movie_url'   => $movie_url,
                            'type'        => $type
                        );
                    }
                }
            }
        return $daily;
    }

    private static function parse_weekly_box_office($html)
    {
        $weekly = array();
        preg_match_all('/(<a[^>]*class="[^"]*bo-card[^"]*"[^>]*>)(.*?)<\/a>/is', $html, $cardMatches, PREG_SET_ORDER);

        if (!empty($cardMatches)) {
            foreach ($cardMatches as $match) {
                $movie_url = '';
                if (preg_match('/href="([^"]+)"/i', $match[1], $hrefM)) {
                    $movie_url = esc_url_raw($hrefM[1]);
                }
                $cardHtml = $match[2];
                $rank = '';
                $poster = '';
                $title = '';
                $weekly_gross = '';
                $total_revenue = '';
                $admissions = '';

                if (preg_match('/<div[^>]*class="[^"]*bo-rank[^"]*"[^>]*>(\d+)<\/div>/is', $cardHtml, $m)) {
                    $rank = $m[1];
                }

                if (preg_match('/<img[^>]*src="([^"]+)"/is', $cardHtml, $m)) {
                    $poster = $m[1];
                }

                if (preg_match('/<h4>(.*?)<\/h4>/is', $cardHtml, $m)) {
                    $title = trim(strip_tags($m[1]));
                }

                if (preg_match('/<div[^>]*class="[^"]*metric\s+gross[^"]*"[^>]*>.*?<span class="value">(.*?)<\/span>/is', $cardHtml, $m)) {
                    $weekly_gross = trim(strip_tags($m[1]));
                }

                if (preg_match('/<div[^>]*class="[^"]*metric\s+total[^"]*"[^>]*>.*?<span class="value">(.*?)<\/span>/is', $cardHtml, $m)) {
                    $total_revenue = trim(strip_tags($m[1]));
                }

                if (preg_match('/<div[^>]*class="[^"]*metric\s+adm[^"]*"[^>]*>.*?<span class="value">(.*?)<\/span>/is', $cardHtml, $m)) {
                    $admissions = trim(strip_tags($m[1]));
                }

                if (!empty($title)) {
                    $weekly[] = array(
                        'rank' => $rank,
                        'poster' => $poster,
                        'title' => $title,
                        'weekly_gross' => $weekly_gross,
                        'total_revenue' => $total_revenue,
                        'admissions' => $admissions,
                        'movie_url' => $movie_url
                    );
                }
            }
        }
        return $weekly;
    }

    private static function parse_market_insights($html)
    {
        $top_labels = array();
        $top_vals = array();
        $trend_labels = array();
        $trend_vals = array();

        if (preg_match('/const\s+topLabels\s*=\s*(\[.*?\]);/is', $html, $m)) {
            $top_labels = json_decode($m[1], true) ?: array();
        }
        if (preg_match('/const\s+topVals\s*=\s*(\[.*?\]);/is', $html, $m)) {
            $top_vals = json_decode($m[1], true) ?: array();
        }
        if (preg_match('/const\s+trendLabels\s*=\s*(\[.*?\]);/is', $html, $m)) {
            $trend_labels = json_decode($m[1], true) ?: array();
        }
        if (preg_match('/const\s+trendVals\s*=\s*(\[.*?\]);/is', $html, $m)) {
            $trend_vals = json_decode($m[1], true) ?: array();
        }

        return array(
            'top_labels' => $top_labels,
            'top_vals' => $top_vals,
            'trend_labels' => $trend_labels,
            'trend_vals' => $trend_vals
        );
    }

    private static function parse_all_time_box_office($html)
    {
        $all_time = array();
        // Locate the bo-slider container
        if (preg_match('/<div[^>]*class="[^"]*bo-slider[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/is', $html, $sliderMatch)) {
            $slider_content = $sliderMatch[1];
        } else {
            $slider_content = $html;
        }

        // Split by flex:0 0 300px
        $chunks = preg_split('/<div style="flex:0 0 300px;/is', $slider_content);
        array_shift($chunks); // discard first chunk before splitter

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            $poster = '';
            $title = '';
            $status = 'Ended';
            $status_class = 'ended';
            $total_revenue = '';
            $release_date = '';
            $today_revenue = '';
            $today_tickets = '';

            if (preg_match('/<img[^>]*src="([^"]+)"/is', $chunk, $m)) {
                $poster = $m[1];
            }
            if (preg_match('/<img[^>]*alt="([^"]+)"/is', $chunk, $m)) {
                $title = trim(strip_tags($m[1]));
            } elseif (preg_match('/<h4[^>]*>(.*?)<\/h4>/is', $chunk, $m)) {
                $title = trim(strip_tags($m[1]));
            }

            if (preg_match('/<span[^>]*class="[^"]*movie-status-badge\s+status-([^"]+)"[^>]*>(.*?)<\/span>/is', $chunk, $m)) {
                $status_class = trim($m[1]);
                $status = trim(strip_tags($m[2]));
            }

            // Extract normal metrics
            preg_match_all('/<div class="metric">.*?<span class="icon">(.*?)<\/span>.*?<span class="value">(.*?)<\/span>.*?<span class="label">(.*?)<\/span>/is', $chunk, $metMatches, PREG_SET_ORDER);
            foreach ($metMatches as $match) {
                $label = strtolower(trim($match[3]));
                if (strpos($label, 'total') !== false) {
                    $total_revenue = trim($match[2]);
                } elseif (strpos($label, 'release') !== false) {
                    $release_date = trim($match[2]);
                }
            }

            // Extract high-lighted items (Now Playing movies in slider)
            preg_match_all('/<div class="highlight-item\s+([^"]+)">.*?<span class="icon">(.*?)<\/span>.*?<span class="value">(.*?)<\/span>.*?<span class="label">(.*?)<\/span>/is', $chunk, $highMatches, PREG_SET_ORDER);
            foreach ($highMatches as $match) {
                $type = strtolower(trim($match[1]));
                if (strpos($type, 'revenue') !== false) {
                    $today_revenue = trim($match[3]);
                } elseif (strpos($type, 'tickets') !== false) {
                    $today_tickets = trim($match[3]);
                }
            }

            if (!empty($title)) {
                $all_time[] = array(
                    'title' => $title,
                    'poster' => $poster,
                    'status' => $status,
                    'status_class' => $status_class,
                    'total_revenue' => $total_revenue,
                    'release_date' => $release_date,
                    'today_revenue' => $today_revenue,
                    'today_tickets' => $today_tickets
                );
            }
        }

        return $all_time;
    }

    private static function parse_box_office_news($html)
    {
        $news = array();
        // Match articles
        preg_match_all('/<article[^>]*>(.*?)<\/article>/is', $html, $articleMatches);

        if (!empty($articleMatches[1])) {
            foreach ($articleMatches[1] as $artHtml) {
                if (strpos($artHtml, 'Box office news') === false && strpos($artHtml, 'box-office-news') === false) {
                    continue; // Skip non-box office news articles
                }

                $title = '';
                $link = '';
                $poster = '';
                $excerpt = '';
                $date = '';

                if (preg_match('/<h3[^>]*class="[^"]*elementor-post__title[^"]*"[^>]*>.*?<a[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', $artHtml, $m)) {
                    $link = $m[1];
                    $title = trim(strip_tags($m[2]));
                }

                if (preg_match('/<img[^>]*src="([^"]+)"/is', $artHtml, $m)) {
                    $poster = $m[1];
                }

                if (preg_match('/<div[^>]*class="[^"]*elementor-post__excerpt[^"]*"[^>]*>.*?<p>(.*?)<\/p>/is', $artHtml, $m)) {
                    $excerpt = trim(strip_tags($m[1]));
                }

                if (preg_match('/<span[^>]*class="[^"]*elementor-post-date[^"]*"[^>]*>(.*?)<\/span>/is', $artHtml, $m)) {
                    $date = trim(strip_tags($m[1]));
                }

                if (!empty($title)) {
                    $news[] = array(
                        'title' => $title,
                        'link' => $link,
                        'poster' => $poster,
                        'excerpt' => $excerpt,
                        'date' => $date
                    );
                }
            }
        }
        return array_slice($news, 0, 3); // Return top 3 articles
    }

    public static function parse_single_movie_box_office($html)
    {
        $stats = array(
            'total_gross' => '',
            'today_gross' => '',
            'opening_week_gross' => '',
            'days_in_theaters' => '',
            'admissions_today' => '',
            'total_admissions' => '',
            'cinemas' => ''
        );

        if (preg_match('/<h4>Total Gross<\/h4>\s*<p>(.*?)<\/p>/is', $html, $m)) {
            $stats['total_gross'] = trim(strip_tags($m[1]));
        }
        if (preg_match('/<h4>Today\'s Gross<\/h4>\s*<p>(.*?)<\/p>/is', $html, $m)) {
            $stats['today_gross'] = trim(strip_tags($m[1]));
        }
        if (preg_match('/<h4>Opening Week Gross<\/h4>\s*<p>(.*?)<\/p>/is', $html, $m)) {
            $stats['opening_week_gross'] = trim(strip_tags($m[1]));
        }
        if (preg_match('/<h4>Days in Theaters<\/h4>\s*<p>(.*?)<\/p>/is', $html, $m)) {
            $stats['days_in_theaters'] = trim(strip_tags($m[1]));
        }
        if (preg_match('/<h4>Admissions Today<\/h4>\s*<p>(.*?)<\/p>/is', $html, $m)) {
            $stats['admissions_today'] = trim(strip_tags($m[1]));
        }
        if (preg_match('/<h4>Total Admissions<\/h4>\s*<p>(.*?)<\/p>/is', $html, $m)) {
            $stats['total_admissions'] = trim(strip_tags($m[1]));
        }
        if (preg_match('/<h4>Cinemas<\/h4>\s*<p>(.*?)<\/p>/is', $html, $m)) {
            $stats['cinemas'] = trim(strip_tags($m[1]));
        }

        return $stats;
    }

    public static function sync_scraped_movies_stats($data)
    {
        $all_movies = array();

        // 1. Gather unique scraped movies from daily list
        if (!empty($data['daily'])) {
            foreach ($data['daily'] as $m) {
                $title = $m['title'];
                if (!isset($all_movies[$title])) {
                    $all_movies[$title] = array(
                        'title' => $title,
                        'movie_url' => $m['movie_url'] ?? '',
                        'today_gross' => $m['revenue'] ?? '',
                        'admissions_today' => $m['tickets'] ?? '',
                        'cinemas' => $m['cinemas'] ?? ''
                    );
                } else {
                    if (!empty($m['movie_url'])) $all_movies[$title]['movie_url'] = $m['movie_url'];
                    if (!empty($m['revenue'])) $all_movies[$title]['today_gross'] = $m['revenue'];
                    if (!empty($m['tickets'])) $all_movies[$title]['admissions_today'] = $m['tickets'];
                    if (!empty($m['cinemas'])) $all_movies[$title]['cinemas'] = $m['cinemas'];
                }
            }
        }

        // 2. Gather unique scraped movies from weekly list
        if (!empty($data['weekly'])) {
            foreach ($data['weekly'] as $m) {
                $title = $m['title'];
                if (!isset($all_movies[$title])) {
                    $all_movies[$title] = array(
                        'title' => $title,
                        'movie_url' => $m['movie_url'] ?? '',
                        'total_gross' => $m['total_revenue'] ?? '',
                    );
                } else {
                    if (!empty($m['movie_url'])) $all_movies[$title]['movie_url'] = $m['movie_url'];
                    if (!empty($m['total_revenue'])) $all_movies[$title]['total_gross'] = $m['total_revenue'];
                }
            }
        }

        // 3. Process each movie to match and sync
        foreach ($all_movies as $title => $movie_data) {
            self::sync_single_movie_stats($title, $movie_data);
        }
    }

    public static function sync_single_movie_stats($title, $movie_data)
    {
        if (empty($title) || !function_exists('ktn_get_movie_id_by_title')) {
            return;
        }

        $post_id = ktn_get_movie_id_by_title($title);
        if (!$post_id) {
            return; // No local movie CPT post matched
        }

        // 1. Immediately update CPT metadata from list tables (always available)
        if (!empty($movie_data['movie_url'])) {
            update_post_meta($post_id, '_ktn_bo_cinema_track_url', esc_url_raw($movie_data['movie_url']));
        }
        if (!empty($movie_data['today_gross'])) {
            update_post_meta($post_id, '_ktn_bo_today_gross', sanitize_text_field($movie_data['today_gross']));
        }
        if (!empty($movie_data['admissions_today'])) {
            update_post_meta($post_id, '_ktn_bo_admissions_today', sanitize_text_field($movie_data['admissions_today']));
        }
        if (!empty($movie_data['cinemas'])) {
            update_post_meta($post_id, '_ktn_bo_cinemas', sanitize_text_field($movie_data['cinemas']));
        }
        if (!empty($movie_data['total_gross'])) {
            update_post_meta($post_id, '_ktn_bo_total_gross', sanitize_text_field($movie_data['total_gross']));
        }

        // 2. Fetch specific movie page for Opening Week and Days in Theaters (once every 12 hours)
        $movie_url = get_post_meta($post_id, '_ktn_bo_cinema_track_url', true);
        if (empty($movie_url)) {
            return;
        }

        $last_scraped = get_post_meta($post_id, '_ktn_bo_last_scraped', true);
        $time_diff = time() - intval($last_scraped);

        if (empty($last_scraped) || $time_diff > 12 * HOUR_IN_SECONDS) {
            $args = array(
                'headers' => array(
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'
                ),
                'timeout' => 30,
                'sslverify' => false
            );

            $res = wp_remote_get($movie_url, $args);
            if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
                $html = wp_remote_retrieve_body($res);
                if (!empty($html)) {
                    $parsed_stats = self::parse_single_movie_box_office($html);
                    
                    if (!empty($parsed_stats['total_gross'])) {
                        update_post_meta($post_id, '_ktn_bo_total_gross', sanitize_text_field($parsed_stats['total_gross']));
                    }
                    if (!empty($parsed_stats['today_gross'])) {
                        update_post_meta($post_id, '_ktn_bo_today_gross', sanitize_text_field($parsed_stats['today_gross']));
                    }
                    if (!empty($parsed_stats['opening_week_gross'])) {
                        update_post_meta($post_id, '_ktn_bo_opening_week_gross', sanitize_text_field($parsed_stats['opening_week_gross']));
                    }
                    if (!empty($parsed_stats['days_in_theaters'])) {
                        update_post_meta($post_id, '_ktn_bo_days_in_theaters', sanitize_text_field($parsed_stats['days_in_theaters']));
                    }
                    if (!empty($parsed_stats['admissions_today'])) {
                        update_post_meta($post_id, '_ktn_bo_admissions_today', sanitize_text_field($parsed_stats['admissions_today']));
                    }
                    if (!empty($parsed_stats['total_admissions'])) {
                        update_post_meta($post_id, '_ktn_bo_total_admissions', sanitize_text_field($parsed_stats['total_admissions']));
                    }
                    if (!empty($parsed_stats['cinemas'])) {
                        update_post_meta($post_id, '_ktn_bo_cinemas', sanitize_text_field($parsed_stats['cinemas']));
                    }

                    update_post_meta($post_id, '_ktn_bo_last_scraped', time());
                }
            }
        }
    }
}
