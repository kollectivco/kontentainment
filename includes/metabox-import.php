<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes', 'ktn_add_import_metabox' );
function ktn_add_import_metabox() {
	add_meta_box(
		'ktn_import_meta_box',
		__( 'Import from TMDB', 'kontentainment' ),
		'ktn_import_meta_box_html',
		array( 'movie', 'tv_show' ),
		'side',
		'high'
	);
}

function ktn_import_meta_box_html( $post ) {
	$imdb_id     = get_post_meta( $post->ID, '_movie_imdb_id', true );
	$tmdb_id     = get_post_meta( $post->ID, '_movie_tmdb_id', true );
	$imported_at = get_post_meta( $post->ID, '_movie_last_imported_at', true );

	wp_nonce_field( 'ktn_save_meta_box_data', 'ktn_meta_box_nonce' );
	?>
	<div class="ktn-metabox-wrapper">
		<p>
			<label for="ktn_imdb_id"><?php esc_html_e( 'IMDb ID:', 'kontentainment' ); ?></label><br/>
			<input type="text" id="ktn_imdb_id" name="ktn_imdb_id" value="<?php echo esc_attr( $imdb_id ); ?>" class="widefat" placeholder="e.g. tt0111161" />
		</p>
		<?php if ( $tmdb_id ) : ?>
		<p>
			<label><?php esc_html_e( 'TMDB ID:', 'kontentainment' ); ?></label><br/>
			<input type="text" readonly value="<?php echo esc_attr( $tmdb_id ); ?>" class="widefat" />
		</p>
		<?php endif; ?>
		
		<div id="ktn_import_status" style="margin-bottom: 10px; font-weight: bold;"></div>

		<p>
			<button type="button" class="button button-primary ktn-import-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-action="import">
				<?php esc_html_e( 'Import Media', 'kontentainment' ); ?>
			</button>
			<?php if ( $imported_at ) : ?>
			<button type="button" class="button ktn-import-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-action="refresh">
				<?php esc_html_e( 'Refresh from TMDB', 'kontentainment' ); ?>
			</button>
			<?php endif; ?>
		</p>
		
		<?php if ( $imported_at ) : ?>
		<p class="description">
			<?php printf( esc_html__( 'Last imported at: %s', 'kontentainment' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $imported_at ) ); ?>
		</p>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', 'ktn_admin_enqueue_scripts' );
function ktn_admin_enqueue_scripts( $hook ) {
	global $post;
	if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
		if ( in_array( $post->post_type, array('movie', 'tv_show') ) ) {
			wp_enqueue_style( 'ktn-admin-css', KTN_PLUGIN_URL . 'assets/admin.css', array(), KTN_PLUGIN_VERSION );
			wp_enqueue_script( 'ktn-admin-js', KTN_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), KTN_PLUGIN_VERSION, true );
			
			wp_localize_script( 'ktn-admin-js', 'ktnAdminObj', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ktn_import_nonce' )
			) );
		}
	}
}
