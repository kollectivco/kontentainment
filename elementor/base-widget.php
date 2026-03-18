<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

abstract class KTN_Elementor_Base_Widget extends \Elementor\Widget_Base {

    public function get_categories() {
        return ['kontentainment-widgets'];
    }

    protected function add_skin_control($skins = []) {
        $options = [];
        foreach ($skins as $key => $label) {
            $options[$key] = $label;
        }

        $this->add_control(
            'layout_skin',
            [
                'label' => esc_html__('Layout Skin', 'kontentainment'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => array_keys($options)[0],
                'options' => $options,
            ]
        );
    }

    protected function add_columns_control() {
        $this->add_responsive_control(
            'columns',
            [
                'label' => esc_html__('Columns', 'kontentainment'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '4',
                'tablet_default' => '2',
                'mobile_default' => '1',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                ],
                'selectors' => [
                    '{{WRAPPER}} .ktn-elementor-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
                ],
            ]
        );
    }

    protected function add_query_controls($post_type = 'movie') {
        $this->add_control(
            'source',
            [
                'label' => esc_html__('Source', 'kontentainment'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'latest',
                'options' => [
                    'latest' => esc_html__('Latest', 'kontentainment'),
                    'manual' => esc_html__('Manual Selection', 'kontentainment'),
                ],
            ]
        );

        $this->add_control(
            'posts_per_page',
            [
                'label' => esc_html__('Items count', 'kontentainment'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 8,
                'condition' => [
                    'source' => 'latest',
                ],
            ]
        );
    }
}
