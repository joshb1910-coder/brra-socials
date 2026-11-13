<?php
/**
 * Contact form: [brra_contact]. Messages are emailed to mail@buryrefs.org.uk (the brra_contact_to
 * option can change that). Nothing is stored on the site.
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		add_shortcode( 'brra_contact', 'brra_shortcode_contact' );
		add_shortcode( 'brra_map', 'brra_shortcode_map' );
	}
);

/**
 * Topics people can pick.
 */
function brra_contact_topics() {
	return array( 'General question', 'Membership', 'Becoming a referee', 'Events and Development Sessions', 'Partnerships and sponsorship', 'Something else' );
}

/**
 * The form.
 */
function brra_shortcode_contact() {
	$state = isset( $_GET['contact'] ) ? sanitize_key( $_GET['contact'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	ob_start();
	?>
	<div class="brra-rsvp brra-contact" id="contact-form">
		<?php if ( 'sent' === $state ) : ?>
			<h2 style="margin:0;font-size:24px">Thanks, message sent</h2>
			<p class="brra-notice">We've got it and one of the committee will reply by email, usually within 72 hours.</p>
		<?php else : ?>
			<h2 style="margin:0;font-size:24px">Send us a message</h2>
			<?php if ( 'error' === $state ) : ?>
				<p class="brra-notice">Sorry, that didn't send. Please check you've filled in every box, or email us at <a href="mailto:mail@buryrefs.org.uk">mail@buryrefs.org.uk</a>.</p>
			<?php endif; ?>
			<form class="brra-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:grid;gap:14px">
				<input type="hidden" name="action" value="brra_contact">
				<input type="hidden" name="back" value="<?php echo esc_url( get_permalink() ); ?>">
				<?php wp_nonce_field( 'brra_contact', '_brra_contact' ); ?>
				<label>Your name <input type="text" name="c_name" required autocomplete="name" maxlength="100"></label>
				<label>Email <input type="email" name="c_email" required autocomplete="email" maxlength="150"></label>
				<label>What's it about?
					<select name="c_topic">
						<?php foreach ( brra_contact_topics() as $t ) : ?>
							<option><?php echo esc_html( $t ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Message <textarea name="c_message" rows="6" required maxlength="5000"></textarea></label>
				<label style="position:absolute;left:-9999px" aria-hidden="true">Leave blank <input type="text" name="c_website" tabindex="-1" autocomplete="off"></label>
				<p style="margin:0;font-size:13px;color:var(--wp--preset--color--slate)">We only use your details to reply. See our <a href="<?php echo esc_url( home_url( '/privacy-notice/' ) ); ?>">privacy notice</a>.</p>
				<p style="margin:0"><button class="brra-btn" type="submit">Send message</button></p>
			</form>
		<?php endif; ?>
	</div>
	<?php
	return preg_replace( '/>\s+</', '><', ob_get_clean() );
}

/**
 * Send it.
 */
function brra_handle_contact() {
	$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : home_url( '/contact/' );
	$back = wp_validate_redirect( $back, home_url( '/contact/' ) );
	$go   = function ( $state ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'contact', $state, $back ) . '#contact-form' );
		exit;
	};
	if ( ! isset( $_POST['_brra_contact'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_brra_contact'] ), 'brra_contact' ) ) {
		$go( 'error' );
	}
	// Bots fill the hidden box: pretend it worked.
	if ( ! empty( $_POST['c_website'] ) ) {
		$go( 'sent' );
	}
	$name    = isset( $_POST['c_name'] ) ? sanitize_text_field( wp_unslash( $_POST['c_name'] ) ) : '';
	$email   = isset( $_POST['c_email'] ) ? sanitize_email( wp_unslash( $_POST['c_email'] ) ) : '';
	$topic   = isset( $_POST['c_topic'] ) ? sanitize_text_field( wp_unslash( $_POST['c_topic'] ) ) : '';
	$message = isset( $_POST['c_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['c_message'] ) ) : '';
	if ( ! $name || ! is_email( $email ) || ! $message ) {
		$go( 'error' );
	}
	if ( ! in_array( $topic, brra_contact_topics(), true ) ) {
		$topic = 'General question';
	}
	// At most 5 messages an hour from one address, to slow down spam.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'brra_contact_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		$go( 'error' );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );

	$to      = get_option( 'brra_contact_to', 'mail@buryrefs.org.uk' );
	$subject = 'Website message: ' . $topic . ' (from ' . $name . ')';
	$body    = "Name: {$name}\nEmail: {$email}\nAbout: {$topic}\n\n{$message}\n\n--\nSent from the contact form on " . home_url( '/' ) . ". Reply to this email to answer {$name} directly.";
	$headers = array(
		'Reply-To: ' . str_replace( array( "\r", "\n", '<', '>', '"' ), '', $name ) . ' <' . $email . '>',
	);
	$ok = wp_mail( $to, $subject, $body, $headers );
	$go( $ok ? 'sent' : 'error' );
}
add_action( 'admin_post_brra_contact', 'brra_handle_contact' );
add_action( 'admin_post_nopriv_brra_contact', 'brra_handle_contact' );

/**
 * [brra_map address="..."]: a Google Map of the venue.
 *
 * @param array $atts Shortcode attributes.
 */
function brra_shortcode_map( $atts ) {
	$atts = shortcode_atts( array( 'address' => "St Mary's Social Club, Pine Street, Radcliffe, M26 2WQ" ), $atts );
	$src  = 'https://www.google.com/maps?q=' . rawurlencode( $atts['address'] ) . '&output=embed';
	$link = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $atts['address'] );
	return '<div class="brra-map"><iframe src="' . esc_url( $src ) . '" title="Map showing ' . esc_attr( $atts['address'] ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>'
		. '<p class="brra-map__link"><a href="' . esc_url( $link ) . '" target="_blank" rel="noreferrer noopener">Open in Google Maps for directions</a></p>';
}
