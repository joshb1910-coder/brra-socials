<?php
/**
 * Tools > BRRA site setup: builds the pages, events, committee, membership levels and news
 * on a fresh WordPress, using the setup files bundled in the launch pack (setup/build.php).
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		if ( file_exists( BRRA_SITE_DIR . '/setup/build.php' ) ) {
			add_management_page( 'BRRA site setup', 'BRRA site setup', 'manage_options', 'brra-setup', 'brra_setup_page' );
		}
	}
);

/**
 * The setup screen.
 */
function brra_setup_page() {
	$ready = function_exists( 'pmpro_getAllLevels' );
	$built = get_option( 'brra_built' );
	?>
	<div class="wrap">
		<h1>BRRA site setup</h1>
		<p>This builds the whole site in one go: Home, About Us, Upcoming Events, Membership and News, the events, the committee, the membership levels and every post from the old Wix site.</p>
		<?php if ( ! $ready ) : ?>
			<div class="notice notice-error"><p>Install and activate <strong>Paid Memberships Pro</strong> first (Plugins &gt; Add New), then come back here.</p></div>
		<?php else : ?>
			<?php
			if ( isset( $_POST['brra_build'] ) && check_admin_referer( 'brra_build' ) ) {
				if ( function_exists( 'set_time_limit' ) ) {
					set_time_limit( 600 );
				}
				ob_start();
				include BRRA_SITE_DIR . '/setup/build.php';
				$log = ob_get_clean();
				update_option( 'brra_built', current_time( 'mysql' ) );
				$built = get_option( 'brra_built' );
				echo '<div class="notice notice-success"><p>Done. <a href="' . esc_url( home_url( '/' ) ) . '">View the site</a>, then go to Tools &gt; Copy Wix images.</p></div>';
				if ( trim( $log ) ) {
					echo '<pre style="white-space:pre-wrap">' . esc_html( $log ) . '</pre>';
				}
			}
			?>
			<?php if ( $built ) : ?>
				<div class="notice notice-warning"><p>The site was built on <?php echo esc_html( $built ); ?>. Running it again resets Home, About Us, Upcoming Events and Membership to the original text, and puts back any events, committee entries or posts that were deleted.</p></div>
			<?php endif; ?>
			<form method="post" onsubmit="return confirm('Build the site now?');">
				<?php wp_nonce_field( 'brra_build' ); ?>
				<?php submit_button( $built ? 'Build again' : 'Build the site', 'primary', 'brra_build' ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
