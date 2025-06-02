<?php

/*
Plugin Name: Bulk Image Generator
Description: A plugin to bulk add placeholder images to the WordPress media gallery for testing purposes.
Author: SirLouen <sir.louen@gmail.com>
Version: 1.0.0
License: GPL-2.0+
Text Domain: bulk-image-generator
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Download and save an image from a URL.
 *
 * @since 1.0.0
 *
 * @param string $image_url The URL of the image to download.
 * @param int    $width     The width of the image.
 * @param int    $height    The height of the image.
 *
 * @return array|false Array with file info on success, false on failure.
 */
function bulk_image_generator_download_and_save_image( $image_url, $width, $height ) {
	$upload_dir = wp_upload_dir();
	$filename   = 'placeholder_' . absint( $width ) . 'x' . absint( $height ) . '_' . uniqid() . '.png';
	$file_path  = $upload_dir['path'] . '/' . $filename;

	$response = wp_remote_get(
		$image_url,
		array(
			'timeout'   => 30,
			'sslverify' => false,
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$image_data = wp_remote_retrieve_body( $response );

	if ( empty( $image_data ) ) {
		return false;
	}

	$saved = file_put_contents( $file_path, $image_data );

	if ( ! $saved ) {
		return false;
	}

	return array(
		'file' => $file_path,
		'url'  => $upload_dir['url'] . '/' . $filename,
		'type' => 'image/png',
	);
}

/**
 * Add an image to the WordPress media gallery.
 *
 * @since 1.0.0
 *
 * @param string $image_url The URL of the image to download.
 * @param int    $width     The width of the image.
 * @param int    $height    The height of the image.
 *
 * @return int|false Attachment ID on success, false on failure.
 */
function bulk_image_generator_add_image_to_media_gallery( $image_url, $width, $height ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$download_result = bulk_image_generator_download_and_save_image( $image_url, $width, $height );

	if ( ! $download_result ) {
		return false;
	}

	$file_path = $download_result['file'];
	$file_url  = $download_result['url'];

	$attachment = array(
		'guid'           => $file_url,
		'post_mime_type' => 'image/png',
		'post_title'     => sprintf(
			/* translators: %1$d: width, %2$d: height */
			__( 'Placeholder %1$dx%2$d', 'bulk-image-generator' ),
			absint( $width ),
			absint( $height )
		),
		'post_content'   => '',
		'post_status'    => 'inherit',
	);

	$attach_id = wp_insert_attachment( $attachment, $file_path );

	if ( is_wp_error( $attach_id ) ) {
		wp_delete_file( $file_path );
		return false;
	}

	$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
	wp_update_attachment_metadata( $attach_id, $attach_data );

	return $attach_id;
}

/**
 * Bulk add multiple placeholder images to the media gallery.
 *
 * @since 1.0.0
 *
 * @param int $count Number of images to add. Default 10.
 *
 * @return int Number of successfully added images.
 */
function bulk_image_generator_add_images_bulk( $count = 10 ) {
	$count      = max( 1, min( 500, absint( $count ) ) );
	$successful = 0;

	for ( $i = 0; $i < $count; $i++ ) {
		$rand_x    = wp_rand( 100, 500 );
		$rand_y    = wp_rand( 100, 500 );
		$image_url = 'https://placehold.co/' . $rand_x . 'x' . $rand_y . '.png';

		$result = bulk_image_generator_add_image_to_media_gallery( $image_url, $rand_x, $rand_y );
		if ( $result ) {
			$successful++;
		}
	}

	return $successful;
}

/**
 * Add admin menu for the plugin.
 *
 * @since 1.0.0
 */
function bulk_image_generator_admin_menu() {
	add_management_page(
		__( 'Bulk Image Generator', 'bulk-image-generator' ),
		__( 'Bulk Image Generator', 'bulk-image-generator' ),
		'manage_options',
		'bulk-image-generator',
		'bulk_image_generator_admin_page'
	);
}
add_action( 'admin_menu', 'bulk_image_generator_admin_menu' );

/**
 * Display the admin page for the plugin.
 *
 * @since 1.0.0
 */
function bulk_image_generator_admin_page() {
	if ( isset( $_POST['bulk_add_images'] ) && wp_verify_nonce( $_POST['bulk_image_nonce'], 'bulk_image_action' ) ) {
		$image_count = isset( $_POST['image_count'] ) ? absint( $_POST['image_count'] ) : 10;
		$added_count = bulk_image_generator_add_images_bulk( $image_count );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			sprintf(
				/* translators: %d: number of images added */
				esc_html__( 'Successfully added %d placeholder images to the media gallery.', 'bulk-image-generator' ),
				$added_count
			)
		);
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Bulk Image Generator', 'bulk-image-generator' ); ?></h1>
		<p><?php esc_html_e( 'This tool will add placeholder images to your WordPress media gallery for testing purposes.', 'bulk-image-generator' ); ?></p>

		<form method="post" action="">
			<?php wp_nonce_field( 'bulk_image_action', 'bulk_image_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Number of Images', 'bulk-image-generator' ); ?></th>
					<td>
						<input type="number" name="image_count" value="10" min="1" max="500" class="small-text" />
						<p class="description"><?php esc_html_e( 'Enter the number of placeholder images to add (1-500).', 'bulk-image-generator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Add Placeholder Images', 'bulk-image-generator' ); ?></th>
					<td>
						<input type="submit" name="bulk_add_images" class="button button-primary" value="<?php esc_attr_e( 'Add Placeholder Images', 'bulk-image-generator' ); ?>" />
						<p class="description"><?php esc_html_e( 'This will download and save the specified number of random placeholder PNG images from placehold.co to your media gallery.', 'bulk-image-generator' ); ?></p>
					</td>
				</tr>
			</table>
		</form>
	</div>
	<?php
}