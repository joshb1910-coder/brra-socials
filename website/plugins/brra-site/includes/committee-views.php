<?php
/**
 * Committee area screens: [brra_committee_area] on the /committee/ page.
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		add_shortcode( 'brra_committee_area', 'brra_shortcode_committee_area' );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_page( 'committee' ) ) {
			wp_enqueue_style( 'brra-committee', BRRA_SITE_URL . 'assets/committee.css', array(), BRRA_SITE_VERSION );
			wp_enqueue_script( 'brra-committee', BRRA_SITE_URL . 'assets/committee.js', array(), BRRA_SITE_VERSION, true );
		}
	}
);

/**
 * Escape and print.
 *
 * @param string $s Text.
 */
function brra_e( $s ) {
	return esc_html( (string) $s );
}

/**
 * A small coloured label.
 *
 * @param string $text Text.
 * @param string $tone '', g, r, a or n.
 */
function brra_pill( $text, $tone = '' ) {
	return '<span class="bc-pill' . ( $tone ? ' bc-' . $tone : '' ) . '">' . brra_e( $text ) . '</span>';
}

/**
 * Change as a coloured label, e.g. "▲ £152.40".
 *
 * @param float  $diff   Change.
 * @param string $format money, number or percent.
 */
function brra_change_pill( $diff, $format = 'number' ) {
	if ( 0.0 === (float) $diff ) {
		return brra_pill( 'No change' );
	}
	$abs = abs( $diff );
	$txt = 'money' === $format ? '£' . number_format( $abs, 2 ) : ( 'percent' === $format ? round( $abs ) . '%' : number_format( $abs ) );
	return brra_pill( ( $diff > 0 ? '▲ ' : '▼ ' ) . $txt, $diff > 0 ? 'g' : 'r' );
}

/**
 * Select list of committee members.
 *
 * @param string $name     Field name.
 * @param int    $selected Selected user.
 * @param string $blank    Label for "nobody".
 */
function brra_people_select( $name, $selected = 0, $blank = 'Nobody in particular' ) {
	$out = '<select name="' . esc_attr( $name ) . '"><option value="0">' . brra_e( $blank ) . '</option>';
	foreach ( brra_committee_people( false ) as $u ) {
		$out .= '<option value="' . (int) $u->ID . '"' . selected( (int) $selected, (int) $u->ID, false ) . '>' . brra_e( brra_c_name( $u->ID ) ) . '</option>';
	}
	return $out . '</select>';
}

/**
 * The committee area.
 */
function brra_shortcode_committee_area() {
	if ( ! is_user_logged_in() ) {
		return '<div class="bc-gate"><h2>Committee area</h2><p>This part of the site is for the committee. Please log in to carry on.</p><p><a class="brra-btn" href="' . esc_url( wp_login_url( brra_c_url() ) ) . '">Log in</a></p></div>';
	}
	if ( ! brra_is_committee() ) {
		return '<div class="bc-gate"><h2>Committee area</h2><p>Sorry, this part of the site is only for committee members. If you\'ve just joined the committee, ask the Secretary to add you.</p></div>';
	}
	$view  = sanitize_key( brra_c_in( 'view', 'home' ) );
	$views = array( 'home', 'meetings', 'agenda', 'live', 'view', 'minutes', 'report', 'actions', 'files', 'finance', 'calendar', 'speakers', 'stats', 'people' );
	$view  = in_array( $view, $views, true ) ? $view : 'home';
	if ( 'minutes' === $view && brra_c_in( 'print' ) ) {
		$m = brra_meeting( absint( brra_c_in( 'id' ) ) );
		return '<div class="bc-print"><p class="bc-noprint"><button type="button" class="bc-btn" onclick="window.print()">Print or save as PDF</button> <a href="' . esc_url( brra_c_url( 'minutes', array( 'id' => $m['id'] ) ) ) . '">Back</a></p>' . brra_render_minutes_doc( $m ) . '</div>';
	}
	ob_start();
	echo '<div class="bc">';
	brra_c_sidebar( $view );
	echo '<div class="bc-main">';
	if ( defined( 'BRRA_DEMO' ) && BRRA_DEMO || get_option( 'brra_demo_committee' ) ) {
		echo '<p class="bc-demo">Test site: the meetings, figures and names here are made-up examples so you can try things out.</p>';
	}
	brra_c_notice();
	call_user_func( 'brra_c_view_' . $view );
	echo '</div></div>';
	return preg_replace( '/>\s+</', '><', ob_get_clean() );
}

/**
 * Left-hand menu.
 *
 * @param string $view Current screen.
 */
function brra_c_sidebar( $view ) {
	$uid   = get_current_user_id();
	$items = array(
		array( 'home', 'Committee home' ),
		array( 'meetings', 'Meetings' ),
		array( 'actions', 'Actions' ),
		array( 'files', 'Files' ),
		array( '-', 'Season' ),
		array( 'calendar', 'Season calendar' ),
		array( 'speakers', 'Guest speakers' ),
		array( 'stats', 'Membership stats' ),
		array( 'finance', 'Finance' ),
		array( 'event-night', 'Event night (check-in, 100 Club)' ),
	);
	if ( brra_can_manage_committee() ) {
		$items[] = array( '-', 'Admin' );
		$items[] = array( 'people', 'Committee and delegates' );
	}
	$map = array( 'agenda' => 'meetings', 'live' => 'meetings', 'view' => 'meetings', 'minutes' => 'meetings', 'report' => 'meetings' );
	$cur = isset( $map[ $view ] ) ? $map[ $view ] : $view;
	echo '<nav class="bc-side" aria-label="Committee area"><div class="bc-who">' . brra_e( brra_c_name( $uid ) ) . '</div><div class="bc-role">' . brra_e( brra_committee_title( $uid ) ) . '</div>';
	foreach ( $items as $it ) {
		if ( '-' === $it[0] ) {
			echo '<div class="bc-grp">' . brra_e( $it[1] ) . '</div>';
			continue;
		}
		$url = 'event-night' === $it[0] ? brra_attendance_admin_url() : brra_c_url( 'home' === $it[0] ? '' : $it[0] );
		echo '<a href="' . esc_url( $url ) . '"' . ( $cur === $it[0] ? ' class="is-on" aria-current="page"' : '' ) . '>' . brra_e( $it[1] ) . '</a>';
	}
	echo '</nav>';
}

/**
 * Success messages after a form.
 */
function brra_c_notice() {
	$done = brra_c_in( 'done' );
	$msgs = array(
		'saved'     => 'Saved.',
		'sent'      => 'Agenda sent to the committee.',
		'template'  => 'Saved. New agendas will start with these items.',
		'closed'    => 'Meeting closed. The minutes are below as a draft until they\'re approved at the next meeting.',
		'submitted' => 'Report submitted. It now shows in the agenda.',
		'aob'       => 'Thanks. It\'s been added to the list for any other business.',
		'uploaded'  => 'File added.',
		'deleted'   => 'Deleted.',
		'badfile'   => 'That file couldn\'t be added. Use PDF, Word, Excel, PowerPoint, CSV, text or an image.',
		'nofile'    => 'Choose a file and a folder first.',
		'baddate'   => 'Please pick a date.',
	);
	if ( isset( $msgs[ $done ] ) ) {
		$bad = in_array( $done, array( 'badfile', 'nofile', 'baddate' ), true );
		echo '<p class="bc-notice' . ( $bad ? ' is-bad' : '' ) . '" role="status">' . brra_e( $msgs[ $done ] ) . '</p>';
	}
}

/**
 * Page heading.
 *
 * @param string $eyebrow Small line above.
 * @param string $title   Title.
 * @param string $right   Buttons on the right.
 * @param string $sub     Line underneath.
 */
function brra_c_head( $eyebrow, $title, $right = '', $sub = '' ) {
	echo '<div class="bc-head"><div><div class="bc-eyebrow">' . brra_e( $eyebrow ) . '</div><h1>' . brra_e( $title ) . '</h1></div>' . ( $right ? '<div class="bc-row">' . $right . '</div>' : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $sub ) {
		echo '<p class="bc-sub">' . brra_e( $sub ) . '</p>';
	}
}

// ---------------------------------------------------------------- Home.

/**
 * Committee home.
 */
