<?php
/**
 * Committee area: who is on the committee, what they can do, meetings (agenda, live minutes,
 * minutes document), reports, actions, finance, files, season calendar, guest speakers and
 * membership stats. Everything lives on the private /committee/ page ([brra_committee_area]).
 *
 * Data:
 * - People: user meta brra_committee_roles (array of role keys), brra_committee_order (int),
 *   brra_minutes_delegate ('' | 'on' | a meeting ID).
 * - Meetings: private brra_meeting posts, everything in one meta array (_brra_meeting) plus
 *   _brra_m_date for sorting.
 * - Lists kept as options: brra_finance, brra_speakers, brra_calendar_extra, brra_aob,
 *   brra_committee_files, brra_membership_history, brra_agenda_template, brra_100club_holders.
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------- People and permissions.

/**
 * Committee positions. A person can hold more than one.
 */
function brra_committee_roles_list() {
	return array(
		'chair'      => 'Chairman',
		'vice'       => 'Vice-Chairman',
		'secretary'  => 'Secretary',
		'media'      => 'Media Officer',
		'treasurer'  => 'Treasurer',
		'membership' => 'Membership Secretary',
		'member'     => 'Committee member',
		'president'  => 'President (can view only)',
	);
}

/**
 * Someone's committee positions.
 *
 * @param int $uid User ID.
 */
function brra_user_committee_roles( $uid ) {
	$r = get_user_meta( $uid, 'brra_committee_roles', true );
	return is_array( $r ) ? array_values( array_intersect( $r, array_keys( brra_committee_roles_list() ) ) ) : array();
}

/**
 * Current user ID, or the one given.
 *
 * @param int|null $uid User ID.
 */
function brra_c_user( $uid = null ) {
	return null === $uid ? get_current_user_id() : (int) $uid;
}

/**
 * Is this person on the committee (or a site admin)?
 *
 * @param int|null $uid User ID.
 */
function brra_is_committee( $uid = null ) {
	$uid = brra_c_user( $uid );
	if ( ! $uid ) {
		return false;
	}
	return user_can( $uid, 'manage_options' ) || (bool) brra_user_committee_roles( $uid );
}

/**
 * Does this person hold a position?
 *
 * @param string   $role Role key.
 * @param int|null $uid  User ID.
 */
function brra_has_committee_role( $role, $uid = null ) {
	return in_array( $role, brra_user_committee_roles( brra_c_user( $uid ) ), true );
}

/**
 * Can this person change things (everyone on the committee except view-only places such as the President)?
 *
 * @param int|null $uid User ID.
 */
function brra_committee_can_edit( $uid = null ) {
	$uid = brra_c_user( $uid );
	if ( ! brra_is_committee( $uid ) ) {
		return false;
	}
	$roles = brra_user_committee_roles( $uid );
	return user_can( $uid, 'manage_options' ) || array_diff( $roles, array( 'president' ) );
}

/**
 * Can this person build agendas, run meetings and take minutes? Chairman and Secretary always;
 * anyone the Secretary has made a delegate, either until switched off or for one meeting.
 *
 * @param int      $meeting_id Meeting ID (0 for "any").
 * @param int|null $uid        User ID.
 */
function brra_can_manage_minutes( $meeting_id = 0, $uid = null ) {
	$uid = brra_c_user( $uid );
	if ( ! $uid ) {
		return false;
	}
	if ( user_can( $uid, 'manage_options' ) || brra_has_committee_role( 'chair', $uid ) || brra_has_committee_role( 'secretary', $uid ) ) {
		return true;
	}
	if ( ! brra_is_committee( $uid ) ) {
		return false;
	}
	$d = (string) get_user_meta( $uid, 'brra_minutes_delegate', true );
	return 'on' === $d || ( $d && (int) $d === (int) $meeting_id && $meeting_id );
}

/**
 * Can this person manage the committee list and delegates?
 *
 * @param int|null $uid User ID.
 */
function brra_can_manage_committee( $uid = null ) {
	$uid = brra_c_user( $uid );
	return $uid && ( user_can( $uid, 'manage_options' ) || brra_has_committee_role( 'secretary', $uid ) || brra_has_committee_role( 'chair', $uid ) );
}

/**
 * Can this person see members' names and emails? Membership Secretary and Secretary (and admins).
 *
 * @param int|null $uid User ID.
 */
function brra_can_see_member_names( $uid = null ) {
	$uid = brra_c_user( $uid );
	return $uid && ( user_can( $uid, 'manage_options' ) || brra_has_committee_role( 'secretary', $uid ) || brra_has_committee_role( 'membership', $uid ) );
}

/**
 * Can this person see and update the money (finance files, balances, prizes paid)?
 *
 * @param int|null $uid User ID.
 */
function brra_can_manage_finance( $uid = null ) {
	$uid = brra_c_user( $uid );
	return $uid && ( user_can( $uid, 'manage_options' ) || brra_has_committee_role( 'treasurer', $uid ) || brra_has_committee_role( 'chair', $uid ) );
}

/**
 * Everyone on the committee, in their set order.
 *
 * @param bool $with_president Include view-only places.
 */
function brra_committee_people( $with_president = true ) {
	$users = get_users(
		array(
			'meta_key'     => 'brra_committee_roles', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_compare' => 'EXISTS',
		)
	);
	$out = array();
	foreach ( $users as $u ) {
		$roles = brra_user_committee_roles( $u->ID );
		if ( ! $roles || ( ! $with_president && array( 'president' ) === $roles ) ) {
			continue;
		}
		$out[] = $u;
	}
	usort(
		$out,
		function ( $a, $b ) {
			$oa = (int) get_user_meta( $a->ID, 'brra_committee_order', true );
			$ob = (int) get_user_meta( $b->ID, 'brra_committee_order', true );
			return $oa === $ob ? strcmp( $a->display_name, $b->display_name ) : $oa - $ob;
		}
	);
	return $out;
}

/**
 * Someone's positions as words, e.g. "Secretary and Media Officer".
 *
 * @param int $uid User ID.
 */
function brra_committee_title( $uid ) {
	$labels = brra_committee_roles_list();
	$names  = array();
	foreach ( brra_user_committee_roles( $uid ) as $r ) {
		$names[] = 'president' === $r ? 'President' : $labels[ $r ];
	}
	if ( ! $names ) {
		return user_can( $uid, 'manage_options' ) ? 'Site admin' : '';
	}
	$last = array_pop( $names );
	return $names ? implode( ', ', $names ) . ' and ' . $last : $last;
}

/**
 * A person's name.
 *
 * @param int $uid User ID.
 */
function brra_c_name( $uid ) {
	$u = $uid ? get_userdata( $uid ) : null;
	if ( ! $u ) {
		return '';
	}
	$name = trim( $u->first_name . ' ' . $u->last_name );
	return $name ? $name : $u->display_name;
}

/**
 * First person holding a position.
 *
 * @param string $role Role key.
 */
function brra_committee_holder( $role ) {
	foreach ( brra_committee_people() as $u ) {
		if ( brra_has_committee_role( $role, $u->ID ) ) {
			return (int) $u->ID;
		}
	}
	return 0;
}

// Swap the header's "Join now" button for a gold "Committee" button for committee members.
add_filter(
	'render_block',
	function ( $html, $block ) {
		if ( 'core/button' !== $block['blockName'] || false === strpos( $html, 'Join now' ) || ! brra_is_committee() ) {
			return $html;
		}
		return '<div class="wp-block-button brra-committee-btn"><a class="wp-block-button__link wp-element-button" href="' . esc_url( brra_c_url() ) . '">Committee</a></div>';
	},
	10,
	2
);

