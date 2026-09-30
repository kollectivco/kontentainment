<?php
if (!defined('ABSPATH')) exit;

/**
 * Custom Meta Box for selecting Actors (ktn_cast) using AJAX Select2
 */
class Ktn_Admin_Actors_Metabox {

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'remove_default_and_add_custom']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('save_post', [__CLASS__, 'save_postdata']);
        add_action('wp_ajax_ktn_search_actors_ajax', [__CLASS__, 'ajax_search']);
    }

    public static function remove_default_and_add_custom() {
        $post_types = ['post', 'movie', 'tv_show'];
        foreach ($post_types as $pt) {
            // Remove default tag cloud box for ktn_cast
            remove_meta_box('tagsdiv-ktn_cast', $pt, 'side');
            
            // Add our custom select2 box
            add_meta_box(
                'ktn_cast_select2_box',
                __('Cast / Actors', 'kontentainment'),
                [__CLASS__, 'render_metabox'],
                $pt,
                'side',
                'high'
            );
        }
    }

    public static function enqueue_scripts($hook) {
        global $post;
        if (($hook === 'post-new.php' || $hook === 'post.php') && $post && in_array($post->post_type, ['post', 'movie', 'tv_show'])) {
            wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
            wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
        }
    }

    public static function render_metabox($post) {
        wp_nonce_field('ktn_save_actors_data', 'ktn_actors_meta_nonce');

        $current_terms = wp_get_post_terms($post->ID, 'ktn_cast');
        ?>
        <select id="ktn-actors-select2" name="ktn_cast_terms[]" multiple="multiple" style="width: 100%;">
            <?php foreach ($current_terms as $term): ?>
                <option value="<?php echo esc_attr($term->term_id); ?>" selected="selected">
                    <?php 
                    $arabic_name = get_term_meta($term->term_id, '_ktn_cast_arabic_name', true);
                    $display = $arabic_name ? $arabic_name . ' (' . $term->name . ')' : $term->name;
                    echo esc_html($display); 
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Search for an actor to link them to this post.', 'kontentainment'); ?></p>

        <script>
        jQuery(document).ready(function($) {
            $('#ktn-actors-select2').select2({
                placeholder: '<?php echo esc_js(__('Search or add actors...', 'kontentainment')); ?>',
                tags: true,
                createTag: function (params) {
                    var term = $.trim(params.term);
                    if (term === '') {
                        return null;
                    }
                    return {
                        id: term,
                        text: term + ' (New)'
                    }
                },
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term,
                            action: 'ktn_search_actors_ajax',
                            nonce: '<?php echo wp_create_nonce("ktn_search_actors"); ?>'
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 2
            });
        });
        </script>
        <?php
    }

    public static function save_postdata($post_id) {
        if (!isset($_POST['ktn_actors_meta_nonce']) || !wp_verify_nonce($_POST['ktn_actors_meta_nonce'], 'ktn_save_actors_data')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $term_ids = [];
        if (!empty($_POST['ktn_cast_terms'])) {
            foreach ($_POST['ktn_cast_terms'] as $val) {
                if (is_numeric($val)) {
                    $term_ids[] = intval($val);
                } else {
                    $val = sanitize_text_field($val);
                    $term_info = term_exists($val, 'ktn_cast');
                    if (!$term_info) {
                        $term_info = wp_insert_term($val, 'ktn_cast');
                    }
                    if (!is_wp_error($term_info) && isset($term_info['term_id'])) {
                        $term_ids[] = intval($term_info['term_id']);
                    }
                }
            }
        }
        
        wp_set_object_terms($post_id, $term_ids, 'ktn_cast', false);
    }

    public static function ajax_search() {
        check_ajax_referer('ktn_search_actors', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }

        $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        
        // Custom query to search in term name OR meta value for arabic name
        global $wpdb;
        $term_query = $wpdb->prepare("
            SELECT t.term_id, t.name, tm.meta_value as arabic_name
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id AND tm.meta_key = '_ktn_cast_arabic_name'
            WHERE tt.taxonomy = 'ktn_cast'
            AND (t.name LIKE %s OR tm.meta_value LIKE %s)
            LIMIT 20
        ", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');

        $terms = $wpdb->get_results($term_query);
        $results = [];

        if (!empty($terms)) {
            foreach ($terms as $term) {
                $display = !empty($term->arabic_name) ? $term->arabic_name . ' (' . $term->name . ')' : $term->name;
                $results[] = [
                    'id'   => $term->term_id,
                    'text' => $display
                ];
            }
        }

        wp_send_json_success($results);
    }
}

Ktn_Admin_Actors_Metabox::init();
