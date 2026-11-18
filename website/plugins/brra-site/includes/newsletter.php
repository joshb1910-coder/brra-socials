<?php
/**
 * Newsletter sign-up: [brra_newsletter] box, and Users > Newsletter list for sending from newsletter@ in webmail.
 *
 * Guests who tick "happy to be contacted" when they check in at an event are added here too.
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add someone to the newsletter list (ignored if already there).
 *
 * @param string $email  Email.
 * @param string $name   Name.
 * @param string $source website or guest.
 */
function brra_newsletter_add( $email, $name = '', $source = 'website' ) {
	global $wpdb;
	$email = strtolower( sanitize_email( $email ) );
	if ( ! is_email( $email ) ) {
		return false;
	}
	$table = brra_subscribers_table();
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE email = %s", $email ) ) ) { // phpcs:ignore WordPress.DB
		return false;
	}
	return (bool) $wpdb->insert( $table, array( 'email' => $email, 'name' => $name, 'source' => $source, 'created' => current_time( 'mysql' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

add_action(
	'init',
	function () {
		add_shortcode( 'brra_newsletter', 'brra_shortcode_newsletter' );
	}
);

/**
 * [brra_newsletter] sign-up box.
 */
function brra_shortcode_newsletter() {
	$done = isset( $_GET['newsletter'] ) && 'thanks' === $_GET['newsletter']; // phpcs:ignore WordPress.Security.NonceVerification
	ob_start();
	?>
	<div class="brra-newsletter" id="newsletter">
		<div class="brra-newsletter__text">
			<h2>Get our event news</h2>
			<p>Guest speakers, socials and referee courses, straight to your inbox about once a month. Free, and you don't need to be a member.</p>
		</div>
		<?php if ( $done ) : ?>
			<p class="brra-notice">Thanks, you're signed up. Look out for our next email.</p>
		<?php else : ?>
			<form class="brra-newsletter__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="brra_newsletter">
				<input type="hidden" name="back" value="<?php echo esc_attr( get_permalink() ); ?>">
				<?php wp_nonce_field( 'brra_newsletter' ); ?>
				<label class="screen-reader-text" for="nl_name">First name</label>
				<input id="nl_name" type="text" name="nl_name" placeholder="First name" autocomplete="given-name">
				<label class="screen-reader-text" for="nl_email">Email</label>
				<input id="nl_email" type="email" name="nl_email" placeholder="Email address" required autocomplete="email">
				<input type="text" name="nl_website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
				<button class="brra-btn is-gold" type="submit">Sign me up</button>
				<small>Unsubscribe any time by replying "unsubscribe" to any email. See our <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">privacy notice</a>.</small>
			</form>
		<?php endif; ?>
	</div>
	<?php
	return preg_replace( '/>\s+</', '><', ob_get_clean() );
}

/**
 * Save a newsletter sign-up.
 */
function brra_handle_newsletter() {
	$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : home_url( '/' ); // phpcs:ignore WordPress.Security.NonceVerification
	$back = wp_validate_redirect( $back, home_url( '/' ) );
	if ( ! check_admin_referer( 'brra_newsletter' ) ) {
		wp_die( 'Sorry, something went wrong. Please go back and try again.' );
	}
	if ( empty( $_POST['nl_website'] ) ) {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$limit = 'brra_nl_' . md5( $ip );
		$count = (int) get_transient( $limit );
		if ( $count < 10 ) {
			set_transient( $limit, $count + 1, HOUR_IN_SECONDS );
			brra_newsletter_add(
				isset( $_POST['nl_email'] ) ? sanitize_email( wp_unslash( $_POST['nl_email'] ) ) : '',
				isset( $_POST['nl_name'] ) ? sanitize_text_field( wp_unslash( $_POST['nl_name'] ) ) : ''
			);
		}
	}
	wp_safe_redirect( add_query_arg( 'newsletter', 'thanks', $back ) . '#newsletter' );
	exit;
}
add_action( 'admin_post_brra_newsletter', 'brra_handle_newsletter' );
add_action( 'admin_post_nopriv_brra_newsletter', 'brra_handle_newsletter' );

// Users > Newsletter list.
add_action(
	'admin_menu',
	function () {
		add_users_page( 'Newsletter list', 'Newsletter list', 'list_users', 'brra-newsletter', 'brra_newsletter_page' );
	}
);

/**
 * The Newsletter list screen.
 */
function brra_newsletter_page() {
	global $wpdb;
	$table = brra_subscribers_table();
	$rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY created DESC" ); // phpcs:ignore WordPress.DB
	echo '<div class="wrap"><h1>Newsletter list</h1>';
	echo '<p>People who signed up on the website, plus guests who said they\'re happy to be contacted when they checked in at an event. Members get the members\' emails as usual. <strong>' . count( $rows ) . '</strong> on the list.</p>';
	if ( isset( $_GET['removed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success inline"><p>Removed.</p></div>';
	}
	if ( ! $rows ) {
		echo '<p>No one yet.</p></div>';
		return;
	}
	$emails = implode( ', ', wp_list_pluck( $rows, 'email' ) );
	echo '<h2>To send an email</h2><p>Log in to newsletter@buryrefs.org.uk, start a new email to yourself and paste these addresses into <strong>Bcc</strong>, so nobody sees anyone else\'s address.</p>';
	echo '<textarea readonly rows="4" style="width:100%;max-width:900px" onclick="this.select()">' . esc_textarea( $emails ) . '</textarea>';
	echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=brra_newsletter_csv' ), 'brra_newsletter_csv' ) ) . '">Download as a spreadsheet</a></p>';
	echo '<h2>Everyone on the list</h2><p>If someone replies "unsubscribe", remove them here.</p><table class="widefat striped" style="max-width:900px"><thead><tr><th>Email</th><th>Name</th><th>Signed up</th><th>From</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$del = wp_nonce_url( admin_url( 'admin-post.php?action=brra_newsletter_remove&id=' . $r->id ), 'brra_newsletter_remove' );
		echo '<tr><td>' . esc_html( $r->email ) . '</td><td>' . esc_html( $r->name ) . '</td><td>' . esc_html( mysql2date( 'j M Y', $r->created ) ) . '</td><td>' . ( 'guest' === $r->source ? 'Event guest' : 'Website' ) . '</td><td><a href="' . esc_url( $del ) . '">Remove</a></td></tr>';
	}
	echo '</tbody></table></div>';
}

add_action(
	'admin_post_brra_newsletter_remove',
	function () {
		global $wpdb;
		if ( ! current_user_can( 'list_users' ) ) {
			wp_die( 'Sorry, you are not allowed to do that.' );
		}
		check_admin_referer( 'brra_newsletter_remove' );
		$wpdb->delete( brra_subscribers_table(), array( 'id' => isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_safe_redirect( admin_url( 'users.php?page=brra-newsletter&removed=1' ) );
		exit;
	}
);

add_action(
	'admin_post_brra_newsletter_csv',
	function () {
		global $wpdb;
		if ( ! current_user_can( 'list_users' ) ) {
			wp_die( 'Sorry, you are not allowed to do that.' );
		}
		check_admin_referer( 'brra_newsletter_csv' );
		$table = brra_subscribers_table();
		$rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY created" ); // phpcs:ignore WordPress.DB
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="newsletter-list.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'Email', 'Name', 'Signed up', 'From' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, array( $r->email, $r->name, $r->created, 'guest' === $r->source ? 'Event guest' : 'Website' ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
);