// Keep the committee page out of search engines.
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_page( 'committee' ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
);

// ---------------------------------------------------------------- URLs and posting.

/**
 * Link to a committee area screen.
 *
 * @param string $view Screen.
 * @param array  $args Extra query args.
 */
function brra_c_url( $view = '', $args = array() ) {
	$page = get_page_by_path( 'committee' );
	$base = $page ? get_permalink( $page ) : home_url( '/committee/' );
	if ( $view ) {
		$args = array_merge( array( 'view' => $view ), $args );
	}
	return $args ? add_query_arg( $args, $base ) : $base;
}

/**
 * Hidden fields for a committee form.
 *
 * @param string $do What the form does.
 * @param array  $extra Extra hidden fields.
 */
function brra_c_form_fields( $do, $extra = array() ) {
	$out = '<input type="hidden" name="action" value="brra_c"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
	foreach ( $extra as $k => $v ) {
		$out .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	return $out . wp_nonce_field( 'brra_c', '_brra_c', true, false );
}

/**
 * Opening tag of a committee form.
 *
 * @param string $do    What the form does.
 * @param array  $extra Extra hidden fields.
 * @param string $attrs Extra attributes.
 */
function brra_c_form( $do, $extra = array(), $attrs = '' ) {
	return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" ' . $attrs . '>' . brra_c_form_fields( $do, $extra );
}

/**
 * Link that runs a committee action (for simple one-click things).
 *
 * @param string $do   What it does.
 * @param array  $args Query args.
 */
function brra_c_action_url( $do, $args = array() ) {
	return add_query_arg( array_merge( array( 'action' => 'brra_c', 'do' => $do, '_brra_c' => wp_create_nonce( 'brra_c' ) ), $args ), admin_url( 'admin-post.php' ) );
}

/**
 * A posted value, cleaned.
 *
 * @param string $key     Field.
 * @param string $default Default.
 * @param bool   $multi   Multi-line text.
 */
function brra_c_in( $key, $default = '', $multi = false ) {
	// phpcs:disable WordPress.Security.NonceVerification -- checked in the dispatcher.
	$src = isset( $_POST[ $key ] ) ? $_POST : $_GET;
	if ( ! isset( $src[ $key ] ) || is_array( $src[ $key ] ) ) {
		return $default;
	}
	$v = wp_unslash( $src[ $key ] );
	// phpcs:enable WordPress.Security.NonceVerification
	return $multi ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
}

/**
 * Clean a nested array of posted text.
 *
 * @param mixed $v Value.
 */
function brra_c_clean( $v ) {
	if ( is_array( $v ) ) {
		return array_map( 'brra_c_clean', $v );
	}
	return sanitize_textarea_field( (string) $v );
}

/**
 * Back to a committee screen with a message.
 *
 * @param string $view   Screen.
 * @param array  $args   Query args.
 * @param string $notice Message key.
 */
function brra_c_back( $view, $args = array(), $notice = 'saved' ) {
	if ( $notice ) {
		$args['done'] = $notice;
	}
	wp_safe_redirect( brra_c_url( $view, $args ) );
	exit;
}

/**
 * Stop with a message.
 *
 * @param string $msg Message.
 */
function brra_c_die( $msg = 'Sorry, you are not allowed to do that.' ) {
	wp_die( esc_html( $msg ), 'Committee area', array( 'response' => 403, 'back_link' => true ) );
}

// One entry point for every committee form and button.
add_action(
	'admin_post_brra_c',
	function () {
		$nonce = isset( $_REQUEST['_brra_c'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_brra_c'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'brra_c' ) ) {
			brra_c_die( 'This page has expired. Please go back, refresh and try again.' );
		}
		if ( ! brra_is_committee() ) {
			brra_c_die();
		}
		$do = brra_c_in( 'do' );
		$fn = 'brra_c_do_' . preg_replace( '/[^a-z0-9_]/', '', $do );
		if ( ! function_exists( $fn ) ) {
			brra_c_die( 'Unknown action.' );
		}
		call_user_func( $fn );
	}
);

// ---------------------------------------------------------------- Committee and delegates.

/**
 * Save the committee list: positions, order and delegates.
 */
function brra_c_do_people() {
	if ( ! brra_can_manage_committee() ) {
		brra_c_die();
	}
	// phpcs:disable WordPress.Security.NonceVerification -- checked in the dispatcher.
	$rows = isset( $_POST['people'] ) && is_array( $_POST['people'] ) ? wp_unslash( $_POST['people'] ) : array();
	// phpcs:enable WordPress.Security.NonceVerification
	$valid = array_keys( brra_committee_roles_list() );
	foreach ( $rows as $uid => $row ) {
		$uid = absint( $uid );
		if ( ! $uid || ! get_userdata( $uid ) ) {
			continue;
		}
		$roles = isset( $row['roles'] ) && is_array( $row['roles'] ) ? array_values( array_intersect( array_map( 'sanitize_key', $row['roles'] ), $valid ) ) : array();
		if ( ! empty( $row['remove'] ) ) {
			$roles = array();
		}
		if ( $uid === get_current_user_id() && ! array_intersect( $roles, array( 'secretary', 'chair' ) ) && ! current_user_can( 'manage_options' ) ) {
			$roles = brra_user_committee_roles( $uid ); // Don't lock yourself out.
		}
		if ( $roles ) {
			update_user_meta( $uid, 'brra_committee_roles', $roles );
		} else {
			delete_user_meta( $uid, 'brra_committee_roles' );
			delete_user_meta( $uid, 'brra_minutes_delegate' );
			continue;
		}
		update_user_meta( $uid, 'brra_committee_order', isset( $row['order'] ) ? (int) $row['order'] : 0 );
		$d = isset( $row['delegate'] ) ? sanitize_text_field( $row['delegate'] ) : '';
		update_user_meta( $uid, 'brra_minutes_delegate', ( 'on' === $d || ctype_digit( $d ) ) ? $d : '' );
	}
	$new = absint( brra_c_in( 'add_user' ) );
	if ( $new && get_userdata( $new ) ) {
		$role = sanitize_key( brra_c_in( 'add_role', 'member' ) );
		update_user_meta( $new, 'brra_committee_roles', array( in_array( $role, $valid, true ) ? $role : 'member' ) );
		update_user_meta( $new, 'brra_committee_order', 99 );
	}
	brra_c_back( 'people' );
}

// ---------------------------------------------------------------- Meetings.

add_action(
	'init',
	function () {
		register_post_type(
			'brra_meeting',
			array(
				'label'   => 'Committee meetings',
				'public'  => false,
				'show_ui' => false,
			)
		);
	}
);

/**
 * Reports that can be attached to the agenda, and who writes each.
 */
function brra_report_kinds() {
	return array(
		'secretary'  => array( 'Secretary\'s report and correspondence', 'secretary' ),
		'treasurer'  => array( 'Treasurer\'s report', 'treasurer' ),
		'membership' => array( 'Membership Secretary\'s report', 'membership' ),
		'media'      => array( 'Media report', 'media' ),
		'mds'        => array( 'MDS programme', '' ),
	);
}

/**
 * Standing items every agenda starts with. Saved from an agenda with "Use as standing items".
 */
function brra_agenda_template() {
	$t = get_option( 'brra_agenda_template' );
	if ( is_array( $t ) && $t ) {
		return $t;
	}
	return array(
		array( 'Chairman\'s welcome and apologies for absence', 'welcome', '' ),
		array( 'Minutes of the previous meeting', 'minutes', '' ),
		array( 'Matters arising', 'arising', '' ),
		array( 'Secretary\'s report and correspondence', 'report', 'secretary' ),
		array( 'Treasurer\'s report', 'report', 'treasurer' ),
		array( 'Membership Secretary\'s report', 'report', 'membership' ),
		array( 'Fundraising: Bonus Ball and 100 Club', 'fundraising', '' ),
		array( 'Media report', 'report', 'media' ),
		array( 'MDS programme', 'report', 'mds' ),
		array( 'Any other business', 'aob', '' ),
		array( 'Date of next meeting', 'next', '' ),
	);
}

/**
 * A new agenda item.
 *
 * @param string $title  Title.
 * @param string $kind   welcome, minutes, arising, report, fundraising, aob, next or custom.
 * @param string $report Report key for report items.
 * @param int    $lead   Who leads it.
 */
function brra_new_item( $title, $kind = 'custom', $report = '', $lead = 0 ) {
	if ( 'report' === $kind && ! $lead ) {
		$kinds = brra_report_kinds();
		$lead  = isset( $kinds[ $report ] ) && $kinds[ $report ][1] ? brra_committee_holder( $kinds[ $report ][1] ) : 0;
	}
	return array(
		'key'      => strtolower( wp_generate_password( 8, false ) ),
		'title'    => $title,
		'kind'     => $kind,
		'report'   => $report,
		'lead'     => (int) $lead,
		'subs'     => array(),
		'notes'    => '',
		'decision' => '',
		'actions'  => array(),
		'f'        => array(),
	);
}

/**
 * A meeting, with defaults filled in.
 *
 * @param int $id Meeting ID.
 */
function brra_meeting( $id ) {
	$m = get_post_meta( $id, '_brra_meeting', true );
	$m = is_array( $m ) ? $m : array();
	return array_merge(
		array(
			'id'       => (int) $id,
			'date'     => '',
			'time'     => '20:00',
			'venue'    => 'St Mary\'s Social Club, Pine Street, Radcliffe, M26 2WQ',
			'status'   => 'draft',
			'opened'   => '',
			'closed'   => '',
			'items'    => array(),
			'att'      => array(),
			'also'     => '',
			'chair'    => 0,
			'minuter'  => 0,
			'reports'  => array(),
			'sent'     => '',
			'approved' => '',
		),
		$m,
		array( 'id' => (int) $id )
	);
}

/**
 * Save a meeting.
 *
 * @param array $m Meeting.
 */
function brra_meeting_save( $m ) {
	update_post_meta( $m['id'], '_brra_meeting', $m );
	update_post_meta( $m['id'], '_brra_m_date', $m['date'] );
	wp_update_post(
		array(
			'ID'         => $m['id'],
			'post_title' => 'Committee meeting ' . $m['date'],
		)
	);
}

/**
 * All meetings, newest first.
 */
function brra_meetings() {
	$ids = get_posts(
		array(
			'post_type'      => 'brra_meeting',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_brra_m_date', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
		)
	);
	return array_map( 'brra_meeting', $ids );
}

/**
 * The meeting before this one.
 *
 * @param array $m Meeting.
 */
function brra_previous_meeting( $m ) {
	foreach ( brra_meetings() as $x ) {
		if ( $x['date'] < $m['date'] ) {
			return $x;
		}
	}
	return null;
}

/**
 * The next meeting that hasn't been closed yet.
 */
function brra_next_meeting() {
	$next = null;
	foreach ( brra_meetings() as $m ) {
		if ( in_array( $m['status'], array( 'draft', 'sent', 'open' ), true ) ) {
			$next = $m; // List is newest first, so the last match is the earliest.
		}
	}
	return $next;
}

/**
 * Meeting status in words.
 *
 * @param string $s Status.
 */
function brra_meeting_status_label( $s ) {
	$l = array(
		'draft'    => array( 'Agenda draft', '' ),
		'sent'     => array( 'Agenda sent', 'n' ),
		'open'     => array( 'Meeting open', 'a' ),
		'closed'   => array( 'Minutes draft', 'a' ),
		'approved' => array( 'Minutes approved', 'g' ),
	);
	return isset( $l[ $s ] ) ? $l[ $s ] : array( $s, '' );
}

/**
 * Pretty date, e.g. "Thursday 10 December 2026".
 *
 * @param string $ymd    Date.
 * @param string $format Format.
 */
function brra_c_date( $ymd, $format = 'l j F Y' ) {
	if ( ! $ymd ) {
		return '';
	}
	$d = date_create_immutable( $ymd, wp_timezone() );
	return $d ? wp_date( $format, $d->getTimestamp() ) : $ymd;
}

/**
 * Date with an ordinal, as in the minutes ("Thursday 10th December 2026").
 *
 * @param string $ymd Date.
 */
function brra_c_date_long( $ymd ) {
	return brra_c_date( $ymd, 'l jS F Y' );
}

/**
 * Pretty time from "20:00".
 *
 * @param string $hm Time.
 */
function brra_c_time( $hm ) {
	if ( ! $hm ) {
		return '';
	}
	$t = strtotime( '2000-01-01 ' . $hm );
	return $t ? strtolower( gmdate( 'g.ia', $t ) ) : $hm;
}

/**
 * Every action from every meeting.
 *
 * @param bool $open_only Only those not done.
 */
function brra_all_actions( $open_only = false ) {
	$out = array();
	foreach ( brra_meetings() as $m ) {
		foreach ( $m['items'] as $n => $item ) {
			foreach ( $item['actions'] as $a ) {
				if ( empty( $a['text'] ) || ( $open_only && ! empty( $a['done'] ) ) ) {
					continue;
				}
				$a['meeting'] = $m['id'];
				$a['date']    = $m['date'];
				$a['item']    = ( $n + 1 ) . '. ' . $item['title'];
				$out[]        = $a;
			}
		}
	}
	usort(
		$out,
		function ( $a, $b ) {
			$da = empty( $a['due'] ) ? '9999' : $a['due'];
			$db = empty( $b['due'] ) ? '9999' : $b['due'];
			return strcmp( $da, $db );
		}
	);
	return $out;
}

/**
 * Create a meeting from the standing items, carrying forward open actions and AOB suggestions.
 */
function brra_c_do_meeting_new() {
	if ( ! brra_can_manage_minutes() ) {
		brra_c_die();
	}
	$date = brra_c_in( 'date' );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		brra_c_back( 'meetings', array(), 'baddate' );
	}
	$id = wp_insert_post(
		array(
			'post_type'   => 'brra_meeting',
			'post_status' => 'private',
			'post_title'  => 'Committee meeting ' . $date,
		)
	);
	$m          = brra_meeting( $id );
	$m['date']  = $date;
	$m['time']  = brra_c_in( 'time', '20:00' );
	$m['venue'] = brra_c_in( 'venue', $m['venue'] );
	$m['chair'] = brra_committee_holder( 'chair' );
	$prev       = brra_previous_meeting( $m );
	foreach ( brra_agenda_template() as $t ) {
		$item = brra_new_item( $t[0], $t[1], $t[2] );
		if ( 'minutes' === $t[1] && $prev ) {
			$item['title'] = 'Minutes of the meeting held on ' . brra_c_date_long( $prev['date'] );
		}
		if ( 'arising' === $t[1] ) {
			foreach ( brra_all_actions( true ) as $a ) {
				$item['subs'][] = array(
					'title'  => $a['text'] . ( ! empty( $a['owner'] ) ? ' (' . brra_c_name( $a['owner'] ) . ')' : '' ),
					'notes'  => '',
					'action' => $a['id'],
				);
			}
		}
		if ( 'aob' === $t[1] ) {
			$aob = get_option( 'brra_aob', array() );
			foreach ( $aob as $k => $s ) {
				if ( empty( $s['meeting'] ) ) {
					$item['subs'][]       = array( 'title' => $s['title'] . ' (raised by ' . brra_c_name( $s['by'] ) . ')', 'notes' => '' );
					$aob[ $k ]['meeting'] = $id;
				}
			}
			update_option( 'brra_aob', $aob, false );
		}
		$m['items'][] = $item;
	}
	brra_meeting_save( $m );
	brra_c_back( 'agenda', array( 'id' => $id ) );
}

/**
 * Find an item's position by key.
 *
 * @param array  $m   Meeting.
 * @param string $key Item key.
 */
function brra_item_index( $m, $key ) {
	foreach ( $m['items'] as $i => $it ) {
		if ( $it['key'] === $key ) {
			return $i;
		}
	}
	return -1;
}

/**
 * Load a meeting from the request, checking the person may manage it.
 *
 * @param bool $manage Needs minute-taking rights.
 */
function brra_c_meeting_from_request( $manage = true ) {
	$id = absint( brra_c_in( 'id' ) );
	if ( ! $id || 'brra_meeting' !== get_post_type( $id ) ) {
		brra_c_die( 'Meeting not found.' );
	}
	if ( $manage && ! brra_can_manage_minutes( $id ) ) {
		brra_c_die();
	}
	return brra_meeting( $id );
}

/**
 * Agenda builder: renames and leads first, then the button that was pressed.
 */
function brra_c_do_agenda() {
	$m = brra_c_meeting_from_request();
	// phpcs:disable WordPress.Security.NonceVerification -- checked in the dispatcher.
	$items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
	// phpcs:enable WordPress.Security.NonceVerification
	$m['date']  = preg_match( '/^\d{4}-\d{2}-\d{2}$/', brra_c_in( 'date' ) ) ? brra_c_in( 'date' ) : $m['date'];
	$m['time']  = brra_c_in( 'time', $m['time'] );
	$m['venue'] = brra_c_in( 'venue', $m['venue'] );
	foreach ( $m['items'] as $i => $it ) {
		if ( isset( $items[ $it['key'] ] ) ) {
			$row = $items[ $it['key'] ];
			if ( isset( $row['title'] ) && '' !== trim( $row['title'] ) ) {
				$m['items'][ $i ]['title'] = sanitize_text_field( $row['title'] );
			}
			$m['items'][ $i ]['lead'] = isset( $row['lead'] ) ? absint( $row['lead'] ) : $it['lead'];
			$keep                     = isset( $row['subs'] ) && is_array( $row['subs'] ) ? array_map( 'absint', $row['subs'] ) : array();
			$subs                     = array();
			foreach ( $it['subs'] as $si => $s ) {
				if ( in_array( $si, $keep, true ) ) {
					$subs[] = $s;
				}
			}
			$m['items'][ $i ]['subs'] = $subs;
		}
	}
	$op  = explode( '|', brra_c_in( 'op', 'save' ) );
	$key = isset( $op[1] ) ? $op[1] : '';
	$i   = brra_item_index( $m, $key );
	switch ( $op[0] ) {
		case 'up':
		case 'down':
			$j = 'up' === $op[0] ? $i - 1 : $i + 1;
			if ( $i >= 0 && isset( $m['items'][ $j ] ) ) {
				$tmp               = $m['items'][ $i ];
				$m['items'][ $i ]  = $m['items'][ $j ];
				$m['items'][ $j ]  = $tmp;
			}
			break;
		case 'remove':
			if ( $i >= 0 ) {
				array_splice( $m['items'], $i, 1 );
			}
			break;
		case 'add':
			$title = brra_c_in( 'new_title' );
			if ( $title ) {
				$lead = absint( brra_c_in( 'new_lead' ) );
				if ( 'aob' === brra_c_in( 'new_where' ) ) {
					foreach ( $m['items'] as $n => $it ) {
						if ( 'aob' === $it['kind'] ) {
							$m['items'][ $n ]['subs'][] = array( 'title' => $title . ( $lead ? ' (' . brra_c_name( $lead ) . ')' : '' ), 'notes' => '' );
							break 2;
						}
					}
				}
				$new = brra_new_item( $title, 'custom', '', $lead );
				$pos = count( $m['items'] );
				foreach ( $m['items'] as $n => $it ) {
					if ( 'aob' === $it['kind'] ) {
						$pos = $n;
						break;
					}
				}
				array_splice( $m['items'], $pos, 0, array( $new ) );
			}
			break;
		case 'aobadd':
			$aob = get_option( 'brra_aob', array() );
			if ( isset( $aob[ $key ] ) ) {
				foreach ( $m['items'] as $n => $it ) {
					if ( 'aob' === $it['kind'] ) {
						$m['items'][ $n ]['subs'][] = array( 'title' => $aob[ $key ]['title'] . ' (raised by ' . brra_c_name( $aob[ $key ]['by'] ) . ')', 'notes' => '' );
					}
				}
				$aob[ $key ]['meeting'] = $m['id'];
				update_option( 'brra_aob', $aob, false );
			}
			break;
		case 'template':
			$t = array();
			foreach ( $m['items'] as $it ) {
				$title = 'minutes' === $it['kind'] ? 'Minutes of the previous meeting' : $it['title'];
				$t[]   = array( $title, $it['kind'], $it['report'] );
			}
			update_option( 'brra_agenda_template', $t, false );
			brra_meeting_save( $m );
			brra_c_back( 'agenda', array( 'id' => $m['id'] ), 'template' );
			break;
		case 'send':
			$m['status'] = 'open' === $m['status'] ? 'open' : 'sent';
			$m['sent']   = current_time( 'mysql' );
			brra_meeting_save( $m );
			$to = array();
			foreach ( brra_committee_people() as $u ) {
				$to[] = $u->user_email;
			}
			$subject = 'Committee meeting agenda: ' . brra_c_date( $m['date'] );
			$body    = "Hi all,\n\nThe agenda for the committee meeting on " . brra_c_date( $m['date'] ) . ' at ' . brra_c_time( $m['time'] ) . " is ready:\n\n" . brra_c_url( 'view', array( 'id' => $m['id'] ) ) . "\n\nIf you have a report to give, please add it in the committee area before the meeting so everyone can read it on the night.\n\n" . brra_c_name( get_current_user_id() );
			if ( $to ) {
				wp_mail( array_shift( $to ), $subject, $body, $to ? array( 'Bcc: ' . implode( ',', $to ) ) : array() );
			}
			brra_c_back( 'agenda', array( 'id' => $m['id'] ), 'sent' );
			break;
		case 'delete':
			if ( in_array( $m['status'], array( 'draft', 'sent' ), true ) ) {
				wp_delete_post( $m['id'], true );
				brra_c_back( 'meetings', array(), 'deleted' );
			}
			break;
	}
	brra_meeting_save( $m );
	brra_c_back( 'agenda', array( 'id' => $m['id'] ) );
}

/**
 * Open the meeting (Chairman's opening time) and go to the live screen.
 */
function brra_c_do_meeting_open() {
	$m = brra_c_meeting_from_request();
	if ( in_array( $m['status'], array( 'draft', 'sent' ), true ) ) {
		$m['status']  = 'open';
		$m['opened']  = current_time( 'H:i' );
		$m['minuter'] = get_current_user_id();
		foreach ( brra_committee_people( false ) as $u ) {
			if ( ! isset( $m['att'][ $u->ID ] ) ) {
				$m['att'][ $u->ID ] = '';
			}
		}
		brra_meeting_save( $m );
	}
	brra_c_back( 'live', array( 'id' => $m['id'] ), '' );
}

/**
 * Save the live minutes (also used for auto-save while typing).
 */
function brra_c_do_live() {
	$m = brra_c_meeting_from_request();
	// phpcs:disable WordPress.Security.NonceVerification -- checked in the dispatcher.
	$att   = isset( $_POST['att'] ) && is_array( $_POST['att'] ) ? brra_c_clean( wp_unslash( $_POST['att'] ) ) : array();
	$items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? brra_c_clean( wp_unslash( $_POST['items'] ) ) : array();
	// phpcs:enable WordPress.Security.NonceVerification
	foreach ( $att as $uid => $v ) {
		$m['att'][ absint( $uid ) ] = in_array( $v, array( 'present', 'apologies', 'absent' ), true ) ? $v : '';
	}
	$m['also']   = brra_c_in( 'also', $m['also'], true );
	$m['opened'] = brra_c_in( 'opened', $m['opened'] );
	foreach ( $m['items'] as $i => $it ) {
		if ( ! isset( $items[ $it['key'] ] ) ) {
			continue;
		}
		$row                          = $items[ $it['key'] ];
		$m['items'][ $i ]['notes']    = isset( $row['notes'] ) ? $row['notes'] : '';
		$m['items'][ $i ]['decision'] = isset( $row['decision'] ) ? $row['decision'] : '';
		$m['items'][ $i ]['f']        = isset( $row['f'] ) && is_array( $row['f'] ) ? $row['f'] : array();
		foreach ( $it['subs'] as $si => $s ) {
			$m['items'][ $i ]['subs'][ $si ]['notes'] = isset( $row['subs'][ $si ] ) ? $row['subs'][ $si ] : '';
		}
		$actions = array();
		if ( isset( $row['actions'] ) && is_array( $row['actions'] ) ) {
			foreach ( $row['actions'] as $a ) {
				if ( empty( $a['text'] ) ) {
					continue;
				}
				$actions[] = array(
					'id'    => ! empty( $a['id'] ) ? sanitize_key( $a['id'] ) : strtolower( wp_generate_password( 10, false ) ),
					'text'  => $a['text'],
					'owner' => isset( $a['owner'] ) ? absint( $a['owner'] ) : 0,
					'due'   => isset( $a['due'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $a['due'] ) ? $a['due'] : '',
					'done'  => ! empty( $a['done'] ) ? 1 : 0,
				);
			}
		}
		$m['items'][ $i ]['actions'] = $actions;
		// Ticking off a carried-forward action under Matters arising closes it everywhere.
		if ( 'arising' === $it['kind'] && isset( $row['subdone'] ) && is_array( $row['subdone'] ) ) {
			foreach ( $row['subdone'] as $aid ) {
				brra_set_action_done( sanitize_key( $aid ), 1 );
			}
		}
	}
	brra_meeting_save( $m );
	if ( brra_c_in( 'ajax' ) ) {
		wp_send_json_success(
			array(
				'saved'   => current_time( 'H:i:s' ),
				'actions' => array_map(
					function ( $it ) {
						return wp_list_pluck( $it['actions'], 'id' );
					},
					array_column( $m['items'], null, 'key' )
				),
			)
		);
	}
	if ( 'close' === brra_c_in( 'op' ) ) {
		brra_c_do_meeting_close( $m );
	}
	brra_c_back( 'live', array( 'id' => $m['id'] ) );
}

/**
 * Close the meeting: record the time and, if the previous minutes were approved, mark them approved.
 *
 * @param array $m Meeting (already saved).
 */
function brra_c_do_meeting_close( $m ) {
	$m['status'] = 'closed';
	$m['closed'] = current_time( 'H:i' );
	brra_meeting_save( $m );
	$prev = brra_previous_meeting( $m );
	foreach ( $m['items'] as $it ) {
		if ( 'minutes' === $it['kind'] && $prev && isset( $it['f']['outcome'] ) && in_array( $it['f']['outcome'], array( 'yes', 'amended' ), true ) ) {
			$prev['status']   = 'approved';
			$prev['approved'] = $m['date'];
			brra_meeting_save( $prev );
		}
	}
	brra_c_back( 'minutes', array( 'id' => $m['id'] ), 'closed' );
}

/**
 * Re-open a closed meeting's minutes for corrections, or mark them approved by hand.
 */
function brra_c_do_meeting_status() {
	$m   = brra_c_meeting_from_request();
	$set = brra_c_in( 'set' );
	if ( 'reopen' === $set ) {
		$m['status'] = 'open';
		brra_meeting_save( $m );
		brra_c_back( 'live', array( 'id' => $m['id'] ), '' );
	}
	if ( 'approved' === $set ) {
		$m['status']   = 'approved';
		$m['approved'] = current_time( 'Y-m-d' );
		brra_meeting_save( $m );
	}
	brra_c_back( 'minutes', array( 'id' => $m['id'] ) );
}

/**
 * Mark an action done or not done, wherever it is.
 *
 * @param string $aid  Action ID.
 * @param int    $done 1 or 0.
 */
function brra_set_action_done( $aid, $done ) {
	foreach ( brra_meetings() as $m ) {
		foreach ( $m['items'] as $i => $it ) {
			foreach ( $it['actions'] as $k => $a ) {
				if ( $a['id'] === $aid ) {
					$m['items'][ $i ]['actions'][ $k ]['done'] = $done;
					brra_meeting_save( $m );
					return true;
				}
			}
		}
	}
	return false;
}

/**
 * Tick an action off from the Actions screen.
 */
function brra_c_do_action_done() {
	if ( ! brra_committee_can_edit() ) {
		brra_c_die();
	}
	brra_set_action_done( sanitize_key( brra_c_in( 'aid' ) ), brra_c_in( 'done' ) ? 1 : 0 );
	brra_c_back( brra_c_in( 'back', 'actions' ), array(), '' );
}

/**
 * Suggest an item for any other business at the next meeting.
 */
function brra_c_do_aob() {
	if ( ! brra_committee_can_edit() ) {
		brra_c_die();
	}
	$title = brra_c_in( 'title' );
	if ( $title ) {
		$aob = get_option( 'brra_aob', array() );
		$aob[ strtolower( wp_generate_password( 8, false ) ) ] = array(
			'title'   => $title,
			'by'      => get_current_user_id(),
			'at'      => current_time( 'mysql' ),
			'meeting' => 0,
		);
		update_option( 'brra_aob', $aob, false );
	}
	brra_c_back( 'meetings', array(), 'aob' );
}

/**
 * Save a report for a meeting.
 */
function brra_c_do_report() {
	$m     = brra_c_meeting_from_request( false );
	$key   = sanitize_key( brra_c_in( 'report' ) );
	$kinds = brra_report_kinds();
	if ( ! isset( $kinds[ $key ] ) || ! brra_can_write_report( $key, $m['id'] ) ) {
		brra_c_die();
	}
	// phpcs:disable WordPress.Security.NonceVerification -- checked in the dispatcher.
	$data = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? brra_c_clean( wp_unslash( $_POST['data'] ) ) : array();
	// phpcs:enable WordPress.Security.NonceVerification
	$m['reports'][ $key ] = array(
		'text'      => brra_c_in( 'text', '', true ),
		'data'      => $data,
		'by'        => get_current_user_id(),
		'at'        => current_time( 'mysql' ),
		'submitted' => 'submit' === brra_c_in( 'op' ) ? 1 : ( isset( $m['reports'][ $key ]['submitted'] ) ? $m['reports'][ $key ]['submitted'] : 0 ),
	);
	brra_meeting_save( $m );
	brra_c_back( 'report', array( 'id' => $m['id'], 'report' => $key ), 'submit' === brra_c_in( 'op' ) ? 'submitted' : 'saved' );
}

/**
 * Can this person write a report? Its owner, or anyone who manages minutes. The MDS programme is open to all.
 *
 * @param string $key        Report key.
 * @param int    $meeting_id Meeting ID.
 */
function brra_can_write_report( $key, $meeting_id ) {
	if ( ! brra_committee_can_edit() ) {
		return false;
	}
	$kinds = brra_report_kinds();
	$role  = isset( $kinds[ $key ] ) ? $kinds[ $key ][1] : '';
	if ( 'media' === $role && brra_has_committee_role( 'secretary' ) && ! brra_committee_holder( 'media' ) ) {
		return true;
	}
	return ! $role || brra_has_committee_role( $role ) || brra_can_manage_minutes( $meeting_id );
}

// ---------------------------------------------------------------- Finance.

/**
 * Balance entries, oldest first.
 */
function brra_finance_entries() {
	$e = get_option( 'brra_finance', array() );
	$e = is_array( $e ) ? $e : array();
	usort(
		$e,
		function ( $a, $b ) {
			return strcmp( $a['date'], $b['date'] );
		}
	);
	return $e;
}

/**
 * Latest balance on or before a date, and the one before it.
 *
 * @param string $date Date (default today).
 */
function brra_finance_at( $date = '' ) {
	$date = $date ? $date : current_time( 'Y-m-d' );
	$now  = null;
	$prev = null;
	foreach ( brra_finance_entries() as $e ) {
		if ( $e['date'] <= $date ) {
			$prev = $now;
			$now  = $e;
		}
	}
	return array( $now, $prev );
}

/**
 * Money as £1,234.56.
 *
 * @param float $n Amount.
 */
function brra_money( $n ) {
	return ( $n < 0 ? '−' : '' ) . '£' . number_format( abs( (float) $n ), 2 );
}

/**
 * Add a balance.
 */
function brra_c_do_finance() {
	if ( ! brra_can_manage_finance() && ! brra_has_committee_role( 'secretary' ) ) {
		brra_c_die();
	}
	$date = brra_c_in( 'date' );
	$bal  = (float) str_replace( array( '£', ',', ' ' ), '', brra_c_in( 'balance' ) );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && '' !== brra_c_in( 'balance' ) ) {
		$e   = get_option( 'brra_finance', array() );
		$e   = is_array( $e ) ? $e : array();
		$e[] = array(
			'id'      => strtolower( wp_generate_password( 8, false ) ),
			'date'    => $date,
			'balance' => round( $bal, 2 ),
			'notes'   => brra_c_in( 'notes', '', true ),
			'by'      => get_current_user_id(),
		);
		update_option( 'brra_finance', $e, false );
	}
	brra_c_back( 'finance' );
}

/**
 * Remove a balance entered by mistake.
 */
function brra_c_do_finance_remove() {
	if ( ! brra_can_manage_finance() && ! brra_has_committee_role( 'secretary' ) ) {
		brra_c_die();
	}
	$id = sanitize_key( brra_c_in( 'eid' ) );
	$e  = array_values(
		array_filter(
			(array) get_option( 'brra_finance', array() ),
			function ( $x ) use ( $id ) {
				return $x['id'] !== $id;
			}
		)
	);
	update_option( 'brra_finance', $e, false );
	brra_c_back( 'finance' );
}

/**
 * 100 Club draws recorded on event nights, newest first.
 *
 * @param string $from Only draws after this date.
 * @param string $to   Only draws up to this date.
 */
function brra_100club_draws( $from = '', $to = '' ) {
	$out = array();
	foreach ( brra_all_events() as $e ) {
		$d    = get_post_meta( $e->ID, '_brra_100club', true );
		$date = brra_event_get( $e->ID, 'date' );
		if ( ! is_array( $d ) || empty( $d['places'] ) || ( $from && $date <= $from ) || ( $to && $date > $to ) ) {
			continue;
		}
		$d['event'] = $e->ID;
		$d['date']  = $date;
		$d['title'] = get_the_title( $e );
		$out[]      = $d;
	}
	return $out;
}

/**
 * Mark a 100 Club prize paid (or not).
 */
function brra_c_do_prize_paid() {
	if ( ! brra_can_manage_finance() ) {
		brra_c_die();
	}
	$event = absint( brra_c_in( 'event' ) );
	$place = absint( brra_c_in( 'place' ) );
	$d     = get_post_meta( $event, '_brra_100club', true );
	if ( is_array( $d ) && isset( $d['places'][ $place ] ) ) {
		$d['places'][ $place ]['paid'] = brra_c_in( 'paid' ) ? 1 : 0;
		update_post_meta( $event, '_brra_100club', $d );
	}
	brra_c_back( 'finance', array(), '' );
}

// ---------------------------------------------------------------- Files.

/**
 * Folders in the file share, and who can open each ('' = everyone on the committee).
 */
function brra_file_folders() {
	return array(
		'meetings' => array( 'Meetings', '' ),
		'finance'  => array( 'Finance', 'finance' ),
		'events'   => array( 'Events and MDS', '' ),
		'rules'    => array( 'Rules and policies', '' ),
		'members'  => array( 'Membership', 'names' ),
		'other'    => array( 'Templates and other', '' ),
	);
}

/**
 * Can this person open a folder?
 *
 * @param string $folder Folder key.
 */
function brra_can_open_folder( $folder ) {
	$f = brra_file_folders();
	if ( ! isset( $f[ $folder ] ) ) {
		return false;
	}
	if ( 'finance' === $f[ $folder ][1] ) {
		return brra_can_manage_finance();
	}
	if ( 'names' === $f[ $folder ][1] ) {
		return brra_can_see_member_names();
	}
	return brra_is_committee();
}

/**
 * Private folder for committee files, locked against direct downloads.
 */
function brra_files_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'brra-committee';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $dir . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $dir;
}

