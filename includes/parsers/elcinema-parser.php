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
        $address = ''; $area = ''; $city = ''; $country = ''; $phone = ''; $notes = ''; $maps = ''; $cover = '';

        // Name and Rating from Header
        // <h1> typically contains "Arabic Name English Name" on English pages
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $full_h1 = trim(strip_tags($m[1], '<span>'));
            
            // On English pages, elCinema often puts BOTH names in the H1
            // e.g. "سينما كايرو فيستفال Scene Cinemas Cairo Festival Scene Cinema"
            // We can try to split it if we see a clear boundary or just use it as is
            $name_clean = trim(strip_tags($full_h1));
            $name = $name_clean;
            
            if ($is_en) {
                $english_name = $name_clean;
                // Try to extract Arabic part if possible (usually at the beginning)
                if (preg_match('/^([\x{0600}-\x{06FF}\s]+)/u', $name_clean, $arM)) {
                    $arabic_name = trim($arM[1]);
                }
            } else {
                $arabic_name = $name_clean;
            }
        }

        // Rating
        if (preg_match('/class="action-button-on"[^>]*>.*?([0-9.]+).*?</is', $html, $rM)) {
            $rating = $rM[1];
        } elseif (preg_match('/([0-9.]+) (?:من|of) 10/i', $html, $rM)) {
            $rating = $rM[1];
        }

        // Details Block (Logo, Phone, Address)
        if (preg_match('/<div[^>]*class="[^"]*list-details[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m) || 
            preg_match('/<ul[^>]*class="[^"]*list-details[^"]*"[^>]*>(.*?)<\/ul>/is', $html, $m)) {
            $details = $m[1];
            if (preg_match('/src="([^"]*)"/i', $details, $logoMatch)) $logo = $logoMatch[1];
            if (preg_match('/fa-phone.*?<\/i>(.*?)</is', $details, $pMatch)) $phone = trim(strip_tags($pMatch[1]));
            if (preg_match('/fa-map-marker.*?<\/i>(.*?)</is', $details, $aMatch)) $address = trim(strip_tags($aMatch[1]));
        }

        // Location via Breadcrumbs (Country > City > Area)
        if (preg_match_all('/<li[^>]*><a[^>]*>(.*?)<\/a><\/li>/is', $html, $bc)) {
            $crumbs = array_map('trim', array_map('strip_tags', $bc[1]));
            // Usually: Home > Cinemas > Country > City > Area > Cinema Name
            if (count($crumbs) >= 4) {
                // If it's English, crumbs might be "Home", "Cinemas", "Egypt", "Cairo"...
                $country = $crumbs[2] ?? '';
                $city = $crumbs[3] ?? '';
                if (isset($crumbs[4]) && !in_array($crumbs[4], [$name, 'Theater', 'سينما', 'Cinemas'])) {
                    $area = $crumbs[4];
                }
            }
        }

        // Description / Notes
        if (preg_match('/<div[^>]*class="[^"]*description[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m)) {
            $notes = trim(strip_tags($m[1]));
        }

        // Google Maps URL
        if (preg_match('/href="([^"]*google-map-theater[^"]*)"/i', $html, $m)) {
            $maps = $m[1];
        }

        // Images (Cover)
        if (preg_match('/<div[^>]*class="[^"]*theater-image[^"]*"[^>]*>.*?src="([^"]*)"/is', $html, $imgM)) {
            $cover = $imgM[1];
        }

        // Date Detection
        $doc_date = '';
        if (preg_match('/[\?&]date=([0-9]{4}-[0-9]{1,2}-[0-9]{1,2})/', $url, $dm)) {
            $doc_date = $dm[1];
        } else {
            // Look for active date in the bar or h4.section-title
            if (preg_match('/<li[^>]*class="active"[^>]*>.*?<a[^>]*>(.*?)<\/a>/is', $html, $activeMatch)) {
                $translated = Ktn_Cinema_Scraper::translateDate($activeMatch[1]);
                $ts = strtotime($translated);
                if ($ts) $doc_date = date('Y-m-d', $ts);
            } elseif (preg_match('/<h4[^>]*class="section-title"[^>]*>(.*?)<\/h4>/is', $html, $h4Match)) {
                $translated = Ktn_Cinema_Scraper::translateDate($h4Match[1]);
                $ts = strtotime($translated);
                if ($ts) $doc_date = date('Y-m-d', $ts);
            }
        }
        if (!$doc_date) $doc_date = date('Y-m-d');

        // Showtimes Parsing
        $showtimes = array();
        
        // Split by row (generic container for movie entries)
        $rows = preg_split('/<div[^>]*class="[^"]*row[^"]*"[^>]*>/is', $html);
        array_shift($rows); // discard first part

        foreach ($rows as $row) {
            // Check if this row contains a showtimes table
            if (strpos($row, 'table') !== false && (strpos($row, 'class="showtimes"') !== false || strpos($row, 'strong') !== false)) {
                
                // Extract Movie Title
                $movie_title = '';
                if (preg_match('/<h3[^>]*>.*?<a[^>]*>(.*?)<\/a>.*?<\/h3>/is', $row, $tMatch)) {
                    $movie_title = trim(strip_tags($tMatch[1]));
                } elseif (preg_match('/<a[^>]*href="[^"]*\/work\/[^"]*"[^>]*>(.*?)<\/a>/is', $row, $tMatch)) {
                    $movie_title = trim(strip_tags($tMatch[1]));
                }

                if (empty($movie_title)) continue;

                // Detect Experience & Times
                // Each row might have multiple swiper-slides or blocks for IMAX, Standard, etc.
                // Experience titles are usually in h6 or span
                // Standard structure: <h6>Standard</h6> <table class="showtimes">...</table>
                $blocks = preg_split('/<h6[^>]*>(.*?)<\/h6>/is', $row, -1, PREG_SPLIT_DELIM_CAPTURE);
                
                if (count($blocks) > 1) {
                    for ($i = 1; $i < count($blocks); $i += 2) {
                        $exp = trim(strip_tags($blocks[$i]));
                        $block_content = $blocks[$i+1];
                        $parsed_times = Ktn_Cinema_Scraper::parseShowtimeBlock($block_content);
                        foreach($parsed_times as $st) {
                            $showtimes[] = array(
                                'movie_title' => $movie_title,
                                'show_date' => $doc_date,
                                'show_time' => $st['show_time'],
                                'experience' => $exp ?: 'Standard',
                                'price_text' => $st['price_text'],
                                'source_url' => $url,
                                'scraped_at' => $scraped_at
                            );
                        }
                    }
                } else {
                    // Fallback: No H6 headers, just parse the whole row for times
                    $parsed_times = Ktn_Cinema_Scraper::parseShowtimeBlock($row);
                    foreach($parsed_times as $st) {
                        $showtimes[] = array(
                            'movie_title' => $movie_title,
                            'show_date' => $doc_date,
                            'show_time' => $st['show_time'],
                            'experience' => (strpos($row, 'IMAX') !== false) ? 'IMAX' : 'Standard',
                            'price_text' => $st['price_text'],
                            'source_url' => $url,
                            'scraped_at' => $scraped_at
                        );
                    }
                }
            }
        }

        // Final Fallback for older/mobile structures
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
                'maps_url' => $maps,
                'cover_image' => $cover
            ),
            'showtimes' => $showtimes
        );
    }
}
