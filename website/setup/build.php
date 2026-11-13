<?php
/**
 * Builds the test copy of the BRRA site: settings, pages, events, committee and membership.
 * Run with: wp eval-file website/setup/build.php
 * Safe to run more than once: it updates what it created before.
 *
 * @package brra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$brra_dir = __DIR__;

/**
 * Print a build message (works under WP-CLI and in WordPress Playground).
 *
 * @param string $msg  Message.
 * @param string $type success or warning.
 */
function brra_build_log( $msg, $type = 'warning' ) {
	if ( class_exists( 'WP_CLI' ) ) {
		'success' === $type ? WP_CLI::success( $msg ) : WP_CLI::warning( $msg );
	} else {
		echo esc_html( ucfirst( $type ) . ': ' . $msg ) . "\n";
	}
}

// ---------------------------------------------------------------- Settings.
update_option( 'blogname', "Bury & Rochdale Referees' Association" );
update_option( 'blogdescription', 'Supporting, training and developing match officials since 1898.' );
update_option( 'timezone_string', 'Europe/London' );
update_option( 'date_format', 'j F Y' );
update_option( 'time_format', 'g.ia' );
update_option( 'start_of_week', 1 );
update_option( 'default_comment_status', 'closed' );
$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );

// Remove WordPress's sample content.
foreach ( array( 'hello-world' => 'post', 'sample-page' => 'page' ) as $slug => $type ) {
	$p = get_page_by_path( $slug, OBJECT, $type );
	if ( $p ) {
		wp_delete_post( $p->ID, true );
	}
}

// ---------------------------------------------------------------- Media.
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

/**
 * Import an image from setup/media once, keyed by file name.
 *
 * @param string $file File name.
 * @param string $alt  Alt text.
 */
function brra_build_media( $file, $alt = '' ) {
	$key      = sanitize_title( pathinfo( $file, PATHINFO_FILENAME ) );
	$existing = get_posts( array( 'post_type' => 'attachment', 'meta_key' => '_brra_key', 'meta_value' => $key, 'posts_per_page' => 1, 'post_status' => 'any' ) ); // phpcs:ignore
	if ( $existing ) {
		return $existing[0]->ID;
	}
	$tmp = wp_tempnam( $file );
	copy( __DIR__ . '/media/' . $file, $tmp );
	$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0 );
	if ( is_wp_error( $id ) ) {
		brra_build_log( $file . ': ' . $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, '_brra_key', $key );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	return $id;
}

$brra_media = array(
	'hero-toss'         => brra_build_media( 'hero-toss.jpg', 'Match officials at the coin toss before kick-off' ),
	'members-night'     => brra_build_media( 'members-night.jpg', 'Members at a monthly meeting' ),
	'walkout'           => brra_build_media( 'walkout.jpg', 'Match officials walking out onto the pitch' ),
	'peter-wright'      => brra_build_media( 'peter-wright.jpg', 'Peter Wright' ),
	'grassroots-action' => brra_build_media( 'grassroots-action.jpg', 'A referee during a grassroots match' ),
	'referee-stadium'   => brra_build_media( 'referee-stadium.jpg', 'A referee in a stadium' ),
);

// ---------------------------------------------------------------- Categories.
foreach ( array( 'Appointments', 'Educational', 'News' ) as $brra_cat ) {
	if ( ! term_exists( $brra_cat, 'category' ) ) {
		wp_insert_term( $brra_cat, 'category' );
	}
}

// ---------------------------------------------------------------- Pages.

/**
 * Create or update a page by slug.
 *
 * @param string $slug     Slug.
 * @param string $title    Title.
 * @param string $content  Content.
 * @param string $template Page template slug.
 * @param int    $parent   Parent page.
 */
