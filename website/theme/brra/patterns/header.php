<?php
/**
 * Title: Header
 * Slug: brra/header
 * Categories: header
 * Block Types: core/template-part/header
 * Inserter: no
 *
 * @package brra
 */

$brra_nav = array(
	array( 'label' => 'Home', 'path' => '' ),
	array( 'label' => 'About Us', 'path' => 'about-us' ),
	array( 'label' => 'Upcoming Events', 'path' => 'upcoming-events' ),
	array( 'label' => 'Membership', 'path' => 'membership' ),
	array( 'label' => 'News', 'path' => 'news' ),
);
$brra_links = '';
foreach ( $brra_nav as $brra_item ) {
	$brra_page = $brra_item['path'] ? get_page_by_path( $brra_item['path'] ) : get_post( (int) get_option( 'page_on_front' ) );
	$brra_attr = array(
		'label'          => $brra_item['label'],
		'url'            => $brra_page ? get_permalink( $brra_page ) : home_url( '/' . $brra_item['path'] ),
		'isTopLevelLink' => true,
	);
	if ( $brra_page ) {
		$brra_attr['type'] = 'page';
		$brra_attr['id']   = $brra_page->ID;
		$brra_attr['kind'] = 'post-type';
	}
	$brra_links .= '<!-- wp:navigation-link ' . wp_json_encode( $brra_attr ) . ' /-->';
}
$brra_join = get_page_by_path( 'membership' );
?>
<!-- wp:group {"tagName":"header","className":"brra-header","backgroundColor":"navy","textColor":"white","layout":{"type":"constrained"}} -->
<header class="wp-block-group brra-header has-white-color has-navy-background-color has-text-color has-background">
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"14px","bottom":"14px"}}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide" style="padding-top:14px;padding-bottom:14px">
<!-- wp:html -->
<a class="brra-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><img src="<?php echo brra_asset( 'images/crest-white.png' ); ?>" alt="Bury &amp; Rochdale Referees' Association crest" width="44" height="54"><span><span class="brra-brand__name">BURY &amp; ROCHDALE RA</span><span class="brra-brand__sub">REFEREES' ASSOCIATION · EST 1898</span></span></a>
<!-- /wp:html -->
<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap"},"style":{"spacing":{"blockGap":"26px"}}} -->
<div class="wp-block-group">
<!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","justifyContent":"right"},"style":{"spacing":{"blockGap":"26px"}}} -->
<?php echo $brra_links; // phpcs:ignore WordPress.Security.EscapeOutput ?>
<!-- /wp:navigation -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $brra_join ? get_permalink( $brra_join ) . '#join' : home_url( '/membership/#join' ) ); ?>">Join now</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
</header>
<!-- /wp:group -->
