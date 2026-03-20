<?php
if (!defined('ABSPATH')) {
    exit;
}

// Load individual parsers
require_once KTN_PLUGIN_DIR . 'includes/parsers/elcinema-parser.php';

class Ktn_Cinema_Scraper
{

    private static $DAY_MAP = array(
        'السبت' => 'Saturday', 'الأحد' => 'Sunday', 'الاحد' => 'Sunday',
        'الإثنين' => 'Monday', 'الاثنين' => 'Monday', 'الثلاثاء' => 'Tuesday',
        'الأربعاء' => 'Wednesday', 'الاربعاء' => 'Wednesday', 'الخميس' => 'Thursday',
        'الجمعة' => 'Friday', 'الجمعه' => 'Friday'
    );

    private static $MONTH_MAP = array(
        'يناير' => 'January', 'فبراير' => 'February', 'مارس' => 'March',
        'أبريل' => 'April', 'ابريل' => 'April', 'إبريل' => 'April',
        'مايو' => 'May', 'يونيو' => 'June', 'يونيه' => 'June',
        'يوليو' => 'July', 'يوليه' => 'July', 'أغسطس' => 'August',
        'اغسطس' => 'August', 'سبتمبر' => 'September', 'أكتوبر' => 'October',
        'اكتوبر' => 'October', 'نوفمبر' => 'November', 'ديسمبر' => 'December'
    );

    private static $ARABIC_NUMERALS = array(
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'
    );

    public static function normalizeArabicDigits($str) {
        if (!$str) return '';
        return strtr($str, self::$ARABIC_NUMERALS);
    }

    public static function normalizeWhitespace($str) {
        if (!$str) return '';
        return trim(preg_replace('/\s+/', ' ', $str));
    }

    public static function translateDate($dateStr) {
        if (!$dateStr) return '';
        $engDate = self::normalizeWhitespace($dateStr);
        $engDate = str_ireplace(array('يعرض حاليًا', 'يعرض حاليا', 'Now Playing'), '', $engDate);
        $engDate = trim($engDate);

        foreach (self::$DAY_MAP as $arDay => $enDay) $engDate = str_replace($arDay, $enDay, $engDate);
        foreach (self::$MONTH_MAP as $arMonth => $enMonth) $engDate = str_replace($arMonth, $enMonth, $engDate);
        return $engDate;
    }

    public static function translateAmPm($timeStr) {
        if (!$timeStr) return '';
        $t = $timeStr;
        // Normalize Arabic indicators
        $t = preg_replace('/ص(?:باحا|باحاً|باحا)?/u', 'AM', $t);
        $t = preg_replace('/ظ(?:هرا|هراً)?/u', 'PM', $t);
        $t = preg_replace('/م(?:ساء|ساءا|ساءً|ساء)?/u', 'PM', $t);
        $t = str_replace('ج.م', 'EGP', $t);
        // Map common phrases
        $t = str_ireplace(['am', 'pm'], ['AM', 'PM'], $t);
        return self::normalizeWhitespace($t);
    }

    public static function parseShowtimeBlock($blockText) {
        $norm = self::normalizeArabicDigits($blockText);
        // Robust Regex for Time + AMPM + Optional Price
        $regex = '/([0-9]{1,2}:[0-9]{2})\s*([صمظ]|[AP]M| صباحًا| مساءً)\s*(?:.*?([0-9]+\s*(?:ج\.م|EGP|LE|L\.E)))?/iu';
        
        $showtimes = array();
        if (preg_match_all($regex, $norm, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $time = $match[1];
                $ampm = self::translateAmPm($match[2]);
                $price = isset($match[3]) ? trim(str_replace(['ج.م', 'ج.PM', 'LE', 'L.E'], 'EGP', self::translateAmPm($match[3]))) : '';
                
                $showtimes[] = array(
                    'experience' => (strpos($blockText, '3D') !== false) ? '3D' : (strpos($blockText, 'MX4D') !== false ? 'MX4D' : 'Standard'),
                    'show_time' => trim("$time $ampm"),
                    'price_text' => $price
                );
            }
        }
        return $showtimes;
    }

    public static function fetch_from_source($cinema_id, $url, $source_type = 'elcinema_theater')
    {
        $args = array(
            'headers' => array('User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'),
            'timeout' => 45,
            'sslverify' => false
        );

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) return $response;
        if (wp_remote_retrieve_response_code($response) !== 200) return new WP_Error('http_error', 'HTTP ' . wp_remote_retrieve_response_code($response));

        $html = wp_remote_retrieve_body($response);
        if (!$html) return new WP_Error('empty_body', 'Empty body from source');

        $scraped_at = current_time('mysql');
        $results = array();
        $metadata = array();

        // Detect correct parser
        if ($source_type === 'elcinema_theater' || strpos($url, 'elcinema.com') !== false) {
             $initial = Ktn_Elcinema_Parser::parse($html, $url, $cinema_id, $scraped_at);
             $results = $initial['showtimes']; $metadata = $initial['metadata'];
             
             // Handle Multi-date for elCinema
             $all_dates = array();
             
             // Method A: Link patterns with ?date=
             if (preg_match_all('/href="([^"]*[\?&]date=([0-9]{4}-[0-9]{1,2}-[0-9]{1,2}))"/i', $html, $matches)) {
                 $all_dates = array_unique($matches[1]);
             }
             
             // Method B: Select options in #theater-showtimes-date-selector
             if (preg_match_all('/<option[^>]*value="([0-9]{4}-[0-9]{1,2}-[0-9]{1,2})"[^>]*>/i', $html, $mOpts)) {
                  foreach ($mOpts[1] as $d) {
                       $all_dates[] = $url . (strpos($url, '?') !== false ? '&' : '?') . 'date=' . $d;
                  }
             }

             if (!empty($all_dates)) {
                 foreach (array_unique(array_slice($all_dates, 0, 7)) as $rel_url) {
                     $date_url = (strpos($rel_url, 'http') === 0) ? $rel_url : 'https://elcinema.com' . (strpos($rel_url, '/') === 0 ? '' : '/') . $rel_url;
                     
                     // Ensure English URLs remain English if needed
                     if (strpos($url, '/en/') !== false && strpos($date_url, '/en/') === false) {
                          $date_url = str_replace('elcinema.com/', 'elcinema.com/en/', $date_url);
                     }
                     
                     if (rtrim($date_url, '/') === rtrim($url, '/')) continue;
                     
                     usleep(300000); 
                     $d_res = wp_remote_get($date_url, $args);
                     if (!is_wp_error($d_res)) {
                         $d_body = wp_remote_retrieve_body($d_res);
                         if (!empty($d_body)) {
                              $d_data = Ktn_Elcinema_Parser::parse($d_body, $date_url, $cinema_id, $scraped_at);
                              if (!empty($d_data['showtimes'])) {
                                   $results = array_merge($results, $d_data['showtimes']);
                              }
                         }
                     }
                 }
             }
        } else {
             return new WP_Error('invalid_source', 'Unsupported source type (only elCinema Theater is supported).');
        }

        // De-duplicate
        $final_results = array(); $seen = array();
        foreach($results as $res) {
            $key = md5(($res['movie_title'] ?? '') . ($res['show_date'] ?? '') . ($res['show_time'] ?? '') . ($res['experience'] ?? ''));
            if (!isset($seen[$key])) { $final_results[] = $res; $seen[$key] = true; }
        }

        if (empty($final_results) && empty($metadata['name'])) return new WP_Error('no_data', 'No data extracted from source.');
        
        return array('metadata' => $metadata, 'showtimes' => $final_results);
    }
}