function brra_build_page( $slug, $title, $content = '', $template = '', $parent = 0 ) {
	$page = get_page_by_path( $slug );
	$data = array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'post_name'      => $slug,
		'post_title'     => $title,
		'post_content'   => $content,
		'post_parent'    => $parent,
		'comment_status' => 'closed',
	);
	if ( $page ) {
		$data['ID'] = $page->ID;
		$id         = wp_update_post( wp_slash( $data ) );
	} else {
		$id = wp_insert_post( wp_slash( $data ) );
	}
	update_post_meta( $id, '_wp_page_template', $template ? $template : 'default' );
	return $id;
}

$brra_pages = array(
	'home'            => brra_build_page( 'home', 'Home', '', 'page-full' ),
	'about-us'        => brra_build_page( 'about-us', 'About Us', '', 'page-full' ),
	'upcoming-events' => brra_build_page( 'upcoming-events', 'Upcoming Events', '', 'page-full' ),
	'membership'      => brra_build_page( 'membership', 'Membership', '', 'page-full' ),
	'become-a-referee' => brra_build_page( 'become-a-referee', 'Become a Referee', '', 'page-full' ),
	'news'            => brra_build_page( 'news', 'News' ),
);
$brra_pages['contact']   = brra_build_page(
	'contact',
	'Contact us',
	"<!-- wp:paragraph -->\n<p>Email us at <a href=\"mailto:mail@buryrefs.org.uk\">mail@buryrefs.org.uk</a> and we'll get back to you within 72 hours.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Or come and find us at a Development Session: first Thursday of the month, doors 7.00pm, at St Mary's Social Club, Pine Street, Radcliffe, M26 2WQ.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>We're also on <a href=\"https://www.instagram.com/buryreferees/\">Instagram</a>, <a href=\"https://www.facebook.com/profile.php?id=61565202158021\">Facebook</a> and <a href=\"https://twitter.com/BuryReferees\">X</a>.</p>\n<!-- /wp:paragraph -->"
);
$brra_pages['privacy-notice'] = brra_build_page( 'privacy-notice', 'Privacy notice' );
update_option( 'wp_page_for_privacy_policy', $brra_pages['privacy-notice'] );

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $brra_pages['home'] );
update_option( 'page_for_posts', $brra_pages['news'] );

// ---------------------------------------------------------------- Membership (Paid Memberships Pro).
global $wpdb;
$brra_levels_table = $wpdb->prefix . 'pmpro_membership_levels';

/**
 * Create or update a membership level by name.
 *
 * @param array $data Level fields.
 */
function brra_build_level( $data ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_membership_levels';
	$id    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE name = %s", $data['name'] ) ); // phpcs:ignore
	$row   = wp_parse_args(
		$data,
		array(
			'description'       => '',
			'confirmation'      => '',
			'initial_payment'   => 0,
			'billing_amount'    => 0,
			'cycle_number'      => 0,
			'cycle_period'      => '',
			'billing_limit'     => 0,
			'trial_amount'      => 0,
			'trial_limit'       => 0,
			'expiration_number' => 0,
			'expiration_period' => '',
			'allow_signups'     => 1,
		)
	);
	if ( $id ) {
		$wpdb->update( $table, $row, array( 'id' => $id ) ); // phpcs:ignore
		return $id;
	}
	$wpdb->insert( $table, $row ); // phpcs:ignore
	return (int) $wpdb->insert_id;
}

$brra_full = brra_build_level(
	array(
		'name'            => 'Full Member',
		'description'     => 'Full membership of the Bury & Rochdale Referees\' Association for the season.',
		'confirmation'    => "<p>Thanks for joining the Bury & Rochdale RA. You'll get a confirmation email shortly with the link to join our members' WhatsApp community, for news, updates and the chance to get your games published.</p>",
		'initial_payment' => 20,
	)
);
$brra_life = brra_build_level(
	array(
		'name'          => 'Life Member',
		'description'   => 'Set up by the committee.',
		'allow_signups' => 0,
	)
);
update_option( 'brra_membership', array( 'season_levels' => array( $brra_full ), 'discount' => 25 ) );
// Demo only: one address that counts as a past member. The real list is pasted in under Memberships > New member price.
// Demo only: one made-up past member so the full price can be tried. On the live site the list starts
// empty and the real past-member emails are pasted in under Memberships > New member price.
if ( defined( 'BRRA_DEMO' ) && BRRA_DEMO ) {
	update_option( 'brra_past_member_emails', array( 'member@buryrefs.org.uk' ), false );
}

