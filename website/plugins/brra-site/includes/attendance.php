<?php
/**
 * Event attendance: QR check-in for members and guests, the door list, and the Attendance screen.
 *
 * Each event has two QR codes (printed from Events > Attendance). Members scan and tap "I'm here";
 * guests scan and fill in a short form. Codes only work on the day of the event.
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

const BRRA_DB_VERSION = '1';

/**
 * Attendance table name.
 */
function brra_attendance_table() {
	global $wpdb;
	return $wpdb->prefix . 'brra_attendance';
}

/**
 * Newsletter table name.
 */
function brra_subscribers_table() {
	global $wpdb;
	return $wpdb->prefix . 'brra_subscribers';
}

// Create or update the tables when the plugin's database version changes.
add_action(
	'init',
	function () {
		if ( get_option( 'brra_db_version' ) === BRRA_DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			'CREATE TABLE ' . brra_attendance_table() . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				name varchar(190) NOT NULL DEFAULT '',
				email varchar(190) NOT NULL DEFAULT '',
				phone varchar(50) NOT NULL DEFAULT '',
				referee varchar(60) NOT NULL DEFAULT '',
				heard varchar(60) NOT NULL DEFAULT '',
				contact_ok tinyint(1) NOT NULL DEFAULT 0,
				source varchar(20) NOT NULL DEFAULT '',
				created datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY event_id (event_id),
				KEY email (email)
			) $charset;"
		);
		dbDelta(
			'CREATE TABLE ' . brra_subscribers_table() . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(190) NOT NULL,
				name varchar(190) NOT NULL DEFAULT '',
				source varchar(20) NOT NULL DEFAULT '',
				created datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY email (email)
			) $charset;"
		);
		update_option( 'brra_db_version', BRRA_DB_VERSION );
	},
	5
);

/**
 * Options for "Do you referee?" on the guest form.
 */
function brra_guest_referee_options() {
	return array( 'Not refereeing', 'Thinking about it', 'On a referee course', 'Level 7', 'Level 6', 'Level 5', 'Level 4', 'Level 3', 'Level 2', 'Level 1' );
}

/**
 * Options for "How did you hear about us?".
 */
function brra_guest_heard_options() {
	return array( 'A friend or colleague', 'Social media', 'My County FA', 'Our website', 'A referee course', 'Other' );
}

/**
 * Secret part of an event's check-in links, so the codes can't be guessed.
 *
 * @param int $event_id Event ID.
 */
function brra_checkin_key( $event_id ) {
	return substr( hash_hmac( 'sha256', 'brra-checkin-' . $event_id, wp_salt( 'auth' ) ), 0, 12 );
}

/**
 * Check-in link for a QR code.
 *
 * @param int    $event_id Event ID.
 * @param string $who      member or guest.
 */
function brra_checkin_url( $event_id, $who ) {
	$page = get_page_by_path( 'check-in' );
	$base = $page ? get_permalink( $page ) : home_url( '/check-in/' );
	return add_query_arg( array( 'event' => $event_id, 'k' => brra_checkin_key( $event_id ), 'as' => $who ), $base );
}

/**
 * Whether check-in is open for an event right now (any time on the day of the event).
 *
 * @param int $event_id Event ID.
 */
function brra_checkin_open( $event_id ) {
	return brra_event_get( $event_id, 'date' ) === wp_date( 'Y-m-d' );
}

/**
 * An attendance row for this event and person, if there is one.
 *
 * @param int    $event_id Event ID.
 * @param int    $user_id  Member's user ID (0 for a guest).
 * @param string $email    Email.
 */
function brra_attendance_find( $event_id, $user_id, $email ) {
	global $wpdb;
	$table = brra_attendance_table();
	if ( $user_id ) {
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE event_id = %d AND user_id = %d", $event_id, $user_id ) ); // phpcs:ignore WordPress.DB
	}
	return $email ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE event_id = %d AND email = %s", $event_id, strtolower( $email ) ) ) : null; // phpcs:ignore WordPress.DB
}

/**
 * Record that someone came. A guest whose email belongs to an account is recorded as that member.
 *
 * @param int   $event_id Event ID.
 * @param array $data     name, email, phone, referee, heard, contact_ok, source, user_id.
 */
