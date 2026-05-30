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

        return self::scrape_remote_data();
    }

    public static function scrape_remote_data()
    {
        $args = array(
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'
            ),
            'timeout' => 45,
            'sslverify' => false
        );

        $response = wp_remote_get(self::$SOURCE_URL, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('http_error', 'HTTP ' . $code);
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return new WP_Error('empty_body', 'Empty body received from source.');
        }

        // Parse sections
        $date = self::parse_box_office_date($html);
        $daily = self::parse_daily_box_office($html);
        $weekly = self::parse_weekly_box_office($html);
        $charts = self::parse_market_insights($html);
        $all_time = self::parse_all_time_box_office($html);
        $news = self::parse_box_office_news($html);

        $data = array(
            'date' => $date,
            'daily' => $daily,
            'weekly' => $weekly,
            'charts' => $charts,
            'all_time' => $all_time,
            'news' => $news,
            'scraped_at' => current_time('mysql')
        );

        // Cache for 12 hours
        set_transient(self::$TRANSIENT_KEY, $data, 12 * HOUR_IN_SECONDS);
        update_option('ktn_box_office_last_synced', current_time('mysql'));

        return $data;
    }

    private static function parse_box_office_date($html)
    {
        if (preg_match('/Arabic Box Office\s*(?:-|–|&ndash;)\s*(?:<\/span>\s*)?([^<]+)/iu', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        return date('j M Y'); // Fallback
    }

    private static function parse_daily_box_office($html)
    {
        $daily = array();
        // Extract the table body
        if (preg_match('/<table[^>]*analytics-today-table[^>]*>.*?<tbody>(.*?)<\/tbody>/is', $html, $tableMatch)) {
            $tbody = $tableMatch[1];
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

                    if (preg_match('/<a[^>]*class="[^"]*movie-link[^"]*"[^>]*>(.*?)<\/a>/is', $rowHtml, $m)) {
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
                            'rank' => $rank,
                            'poster' => $poster,
                            'title' => $title,
                            'revenue' => $revenue,
                            'tickets' => $tickets,
                            'cinemas' => $cinemas,
                            'trend_class' => $trend_class,
                            'trend_text' => $trend_text
                        );
                    }
                }
            }
        }
        return $daily;
    }

    private static function parse_weekly_box_office($html)
    {
        $weekly = array();
        preg_match_all('/<a[^>]*class="[^"]*bo-card[^"]*"[^>]*>(.*?)<\/a>/is', $html, $cardMatches);

        if (!empty($cardMatches[1])) {
            foreach ($cardMatches[1] as $cardHtml) {
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
                        'admissions' => $admissions
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
}