update_option( 'pmpro_currency', 'GBP' );
// Online card payment only (Stripe). Bank transfer and cash are handled offline by the Membership Secretary.
// The test site has no Stripe keys, so it uses a stand-in "test payment" that takes no money.
update_option( 'pmpro_gateway_environment', 'sandbox' );
if ( defined( 'BRRA_DEMO' ) && BRRA_DEMO ) {
	update_option( 'pmpro_gateway', 'check' );
	update_option( 'pmpro_check_gateway_label', 'test payment' );
	update_option( 'pmpro_instructions', 'This is the test site, so no money is taken. On the real site you pay here by card, Apple Pay or Google Pay.' );
} else {
	update_option( 'pmpro_gateway', 'stripe' );
	// Stripe's own payment page: card, Apple Pay and Google Pay with no extra setup on this site.
	update_option( 'pmpro_stripe_payment_flow', 'checkout' );
	delete_option( 'pmpro_instructions' );
}
update_option( 'pmpro_hide_footer_link', 1 );
// Member emails in the RA's own voice. The renewal reminder goes out 30 days before 31 August
// (see brra-site membership.php), and a last nudge the day it ends.
update_option( 'pmpro_from_email', 'mail@buryrefs.org.uk' );
update_option( 'pmpro_from_name', "Bury & Rochdale Referees' Association" );
update_option( 'pmpro_email_header_body', '<p>Hi !!header_name!!,</p>' );
update_option( 'pmpro_email_footer_body', "<p>Best wishes,<br>Bury &amp; Rochdale Referees' Association<br><a href=\"mailto:mail@buryrefs.org.uk\">mail@buryrefs.org.uk</a></p>" );
$brra_welcome = "<p>Thanks for joining the Bury &amp; Rochdale Referees' Association. You're now a {{ membership_level_name }}, and we're really glad to have you.</p>\n\n" .
	"<p><strong>What happens next</strong></p>\n" .
	"<ul>\n<li>{{ brra_whatsapp }}</li>\n" .
	"<li>Come along to our next Development Session: the first Thursday of the month, doors 7.00pm, at St Mary's Social Club, Pine Street, Radcliffe, M26 2WQ. <a href=\"{{ brra_events_url }}\">See what's on</a>.</li>\n" .
	"<li>If you need help with anything, from a tricky game to your next promotion, just reply to this email.</li>\n</ul>\n\n";
update_option( 'pmpro_email_checkout_paid_subject', "Welcome to the Bury & Rochdale RA" );
update_option(
	'pmpro_email_checkout_paid_body',
	$brra_welcome .
	"<p><strong>Your receipt</strong><br>\nOrder {{ order_id }} on {{ order_date }}<br>\nMembership: {{ membership_level_name }}<br>\nPaid: {{ order_total }}</p>\n\n" .
	"{% if membership_expiration %}{{ membership_expiration }}{% endif %}\n\n" .
	"<p>You can see your membership and update your details at any time: <a href=\"{{ login_url }}\">log in to My Membership</a>.</p>"
);
update_option( 'pmpro_email_checkout_free_subject', "Welcome to the Bury & Rochdale RA" );
update_option(
	'pmpro_email_checkout_free_body',
	$brra_welcome .
	"{% if membership_expiration %}{{ membership_expiration }}{% endif %}\n\n" .
	"<p>You can see your membership and update your details at any time: <a href=\"{{ login_url }}\">log in to My Membership</a>.</p>"
);
update_option( 'pmpro_email_membership_expiring_subject', 'Time to renew: your membership ends on {{ enddate }}' );
update_option(
	'pmpro_email_membership_expiring_body',
	"<p>Your {{ membership_level_name }} membership with the RA ends on <strong>{{ enddate }}</strong>. Renewals for the new season are open now, so renew today and you won't miss a thing.</p>\n\n" .
	"<p><a href=\"{{ renew_url }}\"><strong>Renew my membership</strong></a></p>\n\n" .
	"<p>It takes a minute online by card, Apple Pay or Google Pay. Renewing keeps your place at our monthly Development Sessions, mentoring from experienced officials, your weekly appointments, the members' WhatsApp community and your vote at the AGM.</p>\n\n" .
	"<p>If you've already paid at a meeting, thank you, and please ignore this email.</p>"
);
update_option( 'pmpro_email_membership_expired_subject', 'Your RA membership has ended' );
update_option(
	'pmpro_email_membership_expired_body',
	"<p>Your {{ membership_level_name }} membership with the RA has now ended. Thank you for being part of it last season.</p>\n\n" .
	"<p>We'd love to have you back. You can rejoin at any time and pick up where you left off.</p>\n\n" .
	"<p><a href=\"{{ renew_url }}\"><strong>Renew my membership</strong></a></p>\n\n" .
	"<p>If you've stopped refereeing or moved on, we'd still love to hear how you're getting on. Just reply to this email.</p>"
);

