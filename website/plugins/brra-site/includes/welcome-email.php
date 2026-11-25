<?php
/**
 * The welcome email new members get when they join online: WhatsApp community, the next
 * Development Session (picked from Events, so it moves on through the season), social links,
 * what membership includes and their receipt.
 *
 * It replaces the body of Paid Memberships Pro's member checkout emails. The plain welcome
 * saved in Memberships > Settings > Email Templates is kept as a fallback if this file is removed.
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is this one of the emails a new member gets at checkout (not the admin copy)?
 *
 * @param string $template PMPro template slug.
 */
function brra_is_welcome_template( $template ) {
	return 0 === strpos( (string) $template, 'checkout_' ) && '_admin' !== substr( $template, -6 );
}

/**
 * The next event people can RSVP for.
 *
 * @return WP_Post|null
 */
function brra_welcome_next_event() {
	foreach ( brra_upcoming_events( 6 ) as $e ) {
		if ( brra_event_get( $e->ID, 'rsvp' ) ) {
			return $e;
		}
	}
	return null;
}

/**
 * Is this person coming back (renewing, or rejoining after a gap)?
 * True if they're on the past members' list or have held an earlier membership on this site.
 *
 * @param WP_User|null $user User.
 */
function brra_is_renewal( $user ) {
	global $wpdb;
	if ( ! $user ) {
		return false;
	}
	if ( in_array( strtolower( $user->user_email ), brra_past_member_emails(), true ) ) {
		return true;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pmpro_memberships_users} WHERE user_id = %d", $user->ID ) ) > 1; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * One numbered step box.
 *
 * @param int    $n     Number.
 * @param string $title Title.
 * @param string $body  Body HTML.
 */
function brra_welcome_step( $n, $title, $body ) {
	return '<tr><td style="padding:14px 28px 0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #DDE2EA;border-radius:8px"><tr>'
		. '<td width="56" valign="top" style="padding:18px 0 18px 18px"><div style="width:38px;height:38px;line-height:38px;border-radius:19px;background:#D4AF37;color:#050F1C;font-weight:bold;font-size:18px;text-align:center">' . (int) $n . '</div></td>'
		. '<td valign="top" style="padding:18px 18px 18px 12px"><p style="margin:0 0 6px;font-size:17px;font-weight:bold;color:#050F1C">' . esc_html( $title ) . '</p>' . $body . '</td>'
		. '</tr></table></td></tr>';
}

/**
 * A button.
 *
 * @param string $url   Link.
 * @param string $label Label.
 * @param string $bg    Background colour.
 */
function brra_welcome_button( $url, $label, $bg = '#050F1C' ) {
	return '<a href="' . esc_url( $url ) . '" style="display:inline-block;background:' . esc_attr( $bg ) . ';color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:12px 22px;border-radius:6px">' . esc_html( $label ) . '</a>';
}

/**
 * The welcome email body.
 *
 * @param WP_User|null $user The new member.
 * @param array        $data PMPro email data (order_id, order_total, order_date, membership_level_name).
 */
function brra_welcome_email_html( $user, $data = array(), $back = null ) {
	$back  = null === $back ? brra_is_renewal( $user ) : $back;
	$p     = 'margin:0 0 12px;font-size:16px;line-height:1.55';
	$small = 'margin:0 0 14px;font-size:15px;line-height:1.5;color:#3E4C60';
	$first = $user ? trim( $user->first_name ) : '';
	$first = $first ? $first : ( $user ? $user->display_name : '' );
	$level = ! empty( $data['membership_level_name'] ) ? wp_strip_all_tags( $data['membership_level_name'] ) : '';
	$end   = '';
	if ( $user ) {
		global $wpdb;
		$ml = $wpdb->get_row( $wpdb->prepare( "SELECT membership_id, enddate FROM {$wpdb->prefix}pmpro_memberships_users WHERE user_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1", $user->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $ml ) {
			$lv    = function_exists( 'pmpro_getLevel' ) ? pmpro_getLevel( $ml->membership_id ) : null;
			$level = $level ? $level : ( $lv ? $lv->name : '' );
			$end   = $ml->enddate && '0000-00-00 00:00:00' !== $ml->enddate ? mysql2date( 'j F Y', $ml->enddate ) : 'No end date';
		}
	}

	$out  = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEF0F4"><tr><td align="center" style="padding:24px 12px">';
	$out .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#0B1626">';
	$out .= '<tr><td style="background:#050F1C;padding:22px 28px;border-bottom:4px solid #D4AF37"><img src="' . esc_url( BRRA_SITE_URL . 'assets/email-header.png' ) . '" width="300" alt="Bury &amp; Rochdale Referees&#39; Association" style="display:block;width:300px;max-width:100%;height:auto;border:0"></td></tr>';

	// Welcome.
	$season = function_exists( 'brra_sales_season' ) ? brra_season_text( brra_sales_season() ) : '';
	if ( $back ) {
		$out .= '<tr><td style="padding:30px 28px 8px"><p style="margin:0 0 6px;font-size:12px;letter-spacing:2px;font-weight:bold;color:#9A7B1C">WELCOME BACK</p>';
		$out .= '<h1 style="margin:0 0 14px;font-size:28px;line-height:1.2;color:#050F1C">' . ( $first ? 'Great to have you back, ' . esc_html( $first ) . '!' : 'Great to have you back!' ) . '</h1>';
		$out .= '<p style="' . $p . '">Thanks for renewing with Bury &amp; Rochdale Referees&#39; Association. We&#39;re really glad to see you join us for another season' . ( $season ? ' in ' . esc_html( $season ) : '' ) . '.</p>';
		$out .= '<p style="margin:0;font-size:16px;line-height:1.55">Here&#39;s a quick reminder of how to stay involved this season.</p></td></tr>';
	} else {
		$out .= '<tr><td style="padding:30px 28px 8px"><p style="margin:0 0 6px;font-size:12px;letter-spacing:2px;font-weight:bold;color:#9A7B1C">WELCOME TO THE RA</p>';
		$out .= '<h1 style="margin:0 0 14px;font-size:28px;line-height:1.2;color:#050F1C">' . ( $first ? 'You&#39;re in, ' . esc_html( $first ) . '!' : 'You&#39;re in!' ) . '</h1>';
		$out .= '<p style="' . $p . '">Thanks for joining Bury &amp; Rochdale Referees&#39; Association. We&#39;ve been supporting, training and developing match officials since 1898, and we&#39;re really glad to have you with us this season.</p>';
		$out .= '<p style="margin:0;font-size:16px;line-height:1.55">Here are three things to do now so you get the most out of your membership.</p></td></tr>';
	}

	// 1. WhatsApp.
	$wa   = get_option( 'brra_whatsapp_url', '' );
	$body = '<p style="' . $small . '">' . ( $back ? 'Not in it yet, or left over the summer? ' : '' ) . 'This is where it all happens: post games you need an official for (or games you can cover), ask questions after a tricky match, network with referees at every level and keep up with RA news.</p>';
	if ( $wa ) {
		$body .= brra_welcome_button( $wa, 'Join on WhatsApp', '#25D366' );
	} else {
		$body .= '<p style="margin:0;font-size:15px;line-height:1.5;color:#0B1626">Our Membership Secretary will add you in the next few days.</p>';
	}
	$out .= brra_welcome_step( 1, $back ? 'Stay in the members\' WhatsApp community' : 'Join the members\' WhatsApp community', $body );

	// 2. Next session, picked from Events.
	$ev   = brra_welcome_next_event();
	$body = '<p style="margin:0 0 10px;font-size:15px;line-height:1.5;color:#3E4C60">Our Monthly Development Session (MDS) is on the first Thursday of every month, with guest speakers, law discussions and a chance to meet everyone. Let us know you&#39;re coming so we can plan food and seating.</p>';
	if ( $ev ) {
		$start = brra_event_time( $ev->ID, 'start' );
		$lab   = 'padding:2px 12px 2px 0;font-weight:bold;white-space:nowrap;vertical-align:top';
		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 14px;font-size:14px;color:#0B1626">'
			. '<tr><td style="' . $lab . '">Next session</td><td style="padding:2px 0">' . esc_html( get_the_title( $ev ) ) . '</td></tr>'
			. '<tr><td style="' . $lab . '">When</td><td style="padding:2px 0">' . esc_html( ( $start ? $start->format( 'l j F Y' ) . ', ' : '' ) . lcfirst( brra_event_times_text( $ev->ID ) ) ) . '</td></tr>'
			. '<tr><td style="' . $lab . '">Where</td><td style="padding:2px 0">' . esc_html( brra_event_get( $ev->ID, 'location' ) ) . '</td></tr></table>';
		$body .= brra_welcome_button( get_permalink( $ev ) . '#rsvp', 'Register for the next session' );
	} else {
		$events = get_page_by_path( 'upcoming-events' );
		$body  .= brra_welcome_button( $events ? get_permalink( $events ) : home_url( '/' ), 'See what\'s on' );
	}
	$body .= '<p style="margin:10px 0 0;font-size:13px;line-height:1.5;color:#3E4C60">Getting there: Radcliffe Metrolink is a five-minute walk, and the 524 bus stops at the tram stop. Parking is on nearby streets.</p>';
	$out  .= brra_welcome_step( 2, 'Book your place at a Development Session', $body );

	// 3. Social.
	$link  = 'color:#050F1C;font-weight:bold;font-size:15px';
	$body  = '<p style="margin:0 0 12px;font-size:15px;line-height:1.5;color:#3E4C60">Event news, appointments and features from across the RA.</p>';
	$body .= '<a href="https://www.instagram.com/buryreferees/" style="' . $link . '">Instagram</a>&nbsp;&nbsp;·&nbsp;&nbsp;<a href="https://www.facebook.com/profile.php?id=61565202158021" style="' . $link . '">Facebook</a>&nbsp;&nbsp;·&nbsp;&nbsp;<a href="https://twitter.com/BuryReferees" style="' . $link . '">X</a>';
	$out  .= brra_welcome_step( 3, 'Follow us', $body );

	// What membership includes.
	$tick = '<td width="22" valign="top" style="color:#D4AF37;font-weight:bold;padding:4px 0">&#10003;</td>';
	$out .= '<tr><td style="padding:26px 28px 0"><p style="margin:0 0 8px;font-size:13px;letter-spacing:2px;font-weight:bold;color:#050F1C;border-bottom:1px solid #D4AF37;padding-bottom:5px">' . ( $back ? 'THIS SEASON YOU GET' : 'YOUR MEMBERSHIP INCLUDES' ) . '</p><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.5">';
	foreach ( array( 'Monthly Development Sessions with guest speakers', 'Mentoring and support from experienced officials, whatever level you\'re at', 'The members\' WhatsApp community', 'Socials, the 100 Club and your vote at the AGM' ) as $li ) {
		$out .= '<tr>' . $tick . '<td style="padding:4px 0">' . esc_html( $li ) . '</td></tr>';
	}
	$out .= '</table></td></tr>';

	// Receipt.
	$row  = function ( $k, $v, $bold = false ) {
		return '<tr><td style="padding:3px 18px;color:#3E4C60">' . esc_html( $k ) . '</td><td style="padding:3px 18px;text-align:right' . ( $bold ? ';font-weight:bold' : '' ) . '">' . esc_html( $v ) . '</td></tr>';
	};
	$out .= '<tr><td style="padding:24px 28px 0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F6F9;border-radius:8px;font-size:14px">';
	$out .= '<tr><td colspan="2" style="padding:14px 18px 6px;font-weight:bold;font-size:13px;letter-spacing:2px;color:#050F1C">YOUR RECEIPT</td></tr>';
	$out .= $level ? $row( 'Membership', $level, true ) : '';
	$out .= ! empty( $data['order_total'] ) ? $row( 'Paid', html_entity_decode( wp_strip_all_tags( $data['order_total'] ) ), true ) : '';
	$out .= ! empty( $data['order_id'] ) ? $row( 'Order', wp_strip_all_tags( $data['order_id'] ) . ( ! empty( $data['order_date'] ) ? ' · ' . wp_strip_all_tags( $data['order_date'] ) : '' ) ) : '';
	$out .= $end ? $row( 'Valid until', $end ) : '';
	$out .= '<tr><td colspan="2" style="padding:0 0 11px"></td></tr></table>';
	$acct = function_exists( 'pmpro_url' ) ? pmpro_url( 'account' ) : wp_login_url();
	$out .= '<p style="margin:12px 0 0;font-size:14px;line-height:1.5;color:#3E4C60">You can see your membership and update your details any time in <a href="' . esc_url( $acct ? $acct : wp_login_url() ) . '" style="color:#050F1C;font-weight:bold">My Account</a>.</p></td></tr>';

	// Sign-off from the Membership Secretary.
	$ms   = function_exists( 'brra_committee_holder' ) ? brra_committee_holder( 'membership' ) : 0;
	$out .= '<tr><td style="padding:24px 28px 28px"><p style="' . $p . '">If you need anything at all, from help after a tricky game to advice on your next promotion, just reply to this email.</p>';
	$out .= '<p style="margin:0;font-size:16px;line-height:1.55">' . ( $back ? 'See you at the next session, and thanks for sticking with us,' : 'See you at the next session,' ) . '</p>';
	$out .= '<p style="margin:6px 0 0;font-size:16px;line-height:1.4"><strong>' . esc_html( $ms ? get_userdata( $ms )->display_name : 'The committee' ) . '</strong><br><span style="color:#3E4C60;font-size:14px">' . ( $ms ? 'Membership Secretary, ' : '' ) . 'Bury &amp; Rochdale Referees&#39; Association</span></p></td></tr>';

	// Footer.
	$out .= '<tr><td style="background:#050F1C;padding:18px 28px;border-top:4px solid #D4AF37;font-size:12px;line-height:1.6;color:#C9D2DD"><strong style="color:#ffffff">Bury &amp; Rochdale Referees&#39; Association</strong><br>Supporting, Training &amp; Developing Match Officials since 1898<br>'
		. '<a href="mailto:mail@buryrefs.org.uk" style="color:#D4AF37">mail@buryrefs.org.uk</a> &nbsp;|&nbsp; <a href="' . esc_url( home_url( '/' ) ) . '" style="color:#D4AF37">www.buryrefs.org.uk</a> &nbsp;|&nbsp; @buryreferees<br><span style="color:#8C99AB">You&#39;re getting this because you&#39;ve just ' . ( $back ? 'renewed your membership' : 'joined the RA' ) . ' online.</span></td></tr>';
	return $out . '</table></td></tr></table>';
}

// Swap the member's checkout email for the welcome email.
add_filter(
	'pmpro_email_filter',
	function ( $email ) {
		if ( is_object( $email ) && brra_is_welcome_template( $email->template ) ) {
			$user        = get_user_by( 'email', $email->email );
			$email->body = brra_welcome_email_html( $user ? $user : null, is_array( $email->data ) ? $email->data : array() );
			$email->subject = brra_is_renewal( $user ? $user : null ) ? 'Thanks for renewing with Bury & Rochdale RA' : 'Welcome to Bury & Rochdale RA';
		}
		return $email;
	}
);

// The welcome email has its own greeting and sign-off, so leave out the usual "Hi name" and "Best wishes".
foreach ( array( 'pmpro_email_header', 'pmpro_email_footer' ) as $brra_hook ) {
	add_filter(
		$brra_hook,
		function ( $html, $email ) {
			return is_object( $email ) && brra_is_welcome_template( $email->template ) ? '' : $html;
		},
		10,
		2
	);
}

// Preview: Memberships > New member price has a link that shows the welcome email as you'd get it.
add_action(
	'admin_post_brra_welcome_preview',
	function () {
		if ( ! current_user_can( 'pmpro_memberships_menu' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sorry, you can\'t do that.' );
		}
		check_admin_referer( 'brra_welcome_preview' );
		$user = wp_get_current_user();
		$data = array(
			'order_id'    => 'EXAMPLE1',
			'order_total' => '£15.00',
			'order_date'  => wp_date( 'j F Y' ),
		);
		$html = brra_welcome_email_html( $user, $data, ! empty( $_GET['back'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Welcome email preview</title></head><body style="margin:0">' . ( function_exists( 'pmpro_kses' ) ? pmpro_kses( $html ) : wp_kses_post( $html ) ) . '</body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
);

// The committee's copy of each online join or renewal goes to mail@ (not just the site admin),
// with a subject that says straight away whether it's a new member or a renewal.
add_filter(
	'pmpro_email_filter',
	function ( $email ) {
		if ( ! is_object( $email ) || 0 !== strpos( (string) $email->template, 'checkout_' ) || '_admin' !== substr( $email->template, -6 ) ) {
			return $email;
		}
		$to = get_option( 'brra_signup_notify', 'mail@buryrefs.org.uk' );
		if ( is_email( $to ) ) {
			$email->email = $to;
		}
		$login  = is_array( $email->data ) && ! empty( $email->data['user_login'] ) ? $email->data['user_login'] : '';
		$member = $login ? get_user_by( 'login', $login ) : null;
		if ( $member ) {
			$level          = is_array( $email->data ) && ! empty( $email->data['membership_level_name'] ) ? wp_strip_all_tags( $email->data['membership_level_name'] ) : 'membership';
			$email->subject = ( brra_is_renewal( $member ) ? 'Renewal: ' : 'New member: ' ) . $member->display_name . ' (' . $level . ')';
		}
		return $email;
	},
	20
);
