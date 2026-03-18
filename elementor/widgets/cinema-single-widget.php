<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once KTN_PLUGIN_DIR . 'elementor/base-widget.php';

class KTN_Cinema_Single_Widget extends KTN_Elementor_Base_Widget {

    public function get_name() {
        return 'ktn-cinema-single-widget';
    }

    public function get_title() {
        return esc_html__('Kueue Cinema Single Data', 'kontentainment');
    }

    public function get_icon() {
        return 'eicon-price-list';
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
                'current' => 'Current Cinema (from URL)',
                'manual'  => 'Manually Selected Cinema',
            ],
        ]);

        $this->add_control('cinema_id', [
            'label' => esc_html__('Cinema ID', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'condition' => ['context' => 'manual'],
        ]);

        $this->add_control('display_block', [
            'label' => esc_html__('Display Block', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'hero',
            'options' => [
                'hero'     => 'Cinema Hero Block (Logo & Title)',
                'info'     => 'Cinema Meta (Location, Area, Contact)',
                'notes'    => 'Cinema Policies/Notes',
                'stats'    => 'Cinema Stats (Playing Count)',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        $post_id = ($settings['context'] === 'manual' && $settings['cinema_id']) ? intval($settings['cinema_id']) : get_the_ID();

        if (!$post_id || get_post_type($post_id) !== 'ktn_cinema') {
            echo '<div class="ktn-elem-notice">Cinema data not found. Please ensure context is correct.</div>';
            return;
        }

        $block = $settings['display_block'];
        echo '<div class="ktn-cinema-single-elem ktn-block-' . esc_attr($block) . '">';
        
        if ($block === 'hero') {
            $logo = has_post_thumbnail($post_id) ? get_the_post_thumbnail_url($post_id, 'full') : get_post_meta($post_id, '_ktn_cinema_logo', true);
            if (!$logo) $logo = get_post_meta($post_id, 'logo', true);
            $title = get_the_title($post_id);

            echo '<div class="ktn-hero">';
            echo '<div class="ktn-hero-content">';
            if ($logo) echo '<div class="ktn-hero-poster"><img src="' . esc_url($logo) . '" alt="' . esc_attr($title) . '"></div>';
            echo '<h1 class="ktn-hero-title">' . esc_html($title) . '</h1>';
            echo '</div>';
            echo '</div>';
            
        } elseif ($block === 'info') {
            $address = get_post_meta($post_id, '_ktn_cinema_address', true) ?: get_post_meta($post_id, 'address', true);
            $terms = get_the_terms($post_id, 'cinema_area');
            $area_name = ($terms && !is_wp_error($terms)) ? $terms[0]->name : '';
            
            echo '<ul class="ktn-info-panel-list">';
            if ($address) echo '<li><i class="fa fa-map-marker"></i> ' . esc_html($address) . '</li>';
            if ($area_name) echo '<li><i class="fa fa-map"></i> Area: <a href="' . esc_url(get_term_link($terms[0])) . '">' . esc_html($area_name) . '</a></li>';
            echo '</ul>';
            if ($address) {
                echo '<a href="https://maps.google.com/?q=' . urlencode($address) . '" target="_blank" class="ktn-btn-secondary"><i class="fa fa-external-link"></i> Get Directions</a>';
            }
            
        } elseif ($block === 'notes') {
            $content = get_post_field('post_content', $post_id);
            if (!empty($content)) {
                echo '<div class="ktn-notes">';
                echo '<h3>Cinema Information</h3>';
                echo apply_filters('the_content', $content);
                echo '</div>';
            }
        } elseif ($block === 'stats') {
            global $wpdb;
            $today = date('Y-m-d');
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT movie_title_scraped) FROM {$wpdb->prefix}ktn_showtimes WHERE cinema_id = %d AND (show_date >= %s OR show_date = 'Today')",
                $post_id,
                $today
            ));
            if ($count > 0) {
                echo '<div class="ktn-stats-box">';
                echo '<span class="ktn-stats-number">' . intval($count) . '</span>';
                echo '<span class="ktn-stats-label">Movies Now Playing</span>';
                echo '</div>';
            } else {
                echo '<p>No movies playing right now.</p>';
            }
        }

        echo '</div>';
    }
}