update_option( 'pmpro_wizard_step', 'done' );
update_option( 'pmpro_filterqueries', 1 );
update_option( 'pmpro_tos', '' );
update_option( 'pmpro_spamprotection', 2 );

// The Membership page doubles as PMPro's levels page; PMPro makes the rest.
update_option( 'pmpro_levels_page_id', $brra_pages['membership'] );
if ( function_exists( 'pmpro_generatePages' ) ) {
	global $pmpro_pages;
	foreach ( array( 'account', 'billing', 'cancel', 'checkout', 'confirmation', 'invoice', 'login', 'member_profile_edit' ) as $brra_name ) {
		$pmpro_pages[ $brra_name ] = (int) get_option( 'pmpro_' . $brra_name . '_page_id' );
		if ( $pmpro_pages[ $brra_name ] && ! get_post( $pmpro_pages[ $brra_name ] ) ) {
			$pmpro_pages[ $brra_name ] = 0;
		}
	}
	pmpro_generatePages(
		array(
			'account'             => 'My Membership',
			'billing'             => 'Billing Information',
			'cancel'              => 'Cancel Membership',
			'checkout'            => 'Join',
			'confirmation'        => 'Welcome',
			'invoice'             => 'Orders',
			'login'               => 'Log in',
			'member_profile_edit' => 'Your Profile',
		)
	);
	wp_update_post( array( 'ID' => (int) get_option( 'pmpro_checkout_page_id' ), 'post_name' => 'join' ) );
	wp_update_post( array( 'ID' => (int) get_option( 'pmpro_account_page_id' ), 'post_name' => 'my-membership' ) );
}

// Membership sign-up fields: FA number, referee level, county FA.
update_option(
	'pmpro_user_fields_settings',
	array(
		(object) array(
			'name'        => 'About you',
			'checkout'    => 'yes',
			'profile'     => 'yes',
			'description' => '',
			'levels'      => array(),
			'fields'      => array(
				(object) array( 'label' => 'Phone', 'name' => 'brra_phone', 'type' => 'text', 'required' => false, 'readonly' => false, 'profile' => 'yes', 'wrapper_class' => '', 'element_class' => '', 'hint' => '', 'options' => '', 'allowed_file_types' => '', 'max_file_size' => '', 'default' => '' ),
				(object) array( 'label' => 'FA number (FAN)', 'name' => 'brra_fan', 'type' => 'text', 'required' => false, 'readonly' => false, 'profile' => 'yes', 'wrapper_class' => '', 'element_class' => '', 'hint' => 'Leave blank if you are not registered yet.', 'options' => '', 'allowed_file_types' => '', 'max_file_size' => '', 'default' => '' ),
				(object) array( 'label' => 'Referee level', 'name' => 'brra_level', 'type' => 'select', 'required' => true, 'readonly' => false, 'profile' => 'yes', 'wrapper_class' => '', 'element_class' => '', 'hint' => '', 'options' => ":Choose your level\nTrainee / not yet qualified\nLevel 7\nLevel 6\nLevel 5\nLevel 4\nLevel 3 and above\nNot refereeing (supporter)", 'allowed_file_types' => '', 'max_file_size' => '', 'default' => '' ),
				(object) array( 'label' => 'County FA', 'name' => 'brra_county', 'type' => 'select', 'required' => false, 'readonly' => false, 'profile' => 'yes', 'wrapper_class' => '', 'element_class' => '', 'hint' => '', 'options' => ":Choose your county\nManchester FA\nLancashire FA\nOther", 'allowed_file_types' => '', 'max_file_size' => '', 'default' => '' ),
			),
		),
	)
);

