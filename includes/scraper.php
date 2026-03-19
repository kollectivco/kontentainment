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
        // Improved regex to support both Arabic (ص/م/ظ) and Latin (AM/PM) indicators, and wider prefix matching
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
        
        // Fallback for list based times if regex fails
        if (empty($showtimes) && strpos($norm, ':') !== false) {
             // simplified extraction for cases where UL/LI tags might break the regex
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
            $msg = 'Network Error: ' . $response->get_error_message();
            update_post_meta($cinema_id, '_ktn_last_error', $msg);
            return new WP_Error('scraper_network_error', $msg);
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $msg = 'HTTP Error: ' . $code;
            update_post_meta($cinema_id, '_ktn_last_error', $msg);
            return new WP_Error('scraper_http_error', $msg);
        }

        $html = wp_remote_retrieve_body($response);
        if (!$html || strpos($html, 'Access Denied') !== false) {
            $msg = 'Access Blocked by elCinema or empty body.';
            update_post_meta($cinema_id, '_ktn_last_error', $msg);
            return new WP_Error('scraper_access_error', $msg);
        }

        $results = array();
        $scraped_at = current_time('mysql');

        $initial_data = self::extractShowtimesFromHtml($html, $cinema_id, $url, $scraped_at);
        $cinema_metadata = $initial_data['metadata'];
        if (!empty($initial_data['showtimes'])) {
            $results = array_merge($results, $initial_data['showtimes']);
        }

        // Find more dates
        $date_urls = array();
        if (preg_match_all('/href="([^"]*[\?&]date=([0-9]{4}-[0-9]{1,2}-[0-9]{1,2}))"/i', $html, $matches)) {
            $base_url = 'https://elcinema.com';
            $is_en = (strpos($url, '/en/') !== false);
            
            foreach ($matches[1] as $relative_url) {
                // Ensure absolute URL
                $full_url = (strpos($relative_url, 'http') === 0) ? $relative_url : (strpos($relative_url, '/') === 0 ? $base_url . $relative_url : $base_url . '/' . $relative_url);
                
                // If on EN page, sub-URLs should also be EN
                if ($is_en && strpos($full_url, '/en/') === false) {
                    $full_url = str_replace('elcinema.com/', 'elcinema.com/en/', $full_url);
                }

                if ($full_url !== $url && !in_array($full_url, $date_urls)) {
                    $date_urls[] = $full_url;
                }
            }
        }
        
        $date_urls = array_unique($date_urls);
        $date_urls = array_slice($date_urls, 0, 10);

        foreach ($date_urls as $date_url) {
            usleep(500000); // 0.5s delay to be polite
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
        $seen_keys = array();
        foreach($results as $res) {
            $key = md5($res['cinema_id'] . $res['movie_title_scraped'] . $res['show_date'] . $res['show_time'] . $res['experience']);
            if (!isset($seen_keys[$key])) {
                $final_results[] = $res;
                $seen_keys[$key] = true;
            }
        }

        if (empty($final_results) && empty($cinema_metadata['name'])) {
            $msg = 'Parsing Error: No showtimes or metadata detected.';
            update_post_meta($cinema_id, '_ktn_last_error', $msg);
            return new WP_Error('scraper_no_results', $msg);
        }

        update_post_meta($cinema_id, '_ktn_last_error', 'Success: Extracted ' . count($final_results) . ' showtimes.');
        
        return array(
            'metadata' => $cinema_metadata,
            'showtimes' => $final_results
        );
    }

    private static function extractShowtimesFromHtml($html, $cinema_id, $url, $scraped_at)
    {
        $showtimes = array();
        $is_en = (strpos($url, '/en/') !== false);
        
        // Metadata extraction
        $theater_id = '';
        if (preg_match('/\/theater\/([0-9]+)/', $url, $idMatch)) {
            $theater_id = $idMatch[1];
        }

        $cinema_name = '';
        $arabic_name = '';
        $english_name = '';
        $logo_url = '';
        $rating = '';
        $address = '';
        $area = '';
        $phone = '';
        $city = '';
        $country = '';
        $notes = '';
        $maps_url = '';

        // Extract H1 name as priority display name
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $cinema_name = trim(strip_tags($m[1]));
            if ($is_en) {
                $english_name = $cinema_name;
            } else {
                $arabic_name = $cinema_name;
            }
        }

        // Try to find both names in the list-details block
        if (preg_match('/<ul[^>]*class="[^"]*list-details[^"]*"[^>]*>(.*?)<\/ul>/is', $html, $ulMatch)) {
             $ul_html = $ulMatch[1];
             $li_items = preg_split('/<\/li>/i', $ul_html);
             
             // First item usually contains the alternate name or main name
             // For English, it usually lists: 1. Arabic Name 2. English Name
             foreach ($li_items as $li) {
                 $li_clean = trim(strip_tags($li));
                 if (empty($li_clean)) continue;
                 
                 // Experience logic check
                 if ($is_en && empty($arabic_name) && preg_match('/[\x{0600}-\x{06FF}]/u', $li_clean)) {
                      $arabic_name = $li_clean;
                 }
                 if (!$is_en && empty($english_name) && preg_match('/[a-z]{3,}/i', $li_clean)) {
                      $english_name = $li_clean;
                 }
                 
                 // Rating extraction from li if it has rating tag
                 if (strpos($li, 'rating') !== false && preg_match('/([0-9.]+)/', $li_clean, $rVal)) {
                      $rating = $rVal[1];
                 }
                 
                 // Address extraction
                 if (strpos($li, 'fa-map-marker') !== false || strpos($li, 'Address') !== false || strpos($li, 'العنوان') !== false) {
                      $address = trim(str_replace(array('Address:', 'العنوان:'), '', $li_clean));
                 }
                 
                 // Phone extraction
                 if (strpos($li, 'fa-phone') !== false || strpos($li, 'Phone') !== false || strpos($li, 'الهاتف') !== false) {
                      $phone = trim(str_replace(array('Phone:', 'الهاتف:'), '', $li_clean));
                 }
             }
        }

        // Fallback for names if list-details didn't give them
        if (empty($english_name) && $is_en) $english_name = $cinema_name;
        if (empty($arabic_name) && !$is_en) $arabic_name = $cinema_name;

        // Extract logo with og:image fallback
        if (preg_match('/<img[^>]*class="[^"]*cinema-logo[^"]*"[^>]*src="([^"]*)"/i', $html, $m)) {
            $logo_url = $m[1];
        } elseif (preg_match('/<div[^>]*class="[^"]*cinema-image[^"]*"[^>]*>.*?<img[^>]*src="([^"]*)"/is', $html, $m)) {
            $logo_url = $m[1];
        } elseif (preg_match('/<meta property="og:image" content="([^"]*)"/i', $html, $m)) {
            $logo_url = $m[1];
        }
        
        // Rating fallback
        if (empty($rating) && preg_match('/<span[^>]*class="[^"]*rating[^"]*"[^>]*>(.*?)<\/span>/is', $html, $rMatch)) {
            $rating = trim(strip_tags($rMatch[1]));
        }

        // Maps URL
        if (preg_match('/href="([^"]*google-map-theater[^"]*)"/i', $html, $m)) {
            $maps_url = $m[1];
        }

        // Notes / Descriptions (Policies)
        if (preg_match('/<div[^>]*class="[^"]*description[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m)) {
            $notes = trim(strip_tags($m[1]));
        }

        // Breadcrumbs for Country, City, Area (very reliable on elCinema)
        if (preg_match_all('/<li[^>]*><a[^>]*>(.*?)<\/a><\/li>/is', $html, $bc)) {
            $crumbs = array_map('strip_tags', $bc[1]);
            // Usually: [Home, Theaters, Country, City, Area (optional), Theater Name]
            if (count($crumbs) >= 4) {
                 $country = trim($crumbs[2]);
                 $city = trim($crumbs[3]);
                 if (count($crumbs) >= 5 && trim($crumbs[4]) !== $cinema_name) {
                     $area = trim($crumbs[4]);
                 }
            }
        }

        // Showtime Date
        $current_date = '';
        if (preg_match('/[\?&]date=([0-9]{4}-[0-9]{1,2}-[0-9]{1,2})/', $url, $urlDateMatch)) {
            $current_date = date('Y-m-d', strtotime($urlDateMatch[1]));
        }

        if (!$current_date) {
            if (preg_match('/<li[^>]*class="active"[^>]*>.*?<a[^>]*>(.*?)<\/a>/is', $html, $activeDateMatch)) {
                $found_date_text = trim(strip_tags($activeDateMatch[1]));
                $translated = self::translateDate($found_date_text);
                if ($translated) {
                    $ts = strtotime($translated);
                    if ($ts) $current_date = date('Y-m-d', $ts);
                }
            }
        }

        if (!$current_date) {
            $current_date = date('Y-m-d');
        }

        // Showtime parsing
        $movie_pattern = '/<(?:h[123])[^>]*>.*?<a[^>]*href="[^"]*(?:\/work\/|wk)([0-9]+)[^"]*"[^>]*>(.*?)<\/a>.*?<ul[^>]*>(.*?)<\/ul>/is';
        if (preg_match_all($movie_pattern, $html, $movieMatches, PREG_SET_ORDER)) {
            foreach ($movieMatches as $mMatch) {
                $movieTitle = trim(strip_tags($mMatch[2]));
                $ulContent = $mMatch[3];
                
                $parsed_times = self::parseShowtimeBlock($ulContent);
                foreach ($parsed_times as $show) {
                    $showtimes[] = array(
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

        // Alt pattern for simpler blocks
        if (empty($showtimes)) {
             $alt_pattern = '/<(?:h[23])[^>]*>.*?<a[^>]*href="[^"]*(?:\/work\/|wk)([0-9]+)[^"]*"[^>]*>(.*?)<\/a>(.*?)((?=<h[23]|<\/body))/is';
             if (preg_match_all($alt_pattern, $html, $altMatches, PREG_SET_ORDER)) {
                 foreach ($altMatches as $aMatch) {
                     $movieTitle = trim(strip_tags($aMatch[2]));
                     $contentSuffix = $aMatch[3];
                     
                     $parsed_times = self::parseShowtimeBlock($contentSuffix);
                     foreach ($parsed_times as $show) {
                         $showtimes[] = array(
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
        
        return array(
            'metadata' => array(
                'theater_id' => $theater_id,
                'name' => $cinema_name,
                'arabic_name' => $arabic_name,
                'english_name' => $english_name,
                'logo' => $logo_url,
                'rating' => $rating,
                'address' => $address,
                'area' => $area,
                'phone' => $phone,
                'city' => $city,
                'country' => $country,
                'notes' => $notes,
                'maps_url' => $maps_url
            ),
            'showtimes' => $showtimes
        );
    }
}