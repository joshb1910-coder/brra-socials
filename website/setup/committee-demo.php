<?php
/**
 * Test site only: made-up committee area content so every screen has something to show.
 * Loaded from build.php when BRRA_DEMO is set. Never run on the real site.
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
update_option( 'brra_demo_committee', 1 );

// ---- Committee accounts (the admin login plays Josh). Test logins use the password "password".
$brra_admin = get_user_by( 'login', 'admin' );
$brra_admin = $brra_admin ? $brra_admin : get_userdata( 1 );
wp_update_user( array( 'ID' => $brra_admin->ID, 'first_name' => 'Josh', 'last_name' => 'Brammer', 'display_name' => 'Josh Brammer' ) );
update_user_meta( $brra_admin->ID, 'brra_committee_roles', array( 'secretary', 'media' ) );
update_user_meta( $brra_admin->ID, 'brra_committee_order', 2 );
$brra_cttee = array( 'Josh Brammer' => $brra_admin->ID );
foreach (
	array(
		array( 'aron', 'Aron', 'Connolly', array( 'chair' ), 1 ),
		array( 'norman', 'Norman', 'Rowlands', array( 'treasurer' ), 3 ),
		array( 'molly', 'Molly', 'Ashworth', array( 'membership' ), 4 ),
		array( 'kaan', 'Kaan', 'Hayes', array( 'member' ), 5 ),
		array( 'martin', 'Martin', 'Flynn', array( 'member' ), 6 ),
		array( 'ian', 'Ian', 'Claridge', array( 'member' ), 7 ),
	) as $brra_p
) {
	$brra_u = get_user_by( 'login', $brra_p[0] );
	$brra_id = $brra_u ? $brra_u->ID : wp_insert_user(
		array(
			'user_login'   => $brra_p[0],
			'user_pass'    => 'password',
			'user_email'   => $brra_p[0] . '.test@example.com',
			'first_name'   => $brra_p[1],
			'last_name'    => $brra_p[2],
			'display_name' => $brra_p[1] . ' ' . $brra_p[2],
			'role'         => 'subscriber',
		)
	);
	if ( is_wp_error( $brra_id ) ) {
		continue;
	}
	update_user_meta( $brra_id, 'brra_committee_roles', $brra_p[3] );
	update_user_meta( $brra_id, 'brra_committee_order', $brra_p[4] );
	$brra_cttee[ $brra_p[1] . ' ' . $brra_p[2] ] = $brra_id;
}
$brra_c = function ( $name ) use ( $brra_cttee ) {
	return isset( $brra_cttee[ $name ] ) ? (int) $brra_cttee[ $name ] : 0;
};

// ---- Test members for this season, so the stats have something to count.
$brra_levels   = array( 'Trainee / not yet qualified', 'Level 7', 'Level 7', 'Level 7', 'Level 6', 'Level 6', 'Level 6', 'Level 6', 'Level 5', 'Level 5', 'Level 5', 'Level 4', 'Level 4', 'Level 3', 'Level 2', 'Not refereeing (supporter)' );
$brra_counties = array( 'Manchester FA', 'Manchester FA', 'Manchester FA', 'Lancashire FA', 'Manchester FA', 'Lancashire FA', 'Cheshire FA', 'Not registered yet' );
$brra_roles    = array( 'Referee', 'Referee', 'Dual Role', 'Assistant Referee', 'Referee', 'Workforce', '' );
$brra_months   = array( '06', '06', '06', '06', '06', '07', '07', '07', '08', '08', '09', '09', '10', '11' );
$brra_season   = brra_current_season()[0];
$brra_past     = (array) get_option( 'brra_past_member_emails', array() );
$brra_mtable   = $wpdb->prefix . 'pmpro_memberships_users';
for ( $brra_i = 1; $brra_i <= 48; $brra_i++ ) {
	$brra_login = sprintf( 'testmember%02d', $brra_i );
	if ( get_user_by( 'login', $brra_login ) ) {
		continue;
	}
	$brra_email = $brra_login . '@example.com';
	$brra_uid   = wp_insert_user(
		array(
			'user_login'   => $brra_login,
			'user_pass'    => wp_generate_password(),
			'user_email'   => $brra_email,
			'first_name'   => 'Test',
			'last_name'    => sprintf( 'Member %02d', $brra_i ),
			'display_name' => sprintf( 'Test Member %02d', $brra_i ),
			'role'         => 'subscriber',
		)
	);
	if ( is_wp_error( $brra_uid ) ) {
		continue;
	}
	update_user_meta( $brra_uid, 'brra_level', $brra_levels[ ( $brra_i * 7 ) % count( $brra_levels ) ] );
	update_user_meta( $brra_uid, 'brra_county', $brra_counties[ ( $brra_i * 3 ) % count( $brra_counties ) ] );
	update_user_meta( $brra_uid, 'brra_role', $brra_roles[ ( $brra_i * 5 ) % count( $brra_roles ) ] );
	$brra_month = $brra_months[ ( $brra_i * 11 ) % count( $brra_months ) ];
	$brra_year  = (int) $brra_month >= 6 ? $brra_season : $brra_season + 1;
	$wpdb->insert(
		$brra_mtable,
		array(
			'user_id'         => $brra_uid,
			'membership_id'   => $brra_full,
			'code_id'         => 0,
			'initial_payment' => 20,
			'billing_amount'  => 0,
			'cycle_number'    => 0,
			'cycle_period'    => '',
			'billing_limit'   => 0,
			'trial_amount'    => 0,
			'trial_limit'     => 0,
			'status'          => 'active',
			'startdate'       => sprintf( '%d-%s-%02d 19:30:00', $brra_year, $brra_month, 1 + ( $brra_i * 13 ) % 27 ),
			'enddate'         => ( $brra_season + 1 ) . '-08-31 23:59:59',
		)
	);
	if ( $brra_i % 5 ) {
		$brra_past[] = $brra_email; // Most are returning members.
	}
}
update_option( 'brra_past_member_emails', array_values( array_unique( $brra_past ) ), false );

// ---- Seasons before the website (example figures).
update_option(
	'brra_membership_history',
	array(
		$brra_season - 1 => array( 'members' => 54, 'new' => 8, 'renewed' => 46, 'lapsed' => 10, 'avg' => 24, 'monthly' => array( 30, 41, 46, 49, 51, 52, 53, 53, 54, 54, 54, 54 ), 'note' => 'Example figures' ),
		$brra_season - 2 => array( 'members' => 56, 'new' => 11, 'renewed' => 45, 'avg' => 22, 'monthly' => array( 28, 40, 47, 50, 52, 53, 54, 55, 55, 56, 56, 56 ), 'note' => 'Example figures' ),
	),
	false
);

// ---- Finance (example balances).
$brra_fin = array();
foreach ( array( array( '06-30', 4410.20, 'Room hire for the AGM paid' ), array( '07-31', 4885.50, 'Renewals coming in' ), array( '08-31', 5212.00, 'Renewals' ), array( '09-30', 5245.00, '' ), array( '10-31', 5160.00, 'Website renewal' ), array( '11-20', 5312.40, 'Raffle float in, December room hire paid, 100 Club payout' ) ) as $brra_k => $brra_f ) {
	$brra_fin[] = array( 'id' => 'demo' . $brra_k, 'date' => $brra_season . '-' . $brra_f[0], 'balance' => $brra_f[1], 'notes' => $brra_f[2], 'by' => $brra_c( 'Norman Rowlands' ) );
}
update_option( 'brra_finance', $brra_fin, false );

// ---- 100 Club numbers and the November draw (made-up holders).
$brra_holders = array();
foreach ( array( 3, 8, 14, 19, 22, 27, 31, 36, 41, 45, 52, 58, 63, 70, 77 ) as $brra_k => $brra_n ) {
	$brra_holders[ $brra_n ] = sprintf( 'Test Member %02d', $brra_k + 1 );
}
update_option( 'brra_100club_holders', $brra_holders, false );
update_option( 'brra_100club_prizes', array( '40', '20', '10', '5' ), false );
$brra_nov = get_page_by_path( 'november-development-session-2026', OBJECT, 'brra_event' );
if ( $brra_nov ) {
	update_post_meta(
		$brra_nov->ID,
		'_brra_100club',
		array(
			'places' => array(
				array( 'num' => 27, 'holder' => $brra_holders[27], 'prize' => '40', 'paid' => 1 ),
				array( 'num' => 63, 'holder' => $brra_holders[63], 'prize' => '20', 'paid' => 0 ),
				array( 'num' => 14, 'holder' => $brra_holders[14], 'prize' => '10', 'paid' => 0 ),
				array( 'num' => 90, 'holder' => '', 'prize' => '5', 'paid' => 0 ),
			),
			'by'     => $brra_c( 'Norman Rowlands' ),
			'at'     => $brra_season . '-11-05 21:00:00',
		)
	);
}

// ---- Guest speakers and calendar dates.
update_option(
	'brra_speakers',
	array(
		'demo1' => array( 'date' => $brra_season . '-10-01', 'name' => 'James Mainwaring', 'topic' => 'Teamwork', 'status' => 'done', 'notes' => 'Our best social post of the month. Invite back.', 'by' => $brra_c( 'Martin Flynn' ) ),
		'demo2' => array( 'date' => $brra_season . '-11-05', 'name' => 'Peter Wright', 'topic' => 'What it takes to progress', 'status' => 'done', 'notes' => '', 'by' => $brra_c( 'Aron Connolly' ) ),
		'demo3' => array( 'date' => ( $brra_season + 1 ) . '-01-07', 'name' => '', 'topic' => '', 'status' => 'needed', 'notes' => 'Ask the County FA for a contact.', 'by' => $brra_admin->ID ),
	),
	false
);
update_option(
	'brra_calendar_extra',
	array(
		'demo1' => array( 'date' => $brra_season . '-12-31', 'title' => 'Ticket applications due', 'kind' => 'deadline' ),
		'demo2' => array( 'date' => ( $brra_season + 1 ) . '-04-01', 'title' => 'Nominations to the Secretary', 'kind' => 'deadline' ),
		'demo3' => array( 'date' => ( $brra_season + 1 ) . '-05-01', 'title' => 'Rule changes due', 'kind' => 'deadline' ),
	),
	false
);
update_option(
	'brra_aob',
	array( 'demo1' => array( 'title' => 'Referee kit discount offer', 'by' => $brra_c( 'Kaan Hayes' ), 'at' => $brra_season . '-11-20 18:00:00', 'meeting' => 0 ) ),
	false
);

// ---- Meetings: 15 October (approved), 12 November (closed, minutes draft) and 10 December (agenda).
foreach ( get_posts( array( 'post_type' => 'brra_meeting', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $brra_old ) {
	wp_delete_post( $brra_old, true );
}
$brra_people_ids = array_values( $brra_cttee );
$brra_make       = function ( $date, $status, $setup ) use ( $brra_c, $brra_people_ids ) {
	$id         = wp_insert_post( array( 'post_type' => 'brra_meeting', 'post_status' => 'private', 'post_title' => 'Committee meeting ' . $date ) );
	$m          = brra_meeting( $id );
	$m['date']  = $date;
	$m['time']  = '20:00';
	$m['chair'] = $brra_c( 'Aron Connolly' );
	foreach ( brra_agenda_template() as $t ) {
		$m['items'][] = brra_new_item( $t[0], $t[1], $t[2] );
	}
	$m['status'] = $status;
	$m           = $setup( $m );
	brra_meeting_save( $m );
	return $m;
};
$brra_find = function ( &$m, $kind, $report = '' ) {
	foreach ( $m['items'] as $i => $it ) {
		if ( $it['kind'] === $kind && ( ! $report || $it['report'] === $report ) ) {
			return $i;
		}
	}
	return 0;
};
$brra_make(
	$brra_season . '-10-15',
	'approved',
	function ( $m ) use ( $brra_c, $brra_admin ) {
		$m['opened']   = '20:03';
		$m['closed']   = '21:20';
		$m['minuter']  = $brra_admin->ID;
		$m['approved'] = '';
		foreach ( array( 'Aron Connolly', 'Norman Rowlands', 'Molly Ashworth', 'Kaan Hayes', 'Martin Flynn', 'Ian Claridge' ) as $n ) {
			$m['att'][ $brra_c( $n ) ] = 'present';
		}
		$m['att'][ $brra_admin->ID ] = 'present';
		$m['reports']['media']       = array( 'text' => '', 'data' => array( 'instagram' => array( 'posts' => '20', 'views' => '2441', 'likes' => '75', 'followers' => '398' ), 'facebook' => array( 'posts' => '19', 'views' => '1157', 'likes' => '44', 'followers' => '681' ), 'x' => array( 'posts' => '19', 'views' => '1179', 'likes' => '5', 'followers' => '300' ), 'web' => array( 'visits' => '200', 'forms' => '2' ) ), 'by' => $brra_admin->ID, 'at' => '', 'submitted' => 1 );
		return $m;
	}
);
$brra_nov_m = $brra_make(
	$brra_season . '-11-12',
	'closed',
	function ( $m ) use ( $brra_c, $brra_find, $brra_admin, $brra_season ) {
		$m['opened']  = '20:04';
		$m['closed']  = '21:12';
		$m['minuter'] = $brra_admin->ID;
		foreach ( array( 'Aron Connolly', 'Norman Rowlands', 'Kaan Hayes', 'Martin Flynn' ) as $n ) {
			$m['att'][ $brra_c( $n ) ] = 'present';
		}
		$m['att'][ $brra_admin->ID ]           = 'present';
		$m['att'][ $brra_c( 'Molly Ashworth' ) ] = 'apologies';
		$m['att'][ $brra_c( 'Ian Claridge' ) ]   = 'apologies';
		$i                                     = $brra_find( $m, 'minutes' );
		$m['items'][ $i ]['title']             = 'Minutes of the meeting held on Thursday 15th October 2026';
		$m['items'][ $i ]['f']                 = array( 'proposed' => $brra_c( 'Martin Flynn' ), 'seconded' => $brra_c( 'Kaan Hayes' ), 'outcome' => 'yes', 'amendments' => '' );
		$i                                     = $brra_find( $m, 'report', 'secretary' );
		$m['items'][ $i ]['notes']             = 'Example: website move to WordPress on track for December. Committee area being built.';
		$m['items'][ $i ]['actions'][]         = array( 'id' => 'demoa1', 'text' => 'Send Christmas Social poster to St Mary\'s', 'owner' => $brra_admin->ID, 'due' => $brra_season . '-12-01', 'done' => 0 );
		$m['items'][ $i ]['actions'][]         = array( 'id' => 'demoa2', 'text' => 'Update the rules page with the AGM changes', 'owner' => $brra_admin->ID, 'due' => $brra_season . '-11-19', 'done' => 0 );
		$i                                     = $brra_find( $m, 'report', 'treasurer' );
		$m['items'][ $i ]['notes']             = 'Example: website renewal paid in October.';
		$m['items'][ $i ]['actions'][]         = array( 'id' => 'demoa3', 'text' => 'Ask the venue for 2027 room rates', 'owner' => $brra_c( 'Norman Rowlands' ), 'due' => ( $brra_season + 1 ) . '-01-07', 'done' => 0 );
		$i                                     = $brra_find( $m, 'aob' );
		$m['items'][ $i ]['subs'][]            = array( 'title' => 'T-shirts', 'notes' => 'Example: sizes still to collect.' );
		$m['items'][ $i ]['actions'][]         = array( 'id' => 'demoa4', 'text' => 'Collect final T-shirt sizes', 'owner' => $brra_c( 'Aron Connolly' ), 'due' => $brra_season . '-12-10', 'done' => 0 );
		$i                                     = $brra_find( $m, 'report', 'mds' );
		$m['items'][ $i ]['actions'][]         = array( 'id' => 'demoa5', 'text' => 'Book a guest speaker for January', 'owner' => $brra_c( 'Martin Flynn' ), 'due' => $brra_season . '-12-10', 'done' => 0 );
		$i                                     = $brra_find( $m, 'next' );
		$m['items'][ $i ]['f']                 = array( 'next' => 'Thursday 10th December 2026, 20:00, St Mary\'s Social Club, Radcliffe' );
		$m['reports']['media']                 = array( 'text' => 'Example summary.', 'data' => array( 'instagram' => array( 'posts' => '21', 'views' => '2610', 'likes' => '82', 'followers' => '405' ), 'facebook' => array( 'posts' => '20', 'views' => '1210', 'likes' => '47', 'followers' => '684' ), 'x' => array( 'posts' => '19', 'views' => '1102', 'likes' => '6', 'followers' => '300' ), 'web' => array( 'visits' => '231', 'forms' => '3' ), 'best' => 'Peter Wright announcement' ), 'by' => $brra_admin->ID, 'at' => '', 'submitted' => 1 );
		return $m;
	}
);

// December: built the same way as "Add a meeting", so open actions carry forward.
$brra_dec_id = wp_insert_post( array( 'post_type' => 'brra_meeting', 'post_status' => 'private', 'post_title' => 'Committee meeting ' . $brra_season . '-12-10' ) );
$brra_dec    = brra_meeting( $brra_dec_id );
$brra_dec['date']  = $brra_season . '-12-10';
$brra_dec['chair'] = $brra_c( 'Aron Connolly' );
foreach ( brra_agenda_template() as $brra_t ) {
	$brra_item = brra_new_item( $brra_t[0], $brra_t[1], $brra_t[2] );
	if ( 'minutes' === $brra_t[1] ) {
		$brra_item['title'] = 'Minutes of the meeting held on Thursday 12th November 2026';
	}
	if ( 'arising' === $brra_t[1] ) {
		foreach ( brra_all_actions( true ) as $brra_a ) {
			$brra_item['subs'][] = array( 'title' => $brra_a['text'] . ' (' . brra_c_name( $brra_a['owner'] ) . ')', 'notes' => '', 'action' => $brra_a['id'] );
		}
	}
	$brra_dec['items'][] = $brra_item;
}
$brra_dec['reports']['secretary'] = array( 'text' => "Example report.\n\nCorrespondence: a letter from the County FA about next season's ticket applications (deadline 31 December).\n\nWebsite: the new site is ready to move to our own hosting.", 'data' => array(), 'by' => $brra_admin->ID, 'at' => current_time( 'mysql' ), 'submitted' => 1 );
$brra_dec['reports']['treasurer'] = array( 'text' => 'Example: nothing unusual this month. 100 Club 2nd and 3rd prizes still to pay.', 'data' => array(), 'by' => $brra_c( 'Norman Rowlands' ), 'at' => current_time( 'mysql' ), 'submitted' => 1 );
brra_meeting_save( $brra_dec );
