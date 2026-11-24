<?php
/**
 * Committee area: expenses and business card spend, and the season budget.
 *
 * Options: brra_expenses (id => entry), brra_expense_cards (user ID => last four digits),
 * brra_budget (season start year => areas, who set it and when).
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------- Who can do what.

/**
 * Can see everyone's expenses and receipts: Treasurer, Secretary, Chairman (and site admins).
 *
 * @param int|null $uid User.
 */
function brra_can_see_all_expenses( $uid = null ) {
	$uid = brra_c_user( $uid );
	return $uid && ( brra_can_manage_finance( $uid ) || brra_has_committee_role( 'secretary', $uid ) );
}

/**
 * Can approve claims, mark them paid and tick card payments off the statement: Treasurer and Chairman.
 *
 * @param int|null $uid User.
 */
function brra_can_approve_expenses( $uid = null ) {
	return brra_can_manage_finance( $uid );
}

/**
 * Can change the budget: Treasurer, Secretary and Chairman.
 *
 * @param int|null $uid User.
 */
function brra_can_edit_budget( $uid = null ) {
	return brra_can_see_all_expenses( $uid );
}

// ---------------------------------------------------------------- Data.

/**
 * Business debit cards: user ID => last four digits.
 */
function brra_expense_cards() {
	$c = get_option( 'brra_expense_cards', array() );
	return is_array( $c ) ? $c : array();
}

/**
 * Starting list of budget areas for a new season.
 */
function brra_budget_default_areas() {
	return array(
		array( 'key' => 'room', 'name' => 'Room hire', 'note' => 'St Mary\'s, season', 'amount' => 0 ),
		array( 'key' => 'refreshments', 'name' => 'MDS refreshments', 'note' => 'Food on MDS nights', 'amount' => 0 ),
		array( 'key' => 'speakers', 'name' => 'Guest speakers', 'note' => 'Petrol and thank-you gifts', 'amount' => 0 ),
		array( 'key' => 'christmas', 'name' => 'Christmas social', 'note' => 'Raffle prizes and buffet extras', 'amount' => 0 ),
		array( 'key' => 'newrefs', 'name' => 'New referee evenings', 'note' => 'Room, food and guest expenses', 'amount' => 0 ),
		array( 'key' => 'web', 'name' => 'Website and email', 'note' => 'Hosting and email', 'amount' => 0 ),
		array( 'key' => 'kit', 'name' => 'Kit and printing', 'note' => 'Signs, posters, lanyards', 'amount' => 0 ),
		array( 'key' => 'admin', 'name' => 'Admin and postage', 'note' => '', 'amount' => 0 ),
		array( 'key' => 'contingency', 'name' => 'Contingency', 'note' => '', 'amount' => 0 ),
	);
}

/**
 * The budget set for a season, or null.
 *
 * @param int $season Season start year.
 */
function brra_budget( $season ) {
	$all = get_option( 'brra_budget', array() );
	return is_array( $all ) && ! empty( $all[ $season ]['areas'] ) ? $all[ $season ] : null;
}

/**
 * Budget areas for a season as key => name (the budget's own, or the starting list).
 *
 * @param int $season Season start year.
 */
function brra_budget_areas( $season ) {
	$b     = brra_budget( $season );
	$areas = $b ? $b['areas'] : brra_budget_default_areas();
	$out   = array();
	foreach ( $areas as $a ) {
		$out[ $a['key'] ] = $a['name'];
	}
	return $out;
}

/**
 * Every expense, newest first.
 */
function brra_expenses() {
	$e = get_option( 'brra_expenses', array() );
	$e = is_array( $e ) ? $e : array();
	uasort(
		$e,
		function ( $a, $b ) {
			return strcmp( $b['date'] . $b['at'], $a['date'] . $a['at'] );
		}
	);
	return $e;
}

/**
 * The season an expense falls in.
 *
 * @param array $e Expense.
 */
function brra_expense_season( $e ) {
	return (int) brra_current_season( strtotime( $e['date'] . ' 12:00' ) )[0];
}

/**
 * Expenses in a season.
 *
 * @param int $season Season start year.
 */
function brra_expenses_in( $season ) {
	return array_filter(
		brra_expenses(),
		function ( $e ) use ( $season ) {
			return brra_expense_season( $e ) === (int) $season;
		}
	);
}

/**
 * Whose money it was: the claimant, or the card holder.
 *
 * @param array $e Expense.
 */
function brra_expense_person( $e ) {
	return 'card' === $e['type'] ? (int) $e['card'] : (int) $e['by'];
}

/**
 * Does this count as spent? Card payments always; claims once approved.
 *
 * @param array $e Expense.
 */
function brra_expense_counts( $e ) {
	return 'card' === $e['type'] || in_array( $e['status'], array( 'approved', 'paid' ), true );
}

/**
 * Still needs something doing?
 *
 * @param array $e Expense.
 */
function brra_expense_outstanding( $e ) {
	if ( 'card' === $e['type'] ) {
		return empty( $e['receipt'] ) || 'checked' !== $e['status'];
	}
	return 'paid' !== $e['status'];
}

/**
 * Status in words, with a colour.
 *
 * @param array $e Expense.
 */
function brra_expense_status( $e ) {
	if ( 'card' === $e['type'] ) {
		if ( empty( $e['receipt'] ) ) {
			return array( 'Needs receipt', 'r' );
		}
		return 'checked' === $e['status'] ? array( 'On statement ✓', 'g' ) : array( 'Not on statement yet', 'a' );
	}
	$l = array(
		'pending'  => array( 'To approve', 'a' ),
		'queried'  => array( 'Queried', 'r' ),
		'approved' => array( 'Approved, to pay back', 'g' ),
		'paid'     => array( 'Paid back' . ( ! empty( $e['paid_at'] ) ? ' ' . mysql2date( 'j M', $e['paid_at'] ) : '' ), 'g' ),
	);
	return isset( $l[ $e['status'] ] ) ? $l[ $e['status'] ] : array( $e['status'], '' );
}