/**
 * Upload a file to the committee file share.
 */
function brra_c_do_file_upload() {
	if ( ! brra_committee_can_edit() ) {
		brra_c_die();
	}
	$folder = sanitize_key( brra_c_in( 'folder' ) );
	if ( ! brra_can_open_folder( $folder ) || empty( $_FILES['file']['name'] ) ) {
		brra_c_back( 'files', array(), 'nofile' );
	}
	$file    = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$allowed = array(
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xls'  => 'application/vnd.ms-excel',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'csv'  => 'text/csv',
		'ppt'  => 'application/vnd.ms-powerpoint',
		'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'txt'  => 'text/plain',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
	);
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
	if ( ! $check['ext'] || UPLOAD_ERR_OK !== (int) $file['error'] ) {
		brra_c_back( 'files', array(), 'badfile' );
	}
	$stored = wp_generate_password( 20, false ) . '.' . $check['ext'];
	if ( ! move_uploaded_file( $file['tmp_name'], brra_files_dir() . '/' . $stored ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions
		brra_c_back( 'files', array(), 'badfile' );
	}
	$files   = get_option( 'brra_committee_files', array() );
	$id      = strtolower( wp_generate_password( 10, false ) );
	$name    = sanitize_file_name( wp_unslash( $file['name'] ) );
	$title   = brra_c_in( 'title' );
	$files[ $id ] = array(
		'name'   => $title ? $title : $name,
		'file'   => $stored,
		'orig'   => $name,
		'folder' => $folder,
		'by'     => get_current_user_id(),
		'at'     => current_time( 'mysql' ),
		'size'   => (int) $file['size'],
	);
	update_option( 'brra_committee_files', $files, false );
	brra_c_back( 'files', array( 'folder' => $folder ), 'uploaded' );
}

/**
 * Delete a committee file (the person who added it, or the Chairman or Secretary).
 */
function brra_c_do_file_delete() {
	$files = get_option( 'brra_committee_files', array() );
	$id    = sanitize_key( brra_c_in( 'fid' ) );
	if ( ! isset( $files[ $id ] ) || ! ( (int) $files[ $id ]['by'] === get_current_user_id() || brra_can_manage_committee() ) ) {
		brra_c_die();
	}
	$path = brra_files_dir() . '/' . basename( $files[ $id ]['file'] );
	if ( file_exists( $path ) ) {
		wp_delete_file( $path );
	}
	$folder = $files[ $id ]['folder'];
	unset( $files[ $id ] );
	update_option( 'brra_committee_files', $files, false );
	brra_c_back( 'files', array( 'folder' => $folder ), 'deleted' );
}

/**
 * Download a committee file (only for committee members allowed in that folder).
 */
function brra_c_do_file() {
	$files = get_option( 'brra_committee_files', array() );
	$id    = sanitize_key( brra_c_in( 'fid' ) );
	if ( ! isset( $files[ $id ] ) || ! brra_can_open_folder( $files[ $id ]['folder'] ) ) {
		brra_c_die();
	}
	$f    = $files[ $id ];
	$path = brra_files_dir() . '/' . basename( $f['file'] );
	if ( ! file_exists( $path ) ) {
		brra_c_die( 'That file is missing.' );
	}
	$type = wp_check_filetype( $f['orig'] );
	nocache_headers();
	header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
	header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $f['orig'] ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

// ---------------------------------------------------------------- Season calendar and guest speakers.

/**
 * Guest speakers, newest first.
 */
function brra_speakers() {
	$s = get_option( 'brra_speakers', array() );
	$s = is_array( $s ) ? $s : array();
	uasort(
		$s,
		function ( $a, $b ) {
			return strcmp( $b['date'], $a['date'] );
		}
	);
	return $s;
}

/**
 * Speaker statuses.
 */
function brra_speaker_statuses() {
	return array(
		'needed'    => array( 'Needed', 'r' ),
		'asked'     => array( 'Asked', 'a' ),
		'confirmed' => array( 'Confirmed', 'n' ),
		'done'      => array( 'Done', 'g' ),
		'cancelled' => array( 'Cancelled', '' ),
	);
}

/**
 * Add or update a speaker.
 */
function brra_c_do_speaker() {
	if ( ! brra_committee_can_edit() ) {
		brra_c_die();
	}
	$s  = get_option( 'brra_speakers', array() );
	$s  = is_array( $s ) ? $s : array();
	$id = sanitize_key( brra_c_in( 'sid' ) );
	if ( 'delete' === brra_c_in( 'op' ) ) {
		unset( $s[ $id ] );
		update_option( 'brra_speakers', $s, false );
		brra_c_back( 'speakers', array(), 'deleted' );
	}
	$id     = $id && isset( $s[ $id ] ) ? $id : strtolower( wp_generate_password( 8, false ) );
	$status = sanitize_key( brra_c_in( 'status', 'confirmed' ) );
	$date   = brra_c_in( 'date' );
	$s[ $id ] = array(
		'date'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '',
		'name'   => brra_c_in( 'name' ),
		'topic'  => brra_c_in( 'topic' ),
		'status' => array_key_exists( $status, brra_speaker_statuses() ) ? $status : 'confirmed',
		'notes'  => brra_c_in( 'notes', '', true ),
		'by'     => isset( $s[ $id ]['by'] ) ? $s[ $id ]['by'] : get_current_user_id(),
	);
	update_option( 'brra_speakers', $s, false );
	brra_c_back( 'speakers' );
}

/**
 * Add or remove one of your own calendar entries (deadlines, courses and the like).
 */
function brra_c_do_calendar() {
	if ( ! brra_committee_can_edit() ) {
		brra_c_die();
	}
	$c = get_option( 'brra_calendar_extra', array() );
	$c = is_array( $c ) ? $c : array();
	if ( 'delete' === brra_c_in( 'op' ) ) {
		unset( $c[ sanitize_key( brra_c_in( 'cid' ) ) ] );
	} else {
		$date = brra_c_in( 'date' );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && brra_c_in( 'title' ) ) {
			$c[ strtolower( wp_generate_password( 8, false ) ) ] = array(
				'date'  => $date,
				'title' => brra_c_in( 'title' ),
				'kind'  => 'deadline' === brra_c_in( 'kind' ) ? 'deadline' : 'other',
			);
		}
	}
	update_option( 'brra_calendar_extra', $c, false );
	brra_c_back( 'calendar', array( 'season' => brra_c_in( 'season' ) ) );
}

/**
 * Is this event a monthly development session?
 *
 * @param int $event_id Event ID.
 */
function brra_is_mds( $event_id ) {
	$t = get_the_title( $event_id );
	return (bool) preg_match( '/development session|\bMDS\b/i', $t );
}

// ---------------------------------------------------------------- Membership stats.

/**
 * Membership rows from Paid Memberships Pro: one per person per season they joined in.
 * Each season runs 1 June to 31 May. Works straight from the table so it needs nothing else.
 */
function brra_membership_rows() {
	global $wpdb;
	static $rows = null;
	if ( null !== $rows ) {
		return $rows;
	}
	$table = $wpdb->prefix . 'pmpro_memberships_users';
	$lt    = $wpdb->prefix . 'pmpro_membership_levels';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB
		$rows = array();
		return $rows;
	}
	$raw  = $wpdb->get_results( "SELECT m.user_id, m.membership_id, m.startdate, l.name AS level FROM $table m LEFT JOIN $lt l ON l.id = m.membership_id WHERE m.user_id > 0" ); // phpcs:ignore WordPress.DB
	$rows = array();
	foreach ( $raw as $r ) {
		$ts = strtotime( $r->startdate );
		if ( ! $ts ) {
			continue;
		}
		$season = brra_current_season( $ts )[0];
		$key    = $r->user_id . '-' . $season;
		if ( ! isset( $rows[ $key ] ) || $r->startdate < $rows[ $key ]['start'] ) {
			$rows[ $key ] = array(
				'user'   => (int) $r->user_id,
				'season' => $season,
				'start'  => $r->startdate,
				'type'   => $r->level ? $r->level : 'Member',
			);
		}
	}
	return $rows;
}

