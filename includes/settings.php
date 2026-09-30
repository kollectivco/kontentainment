<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'ktn_add_settings_page' );
function ktn_add_settings_page() {
	// Main settings page
	add_submenu_page(
		'edit.php?post_type=movie',
		__( 'Kontentainment Settings', 'kontentainment' ),
		__( 'Settings', 'kontentainment' ),
		'manage_options',
		'kontentainment-settings',
		'ktn_settings_page_html'
	);
}

add_action( 'admin_init', 'ktn_register_settings' );
function ktn_register_settings() {
	register_setting( 'ktn_settings_group', 'ktn_tmdb_bearer_token' );
	register_setting( 'ktn_settings_group', 'ktn_default_language', array( 'default' => 'en-US' ) );
	register_setting( 'ktn_settings_group', 'ktn_default_region', array( 'default' => 'US' ) );
	register_setting( 'ktn_settings_group', 'ktn_download_images', array( 'default' => 0 ) );
	register_setting( 'ktn_settings_group', 'ktn_auto_set_featured_image', array( 'default' => 0 ) );
	register_setting( 'ktn_settings_group', 'ktn_cast_limit', array( 'default' => 10 ) );
	register_setting( 'ktn_settings_group', 'ktn_prevent_duplicates', array( 'default' => 1 ) );
	register_setting( 'ktn_settings_group', 'ktn_github_token', array( 'default' => '' ) );
}

function ktn_settings_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p><em>This product uses the TMDB API but is not endorsed or certified by TMDB.</em></p>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'ktn_settings_group' );
			do_settings_sections( 'ktn_settings_group' );
			?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'TMDB Bearer Token', 'kontentainment' ); ?></th>
					<td>
						<input type="password" name="ktn_tmdb_bearer_token" value="<?php echo esc_attr( get_option('ktn_tmdb_bearer_token') ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Default Language', 'kontentainment' ); ?></th>
					<td>
						<input type="text" name="ktn_default_language" value="<?php echo esc_attr( get_option('ktn_default_language', 'en-US') ); ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Default Region', 'kontentainment' ); ?></th>
					<td>
						<input type="text" name="ktn_default_region" value="<?php echo esc_attr( get_option('ktn_default_region', 'US') ); ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Download Images', 'kontentainment' ); ?></th>
					<td>
						<input type="checkbox" name="ktn_download_images" value="1" <?php checked( 1, get_option('ktn_download_images', 0), true ); ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Auto-set Featured Image', 'kontentainment' ); ?></th>
					<td>
						<input type="checkbox" name="ktn_auto_set_featured_image" value="1" <?php checked( 1, get_option('ktn_auto_set_featured_image', 0), true ); ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Cast Limit', 'kontentainment' ); ?></th>
					<td>
						<input type="number" name="ktn_cast_limit" value="<?php echo esc_attr( get_option('ktn_cast_limit', 10) ); ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Prevent Duplicates', 'kontentainment' ); ?></th>
					<td>
						<input type="checkbox" name="ktn_prevent_duplicates" value="1" <?php checked( 1, get_option('ktn_prevent_duplicates', 1), true ); ?> />
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Auto-Update from GitHub', 'kontentainment' ); ?></h2>
			<p>This plugin is configured to automatically download updates from its official GitHub repository.</p>
			
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'GitHub Token (Optional)', 'kontentainment' ); ?></th>
					<td>
						<input type="password" name="ktn_github_token" value="<?php echo esc_attr( get_option('ktn_github_token') ); ?>" class="regular-text" placeholder="Required only if repository is Private" />
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr style="margin: 40px 0 20px 0; border: 0; border-top: 1px solid #ccc;" />

		<h2><?php esc_html_e( 'Box Office Sync', 'kontentainment' ); ?></h2>
		<p><?php esc_html_e( 'Manage and manually update the movies sales and revenue data scraped from cinema-track.com.', 'kontentainment' ); ?></p>
		
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Last Synced', 'kontentainment' ); ?></th>
				<td>
					<strong>
						<?php 
						$last_synced = get_option('ktn_box_office_last_synced');
						echo esc_html($last_synced ? $last_synced : __('Never', 'kontentainment')); 
						?>
					</strong>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Manual Update', 'kontentainment' ); ?></th>
				<td>
					<button type="button" id="ktn-sync-box-office-btn" class="button button-secondary">
						<span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Force Sync Now', 'kontentainment' ); ?>
					</button>
					<span id="ktn-sync-box-office-status" style="margin-left: 10px; font-weight: 600; vertical-align: middle;"></span>
				</td>
			</tr>
		</table>

        <hr style="margin: 40px 0 20px 0; border: 0; border-top: 1px solid #ccc;" />

        <h2><?php esc_html_e( 'Auto-Tag Articles to Actors', 'kontentainment' ); ?></h2>
        <p><?php esc_html_e( 'Scan all standard posts and automatically tag them with existing actors if their names are found in the post title.', 'kontentainment' ); ?></p>
        
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php esc_html_e( 'Run Auto-Tagger', 'kontentainment' ); ?></th>
                <td>
                    <button type="button" id="ktn-auto-tag-actors-btn" class="button button-primary">
                        <span class="dashicons dashicons-admin-links" style="vertical-align: middle; margin-right: 4px;"></span>
                        <?php esc_html_e( 'Auto-Tag Posts Now', 'kontentainment' ); ?>
                    </button>
                    <span id="ktn-auto-tag-status" style="margin-left: 10px; font-weight: 600; vertical-align: middle;"></span>
                </td>
            </tr>
        </table>
	</div>

	<script>
	jQuery(document).ready(function($) {
		$('#ktn-sync-box-office-btn').on('click', function(e) {
			e.preventDefault();
			var btn = $(this);
			var status = $('#ktn-sync-box-office-status');
			
			if (btn.hasClass('disabled')) return;
			
			btn.addClass('disabled').attr('disabled', 'disabled');
			status.css('color', '#f59e0b').text('<?php esc_html_e( 'Syncing...', 'kontentainment' ); ?>');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ktn_force_sync_box_office',
					security: '<?php echo wp_create_nonce("ktn_settings_nonce"); ?>'
				},
				success: function(response) {
					btn.removeClass('disabled').removeAttr('disabled');
					if (response.success) {
						status.css('color', 'green').text('<?php esc_html_e( 'Success! Data updated.', 'kontentainment' ); ?>');
						location.reload();
					} else {
						status.css('color', 'red').text('<?php esc_html_e( 'Error: ', 'kontentainment' ); ?>' + response.data);
					}
				},
				error: function() {
					btn.removeClass('disabled').removeAttr('disabled');
					status.css('color', 'red').text('<?php esc_html_e( 'Request failed.', 'kontentainment' ); ?>');
				}
			});
			$('#ktn-auto-tag-actors-btn').on('click', function(e) {
				e.preventDefault();
				var btn = $(this);
				var status = $('#ktn-auto-tag-status');
				
				if (btn.hasClass('disabled')) return;
				
				btn.addClass('disabled').attr('disabled', 'disabled');
				status.css('color', '#f59e0b').text('Processing... This may take a while.');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'ktn_auto_tag_actors',
						security: '<?php echo wp_create_nonce("ktn_settings_nonce"); ?>'
					},
					success: function(response) {
						btn.removeClass('disabled').removeAttr('disabled');
						if (response.success) {
							status.css('color', 'green').text(response.data.message);
						} else {
							status.css('color', 'red').text('Error: ' + response.data);
						}
					},
					error: function() {
						btn.removeClass('disabled').removeAttr('disabled');
						status.css('color', 'red').text('Request failed.');
					}
				});
			});
		});
	});
	</script>
	<?php
}