/**
 * Can this person see this expense?
 *
 * @param array $e Expense.
 */
function brra_can_see_expense( $e ) {
	return brra_can_see_all_expenses() || brra_expense_person( $e ) === get_current_user_id() || (int) $e['by'] === get_current_user_id();
}

/**
 * Save the posted receipt file, if any. Returns array( stored, original ) or null.
 */
function brra_expense_store_receipt() {
	if ( empty( $_FILES['receipt']['name'] ) || UPLOAD_ERR_OK !== (int) $_FILES['receipt']['error'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return null;
	}
	$file  = $_FILES['receipt']; // phpcs:ignore WordPress.Security
	$check = wp_check_filetype_and_ext(
		$file['tmp_name'],
		$file['name'],
		array(
			'pdf'  => 'application/pdf',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'heic' => 'image/heic',
			'webp' => 'image/webp',
		)
	);
	if ( ! $check['ext'] ) {
		return false;
	}
	$stored = 'r-' . wp_generate_password( 20, false ) . '.' . $check['ext'];
	if ( ! move_uploaded_file( $file['tmp_name'], brra_files_dir() . '/' . $stored ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions
		return false;
	}
	return array( $stored, sanitize_file_name( wp_unslash( $file['name'] ) ) );
}

/**
 * Save the list of expenses.
 *
 * @param array $all Expenses.
 */
function brra_expenses_save( $all ) {
	update_option( 'brra_expenses', $all, false );
}

/**
 * The expense named in the request, or stop.
 */
function brra_expense_from_request() {
	$all = brra_expenses();
	$id  = sanitize_key( brra_c_in( 'eid' ) );
	if ( ! isset( $all[ $id ] ) || ! brra_can_see_expense( $all[ $id ] ) ) {
		brra_c_die();
	}
	return array( $all, $id );
}

/**
 * Back to the expenses page, keeping the filters.
 *
 * @param string $notice Message key.
 */
function brra_expense_back( $notice = 'saved' ) {
	$args = array();
	foreach ( array( 'season', 'who', 'area', 'show' ) as $k ) {
		if ( '' !== brra_c_in( 'f_' . $k ) ) {
			$args[ $k ] = brra_c_in( 'f_' . $k );
		}
	}
	brra_c_back( 'expenses', $args, $notice );
}

// ---------------------------------------------------------------- Actions.

/**
 * Add a claim or a card payment.
 */
function brra_c_do_expense_add() {
	if ( ! brra_committee_can_edit() ) {
		brra_c_die();
	}
	$uid    = get_current_user_id();
	$type   = 'card' === brra_c_in( 'type' ) ? 'card' : 'claim';
	$cards  = brra_expense_cards();
	$card   = absint( brra_c_in( 'card' ) );
	$date   = brra_c_in( 'date' );
	$amount = round( (float) str_replace( array( '£', ',', ' ' ), '', brra_c_in( 'amount' ) ), 2 );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || $amount <= 0 || '' === brra_c_in( 'what' ) ) {
		brra_expense_back( 'badexpense' );
	}
	if ( 'card' === $type && ( ! isset( $cards[ $card ] ) || ( $card !== $uid && ! brra_can_see_all_expenses() ) ) ) {
		brra_c_die( 'Choose one of the business cards.' );
	}
	$receipt = brra_expense_store_receipt();
	if ( false === $receipt ) {
		brra_expense_back( 'badreceipt' );
	}
	$all        = brra_expenses();
	$id         = strtolower( wp_generate_password( 10, false ) );
	$all[ $id ] = array(
		'id'      => $id,
		'type'    => $type,
		'card'    => 'card' === $type ? $card : 0,
		'date'    => $date,
		'amount'  => $amount,
		'what'    => brra_c_in( 'what' ),
		'area'    => sanitize_key( brra_c_in( 'area' ) ),
		'receipt' => $receipt ? $receipt[0] : '',
		'orig'    => $receipt ? $receipt[1] : '',
		'by'      => $uid,
		'at'      => current_time( 'mysql' ),
		'status'  => 'card' === $type ? 'unchecked' : 'pending',
		'note'    => '',
	);
	brra_expenses_save( $all );
	brra_expense_back( 'expense' );
}

/**
 * Add a receipt to an entry that was saved without one.
 */
function brra_c_do_expense_receipt() {
	list( $all, $id ) = brra_expense_from_request();
	$receipt          = brra_expense_store_receipt();
	if ( ! $receipt ) {
		brra_expense_back( 'badreceipt' );
	}
	$all[ $id ]['receipt'] = $receipt[0];
	$all[ $id ]['orig']    = $receipt[1];
	if ( 'queried' === $all[ $id ]['status'] ) {
		$all[ $id ]['status'] = 'pending';
	}
	brra_expenses_save( $all );
	brra_expense_back();
}

/**
 * Treasurer's buttons: approve, paid back, on the statement, query.
 */
function brra_c_do_expense_set() {
	if ( ! brra_can_approve_expenses() ) {
		brra_c_die();
	}
	list( $all, $id ) = brra_expense_from_request();
	$e                = $all[ $id ];
	$set              = brra_c_in( 'set' );
	$mine             = brra_expense_person( $e ) === get_current_user_id() && ! current_user_can( 'manage_options' );
	if ( $mine && in_array( $set, array( 'approve', 'paid' ), true ) ) {
		brra_c_die( 'Someone else needs to approve your own claim.' );
	}
	switch ( $set ) {
		case 'approve':
			$e['status']      = 'approved';
			$e['approved_by'] = get_current_user_id();
			break;
		case 'paid':
			$e['status']  = 'paid';
			$e['paid_at'] = current_time( 'mysql' );
			break;
		case 'check':
			$e['status']     = 'checked' === $e['status'] ? 'unchecked' : 'checked';
			$e['checked_at'] = current_time( 'mysql' );
			break;
		case 'query':
			$e['status'] = 'queried';
			$e['note']   = brra_c_in( 'note', '', true );
			$who         = get_userdata( brra_expense_person( $e ) );
			if ( $who ) {
				wp_mail( $who->user_email, 'Question about your expense: ' . $e['what'], 'Hi ' . $who->first_name . ",\n\n" . brra_c_name( get_current_user_id() ) . ' has a question about ' . $e['what'] . ' (' . brra_money( $e['amount'] ) . ', ' . brra_c_date( $e['date'], 'j M Y' ) . "):\n\n" . $e['note'] . "\n\n" . brra_c_url( 'expenses' ) );
			}
			break;
	}
	$all[ $id ] = $e;
	brra_expenses_save( $all );
	brra_expense_back();
}

/**
 * Ask a card holder for a missing receipt.
 */
function brra_c_do_expense_remind() {
	if ( ! brra_can_approve_expenses() ) {
		brra_c_die();
	}
	list( $all, $id ) = brra_expense_from_request();
	$e                = $all[ $id ];
	$who              = get_userdata( brra_expense_person( $e ) );
	if ( $who ) {
		wp_mail( $who->user_email, 'Receipt needed: ' . $e['what'], 'Hi ' . $who->first_name . ",\n\nPlease add the receipt for " . $e['what'] . ' (' . brra_money( $e['amount'] ) . ' on ' . brra_c_date( $e['date'], 'j M Y' ) . ") in the committee area, so Norman has proof of spend for the bank:\n\n" . brra_c_url( 'expenses' ) . "\n\nThanks,\n" . brra_c_name( get_current_user_id() ) );
	}
	$all[ $id ]['reminded'] = current_time( 'mysql' );
	brra_expenses_save( $all );
	brra_expense_back( 'reminded' );
}

/**
 * Delete an entry: the person who added it while it's still open, or the Treasurer or Chairman.
 */
function brra_c_do_expense_delete() {
	list( $all, $id ) = brra_expense_from_request();
	$e                = $all[ $id ];
	$own_open         = (int) $e['by'] === get_current_user_id() && in_array( $e['status'], array( 'pending', 'queried', 'unchecked' ), true );
	if ( ! $own_open && ! brra_can_approve_expenses() ) {
		brra_c_die();
	}
	if ( $e['receipt'] && file_exists( brra_files_dir() . '/' . basename( $e['receipt'] ) ) ) {
		wp_delete_file( brra_files_dir() . '/' . basename( $e['receipt'] ) );
	}
	unset( $all[ $id ] );
	brra_expenses_save( $all );
	brra_expense_back( 'deleted' );
}

/**
 * Open a receipt.
 */
function brra_c_do_expense_file() {
	list( $all, $id ) = brra_expense_from_request();
	$e                = $all[ $id ];
	$path             = brra_files_dir() . '/' . basename( (string) $e['receipt'] );
	if ( ! $e['receipt'] || ! file_exists( $path ) ) {
		brra_c_die( 'That receipt is missing.' );
	}
	$type = wp_check_filetype( $e['orig'] ? $e['orig'] : $e['receipt'] );
	nocache_headers();
	header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
	header( 'Content-Disposition: inline; filename="' . str_replace( '"', '', $e['orig'] ? $e['orig'] : $e['receipt'] ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/**
 * Who holds a business debit card.
 */
function brra_c_do_expense_cards() {
	if ( ! brra_can_edit_budget() ) {
		brra_c_die();
	}
	$cards = array();
	// phpcs:ignore WordPress.Security.NonceVerification
	$rows = isset( $_POST['cards'] ) && is_array( $_POST['cards'] ) ? wp_unslash( $_POST['cards'] ) : array();
	foreach ( $rows as $r ) {
		$uid  = isset( $r['uid'] ) ? absint( $r['uid'] ) : 0;
		$last = isset( $r['last'] ) ? preg_replace( '/\D/', '', (string) $r['last'] ) : '';
		if ( $uid && empty( $r['remove'] ) ) {
			$cards[ $uid ] = substr( $last, -4 );
		}
	}
	update_option( 'brra_expense_cards', $cards, false );
	brra_expense_back();
}

/**
 * The season's expenses as a spreadsheet for the income and expenditure account.
 */
function brra_c_do_expenses_csv() {
	if ( ! brra_can_see_all_expenses() ) {
		brra_c_die();
	}
	$season = brra_c_season_in();
	$areas  = brra_budget_areas( $season );
	$cards  = brra_expense_cards();
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="brra-expenses-' . $season . '-' . substr( (string) ( $season + 1 ), 2 ) . '.csv"' );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fputcsv( $out, array( 'Date', 'Type', 'Who', 'Card', 'What', 'Budget area', 'Amount (£)', 'Status', 'Paid back / checked on', 'Receipt' ) );
	foreach ( array_reverse( brra_expenses_in( $season ) ) as $e ) {
		$st   = brra_expense_status( $e );
		$when = 'card' === $e['type'] ? ( 'checked' === $e['status'] && ! empty( $e['checked_at'] ) ? substr( $e['checked_at'], 0, 10 ) : '' ) : ( ! empty( $e['paid_at'] ) ? substr( $e['paid_at'], 0, 10 ) : '' );
		fputcsv(
			$out,
			array(
				$e['date'],
				'card' === $e['type'] ? 'Business card' : 'Claim',
				brra_c_name( brra_expense_person( $e ) ),
				'card' === $e['type'] && isset( $cards[ $e['card'] ] ) ? 'ending ' . $cards[ $e['card'] ] : '',
				$e['what'],
				isset( $areas[ $e['area'] ] ) ? $areas[ $e['area'] ] : 'Other',
				number_format( (float) $e['amount'], 2, '.', '' ),
				str_replace( ' ✓', '', $st[0] ),
				$when,
				$e['receipt'] ? 'Yes' : 'Missing',
			)
		);
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/**
 * Every receipt for the season in one zip.
 */
function brra_c_do_expenses_zip() {
	if ( ! brra_can_see_all_expenses() || ! class_exists( 'ZipArchive' ) ) {
		brra_c_die();
	}
	$season = brra_c_season_in();
	$tmp    = wp_tempnam( 'brra-receipts' );
	$zip    = new ZipArchive();
	$zip->open( $tmp, ZipArchive::OVERWRITE );
	foreach ( array_reverse( brra_expenses_in( $season ) ) as $e ) {
		$path = brra_files_dir() . '/' . basename( (string) $e['receipt'] );
		if ( $e['receipt'] && file_exists( $path ) ) {
			$ext = pathinfo( $e['receipt'], PATHINFO_EXTENSION );
			$zip->addFile( $path, $e['date'] . ' ' . sanitize_file_name( $e['what'] ) . ' ' . number_format( (float) $e['amount'], 2 ) . '.' . $ext );
		}
	}
	$zip->close();
	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="brra-receipts-' . $season . '-' . substr( (string) ( $season + 1 ), 2 ) . '.zip"' );
	header( 'Content-Length: ' . filesize( $tmp ) );
	readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	wp_delete_file( $tmp );
	exit;
}

/**
 * Save the budget for a season.
 */
function brra_c_do_budget() {
	if ( ! brra_can_edit_budget() ) {
		brra_c_die();
	}
	$season = brra_c_season_in();
	$all    = get_option( 'brra_budget', array() );
	$all    = is_array( $all ) ? $all : array();
	// phpcs:ignore WordPress.Security.NonceVerification
	$rows  = isset( $_POST['areas'] ) && is_array( $_POST['areas'] ) ? wp_unslash( $_POST['areas'] ) : array();
	$areas = array();
	$keys  = array();
	foreach ( $rows as $r ) {
		$name = isset( $r['name'] ) ? sanitize_text_field( $r['name'] ) : '';
		if ( '' === $name || ! empty( $r['remove'] ) ) {
			continue;
		}
		$key = ! empty( $r['key'] ) ? sanitize_key( $r['key'] ) : sanitize_key( sanitize_title( $name ) );
		$key = $key ? $key : 'area' . count( $areas );
		while ( isset( $keys[ $key ] ) ) {
			$key .= 'x';
		}
		$keys[ $key ] = true;
		$areas[]      = array(
			'key'    => $key,
			'name'   => $name,
			'note'   => isset( $r['note'] ) ? sanitize_text_field( $r['note'] ) : '',
			'amount' => round( (float) str_replace( array( '£', ',', ' ' ), '', isset( $r['amount'] ) ? $r['amount'] : '0' ), 2 ),
		);
	}
	$all[ $season ] = array(
		'areas' => $areas,
		'by'    => get_current_user_id(),
		'at'    => current_time( 'mysql' ),
	);
	update_option( 'brra_budget', $all, false );
	brra_c_back( 'budget', array( 'season' => $season ) );
}

// ---------------------------------------------------------------- Screens.

/**
 * A season picker that reloads the page.
 *
 * @param string $view    Screen.
 * @param int    $season  Selected season.
 * @param int[]  $seasons Seasons to offer.
 * @param array  $keep    Other query args to keep.
 */
function brra_c_season_picker( $view, $season, $seasons, $keep = array() ) {
	$seasons[] = $season;
	$seasons[] = (int) brra_current_season()[0];
	$seasons   = array_unique( array_map( 'intval', $seasons ) );
	rsort( $seasons );
	$out = '<form method="get" class="bc-season-pick"><input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
	foreach ( $keep as $k => $v ) {
		$out .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	$out .= '<label><span class="screen-reader-text">Season</span><select name="season" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">';
	foreach ( $seasons as $s ) {
		$out .= '<option value="' . (int) $s . '"' . selected( $season, $s, false ) . '>Season ' . brra_e( brra_season_label( $s ) ) . ( (int) brra_current_season()[0] === $s ? ' (now)' : '' ) . '</option>';
	}
	return $out . '</select></label><noscript><button class="bc-btn is-sm">Show</button></noscript></form>';
}

/**
 * Expenses and card spend.
 */
function brra_c_view_expenses() {
	$uid     = get_current_user_id();
	$all_see = brra_can_see_all_expenses();
	$approve = brra_can_approve_expenses();
	$season  = brra_c_season_in();
	$who     = absint( brra_c_in( 'who' ) );
	$area    = sanitize_key( brra_c_in( 'area' ) );
	$show    = 'all' === brra_c_in( 'show' ) ? 'all' : 'open';
	$areas   = brra_budget_areas( $season );
	$cards   = brra_expense_cards();
	$list    = array_filter( brra_expenses_in( $season ), 'brra_can_see_expense' );
	$seasons = array();
	foreach ( brra_expenses() as $e ) {
		$seasons[] = brra_expense_season( $e );
	}

	$right = brra_c_season_picker( 'expenses', $season, $seasons );
	if ( $all_see ) {
		$right .= '<a class="bc-btn is-ghost bc-dl" href="' . esc_url( brra_c_action_url( 'expenses_csv', array( 'season' => $season ) ) ) . '">Download for the I&amp;E (Excel)</a>';
		if ( class_exists( 'ZipArchive' ) ) {
			$right .= '<a class="bc-btn is-ghost bc-dl" href="' . esc_url( brra_c_action_url( 'expenses_zip', array( 'season' => $season ) ) ) . '">Download receipts (zip)</a>';
		}
	}
	brra_c_head( 'Finance', 'Expenses and card spend', $right, $all_see ? 'Claim back anything you paid for yourself, and log every business debit card payment with its receipt. The Treasurer approves claims and ticks card payments off against the bank statement.' : 'Claim back anything you paid for the RA yourself. Add the receipt, and the Treasurer will approve it and pay you back.' );

	// Summary cards.
	$sum = function ( $rows ) {
		return array_sum( wp_list_pluck( $rows, 'amount' ) );
	};
	$by  = function ( $test ) use ( $list ) {
		return array_filter( $list, $test );
	};
	if ( $all_see ) {
		$to_approve = $by(
			function ( $e ) {
				return 'claim' === $e['type'] && 'pending' === $e['status'];
			}
		);
		$to_pay     = $by(
			function ( $e ) {
				return 'claim' === $e['type'] && 'approved' === $e['status'];
			}
		);
		$no_receipt = $by(
			function ( $e ) {
				return 'card' === $e['type'] && empty( $e['receipt'] );
			}
		);
		$spent      = $by( 'brra_expense_counts' );
		$names      = array_unique( array_map( 'brra_c_name', array_map( 'brra_expense_person', $to_pay ) ) );
		$first_nr   = $no_receipt ? end( $no_receipt ) : null;
		echo '<div class="bc-grid bc-g4">';
		echo '<div class="bc-card"><div class="bc-muted">Claims to approve</div><div class="bc-big">' . count( $to_approve ) . '</div><div class="bc-muted">' . ( $to_approve ? brra_e( brra_money( $sum( $to_approve ) ) ) . ' in total' : 'Nothing waiting' ) . '</div></div>';
		echo '<div class="bc-card"><div class="bc-muted">Approved, to pay back</div><div class="bc-big">' . count( $to_pay ) . '</div><div class="bc-muted">' . ( $to_pay ? brra_e( brra_money( $sum( $to_pay ) ) . ' to ' . implode( ', ', $names ) ) : 'All paid' ) . '</div></div>';
		echo '<div class="bc-card' . ( $no_receipt ? ' is-alert' : '' ) . '"><div class="bc-muted">Card payments with no receipt</div><div class="bc-big">' . count( $no_receipt ) . '</div><div class="bc-muted">' . ( $first_nr ? brra_e( brra_c_name( brra_expense_person( $first_nr ) ) . ', ' . brra_c_date( $first_nr['date'], 'j M' ) ) . ( count( $no_receipt ) > 1 ? ' and others' : '' ) : 'Every card payment has proof' ) . '</div></div>';
		echo '<div class="bc-card"><div class="bc-muted">Spent this season</div><div class="bc-big">' . brra_e( brra_money( $sum( $spent ) ) ) . '</div><div class="bc-muted">since 1 June ' . (int) $season . ' · <a href="' . esc_url( brra_c_url( 'budget', array( 'season' => $season ) ) ) . '">see the budget</a></div></div>';
		echo '</div>';
	} else {
		$mine_open = $by(
			function ( $e ) {
				return 'claim' === $e['type'] && in_array( $e['status'], array( 'pending', 'queried', 'approved' ), true );
			}
		);
		$mine_paid = $by(
			function ( $e ) {
				return 'claim' === $e['type'] && 'paid' === $e['status'];
			}
		);
		echo '<div class="bc-grid bc-g3"><div class="bc-card"><div class="bc-muted">Your claims waiting</div><div class="bc-big">' . count( $mine_open ) . '</div><div class="bc-muted">' . brra_e( brra_money( $sum( $mine_open ) ) ) . '</div></div><div class="bc-card"><div class="bc-muted">Paid back this season</div><div class="bc-big">' . brra_e( brra_money( $sum( $mine_paid ) ) ) . '</div><div class="bc-muted">' . count( $mine_paid ) . ' ' . ( 1 === count( $mine_paid ) ? 'claim' : 'claims' ) . '</div></div></div>';
	}

	// Add a payment.
	$my_card  = isset( $cards[ $uid ] );
	$can_card = $my_card || ( $all_see && $cards );
	if ( brra_committee_can_edit() ) {
		$keep = array( 'f_season' => $season );
		echo '<div class="bc-grid bc-g2w"><div class="bc-card is-navy bc-addpay">' . brra_c_form( 'expense_add', $keep, 'enctype="multipart/form-data" class="bc-pay"' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="bc-row bc-sp"><h3>Add a payment</h3>';
		if ( $can_card ) {
			echo '<div class="bc-seg bc-seg-dark"><label><input type="radio" name="type" value="claim"' . checked( ! $my_card, true, false ) . '><span>I paid, claim it back</span></label><label><input type="radio" name="type" value="card"' . checked( $my_card, true, false ) . '><span>Business debit card</span></label></div>';
		} else {
			echo '<input type="hidden" name="type" value="claim">';
		}
		echo '</div><div class="bc-pay-grid">';
		if ( $can_card ) {
			echo '<label class="bc-stack bc-only-card">Card<select name="card">';
			foreach ( $cards as $cu => $last ) {
				if ( $cu === $uid || $all_see ) {
					echo '<option value="' . (int) $cu . '"' . selected( $cu, $uid, false ) . '>' . brra_e( brra_c_name( $cu ) . ( $last ? ' (ending ' . $last . ')' : '' ) ) . '</option>';
				}
			}
			echo '</select></label>';
		}
		echo '<label class="bc-stack">Date<input type="date" name="date" value="' . esc_attr( current_time( 'Y-m-d' ) ) . '" required></label><label class="bc-stack">Amount<input type="text" name="amount" inputmode="decimal" placeholder="£0.00" required></label>';
		echo '<label class="bc-stack bc-wide">What was it for?<input type="text" name="what" placeholder="e.g. Speaker petrol, Peter Wright" required></label><label class="bc-stack">Budget area<select name="area">';
		foreach ( $areas as $k => $n ) {
			echo '<option value="' . esc_attr( $k ) . '">' . brra_e( $n ) . '</option>';
		}
		echo '<option value="other">Something else</option></select></label>';
		echo '<label class="bc-stack bc-wide">Receipt (photo or PDF)<input type="file" name="receipt" accept="image/*,.pdf" capture="environment"></label><button class="bc-btn is-gold">Save payment</button></div></form></div>';

		// Side: your claims, cards.
		echo '<div>';
		$mine = array_filter(
			$list,
			function ( $e ) use ( $uid ) {
				return 'claim' === $e['type'] && (int) $e['by'] === $uid && 'paid' !== $e['status'];
			}
		);
		echo '<div class="bc-card"><h3>Your claims</h3>';
		if ( $mine ) {
			echo '<table class="bc-table bc-tight">';
			foreach ( $mine as $e ) {
				$st = brra_expense_status( $e );
				echo '<tr><td>' . brra_e( $e['what'] ) . '<div class="bc-muted">' . brra_e( brra_c_date( $e['date'], 'j M' ) . ' · ' . brra_money( $e['amount'] ) ) . '</div></td><td>' . brra_pill( $st[0], $st[1] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</table>';
		} else {
			echo '<p class="bc-muted">Nothing waiting. Claims you add show here until they\'re paid back.</p>';
		}
		echo '</div>';
		if ( brra_can_edit_budget() ) {
			echo '<details class="bc-card bc-fold"><summary>Business debit cards (' . count( $cards ) . ')</summary>' . brra_c_form( 'expense_cards', array( 'f_season' => $season ), 'class="bc-form"' ); // phpcs:ignore WordPress.Security.EscapeOutput
			$i = 0;
			foreach ( $cards + array( 0 => '' ) as $cu => $last ) {
				echo '<div class="bc-row bc-card-row">' . brra_people_select( 'cards[' . $i . '][uid]', $cu, 'Add a card holder' ) . '<input type="text" name="cards[' . $i . '][last]" value="' . esc_attr( $last ) . '" placeholder="Last 4" maxlength="4" inputmode="numeric" class="bc-last4">' . ( $cu ? '<label class="bc-check"><input type="checkbox" name="cards[' . $i . '][remove]" value="1"> Remove</label>' : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				++$i;
			}
			echo '<button class="bc-btn is-sm">Save cards</button></form><p class="bc-muted">Only card holders can log card payments, for their own card.</p></details>';
		}
		echo '</div></div>';
	}

	// The list.
	$rows = array_filter(
		$list,
		function ( $e ) use ( $who, $area, $show ) {
			return ( ! $who || brra_expense_person( $e ) === $who ) && ( ! $area || $e['area'] === $area ) && ( 'all' === $show || brra_expense_outstanding( $e ) );
		}
	);
	echo '<div class="bc-card"><div class="bc-row bc-sp"><h3>' . ( $all_see ? 'Season ' . brra_e( brra_season_label( $season ) ) : 'Your payments, ' . brra_e( brra_season_label( $season ) ) ) . '</h3>';
	echo '<form method="get" class="bc-row bc-filter-row"><input type="hidden" name="view" value="expenses"><input type="hidden" name="season" value="' . (int) $season . '">';
	if ( $all_see ) {
		echo brra_people_select( 'who', $who, 'Everyone' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<select name="area"><option value="">All areas</option>';
		foreach ( $areas + array( 'other' => 'Something else' ) as $k => $n ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $area, $k, false ) . '>' . brra_e( $n ) . '</option>';
		}
		echo '</select>';
	}
	echo '<select name="show"><option value="open">Anything outstanding</option><option value="all"' . selected( $show, 'all', false ) . '>Everything</option></select><button class="bc-btn is-sm is-ghost">Show</button></form></div>';
	if ( ! $rows ) {
		echo '<p class="bc-empty">' . ( 'open' === $show ? 'Nothing outstanding. Everything this season is approved, paid back or ticked off.' : 'No payments logged this season yet. Add the first one above.' ) . '</p></div>';
		return;
	}
	$filters = array( 'f_season' => $season, 'f_who' => $who ? $who : '', 'f_area' => $area, 'f_show' => $show );
	echo '<table class="bc-table bc-exp"><thead><tr><th>Date</th><th>What</th><th>Who</th><th>Type</th><th class="bc-num-cell">Amount</th><th>Receipt</th><th>Status</th></tr></thead><tbody>';
	foreach ( $rows as $id => $e ) {
		$st     = brra_expense_status( $e );
		$person = brra_expense_person( $e );
		$mine   = $person === $uid && ! current_user_can( 'manage_options' );
		$args   = array_merge( array( 'eid' => $id ), array_filter( $filters ) );
		$btns   = '';
		if ( $approve && 'claim' === $e['type'] && in_array( $e['status'], array( 'pending', 'queried' ), true ) && ! $mine ) {
			$btns .= '<a class="bc-btn is-sm" href="' . esc_url( brra_c_action_url( 'expense_set', array_merge( $args, array( 'set' => 'approve' ) ) ) ) . '">Approve</a>';
			$btns .= '<details class="bc-query"><summary class="bc-btn is-sm is-ghost">Query</summary>' . brra_c_form( 'expense_set', array_merge( $args, array( 'set' => 'query' ) ), 'class="bc-form"' ) . '<textarea name="note" rows="2" placeholder="What\'s the question?" required>' . esc_textarea( $e['note'] ) . '</textarea><button class="bc-btn is-sm">Send to ' . brra_e( strtok( brra_c_name( $person ), ' ' ) ) . '</button></form></details>';
		}
		if ( $approve && 'claim' === $e['type'] && 'approved' === $e['status'] && ! $mine ) {
			$btns .= '<a class="bc-btn is-sm is-gold" href="' . esc_url( brra_c_action_url( 'expense_set', array_merge( $args, array( 'set' => 'paid' ) ) ) ) . '">Mark paid back</a>';
		}
		if ( $approve && 'card' === $e['type'] && $e['receipt'] ) {
			$btns .= '<a class="bc-btn is-sm' . ( 'checked' === $e['status'] ? ' is-ghost' : '' ) . '" href="' . esc_url( brra_c_action_url( 'expense_set', array_merge( $args, array( 'set' => 'check' ) ) ) ) . '">' . ( 'checked' === $e['status'] ? 'Untick' : 'Tick on statement' ) . '</a>';
		}
		if ( $approve && 'card' === $e['type'] && ! $e['receipt'] && $person !== $uid ) {
			$btns .= '<a class="bc-btn is-sm is-ghost" href="' . esc_url( brra_c_action_url( 'expense_remind', $args ) ) . '">' . ( ! empty( $e['reminded'] ) ? 'Remind again' : 'Remind ' . brra_e( strtok( brra_c_name( $person ), ' ' ) ) ) . '</a>';
		}
		$receipt = $e['receipt'] ? '<a class="bc-dl" target="_blank" href="' . esc_url( brra_c_action_url( 'expense_file', array( 'eid' => $id ) ) ) . '">View</a>' : '<strong class="bc-red">Missing</strong>';
		if ( ! $e['receipt'] && ( $person === $uid || (int) $e['by'] === $uid || $all_see ) ) {
			$receipt .= '<details class="bc-query"><summary class="bc-btn is-sm is-ghost">Add receipt</summary>' . brra_c_form( 'expense_receipt', $args, 'enctype="multipart/form-data" class="bc-form"' ) . '<input type="file" name="receipt" accept="image/*,.pdf" required><button class="bc-btn is-sm">Upload</button></form></details>';
		}
		$own_open = (int) $e['by'] === $uid && in_array( $e['status'], array( 'pending', 'queried', 'unchecked' ), true );
		$del      = $own_open || $approve ? '<a class="bc-link-danger" href="' . esc_url( brra_c_action_url( 'expense_delete', $args ) ) . '" onclick="return confirm(\'Delete this payment?\')">Delete</a>' : '';
		echo '<tr class="' . ( 'r' === $st[1] ? 'is-warn' : '' ) . '"><td>' . brra_e( brra_c_date( $e['date'], 'j M' ) ) . '</td><td><strong>' . brra_e( $e['what'] ) . '</strong><div class="bc-muted">' . brra_e( isset( $areas[ $e['area'] ] ) ? $areas[ $e['area'] ] : 'Something else' ) . '</div>' . ( 'queried' === $e['status'] && $e['note'] ? '<div class="bc-note">Query: ' . brra_e( $e['note'] ) . '</div>' : '' ) . '</td><td>' . brra_e( brra_c_name( $person ) ) . '</td><td>' . brra_e( 'card' === $e['type'] ? 'Card' . ( isset( $cards[ $e['card'] ] ) ? ' ••' . $cards[ $e['card'] ] : '' ) : 'Claim' ) . '</td><td class="bc-num-cell">' . brra_e( brra_money( $e['amount'] ) ) . '</td><td>' . $receipt . '</td><td>' . brra_pill( $st[0], $st[1] ) . ( $btns || $del ? '<div class="bc-row bc-btns">' . $btns . $del . '</div>' : '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table>';
	echo $all_see ? '<p class="bc-note">Receipts are only visible to the Treasurer, Secretary and Chairman. Other committee members see just their own claims. Nobody can approve their own claim.</p>' : '';
	echo '</div>';
}

/**
 * The season budget.
 */
function brra_c_view_budget() {
	$season  = brra_c_season_in();
	$cur     = (int) brra_current_season()[0];
	$budget  = brra_budget( $season );
	$can     = brra_can_edit_budget();
	$editing = $can && ( brra_c_in( 'edit' ) || ! $budget );
	$all     = get_option( 'brra_budget', array() );
	$seasons = is_array( $all ) ? array_keys( $all ) : array();

	$right = brra_c_season_picker( 'budget', $season, $seasons );
	if ( $can && $budget && ! $editing ) {
		$right .= '<a class="bc-btn is-ghost" href="' . esc_url( brra_c_url( 'budget', array( 'season' => $season, 'edit' => 1 ) ) ) . '">Edit budget</a>';
	}
	brra_c_head( 'Finance', 'Budget ' . brra_season_label( $season ), $right, 'What we plan to spend in each area this season, and what has gone out so far from expenses and card payments. Everyone on the committee can see it; only the Treasurer, Secretary and Chairman can change it.' );

	if ( $editing ) {
		$prev  = brra_budget( $season - 1 );
		$start = $budget ? $budget['areas'] : ( $prev ? $prev['areas'] : brra_budget_default_areas() );
		if ( ! $budget ) {
			echo '<p class="bc-notice is-info">' . ( $prev ? 'No budget set for ' . brra_e( brra_season_label( $season ) ) . ' yet. It starts from last season\'s figures; change anything you need and save.' : 'No budget set for ' . brra_e( brra_season_label( $season ) ) . ' yet. Fill in an amount for each area and save.' ) . '</p>';
		}
		echo brra_c_form( 'budget', array( 'season' => $season ) ) . '<div class="bc-card"><table class="bc-table bc-budget-edit"><thead><tr><th>Area</th><th>Note</th><th>Budget (£)</th><th>Remove</th></tr></thead><tbody>'; // phpcs:ignore WordPress.Security.EscapeOutput
		$i = 0;
		foreach ( array_merge( $start, array_fill( 0, 3, array( 'key' => '', 'name' => '', 'note' => '', 'amount' => '' ) ) ) as $a ) {
			echo '<tr><td><input type="hidden" name="areas[' . $i . '][key]" value="' . esc_attr( $a['key'] ) . '"><input type="text" name="areas[' . $i . '][name]" value="' . esc_attr( $a['name'] ) . '" placeholder="' . ( $a['key'] ? '' : 'New area' ) . '"></td><td><input type="text" name="areas[' . $i . '][note]" value="' . esc_attr( $a['note'] ) . '"></td><td><input type="text" inputmode="decimal" name="areas[' . $i . '][amount]" value="' . esc_attr( '' === $a['amount'] ? '' : number_format( (float) $a['amount'], 2, '.', '' ) ) . '" class="bc-amount"></td><td>' . ( $a['key'] ? '<input type="checkbox" name="areas[' . $i . '][remove]" value="1" aria-label="Remove ' . esc_attr( $a['name'] ) . '">' : '' ) . '</td></tr>';
			++$i;
		}
		echo '</tbody></table></div><div class="bc-row"><button class="bc-btn">Save budget</button>' . ( $budget ? '<a class="bc-btn is-ghost" href="' . esc_url( brra_c_url( 'budget', array( 'season' => $season ) ) ) . '">Cancel</a>' : '' ) . '</div></form>';
		return;
	}
	if ( ! $budget ) {
		echo '<div class="bc-card"><p class="bc-empty">No budget has been set for ' . brra_e( brra_season_label( $season ) ) . ' yet. The Treasurer, Secretary or Chairman can set one.</p></div>';
		return;
	}

	$spent   = array();
	$waiting = array();
	foreach ( brra_expenses_in( $season ) as $e ) {
		$k = $e['area'] ? $e['area'] : 'other';
		if ( brra_expense_counts( $e ) ) {
			$spent[ $k ] = ( isset( $spent[ $k ] ) ? $spent[ $k ] : 0 ) + $e['amount'];
		} elseif ( in_array( $e['status'], array( 'pending', 'queried' ), true ) ) {
			$waiting[ $k ] = ( isset( $waiting[ $k ] ) ? $waiting[ $k ] : 0 ) + $e['amount'];
		}
	}
	$total = array_sum( wp_list_pluck( $budget['areas'], 'amount' ) );
	$used  = array_sum( $spent );
	if ( $season < $cur ) {
		$gone = 100;
	} elseif ( $season > $cur ) {
		$gone = 0;
	} else {
		$months = ( (int) current_time( 'Y' ) - $season ) * 12 + (int) current_time( 'n' ) - 6;
		$gone   = (int) round( min( 12, max( 0, $months + (int) current_time( 'j' ) / (int) current_time( 't' ) ) ) / 12 * 100 );
	}
	echo '<div class="bc-grid bc-g4">';
	echo '<div class="bc-card"><div class="bc-muted">Budget for the season</div><div class="bc-big">' . brra_e( '£' . number_format( $total ) ) . '</div><div class="bc-muted">set ' . brra_e( mysql2date( 'j M', $budget['at'] ) ) . ' by ' . brra_e( brra_c_name( $budget['by'] ) ) . '</div></div>';
	echo '<div class="bc-card"><div class="bc-muted">Spent so far</div><div class="bc-big">' . brra_e( brra_money( $used ) ) . '</div><div class="bc-muted">' . ( $total > 0 ? (int) round( $used / $total * 100 ) . '% of the budget' : '' ) . '</div></div>';
	echo '<div class="bc-card' . ( $used > $total ? ' is-alert' : '' ) . '"><div class="bc-muted">Left to spend</div><div class="bc-big">' . brra_e( brra_money( $total - $used ) ) . '</div><div class="bc-muted">to 31 May ' . (int) ( $season + 1 ) . '</div></div>';
	echo '<div class="bc-card"><div class="bc-muted">Season gone</div><div class="bc-big">' . (int) $gone . '%</div><div class="bc-muted">June to May</div></div></div>';

	echo '<div class="bc-card"><div class="bc-row bc-sp"><h3>By area</h3><div class="bc-legend"><span><i class="bc-lg-n"></i>On track</span><span><i class="bc-lg-a"></i>Over 80%</span><span><i class="bc-lg-r"></i>Over budget</span><span><i class="bc-lg-m"></i>Season so far</span></div></div>';
	echo '<table class="bc-table bc-budget"><thead><tr><th>Area</th><th class="bc-num-cell">Budget</th><th class="bc-num-cell">Spent</th><th class="bc-num-cell">Left</th><th class="bc-bar-col">Progress</th></tr></thead><tbody>';
	$areas = $budget['areas'];
	$known = wp_list_pluck( $areas, 'key' );
	$extra = 0;
	foreach ( $spent as $k => $v ) {
		if ( ! in_array( $k, $known, true ) ) {
			$extra += $v;
		}
	}
	if ( $extra ) {
		$areas[]        = array( 'key' => '_other', 'name' => 'Not in the budget', 'note' => 'Spending put down as something else', 'amount' => 0 );
		$spent['_other'] = $extra;
	}
	foreach ( $areas as $a ) {
		$s    = isset( $spent[ $a['key'] ] ) ? $spent[ $a['key'] ] : 0;
		$w    = isset( $waiting[ $a['key'] ] ) ? $waiting[ $a['key'] ] : 0;
		$pct  = $a['amount'] > 0 ? $s / $a['amount'] * 100 : ( $s > 0 ? 101 : 0 );
		$tone = $pct > 100 ? 'r' : ( $pct >= 80 ? 'a' : 'n' );
		$left = $a['amount'] - $s;
		echo '<tr><td><strong>' . brra_e( $a['name'] ) . '</strong>' . ( $a['note'] ? '<div class="bc-muted">' . brra_e( $a['note'] ) . '</div>' : '' ) . '</td><td class="bc-num-cell">' . brra_e( brra_money( $a['amount'] ) ) . '</td><td class="bc-num-cell">' . brra_e( brra_money( $s ) ) . ( $w ? '<div class="bc-muted">+' . brra_e( brra_money( $w ) ) . ' waiting</div>' : '' ) . '</td><td class="bc-num-cell' . ( $left < 0 ? ' bc-red' : '' ) . '">' . brra_e( brra_money( $left ) ) . '</td>';
		echo '<td><div class="bc-meter"><i class="is-' . esc_attr( $tone ) . '" style="width:' . (int) min( 100, round( $pct ) ) . '%"></i><b style="left:' . (int) $gone . '%"></b></div><div class="bc-muted">' . ( $a['amount'] > 0 ? (int) round( $pct ) . '% used' : 'No budget' ) . '</div></td></tr>';
	}
	echo '<tr class="bc-total"><td><strong>Total</strong></td><td class="bc-num-cell">' . brra_e( brra_money( $total ) ) . '</td><td class="bc-num-cell">' . brra_e( brra_money( $used ) ) . '</td><td class="bc-num-cell' . ( $total - $used < 0 ? ' bc-red' : '' ) . '">' . brra_e( brra_money( $total - $used ) ) . '</td><td></td></tr></tbody></table>';
	list( $bal ) = brra_finance_at();
	echo '<p class="bc-note">' . ( $bal ? 'Bank balance on ' . brra_e( brra_c_date( $bal['date'], 'j M' ) ) . ': ' . brra_e( brra_money( $bal['balance'] ) ) . '. ' : '' ) . 'Spending counts once it\'s on the <a href="' . esc_url( brra_c_url( 'expenses', array( 'season' => $season ) ) ) . '">Expenses</a> page: card payments straight away, claims once approved.</p></div>';
}