/**
 * Members in a season, with the details used for filters.
 *
 * @param int   $season  Season start year.
 * @param array $filters level, county, role, type.
 */
function brra_season_members( $season, $filters = array() ) {
	$all  = brra_membership_rows();
	$prev = array();
	foreach ( $all as $r ) {
		if ( $r['season'] === $season - 1 ) {
			$prev[ $r['user'] ] = true;
		}
	}
	$out = array();
	foreach ( $all as $r ) {
		if ( $r['season'] !== $season ) {
			continue;
		}
		$u = get_userdata( $r['user'] );
		if ( ! $u ) {
			continue;
		}
		$m = array(
			'user'    => $r['user'],
			'name'    => brra_c_name( $r['user'] ),
			'email'   => $u->user_email,
			'start'   => $r['start'],
			'type'    => $r['type'],
			'level'   => (string) get_user_meta( $r['user'], 'brra_level', true ),
			'county'  => (string) get_user_meta( $r['user'], 'brra_county', true ),
			'role'    => (string) get_user_meta( $r['user'], 'brra_role', true ),
			'renewed' => isset( $prev[ $r['user'] ] ) || ( function_exists( 'brra_is_returning_member' ) && brra_is_returning_member( $u->user_email ) ),
		);
		foreach ( array( 'level', 'county', 'role', 'type' ) as $f ) {
			if ( ! empty( $filters[ $f ] ) && ( '(not given)' === $filters[ $f ] ? '' !== $m[ $f ] : $m[ $f ] !== $filters[ $f ] ) ) {
				continue 2;
			}
		}
		$out[] = $m;
	}
	usort(
		$out,
		function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		}
	);
	return $out;
}

