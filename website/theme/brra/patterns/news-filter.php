<?php
/**
 * Title: News category filter
 * Slug: brra/news-filter
 * Inserter: no
 *
 * @package brra
 */

$brra_current = is_category() ? get_queried_object_id() : 0;
$brra_posts   = (int) get_option( 'page_for_posts' );
$brra_links   = array( '<a href="' . esc_url( $brra_posts ? get_permalink( $brra_posts ) : home_url( '/' ) ) . '"' . ( $brra_current ? '' : ' class="is-active" aria-current="page"' ) . '>All posts</a>' );
foreach ( get_categories( array( 'hide_empty' => true, 'exclude' => array( (int) get_option( 'default_category' ) ) ) ) as $brra_cat ) {
	$brra_active  = $brra_current === $brra_cat->term_id;
	$brra_links[] = '<a href="' . esc_url( get_category_link( $brra_cat ) ) . '"' . ( $brra_active ? ' class="is-active" aria-current="page"' : '' ) . '>' . esc_html( $brra_cat->name ) . '</a>';
}
?>
<!-- wp:html -->
<nav class="brra-filter" aria-label="Filter by category"><?php echo implode( '', $brra_links ); // phpcs:ignore WordPress.Security.EscapeOutput ?></nav>
<!-- /wp:html -->
