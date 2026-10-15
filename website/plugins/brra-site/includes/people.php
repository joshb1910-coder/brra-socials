<?php
/**
 * Committee: one entry per person (name, role, optional photo), shown with [brra_committee].
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		register_post_type(
			'brra_person',
			array(
				'labels'       => array(
					'name'          => __( 'Committee', 'brra' ),
					'singular_name' => __( 'Committee member', 'brra' ),
					'add_new_item'  => __( 'Add committee member', 'brra' ),
					'edit_item'     => __( 'Edit committee member', 'brra' ),
					'all_items'     => __( 'Committee', 'brra' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-groups',
				'supports'     => array( 'title', 'excerpt', 'thumbnail', 'page-attributes' ),
			)
		);
		add_shortcode( 'brra_committee', 'brra_shortcode_committee' );
	}
);

// Clearer labels: the title is the name, the excerpt is the role.
add_filter(
	'enter_title_here',
	function ( $text, $post ) {
		return 'brra_person' === $post->post_type ? __( 'Name', 'brra' ) : $text;
	},
	10,
	2
);

/**
 * Initials for a name, e.g. "AC".
 *
 * @param string $name Full name.
 */
function brra_initials( $name ) {
	$out = '';
	foreach ( preg_split( '/\s+/', trim( $name ) ) as $part ) {
		$out .= mb_strtoupper( mb_substr( $part, 0, 1 ) );
	}
	return mb_substr( $out, 0, 2 );
}

/**
 * [brra_committee]
 */
function brra_shortcode_committee() {
	$people = get_posts(
		array(
			'post_type'      => 'brra_person',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		)
	);
	if ( ! $people ) {
		return '';
	}
	$out = '<div class="brra-people">';
	foreach ( $people as $p ) {
		$photo = get_the_post_thumbnail_url( $p, 'thumbnail' );
		$name  = get_the_title( $p );
		$out  .= '<div class="brra-person"><div class="brra-person__avatar">';
		$out  .= $photo ? '<img src="' . esc_url( $photo ) . '" alt="">' : esc_html( 'Vacant' === $name ? '?' : brra_initials( $name ) );
		$out  .= '</div><div><span class="brra-person__name">' . esc_html( $name ) . '</span><span class="brra-person__role">' . esc_html( $p->post_excerpt ) . '</span></div></div>';
	}
	return $out . '</div>';
}