function brra_attendance_add( $event_id, $data ) {
	global $wpdb;
	$email   = strtolower( isset( $data['email'] ) ? $data['email'] : '' );
	$user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
	if ( ! $user_id && $email ) {
		$user    = get_user_by( 'email', $email );
		$user_id = $user ? $user->ID : 0;
	}
	if ( $user_id ) {
		$user  = get_userdata( $user_id );
		$email = $user ? strtolower( $user->user_email ) : $email;
		if ( empty( $data['name'] ) && $user ) {
			$data['name'] = trim( $user->first_name . ' ' . $user->last_name ) ? trim( $user->first_name . ' ' . $user->last_name ) : $user->display_name;
		}
	}
	if ( brra_attendance_find( $event_id, $user_id, $email ) ) {
		return false;
	}
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		brra_attendance_table(),
		array(
			'event_id'   => $event_id,
			'user_id'    => $user_id,
			'name'       => isset( $data['name'] ) ? $data['name'] : '',
			'email'      => $email,
			'phone'      => isset( $data['phone'] ) ? $data['phone'] : '',
			'referee'    => isset( $data['referee'] ) ? $data['referee'] : '',
			'heard'      => isset( $data['heard'] ) ? $data['heard'] : '',
			'contact_ok' => empty( $data['contact_ok'] ) ? 0 : 1,
			'source'     => isset( $data['source'] ) ? $data['source'] : '',
			'created'    => current_time( 'mysql' ),
		)
	);
	if ( ! $user_id && ! empty( $data['contact_ok'] ) && $email ) {
		brra_newsletter_add( $email, isset( $data['name'] ) ? $data['name'] : '', 'guest' );
	}
	return true;
}

/**
 * Everyone who came to an event, members first.
 *
 * @param int $event_id Event ID.
 */
function brra_attendance_for_event( $event_id ) {
	global $wpdb;
	$table = brra_attendance_table();
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE event_id = %d ORDER BY (user_id = 0), name", $event_id ) ); // phpcs:ignore WordPress.DB
}

// ---------------------------------------------------------------- The check-in page.

add_action(
	'init',
	function () {
		add_shortcode( 'brra_checkin', 'brra_shortcode_checkin' );
	}
);

/**
 * [brra_checkin] on the Check in page. The QR codes open this page.
 */
function brra_shortcode_checkin() {
	// phpcs:disable WordPress.Security.NonceVerification
	$event_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;
	$key      = isset( $_GET['k'] ) ? sanitize_text_field( wp_unslash( $_GET['k'] ) ) : '';
	$who      = isset( $_GET['as'] ) && 'guest' === $_GET['as'] ? 'guest' : 'member';
	$done     = isset( $_GET['checked'] ) ? sanitize_key( $_GET['checked'] ) : '';
	// phpcs:enable WordPress.Security.NonceVerification
	if ( ! $event_id || 'brra_event' !== get_post_type( $event_id ) || ! hash_equals( brra_checkin_key( $event_id ), $key ) ) {
		return '<p>This check-in code isn\'t valid. Please scan the code on the sign at the door, or ask a committee member to add you.</p>';
	}
	$title = get_the_title( $event_id );
	$start = brra_event_time( $event_id );
	$head  = '<p class="brra-checkin__event"><strong>' . esc_html( $title ) . '</strong><br>' . esc_html( $start ? wp_date( 'l j F Y', $start->getTimestamp() ) : '' ) . '</p>';
	if ( 'yes' === $done ) {
		$out  = $head . '<p class="brra-notice">You\'re checked in. Enjoy the evening!</p>';
		$out .= 'guest' === $who ? '<p>Thinking about joining? Your first season as a member is just £15. <a href="' . esc_url( home_url( '/membership/' ) ) . '">Find out more</a>.</p>' : '';
		return '<div class="brra-checkin">' . $out . '</div>';
	}
	if ( ! brra_checkin_open( $event_id ) ) {
		return '<div class="brra-checkin">' . $head . '<p>Check-in opens on the day of the event. See you there!</p></div>';
	}
	$action = esc_url( admin_url( 'admin-post.php' ) );
	$hidden = '<input type="hidden" name="event" value="' . esc_attr( $event_id ) . '"><input type="hidden" name="k" value="' . esc_attr( $key ) . '">' . wp_nonce_field( 'brra_checkin_' . $event_id, '_wpnonce', true, false );

	if ( 'member' === $who ) {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			$here = brra_checkin_url( $event_id, 'member' );
			return '<div class="brra-checkin">' . $head . '<p>Log in to your account to check in. Your phone will remember you next time.</p><p><a class="brra-btn" href="' . esc_url( wp_login_url( $here ) ) . '">Log in</a></p><p>Not a member? <a href="' . esc_url( brra_checkin_url( $event_id, 'guest' ) ) . '">Check in as a guest</a>.</p></div>';
		}
		if ( brra_attendance_find( $event_id, $user->ID, '' ) ) {
			return '<div class="brra-checkin">' . $head . '<p class="brra-notice">You\'re already checked in. Enjoy the evening!</p></div>';
		}
		$name = $user->first_name ? $user->first_name : $user->display_name;
		return '<div class="brra-checkin">' . $head . '<p>Hi ' . esc_html( $name ) . '.</p><form method="post" action="' . $action . '"><input type="hidden" name="action" value="brra_checkin_member">' . $hidden . '<button class="brra-btn is-big" type="submit">I\'m here</button></form></div>';
	}

	$referee = '';
	foreach ( brra_guest_referee_options() as $o ) {
		$referee .= '<option>' . esc_html( $o ) . '</option>';
	}
	$heard = '<option value="">Choose one</option>';
	foreach ( brra_guest_heard_options() as $o ) {
		$heard .= '<option>' . esc_html( $o ) . '</option>';
	}
	ob_start();
	?>
	<div class="brra-checkin">
		<?php echo $head; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<p>Welcome! Please tell us a little about you.</p>
		<form class="brra-form" method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput ?>" style="display:grid;gap:14px">
			<input type="hidden" name="action" value="brra_checkin_guest">
			<?php echo $hidden; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<label>Your name <input type="text" name="g_name" required autocomplete="name"></label>
			<label>Email <input type="email" name="g_email" required autocomplete="email"></label>
			<label>Phone (optional) <input type="tel" name="g_phone" autocomplete="tel"></label>
			<label>Do you referee? <select name="g_referee"><?php echo $referee; // phpcs:ignore WordPress.Security.EscapeOutput ?></select></label>
			<label>How did you hear about us? <select name="g_heard"><?php echo $heard; // phpcs:ignore WordPress.Security.EscapeOutput ?></select></label>
			<label class="brra-check"><input type="checkbox" name="g_contact" value="1"> Yes, the RA can contact me about future events and news.</label>
			<label style="position:absolute;left:-9999px" aria-hidden="true">Leave blank <input type="text" name="g_website" tabindex="-1" autocomplete="off"></label>
			<p style="margin:0"><button class="brra-btn is-big" type="submit">Check in</button></p>
			<p style="margin:0;font-size:14px;color:var(--wp--preset--color--slate)">We use these details to know who came and to keep everyone safe at our events. See our <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">privacy notice</a>.</p>
		</form>
	</div>
	<?php
	return preg_replace( '/>\s+</', '><', ob_get_clean() );
}

