<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once KTN_PLUGIN_DIR . 'elementor/base-widget.php';

class KTN_Box_Office_Widget extends KTN_Elementor_Base_Widget {

    public function get_name() {
        return 'ktn-box-office-widget';
    }

    public function get_title() {
        return esc_html__('Box Office', 'kontentainment');
    }

    public function get_icon() {
        return 'eicon-price-table';
    }

    protected function register_controls() {
        // Controls not needed as it loads the full dynamic dashboard, but we register section for UI
        $this->start_controls_section('section_content', [
            'label' => esc_html__('Settings', 'kontentainment'),
        ]);

        $this->add_control('notice', [
            'type' => \Elementor\Controls_Manager::RAW_HTML,
            'raw' => '<strong>' . esc_html__('Box Office Dashboard', 'kontentainment') . '</strong><br>' . esc_html__('This widget dynamically displays the daily, weekly, all-time highest-grossing movies and insights from cinema-track.com.', 'kontentainment'),
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        global $ktn_is_box_office_shortcode;
        $old_flag = $ktn_is_box_office_shortcode;
        $ktn_is_box_office_shortcode = true; // Render in widget mode without page header/footer

        $custom_template = KTN_PLUGIN_DIR . 'templates/page-box-office.php';
        if (file_exists($custom_template)) {
            include $custom_template;
        }

        $ktn_is_box_office_shortcode = $old_flag;
    }
}
