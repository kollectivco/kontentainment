<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * elCinema Parser Module for Kontentainment
 */
class Ktn_Elcinema_Parser
{
    public static function parse($html, $url, $cinema_id, $scraped_at)
    {
        $is_en = (strpos($url, '/en/') !== false);
        
        // Metadata ID
        $theater_id = '';
        if (preg_match('/\/theater\/([0-9]+)/', $url, $idMatch)) {
            $theater_id = $idMatch[1];
        }

        $name = ''; $arabic_name = ''; $english_name = ''; $logo = ''; $rating = ''; 
        $address = ''; $area = ''; $city = ''; $country = ''; $phone = ''; $notes = ''; $maps = '';

        // Name
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $name = trim(strip_tags($m[1]));
            if ($is_en) $english_name = $name; else $arabic_name = $name;
        }

        // Details Block
        if (preg_match('/<div[^>]*class="[^"]*list-details[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m) || preg_match('/<ul[^>]*class="[^"]*list-details[^"]*"[^>]*>(.*?)<\/ul>/is', $html, $m)) {
            $details = $m[1];
            if (preg_match('/src="([^"]*)"/i', $details, $logoMatch)) $logo = $logoMatch[1];
            if (preg_match('/([0-9.]+) من 10/', $details, $rAr)) $rating = $rAr[1];
            if (preg_match('/([0-9.]+) of 10/', $details, $rEn)) $rating = $rEn[1];
            if (preg_match('/fa-phone.*?<\/i>(.*?)</is', $details, $pMatch)) $phone = trim(strip_tags($pMatch[1]));
            if (preg_match('/fa-map-marker.*?<\/i>(.*?)</is', $details, $aMatch)) $address = trim(strip_tags($aMatch[1]));
        }

        // Location via Breadcrumbs
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

        if (preg_match('/<div[^>]*class="[^"]*description[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m)) $notes = trim(strip_tags($m[1]));
        if (preg_match('/href="([^"]*google-map-theater[^"]*)"/i', $html, $m)) $maps = $m[1];

        // Date
        $doc_date = '';
        if (preg_match('/[\?&]date=([0-9]{4}-[0-9]{1,2}-[0-9]{1,2})/', $url, $dm)) {
            $doc_date = $dm[1];
        } else {
            if (preg_match('/<li[^>]*class="active"[^>]*>.*?<a[^>]*>(.*?)<\/a>/is', $html, $activeMatch)) {
                $translated = Ktn_Cinema_Scraper::translateDate($activeMatch[1]);
                $ts = strtotime($translated);
                if ($ts) $doc_date = date('Y-m-d', $ts);
            }
        }
        if (!$doc_date) $doc_date = date('Y-m-d');

        // Showtimes
        $showtimes = array();
        $movie_blocks = preg_split('/<div[^>]*class="[^"]*work-title[^"]*"[^>]*>/is', $html);
        array_shift($movie_blocks);

        foreach ($movie_blocks as $block) {
            if (preg_match('/<a[^>]*>(.*?)<\/a>/is', $block, $tMatch)) {
                $movie_title = trim(strip_tags($tMatch[1]));
                $parsed_times = Ktn_Cinema_Scraper::parseShowtimeBlock($block);
                foreach($parsed_times as $st) {
                    $showtimes[] = array(
                        'movie_title' => $movie_title,
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

        if (empty($showtimes)) {
            $movie_table_pattern = '/<(?:h[23]).*?wk([0-9]+).*?>(.*?)<\/a>.*?<ul[^>]*>(.*?)<\/ul>/is';
            if (preg_match_all($movie_table_pattern, $html, $mMatches, PREG_SET_ORDER)) {
                foreach ($mMatches as $mm) {
                    $movie_title = trim(strip_tags($mm[2]));
                    $parsed_times = Ktn_Cinema_Scraper::parseShowtimeBlock($mm[3]);
                    foreach($parsed_times as $st) {
                        $showtimes[] = array(
                            'movie_title' => $movie_title,
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