/**
 * Seasons the site has membership records for.
 */
function brra_membership_seasons_with_data() {
	$s = array();
	foreach ( brra_membership_rows() as $r ) {
		$s[ $r['season'] ] = true;
	}
	return array_keys( $s );
}

/**
 * Members through a season by month (June to May), cumulative. From the site's records, or from
 * figures typed in for seasons before the website.
 *
 * @param int $season Season start year.
 */
function brra_season_monthly( $season ) {
	$hist = get_option( 'brra_membership_history', array() );
	if ( ! in_array( $season, brra_membership_seasons_with_data(), true ) ) {
		return isset( $hist[ $season ]['monthly'] ) && is_array( $hist[ $season ]['monthly'] ) ? $hist[ $season ]['monthly'] : array();
	}
	$counts = array_fill( 0, 12, 0 );
	foreach ( brra_membership_rows() as $r ) {
		if ( $r['season'] !== $season ) {
			continue;
		}
		$month = (int) gmdate( 'n', strtotime( $r['start'] ) );
		$idx   = ( $month + 6 ) % 12; // June = 0.
		for ( $i = $idx; $i < 12; $i++ ) {
			++$counts[ $i ];
		}
	}
	// Don't draw months that haven't happened yet.
	$cur = brra_current_season()[0];
	if ( $season === $cur ) {
		$now    = ( (int) current_time( 'n' ) + 6 ) % 12;
		$counts = array_slice( $counts, 0, $now + 1 );
	}
	return $counts;
}

