<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_notices', 'ktn_admin_notices' );
function ktn_admin_notices() {
	$token = get_option('ktn_tmdb_bearer_token');
	if ( empty( $token ) ) {
		$settings_url = admin_url( 'options-general.php?page=kontentainment-settings' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php printf( __( '<strong>Kontentainment Importer:</strong> TMDB Bearer Token is not configured. Please <a href="%s">configure your settings</a>.', 'kontentainment' ), esc_url( $settings_url ) ); ?></p>
		</div>
		<?php
	}
}
