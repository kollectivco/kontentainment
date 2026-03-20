<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once KTN_PLUGIN_DIR . 'elementor/base-widget.php';

class KTN_Cinemas_Widget extends KTN_Elementor_Base_Widget {

    public function get_name() {
        return 'ktn-cinemas-widget';
    }

    public function get_title() {
        return esc_html__('Kueue Cinemas', 'kontentainment');
    }

    public function get_icon() {
        return 'eicon-price-list';
    }

    protected function register_controls() {
        $this->start_controls_section('section_query', [
            'label' => esc_html__('Query & Layout', 'kontentainment'),
        ]);

        $this->add_control('source', [
            'label' => esc_html__('Source', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'latest',
            'options' => [
                'latest' => 'All Cinemas',
                'manual' => 'Manual Selection',
                'area'   => 'By Area Filter',
            ],
        ]);

        $this->add_control('manual_ids', [
            'label' => esc_html__('Cinema IDs (comma separated)', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'condition' => ['source' => 'manual'],
        ]);

        $areas = get_terms(['taxonomy' => 'cinema_area', 'hide_empty' => false]);
        $area_options = [];
        if (!is_wp_error($areas) && !empty($areas)) {
            foreach ($areas as $area) {
                $area_options[$area->slug] = $area->name;
            }
        }
        $this->add_control('area_slug', [
            'label' => esc_html__('Select Area', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $area_options,
            'condition' => ['source' => 'area'],
        ]);

        $this->add_control('posts_per_page', [
            'label' => esc_html__('Items Count', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 8,
            'condition' => ['source!' => 'manual'],
        ]);

        $skins = [
            'grid'    => 'Grid Layout',
            'list'    => 'List Layout',
            'compact' => 'Compact List',
            'overlay' => 'Overlay Card',
        ];
        $this->add_skin_control($skins);
        $this->add_columns_control();
        
        $this->end_controls_section();

        $this->start_controls_section('section_visibility', [
            'label' => esc_html__('Visibility Controls', 'kontentainment'),
        ]);

        $vis = [
            'show_logo' => 'Show Logo/Image',
            'show_address' => 'Show Address',
            'show_area' => 'Show Area Badge',
            'show_count' => 'Show Playing Movies Count',
            'show_btn' => 'Show View Button',
        ];
        foreach ($vis as $key => $lbl) {
            $this->add_control($key, [
                'label' => esc_html__($lbl, 'kontentainment'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
            ]);
        }
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        $args = [
            'post_type' => 'ktn_cinema',
            'post_status' => 'publish',
            'posts_per_page' => $settings['posts_per_page'] ? $settings['posts_per_page'] : 8,
        ];

        if ($settings['source'] === 'manual' && !empty($settings['manual_ids'])) {
            $args['post__in'] = array_map('intval', explode(',', $settings['manual_ids']));
            $args['orderby'] = 'post__in';
            $args['posts_per_page'] = -1;
        } elseif ($settings['source'] === 'area' && !empty($settings['area_slug'])) {
            $args['tax_query'] = [[
                'taxonomy' => 'cinema_area',
                'field' => 'slug',
                'terms' => $settings['area_slug']
            ]];
        }

        $query = new \WP_Query($args);
        global $wpdb;

        echo '<div class="ktn-elementor-cinemas-wrapper ktn-skin-' . esc_attr($settings['layout_skin']) . '">';
        if ($query->have_posts()) {
            echo '<div class="ktn-elementor-grid">';
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $title = get_the_title();
                $permalink = get_permalink();

                $logo = has_post_thumbnail() ? get_the_post_thumbnail_url($post_id, 'medium') : get_post_meta($post_id, '_ktn_cinema_logo', true);
                if (!$logo) $logo = get_post_meta($post_id, 'logo', true);

                $address = get_post_meta($post_id, '_ktn_cinema_address', true) ?: get_post_meta($post_id, 'address', true);
                $terms = get_the_terms($post_id, 'cinema_area');
                $area_name = ($terms && !is_wp_error($terms)) ? $terms[0]->name : '';

                $playing_count = 0;
                if ($settings['show_count'] === 'yes') {
                    $today = date('Y-m-d');
                    $playing_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT movie_title_scraped) FROM {$wpdb->prefix}ktn_showtimes WHERE cinema_id = %d AND (show_date >= %s OR show_date = 'Today')",
                        $post_id,
                        $today
                    ));
                }
                echo Ktn_Card_System::render_cinema_card($post_id, array(
                    'show_rating'   => true,
                    'show_location' => ($settings['show_area'] === 'yes'),
                    'show_address'  => ($settings['show_address'] === 'yes'),
                    'show_count'    => ($settings['show_count'] === 'yes'),
                    'movie_count'   => $playing_count,
                    'show_cta'      => ($settings['show_btn'] === 'yes')
                ));
            }
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p>No cinemas found.</p>';
        }
        echo '</div>';
    }
}