/**
 * One row of the season-by-season table.
 *
 * @param int $season Season start year.
 */
function brra_season_summary( $season ) {
	global $wpdb;
	$hist = get_option( 'brra_membership_history', array() );
	$row  = isset( $hist[ $season ] ) ? $hist[ $season ] : array();
	if ( in_array( $season, brra_membership_seasons_with_data(), true ) ) {
		$members = brra_season_members( $season );
		$renewed = count( array_filter( wp_list_pluck( $members, 'renewed' ) ) );
		$row     = array_merge(
			$row,
			array(
				'members' => count( $members ),
				'new'     => count( $members ) - $renewed,
				'renewed' => $renewed,
				'live'    => true,
			)
		);
		if ( in_array( $season - 1, brra_membership_seasons_with_data(), true ) ) {
			$now          = wp_list_pluck( $members, 'user' );
			$then         = wp_list_pluck( brra_season_members( $season - 1 ), 'user' );
			$row['lapsed'] = count( array_diff( $then, $now ) );
		}
	}
	// Average turnout at that season's events, from check-ins.
	$ids = array();
	foreach ( brra_all_events() as $e ) {
		$d = brra_event_get( $e->ID, 'date' );
		if ( $d >= $season . '-06-01' && $d < ( $season + 1 ) . '-06-01' && brra_is_mds( $e->ID ) ) {
			$ids[] = (int) $e->ID;
		}
	}
	if ( $ids && function_exists( 'brra_attendance_table' ) ) {
		$t   = brra_attendance_table();
		$avg = $wpdb->get_var( "SELECT AVG(n) FROM (SELECT COUNT(*) AS n FROM $t WHERE event_id IN (" . implode( ',', $ids ) . ') GROUP BY event_id) x' ); // phpcs:ignore WordPress.DB
		if ( $avg ) {
			$row['avg'] = round( (float) $avg );
		}
	}
	return $row;
}

