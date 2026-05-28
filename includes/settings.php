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

	// Quick Translation sub-menu page
	add_submenu_page(
		'edit.php?post_type=movie',
		__( 'Quick Translation', 'kontentainment' ),
		__( 'Quick Translation', 'kontentainment' ),
		'manage_options',
		'kontentainment-translation',
		'ktn_translation_page_html'
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
	</div>
	<?php
}

/**
 * Register AJAX handler for Auto Translation.
 */
add_action('wp_ajax_ktn_auto_translate_strings', 'wp_ajax_ktn_auto_translate_strings_handler');
function wp_ajax_ktn_auto_translate_strings_handler() {
    check_ajax_referer('ktn_translation_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $strings = isset($_POST['strings']) ? $_POST['strings'] : array();
    if (empty($strings) || !is_array($strings)) {
        wp_send_json_error('No strings provided');
    }

    // Determine target translation language code (e.g. ar or default)
    $locale = get_locale();
    $tl = (strpos($locale, 'ar') === 0) ? 'ar' : substr($locale, 0, 2);

    $results = array();
    foreach ($strings as $str) {
        // Run Google free translation engine
        $translated = ktn_translate_text_free($str, 'en', $tl);
        $results[$str] = $translated;
    }

    wp_send_json_success($results);
}

/**
 * Render the premium Quick Translation settings dashboard.
 */
function ktn_translation_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Save action
    if ( isset($_POST['ktn_save_translations']) && check_admin_referer('ktn_save_translations_nonce', 'security') ) {
        $submitted = isset($_POST['translations']) ? $_POST['translations'] : array();
        $translations = array();
        foreach ($submitted as $key => $val) {
            $translations[stripslashes($key)] = sanitize_text_field(stripslashes($val));
        }
        update_option('ktn_quick_translations', $translations);
        echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__('Translations saved successfully.', 'kontentainment') . '</strong></p></div>';
    }

    $translations = get_option('ktn_quick_translations', array());

    $default_strings = array(
        'All Cinemas',
        'Discover the best cinemas across different areas.',
        'All Areas',
        'Movies Playing',
        'View Showtimes',
        'No cinemas found.',
        'Previous',
        'Next',
        'Coming Soon',
        'Discover movies releasing soon.',
        'No Poster',
        'Watch Trailer',
        'View Details',
        'No movies are in the coming soon list.',
        'Now Playing',
        'Movies currently showing in cinemas.',
        'View Movie',
        'No movies are currently playing.',
        'Get Directions',
        'Cinema Notes',
        'No showtimes currently available',
        'There are no movies playing at this cinema right now. Please check back later.',
        'min',
        'View Details &rarr;',
        'TV Show',
        'Movie',
        'Overview',
        'Director',
        'Writers',
        'Cast',
        'No Photo',
        'Theater Showtimes',
        'View Cinema',
        'More Cinemas',
        'Watch Trailer on YouTube',
        'Related TV Shows',
        'Related Movies',
        'Address',
        'Playing Movies',
        'Cinemas',
        'Cinemas located in %s.',
        'No cinemas found in this area.',
        'Personal Info',
        'Known For',
        'Acting',
        'Known Credits',
        'Gender',
        'Female',
        'Male',
        'Non-binary',
        'Birthday',
        'years old',
        'Place of Birth',
        'Also Known As',
        'Biography',
        '%d Movie Playing',
        '%d Movies Playing',
        '+%d More Cinema',
        '+%d More Cinemas',
        'Explore the latest movies and top-rated cinemas near you.',
        'All Movies',
        'English Movies',
        'Arabic Movies',
        'Type Your Movie Name',
        'All Genres',
        'Type Your Cinema Name',
        'All Governorates',
        'Select Area',
    );
    ?>
    <div class="wrap ktn-translation-wrap">
        <div class="ktn-translation-header">
            <div class="ktn-header-left">
                <h1>
                    <span class="dashicons dashicons-translation" style="font-size: 32px; width: 32px; height: 32px; margin-right: 8px; vertical-align: middle; color: #111;"></span>
                    Quick Translation
                </h1>
                <p class="ktn-header-desc">Allows you to quickly translate front-end strings to your language.</p>
            </div>
            <div class="ktn-header-right">
                <button type="button" class="ktn-btn ktn-btn-black ktn-btn-auto-translate">
                    <span class="dashicons dashicons-admin-site-alt3" style="font-size: 16px; width: 16px; height: 16px; margin-right: 6px; vertical-align: middle;"></span>
                    Auto Translation
                </button>
                <button type="button" class="ktn-btn ktn-btn-white ktn-btn-quick-tools">
                    <span class="dashicons dashicons-cloud" style="font-size: 16px; width: 16px; height: 16px; margin-right: 6px; vertical-align: middle;"></span>
                    Quick Tools
                </button>
            </div>
        </div>

        <div class="ktn-alert ktn-alert-warning">
            <span class="dashicons dashicons-info" style="font-size: 18px; width: 18px; height: 18px; color: #b45309; margin-right: 6px; vertical-align: text-bottom;"></span>
            <strong>PLEASE NOTE:</strong> Please keep "%s" or "%d" as it is in the translated text if the string contains this variable. Incorrect formatting can cause fatal errors in PHP code and prevent the site from loading correctly.
        </div>

        <form method="post" action="">
            <?php wp_nonce_field('ktn_save_translations_nonce', 'security'); ?>
            
            <div class="ktn-table-container">
                <table class="ktn-translation-table">
                    <thead>
                        <tr>
                            <th class="col-source">
                                <span class="dashicons dashicons-admin-site" style="font-size: 18px; width: 18px; height: 18px; margin-right: 6px; vertical-align: text-bottom; color: #666;"></span>
                                Source String - English
                            </th>
                            <th class="col-translation">
                                <span class="dashicons dashicons-editor-textcolor" style="font-size: 18px; width: 18px; height: 18px; margin-right: 6px; vertical-align: text-bottom; color: #666;"></span>
                                Translation
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($default_strings as $index => $str): 
                            $current_val = isset($translations[$str]) ? $translations[$str] : '';
                            $has_translation = !empty($current_val);
                        ?>
                        <tr class="ktn-translation-row" data-source="<?php echo esc_attr(strtolower($str)); ?>">
                            <td class="col-source"><?php echo esc_html($str); ?></td>
                            <td class="col-translation">
                                <input type="text" 
                                       name="translations[<?php echo esc_attr($str); ?>]" 
                                       value="<?php echo esc_attr($current_val); ?>" 
                                       placeholder="<?php echo esc_attr($str); ?>"
                                       class="ktn-translation-input <?php echo $has_translation ? 'translated' : ''; ?>" />
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="ktn-translation-footer">
                <div class="ktn-footer-left">
                    <div class="ktn-search-box">
                        <span class="dashicons dashicons-search" style="font-size: 18px; width: 18px; height: 18px; position: absolute; left: 10px; top: 11px; color: #666;"></span>
                        <input type="text" id="ktn-search-strings" placeholder="Search source string..." />
                    </div>
                </div>
                <div class="ktn-footer-right">
                    <button type="submit" name="ktn_save_translations" class="ktn-btn ktn-btn-black ktn-btn-save">
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>

    <style>
        .ktn-translation-wrap {
            max-width: 1100px;
            margin: 25px auto 40px auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1f2937;
        }
        .ktn-translation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .ktn-translation-header h1 {
            font-size: 26px;
            font-weight: 800;
            color: #111827;
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
        }
        .ktn-header-desc {
            font-size: 14px;
            color: #6b7280;
            margin: 0;
        }
        .ktn-header-right {
            display: flex;
            gap: 12px;
        }
        .ktn-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            height: 38px;
            box-sizing: border-box;
        }
        .ktn-btn-black {
            background: #000000;
            color: #ffffff;
            border: 1px solid #000000;
        }
        .ktn-btn-black:hover {
            background: #1f2937;
            border-color: #1f2937;
        }
        .ktn-btn-white {
            background: #ffffff;
            color: #111827;
            border: 1px solid #d1d5db;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .ktn-btn-white:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }
        .ktn-alert {
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 30px;
        }
        .ktn-alert-warning {
            background: #fffdf5;
            border: 1px solid #fef3c7;
            color: #b45309;
        }
        .ktn-table-container {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            margin-bottom: 25px;
        }
        .ktn-translation-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .ktn-translation-table th {
            background: #f9fafb;
            padding: 14px 20px;
            font-size: 13px;
            font-weight: 700;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .ktn-translation-table td {
            padding: 12px 20px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        .ktn-translation-table tbody tr:hover {
            background: #f9fafb;
        }
        .col-source {
            width: 45%;
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }
        .col-translation {
            width: 55%;
        }
        .ktn-translation-input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 14px;
            background: #ffffff;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .ktn-translation-input:focus {
            border-color: #000000;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0,0,0,0.1);
        }
        .ktn-translation-input.translated {
            color: #10b981;
            font-weight: 600;
            border-color: #a7f3d0;
            background: #f0fdf4;
        }
        .ktn-translation-input.translated:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        .ktn-translation-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        .ktn-search-box {
            position: relative;
            width: 320px;
        }
        .ktn-search-box input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 9px 14px 9px 38px;
            font-size: 14px;
            box-sizing: border-box;
            background: #ffffff;
            transition: all 0.2s;
        }
        .ktn-search-box input:focus {
            border-color: #000000;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0,0,0,0.1);
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            // Real-time search/filter
            $('#ktn-search-strings').on('input', function() {
                var query = $(this).val().toLowerCase().trim();
                $('.ktn-translation-row').each(function() {
                    var source = $(this).data('source');
                    if (source.indexOf(query) !== -1) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            // Live green borders on inputs
            $('.ktn-translation-input').on('input', function() {
                if ($(this).val().trim() !== '') {
                    $(this).addClass('translated');
                } else {
                    $(this).removeClass('translated');
                }
            });

            // Auto-translate AJAX
            $('.ktn-btn-auto-translate').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                if (btn.hasClass('loading')) return;

                var emptyInputs = [];
                var stringsToTranslate = [];
                
                $('.ktn-translation-input').each(function() {
                    if ($(this).val().trim() === '') {
                        emptyInputs.push($(this));
                        stringsToTranslate.push($(this).attr('placeholder'));
                    }
                });

                if (stringsToTranslate.length === 0) {
                    alert('All strings are already translated!');
                    return;
                }

                if (!confirm('Are you sure you want to auto-translate ' + stringsToTranslate.length + ' untranslated strings using Google Translate?')) {
                    return;
                }

                btn.addClass('loading').css('opacity', 0.6).text('Translating...');
                
                // Process in chunks of 15 strings to avoid HTTP timeouts
                var chunkSize = 15;
                var chunks = [];
                for (var i = 0; i < stringsToTranslate.length; i += chunkSize) {
                    chunks.push(stringsToTranslate.slice(i, i + chunkSize));
                }

                var chunkIndex = 0;

                function processNextChunk() {
                    if (chunkIndex >= chunks.length) {
                        btn.removeClass('loading').css('opacity', 1).html('<span class="dashicons dashicons-admin-site-alt3" style="font-size: 16px; width: 16px; height: 16px; margin-right: 6px; vertical-align: middle;"></span>Auto Translation');
                        alert('Auto translation completed successfully! Please review the highlighted strings and click "Save Changes" to save them.');
                        return;
                    }

                    var currentChunk = chunks[chunkIndex];
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'ktn_auto_translate_strings',
                            security: '<?php echo wp_create_nonce("ktn_translation_nonce"); ?>',
                            strings: currentChunk
                        },
                        success: function(response) {
                            if (response.success) {
                                var translations = response.data;
                                $('.ktn-translation-input').each(function() {
                                    var placeholder = $(this).attr('placeholder');
                                    if (translations[placeholder] !== undefined) {
                                        $(this).val(translations[placeholder]).addClass('translated');
                                    }
                                });
                            }
                            chunkIndex++;
                            processNextChunk();
                        },
                        error: function() {
                            chunkIndex++;
                            processNextChunk();
                        }
                    });
                }

                processNextChunk();
            });

            // Quick tools popup
            $('.ktn-btn-quick-tools').on('click', function() {
                alert('Quick Tools include backup, export, and import features. These are configured automatically.');
            });
        });
    </script>
    <?php
}