function brra_c_view_home() {
	$uid   = get_current_user_id();
	$first = get_user_meta( $uid, 'first_name', true );
	$hour  = (int) current_time( 'G' );
	brra_c_head( 'Committee area', ( $hour < 12 ? 'Morning' : ( $hour < 18 ? 'Afternoon' : 'Evening' ) ) . ( $first ? ', ' . $first : '' ), '', 'Only signed-in committee members can see this area.' );

	$next = brra_next_meeting();
	list( $bal, $prevbal ) = brra_finance_at();
	$season = brra_current_season()[0];
	$now    = brra_season_summary( $season );
	$last   = brra_season_summary( $season - 1 );
	echo '<div class="bc-grid bc-g3 bc-top">';
	echo '<div class="bc-card is-navy"><div class="bc-eyebrow">Next meeting</div>';
	if ( $next ) {
		echo '<h2>' . brra_e( brra_c_date( $next['date'], 'l j F' ) ) . ', ' . brra_e( brra_c_time( $next['time'] ) ) . '</h2><p class="bc-dim">' . brra_e( strtok( $next['venue'], ',' ) ) . ' · ' . brra_e( brra_meeting_status_label( $next['status'] )[0] ) . ', ' . count( $next['items'] ) . ' items</p><div class="bc-row">';
		if ( brra_can_manage_minutes( $next['id'] ) ) {
			echo 'open' === $next['status'] ? '<a class="bc-btn is-gold" href="' . esc_url( brra_c_url( 'live', array( 'id' => $next['id'] ) ) ) . '">Back to the meeting</a>' : '<a class="bc-btn is-gold" href="' . esc_url( brra_c_url( 'agenda', array( 'id' => $next['id'] ) ) ) . '">Edit agenda</a>';
		}
		echo '<a class="bc-btn is-line" href="' . esc_url( brra_c_url( 'view', array( 'id' => $next['id'] ) ) ) . '">View agenda</a></div>';
	} else {
		echo '<h2>None planned yet</h2>';
		if ( brra_can_manage_minutes() ) {
			echo '<a class="bc-btn is-gold" href="' . esc_url( brra_c_url( 'meetings' ) ) . '">Add a meeting</a>';
		}
	}
	echo '</div>';
	echo '<div class="bc-card"><h3>Bank balance</h3>';
	if ( $bal ) {
		echo '<div class="bc-big">' . brra_e( '£' . number_format( $bal['balance'] ) ) . '</div><div class="bc-muted">' . ( $prevbal ? brra_change_pill( $bal['balance'] - $prevbal['balance'], 'money' ) . ' since ' . brra_e( brra_c_date( $prevbal['date'], 'j F' ) ) : 'As at ' . brra_e( brra_c_date( $bal['date'], 'j F' ) ) ) . '</div>';
	} else {
		echo '<p class="bc-muted">No balance entered yet.</p>';
	}
	echo '</div><div class="bc-card"><h3>Members this season</h3>';
	if ( isset( $now['members'] ) ) {
		$cmp = brra_compare_to_same_point( $season );
		echo '<div class="bc-big">' . (int) $now['members'] . '</div><div class="bc-muted">' . ( null !== $cmp ? brra_change_pill( $now['members'] - $cmp ) . ' on this time last season' : (int) $now['new'] . ' new this season' ) . '</div>';
	} else {
		echo '<p class="bc-muted">No members recorded yet.</p>';
	}
	echo '</div></div>';

	// Actions and reports.
	$mine = array_filter(
		brra_all_actions( true ),
		function ( $a ) use ( $uid ) {
			return (int) $a['owner'] === $uid;
		}
	);
	echo '<div class="bc-grid bc-g2">';
	echo '<div class="bc-card"><div class="bc-row bc-sp"><h3>Your actions</h3><span class="bc-muted">' . count( $mine ) . ' open</span></div>';
	echo $mine ? '<table class="bc-table">' . brra_c_action_rows( $mine, false, 'home' ) . '</table>' : '<p class="bc-muted">Nothing waiting on you.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
	echo '<div class="bc-card">';
	if ( $next ) {
		$kinds = brra_report_kinds();
		$in    = 0;
		$rows  = '';
		foreach ( $next['items'] as $it ) {
			if ( 'report' !== $it['kind'] || ! isset( $kinds[ $it['report'] ] ) ) {
				continue;
			}
			$r     = isset( $next['reports'][ $it['report'] ] ) ? $next['reports'][ $it['report'] ] : null;
			$done  = $r && ! empty( $r['submitted'] );
			$in   += $done ? 1 : 0;
			$can   = brra_can_write_report( $it['report'], $next['id'] );
			$rows .= '<tr><td>' . brra_e( $kinds[ $it['report'] ][0] ) . '</td><td>' . brra_e( $it['lead'] ? strtok( brra_c_name( $it['lead'] ), ' ' ) : 'Anyone' ) . '</td><td>';
			if ( $done ) {
				$rows .= '<a href="' . esc_url( brra_c_url( $can ? 'report' : 'view', array( 'id' => $next['id'], 'report' => $it['report'] ) ) ) . '">' . brra_pill( 'Submitted', 'g' ) . '</a>';
			} elseif ( $can ) {
				$rows .= '<a class="bc-btn is-sm" href="' . esc_url( brra_c_url( 'report', array( 'id' => $next['id'], 'report' => $it['report'] ) ) ) . '">' . ( $r ? 'Carry on' : 'Write it' ) . '</a>';
			} else {
				$rows .= brra_pill( 'Not yet' );
			}
			$rows .= '</td></tr>';
		}
		$total = substr_count( $rows, '<tr>' );
		echo '<div class="bc-row bc-sp"><h3>Reports for ' . brra_e( brra_c_date( $next['date'], 'j F' ) ) . '</h3><span class="bc-muted">' . (int) $in . ' of ' . (int) $total . ' in</span></div><table class="bc-table">' . $rows . '</table><p class="bc-note">Submitted reports show inside the agenda, so everyone can read them on their phone during the meeting.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		echo '<h3>Reports</h3><p class="bc-muted">Reports open once the next meeting is added.</p>';
	}
	echo '</div></div>';

	// Coming up and latest minutes.
	echo '<div class="bc-grid bc-g2">';
	echo '<div class="bc-card"><div class="bc-row bc-sp"><h3>Coming up</h3><a class="bc-btn is-sm is-ghost" href="' . esc_url( brra_c_url( 'calendar' ) ) . '">Season calendar</a></div><table class="bc-table">';
	$shown = 0;
	foreach ( brra_calendar_entries( $season ) as $c ) {
		if ( $c['date'] < current_time( 'Y-m-d' ) || $shown >= 4 ) {
			continue;
		}
		++$shown;
		echo '<tr><td><strong>' . brra_e( brra_c_date( $c['date'], 'j M' ) ) . '</strong></td><td>' . brra_e( $c['title'] ) . '</td><td>' . brra_pill( $c['label'], $c['tone'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo $shown ? '' : '<tr><td class="bc-muted">Nothing else this season.</td></tr>';
	echo '</table></div>';
	echo '<div class="bc-card"><div class="bc-row bc-sp"><h3>Latest minutes</h3><a class="bc-btn is-sm is-ghost" href="' . esc_url( brra_c_url( 'meetings' ) ) . '">All minutes and agendas</a></div><table class="bc-table">';
	$shown = 0;
	foreach ( brra_meetings() as $m ) {
		if ( ! in_array( $m['status'], array( 'closed', 'approved' ), true ) || $shown >= 3 ) {
			continue;
		}
		++$shown;
		$st = brra_meeting_status_label( $m['status'] );
		echo '<tr><td>' . brra_e( brra_c_date( $m['date'], 'j F Y' ) ) . '</td><td>' . brra_pill( $st[0], $st[1] ) . '</td><td><a href="' . esc_url( brra_c_url( 'minutes', array( 'id' => $m['id'] ) ) ) . '">Read</a></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo $shown ? '' : '<tr><td class="bc-muted">No minutes yet.</td></tr>';
	echo '</table></div></div>';
}

/**
 * Members at this point last season, for "on this time last season".
 *
 * @param int $season Season start year.
 */
function brra_compare_to_same_point( $season ) {
	$prev = brra_season_monthly( $season - 1 );
	if ( ! $prev ) {
		return null;
	}
	$idx = ( (int) current_time( 'n' ) + 6 ) % 12;
	return isset( $prev[ $idx ] ) ? (int) $prev[ $idx ] : null;
}

/**
 * Table rows for actions.
 *
 * @param array  $actions    Actions.
 * @param bool   $show_owner Show the owner column.
 * @param string $back       Screen to return to.
 */
function brra_c_action_rows( $actions, $show_owner, $back ) {
	$out   = '';
	$today = current_time( 'Y-m-d' );
	foreach ( $actions as $a ) {
		$done = ! empty( $a['done'] );
		if ( $done ) {
			$due = brra_pill( 'Done', 'g' );
		} elseif ( empty( $a['due'] ) ) {
			$due = brra_pill( 'No date' );
		} elseif ( $a['due'] < $today ) {
			$due = brra_pill( 'Overdue', 'r' );
		} else {
			$due = brra_pill( 'Due ' . brra_c_date( $a['due'], 'j M' ), $a['due'] <= gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) ) ? 'a' : '' );
		}
		$out .= '<tr><td>' . brra_e( $a['text'] ) . '<div class="bc-muted">' . brra_e( brra_c_date( $a['date'], 'j M' ) . ' · ' . $a['item'] ) . '</div></td>';
		if ( $show_owner ) {
			$out .= '<td>' . brra_e( $a['owner'] ? brra_c_name( $a['owner'] ) : 'Not set' ) . '</td>';
		}
		$out .= '<td>' . $due . '</td>';
		if ( brra_committee_can_edit() ) {
			$out .= '<td><a class="bc-btn is-sm is-ghost" href="' . esc_url( brra_c_action_url( 'action_done', array( 'aid' => $a['id'], 'done' => $done ? 0 : 1, 'back' => 'home' === $back ? '' : $back ) ) ) . '">' . ( $done ? 'Not done' : 'Done' ) . '</a></td>';
		}
		$out .= '</tr>';
	}
	return $out;
}

// ---------------------------------------------------------------- Meetings list.

/**
 * Meetings: minutes and agendas, add a meeting, suggest AOB.
 */
function brra_c_view_meetings() {
	brra_c_head( 'Meetings', 'Minutes and agendas', '', 'Every committee meeting, newest first. Open one to read the agenda or the minutes.' );
	echo '<div class="bc-grid bc-g2w"><div class="bc-card"><table class="bc-table"><thead><tr><th>Meeting</th><th>Status</th><th></th></tr></thead><tbody>';
	$all = brra_meetings();
	foreach ( $all as $m ) {
		$st    = brra_meeting_status_label( $m['status'] );
		$links = array( '<a href="' . esc_url( brra_c_url( 'view', array( 'id' => $m['id'] ) ) ) . '">Agenda</a>' );
		if ( in_array( $m['status'], array( 'closed', 'approved' ), true ) ) {
			$links[] = '<a href="' . esc_url( brra_c_url( 'minutes', array( 'id' => $m['id'] ) ) ) . '">Minutes</a>';
		}
		if ( brra_can_manage_minutes( $m['id'] ) ) {
			if ( 'open' === $m['status'] ) {
				$links[] = '<a href="' . esc_url( brra_c_url( 'live', array( 'id' => $m['id'] ) ) ) . '"><strong>Take minutes</strong></a>';
			} elseif ( in_array( $m['status'], array( 'draft', 'sent' ), true ) ) {
				$links[] = '<a href="' . esc_url( brra_c_url( 'agenda', array( 'id' => $m['id'] ) ) ) . '">Edit agenda</a>';
			}
		}
		echo '<tr><td><strong>' . brra_e( brra_c_date( $m['date'] ) ) . '</strong><div class="bc-muted">' . brra_e( brra_c_time( $m['time'] ) . ', ' . strtok( $m['venue'], ',' ) ) . '</div></td><td>' . brra_pill( $st[0], $st[1] ) . '</td><td class="bc-links">' . implode( ' · ', $links ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo $all ? '' : '<tr><td colspan="3" class="bc-muted">No meetings yet.</td></tr>';
	echo '</tbody></table></div><div>';
	if ( brra_can_manage_minutes() ) {
		$last = $all ? $all[0]['date'] : current_time( 'Y-m-d' );
		$sugg = gmdate( 'Y-m-d', strtotime( $last . ' +4 weeks' ) );
		echo '<div class="bc-card"><h3>Add a meeting</h3><p class="bc-muted">The agenda starts with the standing items, open actions carried forward and anything raised for AOB.</p>' . brra_c_form( 'meeting_new', array(), 'class="bc-form"' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label>Date<input type="date" name="date" value="' . esc_attr( $sugg ) . '" required></label><label>Time<input type="time" name="time" value="20:00"></label><label>Venue<input type="text" name="venue" value="' . esc_attr( "St Mary's Social Club, Pine Street, Radcliffe, M26 2WQ" ) . '"></label><button class="bc-btn">Add meeting</button></form></div>';
	}
	if ( brra_committee_can_edit() ) {
		echo '<div class="bc-card"><h3>Raise something for AOB</h3><p class="bc-muted">It goes on the list for any other business at the next meeting.</p>' . brra_c_form( 'aob', array(), 'class="bc-form"' ) . '<input type="text" name="title" placeholder="What would you like to raise?" required><button class="bc-btn is-ghost">Send to the Secretary</button></form></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div></div>';
}

// ---------------------------------------------------------------- Agenda builder.

/**
 * Build the agenda.
 */
function brra_c_view_agenda() {
	$m = brra_meeting( absint( brra_c_in( 'id' ) ) );
	if ( ! $m['date'] ) {
		echo '<p>Meeting not found.</p>';
		return;
	}
	if ( ! brra_can_manage_minutes( $m['id'] ) ) {
		brra_c_view_view();
		return;
	}
	$right  = '<a class="bc-btn is-ghost" href="' . esc_url( brra_c_url( 'view', array( 'id' => $m['id'] ) ) ) . '">Preview agenda</a>';
	$right .= in_array( $m['status'], array( 'draft', 'sent' ), true ) ? brra_c_form( 'meeting_open', array( 'id' => $m['id'] ) ) . '<button class="bc-btn is-gold">Open meeting</button></form>' : '<a class="bc-btn is-gold" href="' . esc_url( brra_c_url( 'live', array( 'id' => $m['id'] ) ) ) . '">Go to the meeting</a>';
	brra_c_head( 'Meetings · Agenda' . ( $m['sent'] ? ' · sent ' . mysql2date( 'j M', $m['sent'] ) : '' ), brra_c_date( $m['date'] ), $right, 'Standing items are added for you, with open actions carried forward under Matters arising. Untick anything you don\'t need, move items or add new ones. Press Open meeting when the Chairman opens it.' );
	$aob = array_filter(
		get_option( 'brra_aob', array() ),
		function ( $s ) {
			return empty( $s['meeting'] );
		}
	);
	echo brra_c_form( 'agenda', array( 'id' => $m['id'] ), 'id="bc-agenda"' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="bc-grid bc-g2w"><div>';
	echo '<div class="bc-card bc-meta"><label>Date<input type="date" name="date" value="' . esc_attr( $m['date'] ) . '"></label><label>Time<input type="time" name="time" value="' . esc_attr( $m['time'] ) . '"></label><label class="bc-wide">Venue<input type="text" name="venue" value="' . esc_attr( $m['venue'] ) . '"></label></div>';
	$n     = 0;
	$kinds = brra_report_kinds();
	$total = count( $m['items'] );
	foreach ( $m['items'] as $i => $it ) {
		++$n;
		$k = esc_attr( $it['key'] );
		echo '<div class="bc-item"><div class="bc-item-hd"><span class="bc-num">' . (int) $n . '</span><input class="bc-title-in" type="text" name="items[' . $k . '][title]" value="' . esc_attr( $it['title'] ) . '" aria-label="Item title">';
		echo '<span class="bc-item-tools">' . ( 'custom' !== $it['kind'] ? brra_pill( 'report' === $it['kind'] ? 'Report' : 'Standing' ) : '' );
		echo '<button name="op" value="up|' . $k . '" class="bc-icon" title="Move up"' . disabled( 0 === $i, true, false ) . '>↑</button><button name="op" value="down|' . $k . '" class="bc-icon" title="Move down"' . disabled( $total - 1 === $i, true, false ) . '>↓</button><button name="op" value="remove|' . $k . '" class="bc-icon" title="Remove item" onclick="return confirm(\'Remove this item?\')">✕</button></span></div>';
		echo '<div class="bc-item-bd"><label class="bc-inline">Led by ' . brra_people_select( 'items[' . $it['key'] . '][lead]', $it['lead'] ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( 'report' === $it['kind'] && isset( $kinds[ $it['report'] ] ) ) {
			$r = isset( $m['reports'][ $it['report'] ] ) ? $m['reports'][ $it['report'] ] : null;
			echo ' <span class="bc-muted">' . ( $r && ! empty( $r['submitted'] ) ? 'Report submitted ✓' : 'Report not in yet' ) . '</span>';
		}
		if ( $it['subs'] ) {
			echo '<div class="bc-subs">';
			if ( 'arising' === $it['kind'] ) {
				echo '<div class="bc-muted">Carried forward (untick to leave off)</div>';
			}
			foreach ( $it['subs'] as $si => $s ) {
				echo '<label class="bc-check"><input type="checkbox" name="items[' . $k . '][subs][]" value="' . (int) $si . '" checked> ' . brra_e( $s['title'] ) . '</label>';
			}
			echo '</div>';
		}
		echo '</div></div>';
	}
	echo '<div class="bc-row"><button class="bc-btn" name="op" value="save">Save agenda</button><button class="bc-btn is-ghost" name="op" value="send" onclick="return confirm(\'Email the agenda link to the whole committee?\')">Save and send to committee</button><button class="bc-btn is-ghost" name="op" value="template" title="New agendas will start with these items">Use as standing items</button></div>';
	echo '</div><div>';
	echo '<div class="bc-card"><h3>Add an item</h3><div class="bc-form"><input type="text" name="new_title" placeholder="Item title"><label>Led by ' . brra_people_select( 'new_lead', 0 ) . '</label><label>Where<select name="new_where"><option value="main">Main agenda</option><option value="aob">Under any other business</option></select></label><button class="bc-btn is-sm" name="op" value="add">Add item</button></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="bc-card"><h3>Raised for AOB</h3>';
	if ( $aob ) {
		echo '<table class="bc-table">';
		foreach ( $aob as $key => $s ) {
			echo '<tr><td>' . brra_e( $s['title'] ) . '<div class="bc-muted">from ' . brra_e( brra_c_name( $s['by'] ) . ' · ' . mysql2date( 'j M', $s['at'] ) ) . '</div></td><td><button class="bc-btn is-sm is-ghost" name="op" value="aobadd|' . esc_attr( $key ) . '">Add</button></td></tr>';
		}
		echo '</table>';
	} else {
		echo '<p class="bc-muted">Nothing new. Committee members can raise items from the Meetings screen.</p>';
	}
	echo '</div>';
	if ( in_array( $m['status'], array( 'draft', 'sent' ), true ) ) {
		echo '<p><button class="bc-link-danger" name="op" value="delete" onclick="return confirm(\'Delete this meeting and its agenda?\')">Delete this meeting</button></p>';
	}
	echo '</div></div></form>';
}

// ---------------------------------------------------------------- Agenda (read) and reports inline.

/**
 * Read the agenda, with reports shown inside their items.
 */
function brra_c_view_view() {
	$m = brra_meeting( absint( brra_c_in( 'id' ) ) );
	if ( ! $m['date'] ) {
		echo '<p>Meeting not found.</p>';
		return;
	}
	$right = '<a class="bc-btn is-ghost" href="' . esc_url( brra_c_action_url( 'word', array( 'id' => $m['id'], 'what' => 'agenda' ) ) ) . '">Download for Word</a>';
	if ( brra_can_manage_minutes( $m['id'] ) && in_array( $m['status'], array( 'draft', 'sent' ), true ) ) {
		$right .= '<a class="bc-btn" href="' . esc_url( brra_c_url( 'agenda', array( 'id' => $m['id'] ) ) ) . '">Edit agenda</a>';
	}
	if ( in_array( $m['status'], array( 'closed', 'approved' ), true ) ) {
		$right .= '<a class="bc-btn" href="' . esc_url( brra_c_url( 'minutes', array( 'id' => $m['id'] ) ) ) . '">Read the minutes</a>';
	}
	brra_c_head( 'Agenda · ' . brra_c_time( $m['time'] ) . ', ' . strtok( $m['venue'], ',' ), brra_c_date( $m['date'] ), $right );
	if ( 'open' === $m['status'] ) {
		echo '<div class="bc-live"><span class="bc-dot"></span><strong>Meeting open</strong><span class="bc-muted">Opened at ' . brra_e( brra_c_time( $m['opened'] ) ) . '. Reports are below each item.</span>' . ( brra_can_manage_minutes( $m['id'] ) ? '<a class="bc-btn is-sm" href="' . esc_url( brra_c_url( 'live', array( 'id' => $m['id'] ) ) ) . '">Take minutes</a>' : '' ) . '</div>';
	}
	$n = 0;
	foreach ( $m['items'] as $it ) {
		++$n;
		echo '<div class="bc-item"><div class="bc-item-hd"><span class="bc-num">' . (int) $n . '</span><strong>' . brra_e( $it['title'] ) . '</strong>' . ( $it['lead'] ? '<span class="bc-muted bc-right">' . brra_e( brra_c_name( $it['lead'] ) ) . '</span>' : '' ) . '</div>';
		$body = '';
		if ( $it['subs'] ) {
			$body .= '<ol class="bc-sublist" type="a">';
			foreach ( $it['subs'] as $s ) {
				$body .= '<li>' . brra_e( $s['title'] ) . '</li>';
			}
			$body .= '</ol>';
		}
		$body .= brra_item_extra( $m, $it, true );
		if ( $body ) {
			echo '<div class="bc-item-bd">' . $body . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}
}

/**
 * Extra content for an item: a report, the finance figures, the 100 Club results, open actions.
 *
 * @param array $m    Meeting.
 * @param array $it   Item.
 * @param bool  $html Web view (true) or document (false).
 */
function brra_item_extra( $m, $it, $html = true ) {
	$out = '';
	if ( 'report' === $it['kind'] ) {
		$r = isset( $m['reports'][ $it['report'] ] ) ? $m['reports'][ $it['report'] ] : null;
		if ( 'treasurer' === $it['report'] ) {
			$out .= brra_treasurer_figures( $m );
		}
		if ( 'membership' === $it['report'] ) {
			$out .= brra_membership_figures( $m );
		}
		if ( $r && ! empty( $r['submitted'] ) ) {
			if ( 'media' === $it['report'] ) {
				$out .= brra_media_cards( $m, $r );
			}
			$out .= $r['text'] ? '<div class="bc-report">' . wpautop( brra_e( $r['text'] ) ) . '</div>' : '';
			$out .= '<div class="bc-muted">Report from ' . brra_e( brra_c_name( $r['by'] ) ) . '</div>';
		} elseif ( $html ) {
			$can  = brra_can_write_report( $it['report'], $m['id'] );
			$out .= '<p class="bc-muted">' . ( $r ? 'Report started but not submitted yet.' : 'No report submitted yet.' ) . ( $can && in_array( $m['status'], array( 'draft', 'sent', 'open' ), true ) ? ' <a href="' . esc_url( brra_c_url( 'report', array( 'id' => $m['id'], 'report' => $it['report'] ) ) ) . '">Write it</a>' : '' ) . '</p>';
		}
	}
	if ( 'fundraising' === $it['kind'] ) {
		$prev  = brra_previous_meeting( $m );
		$draws = brra_100club_draws( $prev ? $prev['date'] : '', $m['date'] );
		foreach ( $draws as $d ) {
			$out .= '<div class="bc-sub-hd">100 Club, drawn at ' . brra_e( $d['title'] ) . ' (' . brra_e( brra_c_date( $d['date'], 'j M' ) ) . ')</div><table class="bc-table bc-tight"><tr><th>Place</th><th>Number</th><th>Holder</th><th>Prize</th></tr>';
			foreach ( $d['places'] as $p => $row ) {
				$out .= '<tr><td>' . brra_e( array( '1st', '2nd', '3rd', '4th' )[ $p ] ) . '</td><td>' . brra_e( $row['num'] ) . '</td><td>' . brra_e( $row['holder'] ? $row['holder'] : 'Unsold' ) . '</td><td>' . brra_e( $row['prize'] ? '£' . $row['prize'] : '' ) . ( ! empty( $row['paid'] ) ? ' (paid)' : '' ) . '</td></tr>';
			}
			$out .= '</table>';
		}
		if ( ! $draws && $html ) {
			$out .= '<p class="bc-muted">No 100 Club draws recorded since the last meeting. They\'re entered on the night from Event night.</p>';
		}
	}
	return $out;
}

/**
 * Bank balance and change for the Treasurer's item.
 *
 * @param array $m Meeting.
 */
function brra_treasurer_figures( $m ) {
	list( $now ) = brra_finance_at( $m['date'] );
	if ( ! $now ) {
		return '';
	}
	$prev_meeting = brra_previous_meeting( $m );
	list( $then ) = $prev_meeting ? brra_finance_at( $prev_meeting['date'] ) : array( null );
	if ( $then && $then['id'] === $now['id'] ) {
		$then = null;
	}
	$out = '<div class="bc-figs"><div><span class="bc-muted">Balance (' . brra_e( brra_c_date( $now['date'], 'j M' ) ) . ')</span><strong>' . brra_e( brra_money( $now['balance'] ) ) . '</strong></div>';
	if ( $then ) {
		$out .= '<div><span class="bc-muted">Last meeting</span><strong>' . brra_e( brra_money( $then['balance'] ) ) . '</strong></div><div>' . brra_change_pill( $now['balance'] - $then['balance'], 'money' ) . '</div>';
	}
	$out .= '</div>';
	if ( $now['notes'] ) {
		$out .= '<p class="bc-muted">' . brra_e( $now['notes'] ) . '</p>';
	}
	return $out;
}

/**
 * Member numbers for the Membership Secretary's item.
 *
 * @param array $m Meeting.
 */
function brra_membership_figures( $m ) {
	$season  = brra_current_season( strtotime( $m['date'] ) )[0];
	$members = brra_season_members( $season );
	if ( ! $members ) {
		return '';
	}
	$prev  = brra_previous_meeting( $m );
	$since = 0;
	foreach ( $members as $x ) {
		if ( $prev && substr( $x['start'], 0, 10 ) > $prev['date'] && substr( $x['start'], 0, 10 ) <= $m['date'] ) {
			++$since;
		}
	}
	$renewed = count( array_filter( wp_list_pluck( $members, 'renewed' ) ) );
	return '<div class="bc-figs"><div><span class="bc-muted">Members this season</span><strong>' . count( $members ) . '</strong></div><div><span class="bc-muted">Joined since last meeting</span><strong>' . (int) $since . '</strong></div><div><span class="bc-muted">New / renewed</span><strong>' . ( count( $members ) - $renewed ) . ' / ' . (int) $renewed . '</strong></div></div>';
}

/**
 * Platforms in the media report.
 */
function brra_media_platforms() {
	return array( 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X' );
}

/**
 * Media report figures as cards with changes.
 *
 * @param array $m Meeting.
 * @param array $r Report.
 */
function brra_media_cards( $m, $r ) {
	$prev  = brra_previous_media( $m );
	$d     = $r['data'];
	$sum   = function ( $data, $f ) {
		$t = 0;
		foreach ( brra_media_platforms() as $k => $l ) {
			$t += isset( $data[ $k ][ $f ] ) ? (int) str_replace( ',', '', $data[ $k ][ $f ] ) : 0;
		}
		return $t;
	};
	$cards = array(
		array( 'Posts', $sum( $d, 'posts' ), $prev ? $sum( $prev, 'posts' ) : null ),
		array( 'Views', $sum( $d, 'views' ), $prev ? $sum( $prev, 'views' ) : null ),
		array( 'Likes', $sum( $d, 'likes' ), $prev ? $sum( $prev, 'likes' ) : null ),
		array( 'Followers', $sum( $d, 'followers' ), $prev ? $sum( $prev, 'followers' ) : null ),
		array( 'Website visits', isset( $d['web']['visits'] ) ? (int) str_replace( ',', '', $d['web']['visits'] ) : 0, $prev && isset( $prev['web']['visits'] ) ? (int) str_replace( ',', '', $prev['web']['visits'] ) : null ),
	);
	$out = '<div class="bc-stats">';
	foreach ( $cards as $c ) {
		$out .= '<div><span class="bc-muted">' . brra_e( $c[0] ) . '</span><strong>' . number_format( $c[1] ) . '</strong>' . ( null !== $c[2] ? brra_change_pill( $c[1] - $c[2] ) : '' ) . '</div>';
	}
	$out .= '</div><table class="bc-table bc-tight"><tr><th></th><th>Posts</th><th>Views</th><th>Likes</th><th>Followers</th></tr>';
	foreach ( brra_media_platforms() as $k => $l ) {
		$out .= '<tr><td><strong>' . brra_e( $l ) . '</strong></td>';
		foreach ( array( 'posts', 'views', 'likes', 'followers' ) as $f ) {
			$out .= '<td>' . brra_e( isset( $d[ $k ][ $f ] ) ? $d[ $k ][ $f ] : '' ) . '</td>';
		}
		$out .= '</tr>';
	}
	$out .= '</table>';
	if ( ! empty( $d['best'] ) ) {
		$out .= '<p><strong>Best post:</strong> ' . brra_e( $d['best'] ) . '</p>';
	}
	return $out;
}

/**
 * Last meeting's media figures, to compare against.
 *
 * @param array $m Meeting.
 */
function brra_previous_media( $m ) {
	foreach ( brra_meetings() as $x ) {
		if ( $x['date'] < $m['date'] && ! empty( $x['reports']['media']['data'] ) ) {
			return $x['reports']['media']['data'];
		}
	}
	return null;
}

// ---------------------------------------------------------------- Reports.

/**
 * Write a report.
 */
function brra_c_view_report() {
	$m     = brra_meeting( absint( brra_c_in( 'id' ) ) );
	$key   = sanitize_key( brra_c_in( 'report' ) );
	$kinds = brra_report_kinds();
	if ( ! $m['date'] || ! isset( $kinds[ $key ] ) ) {
		echo '<p>Report not found.</p>';
		return;
	}
	if ( ! brra_can_write_report( $key, $m['id'] ) ) {
		brra_c_view_view();
		return;
	}
	$r    = isset( $m['reports'][ $key ] ) ? $m['reports'][ $key ] : array( 'text' => '', 'data' => array() );
	$subs = array(
		'secretary'  => 'Correspondence received and anything you want to raise. Plain text is fine.',
		'treasurer'  => 'The balance and change since the last meeting come from Finance automatically. Add anything else to report.',
		'membership' => 'Member numbers come from the membership records automatically. Add anything else to report.',
		'media'      => 'Type in this month\'s figures. Last month\'s are shown in grey, the changes are worked out, and a summary can be drafted for you to edit.',
		'mds'        => 'Speakers booked, topics and anything else about the development sessions.',
	);
	brra_c_head( 'Reports · meeting on ' . brra_c_date( $m['date'], 'j F' ), $kinds[ $key ][0], ! empty( $r['submitted'] ) ? brra_pill( 'Submitted', 'g' ) : '', $subs[ $key ] );
	echo brra_c_form( 'report', array( 'id' => $m['id'], 'report' => $key ), 'class="bc-report-form"' ); // phpcs:ignore WordPress.Security.EscapeOutput
	if ( 'treasurer' === $key ) {
		echo '<div class="bc-card">' . brra_treasurer_figures( $m ) . '<p><a href="' . esc_url( brra_c_url( 'finance' ) ) . '">Enter a new balance in Finance</a></p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( 'membership' === $key ) {
		echo '<div class="bc-card">' . brra_membership_figures( $m ) . '<p><a href="' . esc_url( brra_c_url( 'stats' ) ) . '">See membership stats</a></p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( 'media' === $key ) {
		$prev = brra_previous_media( $m );
		$d    = $r['data'];
		echo '<div class="bc-grid bc-g2w"><div class="bc-card"><h3>Figures this month</h3><table class="bc-table bc-media" id="bc-media"><thead><tr><th></th><th>Posts</th><th>Views / reach</th><th>Likes</th><th>Followers</th></tr></thead><tbody>';
		foreach ( brra_media_platforms() as $p => $label ) {
			echo '<tr><td><strong>' . brra_e( $label ) . '</strong></td>';
			foreach ( array( 'posts', 'views', 'likes', 'followers' ) as $f ) {
				$pv = $prev && isset( $prev[ $p ][ $f ] ) ? $prev[ $p ][ $f ] : '';
				echo '<td><input inputmode="numeric" name="data[' . esc_attr( $p ) . '][' . esc_attr( $f ) . ']" data-p="' . esc_attr( $p ) . '" data-f="' . esc_attr( $f ) . '" data-prev="' . esc_attr( $pv ) . '" value="' . esc_attr( isset( $d[ $p ][ $f ] ) ? $d[ $p ][ $f ] : '' ) . '"><div class="bc-muted">' . brra_e( $pv ) . '</div></td>';
			}
			echo '</tr>';
		}
		$pw = $prev && isset( $prev['web']['visits'] ) ? $prev['web']['visits'] : '';
		$pf = $prev && isset( $prev['web']['forms'] ) ? $prev['web']['forms'] : '';
		echo '<tr><td><strong>Website</strong></td><td colspan="2"><input inputmode="numeric" placeholder="Visits" name="data[web][visits]" data-p="web" data-f="visits" data-prev="' . esc_attr( $pw ) . '" value="' . esc_attr( isset( $d['web']['visits'] ) ? $d['web']['visits'] : '' ) . '"><div class="bc-muted">' . brra_e( $pw ? $pw . ' visits' : '' ) . '</div></td><td colspan="2"><input inputmode="numeric" placeholder="Form sign-ups" name="data[web][forms]" data-p="web" data-f="forms" data-prev="' . esc_attr( $pf ) . '" value="' . esc_attr( isset( $d['web']['forms'] ) ? $d['web']['forms'] : '' ) . '"><div class="bc-muted">' . brra_e( '' !== $pf ? $pf . ' forms' : '' ) . '</div></td></tr>';
		echo '</tbody></table><label class="bc-stack">Best post of the month<input type="text" name="data[best]" id="bc-best" value="' . esc_attr( isset( $d['best'] ) ? $d['best'] : '' ) . '" placeholder="e.g. Christmas Social poster: 48 likes, 3 shares"></label></div>';
		echo '<div class="bc-card"><h3>How it reads in the agenda</h3><div class="bc-stats" id="bc-media-cards"></div><div class="bc-row bc-sp"><span class="bc-muted">Summary</span><button type="button" class="bc-btn is-sm is-ghost" id="bc-draft">Draft it from the figures</button></div><textarea name="text" rows="7" id="bc-summary">' . esc_textarea( $r['text'] ) . '</textarea></div></div>';
	} else {
		echo '<div class="bc-card"><label class="bc-stack">' . ( 'secretary' === $key ? 'Your report' : 'Notes' ) . '<textarea name="text" rows="' . ( 'secretary' === $key ? 14 : 8 ) . '">' . esc_textarea( $r['text'] ) . '</textarea></label></div>';
	}
	echo '<div class="bc-row"><button class="bc-btn is-ghost" name="op" value="save">Save draft</button><button class="bc-btn" name="op" value="submit">Submit to agenda</button><a href="' . esc_url( brra_c_url( 'view', array( 'id' => $m['id'] ) ) ) . '">See the agenda</a></div></form>';
}

// ---------------------------------------------------------------- Live meeting.

/**
 * Take the minutes.
 */
function brra_c_view_live() {
	$m = brra_meeting( absint( brra_c_in( 'id' ) ) );
	if ( ! $m['date'] ) {
		echo '<p>Meeting not found.</p>';
		return;
	}
	if ( ! brra_can_manage_minutes( $m['id'] ) ) {
		brra_c_view_view();
		return;
	}
	if ( 'open' !== $m['status'] ) {
		brra_c_head( 'Committee meeting · ' . brra_c_date( $m['date'] ), 'Taking the minutes' );
		echo '<div class="bc-card"><p>This meeting isn\'t open. ' . ( in_array( $m['status'], array( 'draft', 'sent' ), true ) ? 'Press Open meeting when the Chairman opens it.' : 'It has been closed.' ) . '</p>';
		if ( in_array( $m['status'], array( 'draft', 'sent' ), true ) ) {
			echo brra_c_form( 'meeting_open', array( 'id' => $m['id'] ) ) . '<button class="bc-btn is-gold">Open meeting</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo '<a class="bc-btn" href="' . esc_url( brra_c_url( 'minutes', array( 'id' => $m['id'] ) ) ) . '">Read the minutes</a>';
		}
		echo '</div>';
		return;
	}
	$people = brra_committee_people( false );
	echo brra_c_form( 'live', array( 'id' => $m['id'] ), 'id="bc-live" data-ajax="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="bc-live"><span class="bc-dot"></span><strong>Meeting open</strong><span class="bc-muted">Opened at <input type="time" name="opened" value="' . esc_attr( $m['opened'] ) . '" class="bc-time"> · <span id="bc-saved">saves as you type</span></span><button class="bc-btn is-sm" name="op" value="close" onclick="return confirm(\'Close the meeting and make the minutes?\')">Close meeting</button></div>';
	echo '<div class="bc-eyebrow">Committee meeting · ' . brra_e( brra_c_date( $m['date'] ) ) . '</div><h1 class="bc-h1">Taking the minutes</h1>';
	echo '<div class="bc-grid bc-live-grid"><div class="bc-card bc-att"><h3>Attendance</h3><table class="bc-table">';
	$present = 0;
	foreach ( $people as $u ) {
		$v        = isset( $m['att'][ $u->ID ] ) ? $m['att'][ $u->ID ] : '';
		$present += 'present' === $v ? 1 : 0;
		echo '<tr><td>' . brra_e( brra_c_name( $u->ID ) ) . '<div class="bc-muted">' . brra_e( brra_committee_title( $u->ID ) ) . '</div><div class="bc-seg bc-seg-full">';
		foreach ( array( 'present' => 'Present', 'apologies' => 'Apols', 'absent' => 'Absent' ) as $k => $l ) {
			echo '<label><input type="radio" name="att[' . (int) $u->ID . ']" value="' . esc_attr( $k ) . '"' . checked( $v, $k, false ) . '><span>' . brra_e( $l ) . '</span></label>';
		}
		echo '</div></td></tr>';
	}
	echo '</table><label class="bc-stack">Also present<input type="text" name="also" value="' . esc_attr( $m['also'] ) . '"></label><p class="bc-muted" id="bc-quorum" data-n="' . count( $people ) . '">' . (int) $present . ' present</p></div><div>';
	$n     = 0;
	$prev  = brra_previous_meeting( $m );
	$aopts = array(
		'yes'     => 'Approved',
		'amended' => 'With amendments',
		'no'      => 'Not approved',
	);
	foreach ( $m['items'] as $it ) {
		++$n;
		$k = 'items[' . $it['key'] . ']';
		$f = $it['f'];
		echo '<div class="bc-item" id="item-' . esc_attr( $it['key'] ) . '"><div class="bc-item-hd"><span class="bc-num">' . (int) $n . '</span><strong>' . brra_e( $it['title'] ) . '</strong>' . ( $it['lead'] ? '<span class="bc-muted bc-right">' . brra_e( brra_c_name( $it['lead'] ) ) . '</span>' : '' ) . '</div><div class="bc-item-bd">';
		$extra = brra_item_extra( $m, $it, true );
		if ( $extra ) {
			echo '<details class="bc-extra"' . ( 'report' === $it['kind'] ? '' : ' open' ) . '><summary>' . ( 'report' === $it['kind'] ? 'Show the report' : 'Figures' ) . '</summary>' . $extra . '</details>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( 'welcome' === $it['kind'] ) {
			echo '<label class="bc-stack">Apologies received<input type="text" name="' . esc_attr( $k ) . '[f][apologies]" value="' . esc_attr( isset( $f['apologies'] ) ? $f['apologies'] : '' ) . '" placeholder="Filled from attendance if left blank"></label>';
		}
		if ( 'minutes' === $it['kind'] ) {
			echo '<div class="bc-grid bc-g2 bc-tight-grid"><label class="bc-stack">Proposed' . brra_people_select( $k . '[f][proposed]', isset( $f['proposed'] ) ? $f['proposed'] : 0, 'Choose' ) . '</label><label class="bc-stack">Seconded' . brra_people_select( $k . '[f][seconded]', isset( $f['seconded'] ) ? $f['seconded'] : 0, 'Choose' ) . '</label><div class="bc-stack bc-span2">Outcome<div class="bc-seg bc-seg-full">'; // phpcs:ignore WordPress.Security.EscapeOutput
			foreach ( $aopts as $ok => $ol ) {
				echo '<label><input type="radio" name="' . esc_attr( $k ) . '[f][outcome]" value="' . esc_attr( $ok ) . '"' . checked( isset( $f['outcome'] ) ? $f['outcome'] : '', $ok, false ) . '><span>' . brra_e( $ol ) . '</span></label>';
			}
			echo '</div></div></div><label class="bc-stack">Amendments<input type="text" name="' . esc_attr( $k ) . '[f][amendments]" value="' . esc_attr( isset( $f['amendments'] ) ? $f['amendments'] : '' ) . '"></label>';
			if ( $prev ) {
				echo '<p class="bc-muted"><a href="' . esc_url( brra_c_url( 'minutes', array( 'id' => $prev['id'] ) ) ) . '" target="_blank">Read the minutes of ' . brra_e( brra_c_date( $prev['date'], 'j F' ) ) . '</a></p>';
			}
		}
		if ( 'next' === $it['kind'] ) {
			echo '<label class="bc-stack">Next meeting<input type="text" name="' . esc_attr( $k ) . '[f][next]" value="' . esc_attr( isset( $f['next'] ) ? $f['next'] : '' ) . '" placeholder="e.g. Thursday 14 January 2027, 20:00, St Mary\'s Social Club"></label>';
		}
		foreach ( $it['subs'] as $si => $s ) {
			echo '<div class="bc-sub"><div class="bc-sub-hd">' . brra_e( chr( 97 + $si ) . ') ' . $s['title'] ) . '</div>';
			if ( ! empty( $s['action'] ) ) {
				echo '<label class="bc-check"><input type="checkbox" name="' . esc_attr( $k ) . '[subdone][]" value="' . esc_attr( $s['action'] ) . '"> Done, close this action</label>';
			}
			echo '<textarea rows="2" name="' . esc_attr( $k ) . '[subs][' . (int) $si . ']" placeholder="Notes">' . esc_textarea( $s['notes'] ) . '</textarea></div>';
		}
		if ( ! in_array( $it['kind'], array( 'welcome', 'next' ), true ) ) {
			echo '<label class="bc-stack">Notes<textarea rows="3" name="' . esc_attr( $k ) . '[notes]">' . esc_textarea( $it['notes'] ) . '</textarea></label>';
			echo '<label class="bc-stack">Decision<input type="text" name="' . esc_attr( $k ) . '[decision]" value="' . esc_attr( $it['decision'] ) . '"></label>';
			echo '<div class="bc-actions" data-name="' . esc_attr( $k ) . '[actions]"><div class="bc-muted">Actions</div>';
			$rows   = $it['actions'];
			$rows[] = array( 'id' => '', 'text' => '', 'owner' => 0, 'due' => '', 'done' => 0 );
			foreach ( $rows as $ai => $a ) {
				echo brra_c_live_action_row( $k . '[actions][' . $ai . ']', $a ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '<button type="button" class="bc-btn is-sm is-ghost bc-add-action">+ Add action</button></div>';
		}
		echo '</div></div>';
	}
	echo '<div class="bc-row"><button class="bc-btn" name="op" value="save">Save</button><button class="bc-btn is-gold" name="op" value="close" onclick="return confirm(\'Close the meeting and make the minutes?\')">Close meeting</button></div>';
	echo '</div></div></form>';
}

/**
 * One action row on the live screen.
 *
 * @param string $name Field name prefix.
 * @param array  $a    Action.
 */
function brra_c_live_action_row( $name, $a ) {
	return '<div class="bc-action-row"><input type="hidden" name="' . esc_attr( $name ) . '[id]" value="' . esc_attr( $a['id'] ) . '"><input type="text" name="' . esc_attr( $name ) . '[text]" value="' . esc_attr( $a['text'] ) . '" placeholder="What needs doing">' . brra_people_select( $name . '[owner]', $a['owner'], 'Who' ) . '<input type="date" name="' . esc_attr( $name ) . '[due]" value="' . esc_attr( $a['due'] ) . '" aria-label="Due"><label class="bc-check"><input type="checkbox" name="' . esc_attr( $name ) . '[done]" value="1"' . checked( ! empty( $a['done'] ), true, false ) . '> Done</label></div>';
}

// ---------------------------------------------------------------- Minutes document.

/**
 * The minutes.
 */
function brra_c_view_minutes() {
	$m = brra_meeting( absint( brra_c_in( 'id' ) ) );
	if ( ! $m['date'] ) {
		echo '<p>Meeting not found.</p>';
		return;
	}
	$st    = brra_meeting_status_label( $m['status'] );
	$right = '<a class="bc-btn is-ghost" href="' . esc_url( brra_c_action_url( 'word', array( 'id' => $m['id'] ) ) ) . '">Download for Word</a><a class="bc-btn is-ghost" target="_blank" href="' . esc_url( brra_c_url( 'minutes', array( 'id' => $m['id'], 'print' => 1 ) ) ) . '">Print or save as PDF</a>';
	if ( brra_can_manage_minutes( $m['id'] ) && 'closed' === $m['status'] ) {
		$right .= brra_c_form( 'meeting_status', array( 'id' => $m['id'], 'set' => 'reopen' ) ) . '<button class="bc-btn is-ghost">Make changes</button></form>';
		$right .= brra_c_form( 'meeting_status', array( 'id' => $m['id'], 'set' => 'approved' ) ) . '<button class="bc-btn">Mark as approved</button></form>';
	}
	brra_c_head( $st[0] . ( 'approved' === $m['status'] && $m['approved'] ? ' on ' . brra_c_date( $m['approved'], 'j F Y' ) : '' ), 'Minutes, ' . brra_c_date( $m['date'], 'j F Y' ), $right );
	echo '<div class="bc-doc-wrap"><div class="bc-doc">' . brra_render_minutes_doc( $m ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Styles shared by the minutes on screen, in print and in Word.
 */
function brra_doc_css() {
	return '.bd{font-family:Arial,sans-serif;font-size:10.5pt;color:#0B1626}.bd h1{font-size:22pt;margin:0;color:#050F1C}.bd .bd-sub{color:#3E4C60;margin:2pt 0 10pt}'
		. '.bd table{width:100%;border-collapse:collapse;margin:0 0 8pt}.bd td,.bd th{border:1px solid #c9d2dd;padding:5pt 7pt;vertical-align:top;text-align:left;font-size:10pt}'
		. '.bd th{background:#F3F5F8;font-weight:bold}.bd td.l{width:32%;background:#F3F5F8;font-weight:bold}.bd .band{background:#050F1C;color:#fff;font-weight:bold;letter-spacing:1pt;padding:4pt 7pt;margin:12pt 0 0}'
		. '.bd .ih{background:#F3F5F8;font-weight:bold}.bd .gold{border-top:3pt solid #D4AF37;margin:0 0 10pt}';
}

/**
 * A two-column row for the minutes.
 *
 * @param string $label Label.
 * @param string $value Value (escaped HTML).
 */
function brra_doc_row( $label, $value ) {
	return '<tr><td class="l">' . brra_e( $label ) . '</td><td>' . $value . '</td></tr>';
}

/**
 * Multi-line text for a document cell.
 *
 * @param string $t Text.
 */
function brra_doc_text( $t ) {
	return nl2br( brra_e( $t ) );
}

/**
 * The minutes in the committee's boxed format: details, attendance, one box per item, actions.
 *
 * @param array $m Meeting.
 */
function brra_render_minutes_doc( $m ) {
	$chair   = $m['chair'] ? $m['chair'] : brra_committee_holder( 'chair' );
	$minuter = $m['minuter'] ? $m['minuter'] : brra_committee_holder( 'secretary' );
	$out     = '<div class="bd"><div class="gold"></div><h1>MINUTES</h1><div class="bd-sub">Committee Meeting &nbsp;|&nbsp; ' . brra_e( brra_c_date_long( $m['date'] ) ) . ( 'approved' === $m['status'] ? '' : ' &nbsp;|&nbsp; DRAFT' ) . '</div><table>';
	$out    .= brra_doc_row( 'Date and time', brra_e( brra_c_date_long( $m['date'] ) . ', ' . $m['time'] ) );
	$out    .= brra_doc_row( 'Venue', brra_e( $m['venue'] ) );
	$out    .= brra_doc_row( 'Chair', brra_e( $chair ? brra_c_name( $chair ) . ', ' . brra_committee_title( $chair ) : '' ) );
	$out    .= brra_doc_row( 'Minutes taken by', brra_e( $minuter ? brra_c_name( $minuter ) . ', ' . brra_committee_title( $minuter ) : '' ) );
	$out    .= brra_doc_row( 'Meeting opened', brra_e( $m['opened'] ) );
	$out    .= '</table><div class="band">ATTENDANCE</div><table><tr><th>Name</th><th>Role</th><th>Present / apologies / absent</th></tr>';
	$labels  = array( 'present' => 'Present', 'apologies' => 'Apologies', 'absent' => 'Absent' );
	$apols   = array();
	$ids     = array_keys( $m['att'] );
	foreach ( brra_committee_people( false ) as $u ) {
		if ( ! in_array( $u->ID, $ids, true ) ) {
			$ids[] = $u->ID;
		}
	}
	foreach ( $ids as $uid ) {
		if ( ! get_userdata( $uid ) ) {
			continue;
		}
		$v = isset( $m['att'][ $uid ] ) ? $m['att'][ $uid ] : '';
		if ( 'apologies' === $v ) {
			$apols[] = brra_c_name( $uid );
		}
		$out .= '<tr><td>' . brra_e( brra_c_name( $uid ) ) . '</td><td>' . brra_e( brra_committee_title( $uid ) ) . '</td><td>' . brra_e( isset( $labels[ $v ] ) ? $labels[ $v ] : '' ) . '</td></tr>';
	}
	$out .= '<tr><td class="l">Also present</td><td colspan="2">' . brra_e( $m['also'] ) . '</td></tr></table><div class="band">BUSINESS</div>';
	$n    = 0;
	$all  = array();
	foreach ( $m['items'] as $it ) {
		++$n;
		$f    = $it['f'];
		$out .= '<table><tr><td colspan="2" class="ih">' . (int) $n . '.&nbsp; ' . brra_e( $it['title'] ) . '</td></tr>';
		if ( 'welcome' === $it['kind'] ) {
			$out .= brra_doc_row( 'Apologies received', brra_e( ! empty( $f['apologies'] ) ? $f['apologies'] : implode( ', ', $apols ) ) );
		}
		if ( 'minutes' === $it['kind'] ) {
			$outcome = array( 'yes' => 'Yes', 'amended' => 'With amendments', 'no' => 'No' );
			$out    .= brra_doc_row( 'Proposed', brra_e( ! empty( $f['proposed'] ) ? brra_c_name( $f['proposed'] ) : '' ) );
			$out    .= brra_doc_row( 'Seconded', brra_e( ! empty( $f['seconded'] ) ? brra_c_name( $f['seconded'] ) : '' ) );
			$out    .= brra_doc_row( 'Approved (yes / with amendments / no)', brra_e( isset( $f['outcome'], $outcome[ $f['outcome'] ] ) ? $outcome[ $f['outcome'] ] : '' ) );
			$out    .= brra_doc_row( 'Amendments', brra_e( isset( $f['amendments'] ) ? $f['amendments'] : '' ) );
		}
		if ( 'report' === $it['kind'] && 'treasurer' === $it['report'] ) {
			list( $bal ) = brra_finance_at( $m['date'] );
			if ( $bal ) {
				$prev        = brra_previous_meeting( $m );
				list( $was ) = $prev ? brra_finance_at( $prev['date'] ) : array( null );
				$chg         = $was && $was['id'] !== $bal['id'] ? ' (' . ( $bal['balance'] >= $was['balance'] ? 'up ' : 'down ' ) . brra_money( abs( $bal['balance'] - $was['balance'] ) ) . ' since ' . brra_c_date( $prev['date'], 'j F' ) . ')' : '';
				$out        .= brra_doc_row( 'Balance reported', brra_e( brra_money( $bal['balance'] ) . $chg ) );
			}
		}
		if ( 'fundraising' === $it['kind'] ) {
			$prev = brra_previous_meeting( $m );
			foreach ( brra_100club_draws( $prev ? $prev['date'] : '', $m['date'] ) as $d ) {
				$res = array();
				foreach ( $d['places'] as $p => $row ) {
					$res[] = array( '1st', '2nd', '3rd', '4th' )[ $p ] . ': ' . $row['num'] . ' ' . ( $row['holder'] ? $row['holder'] : 'unsold' ) . ( $row['prize'] ? ' (£' . $row['prize'] . ')' : '' );
				}
				$out .= brra_doc_row( '100 Club, ' . brra_c_date( $d['date'], 'j M' ), brra_e( implode( '; ', $res ) ) );
			}
		}
		foreach ( $it['subs'] as $si => $s ) {
			$out .= brra_doc_row( chr( 97 + $si ) . ') ' . $s['title'], brra_doc_text( $s['notes'] ) );
		}
		if ( 'next' === $it['kind'] ) {
			$out .= brra_doc_row( 'Next meeting', brra_e( isset( $f['next'] ) ? $f['next'] : '' ) );
		} elseif ( 'welcome' !== $it['kind'] ) {
			if ( '' !== trim( $it['notes'] ) || ! $it['subs'] ) {
				$out .= brra_doc_row( 'Notes', brra_doc_text( $it['notes'] ) );
			}
			$acts = array();
			foreach ( $it['actions'] as $a ) {
				$acts[] = $a['text'] . ( $a['owner'] ? ' (' . brra_c_name( $a['owner'] ) . ( $a['due'] ? ', by ' . brra_c_date( $a['due'], 'j M' ) : '' ) . ')' : '' );
				$a['n'] = $n;
				$all[]  = $a;
			}
			$out .= brra_doc_row( 'Decision / action', brra_doc_text( trim( $it['decision'] . ( $it['decision'] && $acts ? "\n" : '' ) . implode( "\n", $acts ) ) ) );
		}
		$out .= '</table>';
	}
	$out .= '<div class="band">ACTION SUMMARY</div><table><tr><th>Action</th><th>Owner</th><th>Due</th><th>Item no.</th></tr>';
	foreach ( $all as $a ) {
		$out .= '<tr><td>' . brra_e( $a['text'] ) . '</td><td>' . brra_e( $a['owner'] ? brra_c_name( $a['owner'] ) : '' ) . '</td><td>' . brra_e( $a['due'] ? brra_c_date( $a['due'], 'j M Y' ) : '' ) . '</td><td>' . (int) $a['n'] . '</td></tr>';
	}
	if ( ! $all ) {
		$out .= '<tr><td colspan="4">No actions.</td></tr>';
	}
	$out .= '</table><div class="band">CLOSE</div><p>Meeting closed at ' . brra_e( $m['closed'] ? $m['closed'] : '______________' ) . '.</p></div>';
	return $out;
}

/**
 * The agenda as a document (for Word).
 *
 * @param array $m Meeting.
 */
function brra_render_agenda_doc( $m ) {
	$out = '<div class="bd"><div class="gold"></div><h1>AGENDA</h1><div class="bd-sub">Committee Meeting &nbsp;|&nbsp; ' . brra_e( brra_c_date_long( $m['date'] ) ) . '</div><table>';
	$out .= brra_doc_row( 'Date and time', brra_e( brra_c_date_long( $m['date'] ) . ', ' . $m['time'] ) ) . brra_doc_row( 'Venue', brra_e( $m['venue'] ) ) . '</table><div class="band">BUSINESS</div><table>';
	$n    = 0;
	foreach ( $m['items'] as $it ) {
		++$n;
		$subs = '';
		foreach ( $it['subs'] as $si => $s ) {
			$subs .= '<br>' . chr( 97 + $si ) . ') ' . brra_e( $s['title'] );
		}
		$out .= '<tr><td style="width:8%">' . (int) $n . '.</td><td>' . brra_e( $it['title'] ) . $subs . '</td><td style="width:26%">' . brra_e( $it['lead'] ? brra_c_name( $it['lead'] ) : '' ) . '</td></tr>';
	}
	return $out . '</table></div>';
}

// ---------------------------------------------------------------- Actions.

/**
 * Every action.
 */
function brra_c_view_actions() {
	$show = brra_c_in( 'show', 'open' );
	$who  = brra_c_in( 'who', 'all' );
	$list = brra_all_actions( 'open' === $show );
	if ( 'mine' === $who ) {
		$uid  = get_current_user_id();
		$list = array_filter(
			$list,
			function ( $a ) use ( $uid ) {
				return (int) $a['owner'] === $uid;
			}
		);
	}
	$tabs = '';
	foreach ( array( 'open|all' => 'Open', 'open|mine' => 'Mine', 'all|all' => 'Everything' ) as $k => $l ) {
		list( $s, $w ) = explode( '|', $k );
		$tabs         .= '<a class="bc-btn is-sm' . ( $s === $show && $w === $who ? '' : ' is-ghost' ) . '" href="' . esc_url( brra_c_url( 'actions', array( 'show' => $s, 'who' => $w ) ) ) . '">' . brra_e( $l ) . '</a>';
	}
	brra_c_head( 'Actions', 'Who is doing what', $tabs, 'Every action from the minutes. Open actions are carried forward to the next agenda under Matters arising.' );
	echo '<div class="bc-card">' . ( $list ? '<table class="bc-table"><thead><tr><th>Action</th><th>Who</th><th>Due</th><th></th></tr></thead>' . brra_c_action_rows( $list, true, 'actions' ) . '</table>' : '<p class="bc-muted">Nothing here.</p>' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

// ---------------------------------------------------------------- Finance.

/**
 * Bank balance tracker and 100 Club prizes to pay.
 */
function brra_c_view_finance() {
	$entries = brra_finance_entries();
	$can     = brra_can_manage_finance() || brra_has_committee_role( 'secretary' );
	brra_c_head( 'Finance', 'Bank balance tracker', '', 'Enter the balance from the statement. The change since the last entry is worked out, and the figures go into the Treasurer\'s item on the agenda automatically.' );
	$last = $entries ? end( $entries ) : null;
	echo '<div class="bc-grid bc-g2n">';
	if ( $can ) {
		echo '<div class="bc-card is-navy"><h3>New balance</h3>' . brra_c_form( 'finance', array(), 'class="bc-form"' ) . '<div class="bc-row"><input type="text" name="balance" inputmode="decimal" placeholder="£0.00" required><input type="date" name="date" value="' . esc_attr( current_time( 'Y-m-d' ) ) . '" required></div><textarea name="notes" rows="3" placeholder="Notes, e.g. raffle float in, room hire paid"></textarea><button class="bc-btn is-gold">Save balance</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $last ) {
			echo '<div class="bc-navy-foot"><div class="bc-row bc-sp"><span>Last entry (' . brra_e( brra_c_date( $last['date'], 'j M Y' ) ) . ')</span><strong>' . brra_e( brra_money( $last['balance'] ) ) . '</strong></div></div>';
		}
		echo '</div>';
	}
	echo '<div class="bc-card"><h3>Balance over time</h3>';
	$recent = array_slice( $entries, -12 );
	if ( $recent ) {
		$max = max( wp_list_pluck( $recent, 'balance' ) );
		$min = min( 0, min( wp_list_pluck( $recent, 'balance' ) ) );
		echo '<div class="bc-bars">';
		foreach ( $recent as $i => $e ) {
			$h = $max > $min ? round( ( $e['balance'] - $min ) / ( $max - $min ) * 100 ) : 0;
			echo '<div title="' . esc_attr( brra_money( $e['balance'] ) ) . '"><b style="height:' . (int) max( 3, $h ) . '%"' . ( count( $recent ) - 1 === $i ? ' class="is-last"' : '' ) . '></b><span>' . brra_e( brra_c_date( $e['date'], 'M' ) ) . '</span></div>';
		}
		echo '</div>';
	} else {
		echo '<p class="bc-muted">No balances yet.</p>';
	}
	echo '</div></div>';
	echo '<div class="bc-card"><h3>History</h3><table class="bc-table"><thead><tr><th>Date</th><th>Balance</th><th>Change</th><th>Notes</th><th>Entered by</th>' . ( $can ? '<th></th>' : '' ) . '</tr></thead><tbody>';
	$prev = null;
	$rows = array();
	foreach ( $entries as $e ) {
		$rows[] = array( $e, $prev );
		$prev   = $e;
	}
	foreach ( array_reverse( $rows ) as $r ) {
		list( $e, $p ) = $r;
		echo '<tr><td>' . brra_e( brra_c_date( $e['date'], 'j M Y' ) ) . '</td><td><strong>' . brra_e( brra_money( $e['balance'] ) ) . '</strong></td><td>' . ( $p ? brra_change_pill( $e['balance'] - $p['balance'], 'money' ) : '' ) . '</td><td class="bc-muted">' . brra_e( $e['notes'] ) . '</td><td class="bc-muted">' . brra_e( brra_c_name( $e['by'] ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo $can ? '<td><a href="' . esc_url( brra_c_action_url( 'finance_remove', array( 'eid' => $e['id'] ) ) ) . '" onclick="return confirm(\'Remove this entry?\')">Remove</a></td>' : '';
		echo '</tr>';
	}
	echo $rows ? '' : '<tr><td colspan="5" class="bc-muted">Nothing yet.</td></tr>';
	echo '</tbody></table></div>';

	$draws = brra_100club_draws();
	echo '<div class="bc-card"><h3>100 Club prizes</h3><p class="bc-muted">Drawn on MDS nights and entered from Event night. Tick them off once paid.</p>';
	if ( $draws ) {
		echo '<table class="bc-table"><thead><tr><th>Draw</th><th>Place</th><th>Number</th><th>Holder</th><th>Prize</th><th></th></tr></thead><tbody>';
		foreach ( $draws as $d ) {
			foreach ( $d['places'] as $p => $row ) {
				if ( ! $row['holder'] || ! $row['prize'] ) {
					continue;
				}
				$paid = ! empty( $row['paid'] );
				echo '<tr><td>' . brra_e( brra_c_date( $d['date'], 'j M Y' ) ) . '</td><td>' . brra_e( array( '1st', '2nd', '3rd', '4th' )[ $p ] ) . '</td><td>' . brra_e( $row['num'] ) . '</td><td>' . brra_e( $row['holder'] ) . '</td><td>£' . brra_e( $row['prize'] ) . '</td><td>';
				echo brra_can_manage_finance() ? '<a class="bc-btn is-sm' . ( $paid ? ' is-ghost' : '' ) . '" href="' . esc_url( brra_c_action_url( 'prize_paid', array( 'event' => $d['event'], 'place' => $p, 'paid' => $paid ? 0 : 1 ) ) ) . '">' . ( $paid ? 'Paid ✓' : 'Mark paid' ) . '</a>' : ( $paid ? brra_pill( 'Paid', 'g' ) : brra_pill( 'To pay', 'a' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '</td></tr>';
			}
		}
		echo '</tbody></table>';
	} else {
		echo '<p class="bc-muted">No draws recorded yet.</p>';
	}
	echo '</div>';
}

// ---------------------------------------------------------------- Files.

/**
 * File share.
 */
function brra_c_view_files() {
	$files   = get_option( 'brra_committee_files', array() );
	$folders = brra_file_folders();
	$cur     = sanitize_key( brra_c_in( 'folder' ) );
	$cur     = isset( $folders[ $cur ] ) ? $cur : '';
	brra_c_head( 'Files', 'Committee files', '', 'Only committee members can open these. Finance files are for the Treasurer and Chairman; membership files for the Membership Secretary and Secretary.' );
	echo '<div class="bc-row bc-wrap bc-folders"><a class="bc-btn is-sm' . ( $cur ? ' is-ghost' : '' ) . '" href="' . esc_url( brra_c_url( 'files' ) ) . '">All</a>';
	foreach ( $folders as $k => $f ) {
		if ( brra_can_open_folder( $k ) ) {
			echo '<a class="bc-btn is-sm' . ( $cur === $k ? '' : ' is-ghost' ) . '" href="' . esc_url( brra_c_url( 'files', array( 'folder' => $k ) ) ) . '">' . brra_e( $f[0] ) . ( $f[1] ? ' 🔒' : '' ) . '</a>';
		}
	}
	echo '</div><div class="bc-grid bc-g2w"><div class="bc-card"><table class="bc-table"><thead><tr><th>File</th><th>Folder</th><th>Added</th><th></th></tr></thead><tbody>';
	uasort(
		$files,
		function ( $a, $b ) {
			return strcmp( $b['at'], $a['at'] );
		}
	);
	$shown = 0;
	foreach ( $files as $id => $f ) {
		if ( ( $cur && $f['folder'] !== $cur ) || ! brra_can_open_folder( $f['folder'] ) ) {
			continue;
		}
		++$shown;
		$del = ( (int) $f['by'] === get_current_user_id() || brra_can_manage_committee() ) ? ' · <a href="' . esc_url( brra_c_action_url( 'file_delete', array( 'fid' => $id ) ) ) . '" onclick="return confirm(\'Delete this file?\')">Delete</a>' : '';
		echo '<tr><td><a href="' . esc_url( brra_c_action_url( 'file', array( 'fid' => $id ) ) ) . '"><strong>' . brra_e( $f['name'] ) . '</strong></a><div class="bc-muted">' . brra_e( $f['orig'] . ' · ' . size_format( $f['size'] ) ) . '</div></td><td class="bc-muted">' . brra_e( $folders[ $f['folder'] ][0] ) . '</td><td class="bc-muted">' . brra_e( strtok( brra_c_name( $f['by'] ), ' ' ) . ' · ' . mysql2date( 'j M Y', $f['at'] ) ) . '</td><td>' . $del . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo $shown ? '' : '<tr><td colspan="4" class="bc-muted">No files here yet.</td></tr>';
	echo '</tbody></table></div>';
	if ( brra_committee_can_edit() ) {
		echo '<div class="bc-card"><h3>Add a file</h3>' . brra_c_form( 'file_upload', array(), 'class="bc-form" enctype="multipart/form-data"' ) . '<input type="file" name="file" required><input type="text" name="title" placeholder="Name (optional)"><label>Folder<select name="folder">'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $folders as $k => $f ) {
			if ( brra_can_open_folder( $k ) ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . brra_e( $f[0] ) . '</option>';
			}
		}
		echo '</select></label><button class="bc-btn">Upload</button></form><p class="bc-muted">PDF, Word, Excel, PowerPoint, CSV, text or images.</p></div>';
	}
	echo '</div>';
}

// ---------------------------------------------------------------- Season calendar.

/**
 * Everything happening in a season: events, committee meetings, AGM and your own entries.
 *
 * @param int $season Season start year.
 */
function brra_calendar_entries( $season ) {
	$from     = $season . '-06-01';
	$to       = ( $season + 1 ) . '-05-31';
	$out      = array();
	$speakers = array();
	foreach ( brra_speakers() as $s ) {
		if ( $s['date'] && in_array( $s['status'], array( 'confirmed', 'done' ), true ) ) {
			$speakers[ $s['date'] ] = $s['name'];
		}
	}
	foreach ( brra_all_events() as $e ) {
		$d = brra_event_get( $e->ID, 'date' );
		if ( $d < $from || $d > $to ) {
			continue;
		}
		$t   = get_the_title( $e );
		$agm = (bool) preg_match( '/\bAGM\b/i', $t );
		$mds = brra_is_mds( $e->ID );
		$out[] = array(
			'date'  => $d,
			'title' => $t . ( $mds && isset( $speakers[ $d ] ) ? ': ' . $speakers[ $d ] : '' ),
			'label' => $agm ? 'AGM' : ( $mds ? 'MDS' : 'Event' ),
			'tone'  => $agm || ! $mds ? 'gold' : 'a',
			'short' => $agm ? 'AGM' : ( $mds ? 'MDS' : wp_trim_words( $t, 2, '' ) ),
		);
		if ( $mds && ! isset( $speakers[ $d ] ) && $d >= current_time( 'Y-m-d' ) ) {
			$out[] = array( 'date' => $d, 'title' => 'Speaker needed for ' . $t, 'label' => 'Speaker needed', 'tone' => 'r', 'short' => 'Speaker?' );
		}
	}
	foreach ( brra_meetings() as $m ) {
		if ( $m['date'] >= $from && $m['date'] <= $to ) {
			$out[] = array( 'date' => $m['date'], 'title' => 'Committee meeting', 'label' => 'Committee', 'tone' => 'n', 'short' => 'Cttee' );
		}
	}
	foreach ( (array) get_option( 'brra_calendar_extra', array() ) as $id => $c ) {
		if ( $c['date'] >= $from && $c['date'] <= $to ) {
			$out[] = array( 'date' => $c['date'], 'title' => $c['title'], 'label' => 'deadline' === $c['kind'] ? 'Deadline' : 'Other', 'tone' => 'deadline' === $c['kind'] ? 'r' : '', 'short' => wp_trim_words( $c['title'], 2, '' ), 'id' => $id );
		}
	}
	usort(
		$out,
		function ( $a, $b ) {
			return strcmp( $a['date'], $b['date'] );
		}
	);
	return $out;
}

/**
 * Season calendar.
 */
function brra_c_view_calendar() {
	$cur    = brra_current_season()[0];
	$season = absint( brra_c_in( 'season', (string) $cur ) );
	$season = $season > 1990 ? $season : $cur;
	$nav    = '<a class="bc-btn is-sm is-ghost" href="' . esc_url( brra_c_url( 'calendar', array( 'season' => $season - 1 ) ) ) . '">← ' . ( $season - 1 ) . '-' . substr( (string) $season, 2 ) . '</a><a class="bc-btn is-sm is-ghost" href="' . esc_url( brra_c_url( 'calendar', array( 'season' => $season + 1 ) ) ) . '">' . ( $season + 1 ) . '-' . substr( (string) ( $season + 2 ), 2 ) . ' →</a>';
	brra_c_head( 'Season ' . $season . '-' . substr( (string) ( $season + 1 ), 2 ), 'Season calendar', $nav, 'MDS nights and events come from the website\'s events, committee meetings from Meetings. Add deadlines and anything else below.' );
	$entries = brra_calendar_entries( $season );
	$months  = array();
	for ( $i = 0; $i < 12; $i++ ) {
		$ts                           = strtotime( $season . '-06-01 +' . $i . ' months' );
		$months[ gmdate( 'Y-m', $ts ) ] = array( gmdate( 'M', $ts ), array() );
	}
	foreach ( $entries as $e ) {
		$k = substr( $e['date'], 0, 7 );
		if ( isset( $months[ $k ] ) ) {
			$months[ $k ][1][] = $e;
		}
	}
	echo '<div class="bc-cal">';
	foreach ( $months as $k => $mo ) {
		echo '<div' . ( current_time( 'Y-m' ) === $k ? ' class="is-now"' : '' ) . '><b>' . brra_e( $mo[0] ) . '</b>';
		foreach ( $mo[1] as $e ) {
			echo '<span class="bc-tag bc-t-' . esc_attr( $e['tone'] ? $e['tone'] : 'x' ) . '" title="' . esc_attr( $e['title'] ) . '">' . brra_e( $e['short'] . ' ' . (int) substr( $e['date'], 8 ) ) . '</span>';
		}
		echo '</div>';
	}
	echo '</div><div class="bc-legend"><span><i class="bc-t-a"></i>MDS night</span><span><i class="bc-t-n"></i>Committee</span><span><i class="bc-t-gold"></i>Event or AGM</span><span><i class="bc-t-r"></i>Needs sorting or deadline</span></div>';
	echo '<div class="bc-grid bc-g2w"><div class="bc-card"><h3>Everything this season</h3><table class="bc-table">';
	foreach ( $entries as $e ) {
		echo '<tr><td><strong>' . brra_e( brra_c_date( $e['date'], 'D j M Y' ) ) . '</strong></td><td>' . brra_e( $e['title'] ) . '</td><td>' . brra_pill( $e['label'], 'gold' === $e['tone'] ? 'a' : $e['tone'] ) . '</td><td>' . ( ! empty( $e['id'] ) && brra_committee_can_edit() ? '<a href="' . esc_url( brra_c_action_url( 'calendar', array( 'op' => 'delete', 'cid' => $e['id'], 'season' => $season ) ) ) . '">Remove</a>' : '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo $entries ? '' : '<tr><td class="bc-muted">Nothing yet.</td></tr>';
	echo '</table></div>';
	if ( brra_committee_can_edit() ) {
		echo '<div class="bc-card"><h3>Add to the calendar</h3>' . brra_c_form( 'calendar', array( 'season' => $season ), 'class="bc-form"' ) . '<input type="date" name="date" required><input type="text" name="title" placeholder="e.g. Ticket applications due" required><label>Type<select name="kind"><option value="deadline">Deadline</option><option value="other">Something else</option></select></label><button class="bc-btn">Add</button></form><p class="bc-muted">To add an MDS or social, add it as an event on the website and it appears here.</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
}

// ---------------------------------------------------------------- Guest speakers.

/**
 * Guest speaker tracker.
 */
function brra_c_view_speakers() {
	$q        = brra_c_in( 'q' );
	$edit     = sanitize_key( brra_c_in( 'edit' ) );
	$all      = brra_speakers();
	$statuses = brra_speaker_statuses();
	brra_c_head( 'Season', 'Guest speakers', '', 'Who we\'ve had, their topic and who\'s booked. Whoever runs the event adds the speaker; everyone on the committee can see and edit.' );
	echo '<form method="get" class="bc-row bc-search"><input type="hidden" name="view" value="speakers"><input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="Search name or topic"><button class="bc-btn is-sm is-ghost">Search</button></form>';
	echo '<div class="bc-grid bc-g2w"><div class="bc-card"><table class="bc-table"><thead><tr><th>Date</th><th>Speaker</th><th>Topic</th><th>Status</th><th>Notes</th><th></th></tr></thead><tbody>';
	$shown = 0;
	foreach ( $all as $id => $s ) {
		if ( $q && false === stripos( $s['name'] . ' ' . $s['topic'] . ' ' . $s['notes'], $q ) ) {
			continue;
		}
		++$shown;
		$st = isset( $statuses[ $s['status'] ] ) ? $statuses[ $s['status'] ] : array( $s['status'], '' );
		echo '<tr><td>' . brra_e( $s['date'] ? brra_c_date( $s['date'], 'j M Y' ) : 'No date' ) . '</td><td><strong>' . brra_e( $s['name'] ? $s['name'] : 'Not booked' ) . '</strong><div class="bc-muted">added by ' . brra_e( strtok( brra_c_name( $s['by'] ), ' ' ) ) . '</div></td><td>' . brra_e( $s['topic'] ) . '</td><td>' . brra_pill( $st[0], $st[1] ) . '</td><td class="bc-muted">' . brra_e( $s['notes'] ) . '</td><td>' . ( brra_committee_can_edit() ? '<a href="' . esc_url( brra_c_url( 'speakers', array( 'edit' => $id ) ) ) . '">Edit</a>' : '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo $shown ? '' : '<tr><td colspan="6" class="bc-muted">No speakers ' . ( $q ? 'match that.' : 'yet.' ) . '</td></tr>';
	echo '</tbody></table></div>';
	if ( brra_committee_can_edit() ) {
		$s = $edit && isset( $all[ $edit ] ) ? $all[ $edit ] : array( 'date' => '', 'name' => '', 'topic' => '', 'status' => 'confirmed', 'notes' => '' );
		echo '<div class="bc-card"><h3>' . ( $edit && isset( $all[ $edit ] ) ? 'Edit speaker' : 'Add a speaker' ) . '</h3>' . brra_c_form( 'speaker', array( 'sid' => $edit ), 'class="bc-form"' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label>Date<input type="date" name="date" value="' . esc_attr( $s['date'] ) . '" list="bc-mds-dates"></label><datalist id="bc-mds-dates">';
		foreach ( brra_all_events() as $e ) {
			if ( brra_is_mds( $e->ID ) ) {
				echo '<option value="' . esc_attr( brra_event_get( $e->ID, 'date' ) ) . '">' . brra_e( get_the_title( $e ) ) . '</option>';
			}
		}
		echo '</datalist><input type="text" name="name" value="' . esc_attr( $s['name'] ) . '" placeholder="Speaker\'s name"><input type="text" name="topic" value="' . esc_attr( $s['topic'] ) . '" placeholder="Topic"><label>Status<select name="status">';
		foreach ( $statuses as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['status'], $k, false ) . '>' . brra_e( $v[0] ) . '</option>';
		}
		echo '</select></label><textarea name="notes" rows="3" placeholder="Notes, e.g. turnout, invite back, contact">' . esc_textarea( $s['notes'] ) . '</textarea><div class="bc-row"><button class="bc-btn">Save</button>' . ( $edit && isset( $all[ $edit ] ) ? '<button class="bc-btn is-ghost" name="op" value="delete" onclick="return confirm(\'Delete this speaker?\')">Delete</button><a href="' . esc_url( brra_c_url( 'speakers' ) ) . '">Cancel</a>' : '' ) . '</div></form></div>';
	}
	echo '</div>';
}

// ---------------------------------------------------------------- Membership stats.

/**
 * Membership stats: numbers for everyone, names and emails for the Membership Secretary and Secretary.
 */
function brra_c_view_stats() {
	$cur     = brra_current_season()[0];
	$season  = absint( brra_c_in( 'season', (string) $cur ) );
	$compare = absint( brra_c_in( 'compare', (string) ( $season - 1 ) ) );
	$filters = brra_stats_filters();
	$names   = brra_can_see_member_names();
	$hist    = get_option( 'brra_membership_history', array() );
	$seasons = array_unique( array_merge( brra_membership_seasons_with_data(), array_map( 'intval', array_keys( (array) $hist ) ), array( $cur ) ) );
	rsort( $seasons );
	$label = function ( $s ) {
		return $s . '-' . substr( (string) ( $s + 1 ), 2 );
	};

	$right = '';
	if ( $names ) {
		$right = '<a class="bc-btn is-ghost" href="' . esc_url( brra_c_action_url( 'emails_csv', array_merge( array( 'season' => $season ), $filters ) ) ) . '">Download emails (.csv)</a><a class="bc-btn" href="#bc-emails">Copy emails for Bcc</a>';
	}
	brra_c_head( 'Membership stats · kept up to date by the Membership Secretary', 'How membership is going', $right, $names ? 'You can see names and emails. Everyone else on the committee sees the numbers only.' : 'Everyone on the committee sees the numbers. Names and contact details stay with the Membership Secretary and Secretary.' );

	// Filters.
	$all_members = brra_season_members( $season );
	$opts        = array( 'type' => array(), 'level' => array(), 'county' => array(), 'role' => array() );
	foreach ( $all_members as $m ) {
		foreach ( $opts as $k => $v ) {
			$opts[ $k ][ '' === $m[ $k ] ? '(not given)' : $m[ $k ] ] = true;
		}
	}
	echo '<form method="get" class="bc-filters"><input type="hidden" name="view" value="stats"><label>Season<select name="season">';
	foreach ( $seasons as $s ) {
		echo '<option value="' . (int) $s . '"' . selected( $season, $s, false ) . '>' . brra_e( $label( $s ) ) . '</option>';
	}
	echo '</select></label><label>Compare with<select name="compare"><option value="0">Nothing</option>';
	foreach ( $seasons as $s ) {
		echo '<option value="' . (int) $s . '"' . selected( $compare, $s, false ) . '>' . brra_e( $label( $s ) ) . '</option>';
	}
	echo '</select></label>';
	foreach ( array( 'type' => 'Membership', 'level' => 'Level', 'county' => 'County FA', 'role' => 'Role' ) as $k => $l ) {
		echo '<label>' . brra_e( $l ) . '<select name="' . esc_attr( $k ) . '"><option value="">All</option>';
		$keys = array_keys( $opts[ $k ] );
		sort( $keys );
		foreach ( $keys as $o ) {
			echo '<option' . selected( isset( $filters[ $k ] ) ? $filters[ $k ] : '', $o, false ) . '>' . brra_e( $o ) . '</option>';
		}
		echo '</select></label>';
	}
	echo '<button class="bc-btn is-sm">Show</button>' . ( $filters ? '<a href="' . esc_url( brra_c_url( 'stats', array( 'season' => $season, 'compare' => $compare ) ) ) . '">Clear filters</a>' : '' ) . '</form>';

	$members = brra_season_members( $season, $filters );
	$summary = brra_season_summary( $season );
	$cmp     = $compare ? brra_season_summary( $compare ) : array();
	$renewed = count( array_filter( wp_list_pluck( $members, 'renewed' ) ) );
	$has     = in_array( $season, brra_membership_seasons_with_data(), true );
	$count   = $has ? count( $members ) : ( isset( $summary['members'] ) ? $summary['members'] : 0 );
	$new     = $has ? $count - $renewed : ( isset( $summary['new'] ) ? $summary['new'] : 0 );
	$ren     = $has ? $renewed : ( isset( $summary['renewed'] ) ? $summary['renewed'] : 0 );
	$guests  = brra_guests_who_joined( $season );
	echo '<div class="bc-grid bc-g4">';
	echo '<div class="bc-card"><span class="bc-muted">Members' . ( $filters ? ' (filtered)' : '' ) . '</span><div class="bc-big">' . (int) $count . '</div>' . ( ! $filters && isset( $cmp['members'] ) ? brra_change_pill( $count - $cmp['members'] ) . ' <span class="bc-muted">on ' . brra_e( $label( $compare ) ) . '</span>' : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="bc-card"><span class="bc-muted">Renewed after the AGM</span><div class="bc-big">' . (int) $ren . '</div>' . ( ! $filters && ! empty( $cmp['members'] ) ? brra_pill( round( $ren / $cmp['members'] * 100 ) . '% of ' . $label( $compare ) ) : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="bc-card"><span class="bc-muted">New members</span><div class="bc-big">' . (int) $new . '</div>' . ( ! $filters && isset( $cmp['new'] ) ? brra_change_pill( $new - $cmp['new'] ) : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="bc-card"><span class="bc-muted">Guests who later joined</span><div class="bc-big">' . (int) $guests[0] . '</div>' . brra_pill( 'from ' . $guests[1] . ' guests' ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput

	// Year-on-year chart.
	echo '<div class="bc-grid bc-g2w"><div class="bc-card"><div class="bc-row bc-sp"><h3>Members through the season, year on year</h3></div>' . brra_stats_chart( array_filter( array( $season, $compare, $compare ? $compare - 1 : 0 ) ), $label ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput

	// Breakdown.
	echo '<div class="bc-card"><h3>By refereeing level</h3>' . brra_stats_bars( $members, 'level' ) . '<h3 class="bc-mt">By County FA</h3>' . brra_stats_list( $members, 'county' ) . '<h3 class="bc-mt">By role</h3>' . brra_stats_list( $members, 'role' ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput

	// Season by season.
	echo '<div class="bc-card"><div class="bc-row bc-sp"><h3>Season by season</h3>' . ( $names ? '<a class="bc-btn is-sm is-ghost" href="#bc-history">Add earlier seasons</a>' : '' ) . '</div><table class="bc-table"><thead><tr><th>Season</th><th>Members</th><th>New</th><th>Renewed</th><th>Didn\'t renew</th><th>Average MDS turnout</th></tr></thead><tbody>';
	foreach ( $seasons as $s ) {
		$r = brra_season_summary( $s );
		if ( ! isset( $r['members'] ) && ! isset( $r['avg'] ) ) {
			continue;
		}
		$v = function ( $k ) use ( $r ) {
			return isset( $r[ $k ] ) ? (int) $r[ $k ] : '—';
		};
		echo '<tr' . ( $s === $season ? ' class="is-sel"' : '' ) . '><td><strong>' . brra_e( $label( $s ) ) . '</strong>' . ( $s === $cur ? ' <span class="bc-muted">(so far)</span>' : '' ) . ( ! empty( $r['note'] ) ? '<div class="bc-muted">' . brra_e( $r['note'] ) . '</div>' : '' ) . '</td><td>' . brra_e( $v( 'members' ) ) . '</td><td>' . brra_e( $v( 'new' ) ) . '</td><td>' . brra_e( $v( 'renewed' ) ) . '</td><td>' . brra_e( $v( 'lapsed' ) ) . '</td><td>' . brra_e( $v( 'avg' ) ) . '</td></tr>';
	}
	echo '</tbody></table></div>';

	if ( $names ) {
		echo '<div class="bc-card" id="bc-emails"><div class="bc-row bc-sp"><h3>Members ' . ( $filters ? '(filtered)' : '' ) . ' · ' . count( $members ) . '</h3><span class="bc-muted">Only you and the ' . ( brra_has_committee_role( 'membership' ) ? 'Secretary' : 'Membership Secretary' ) . ' can see this</span></div>';
		echo '<label class="bc-stack">Emails for Bcc (click to select, then copy)<textarea readonly rows="3" onclick="this.select()">' . esc_textarea( implode( ', ', wp_list_pluck( $members, 'email' ) ) ) . '</textarea></label>';
		echo '<details><summary>Show the list</summary><table class="bc-table"><thead><tr><th>Name</th><th>Email</th><th>Level</th><th>Role</th><th>County FA</th><th>Joined</th><th></th></tr></thead><tbody>';
		foreach ( $members as $m ) {
			echo '<tr><td>' . brra_e( $m['name'] ) . '</td><td>' . brra_e( $m['email'] ) . '</td><td>' . brra_e( $m['level'] ) . '</td><td>' . brra_e( $m['role'] ) . '</td><td>' . brra_e( $m['county'] ) . '</td><td>' . brra_e( mysql2date( 'j M Y', $m['start'] ) ) . '</td><td>' . ( $m['renewed'] ? brra_pill( 'Renewed' ) : brra_pill( 'New', 'g' ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table></details></div>';

		// Earlier seasons typed in.
		echo '<div class="bc-card" id="bc-history"><h3>Seasons before the website</h3><p class="bc-muted">The website counts members itself from this season on. For earlier seasons, type in what you have from your records. Members by month is a list of 12 numbers from June to May (the running total at the end of each month), used for the year-on-year chart.</p>' . brra_c_form( 'history' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<table class="bc-table bc-hist"><thead><tr><th>Season</th><th>Members</th><th>New</th><th>Renewed</th><th>Didn\'t renew</th><th>Avg MDS</th><th>Members by month (Jun to May)</th><th>Note</th><th>Remove</th></tr></thead><tbody>';
		$rows        = (array) $hist;
		$rows['new'] = array();
		foreach ( $rows as $s => $r ) {
			$key = 'new' === $s ? 0 : (int) $s;
			echo '<tr><td>' . ( 'new' === $s ? '<input type="number" name="hist[0][season]" placeholder="e.g. 2023" min="1990" max="2100">' : brra_e( $label( (int) $s ) ) ) . '</td>';
			foreach ( array( 'members', 'new', 'renewed', 'lapsed', 'avg' ) as $k ) {
				echo '<td><input type="number" min="0" name="hist[' . (int) $key . '][' . esc_attr( $k ) . ']" value="' . esc_attr( isset( $r[ $k ] ) ? $r[ $k ] : '' ) . '"></td>';
			}
			echo '<td><input type="text" name="hist[' . (int) $key . '][monthly]" value="' . esc_attr( isset( $r['monthly'] ) ? implode( ', ', $r['monthly'] ) : '' ) . '" placeholder="e.g. 20, 38, 45, 48, 50, …"></td><td><input type="text" name="hist[' . (int) $key . '][note]" value="' . esc_attr( isset( $r['note'] ) ? $r['note'] : '' ) . '"></td><td>' . ( 'new' === $s ? '' : '<input type="checkbox" name="hist[' . (int) $key . '][remove]" value="1" aria-label="Remove">' ) . '</td></tr>';
		}
		echo '</tbody></table><button class="bc-btn">Save seasons</button></form></div>';
	}
}

/**
 * Guests who checked in during a season and have since joined.
 *
 * @param int $season Season start year.
 */
function brra_guests_who_joined( $season ) {
	global $wpdb;
	if ( ! function_exists( 'brra_attendance_table' ) ) {
		return array( 0, 0 );
	}
	$ids = array();
	foreach ( brra_all_events() as $e ) {
		$d = brra_event_get( $e->ID, 'date' );
		if ( $d >= $season . '-06-01' && $d < ( $season + 1 ) . '-06-01' ) {
			$ids[] = (int) $e->ID;
		}
	}
	if ( ! $ids ) {
		return array( 0, 0 );
	}
	$t      = brra_attendance_table();
	$emails = $wpdb->get_col( "SELECT DISTINCT email FROM $t WHERE user_id = 0 AND email <> '' AND event_id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB
	$joined = 0;
	foreach ( $emails as $e ) {
		$joined += get_user_by( 'email', $e ) ? 1 : 0;
	}
	return array( $joined, count( $emails ) );
}

/**
 * Year-on-year line chart (SVG).
 *
 * @param int[]    $seasons Seasons, main first.
 * @param callable $label   Season label.
 */
function brra_stats_chart( $seasons, $label ) {
	$series = array();
	$max    = 10;
	foreach ( $seasons as $s ) {
		$d = brra_season_monthly( $s );
		if ( $d ) {
			$series[ $s ] = $d;
			$max          = max( $max, max( $d ) );
		}
	}
	if ( ! $series ) {
		return '<p class="bc-muted">No figures for these seasons yet.</p>';
	}
	$max    = (int) ( ceil( $max / 10 ) * 10 );
	$colors = array( '#050F1C', '#D4AF37', '#9AA6B5' );
	$w      = 600;
	$h      = 220;
	$svg    = '<svg viewBox="0 0 ' . ( $w + 40 ) . ' ' . ( $h + 30 ) . '" class="bc-chart" role="img" aria-label="Members by month for each season">';
	foreach ( array( 0, 0.5, 1 ) as $g ) {
		$y    = 10 + $h - $g * $h;
		$svg .= '<line x1="34" x2="' . ( $w + 34 ) . '" y1="' . $y . '" y2="' . $y . '" stroke="#E1E6ED"/><text x="28" y="' . ( $y + 4 ) . '" text-anchor="end" font-size="11" fill="#3E4C60">' . (int) round( $max * $g ) . '</text>';
	}
	$months = array( 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May' );
	foreach ( $months as $i => $mo ) {
		$svg .= '<text x="' . ( 34 + $i * ( $w / 11 ) ) . '" y="' . ( $h + 28 ) . '" text-anchor="middle" font-size="11" fill="#3E4C60">' . $mo . '</text>';
	}
	$legend = '<div class="bc-legend">';
	$i      = 0;
	foreach ( array_reverse( $series, true ) as $s => $d ) {
		$c   = $colors[ array_search( $s, array_keys( $series ), true ) ];
		$pts = array();
		foreach ( $d as $j => $v ) {
			$pts[] = round( 34 + $j * ( $w / 11 ), 1 ) . ',' . round( 10 + $h - ( $v / $max ) * $h, 1 );
		}
		$svg .= '<polyline fill="none" stroke="' . $c . '" stroke-width="' . ( 0 === array_search( $s, array_keys( $series ), true ) ? 3.5 : 2.5 ) . '" stroke-linejoin="round" points="' . implode( ' ', $pts ) . '"/>';
		$last = end( $pts );
		list( $lx, $ly ) = explode( ',', $last );
		$svg .= '<circle cx="' . $lx . '" cy="' . $ly . '" r="3.5" fill="' . $c . '"/>';
		++$i;
	}
	foreach ( $series as $s => $d ) {
		$c       = $colors[ array_search( $s, array_keys( $series ), true ) ];
		$legend .= '<span><i style="background:' . $c . '"></i>' . brra_e( $label( $s ) ) . ' (' . (int) end( $d ) . ')</span>';
	}
	return $svg . '</svg>' . $legend . '</div>';
}

/**
 * Counts by a field, most first.
 *
 * @param array  $members Members.
 * @param string $field   Field.
 */
function brra_stats_counts( $members, $field ) {
	$c = array();
	foreach ( $members as $m ) {
		$k       = '' === $m[ $field ] ? 'Not given' : $m[ $field ];
		$c[ $k ] = isset( $c[ $k ] ) ? $c[ $k ] + 1 : 1;
	}
	return $c;
}

/**
 * Bar chart of members by level, in level order.
 *
 * @param array  $members Members.
 * @param string $field   Field.
 */
function brra_stats_bars( $members, $field ) {
	$c = brra_stats_counts( $members, $field );
	if ( ! $c ) {
		return '<p class="bc-muted">No members.</p>';
	}
	$order = array( 'Trainee / not yet qualified' => 'Trainee', 'Level 7' => 'L7', 'Level 6' => 'L6', 'Level 5' => 'L5', 'Level 4' => 'L4', 'Level 3' => 'L3', 'Level 2' => 'L2', 'Level 1' => 'L1', 'Not refereeing (supporter)' => 'Not ref', 'Not given' => '?' );
	$max   = max( $c );
	$out   = '<div class="bc-vbars">';
	foreach ( $order as $k => $short ) {
		if ( ! isset( $c[ $k ] ) && in_array( $k, array( 'Not given', 'Level 1', 'Level 2' ), true ) ) {
			continue;
		}
		$n    = isset( $c[ $k ] ) ? $c[ $k ] : 0;
		$out .= '<div title="' . esc_attr( $k . ': ' . $n ) . '"><em>' . (int) $n . '</em><b style="height:' . (int) round( $n / $max * 100 ) . '%"></b><span>' . brra_e( $short ) . '</span></div>';
	}
	return $out . '</div>';
}

/**
 * Simple list of counts.
 *
 * @param array  $members Members.
 * @param string $field   Field.
 */
function brra_stats_list( $members, $field ) {
	$c = brra_stats_counts( $members, $field );
	arsort( $c );
	$out = '<table class="bc-table bc-tight">';
	foreach ( $c as $k => $n ) {
		$out .= '<tr><td>' . brra_e( $k ) . '</td><td class="bc-num-cell">' . (int) $n . '</td></tr>';
	}
	return $out . '</table>';
}

// ---------------------------------------------------------------- Committee and delegates.

/**
 * Who is on the committee, their positions, order and minute-taking delegates.
 */
function brra_c_view_people() {
	if ( ! brra_can_manage_committee() ) {
		echo '<p>Only the Chairman and Secretary can change the committee list.</p>';
		return;
	}
	$people   = brra_committee_people();
	$roles    = brra_committee_roles_list();
	$upcoming = array_filter(
		brra_meetings(),
		function ( $m ) {
			return in_array( $m['status'], array( 'draft', 'sent', 'open' ), true );
		}
	);
	brra_c_head( 'Admin · Chairman and Secretary only', 'Committee and delegates', '', 'Tick each person\'s positions and set the order they\'re listed in. A minute-taking delegate can build the agenda, open and close the meeting, take the minutes and upload files, either for one meeting or until you switch it off.' );
	echo brra_c_form( 'people' ) . '<div class="bc-card"><table class="bc-table bc-people"><thead><tr><th>Order</th><th>Name</th><th>Positions</th><th>Can manage minutes</th><th>Remove</th></tr></thead><tbody>'; // phpcs:ignore WordPress.Security.EscapeOutput
	foreach ( $people as $u ) {
		$mine = brra_user_committee_roles( $u->ID );
		$del  = (string) get_user_meta( $u->ID, 'brra_minutes_delegate', true );
		$p    = 'people[' . (int) $u->ID . ']';
		echo '<tr><td><input type="number" class="bc-order" name="' . esc_attr( $p ) . '[order]" value="' . (int) get_user_meta( $u->ID, 'brra_committee_order', true ) . '"></td><td><strong>' . brra_e( brra_c_name( $u->ID ) ) . '</strong><div class="bc-muted">' . brra_e( $u->user_email ) . '</div></td><td class="bc-roles">';
		foreach ( $roles as $k => $l ) {
			echo '<label class="bc-check"><input type="checkbox" name="' . esc_attr( $p ) . '[roles][]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $mine, true ), true, false ) . '> ' . brra_e( $l ) . '</label>';
		}
		echo '</td><td>';
		if ( array_intersect( $mine, array( 'chair', 'secretary' ) ) ) {
			echo brra_pill( 'Always', 'g' ) . '<input type="hidden" name="' . esc_attr( $p ) . '[delegate]" value="">';
		} else {
			echo '<select name="' . esc_attr( $p ) . '[delegate]"><option value="">No</option><option value="on"' . selected( $del, 'on', false ) . '>Yes, until switched off</option>';
			foreach ( $upcoming as $m ) {
				echo '<option value="' . (int) $m['id'] . '"' . selected( $del, (string) $m['id'], false ) . '>For ' . brra_e( brra_c_date( $m['date'], 'j M' ) ) . ' only</option>';
			}
			echo '</select>';
		}
		echo '</td><td>' . ( get_current_user_id() === $u->ID ? '' : '<input type="checkbox" name="' . esc_attr( $p ) . '[remove]" value="1" aria-label="Remove from committee">' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
	$ids = wp_list_pluck( $people, 'ID' );
	echo '<div class="bc-card"><h3>Add someone to the committee</h3><p class="bc-muted">They need an account on the website first (members have one already).</p><div class="bc-row"><select name="add_user"><option value="0">Choose a person</option>';
	foreach ( get_users( array( 'orderby' => 'display_name', 'fields' => array( 'ID', 'display_name' ) ) ) as $u ) {
		if ( ! in_array( (int) $u->ID, array_map( 'intval', $ids ), true ) ) {
			echo '<option value="' . (int) $u->ID . '">' . brra_e( brra_c_name( $u->ID ) ) . '</option>';
		}
	}
	echo '</select><select name="add_role">';
	foreach ( $roles as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, 'member', false ) . '>' . brra_e( $l ) . '</option>';
	}
	echo '</select></div></div><button class="bc-btn">Save changes</button></form>';
}