/**
 * AJAX handler to force sync box office data
 */
add_action('wp_ajax_ktn_force_sync_box_office', 'wp_ajax_ktn_force_sync_box_office_handler');
function wp_ajax_ktn_force_sync_box_office_handler() {
    check_ajax_referer('ktn_settings_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $result = Ktn_Box_Office_Scraper::scrape_remote_data();
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    wp_send_json_success();
}



/**
 * AJAX handler to auto-tag actors in posts
 */
add_action('wp_ajax_ktn_auto_tag_actors', 'wp_ajax_ktn_auto_tag_actors_handler');
function wp_ajax_ktn_auto_tag_actors_handler() {
    check_ajax_referer('ktn_settings_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    // Get all actors
    $actors = get_terms(array(
        'taxonomy' => 'ktn_cast',
        'hide_empty' => false,
    ));

    if (empty($actors) || is_wp_error($actors)) {
        wp_send_json_error('No actors found.');
    }

    // Get all posts
    $posts = get_posts(array(
        'post_type' => 'post',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ));

    if (empty($posts)) {
        wp_send_json_error('No posts found.');
    }

    $tagged_count = 0;

    foreach ($posts as $post) {
        $post_title = $post->post_title;
        $post_content = $post->post_content;
        $terms_to_add = array();

        foreach ($actors as $actor) {
            $arabic_name = get_term_meta($actor->term_id, '_ktn_cast_arabic_name', true);
            $names_to_check = array($actor->name);
            if (!empty($arabic_name)) {
                $names_to_check[] = $arabic_name;
            }

            foreach ($names_to_check as $name) {
                if (empty(trim($name))) continue;
                // Simple case-insensitive search in title or tags. We'll just do title to be safe and avoid matching random words in content.
                if (stripos($post_title, $name) !== false) {
                    $terms_to_add[] = $actor->term_id;
                    break;
                }
                
                // Also check if the post has a tag with the actor's name
                $post_tags = wp_get_post_tags($post->ID);
                foreach($post_tags as $tag) {
                    if (strcasecmp($tag->name, $name) === 0) {
                        $terms_to_add[] = $actor->term_id;
                        break 2;
                    }
                }
            }
        }

        if (!empty($terms_to_add)) {
            wp_set_object_terms($post->ID, $terms_to_add, 'ktn_cast', true);
            $tagged_count++;
        }
    }

    wp_send_json_success(array('message' => sprintf(__('Successfully scanned posts and tagged %d posts with actors.', 'kontentainment'), $tagged_count)));
}
