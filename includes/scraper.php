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
        $engDate = str_ireplace(array('يعرض حاليًا', 'يعرض حاليا', 'Now Playing'), '', $engDate);
        $engDate = trim($engDate);

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
        return self::normalizeWhitespace($t);
    }

    public static function parseShowtimeBlock($blockText)
    {
        $norm = self::normalizeArabicDigits($blockText);
        $timeExpRegex = '/(?:([A-Z0-9]+)[^\d<]{0,35})?(?:.*?)([0-9]{1,2}:[0-9]{2})\s*([صمظ]|[AP]M| صباحًا| مساءً)\s*(?:.*?([0-9]+\s*(?:ج\.م|EGP|LE|L\.E)))?/u';

        $showtimes = array();

        if (preg_match_all($timeExpRegex, $norm, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $experience = (!empty($match[1]) && strlen($match[1]) < 10) ? $match[1] : 'Standard';
                if (strpos($norm, '3D') !== false && $experience === 'Standard') {
                    $experience = '3D';
                }

                $time = $match[2];
                $ampmArEn = trim($match[3]);
                $priceArEn = isset($match[4]) ? $match[4] : '';

                $ampm = self::translateAmPm($ampmArEn);
                $priceText = $priceArEn ? trim(str_replace(array('ج.م', 'ج.PM', 'LE', 'L.E'), 'EGP', self::translateAmPm($priceArEn))) : '';

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
        
        if (empty($showtimes) && strpos($norm, ':') !== false) {
             if (preg_match_all('/([0-9]{1,2}:[0-9]{2})\s*([صمظ]|[AP]M)/u', $norm, $simpleMatches, PREG_SET_ORDER)) {
                  foreach ($simpleMatches as $sm) {
                       $showtimes[] = array(
                           'experience' => 'Standard',
                           'show_time' => trim($sm[1] . ' ' . self::translateAmPm($sm[2])),
                           'price_text' => ''
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
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9,ar;q=0.8',
                'Referer' => 'https://elcinema.com/'
            ),
            'timeout' => 45,
            'sslverify' => false
        );

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('http_error', 'HTTP ' . $code);
        }

        $html = wp_remote_retrieve_body($response);
        if (!$html) {
            return new WP_Error('empty_body', 'Empty body from elCinema');
        }

        $scraped_at = current_time('mysql');
        $initial_data = self::extractShowtimesFromHtml($html, $cinema_id, $url, $scraped_at);
        $results = $initial_data['showtimes'];
        $cinema_metadata = $initial_data['metadata'];

        // --- Multi-Date Extraction Logic ---
        $date_urls = array();
        
        if (preg_match_all('/href="([^"]*[\?&]date=([0-9]{4}-[0-9]{1,2}-[0-9]{1,2}))"/i', $html, $matches)) {
            $base_url = 'https://elcinema.com';
            $is_en = (strpos($url, '/en/') !== false);
            
            foreach ($matches[1] as $relative_url) {
                $full_url = (strpos($relative_url, 'http') === 0) ? $relative_url : (strpos($relative_url, '/') === 0 ? $base_url . $relative_url : $base_url . '/' . $relative_url);
                
                if ($is_en && strpos($full_url, '/en/') === false) {
                    $full_url = str_replace('elcinema.com/', 'elcinema.com/en/', $full_url);
                }

                if ($full_url !== $url && !in_array($full_url, $date_urls)) {
                    $date_urls[] = $full_url;
                }
            }
        }
        
        $date_urls = array_unique($date_urls);
        $date_urls = array_slice($date_urls, 0, 7); 

        foreach ($date_urls as $date_url) {
            usleep(200000); 
            $date_response = wp_remote_get($date_url, $args);
            if (!is_wp_error($date_response)) {
                $date_html = wp_remote_retrieve_body($date_response);
                if ($date_html) {
                    $date_data = self::extractShowtimesFromHtml($date_html, $cinema_id, $date_url, $scraped_at);
                    if (!empty($date_data['showtimes'])) {
                        $results = array_merge($results, $date_data['showtimes']);
                    }
                }
            }
        }

        $final_results = array();
        $seen = array();
        foreach($results as $res) {
            $key = md5($res['movie_title_scraped'] . $res['show_date'] . $res['show_time'] . $res['experience']);
            if (!isset($seen[$key])) {
                $final_results[] = $res;
                $seen[$key] = true;
            }
        }

        return array(
            'metadata' => $cinema_metadata,
            'showtimes' => $final_results
        );
    }

    private static function extractShowtimesFromHtml($html, $cinema_id, $url, $scraped_at)
    {
        $showtimes = array();
        $is_en = (strpos($url, '/en/') !== false);
        
        // Metadata ID
        $theater_id = '';
        if (preg_match('/\/theater\/([0-9]+)/', $url, $idMatch)) {
            $theater_id = $idMatch[1];
        }

        $name = ''; $arabic_name = ''; $english_name = ''; $logo = ''; $rating = ''; 
        $address = ''; $area = ''; $city = ''; $country = ''; $phone = ''; $notes = ''; $maps = '';

        // Name / Title
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $name = trim(strip_tags($m[1]));
            if ($is_en) $english_name = $name; else $arabic_name = $name;
        }

        // Secondary Names (h3 tags in theater block)
        if (preg_match_all('/<h3[^>]*>(.*?)<\/h3>/is', $html, $h3Matches)) {
            foreach ($h3Matches[1] as $idx => $h3Text) {
                $h3_clean = trim(strip_tags($h3Text));
                if (empty($h3_clean)) continue;
                
                // Usually first h3 is Arabic name, second is English name if on EN page
                if (preg_match('/[\x{0600}-\x{06FF}]/u', $h3_clean)) {
                    $arabic_name = $h3_clean;
                } else {
                    $english_name = $h3_clean;
                }
            }
        }

        // Details Block (Logo, Rating, Addr, Phone)
        if (preg_match('/<div[^>]*class="[^"]*columns[^"]*large-3[^"]*"[^>]*>.*?<img[^>]*src="([^"]*)"/is', $html, $logoMatch)) {
            $logo = $logoMatch[1];
        }

        // Rating
        if (preg_match('/<div[^>]*class="stars-orange"[^>]*>.*?<div>\s*([0-9.]+)\s*/is', $html, $rMatch)) {
            $rating = $rMatch[1];
        }

        // Info Block Parsing (ul.no-bullet)
        if (preg_match('/<ul[^>]*class="no-bullet"[^>]*>(.*?)<\/ul>/is', $html, $ulMatch)) {
            $ul_content = $ulMatch[1];
            $li_blocks = preg_split('/<\/li>/i', $ul_content);
            foreach ($li_blocks as $li) {
                $li_clean = trim(strip_tags($li));
                if (empty($li_clean)) continue;

                // Map Marker -> Address
                if (strpos($li, 'fa-map-marker') !== false) {
                    $address = $li_clean;
                }
                // Phone
                if (strpos($li, 'fa-phone') !== false) {
                    $phone = $li_clean;
                }
            }
        }

        // Breadcrumbs for Reliable Location (Country > City > Area)
        if (preg_match_all('/<li[^>]*><a[^>]*>(.*?)<\/a><\/li>/is', $html, $bc)) {
            $crumbs = array_map('trim', array_map('strip_tags', $bc[1]));
            if (count($crumbs) >= 4) {
                $country = $crumbs[2];
                $city = $crumbs[3];
                if (isset($crumbs[4]) && $crumbs[4] !== $name && strpos($crumbs[4], 'Theater') === false && strpos($crumbs[4], 'سينما') === false) {
                    $area = $crumbs[4];
                }
            }
        }

        // Notes (Policies) - usually the last ul.no-bullet or description div
        if (preg_match_all('/<ul[^>]*class="no-bullet"[^>]*>(.*?)<\/ul>/is', $html, $allUls)) {
            $last_ul = end($allUls[1]);
            if (strpos($last_ul, 'Policy') !== false || strpos($last_ul, 'سياسة') !== false) {
               $notes = trim(strip_tags($last_ul));
            }
        }
        if (empty($notes) && preg_match('/<div[^>]*class="[^"]*description[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m)) {
            $notes = trim(strip_tags($m[1]));
        }
        
        // Maps
        if (preg_match('/href="([^"]*google-map-theater[^"]*)"/i', $html, $m)) $maps = $m[1];

        // --- Date Selection ---
        $doc_date = '';
        if (preg_match('/[\?&]date=([0-9]{4}-[0-9]{1,2}-[0-9]{1,2})/', $url, $dm)) {
            $doc_date = $dm[1];
        } else {
            if (preg_match('/<li[^>]*class="active"[^>]*>.*?<a[^>]*>(.*?)<\/a>/is', $html, $activeMatch)) {
                $translated = self::translateDate($activeMatch[1]);
                $ts = strtotime($translated);
                if ($ts) $doc_date = date('Y-m-d', $ts);
            }
        }
        if (!$doc_date) $doc_date = date('Y-m-d');

        // --- Showtime Matrix Extraction ---
        $movie_blocks = preg_split('/<div[^>]*class="[^"]*work-title[^"]*"[^>]*>/is', $html);
        array_shift($movie_blocks);

        foreach ($movie_blocks as $block) {
            if (preg_match('/<a[^>]*>(.*?)<\/a>/is', $block, $tMatch)) {
                $movie_title = trim(strip_tags($tMatch[1]));
                $parsed_times = self::parseShowtimeBlock($block);
                foreach($parsed_times as $st) {
                    $showtimes[] = array(
                        'cinema_id' => $cinema_id,
                        'movie_title_scraped' => $movie_title,
                        'show_date' => $doc_date,
                        'show_time' => $st['show_time'],
                        'experience' => $st['experience'],
                        'price_text' => $st['price_text'],
                        'source_url' => $url,
                        'scraped_at' => $scraped_at
                    );
                }
            }
        }

        return array(
            'metadata' => array(
                'theater_id' => $theater_id,
                'name' => $name,
                'arabic_name' => $arabic_name,
                'english_name' => $english_name,
                'logo' => $logo,
                'rating' => $rating,
                'address' => $address,
                'area' => $area,
                'city' => $city,
                'country' => $country,
                'phone' => $phone,
                'notes' => $notes,
                'maps_url' => $maps
            ),
            'showtimes' => $showtimes
        );
    }
}