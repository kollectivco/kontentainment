<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once KTN_PLUGIN_DIR . 'elementor/base-widget.php';

class KTN_Areas_Widget extends KTN_Elementor_Base_Widget {

    public function get_name() {
        return 'ktn-areas-widget';
    }

    public function get_title() {
        return esc_html__('Kueue Areas', 'kontentainment');
    }

    public function get_icon() {
        return 'eicon-tags';
    }

    protected function register_controls() {
        $this->start_controls_section('section_query', [
            'label' => esc_html__('Query & Layout', 'kontentainment'),
        ]);

        $this->add_control('number', [
            'label' => esc_html__('Number of Areas', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 0,
            'description' => '0 means all areas',
        ]);

        $this->add_control('hide_empty', [
            'label' => esc_html__('Hide Empty Areas', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
            'return_value' => 'yes',
        ]);

        $skins = [
            'grid'    => 'Grid Layout',
            'list'    => 'List Layout',
            'cards'   => 'Card Design',
            'minimal' => 'Minimal Links',
        ];
        $this->add_skin_control($skins);
        $this->add_columns_control();
        
        $this->end_controls_section();

        $this->start_controls_section('section_visibility', [
            'label' => esc_html__('Visibility Controls', 'kontentainment'),
        ]);

        $this->add_control('show_count', [
            'label' => esc_html__('Show Cinema Count', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
            'return_value' => 'yes',
        ]);

        $this->add_control('show_desc', [
            'label' => esc_html__('Show Description', 'kontentainment'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
            'return_value' => 'yes',
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        $args = [
            'taxonomy' => 'cinema_area',
            'hide_empty' => ($settings['hide_empty'] === 'yes'),
        ];
        if ($settings['number'] > 0) {
            $args['number'] = intval($settings['number']);
        }

        $areas = get_terms($args);

        echo '<div class="ktn-elementor-areas-wrapper ktn-skin-' . esc_attr($settings['layout_skin']) . '">';
        
        if (!empty($areas) && !is_wp_error($areas)) {
            echo '<div class="ktn-elementor-grid">';
            foreach ($areas as $area) {
                $link = get_term_link($area);
                ?>
                <a href="<?php echo esc_url($link); ?>" class="ktn-elem-area-card">
                    <div class="ktn-area-content">
                        <h3 class="ktn-area-title"><?php echo esc_html($area->name); ?></h3>
                        <?php if ($settings['show_count'] === 'yes'): ?>
                            <span class="ktn-area-count"><?php echo intval($area->count); ?> Cinemas</span>
                        <?php endif; ?>
                        <?php if ($settings['show_desc'] === 'yes' && !empty($area->description)): ?>
                            <p class="ktn-area-desc"><?php echo wp_trim_words($area->description, 15); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="ktn-area-arrow"><i class="fa fa-arrow-right"></i></div>
                </a>
                <?php
            }
            echo '</div>';
        } else {
            echo '<p>No cinematic areas found.</p>';
        }

        echo '</div>';
    }
}
