<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

class KTN_Watch_Providers_Widget extends KTN_Elementor_Base_Widget {

    public function get_name() {
        return 'ktn-watch-providers';
    }

    public function get_title() {
        return esc_html__('Watch Providers', 'kontentainment');
    }

    public function get_icon() {
        return 'eicon-play';
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Content', 'kontentainment'),
            ]
        );

        $this->add_control(
            'source',
            [
                'label'   => esc_html__('Source', 'kontentainment'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'current',
                'options' => [
                    'current' => esc_html__('Current Media', 'kontentainment'),
                    'manual'  => esc_html__('Manual Selection', 'kontentainment'),
                ],
            ]
        );

        $this->add_control(
            'post_id',
            [
                'label'     => esc_html__('Select Media', 'kontentainment'),
                'type'      => Controls_Manager::SELECT2,
                'multiple'  => false,
                'options'   => $this->get_media_options(),
                'condition' => [
                    'source' => 'manual',
                ],
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label'     => esc_html__('Show Title', 'kontentainment'),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'label_on'  => esc_html__('Show', 'kontentainment'),
                'label_off' => esc_html__('Hide', 'kontentainment'),
            ]
        );

        $this->add_control(
            'show_region_selector',
            [
                'label'     => esc_html__('Show Region Selector', 'kontentainment'),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'label_on'  => esc_html__('Show', 'kontentainment'),
                'label_off' => esc_html__('Hide', 'kontentainment'),
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'section_style',
            [
                'label' => esc_html__('Style', 'kontentainment'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label'     => esc_html__('Title Color', 'kontentainment'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ktn-wp-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'title_typography',
                'selector' => '{{WRAPPER}} .ktn-wp-title',
            ]
        );

        $this->add_control(
            'card_bg',
            [
                'label'     => esc_html__('Background Color', 'kontentainment'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ktn-watch-providers-section' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $post_id = ($settings['source'] === 'current') ? get_the_ID() : intval($settings['post_id']);

        if (!$post_id) {
            return;
        }

        $providers = get_post_meta($post_id, '_ktn_watch_providers', true);
        if (empty($providers)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="ktn-wp-empty">No watch providers found for this media.</div>';
            }
            return;
        }

        $default_region = get_option('ktn_default_region', 'EG');
        $available_regions = array_keys($providers);
        
        usort($available_regions, function($a, $b) use ($default_region) {
            if ($a === $default_region) return -1;
            if ($b === $default_region) return 1;
            return strcmp(ktn_get_country_name($a), ktn_get_country_name($b));
        });

        wp_enqueue_style('ktn-watch-providers', KTN_PLUGIN_URL . 'assets/css/kontentainment-watch-providers.css', [], KTN_PLUGIN_VERSION);
        wp_enqueue_script('ktn-watch-providers', KTN_PLUGIN_URL . 'assets/js/kontentainment-watch-providers.js', ['jquery'], KTN_PLUGIN_VERSION, true);

        ?>
        <div class="ktn-watch-providers-section" id="ktn-wp-<?php echo $post_id; ?>">
            <div class="ktn-wp-header">
                <?php if ($settings['show_title'] === 'yes'): ?>
                    <h2 class="ktn-wp-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="m7 21 5-5 5 5"/></svg>
                        <?php esc_html_e('Where to Watch', 'kontentainment'); ?>
                    </h2>
                <?php endif; ?>

                <?php if ($settings['show_region_selector'] === 'yes'): ?>
                    <div class="ktn-wp-region-selector">
                        <label for="ktn-region-select-<?php echo $post_id; ?>"><?php esc_html_e('Region:', 'kontentainment'); ?></label>
                        <select id="ktn-region-select-<?php echo $post_id; ?>" class="ktn-wp-select">
                            <?php foreach ($available_regions as $code): ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($code, $default_region); ?>>
                                    <?php echo esc_html(ktn_get_country_name($code)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ktn-wp-content">
                <?php foreach ($providers as $region_code => $data): 
                    $active_class = ($region_code === $default_region || ($region_code === reset($available_regions) && !in_array($default_region, $available_regions))) ? 'active' : '';
                    ?>
                    <div class="ktn-wp-region-panel <?php echo esc_attr($active_class); ?>" data-region="<?php echo esc_attr($region_code); ?>">
                        <?php
                        $categories = [
                            'flatrate' => __('Stream', 'kontentainment'),
                            'free'     => __('Free', 'kontentainment'),
                            'ads'      => __('Ads', 'kontentainment'),
                            'rent'     => __('Rent', 'kontentainment'),
                            'buy'      => __('Buy', 'kontentainment'),
                        ];

                        $found_any = false;
                        foreach ($categories as $key => $label):
                            if (empty($data[$key])) continue;
                            $found_any = true;
                            ?>
                            <div class="ktn-wp-category">
                                <h4 class="ktn-wp-cat-title"><?php echo esc_html($label); ?></h4>
                                <div class="ktn-wp-list">
                                    <?php foreach ($data[$key] as $provider): ?>
                                        <div class="ktn-wp-item" title="<?php echo esc_attr($provider['provider_name']); ?>">
                                            <div class="ktn-wp-logo">
                                                <img src="https://image.tmdb.org/t/p/original<?php echo esc_attr($provider['logo_path']); ?>" alt="<?php echo esc_attr($provider['provider_name']); ?>">
                                            </div>
                                            <span class="ktn-wp-name"><?php echo esc_html($provider['provider_name']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; 

                        if (!$found_any): ?>
                            <p class="ktn-wp-none"><?php esc_html_e('No providers available for this region.', 'kontentainment'); ?></p>
                        <?php endif; 
                        
                        if (!empty($data['link'])): ?>
                            <div class="ktn-wp-footer">
                                <a href="<?php echo esc_url($data['link']); ?>" target="_blank" rel="nofollow" class="ktn-wp-tmdb-link">
                                    <?php esc_html_e('View on TMDB', 'kontentainment'); ?> &rarr;
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function get_media_options() {
        $posts = get_posts([
            'post_type' => ['movie', 'tv_show'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $options = [];
        foreach ($posts as $post) {
            $options[$post->ID] = $post->post_title;
        }
        return $options;
    }
}
