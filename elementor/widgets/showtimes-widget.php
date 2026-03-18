<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once KTN_PLUGIN_DIR . 'elementor/base-widget.php';

class KTN_Showtimes_Widget extends KTN_Elementor_Base_Widget {

    public function get_name() {
        return 'ktn-showtimes-widget';
    }

    public function get_title() {
        return esc_html__('Kueue Showtimes', 'kontentainment');
    }

    public function get_icon() {
        return 'eicon-time-line';
    }

    protected function register_controls() {
        $this->start_controls_section('section_query', [
            'label' => esc_html__('Showtimes Context', 'kontentainment'),
        ]);

        $this->add_control('context_mode', [
            'label' => esc_html__('Context Mode', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'current_movie',
            'options' => [
                'current_movie'   => 'Current Movie',
                'selected_movie'  => 'Selected Movie',
                'current_cinema'  => 'Current Cinema',
                'selected_cinema' => 'Selected Cinema',
            ],
        ]);

        $this->add_control('selected_movie_id', [
            'label' => esc_html__('Movie ID', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'condition' => ['context_mode' => 'selected_movie'],
        ]);

        $this->add_control('selected_cinema_id', [
            'label' => esc_html__('Cinema ID', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'condition' => ['context_mode' => 'selected_cinema'],
        ]);

        $skins = [
            'minimal'  => 'Minimal List',
            'chips'    => 'Chips Grid',
            'cards'    => 'Showtime Cards',
            'timeline' => 'Timeline View',
        ];
        $this->add_skin_control($skins);
        
        $this->end_controls_section();

        $this->start_controls_section('section_visibility', [
            'label' => esc_html__('Visibility Controls', 'kontentainment'),
        ]);

        $vis = [
            'show_tabs' => 'Show Date Tabs',
            'show_names' => 'Show Cinema/Movie Names',
            'show_price' => 'Show Ticket Price',
            'show_experience' => 'Show Experience (e.g. IMAX)',
            'show_location' => 'Show Location Meta (Cinemas only)',
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
        global $wpdb;
        $table_showtimes = $wpdb->prefix . 'ktn_showtimes';

        $mode = $settings['context_mode'];
        $post_id = 0;

        if ($mode === 'current_movie' || $mode === 'current_cinema') {
            $post_id = get_the_ID();
        } elseif ($mode === 'selected_movie') {
            $post_id = intval($settings['selected_movie_id']);
            $mode = 'current_movie';
        } elseif ($mode === 'selected_cinema') {
            $post_id = intval($settings['selected_cinema_id']);
            $mode = 'current_cinema';
        }

        if (!$post_id) {
            echo '<p>No context available for showtimes.</p>';
            return;
        }

        $today = date('Y-m-d');
        if ($mode === 'current_movie') {
            $showtimes = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_showtimes 
                 WHERE matched_movie_id = %d 
                 AND (show_date >= %s OR show_date = 'Today')
                 ORDER BY show_date ASC, cinema_name ASC, show_time ASC",
                $post_id, $today
            ));
        } else {
            $showtimes = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_showtimes 
                 WHERE cinema_id = %d 
                 AND (show_date >= %s OR show_date = 'Today')
                 ORDER BY show_date ASC, movie_title_scraped ASC, show_time ASC",
                $post_id, $today
            ));
        }

        if (empty($showtimes) || is_wp_error($showtimes)) {
            echo '<div class="ktn-elem-empty-showtimes"><i class="fa fa-calendar-times-o"></i> No showtimes available.</div>';
            return;
        }

        $grouped_by_date = [];
        foreach ($showtimes as $st) {
            $date_key = $st->show_date === 'Today' ? date('Y-m-d') : $st->show_date;
            $group_key = ($mode === 'current_movie') ? $st->cinema_name : $st->movie_title_scraped;
            $grouped_by_date[$date_key][$group_key][] = $st;
        }
        ksort($grouped_by_date);
        $unique_dates = array_keys($grouped_by_date);

        echo '<div class="ktn-elementor-showtimes-wrapper ktn-skin-' . esc_attr($settings['layout_skin']) . '">';

        if ($settings['show_tabs'] === 'yes') {
            echo '<div class="ktn-modern-date-switcher"><div class="ktn-date-tabs-scroll">';
            $is_first = true;
            foreach ($unique_dates as $date) {
                $timestamp = strtotime($date);
                $day_name = $timestamp ? date('D', $timestamp) : '';
                $day_num = $timestamp ? date('j', $timestamp) : '';
                $month_name = $timestamp ? date('M', $timestamp) : '';
                
                $active_class = $is_first ? 'active' : '';
                $target_id = 'elem-date-' . md5($date);
                echo '<button class="ktn-date-tab-btn ' . esc_attr($active_class) . '" data-date-target="' . esc_attr($target_id) . '">';
                if ($timestamp && $day_name) {
                    echo '<span class="ktn-date-day-name">' . esc_html($day_name) . '</span>';
                    echo '<span class="ktn-date-day-num">' . esc_html($day_num) . '</span>';
                    echo '<span class="ktn-date-month">' . esc_html($month_name) . '</span>';
                } else {
                    echo '<span class="ktn-date-full">' . esc_html($date) . '</span>';
                }
                echo '</button>';
                $is_first = false;
            }
            echo '</div></div>';
        }

        echo '<div class="ktn-showtimes-content-area">';
        $is_first = true;
        foreach ($grouped_by_date as $date_str => $groups) {
            $panel_id = 'elem-date-' . md5($date_str);
            $active_class = $is_first ? 'active' : '';
            echo '<div class="ktn-date-panel ' . esc_attr($active_class) . '" id="' . esc_attr($panel_id) . '">';
            
            foreach ($groups as $group_name => $times) {
                echo '<div class="ktn-elem-showtime-block">';
                
                if ($settings['show_names'] === 'yes') {
                    echo '<h3 class="ktn-elem-group-title">';
                    if ($mode === 'current_movie') {
                        $cinema_id = $times[0]->cinema_id;
                        $cinema_link = get_permalink($cinema_id);
                        echo '<a href="' . esc_url($cinema_link) . '">' . esc_html($group_name) . '</a>';
                    } else {
                        $matched_id = $times[0]->matched_movie_id;
                        $movie_link = get_permalink($matched_id);
                        if ($matched_id && get_post_type($matched_id) === 'movie') {
                            echo '<a href="' . esc_url($movie_link) . '">' . esc_html($group_name) . '</a>';
                        } else {
                            echo esc_html($group_name);
                        }
                    }
                    echo '</h3>';
                }
                
                echo '<div class="ktn-elem-chips-grid">';
                foreach ($times as $t) {
                    $meta_str = '';
                    if ($settings['show_experience'] === 'yes' && $t->experience) {
                        $meta_str .= $t->experience . ' ';
                    }
                    if ($settings['show_price'] === 'yes' && $t->price_text) {
                        $meta_str .= $t->price_text;
                    }
                    $meta_str = trim($meta_str);

                    echo '<div class="ktn-premium-chip">';
                    echo '<span class="ktn-chip-time">' . esc_html($t->show_time) . '</span>';
                    if ($meta_str) {
                        echo '<span class="ktn-chip-meta">' . esc_html($meta_str) . '</span>';
                    }
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>'; // ktn-elem-showtime-block
            }
            
            echo '</div>'; // ktn-date-panel
            $is_first = false;
        }
        echo '</div>'; // ktn-showtimes-content-area
        echo '</div>'; // ktn-elementor-showtimes-wrapper
    }
}
