<?php
if (!defined('ABSPATH')) {
    exit;
}

class Ktn_Cinema_Scraper
{

    private static $DAY_MAP = array(
        'السبت' => 'Saturday',
        'الأحد' => 'Sunday',
        'الاحد' => 'Sunday',
        'الإثنين' => 'Monday',
        'الاثنين' => 'Monday',
        'الثلاثاء' => 'Tuesday',
        'الأربعاء' => 'Wednesday',
        'الاربعاء' => 'Wednesday',
        'الخميس' => 'Thursday',
        'الجمعة' => 'Friday',
        'الجمعه' => 'Friday'
    );

    private static $MONTH_MAP = array(
        'يناير' => 'January',
        'فبراير' => 'February',
        'مارس' => 'March',
        'أبريل' => 'April',
        'ابريل' => 'April',
        'إبريل' => 'April',
        'مايو' => 'May',
        'يونيو' => 'June',
        'يونيه' => 'June',
        'يوليو' => 'July',
        'يوليه' => 'July',
        'أغسطس' => 'August',
        'اغسطس' => 'August',
        'سبتمبر' => 'September',
        'أكتوبر' => 'October',
        'اكتوبر' => 'October',
        'نوفمبر' => 'November',
        'ديسمبر' => 'December'
    );

    private static $ARABIC_NUMERALS = array(
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'
    );

    public static function normalizeArabicDigits($str)
    {
        if (!$str)
            return '';
        return strtr($str, self::$ARABIC_NUMERALS);
    }

    public static function normalizeWhitespace($str)
    {
        if (!$str)
            return '';
        return trim(preg_replace('/\s+/', ' ', $str));
    }

    public static function translateDate($dateStr)
    {
        if (!$dateStr)
            return '';
        $engDate = self::normalizeWhitespace($dateStr);
        $engDate = str_replace(array('يعرض حاليًا', 'يعرض حاليا'), '', $engDate);
        $engDate = trim($engDate);

        // Remove Arabic numbers if they are sticking to names
        foreach (self::$DAY_MAP as $arDay => $enDay) {
            $engDate = str_replace($arDay, $enDay, $engDate);
        }
        foreach (self::$MONTH_MAP as $arMonth => $enMonth) {
            $engDate = str_replace($arMonth, $enMonth, $engDate);
        }
        return $engDate;
    }

    public static function translateAmPm($timeStr)
    {
        if (!$timeStr)
            return '';

        $t = preg_replace('/ص(?:باحا|باحاً|باحا)?/u', 'AM', $timeStr);
        $t = preg_replace('/ظ(?:هرا|هراً)?/u', 'PM', $t);
        $t = preg_replace('/م(?:ساء|ساءا|ساءً|ساء)?/u', 'PM', $t);

        $t = str_replace('ج.م', 'EGP', $t);
        $t = str_replace('ج.PM', 'EGP', $t);

        return self::normalizeWhitespace($t);
    }

    public static function parseShowtimeBlock($blockText)
    {
        $norm = self::normalizeArabicDigits($blockText);
        $timeExpRegex = '/(?:(Standard|VIP|3D|IMAX|4DX)[^\d]{0,20})?(?:.*?)([0-9]{1,2}:[0-9]{2})\s*([صمظ][^\s\d]{0,8})\s*([0-9]+\s*ج\.م)?/u';

        $showtimes = array();

        if (preg_match_all($timeExpRegex, $norm, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $experience = !empty($match[1]) ? $match[1] : 'Standard';
                if (strpos($norm, '3D') !== false) {
                    $experience = '3D';
                }

                $time = $match[2];
                $ampmAr = $match[3];
                $priceAr = isset($match[4]) ? $match[4] : '';

                $ampm = self::translateAmPm($ampmAr);
                $priceText = $priceAr ? trim(str_replace(array('ج.م', 'ج.PM'), 'EGP', self::translateAmPm($priceAr))) : '';

                // deduplication per line logic, although preg_match_all moves forward
                $showtime = trim("$time $ampm");
                if (!empty($showtime)) {
                    $showtimes[] = array(
                        'experience' => $experience,
                        'show_time' => $showtime,
                        'price_text' => $priceText
                    );
                }
            }
        }
        return $showtimes;
    }