/**
 * Save figures for seasons before the website (typed in by the Membership Secretary).
 */
function brra_c_do_history() {
	if ( ! brra_can_see_member_names() ) {
		brra_c_die();
	}
	// phpcs:disable WordPress.Security.NonceVerification -- checked in the dispatcher.
	$in = isset( $_POST['hist'] ) && is_array( $_POST['hist'] ) ? brra_c_clean( wp_unslash( $_POST['hist'] ) ) : array();
	// phpcs:enable WordPress.Security.NonceVerification
	$hist = array();
	foreach ( $in as $season => $r ) {
		$season = absint( $season );
		if ( ! $season && ! empty( $r['season'] ) ) {
			$season = absint( $r['season'] );
		}
		if ( $season < 1990 || $season > 2100 || ! empty( $r['remove'] ) ) {
			continue;
		}
		$monthly = array();
		foreach ( preg_split( '/[\s,]+/', isset( $r['monthly'] ) ? $r['monthly'] : '', -1, PREG_SPLIT_NO_EMPTY ) as $n ) {
			$monthly[] = (int) $n;
		}
		$row = array( 'monthly' => array_slice( $monthly, 0, 12 ) );
		foreach ( array( 'members', 'new', 'renewed', 'lapsed', 'avg' ) as $k ) {
			if ( isset( $r[ $k ] ) && '' !== $r[ $k ] ) {
				$row[ $k ] = (int) $r[ $k ];
			}
		}
		if ( ! empty( $r['note'] ) ) {
			$row['note'] = $r['note'];
		}
		$hist[ $season ] = $row;
	}
	krsort( $hist );
	update_option( 'brra_membership_history', $hist, false );
	brra_c_back( 'stats' );
}