// ---------------------------------------------------------------- Events.

/**
 * Create or update an event by slug.
 *
 * @param array $e Event data.
 */
function brra_build_event( $e ) {
	$existing = get_page_by_path( $e['slug'], OBJECT, 'brra_event' );
	$data     = array(
		'post_type'    => 'brra_event',
		'post_status'  => 'publish',
		'post_name'    => $e['slug'],
		'post_title'   => $e['title'],
		'post_content' => $e['content'],
		'post_excerpt' => isset( $e['excerpt'] ) ? $e['excerpt'] : '',
	);
	if ( $existing ) {
		$data['ID'] = $existing->ID;
	}
	$id = $existing ? wp_update_post( wp_slash( $data ) ) : wp_insert_post( wp_slash( $data ) );
	foreach ( array( 'date', 'doors', 'start', 'end', 'line', 'tag', 'location', 'rsvp' ) as $key ) {
		if ( isset( $e[ $key ] ) ) {
			update_post_meta( $id, '_brra_' . $key, $e[ $key ] );
		}
	}
	if ( ! empty( $e['image'] ) ) {
		set_post_thumbnail( $id, brra_build_media( $e['image'], $e['title'] . ' graphic' ) );
	}
	return $id;
}

$brra_mds = "<!-- wp:paragraph -->\n<p>Our monthly meeting and Development Session: Laws of the Game, match scenarios and a guest speaker, followed by food and a catch-up. Members and anyone thinking about refereeing are welcome.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>The guest speaker will be announced nearer the date.</p>\n<!-- /wp:paragraph -->";
$brra_events = array(
	array( 'slug' => 'november-development-session-2026', 'title' => 'November Development Session: Peter Wright', 'date' => '2026-11-05', 'line' => 'Former EFL official, now a performance coach.', 'excerpt' => "Peter is a former EFL official and now a performance coach. He'll talk about what it takes to progress at every level of the game.", 'image' => 'ev-2026-11-05.jpg', 'content' => "<!-- wp:paragraph -->\n<p>We're delighted to welcome Peter Wright to our November Monthly Meeting and Development Session.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Peter is a former EFL official and now a performance coach, so he knows what it takes to progress at every level of the game. Bring your questions.</p>\n<!-- /wp:paragraph -->" ),
	array( 'slug' => 'christmas-social-2026', 'title' => 'Christmas Social', 'date' => '2026-12-03', 'line' => 'Free and everyone welcome. Bring a dish for the buffet; raffle on the night.', 'tag' => 'Social', 'image' => 'ev-2026-12-03.jpg', 'content' => "<!-- wp:paragraph -->\n<p>Our Christmas social is free and everyone is welcome: members, partners, friends and anyone thinking about refereeing.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>It's a bring-and-share buffet, so please bring a dish. There's a raffle on the night.</p>\n<!-- /wp:paragraph -->", 'end' => '22:30' ),
	array( 'slug' => 'january-development-session-2027', 'title' => 'January Development Session', 'date' => '2027-01-07', 'line' => 'Guest speaker to be announced.', 'image' => 'ev-2027-01-07.jpg', 'content' => $brra_mds ),
	array( 'slug' => 'february-development-session-2027', 'title' => 'February Development Session', 'date' => '2027-02-04', 'line' => 'Guest speaker to be announced.', 'image' => 'ev-2027-02-04.jpg', 'content' => $brra_mds ),
	array( 'slug' => 'march-development-session-2027', 'title' => 'March Development Session', 'date' => '2027-03-04', 'line' => 'Guest speaker to be announced.', 'image' => 'ev-2027-03-04.jpg', 'content' => $brra_mds ),
	array( 'slug' => 'april-development-session-2027', 'title' => 'April Development Session', 'date' => '2027-04-01', 'line' => 'Guest speaker to be announced.', 'image' => 'ev-2027-04-01.jpg', 'content' => $brra_mds ),
	array( 'slug' => 'may-development-session-2027', 'title' => 'May Development Session', 'date' => '2027-05-06', 'line' => 'Guest speaker to be announced.', 'image' => 'ev-2027-05-06.jpg', 'content' => $brra_mds ),
	array( 'slug' => 'agm-2027', 'title' => 'Annual General Meeting 2027', 'date' => '2027-05-27', 'end' => '21:00', 'line' => 'Reports, elections and the season ahead.', 'tag' => 'Provisional date', 'image' => 'ev-2027-05-27.jpg', 'content' => "<!-- wp:paragraph -->\n<p>Our Annual General Meeting: the officers' reports, the accounts, elections and any rule changes. All members are encouraged to come; Full and Life Members can vote.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Nominations for officers, President, Life Members and Patrons go to the Secretary by 1 April. Proposed rule changes by 1 May. Papers are sent out at least seven clear days before.</p>\n<!-- /wp:paragraph -->" ),
);
foreach ( $brra_events as $brra_event ) {
	brra_build_event( $brra_event );
}

