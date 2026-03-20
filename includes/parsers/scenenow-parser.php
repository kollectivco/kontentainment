<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SceneNow Parser Module for Kontentainment
 */
class Ktn_Scenenow_Parser
{
    public static function parse($html, $url, $cinema_id, $scraped_at)
    {
        $name = ''; $logo = ''; $address = ''; $city = ''; $area = ''; $notes = ''; $rating = '';

        // Cinema Name from H1
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $name = trim(strip_tags($m[1]));
        }

        // Logo
        if (preg_match('/<img[^>]*class="[^"]*cinema-logo[^"]*"[^>]*src="([^"]*)"/i', $html, $m)) {
            $logo = $m[1];
        } elseif (preg_match('/<div[^>]*class="[^"]*image-container[^"]*"[^>]*>.*?<img[^>]*src="([^"]*)"/is', $html, $m)) {
            $logo = $m[1];
        }

        // Location Info
        if (preg_match('/<div[^>]*class="[^"]*location-info[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m) || preg_match('/fa-map-marker.*?<\/i>(.*?)</is', $html, $m)) {
            $loc_text = trim(strip_tags($m[1]));
            $address = $loc_text;
            
            // Try to split location for City/Area (SceneNow often uses: "Area - City")
            if (strpos($loc_text, '-') !== false) {
                $parts = array_map('trim', explode('-', $loc_text));
                if (count($parts) >= 2) {
                    $area = $parts[0];
                    $city = $parts[count($parts)-1];
                }
            }
        }

        // Notes
        if (preg_match('/<p[^>]*class="[^"]*cinema-notes[^"]*"[^>]*>(.*?)<\/p>/is', $html, $m)) {
            $notes = trim(strip_tags($m[1]));
        }

        // Current Date
        $doc_date = date('Y-m-d');
        if (preg_match('/<select[^>]*id="area"[^>]*>.*?<option[^>]*selected[^>]*value="([^"]*)"/is', $html, $m)) {
             $selected_date = trim(Ktn_Cinema_Scraper::translateDate($m[1]));
             $ts = strtotime($selected_date);
             if ($ts) $doc_date = date('Y-m-d', $ts);
        }

        // Movie Blocks
        $showtimes = array();
        // Assuming movie containers have specific class or structure
        $movie_blocks = preg_split('/<div[^>]*class="[^"]*(?:movie-item|movie-box|list-movie-item)[^"]*"[^>]*>/is', $html);
        array_shift($movie_blocks);

        foreach ($movie_blocks as $block) {
            // Title
            if (preg_match('/<a[^>]*class="[^"]*black-color[^"]*"[^>]*>(.*?)<\/a>/is', $block, $tMatch) || preg_match('/<h[23][^>]*>(.*?)<\/h[23]>/is', $block, $tMatch)) {
                $movie_title = trim(strip_tags($tMatch[1]));
                
                // Experience detection (Look for [3D], [VIP], etc)
                $experience = 'Standard';
                if (preg_match('/\[(3D|4DX|VIP|IMAX)\]/i', $block, $expMatch)) {
                    $experience = $expMatch[1];
                }

                // Times
                // Look for strings like 10:30 AM/PM or 22:30
                if (preg_match_all('/([0-9]{1,2}:[0-9]{2})\s*(?:AM|PM|am|pm)?/i', $block, $timeMatches)) {
                    foreach ($timeMatches[0] as $timeStr) {
                         $norm_time = Ktn_Cinema_Scraper::translateAmPm($timeStr);
                         $showtimes[] = array(
                             'movie_title' => $movie_title,
                             'show_date' => $doc_date,
                             'show_time' => $norm_time,
                             'experience' => $experience,
                             'price_text' => '',
                             'source_url' => $url,
                             'scraped_at' => $scraped_at
                         );
                    }
                }
            }
        }

        return array(
            'metadata' => array(
                'name' => $name,
                'logo' => $logo,
                'address' => $address,
                'area' => $area,
                'city' => $city,
                'notes' => $notes,
                'rating' => $rating
            ),
            'showtimes' => $showtimes
        );
    }
}
