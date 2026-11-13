<?php
/**
 * Moving from the old Wix site: old links, cover images and a tool to copy Wix images into the media library.
 *
 * Imported posts keep their Wix cover in `_brra_cover_url` and may have images in their content
 * that still point at static.wixstatic.com. They show as they are until "Copy Wix images" is run
 * (Tools > Copy Wix images, or `wp brra copy-wix-images`), which downloads them into the media library.
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * Use the Wix cover as the featured image until it has been copied across.
 */
add_filter(
	'post_thumbnail_html',
	function ( $html, $post_id, $thumbnail_id, $size, $attr ) {
		if ( $html ) {
			return $html;
		}
		$url = get_post_meta( $post_id, '_brra_cover_url', true );
		if ( ! $url ) {
			return $html;
		}
		$class = is_array( $attr ) && ! empty( $attr['class'] ) ? $attr['class'] : 'wp-post-image';
		$style = is_array( $attr ) && ! empty( $attr['style'] ) ? $attr['style'] : '';
		return sprintf(
			'<img src="%s" alt="%s" class="%s" style="%s" loading="lazy" decoding="async">',
			esc_url( $url ),
			esc_attr( get_the_title( $post_id ) ),
			esc_attr( $class ),
			esc_attr( $style )
		);
	},
	10,
	5
);

add_filter(
	'has_post_thumbnail',
	function ( $has, $post ) {
		if ( $has || ! $post ) {
			return $has;
		}
		return (bool) get_post_meta( is_object( $post ) ? $post->ID : (int) $post, '_brra_cover_url', true );
	},
	10,
	2
);

/**
 * Send old Wix addresses to their new home: /post/<slug>, /blog and /blog/categories/<slug>.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! is_404() ) {
			return;
		}
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = rawurldecode( $path );
		$to   = '';
		if ( preg_match( '#^post/([^/]+)#', $path, $m ) ) {
			$post = get_page_by_path( sanitize_title( $m[1] ), OBJECT, 'post' );
			$to   = $post ? get_permalink( $post ) : '';
		} elseif ( preg_match( '#^blog/categories/([^/]+)#', $path, $m ) ) {
			$map  = array( 'exclusive-posts' => 'exclusive' );
			$term = get_category_by_slug( $map[ $m[1] ] ?? $m[1] );
			$to   = $term ? get_category_link( $term ) : '';
		} elseif ( preg_match( '#^blog(/|$)#', $path ) ) {
			$page = (int) get_option( 'page_for_posts' );
			$to   = $page ? get_permalink( $page ) : '';
		} elseif ( preg_match( '#^(event-details|events)/#', $path ) ) {
			$page = get_page_by_path( 'upcoming-events' );
			$to   = $page ? get_permalink( $page ) : '';
		}
		if ( $to ) {
			wp_safe_redirect( $to, 301 );
			exit;
		}
	}
);

/**
 * Copy Wix images for a few posts at a time.
 *
 * @param int $limit Posts to process in this run.
 * @return array{done:int,left:int,errors:string[]}
 */
function brra_copy_wix_images( $limit = 10 ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$args   = array(
		'post_type'      => 'post',
		'post_status'    => 'any',
		'posts_per_page' => $limit,
		'meta_query'     => array( array( 'key' => '_brra_wix_pending', 'value' => '1' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		'fields'         => 'ids',
	);
	$errors = array();
	$done   = 0;
	foreach ( get_posts( $args ) as $id ) {
		$ok    = true;
		$cover = get_post_meta( $id, '_brra_cover_url', true );
		if ( $cover && ! get_post_thumbnail_id( $id ) ) {
			$att = media_sideload_image( $cover, $id, get_the_title( $id ), 'id' );
			if ( is_wp_error( $att ) ) {
				$ok       = false;
				$errors[] = get_the_title( $id ) . ': ' . $att->get_error_message();
			} else {
				set_post_thumbnail( $id, $att );
				delete_post_meta( $id, '_brra_cover_url' );
			}
		}
		$content = get_post_field( 'post_content', $id );
		if ( preg_match_all( '#https://static\.wixstatic\.com/media/[^"\'\s<>)]+#', $content, $m ) ) {
			foreach ( array_unique( $m[0] ) as $url ) {
				$new = media_sideload_image( $url, $id, '', 'src' );
				if ( is_wp_error( $new ) ) {
					$ok       = false;
					$errors[] = get_the_title( $id ) . ': ' . $new->get_error_message();
					continue;
				}
				$content = str_replace( $url, wp_make_link_relative( $new ), $content );
			}
			wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content ) ) );
		}
		if ( $ok ) {
			delete_post_meta( $id, '_brra_wix_pending' );
			++$done;
		}
	}
	$left = count(
		get_posts( array_merge( $args, array( 'posts_per_page' => -1 ) ) )
	);
	return array( 'done' => $done, 'left' => $left, 'errors' => $errors );
}

add_action(
	'admin_menu',
	function () {
		add_management_page( 'Copy Wix images', 'Copy Wix images', 'manage_options', 'brra-wix-images', 'brra_wix_images_page' );
	}
);

/**
 * Tools > Copy Wix images.
 */
function brra_wix_images_page() {
	$result = null;
	if ( isset( $_POST['brra_copy'] ) && check_admin_referer( 'brra_copy_wix_images' ) ) {
		$result = brra_copy_wix_images( 10 );
	}
	$left = $result ? $result['left'] : count(
		get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => '_brra_wix_pending', 'value' => '1' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		)
	);
	?>
	<div class="wrap">
		<h1>Copy Wix images</h1>
		<p>Posts moved from the old Wix site still show their pictures from Wix. This copies them into the media library, 10 posts at a time, so they keep working after the Wix site closes.</p>
		<?php if ( $result ) : ?>
			<div class="notice notice-<?php echo $result['errors'] ? 'warning' : 'success'; ?>"><p>Copied images for <?php echo (int) $result['done']; ?> posts.</p>
			<?php foreach ( $result['errors'] as $e ) : ?>
				<p><?php echo esc_html( $e ); ?></p>
			<?php endforeach; ?></div>
		<?php endif; ?>
		<p><strong><?php echo (int) $left; ?></strong> posts still to copy.</p>
		<?php if ( $left ) : ?>
			<form method="post"><?php wp_nonce_field( 'brra_copy_wix_images' ); ?>
				<?php submit_button( 'Copy the next 10', 'primary', 'brra_copy' ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'brra copy-wix-images',
		function () {
			do {
				$r = brra_copy_wix_images( 10 );
				foreach ( $r['errors'] as $e ) {
					WP_CLI::warning( $e );
				}
				WP_CLI::log( "Copied {$r['done']}, {$r['left']} left." );
			} while ( $r['left'] && $r['done'] );
			WP_CLI::success( 'Done.' );
		}
	);
}