// ---------------------------------------------------------------- Committee.
$brra_people = array(
	array( 'Aron Connolly', 'Chairman' ),
	array( 'Vacant', 'Vice-Chairman. Interested? Ask us.' ),
	array( 'Josh Brammer', 'Secretary and Media Officer' ),
	array( 'Norman Rowlands', 'Treasurer' ),
	array( 'Molly Ashworth', 'Membership Secretary' ),
	array( 'Kaan Hayes', 'Committee' ),
	array( 'Martin Flynn', 'Committee' ),
	array( 'Ian Claridge', 'Committee' ),
);
foreach ( $brra_people as $brra_i => $brra_person ) {
	$brra_existing = get_page_by_path( sanitize_title( $brra_person[0] ), OBJECT, 'brra_person' );
	$brra_data     = array(
		'post_type'    => 'brra_person',
		'post_status'  => 'publish',
		'post_title'   => $brra_person[0],
		'post_name'    => sanitize_title( $brra_person[0] ),
		'post_excerpt' => $brra_person[1],
		'menu_order'   => $brra_i,
	);
	if ( $brra_existing ) {
		$brra_data['ID'] = $brra_existing->ID;
		wp_update_post( $brra_data );
	} else {
		wp_insert_post( $brra_data );
	}
}

// ---------------------------------------------------------------- Page content.
$brra_season = brra_current_season();
$brra_tokens = array(
	'{{season}}'     => sprintf( '%d/%02d', $brra_season[0], $brra_season[1] % 100 ),
	'{{level:full}}' => $brra_full,
	'{{url:join}}'   => get_permalink( (int) get_option( 'pmpro_checkout_page_id' ) ),
	'{{url:account}}' => get_permalink( (int) get_option( 'pmpro_account_page_id' ) ),
);
foreach ( $brra_pages as $brra_slug => $brra_id ) {
	$brra_tokens[ '{{url:' . $brra_slug . '}}' ] = get_permalink( $brra_id );
}
foreach ( $brra_media as $brra_key => $brra_id ) {
	$brra_tokens[ '{{img:' . $brra_key . '}}' ] = wp_get_attachment_image_url( $brra_id, 'full' );
	$brra_tokens[ '{{id:' . $brra_key . '}}' ]  = $brra_id;
}
// On a real site, links and images in page content are stored without the domain, so they keep
// working when the site moves from Hostinger's temporary address to buryrefs.org.uk.
// (Playground serves the site under a sub-path, so the demo keeps full addresses.)
if ( ! ( defined( 'BRRA_DEMO' ) && BRRA_DEMO ) ) {
	foreach ( $brra_tokens as $brra_k => $brra_v ) {
		if ( is_string( $brra_v ) && 0 === strpos( $brra_v, 'http' ) ) {
			$brra_tokens[ $brra_k ] = wp_make_link_relative( $brra_v );
		}
	}
}
foreach ( array( 'home', 'about-us', 'upcoming-events', 'membership', 'become-a-referee', 'privacy-notice' ) as $brra_slug ) {
	$brra_content = strtr( file_get_contents( $brra_dir . '/pages/' . $brra_slug . '.html' ), $brra_tokens ); // phpcs:ignore
	if ( preg_match( '/\{\{[a-z:-]+\}\}/', $brra_content, $brra_m ) ) {
		brra_build_log( "Unreplaced token {$brra_m[0]} in {$brra_slug}" );
	}
	wp_update_post( wp_slash( array( 'ID' => $brra_pages[ $brra_slug ], 'post_content' => $brra_content ) ) );
}

