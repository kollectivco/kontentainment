<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cairo360 Parser Module for Kontentainment
 */
class Ktn_Cairo360_Parser
{
    public static function parse($html, $url, $cinema_id, $scraped_at)
    {
        $name = ''; $logo = ''; $address = ''; $city = 'Cairo'; $area = ''; $phone = ''; $notes = '';

        // Cinema Name from H1
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $name = trim(strip_tags($m[1]));
        }

        // Logo / Image
        if (preg_match('/<div[^>]*class="[^"]*venue-image[^"]*"[^>]*>.*?<img[^>]*src="([^"]*)"/is', $html, $m)) {
            $logo = $m[1];
        } elseif (preg_match('/<meta property="og:image" content="([^"]*)"/i', $html, $m)) {
            $logo = $m[1];
        }

        // Phone
        if (preg_match('/href="tel:([^"]*)"/i', $html, $m)) {
            $phone = trim($m[1]);
        }

        // Address
        if (preg_match('/<div[^>]*class="[^"]*venue-address[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m) || preg_match('/fa-map-marker.*?<\/i>(.*?)</is', $html, $m)) {
            $address = trim(strip_tags($m[1]));
            // Area check
            if (preg_match('/District:? (.*?)(?:,|$)/i', $address, $dMatch)) {
                 $area = trim($dMatch[1]);
            }
        }

        // Notes
        if (preg_match('/<div[^>]*id="[^"]*venue-content[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m)) {
            $notes = trim(strip_tags($m[1]));
        }

        // Date selection (Cairo360 venues often show today or static)
        $doc_date = date('Y-m-d');
        if (preg_match('/Showing Times for (.*?)(?:<|$)/i', $html, $dateMatch)) {
             $extracted_date = Ktn_Cinema_Scraper::translateDate($dateMatch[1]);
             $ts = strtotime($extracted_date);
             if ($ts) $doc_date = date('Y-m-d', $ts);
        }

        // Showtimes
        $showtimes = array();
        // Look for movie entries which might be in boxes or lists
        // Common pattern for Cairo360 movies: <h3><a ...>Movie Title</a></h3>
        $movie_blocks = preg_split('/<h3[^>]*>(.*?)<\/h3>/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        array_shift($movie_blocks);

        // Even indexes are movie titles, odd indexes are the content following that movie
        for($i=0; $i < count($movie_blocks); $i += 2) {
             $movie_title = trim(strip_tags($movie_blocks[$i]));
             $block_content = isset($movie_blocks[$i+1]) ? $movie_blocks[$i+1] : '';
             
             // In block_content, look for times like 10:30 AM, or a list of times
             if (preg_match_all('/([0-9]{1,2}:[0-9]{2})\s*(?:AM|PM|am|pm)?/i', $block_content, $timeMatches)) {
                  foreach($timeMatches[0] as $timeStr) {
                       $norm_time = Ktn_Cinema_Scraper::translateAmPm($timeStr);
                       $showtimes[] = array(
                           'movie_title' => $movie_title,
                           'show_date' => $doc_date,
                           'show_time' => $norm_time,
                           'experience' => 'Standard',
                           'price_text' => '',
                           'source_url' => $url,
                           'scraped_at' => $scraped_at
                       );
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
                'phone' => $phone,
                'notes' => $notes
            ),
            'showtimes' => $showtimes
        );
    }
}
