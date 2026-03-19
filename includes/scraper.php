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
        $timeExpRegex = '/(?:(Standard|VIP|3D|IMAX|4DX|Premium)[^\d]{0,20})?(?:.*?)([0-9]{1,2}:[0-9]{2})\s*([صمظ][^\s\d<]{0,8})\s*(?:.*?([0-9]+\s*(?:ج\.م|EGP)))?/u';

        $showtimes = array();

        if (preg_match_all($timeExpRegex, $norm, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $experience = !empty($match[1]) ? $match[1] : 'Standard';
                if (strpos($norm, '3D') !== false && $experience === 'Standard') {
                    $experience = '3D';
                }

                $time = $match[2];
                $ampmAr = $match[3];
                $priceAr = isset($match[4]) ? $match[4] : '';

                $ampm = self::translateAmPm($ampmAr);
                $priceText = $priceAr ? trim(str_replace(array('ج.م', 'ج.PM'), 'EGP', self::translateAmPm($priceAr))) : '';

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
        $date_urls = array_slice($date_urls, 0, 10);

        foreach ($date_urls as $date_url) {
            usleep(500000);
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

        if (empty($final_results)) {
            $msg = 'Parsing Error: No showtimes detected.';
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

        // Extract H1 name
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $scraped_name = trim(strip_tags($m[1]));
            if ($is_en) {
                $english_name = $scraped_name;
                $cinema_name = $english_name;
            } else {
                $arabic_name = $scraped_name;
                $cinema_name = $arabic_name;
            }
        }

        // Search for alternate language link to grab the other name
        if ($is_en) {
            if (preg_match('/<li><a[^>]*href="\/theater\/[0-9]+\/"[^>]*>(.*?)<\/a><\/li>/u', $html, $arLink)) {
                // This is usually just "عربي", not the name. 
            }
        }

        // Try to get Arabic name from breadcrumbs or hidden meta if on EN page
        if ($is_en && empty($arabic_name)) {
             if (preg_match('/<meta property="og:title" content="(.*?) - Theater - Cinema"/i', $html, $ogMatch)) {
                  // Sometimes og:title has info, but usually same as H1
             }
        }

        // Logo / main image URL
        if (preg_match('/<img[^>]*class="[^"]*cinema-logo[^"]*"[^>]*src="([^"]*)"/i', $html, $m)) {
            $logo_url = $m[1];
        } elseif (preg_match('/<div[^>]*class="[^"]*cinema-image[^"]*"[^>]*>.*?<img[^>]*src="([^"]*)"/is', $html, $m)) {
            $logo_url = $m[1];
        }
        
        // og:image fallback
        if (empty($logo_url) || strpos($logo_url, 'default') !== false) {
            if (preg_match('/<meta property="og:image" content="([^"]*)"/i', $html, $ogImg)) {
                $logo_url = $ogImg[1];
            }
        }

        // Rating
        if (preg_match('/<span[^>]*class="[^"]*rating[^"]*"[^>]*>(.*?)<\/span>/is', $html, $rMatch)) {
            $rating = trim(strip_tags($rMatch[1]));
        }

        // Extract Details List (Address, Phone, Area, etc)
        if (preg_match('/<ul[^>]*class="[^"]*list-details[^"]*"[^>]*>(.*?)<\/ul>/is', $html, $m)) {
            $details_html = $m[1];
            
            // Address
            if (preg_match('/(?:العنوان|Address):?<\/span>\s*(.*?)<\/li>/is', $details_html, $ad)) {
                $address = trim(strip_tags($ad[1]));
            }
            
            // Phone
            if (preg_match('/(?:الهاتف|Phone):?<\/span>\s*(.*?)<\/li>/is', $details_html, $ph)) {
                $phone = trim(strip_tags($ph[1]));
            }

            // More specific fields if they exist
            if (preg_match('/(?:المنطقة|Area):?<\/span>\s*(.*?)<\/li>/is', $details_html, $ar)) {
                $area = trim(strip_tags($ar[1]));
            }
        }

        // Notes / Descriptions (Policies)
        if (preg_match('/<div[^>]*class="[^"]*description[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m)) {
            $notes = trim(strip_tags($m[1]));
        }

        // Breadcrumbs for Country, City, Area
        if (preg_match_all('/<li[^>]*><a[^>]*>(.*?)<\/a><\/li>/is', $html, $bc)) {
            $crumbs = array_map('strip_tags', $bc[1]);
            // Format: [Home, Theaters, Country, City, Area (optional), Theater Name]
            if (count($crumbs) >= 4) {
                 $country = trim($crumbs[2]);
                 $city = trim($crumbs[3]);
                 if (count($crumbs) >= 5 && trim($crumbs[4]) !== $cinema_name) {
                     $area = trim($crumbs[4]);
                 }
            }
        }

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

        $movie_pattern = '/<(?:h2|h3)[^>]*>.*?<a[^>]*href="[^"]*(?:\/work\/|wk)([0-9]+)[^"]*"[^>]*>(.*?)<\/a>.*?<ul[^>]*>(.*?)<\/ul>/is';
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

        if (empty($showtimes)) {
             $alt_pattern = '/<(?:h2|h3)[^>]*>.*?<a[^>]*href="[^"]*(?:\/work\/|wk)([0-9]+)[^"]*"[^>]*>(.*?)<\/a>(.*?)((?=<h2|<h3|<\/body))/is';
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
                'notes' => $notes
            ),
            'showtimes' => $showtimes
        );
    }
}