    public static function fetch_from_elcinema($cinema_id, $url)
    {
        $args = array(
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9,ar;q=0.8'
            ),
            'timeout' => 30,
            'sslverify' => false
        );

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            update_post_meta($cinema_id, '_ktn_last_error', 'Scraper Error: ' . $response->get_error_message());
            return false;
        }

        $html = wp_remote_retrieve_body($response);
        if (!$html) {
            update_post_meta($cinema_id, '_ktn_last_error', 'Empty HTML body.');
            return false;
        }

        $results = array();
        $scraped_at = current_time('mysql');

        // Parse initial page
        $initial_results = self::extractShowtimesFromHtml($html, $cinema_id, $url, $scraped_at);
        if (!empty($initial_results)) {
            $results = array_merge($results, $initial_results);
        }

        // Find more dates - Look for date tabs or dropdowns
        $date_urls = array();
        // Regex to find ?date=YYYY-MM-DD in hrefs
        if (preg_match_all('/href="([^"]*[\?&]date=([0-9]{4}-[0-9]{2}-[0-9]{2}))"/i', $html, $matches)) {
            $base_url = 'https://elcinema.com';
            foreach ($matches[1] as $relative_url) {
                $full_url = (strpos($relative_url, 'http') === 0) ? $relative_url : $base_url . $relative_url;
                
                // Avoid redundant scraping of the same URL or the initial URL
                if ($full_url !== $url && !in_array($full_url, $date_urls)) {
                    $date_urls[] = $full_url;
                }
            }
        }
        
        // Sometimes dates are in a dropdown or other list without full ?date= suffix but different IDs
        // But for elCinema, the ?date= param is the standard way.
        
        $date_urls = array_unique($date_urls);
        $date_urls = array_slice($date_urls, 0, 10); // Look ahead up to 10 days if available

        foreach ($date_urls as $date_url) {
            // Add slight delay to avoid being blocked
            usleep(200000); // 200ms
            
            $date_response = wp_remote_get($date_url, $args);
            if (!is_wp_error($date_response)) {
                $date_html = wp_remote_retrieve_body($date_response);
                if ($date_html) {
                    $date_results = self::extractShowtimesFromHtml($date_html, $cinema_id, $date_url, $scraped_at);
                    if (!empty($date_results)) {
                        $results = array_merge($results, $date_results);
                    }
                }
            }
        }

        // Final deduplication of results in memory just in case
        $final_results = array();
        $seen_keys = array();
        foreach($results as $res) {
            $key = md5($res['cinema_id'] . $res['movie_title_scraped'] . $res['show_date'] . $res['show_time']);
            if (!isset($seen_keys[$key])) {
                $final_results[] = $res;
                $seen_keys[$key] = true;
            }
        }

        update_post_meta($cinema_id, '_ktn_last_error', 'Scraped ' . (count($date_urls) + 1) . ' pages. Found ' . count($final_results) . ' total unique showtimes.');
        return $final_results;
    }

    private static function extractShowtimesFromHtml($html, $cinema_id, $url, $scraped_at)
    {
        $results = array();
        
        // Find cinema name
        $cinema_name = '';
        if (preg_match('/<h[12][^>]*>(.*?)<\/h[12]>/is', $html, $m)) {
            $cinema_name = trim(strip_tags($m[1]));
        }

        // --- Date Extraction Strategy ---
        $current_date = '';
        
        // 1. Try to extract date from the URL if it has ?date=YYYY-MM-DD
        if (preg_match('/[\?&]date=([0-9]{4}-[0-9]{2}-[0-9]{2})/', $url, $urlDateMatch)) {
            $current_date = $urlDateMatch[1]; // Use YYYY-MM-DD directly
        }

        // 2. If no URL date, try to find the active date tab in the HTML
        if (!$current_date) {
            if (preg_match('/<li[^>]*class="active"[^>]*>.*?<a[^>]*>(.*?)<\/a>/is', $html, $activeDateMatch)) {
                $found_date = self::translateDate(strip_tags($activeDateMatch[1]));
                // Normalize "Today" or other strings to YYYY-MM-DD if possible
                if ($found_date) {
                    $ts = strtotime($found_date);
                    if ($ts) {
                        $current_date = date('Y-m-d', $ts);
                    } else {
                        $current_date = $found_date;
                    }
                }
            }
        }

        // 3. Last fallback: if it's the main page and no date found, it's likely Today
        if (!$current_date) {
            $current_date = date('Y-m-d');
        }

        // Split by row to mimic looping over movies
        $blocks = explode('class="row"', $html);
        foreach ($blocks as $block) {
            // If the row contains a sub-header with a date, it overrides for this block (unlikely on elCinema but safe)
            if (preg_match('/<h2[^>]*class="[^"]*section-title[^"]*"[^>]*>(.*?)<\/h2>/is', $block, $dateMatch)) {
                $h2Title = trim(strip_tags($dateMatch[1]));
                $trans = self::translateDate($h2Title);
                if ($trans) {
                    $ts = strtotime($trans);
                    if ($ts) {
                        $current_date = date('Y-m-d', $ts);
                    }
                }
            }

            // Find Movie Title
            if (preg_match('/(?:<h3|<h2).*?<a[^>]*href="[^"]*\/work\/[^"]*"[^>]*>(.*?)<\/a>.*?(?:<\/h3>|<\/h2>)/is', $block, $m)) {
                $movieTitle = trim(strip_tags($m[1]));

                if (preg_match_all('/<ul[^>]*>(.*?)<\/ul>/is', $block, $ulMatches)) {
                    foreach ($ulMatches[1] as $ulContent) {
                        $listText = trim(strip_tags(str_replace('><', '> <', $ulContent)));
                        $normText = self::normalizeArabicDigits($listText);

                        if ($listText && preg_match('/\d+:\d+/', $normText)) {
                            $showtimes = self::parseShowtimeBlock($listText);
                            foreach ($showtimes as $show) {
                                $results[] = array(
                                    'cinema_id' => $cinema_id,
                                    'cinema_name' => $cinema_name,
                                    'source_url' => $url,
                                    'movie_title_scraped' => $movieTitle,
                                    'show_date' => $current_date,
                                    'experience' => $show['experience'],
                                    'show_time' => $show['show_time'],
                                    'price_text' => $show['price_text'],
                                    'scraped_at' => $scraped_at
                                );
                            }
                        }
                    }
                }
            }
        }
        return $results;
    }
}