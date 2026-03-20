<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bulk Cinema Importer Admin feature
 */
class Ktn_Bulk_Cinema_Importer {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('wp_ajax_ktn_bulk_import_cinema', array($this, 'ajax_bulk_import_handler'));
        add_action('wp_ajax_ktn_get_child_locations_by_id', array($this, 'ajax_get_children_handler'));
    }

    public function add_menu_item() {
        add_submenu_page(
            'edit.php?post_type=movie',
            __('Import Multiple Cinemas', 'kontentainment'),
            __('Bulk Import', 'kontentainment'),
            'manage_options',
            'ktn-bulk-import-cinemas',
            array($this, 'render_page')
        );
    }

    public function render_page() {
        ?>
        <div class="wrap ktn-bulk-import-wrap">
            <h1 class="wp-heading-inline"><?php _e('Import Multiple Cinemas', 'kontentainment'); ?></h1>
            <hr class="wp-header-end">

            <div class="card" style="max-width: 900px; padding: 30px; margin-top: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <form id="ktn-bulk-import-form">
                    <?php wp_nonce_field('ktn_bulk_import_nonce', 'bulk_nonce'); ?>
                    
                    <div class="ktn-form-section" style="margin-bottom: 30px;">
                        <label style="display: block; font-weight: 700; font-size: 1.1rem; margin-bottom: 12px;">
                            <?php _e('Cinema URLs (Select elCinema Theater Pages)', 'kontentainment'); ?>
                        </label>
                        <p class="description" style="margin-bottom: 15px;">
                            <?php _e('Paste one URL per line. Example: https://elcinema.com/en/theater/3101258/', 'kontentainment'); ?>
                        </p>
                        <textarea id="bulk-urls" name="urls" rows="12" style="width: 100%; font-family: monospace; padding: 15px; border-radius: 8px; border: 1px solid #dcdcde;" placeholder="https://elcinema.com/en/theater/3101258/"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 25px; margin-bottom: 30px; background: #f9fafb; padding: 20px; border-radius: 10px; border: 1px solid #f0f0f1;">
                        <div class="ktn-form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('Source Type', 'kontentainment'); ?></label>
                            <select name="source_type" style="width: 100%; height: 40px; border-radius: 6px;">
                                <option value="elcinema_theater"><?php _e('elCinema Theater', 'kontentainment'); ?></option>
                            </select>
                        </div>
                        
                        <div class="ktn-form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('City / Governorate', 'kontentainment'); ?></label>
                            <?php 
                            wp_dropdown_categories(array(
                                'show_option_none' => __('Select City', 'kontentainment'),
                                'taxonomy'         => 'cinema_location',
                                'name'             => 'city_term',
                                'id'               => 'city-selector',
                                'hide_empty'       => 0,
                                'parent'           => 0,
                                'hierarchical'     => 1,
                                'class'            => 'ktn-dropdown',
                                'style'            => 'width: 100%; height: 40px; border-radius: 6px;'
                            ));
                            ?>
                        </div>

                        <div class="ktn-form-group">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('Area', 'kontentainment'); ?></label>
                            <select name="area_term" id="area-selector" style="width: 100%; height: 40px; border-radius: 6px;" disabled>
                                <option value=""><?php _e('Select City first', 'kontentainment'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="ktn-form-group" style="margin-bottom: 30px; padding: 0 5px;">
                        <label style="font-weight: 500; display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="sync_now" value="1" checked style="width: 18px; height: 18px; margin: 0;"> 
                            <?php _e('Fetch cinema details and sync showtimes immediately', 'kontentainment'); ?>
                        </label>
                    </div>

                    <div class="ktn-form-actions">
                        <button type="submit" class="button button-primary button-hero" id="start-bulk-import" style="padding: 0 40px; display: inline-flex; align-items: center; gap: 10px;">
                            <span class="dashicons dashicons-download" style="margin-top: 5px;"></span>
                            <?php _e('Import Cinemas', 'kontentainment'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <div id="bulk-results" style="margin-top: 40px; display: none; max-width: 1000px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2 style="margin: 0;"><?php _e('Import Log', 'kontentainment'); ?></h2>
                    <div id="bulk-progress" style="font-size: 1.1rem; font-weight: 700; color: #1e293b; background: #e2e8f0; padding: 5px 15px; border-radius: 20px;"></div>
                </div>

                <table class="wp-list-table widefat fixed striped" style="border-radius: 8px; overflow: hidden; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <thead>
                        <tr>
                            <th style="width: 35%;"><?php _e('Source URL', 'kontentainment'); ?></th>
                            <th style="width: 20%;"><?php _e('Cinema Name', 'kontentainment'); ?></th>
                            <th style="width: 15%; text-align: center;"><?php _e('Status', 'kontentainment'); ?></th>
                            <th style="width: 30%;"><?php _e('Result Details', 'kontentainment'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="results-body">
                    </tbody>
                </table>
            </div>
        </div>

        <style>
            .ktn-bulk-import-wrap h1 { margin-bottom: 25px; }
            #results-body tr td { vertical-align: middle; padding: 12px 10px; }
            .ktn-status-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-weight: 700; font-size: 11px; text-transform: uppercase; }
            .status-imported { background: #dcfce7; color: #166534; }
            .status-updated { background: #dbeafe; color: #1e40af; }
            .status-failed { background: #fee2e2; color: #991b1b; }
            .status-processing { background: #fef3c7; color: #92400e; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // City -> Area dependency
            $('#city-selector').on('change', function() {
                var cityId = $(this).val();
                var areaSelector = $('#area-selector');
                
                if (!cityId) {
                    areaSelector.prop('disabled', true).html('<option value=""><?php _e('Select City first', 'kontentainment'); ?></option>');
                    return;
                }

                areaSelector.prop('disabled', false).html('<option value=""><?php _e('Loading Areas...', 'kontentainment'); ?></option>');
                
                $.post(ajaxurl, {
                    action: 'ktn_get_child_locations_by_id',
                    parent_id: cityId,
                    nonce: '<?php echo wp_create_nonce('ktn_bulk_import_nonce'); ?>'
                }, function(res) {
                    if (res.success) {
                        var options = '<option value=""><?php _e('-- Select Area (Optional) --', 'kontentainment'); ?></option>';
                        res.data.forEach(function(item) {
                            options += '<option value="'+item.id+'">'+item.name+'</option>';
                        });
                        areaSelector.html(options);
                    }
                });
            });

            $('#ktn-bulk-import-form').on('submit', function(e) {
                e.preventDefault();
                var $btn = $('#start-bulk-import');
                var urls = $('#bulk-urls').val().split('\n').filter(function(u){ return u.trim().length > 0; });
                
                if (urls.length === 0) {
                    alert('<?php _e('Please enter at least one URL.', 'kontentainment'); ?>');
                    return;
                }

                if (!$('#city-selector').val()) {
                    alert('<?php _e('Please select a City/Governorate.', 'kontentainment'); ?>');
                    return;
                }

                if (!confirm('<?php _e('Are you sure you want to import ', 'kontentainment'); ?>' + urls.length + ' <?php _e(' cinemas?', 'kontentainment'); ?>')) {
                    return;
                }

                $btn.prop('disabled', true).addClass('updating');
                $('#bulk-results').show();
                $('#results-body').empty();
                $('#bulk-progress').html('0 / ' + urls.length);

                var processUrl = function(index) {
                    if (index >= urls.length) {
                        $btn.prop('disabled', false).removeClass('updating');
                        $('#bulk-progress').css('background', '#22c55e').css('color', '#fff');
                        return;
                    }

                    var currentUrl = urls[index].trim();
                    var rowId = 'row-' + index;
                    $('#results-body').append('<tr id="'+rowId+'"><td><code>'+currentUrl+'<code></td><td>---</td><td style="text-align:center;"><span class="ktn-status-badge status-processing"><?php _e('Working', 'kontentainment'); ?></span></td><td><?php _e('Parsing source...', 'kontentainment'); ?></td></tr>');

                    $.post(ajaxurl, {
                        action: 'ktn_bulk_import_cinema',
                        url: currentUrl,
                        city_id: $('#city-selector').val(),
                        area_id: $('#area-selector').val(),
                        sync_now: $('input[name="sync_now"]').is(':checked') ? 1 : 0,
                        source_type: $('select[name="source_type"]').val(),
                        nonce: $('#bulk_nonce').val()
                    }, function(res) {
                        var status_html = '';
                        var name = '---';
                        var details = '';
                        
                        if (res.success) {
                            var cls = res.data.status_label === 'IMPORTED' ? 'status-imported' : 'status-updated';
                            status_html = '<span class="ktn-status-badge '+cls+'">'+res.data.status_label+'</span>';
                            name = res.data.name;
                            details = res.data.message;
                        } else {
                            status_html = '<span class="ktn-status-badge status-failed"><?php _e('Failed', 'kontentainment'); ?></span>';
                            details = res.data ? res.data.message : (res.error || 'Unknown Error');
                        }
                        
                        var $row = $('#' + rowId);
                        $row.find('td:nth-child(2)').html('<strong>'+name+'</strong>');
                        $row.find('td:nth-child(3)').html(status_html);
                        $row.find('td:nth-child(4)').text(details);
                        
                        $('#bulk-progress').text((index + 1) + ' / ' + urls.length);
                        processUrl(index + 1);
                    }).fail(function() {
                         var $row = $('#' + rowId);
                         $row.find('td:nth-child(3)').html('<span class="ktn-status-badge status-failed">ERROR</span>');
                         $row.find('td:nth-child(4)').text('Server Timeout or Connection Error');
                         processUrl(index + 1);
                    });
                };

                processUrl(0);
            });
        });
        </script>
        <?php
    }

    public function ajax_get_children_handler() {
        check_ajax_referer('ktn_bulk_import_nonce', 'nonce');
        $parent_id = intval($_POST['parent_id']);
        
        $terms = get_terms(array(
            'taxonomy' => 'cinema_location',
            'parent'   => $parent_id,
            'hide_empty' => false
        ));

        $results = array();
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $results[] = array('id' => $t->term_id, 'name' => $t->name);
            }
        }
        wp_send_json_success($results);
    }

    public function ajax_bulk_import_handler() {
        check_ajax_referer('ktn_bulk_import_nonce', 'nonce');
        
        $url = esc_url_raw($_POST['url']);
        $city_id = intval($_POST['city_id']);
        $area_id = intval($_POST['area_id']);
        $sync_now = intval($_POST['sync_now']);
        $source_type = sanitize_text_field($_POST['source_type']);

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
             wp_send_json_error(array('message' => __('Invalid URL provided.', 'kontentainment')));
        }

        // Extract ID for duplicate check
        $elcinema_id = '';
        if (preg_match('/theater\/([0-9]+)/', $url, $m)) {
            $elcinema_id = $m[1];
        }

        $existing_id = $this->find_existing_cinema($url, $elcinema_id);
        
        if ($existing_id) {
            $status_label = 'UPDATED';
            $post_id = $existing_id;
            $msg_prefix = __('Updated existing Cinema.', 'kontentainment');
            // Update source if it changed
            update_post_meta($post_id, '_ktn_cinema_url', $url);
        } else {
            $status_label = 'IMPORTED';
            $post_id = wp_insert_post(array(
                'post_type'   => 'ktn_cinema',
                'post_title'  => 'Processing...',
                'post_status' => 'publish',
            ));
            $msg_prefix = __('Imported successfully.', 'kontentainment');
            update_post_meta($post_id, '_ktn_cinema_url', $url);
        }

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => $post_id->get_error_message()));
        }

        // Set static data from form
        update_post_meta($post_id, '_ktn_cinema_type', $source_type);
        update_post_meta($post_id, '_ktn_cinema_auto_sync', 'yes');
        update_post_meta($post_id, '_ktn_cinema_status', 'active');

        // Save City/Area Meta for the edit screen
        $city_term = get_term($city_id, 'cinema_location');
        $area_term = get_term($area_id, 'cinema_location');
        if ($city_term && !is_wp_error($city_term)) {
            update_post_meta($post_id, '_ktn_cinema_city', $city_term->name);
        }
        if ($area_term && !is_wp_error($area_term)) {
            update_post_meta($post_id, '_ktn_cinema_area', $area_term->name);
        }

        // Assign Taxonomy (City/Area)
        $term_ids = array_filter(array($city_id, $area_id));
        if (!empty($term_ids)) {
            wp_set_object_terms($post_id, array_map('intval', $term_ids), 'cinema_location', false);
        }

        // Run sync (Full Import)
        $sync_message = '';
        $sync_res = Ktn_Cinema_Importer::syncCinema($post_id, true);
        
        $final_name = get_the_title($post_id);
        $sync_message = is_array($sync_res) ? $sync_res['message'] : 'Sync completed.';
        $success = is_array($sync_res) ? $sync_res['success'] : true;

        if ($success) {
            wp_send_json_success(array(
                'id' => $post_id,
                'name' => $final_name,
                'status_label' => $status_label,
                'message' => $msg_prefix . ' ' . $sync_message
            ));
        } else {
            wp_send_json_error(array(
                'name' => $final_name,
                'message' => $sync_message
            ));
        }
    }

    private function find_existing_cinema($url, $elcinema_id) {
        // 1. Check by elCinema ID (most accurate)
        if ($elcinema_id) {
            $args = array(
                'post_type' => 'ktn_cinema',
                'meta_query' => array(
                    array('key' => '_ktn_cinema_theater_id', 'value' => $elcinema_id, 'compare' => '=')
                ),
                'posts_per_page' => 1,
                'fields' => 'ids'
            );
            $query = new WP_Query($args);
            if ($query->have_posts()) return $query->posts[0];
        }

        // 2. Check by exact URL
        $args = array(
            'post_type' => 'ktn_cinema',
            'meta_query' => array(
                array('key' => '_ktn_cinema_url', 'value' => $url, 'compare' => '=')
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        );
        $query = new WP_Query($args);
        if ($query->have_posts()) return $query->posts[0];

        return false;
    }
}

new Ktn_Bulk_Cinema_Importer();
