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
			<label for="ktn_imdb_id"><strong><?php esc_html_e( 'IMDb ID:', 'kontentainment' ); ?></strong></label><br/>
			<input type="text" id="ktn_imdb_id" name="ktn_imdb_id" value="<?php echo esc_attr( $imdb_id ); ?>" class="widefat" placeholder="e.g. tt0111161" />
            <small class="description"><?php esc_html_e( 'Starts with "tt"', 'kontentainment' ); ?></small>
		</p>

        <p style="text-align: center; margin: 10px 0; color: #777; font-weight: bold; border-top: 1px solid #ddd; padding-top: 10px;">
            <?php esc_html_e( '— OR —', 'kontentainment' ); ?>
        </p>

		<p>
			<label for="ktn_tmdb_id"><strong><?php esc_html_e( 'TMDB ID:', 'kontentainment' ); ?></strong></label><br/>
			<input type="text" id="ktn_tmdb_id" name="ktn_tmdb_id" value="<?php echo esc_attr( $tmdb_id ); ?>" class="widefat" placeholder="e.g. 550" />
            <small class="description"><?php esc_html_e( 'Numeric ID from TMDB', 'kontentainment' ); ?></small>
		</p>
		
		<div id="ktn_import_status" style="margin: 15px 0; font-weight: bold; line-height: 1.4;"></div>

		<p>
			<button type="button" class="button button-primary ktn-import-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-action="import" style="width: 100%; margin-bottom: 5px; height: 32px;">
				<?php esc_html_e( 'Import Media', 'kontentainment' ); ?>
			</button>
			<?php if ( $imported_at ) : ?>
			<button type="button" class="button ktn-import-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-action="refresh" style="width: 100%; height: 32px;">
				<?php esc_html_e( 'Refresh from TMDB', 'kontentainment' ); ?>
			</button>
			<?php endif; ?>
		</p>
		
		<?php if ( $imported_at ) : ?>
		<p class="description" style="margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px;">
			<?php printf( esc_html__( 'Last imported: %s', 'kontentainment' ), '<strong>' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $imported_at ) . '</strong>' ); ?>
		</p>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', 'ktn_admin_enqueue_scripts' );
function ktn_admin_enqueue_scripts( $hook ) {
	global $post;
	if ( ( $hook == 'post-new.php' || $hook == 'post.php' ) && isset( $post->post_type ) ) {
		if ( in_array( $post->post_type, array('movie', 'tv_show') ) ) {
			wp_enqueue_style( 'ktn-admin-css', KTN_PLUGIN_URL . 'assets/css/admin.css', array(), KTN_PLUGIN_VERSION );
			wp_enqueue_script( 'ktn-admin-js', KTN_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), KTN_PLUGIN_VERSION, true );
			
			wp_localize_script( 'ktn-admin-js', 'ktnAdminObj', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ktn_import_nonce' )
			) );
		}
	}
}
