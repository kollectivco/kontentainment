<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once KTN_PLUGIN_DIR . 'elementor/base-widget.php';

class KTN_Movie_Single_Widget extends KTN_Elementor_Base_Widget {

    public function get_name() {
        return 'ktn-movie-single-widget';
    }

    public function get_title() {
        return esc_html__('Kueue Movie Single Data', 'kontentainment');
    }

    public function get_icon() {
        return 'eicon-document-file';
    }

    protected function register_controls() {
        $this->start_controls_section('section_query', [
            'label' => esc_html__('Display Context', 'kontentainment'),
        ]);

        $this->add_control('context', [
            'label' => esc_html__('Source', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'current',
            'options' => [
                'current' => 'Current Movie (from URL)',
                'manual'  => 'Manually Selected Movie',
            ],
        ]);

        $this->add_control('movie_id', [
            'label' => esc_html__('Movie ID', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'condition' => ['context' => 'manual'],
        ]);

        $this->add_control('display_block', [
            'label' => esc_html__('Display Block', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'hero',
            'options' => [
                'hero'     => 'Movie Hero (Backdrop & Title)',
                'meta'     => 'Movie Meta Data (Rating, Date, runtime)',
                'overview' => 'Overview & Tagline',
                'cast'     => 'Movie Cast Grid',
                'trailer'  => 'Trailer Embedded',
                'info'     => 'Side Info Panel (Director, Certification, Language)',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        $post_id = ($settings['context'] === 'manual' && $settings['movie_id']) ? intval($settings['movie_id']) : get_the_ID();

        if (!$post_id || get_post_type($post_id) !== 'movie') {
            echo '<div class="ktn-elem-notice">Movie data not found. Please ensure context is correct.</div>';
            return;
        }

        $block = $settings['display_block'];
        
        echo '<div class="ktn-movie-single-elem ktn-block-' . esc_attr($block) . '">';
        
        if ($block === 'hero') {
            $backdrop_path = get_post_meta($post_id, '_movie_backdrop_path', true);
            $backdrop_url = $backdrop_path ? "https://image.tmdb.org/t/p/original" . $backdrop_path : '';
            $title = get_the_title($post_id);
            $poster_path = get_post_meta($post_id, '_movie_poster_path', true);
            $poster_url = $poster_path ? "https://image.tmdb.org/t/p/w500" . $poster_path : get_the_post_thumbnail_url($post_id, 'medium');

            echo '<div class="ktn-hero">';
            if ($backdrop_url) echo '<div class="ktn-hero-bg" style="background-image:url(' . esc_url($backdrop_url) . ');"></div>';
            echo '<div class="ktn-hero-content">';
            if ($poster_url) echo '<div class="ktn-hero-poster"><img src="' . esc_url($poster_url) . '" alt=""></div>';
            echo '<h1 class="ktn-hero-title">' . esc_html($title) . '</h1>';
            echo '</div>';
            echo '</div>';
            
        } elseif ($block === 'meta') {
            $rating = get_post_meta($post_id, '_movie_vote_average', true);
            $release = get_post_meta($post_id, '_movie_release_date', true);
            $runtime = get_post_meta($post_id, '_movie_runtime', true);
            echo '<div class="ktn-meta-row">';
            if ($rating) echo '<span class="ktn-meta-badge"><i class="fa fa-star"></i> ' . esc_html(round($rating, 1)) . '</span>';
            if ($runtime) echo '<span class="ktn-meta-text"><i class="fa fa-clock-o"></i> ' . esc_html($runtime) . ' min</span>';
            if ($release) echo '<span class="ktn-meta-text"><i class="fa fa-calendar"></i> ' . esc_html($release) . '</span>';
            echo '</div>';
            
        } elseif ($block === 'overview') {
            $tagline = get_post_meta($post_id, '_movie_tagline', true);
            $overview = get_post_meta($post_id, '_movie_overview', true);
            if ($tagline) echo '<h3 class="ktn-tagline">' . esc_html($tagline) . '</h3>';
            if ($overview) echo '<p class="ktn-overview-text">' . esc_html($overview) . '</p>';
            
        } elseif ($block === 'cast') {
            $cast = get_post_meta($post_id, '_movie_cast', true);
            if (!empty($cast) && is_array($cast)) {
                echo '<div class="ktn-cast-grid">';
                $count = 0;
                foreach ($cast as $member) {
                    if ($count >= 12) break;
                    $pfp = $member['profile_path'] ? "https://image.tmdb.org/t/p/w185" . $member['profile_path'] : KTN_PLUGIN_URL . 'assets/images/user-placeholder.png';
                    echo '<div class="ktn-cast-card">';
                    echo '<img src="' . esc_url($pfp) . '" alt="' . esc_attr($member['name']) . '" class="ktn-cast-img">';
                    echo '<div class="ktn-cast-info">';
                    echo '<span class="ktn-cast-name">' . esc_html($member['name']) . '</span>';
                    echo '<span class="ktn-cast-role">' . esc_html($member['character']) . '</span>';
                    echo '</div></div>';
                    $count++;
                }
                echo '</div>';
            } else {
                echo '<p>No cast information available.</p>';
            }
        } elseif ($block === 'trailer') {
            $trailers = get_post_meta($post_id, '_movie_trailers', true);
            if (!empty($trailers) && is_array($trailers)) {
                $yt_key = '';
                foreach ($trailers as $t) {
                    if ($t['site'] === 'YouTube' && $t['type'] === 'Trailer') {
                        $yt_key = $t['key'];
                        break;
                    }
                }
                if ($yt_key) {
                    echo '<div class="ktn-trailer-wrapper">';
                    echo '<iframe width="100%" height="400" src="https://www.youtube.com/embed/' . esc_attr($yt_key) . '" frameborder="0" allowfullscreen></iframe>';
                    echo '</div>';
                } else {
                    echo '<p>No YouTube trailer found.</p>';
                }
            } else {
                echo '<p>No trailer available.</p>';
            }
        } elseif ($block === 'info') {
            $director = get_post_meta($post_id, '_movie_director', true);
            $lang = get_post_meta($post_id, '_movie_original_language', true);
            $cert = get_post_meta($post_id, '_movie_certification', true);
            echo '<ul class="ktn-info-panel-list">';
            if ($director) echo '<li><strong>Director:</strong> ' . esc_html($director) . '</li>';
            if ($cert) echo '<li><strong>Certification:</strong> <span class="ktn-badge-outline">' . esc_html($cert) . '</span></li>';
            if ($lang) echo '<li><strong>Language:</strong> ' . esc_html(strtoupper($lang)) . '</li>';
            echo '</ul>';
        }

        echo '</div>';
    }
}
