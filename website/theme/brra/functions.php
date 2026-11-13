<?php
/**
 * BRRA theme setup.
 *
 * @package brra
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'editor-styles' );
		add_editor_style( 'style.css' );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'brra', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
	}
);

add_action(
	'init',
	function () {
		register_block_style( 'core/paragraph', array( 'name' => 'eyebrow', 'label' => __( 'Eyebrow label', 'brra' ) ) );
		register_block_style( 'core/group', array( 'name' => 'card', 'label' => __( 'Card', 'brra' ) ) );
		register_block_style( 'core/group', array( 'name' => 'card-mist', 'label' => __( 'Card (grey)', 'brra' ) ) );
		register_block_style( 'core/group', array( 'name' => 'raised', 'label' => __( 'Raised card', 'brra' ) ) );
		register_block_style( 'core/button', array( 'name' => 'outline-light', 'label' => __( 'Outline (on navy)', 'brra' ) ) );
		register_block_style( 'core/button', array( 'name' => 'dark', 'label' => __( 'Navy', 'brra' ) ) );
		register_block_style( 'core/button', array( 'name' => 'text-link', 'label' => __( 'Text link', 'brra' ) ) );

		register_block_pattern_category( 'brra', array( 'label' => __( 'Bury & Rochdale RA', 'brra' ) ) );
	}
);

/**
 * URL of a file in the theme's assets folder.
 *
 * @param string $path Path under assets/.
 */
function brra_asset( $path ) {
	return esc_url( get_theme_file_uri( 'assets/' . $path ) );
}
