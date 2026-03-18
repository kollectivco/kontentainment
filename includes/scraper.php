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
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ),
            'timeout' => 15,
            'sslverify' => false
        );
        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $html = wp_remote_retrieve_body($response);
        if (!$html) {
            return false;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . ltrim(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')));
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $results = array();
        $scraped_at = current_time('mysql');

        // Extract Cinema Name from CPT if empty or use scraped
        $cinema_name_node = $xpath->query('//h1 | //h2')->item(0);
        $cinema_name = $cinema_name_node ? trim($cinema_name_node->nodeValue) : '';

        if (!$cinema_name) {
            $panel_a = $xpath->query('//div[contains(@class, "panel")]//a[contains(@class, "unstyled")]')->item(0);
            if ($panel_a) {
                $cinema_name = trim($panel_a->nodeValue);
            }
        }

        $current_date = '';

        $rows = $xpath->query('//div[contains(@class, "row")]');
        foreach ($rows as $row) {
            $h2TitleNodes = $xpath->query('.//h2[contains(@class, "section-title")]', $row);
            if ($h2TitleNodes->length > 0) {
                $h2Title = trim($h2TitleNodes->item(0)->nodeValue);
                if ($h2Title) {
                    $current_date = self::translateDate($h2Title);
                }
            }

            $movieTags = $xpath->query('.//h3//a[contains(@href, "/work/")] | .//h2//a[contains(@href, "/work/")]', $row);
            $isMovieRow = $movieTags->length > 0;

            if ($isMovieRow) {
                $movieTitle = trim($movieTags->item(0)->nodeValue);

                $ulTags = $xpath->query('.//ul | .//*[contains(@class, "unstyled")]', $row);
                foreach ($ulTags as $ul) {
                    $listText = trim($ul->nodeValue);
                    $normText = self::normalizeArabicDigits($listText);
                    if ($listText && preg_match('/\d+:\d+/', $normText)) {
                        $showtimes = self::parseShowtimeBlock($listText);

                        foreach ($showtimes as $show) {
                            $results[] = array(
                                'cinema_id' => $cinema_id,
                                'cinema_name' => $cinema_name,
                                'source_url' => $url,
                                'movie_title_scraped' => $movieTitle,
                                'show_date' => $current_date ? $current_date : 'Today',
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

        return $results;
    }
}