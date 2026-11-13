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

// Main menu items, then the "More" dropdown. Add new pages to $brra_more.
$brra_nav  = array(
	array( 'label' => 'Home', 'path' => '' ),
	array( 'label' => 'About Us', 'path' => 'about-us' ),
	array( 'label' => 'Upcoming Events', 'path' => 'upcoming-events' ),
	array( 'label' => 'Membership', 'path' => 'membership' ),
);
$brra_more = array(
	array( 'label' => 'Become a Referee', 'path' => 'become-a-referee' ),
	array( 'label' => 'News', 'path' => 'news' ),
	array( 'label' => 'Contact Us', 'path' => 'contact' ),
);
$brra_link = function ( $item, $top ) {
	$page = $item['path'] ? get_page_by_path( $item['path'] ) : get_post( (int) get_option( 'page_on_front' ) );
	$attr = array(
		'label'          => $item['label'],
		'url'            => $page ? get_permalink( $page ) : home_url( '/' . $item['path'] ),
		'isTopLevelLink' => $top,
	);
	if ( $page ) {
		$attr['type'] = 'page';
		$attr['id']   = $page->ID;
		$attr['kind'] = 'post-type';
	}
	return '<!-- wp:navigation-link ' . wp_json_encode( $attr ) . ' /-->';
};
$brra_links = '';
foreach ( $brra_nav as $brra_item ) {
	$brra_links .= $brra_link( $brra_item, true );
}
$brra_links .= '<!-- wp:navigation-submenu {"label":"More","url":"#","kind":"custom","isTopLevelItem":true} -->';
foreach ( $brra_more as $brra_item ) {
	$brra_links .= $brra_link( $brra_item, false );
}
$brra_links .= '<!-- /wp:navigation-submenu -->';
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
<!-- wp:navigation {"overlayMenu":"mobile","openSubmenusOnClick":true,"layout":{"type":"flex","justifyContent":"right"},"style":{"spacing":{"blockGap":"26px"}}} -->
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
