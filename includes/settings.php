<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'ktn_add_settings_page' );
function ktn_add_settings_page() {
	add_options_page(
		__( 'Kontentainment Settings', 'kontentainment' ),
		__( 'Kontentainment Importer', 'kontentainment' ),
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
	</div>
	<?php
}