/**
 * Check the event, code and nonce of a check-in form, or stop.
 */
function brra_checkin_verify() {
	$event_id = isset( $_POST['event'] ) ? absint( $_POST['event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$key      = isset( $_POST['k'] ) ? sanitize_text_field( wp_unslash( $_POST['k'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $event_id || 'brra_event' !== get_post_type( $event_id ) || ! hash_equals( brra_checkin_key( $event_id ), $key ) || ! check_admin_referer( 'brra_checkin_' . $event_id ) ) {
		wp_die( 'Sorry, something went wrong. Please scan the code again.' );
	}
	if ( ! brra_checkin_open( $event_id ) ) {
		wp_die( 'Check-in is only open on the day of the event.' );
	}
	return $event_id;
}

add_action(
	'admin_post_brra_checkin_member',
	function () {
		$event_id = brra_checkin_verify();
		brra_attendance_add( $event_id, array( 'user_id' => get_current_user_id(), 'source' => 'qr' ) );
		wp_safe_redirect( add_query_arg( 'checked', 'yes', brra_checkin_url( $event_id, 'member' ) ) );
		exit;
	}
);
// A logged-out visitor posting the member form is sent to log in.
add_action(
	'admin_post_nopriv_brra_checkin_member',
	function () {
		$event_id = isset( $_POST['event'] ) ? absint( $_POST['event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		wp_safe_redirect( wp_login_url( brra_checkin_url( $event_id, 'member' ) ) );
		exit;
	}
);

/**
 * Save a guest check-in.
 */
function brra_handle_checkin_guest() {
	$event_id = brra_checkin_verify();
	$back     = brra_checkin_url( $event_id, 'guest' );
	// phpcs:disable WordPress.Security.NonceVerification -- checked in brra_checkin_verify().
	if ( ! empty( $_POST['g_website'] ) ) {
		wp_safe_redirect( $back );
		exit;
	}
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$limit = 'brra_ci_' . md5( $ip );
	$count = (int) get_transient( $limit );
	if ( $count >= 30 ) {
		wp_die( 'Too many check-ins from this connection. Please ask a committee member to add you.' );
	}
	set_transient( $limit, $count + 1, HOUR_IN_SECONDS );
	$name    = isset( $_POST['g_name'] ) ? sanitize_text_field( wp_unslash( $_POST['g_name'] ) ) : '';
	$email   = isset( $_POST['g_email'] ) ? sanitize_email( wp_unslash( $_POST['g_email'] ) ) : '';
	$referee = isset( $_POST['g_referee'] ) ? sanitize_text_field( wp_unslash( $_POST['g_referee'] ) ) : '';
	$heard   = isset( $_POST['g_heard'] ) ? sanitize_text_field( wp_unslash( $_POST['g_heard'] ) ) : '';
	if ( ! $name || ! is_email( $email ) ) {
		wp_die( 'Please go back and enter your name and a valid email address.' );
	}
	brra_attendance_add(
		$event_id,
		array(
			'name'       => $name,
			'email'      => $email,
			'phone'      => isset( $_POST['g_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['g_phone'] ) ) : '',
			'referee'    => in_array( $referee, brra_guest_referee_options(), true ) ? $referee : '',
			'heard'      => in_array( $heard, brra_guest_heard_options(), true ) ? $heard : '',
			'contact_ok' => ! empty( $_POST['g_contact'] ),
			'source'     => 'qr',
		)
	);
	// phpcs:enable WordPress.Security.NonceVerification
	wp_safe_redirect( add_query_arg( 'checked', 'yes', $back ) );
	exit;
}
add_action( 'admin_post_brra_checkin_guest', 'brra_handle_checkin_guest' );
add_action( 'admin_post_nopriv_brra_checkin_guest', 'brra_handle_checkin_guest' );

// ---------------------------------------------------------------- Events > Attendance.

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=brra_event', 'Attendance', 'Attendance', 'edit_posts', 'brra-attendance', 'brra_attendance_page' );
		// Committee members who can't edit the website still get the event-night screen.
		if ( ! current_user_can( 'edit_posts' ) && function_exists( 'brra_is_committee' ) && brra_is_committee() ) {
			add_menu_page( 'Event night', 'Event night', 'read', 'brra-event-night', 'brra_attendance_page', 'dashicons-groups', 3 );
		}
	}
);

/**
 * Can this person use the Attendance screen? Website editors and the committee.
 */
function brra_can_attendance() {
	return current_user_can( 'edit_posts' ) || ( function_exists( 'brra_is_committee' ) && brra_is_committee() );
}

/**
 * Link to the Attendance screen (Events > Attendance, or "Event night" for committee members).
 *
 * @param array $args Query args.
 */
function brra_attendance_admin_url( $args = array() ) {
	if ( current_user_can( 'edit_posts' ) ) {
		return add_query_arg( array_merge( array( 'post_type' => 'brra_event', 'page' => 'brra-attendance' ), $args ), admin_url( 'edit.php' ) );
	}
	return add_query_arg( array_merge( array( 'page' => 'brra-event-night' ), $args ), admin_url( 'admin.php' ) );
}

/**
 * All events, newest first, for the picker.
 */
function brra_all_events() {
	return get_posts(
		array(
			'post_type'      => 'brra_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => '_brra_date', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
		)
	);
}

/**
 * The event to show first: today's, else the most recent one that has happened, else the next one.
 */
function brra_attendance_default_event() {
	$today = wp_date( 'Y-m-d' );
	$next  = 0;
	foreach ( brra_all_events() as $e ) {
		$date = brra_event_get( $e->ID, 'date' );
		if ( $date <= $today ) {
			return $e->ID;
		}
		$next = $e->ID;
	}
	return $next;
}

/**
 * Admin link for an attendance action.
 *
 * @param string $action   admin-post action.
 * @param array  $args     Extra query args.
 */
function brra_attendance_action_url( $action, $args = array() ) {
	return wp_nonce_url( add_query_arg( array_merge( array( 'action' => $action ), $args ), admin_url( 'admin-post.php' ) ), $action );
}

/**
 * The Attendance screen.
 */
function brra_attendance_page() {
	$event_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : brra_attendance_default_event(); // phpcs:ignore WordPress.Security.NonceVerification
	$events   = brra_all_events();
	echo '<div class="wrap"><h1>Attendance</h1>';
	if ( ! $events ) {
		echo '<p>Add an event first.</p></div>';
		return;
	}
	if ( ! brra_can_attendance() ) {
		echo '<p>Sorry, you are not allowed to do that.</p></div>';
		return;
	}
	$edit = current_user_can( 'edit_posts' );
	echo '<form method="get" action="' . esc_url( admin_url( $edit ? 'edit.php' : 'admin.php' ) ) . '" style="margin:12px 0">' . ( $edit ? '<input type="hidden" name="post_type" value="brra_event"><input type="hidden" name="page" value="brra-attendance">' : '<input type="hidden" name="page" value="brra-event-night">' ) . '<select name="event">';
	foreach ( $events as $e ) {
		$start = brra_event_time( $e->ID );
		echo '<option value="' . esc_attr( $e->ID ) . '"' . selected( $e->ID, $event_id, false ) . '>' . esc_html( ( $start ? wp_date( 'j M Y', $start->getTimestamp() ) . ': ' : '' ) . get_the_title( $e ) ) . '</option>';
	}
	echo '</select> <button class="button">Show</button></form>';

	$rows   = brra_attendance_for_event( $event_id );
	$rsvps  = get_post_meta( $event_id, '_brra_rsvps', true );
	$rsvps  = is_array( $rsvps ) ? $rsvps : array();
	$came   = array();
	$guests = 0;
	foreach ( $rows as $r ) {
		$came[ $r->email ] = true;
		$guests           += $r->user_id ? 0 : 1;
	}
	$no_show = array_diff_key( $rsvps, $came );

	echo '<p style="font-size:15px"><strong>' . count( $rsvps ) . '</strong> said they were coming · <strong>' . count( $rows ) . '</strong> came (' . esc_html( sprintf( _n( '%d member', '%d members', count( $rows ) - $guests, 'brra' ), count( $rows ) - $guests ) . ', ' . sprintf( _n( '%d guest', '%d guests', $guests, 'brra' ), $guests ) ) . ')</p>';
	echo '<p><a class="button button-primary" target="_blank" href="' . esc_url( brra_attendance_action_url( 'brra_checkin_sign', array( 'event' => $event_id ) ) ) . '">Print the check-in sign</a> ';
	echo '<a class="button" href="' . esc_url( brra_attendance_action_url( 'brra_attendance_csv', array( 'event' => $event_id ) ) ) . '">Download this event</a> ';
	echo '<a class="button" href="' . esc_url( brra_attendance_action_url( 'brra_attendance_csv', array( 'season' => 1 ) ) ) . '">Download the whole season</a></p>';
	if ( isset( $_GET['added'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success inline"><p>' . ( '1' === $_GET['added'] ? 'Added.' : 'They were already on the list.' ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
	}

	echo '<h2>Who came</h2>';
	if ( ! $rows ) {
		echo '<p>No one has checked in yet.</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Member or guest</th><th>Email</th><th>Phone</th><th>Referee</th><th>Heard about us</th><th>Can contact</th><th>Checked in</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td><strong>' . esc_html( $r->name ) . '</strong></td><td>' . ( $r->user_id ? 'Member' : 'Guest' ) . '</td><td>' . esc_html( $r->email ) . '</td><td>' . esc_html( $r->phone ) . '</td><td>' . esc_html( $r->referee ) . '</td><td>' . esc_html( $r->heard ) . '</td><td>' . ( $r->user_id ? '' : ( $r->contact_ok ? 'Yes' : 'No' ) ) . '</td>';
			echo '<td>' . esc_html( mysql2date( 'g.ia', $r->created ) . ( 'door' === $r->source ? ' (door)' : '' ) ) . '</td>';
			echo '<td><a href="' . esc_url( brra_attendance_action_url( 'brra_attendance_remove', array( 'id' => $r->id, 'event' => $event_id ) ) ) . '" onclick="return confirm(\'Remove ' . esc_js( $r->name ) . '?\')">Remove</a></td></tr>';
		}
		echo '</tbody></table>';
	}

	if ( $no_show ) {
		echo '<h2>Said they were coming, not checked in</h2><table class="widefat striped" style="max-width:700px"><tbody>';
		foreach ( $no_show as $r ) {
			echo '<tr><td>' . esc_html( $r['name'] ) . '</td><td>' . esc_html( $r['email'] ) . '</td><td><a class="button button-small" href="' . esc_url( brra_attendance_action_url( 'brra_attendance_door', array( 'event' => $event_id, 'name' => $r['name'], 'email' => $r['email'] ) ) ) . '">They\'re here</a></td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '<h2>Add someone at the door</h2><p>For anyone without a phone. If the email matches a member\'s account, they\'re added as that member.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:grid;gap:8px;max-width:420px">';
	echo '<input type="hidden" name="action" value="brra_attendance_door"><input type="hidden" name="event" value="' . esc_attr( $event_id ) . '">';
	wp_nonce_field( 'brra_attendance_door' );
	echo '<input type="text" name="name" placeholder="Name" required><input type="email" name="email" placeholder="Email (optional for members)"><input type="text" name="phone" placeholder="Phone (optional)">';
	echo '<select name="referee"><option value="">Do they referee?</option>';
	foreach ( brra_guest_referee_options() as $o ) {
		echo '<option>' . esc_html( $o ) . '</option>';
	}
	echo '</select><label><input type="checkbox" name="contact_ok" value="1"> They\'re happy to be contacted about events and news</label><p><button class="button button-primary">Add</button></p></form>';

	brra_100club_box( $event_id );

	brra_attendance_season_summary();
	echo '</div>';
}

/**
 * This season's totals: events, attendances, members' counts and guests who have since joined.
 */
function brra_attendance_season_summary() {
	global $wpdb;
	$season = brra_current_season();
	$ids    = array();
	foreach ( brra_all_events() as $e ) {
		$date = brra_event_get( $e->ID, 'date' );
		if ( $date >= $season[0] . '-06-01' && $date < $season[1] . '-06-01' && $date <= wp_date( 'Y-m-d' ) ) {
			$ids[] = (int) $e->ID;
		}
	}
	echo '<h2>Season ' . esc_html( $season[0] . '/' . substr( $season[1], 2 ) ) . ' so far</h2>';
	if ( ! $ids ) {
		echo '<p>No events yet this season.</p>';
		return;
	}
	$table = brra_attendance_table();
	$in    = implode( ',', $ids );
	$rows  = $wpdb->get_results( "SELECT user_id, name, email, COUNT(*) AS n FROM $table WHERE event_id IN ($in) GROUP BY user_id, email, name ORDER BY n DESC, name" ); // phpcs:ignore WordPress.DB
	$total = 0;
	$mem   = array();
	$gst   = array();
	foreach ( $rows as $r ) {
		$total += (int) $r->n;
		if ( $r->user_id ) {
			$mem[] = $r;
		} else {
			$gst[] = $r;
		}
	}
	echo '<p><strong>' . count( $ids ) . '</strong> events · <strong>' . $total . '</strong> check-ins · <strong>' . count( $mem ) . '</strong> ' . _n( 'member', 'different members', count( $mem ), 'brra' ) . ' · <strong>' . count( $gst ) . '</strong> ' . _n( 'guest', 'different guests', count( $gst ), 'brra' ) . '</p>';
	echo '<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start">';
	echo '<table class="widefat striped" style="max-width:420px"><thead><tr><th>Member</th><th>Events</th></tr></thead><tbody>';
	foreach ( $mem as $r ) {
		echo '<tr><td>' . esc_html( $r->name ) . '</td><td>' . (int) $r->n . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<table class="widefat striped" style="max-width:520px"><thead><tr><th>Guest</th><th>Events</th><th>Joined since?</th></tr></thead><tbody>';
	foreach ( $gst as $r ) {
		$joined = $r->email && get_user_by( 'email', $r->email );
		echo '<tr><td>' . esc_html( $r->name ) . '</td><td>' . (int) $r->n . '</td><td>' . ( $joined ? '<strong>Yes</strong>' : 'Not yet' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

/**
 * Stop unless the current user can manage attendance and the nonce is right.
 *
 * @param string $action Nonce action.
 */
function brra_attendance_guard( $action ) {
	if ( ! brra_can_attendance() ) {
		wp_die( 'Sorry, you are not allowed to do that.' );
	}
	check_admin_referer( $action );
}

/**
 * Back to the Attendance screen for an event.
 *
 * @param int   $event_id Event ID.
 * @param array $args     Extra query args.
 */
function brra_attendance_back( $event_id, $args = array() ) {
	wp_safe_redirect( brra_attendance_admin_url( array_merge( array( 'event' => $event_id ), $args ) ) );
	exit;
}

add_action(
	'admin_post_brra_attendance_door',
	function () {
		brra_attendance_guard( 'brra_attendance_door' );
		// phpcs:disable WordPress.Security.NonceVerification -- checked above.
		$src      = $_POST ? $_POST : $_GET;
		$event_id = isset( $src['event'] ) ? absint( $src['event'] ) : 0;
		$added    = brra_attendance_add(
			$event_id,
			array(
				'name'       => isset( $src['name'] ) ? sanitize_text_field( wp_unslash( $src['name'] ) ) : '',
				'email'      => isset( $src['email'] ) ? sanitize_email( wp_unslash( $src['email'] ) ) : '',
				'phone'      => isset( $src['phone'] ) ? sanitize_text_field( wp_unslash( $src['phone'] ) ) : '',
				'referee'    => isset( $src['referee'] ) ? sanitize_text_field( wp_unslash( $src['referee'] ) ) : '',
				'contact_ok' => ! empty( $src['contact_ok'] ),
				'source'     => 'door',
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification
		brra_attendance_back( $event_id, array( 'added' => $added ? '1' : '0' ) );
	}
);

add_action(
	'admin_post_brra_attendance_remove',
	function () {
		global $wpdb;
		brra_attendance_guard( 'brra_attendance_remove' );
		$wpdb->delete( brra_attendance_table(), array( 'id' => isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		brra_attendance_back( isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0 );
	}
);

// Spreadsheet download for one event or the whole season.
add_action(
	'admin_post_brra_attendance_csv',
	function () {
		global $wpdb;
		brra_attendance_guard( 'brra_attendance_csv' );
		$table = brra_attendance_table();
		if ( ! empty( $_GET['season'] ) ) {
			$season = brra_current_season();
			$ids    = array();
			foreach ( brra_all_events() as $e ) {
				$date = brra_event_get( $e->ID, 'date' );
				if ( $date >= $season[0] . '-06-01' && $date < $season[1] . '-06-01' ) {
					$ids[] = (int) $e->ID;
				}
			}
			$rows = $ids ? $wpdb->get_results( "SELECT * FROM $table WHERE event_id IN (" . implode( ',', $ids ) . ') ORDER BY event_id, name' ) : array(); // phpcs:ignore WordPress.DB
			$file = 'attendance-' . $season[0] . '-' . $season[1] . '.csv';
		} else {
			$event_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;
			$rows     = brra_attendance_for_event( $event_id );
			$file     = 'attendance-' . sanitize_title( get_the_title( $event_id ) ) . '.csv';
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // So Excel reads £ and accents correctly.
		fputcsv( $out, array( 'Event', 'Event date', 'Name', 'Member or guest', 'Email', 'Phone', 'Referee', 'Heard about us', 'Happy to be contacted', 'Checked in', 'How' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, array( html_entity_decode( get_the_title( $r->event_id ), ENT_QUOTES ), brra_event_get( $r->event_id, 'date' ), $r->name, $r->user_id ? 'Member' : 'Guest', $r->email, $r->phone, $r->referee, $r->heard, $r->user_id ? '' : ( $r->contact_ok ? 'Yes' : 'No' ), $r->created, 'door' === $r->source ? 'Added at the door' : 'QR code' ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
);

// Printable A4 sign with the two QR codes.
add_action(
	'admin_post_brra_checkin_sign',
	function () {
		brra_attendance_guard( 'brra_checkin_sign' );
		$event_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;
		$start    = brra_event_time( $event_id );
		$crest    = get_theme_file_uri( 'assets/images/crest-white.png' );
		?>
<!doctype html>
<html lang="en-GB"><head><meta charset="utf-8"><title>Check-in sign: <?php echo esc_html( get_the_title( $event_id ) ); ?></title>
<style>
@page { size: A4; margin: 0; }
* { box-sizing: border-box; }
body { margin: 0; font-family: Montserrat, Arial, sans-serif; color: #0B1F33; background: #e9edf2; }
.sheet { width: 210mm; height: 297mm; margin: 0 auto; background: #fff; display: flex; flex-direction: column; }
.top { background: #0B1F33; color: #fff; padding: 14mm 16mm 10mm; display: flex; gap: 8mm; align-items: center; border-bottom: 3mm solid #D4AF37; }
.top img { width: 26mm; }
.top h1 { margin: 0; font-size: 30pt; line-height: 1.05; }
.top p { margin: 3mm 0 0; color: #D4AF37; font-weight: 700; font-size: 13pt; letter-spacing: .04em; text-transform: uppercase; }
.codes { flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 10mm; padding: 14mm 16mm; }
.code { border: 1mm solid #0B1F33; border-radius: 5mm; padding: 9mm 7mm; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 7mm; }
.code.guest { border-color: #D4AF37; }
.code h2 { margin: 0; font-size: 26pt; }
.code .qr { width: 72mm; height: 72mm; }
.code .qr img, .code .qr svg { width: 100%; height: 100%; }
.code p { margin: 0; font-size: 13pt; line-height: 1.4; }
.foot { padding: 0 16mm 14mm; text-align: center; font-size: 12pt; color: #44546A; }
.print { position: fixed; top: 12px; right: 12px; padding: 10px 18px; font-size: 15px; font-weight: 700; background: #D4AF37; border: 0; border-radius: 4px; cursor: pointer; }
@media print { body { background: #fff; } .print { display: none; } .sheet { margin: 0; } }
</style></head><body>
<button class="print" onclick="window.print()">Print</button>
<div class="sheet">
	<div class="top"><img src="<?php echo esc_url( $crest ); ?>" alt=""><div><h1>Check in here</h1><p><?php echo esc_html( get_the_title( $event_id ) ); ?><?php echo $start ? ' · ' . esc_html( wp_date( 'j F Y', $start->getTimestamp() ) ) : ''; ?></p></div></div>
	<div class="codes">
		<div class="code"><h2>Members</h2><div class="qr" data-url="<?php echo esc_attr( brra_checkin_url( $event_id, 'member' ) ); ?>"></div><p>Scan with your phone camera, then tap <strong>I'm here</strong>.</p><p>Not logged in? Log in once and your phone will remember you.</p></div>
		<div class="code guest"><h2>Guests</h2><div class="qr" data-url="<?php echo esc_attr( brra_checkin_url( $event_id, 'guest' ) ); ?>"></div><p>Welcome! Scan with your phone camera and fill in your details.</p><p>No phone? Just tell someone on the committee.</p></div>
	</div>
	<div class="foot">Bury &amp; Rochdale Referees' Association · buryrefs.org.uk</div>
</div>
<script src="<?php echo esc_url( BRRA_SITE_URL . 'assets/qrcode.js?ver=' . BRRA_SITE_VERSION ); ?>"></script>
<script>
document.querySelectorAll('.qr').forEach(function (el) {
	var qr = qrcode(0, 'M');
	qr.addData(el.getAttribute('data-url'));
	qr.make();
	el.innerHTML = qr.createSvgTag({ cellSize: 8, margin: 0, scalable: true });
});
</script>
</body></html>
		<?php
		exit;
	}
);

// ---------------------------------------------------------------- 100 Club draw on the night.

/**
 * 100 Club numbers and who holds them, e.g. array( 27 => 'Kaan Hayes' ).
 */
function brra_100club_holders() {
	$h = get_option( 'brra_100club_holders', array() );
	return is_array( $h ) ? $h : array();
}

/**
 * The 100 Club box on the Attendance screen: Norman enters 1st to 4th on the night.
 *
 * @param int $event_id Event ID.
 */
function brra_100club_box( $event_id ) {
	$d       = get_post_meta( $event_id, '_brra_100club', true );
	$places  = is_array( $d ) && ! empty( $d['places'] ) ? $d['places'] : array();
	$prizes  = get_option( 'brra_100club_prizes', array( '', '', '', '' ) );
	$holders = brra_100club_holders();
	echo '<h2 id="club100">100 Club draw</h2><p>Enter the numbers drawn tonight. The holder fills in from the list below, prizes go on the Treasurer\'s "to pay" list, and the results appear in the Fundraising item at the next committee meeting.</p>';
	if ( isset( $_GET['club'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success inline"><p>100 Club saved.</p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="brra_100club"><input type="hidden" name="event" value="' . esc_attr( $event_id ) . '">';
	wp_nonce_field( 'brra_100club' );
	echo '<table class="widefat" style="max-width:620px"><thead><tr><th>Place</th><th>Number</th><th>Holder</th><th>Prize (£)</th></tr></thead><tbody>';
	foreach ( array( '1st', '2nd', '3rd', '4th' ) as $i => $label ) {
		$num   = isset( $places[ $i ]['num'] ) ? $places[ $i ]['num'] : '';
		$prize = isset( $places[ $i ]['prize'] ) ? $places[ $i ]['prize'] : ( isset( $prizes[ $i ] ) ? $prizes[ $i ] : '' );
		$hold  = isset( $places[ $i ]['holder'] ) ? $places[ $i ]['holder'] : '';
		echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td><td><input type="number" min="1" max="999" name="num[' . (int) $i . ']" value="' . esc_attr( $num ) . '" style="width:80px" class="brra-club-num"></td><td class="brra-club-holder">' . esc_html( $num ? ( $hold ? $hold : 'Unsold' ) : '' ) . '</td><td><input type="number" min="0" step="0.01" name="prize[' . (int) $i . ']" value="' . esc_attr( $prize ) . '" style="width:90px"></td></tr>';
	}
	echo '</tbody></table>';
	echo '<details style="margin:10px 0;max-width:620px"><summary>Number holders (' . count( $holders ) . ')</summary><p>One per line: the number, then the name. For example <code>27 Kaan Hayes</code>.</p><textarea name="holders" rows="8" style="width:100%">';
	foreach ( $holders as $n => $name ) {
		echo esc_textarea( $n . ' ' . $name ) . "\n";
	}
	echo '</textarea></details><p><button class="button button-primary">Save draw</button></p></form>';
	echo '<script>(function(){var h=' . wp_json_encode( (object) $holders ) . ';document.querySelectorAll(".brra-club-num").forEach(function(i){i.addEventListener("input",function(){var c=i.closest("tr").querySelector(".brra-club-holder");c.textContent=i.value?(h[i.value]||"Unsold"):"";});});})();</script>';
}

add_action(
	'admin_post_brra_100club',
	function () {
		brra_attendance_guard( 'brra_100club' );
		// phpcs:disable WordPress.Security.NonceVerification -- checked above.
		$event_id = isset( $_POST['event'] ) ? absint( $_POST['event'] ) : 0;
		if ( isset( $_POST['holders'] ) ) {
			$holders = array();
			foreach ( preg_split( '/\r?\n/', sanitize_textarea_field( wp_unslash( $_POST['holders'] ) ) ) as $line ) {
				if ( preg_match( '/^\s*(\d+)\s+(.+?)\s*$/', $line, $m ) ) {
					$holders[ (int) $m[1] ] = $m[2];
				}
			}
			ksort( $holders );
			update_option( 'brra_100club_holders', $holders, false );
		}
		$holders = brra_100club_holders();
		$old     = get_post_meta( $event_id, '_brra_100club', true );
		$places  = array();
		$prizes  = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$num      = isset( $_POST['num'][ $i ] ) ? absint( $_POST['num'][ $i ] ) : 0;
			$prize    = isset( $_POST['prize'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['prize'][ $i ] ) ) : '';
			$prizes[] = $prize;
			if ( ! $num ) {
				continue;
			}
			$places[ $i ] = array(
				'num'    => $num,
				'holder' => isset( $holders[ $num ] ) ? $holders[ $num ] : '',
				'prize'  => $prize,
				'paid'   => is_array( $old ) && isset( $old['places'][ $i ]['num'] ) && (int) $old['places'][ $i ]['num'] === $num ? (int) ! empty( $old['places'][ $i ]['paid'] ) : 0,
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification
		update_option( 'brra_100club_prizes', $prizes, false );
		if ( $places ) {
			update_post_meta( $event_id, '_brra_100club', array( 'places' => $places, 'by' => get_current_user_id(), 'at' => current_time( 'mysql' ) ) );
		} else {
			delete_post_meta( $event_id, '_brra_100club' );
		}
		brra_attendance_back( $event_id, array( 'club' => 1 ) );
	}
);

// ---------------------------------------------------------------- Keeping data only as long as needed.

/**
 * Delete attendance records from before the previous season, as the privacy notice promises:
 * records (including guests' details) are kept for this season and the next, then deleted.
 */
add_action(
	'brra_clear_old_rsvps',
	function () {
		global $wpdb;
		$season = brra_current_season();
		$cutoff = ( $season[0] - 1 ) . '-06-01';
		$ids    = get_posts(
			array(
				'post_type'      => 'brra_event',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'key' => '_brra_date', 'value' => $cutoff, 'compare' => '<', 'type' => 'DATE' ),
				),
			)
		);
		if ( $ids ) {
			$table = brra_attendance_table();
			$wpdb->query( "DELETE FROM $table WHERE event_id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')' ); // phpcs:ignore WordPress.DB
		}
	}
);

// Remove someone's attendance records when their account is deleted.
add_action(
	'delete_user',
	function ( $user_id ) {
		global $wpdb;
		$wpdb->delete( brra_attendance_table(), array( 'user_id' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
);