/**
 * Members' emails as a spreadsheet, using the filters on the stats screen.
 */
function brra_c_do_emails_csv() {
	if ( ! brra_can_see_member_names() ) {
		brra_c_die();
	}
	$season  = absint( brra_c_in( 'season', (string) brra_current_season()[0] ) );
	$members = brra_season_members( $season, brra_stats_filters() );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="members-' . $season . '-' . ( $season + 1 ) . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fputcsv( $out, array( 'Email', 'First name', 'Last name', 'Membership', 'Level', 'Role', 'County FA', 'Joined', 'New or renewed' ) );
	foreach ( $members as $m ) {
		fputcsv( $out, array( $m['email'], get_user_meta( $m['user'], 'first_name', true ), get_user_meta( $m['user'], 'last_name', true ), $m['type'], $m['level'], $m['role'], $m['county'], substr( $m['start'], 0, 10 ), $m['renewed'] ? 'Renewed' : 'New' ) );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/**
 * Filters chosen on the stats screen.
 */
function brra_stats_filters() {
	$f = array();
	foreach ( array( 'level', 'county', 'role', 'type' ) as $k ) {
		$v = brra_c_in( $k );
		if ( '' !== $v ) {
			$f[ $k ] = $v;
		}
	}
	return $f;
}

// ---------------------------------------------------------------- Minutes as a Word file.

/**
 * Download the minutes (or agenda) as a Word file.
 */
function brra_c_do_word() {
	$m    = brra_c_meeting_from_request( false );
	$what = 'agenda' === brra_c_in( 'what' ) ? 'agenda' : 'minutes';
	$html = 'agenda' === $what ? brra_render_agenda_doc( $m ) : brra_render_minutes_doc( $m );
	nocache_headers();
	header( 'Content-Type: application/msword; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="BRRA Committee ' . ( 'agenda' === $what ? 'Agenda' : 'Minutes' ) . ' ' . $m['date'] . '.doc"' );
	echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word"><head><meta charset="utf-8"><style>' . brra_doc_css() . '</style></head><body>' . $html . '</body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}