// ---------------------------------------------------------------- News: every post from the Wix blog.
// news/wix-posts.json is made by setup/wix-export.py. Covers and pictures stay on Wix until
// Tools > Copy Wix images is run (see plugins/brra-site/includes/wix.php).
$brra_wix = json_decode( file_get_contents( $brra_dir . '/news/wix-posts.json' ), true ); // phpcs:ignore
foreach ( $brra_wix as $brra_n ) {
	$brra_cats = array();
	foreach ( $brra_n['categories'] as $brra_cat ) {
		$brra_term = term_exists( $brra_cat, 'category' );
		if ( ! $brra_term ) {
			$brra_term = wp_insert_term( $brra_cat, 'category' );
		}
		$brra_cats[] = (int) ( is_array( $brra_term ) ? $brra_term['term_id'] : $brra_term );
	}
	$brra_existing = get_page_by_path( $brra_n['slug'], OBJECT, 'post' );
	$brra_id       = wp_insert_post(
		wp_slash(
			array(
				'ID'            => $brra_existing ? $brra_existing->ID : 0,
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_name'     => $brra_n['slug'],
				'post_title'    => $brra_n['title'],
				'post_date'     => get_date_from_gmt( $brra_n['date_gmt'] ),
				'post_date_gmt' => $brra_n['date_gmt'],
				'post_content'  => $brra_n['html'],
				'post_category' => $brra_cats,
				'tags_input'    => $brra_n['tags'],
			)
		)
	);
	if ( is_wp_error( $brra_id ) ) {
		brra_build_log( $brra_n['title'] . ': ' . $brra_id->get_error_message() );
		continue;
	}
	update_post_meta( $brra_id, '_brra_wix_id', $brra_n['wix_id'] );
	if ( $brra_n['cover'] && ! get_post_thumbnail_id( $brra_id ) ) {
		update_post_meta( $brra_id, '_brra_cover_url', $brra_n['cover'] );
	}
	if ( $brra_n['cover'] || false !== strpos( $brra_n['html'], 'static.wixstatic.com' ) ) {
		update_post_meta( $brra_id, '_brra_wix_pending', '1' );
	}
	// Members-only on Wix stays members-only here.
	$wpdb->delete( $wpdb->pmpro_memberships_pages, array( 'page_id' => $brra_id ) );
	if ( $brra_n['members_only'] ) {
		foreach ( array( $brra_full, $brra_life ) as $brra_level ) {
			$wpdb->insert( $wpdb->pmpro_memberships_pages, array( 'membership_id' => $brra_level, 'page_id' => $brra_id ) );
		}
	}
}
update_option( 'posts_per_page', 9 );

flush_rewrite_rules();
brra_build_log( 'Site built.', 'success' );
