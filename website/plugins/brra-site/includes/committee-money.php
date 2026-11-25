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
	return ! in_array( $e['status'], array( 'paid', 'declined' ), true );
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
		'declined' => array( 'Declined', 'r' ),
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
	foreach ( array( 'season', 'who', 'area', 'tab' ) as $k ) {
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
	if ( $mine && 'decline' === $set ) {
		brra_c_die( 'Someone else needs to decide on your own claim.' );
	}
	$notice = 'saved';
	switch ( $set ) {
		case 'approve':
			$e['status']      = 'approved';
			$e['approved_by'] = get_current_user_id();
			$e['approved_at'] = current_time( 'mysql' );
			brra_expense_mail( $e, 'Expense approved: ' . $e['what'], brra_c_name( get_current_user_id() ) . ' has approved your claim for ' . brra_expense_line( $e ) . ". It'll be paid back soon." );
			break;
		case 'paid':
			$e['status']  = 'paid';
			$e['paid_at'] = current_time( 'mysql' );
			$e['paid_by'] = get_current_user_id();
			brra_expense_mail( $e, 'Expense paid back: ' . $e['what'], 'Your claim for ' . brra_expense_line( $e ) . ' has been paid back.' );
			break;
		case 'decline':
			$why = brra_c_in( 'note', '', true );
			if ( 'claim' !== $e['type'] || '' === trim( $why ) ) {
				brra_expense_back( 'needreason' );
			}
			$e['status']      = 'declined';
			$e['note']        = $why;
			$e['declined_by'] = get_current_user_id();
			$e['declined_at'] = current_time( 'mysql' );
			brra_expense_mail( $e, 'Expense declined: ' . $e['what'], brra_c_name( get_current_user_id() ) . ' has declined your claim for ' . brra_expense_line( $e ) . ".\n\nReason: " . $why );
			$notice = 'declined';
			break;
		case 'check':
			$e['status']     = 'checked' === $e['status'] ? 'unchecked' : 'checked';
			$e['checked_at'] = current_time( 'mysql' );
			break;
		case 'query':
			$e['status'] = 'queried';
			$e['note']   = brra_c_in( 'note', '', true );
			brra_expense_mail( $e, 'Question about your expense: ' . $e['what'], brra_c_name( get_current_user_id() ) . ' has a question about ' . brra_expense_line( $e ) . ":\n\n" . $e['note'] );
			break;
	}
	$all[ $id ] = $e;
	brra_expenses_save( $all );
	brra_expense_back( $notice );
}

/**
 * "Speaker petrol (£18.50, 18 Nov 2026)".
 *
 * @param array $e Expense.
 */
function brra_expense_line( $e ) {
	return $e['what'] . ' (' . brra_money( $e['amount'] ) . ', ' . brra_c_date( $e['date'], 'j M Y' ) . ')';
}

/**
 * Email the person whose expense it is.
 *
 * @param array  $e       Expense.
 * @param string $subject Subject.
 * @param string $body    Message.
 */
function brra_expense_mail( $e, $subject, $body ) {
	$who = get_userdata( brra_expense_person( $e ) );
	if ( $who && $who->ID !== get_current_user_id() ) {
		wp_mail( $who->user_email, $subject, 'Hi ' . ( $who->first_name ? $who->first_name : $who->display_name ) . ",\n\n" . $body . "\n\nSee your expenses: " . brra_c_url( 'expenses' ) . "\n\nBury & Rochdale RA" );
	}
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
		if ( 'declined' === $e['status'] ) {
			continue;
		}
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
	$who     = $all_see ? absint( brra_c_in( 'who' ) ) : 0;
	$area    = $all_see ? sanitize_key( brra_c_in( 'area' ) ) : '';
	$adding  = (bool) brra_c_in( 'add' ) && brra_committee_can_edit();
	$areas   = brra_budget_areas( $season );
	$cards   = brra_expense_cards();
	$list    = array_filter( brra_expenses_in( $season ), 'brra_can_see_expense' );
	if ( ! $all_see ) {
		$list = array_filter(
			$list,
			function ( $e ) use ( $uid ) {
				return brra_expense_person( $e ) === $uid || (int) $e['by'] === $uid;
			}
		);
	}
	$seasons = array();
	foreach ( brra_expenses() as $e ) {
		$seasons[] = brra_expense_season( $e );
	}

	// Which tab each payment sits in.
	$group = function ( $e ) {
		if ( 'declined' === $e['status'] ) {
			return 'declined';
		}
		if ( 'card' === $e['type'] ) {
			return brra_expense_outstanding( $e ) ? 'todo' : 'approved';
		}
		return in_array( $e['status'], array( 'approved', 'paid' ), true ) ? 'approved' : 'todo';
	};
	$tabs_n = array( 'todo' => 0, 'approved' => 0, 'declined' => 0 );
	foreach ( $list as $e ) {
		++$tabs_n[ $group( $e ) ];
		if ( $all_see && 'claim' === $e['type'] && 'approved' === $e['status'] ) {
			++$tabs_n['todo']; // Approved, but still to pay back.
		}
	}
	$tab = brra_c_in( 'tab' );
	$tab = isset( $tabs_n[ $tab ] ) ? $tab : 'todo';

	$keep  = array( 'season' => $season, 'tab' => $tab );
	$label = $all_see ? '+ Add a payment' : 'Claim expenses';
	$right = brra_committee_can_edit() && ! $adding ? '<a class="bc-btn is-gold" href="' . esc_url( brra_c_url( 'expenses', array_merge( $keep, array( 'add' => 1 ) ) ) ) . '">' . brra_e( $label ) . '</a>' : '';
	$right .= brra_c_season_picker( 'expenses', $season, $seasons, array( 'tab' => $tab ) );
	if ( $all_see ) {
		$right .= '<a class="bc-btn is-ghost bc-dl" href="' . esc_url( brra_c_action_url( 'expenses_csv', array( 'season' => $season ) ) ) . '">Download for the I&amp;E (Excel)</a>';
		if ( class_exists( 'ZipArchive' ) ) {
			$right .= '<a class="bc-btn is-ghost bc-dl" href="' . esc_url( brra_c_action_url( 'expenses_zip', array( 'season' => $season ) ) ) . '">Download receipts (zip)</a>';
		}
	}
	brra_c_head( 'Finance', 'Expenses and card spend', $right, $all_see ? 'Claims from the committee and every business debit card payment. The Treasurer or Chairman approves claims, pays them back and ticks card payments off against the bank statement.' : 'Paid for something for the RA yourself? Claim it back here with a photo of the receipt. Only you, the Treasurer, Chairman and Secretary can see your claims.' );

	// Add a payment (opened from the button).
	if ( $adding ) {
		$my_card  = isset( $cards[ $uid ] );
		$can_card = $my_card || ( $all_see && $cards );
		echo '<div class="bc-card is-navy bc-addpay bc-addpay-solo">' . brra_c_form( 'expense_add', array( 'f_season' => $season, 'f_tab' => $tab ), 'enctype="multipart/form-data" class="bc-pay"' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="bc-row bc-sp"><h3>' . ( $all_see ? 'Add a payment' : 'Claim expenses' ) . '</h3>';
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
		echo '<label class="bc-stack bc-wide">Receipt (photo or PDF)<input type="file" name="receipt" accept="image/*,.pdf" capture="environment"></label><div class="bc-row bc-pay-go"><button class="bc-btn is-gold">' . ( $all_see ? 'Save payment' : 'Send claim' ) . '</button><a class="bc-btn is-ghost bc-on-dark" href="' . esc_url( brra_c_url( 'expenses', $keep ) ) . '">Cancel</a></div></div></form></div>';
	}

	// Tabs.
	$names = $all_see ? array( 'todo' => 'To approve and pay', 'approved' => 'Approved', 'declined' => 'Declined' ) : array( 'todo' => 'Waiting', 'approved' => 'Approved', 'declined' => 'Declined' );
	echo '<div class="bc-tabs" role="navigation" aria-label="Expenses">';
	foreach ( $names as $k => $n ) {
		echo '<a href="' . esc_url( brra_c_url( 'expenses', array( 'season' => $season, 'tab' => $k ) ) ) . '"' . ( $k === $tab ? ' class="is-on" aria-current="page"' : '' ) . '>' . brra_e( $n ) . ' <span class="bc-tab-n">' . (int) $tabs_n[ $k ] . '</span></a>';
	}
	echo '</div>';

	$filters = array( 'f_season' => $season, 'f_tab' => $tab, 'f_who' => $who ? $who : '', 'f_area' => $area );
	$sum     = function ( $rows ) {
		return array_sum( wp_list_pluck( $rows, 'amount' ) );
	};
	$pick    = function ( $test ) use ( $list ) {
		return array_filter( $list, $test );
	};
	$row     = function ( $id, $e, $mode ) use ( $uid, $all_see, $approve, $areas, $cards, $filters ) {
		$st     = brra_expense_status( $e );
		$person = brra_expense_person( $e );
		$mine   = $person === $uid && ! current_user_can( 'manage_options' );
		$args   = array_merge( array( 'eid' => $id ), array_filter( $filters ) );
		$first  = strtok( brra_c_name( $person ), ' ' );
		$btns   = '';
		$info   = '';
		if ( 'claim' === $e['type'] && in_array( $e['status'], array( 'pending', 'queried' ), true ) ) {
			if ( $approve && ! $mine ) {
				$btns .= '<a class="bc-btn is-sm" href="' . esc_url( brra_c_action_url( 'expense_set', array_merge( $args, array( 'set' => 'approve' ) ) ) ) . '">Approve</a>';
				$btns .= '<details class="bc-query"><summary class="bc-btn is-sm is-ghost">Decline</summary>' . brra_c_form( 'expense_set', array_merge( $args, array( 'set' => 'decline' ) ), 'class="bc-form"' ) . '<label class="bc-stack">Reason (' . brra_e( $first ) . ' will see this)<textarea name="note" rows="2" required></textarea></label><button class="bc-btn is-sm">Decline claim</button></form></details>';
				$btns .= '<details class="bc-query"><summary class="bc-link">Ask a question</summary>' . brra_c_form( 'expense_set', array_merge( $args, array( 'set' => 'query' ) ), 'class="bc-form"' ) . '<textarea name="note" rows="2" placeholder="What\'s the question?" required>' . esc_textarea( 'queried' === $e['status'] ? $e['note'] : '' ) . '</textarea><button class="bc-btn is-sm">Send to ' . brra_e( $first ) . '</button></form></details>';
			} else {
				$info = $mine ? 'The Treasurer or Chairman approves it' : '';
			}
		}
		if ( 'claim' === $e['type'] && 'approved' === $e['status'] ) {
			$info = ! empty( $e['approved_by'] ) ? 'Approved by ' . brra_c_name( $e['approved_by'] ) . ( ! empty( $e['approved_at'] ) ? ', ' . mysql2date( 'j M', $e['approved_at'] ) : '' ) : '';
			if ( $approve && ! $mine && 'todo' === $mode ) {
				$btns .= '<a class="bc-btn is-sm is-gold" href="' . esc_url( brra_c_action_url( 'expense_set', array_merge( $args, array( 'set' => 'paid' ) ) ) ) . '">Mark paid back</a>';
			}
		}
		if ( 'card' === $e['type'] && $approve && $e['receipt'] ) {
			$btns .= '<a class="bc-btn is-sm' . ( 'checked' === $e['status'] ? ' is-ghost' : '' ) . '" href="' . esc_url( brra_c_action_url( 'expense_set', array_merge( $args, array( 'set' => 'check' ) ) ) ) . '">' . ( 'checked' === $e['status'] ? 'Untick' : 'Tick on statement' ) . '</a>';
		}
		if ( 'card' === $e['type'] && $approve && ! $e['receipt'] && $person !== $uid ) {
			$btns .= '<a class="bc-btn is-sm is-ghost" href="' . esc_url( brra_c_action_url( 'expense_remind', $args ) ) . '">' . ( ! empty( $e['reminded'] ) ? 'Remind again' : 'Remind ' . brra_e( $first ) ) . '</a>';
		}
		if ( 'declined' === $e['status'] ) {
			$info = ( $e['note'] ? '"' . $e['note'] . '" ' : '' ) . ( ! empty( $e['declined_by'] ) ? '(' . brra_c_name( $e['declined_by'] ) . ( ! empty( $e['declined_at'] ) ? ', ' . mysql2date( 'j M', $e['declined_at'] ) : '' ) . ')' : '' );
		}
		$receipt = $e['receipt'] ? '<a class="bc-dl" target="_blank" href="' . esc_url( brra_c_action_url( 'expense_file', array( 'eid' => $id ) ) ) . '">View</a>' : '<strong class="bc-red">Missing</strong>';
		if ( ! $e['receipt'] && ( $person === $uid || (int) $e['by'] === $uid || $all_see ) && 'declined' !== $e['status'] ) {
			$receipt .= '<details class="bc-query"><summary class="bc-btn is-sm is-ghost">Add receipt</summary>' . brra_c_form( 'expense_receipt', $args, 'enctype="multipart/form-data" class="bc-form"' ) . '<input type="file" name="receipt" accept="image/*,.pdf" required><button class="bc-btn is-sm">Upload</button></form></details>';
		}
		$own_open = (int) $e['by'] === $uid && in_array( $e['status'], array( 'pending', 'queried', 'unchecked' ), true );
		$del      = $own_open || ( $approve && 'todo' === $mode ) ? '<a class="bc-link-danger" href="' . esc_url( brra_c_action_url( 'expense_delete', $args ) ) . '" onclick="return confirm(\'Delete this payment?\')">Delete</a>' : '';
		$query    = 'queried' === $e['status'] && $e['note'] ? '<div class="bc-note">Question: ' . brra_e( $e['note'] ) . '</div>' : '';
		$status   = ( 'todo' === $mode && $all_see && in_array( $e['status'], array( 'pending', 'approved' ), true ) && 'claim' === $e['type'] ) ? '' : brra_pill( $st[0], $st[1] );
		$out      = '<tr class="' . ( 'r' === $st[1] && 'declined' !== $e['status'] ? 'is-warn' : '' ) . '"><td>' . brra_e( brra_c_date( $e['date'], 'j M' ) ) . '</td><td><strong>' . brra_e( $e['what'] ) . '</strong><div class="bc-muted">' . brra_e( isset( $areas[ $e['area'] ] ) ? $areas[ $e['area'] ] : 'Something else' ) . '</div>' . $query . '</td>';
		if ( $all_see ) {
			$out .= '<td>' . brra_e( brra_c_name( $person ) ) . '</td><td>' . brra_e( 'card' === $e['type'] ? 'Card' . ( isset( $cards[ $e['card'] ] ) ? ' ••' . $cards[ $e['card'] ] : '' ) : 'Claim' ) . '</td>';
		}
		$out .= '<td class="bc-num-cell"><strong>' . brra_e( brra_money( $e['amount'] ) ) . '</strong></td><td>' . $receipt . '</td><td>' . $status . ( $info ? '<div class="bc-muted bc-exp-info">' . brra_e( $info ) . '</div>' : '' ) . ( $btns || $del ? '<div class="bc-row bc-btns">' . $btns . $del . '</div>' : '' ) . '</td></tr>';
		return $out;
	};
	$table = function ( $rows, $mode, $last = '', $foot = '' ) use ( $all_see, $row ) {
		$out = '<table class="bc-table bc-exp"><thead><tr><th>Date</th><th>What</th>' . ( $all_see ? '<th>Who</th><th>Type</th>' : '' ) . '<th class="bc-num-cell">Amount</th><th>Receipt</th><th>' . brra_e( $last ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $id => $e ) {
			$out .= $row( $id, $e, $mode );
		}
		return $out . '</tbody>' . $foot . '</table>';
	};
	$section = function ( $title, $sub, $rows, $mode, $empty, $last = '' ) use ( $table ) {
		echo '<div class="bc-card"><div class="bc-row bc-sp"><h3>' . brra_e( $title ) . '</h3>' . ( $rows ? '<span class="bc-muted">' . brra_e( $sub ) . '</span>' : '' ) . '</div>';
		echo $rows ? $table( $rows, $mode, $last ) : '<p class="bc-empty">' . brra_e( $empty ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
	};
	$money_n = function ( $rows, $word ) use ( $sum ) {
		return count( $rows ) . ' ' . $word . ( 1 === count( $rows ) ? '' : 's' ) . ' · ' . brra_money( $sum( $rows ) );
	};

	if ( 'todo' === $tab && $all_see ) {
		$waiting = $pick(
			function ( $e ) {
				return 'claim' === $e['type'] && in_array( $e['status'], array( 'pending', 'queried' ), true );
			}
		);
		$to_pay  = $pick(
			function ( $e ) {
				return 'claim' === $e['type'] && 'approved' === $e['status'];
			}
		);
		$cardsto = $pick(
			function ( $e ) {
				return 'card' === $e['type'] && brra_expense_outstanding( $e );
			}
		);
		$payees  = array_unique( array_map( 'brra_c_name', array_map( 'brra_expense_person', $to_pay ) ) );
		$section( '1. Waiting for approval', $money_n( $waiting, 'claim' ), $waiting, 'todo', 'No claims waiting.' );
		$section( '2. Approved, to pay back', $money_n( $to_pay, 'claim' ) . ' to ' . implode( ', ', $payees ), $to_pay, 'todo', 'Nothing to pay back.' );
		$section( '3. Card payments to check', 'Tick each one off when it shows on the statement', $cardsto, 'todo', 'Every card payment has a receipt and is ticked off.' );
	} elseif ( 'todo' === $tab ) {
		$rows = $pick(
			function ( $e ) use ( $group ) {
				return 'todo' === $group( $e );
			}
		);
		echo '<div class="bc-card"><h3>Your claims waiting</h3>';
		echo $rows ? $table( $rows, 'todo', 'Where it\'s up to' ) . '<p class="bc-note">You\'ll get an email when it\'s approved, paid back or declined.</p>' : '<p class="bc-empty">Nothing waiting. Use Claim expenses to claim something back.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
	} else {
		$rows = $pick(
			function ( $e ) use ( $group, $tab, $who, $area ) {
				return $tab === $group( $e ) && ( ! $who || brra_expense_person( $e ) === $who ) && ( ! $area || $e['area'] === $area );
			}
		);
		$head = 'approved' === $tab ? ( $all_see ? 'Approved this season' : 'Your approved claims' ) : ( $all_see ? 'Declined this season' : 'Your declined claims' );
		echo '<div class="bc-card"><div class="bc-row bc-sp"><h3>' . brra_e( $head ) . '</h3>';
		if ( $all_see ) {
			echo '<form method="get" class="bc-row bc-filter-row"><input type="hidden" name="view" value="expenses"><input type="hidden" name="season" value="' . (int) $season . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
			echo brra_people_select( 'who', $who, 'Everyone' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<select name="area"><option value="">All areas</option>';
			foreach ( $areas + array( 'other' => 'Something else' ) as $k => $n ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $area, $k, false ) . '>' . brra_e( $n ) . '</option>';
			}
			echo '</select><button class="bc-btn is-sm is-ghost">Show</button></form>';
		}
		echo '</div>';
		if ( $rows ) {
			$cols = $all_see ? 4 : 2;
			$foot = 'approved' === $tab ? '<tfoot><tr><td colspan="' . $cols . '"><strong>Total</strong></td><td class="bc-num-cell"><strong>' . brra_e( brra_money( $sum( $rows ) ) ) . '</strong></td><td colspan="2"></td></tr></tfoot>' : '';
			echo $table( $rows, $tab, 'approved' === $tab ? 'Done' : 'Why it was declined', $foot ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo 'declined' === $tab ? '<p class="bc-note">Declined claims don\'t count towards the budget or the I&amp;E download.</p>' : '';
		} else {
			echo '<p class="bc-empty">' . ( 'approved' === $tab ? 'Nothing approved yet this season.' : 'Nothing declined this season.' ) . '</p>';
		}
		echo '</div>';
	}

	if ( brra_can_edit_budget() ) {
		echo '<details class="bc-card bc-fold"><summary>Business debit cards (' . count( $cards ) . ')</summary>' . brra_c_form( 'expense_cards', array( 'f_season' => $season ), 'class="bc-form"' ); // phpcs:ignore WordPress.Security.EscapeOutput
		$i = 0;
		foreach ( $cards + array( 0 => '' ) as $cu => $last ) {
			echo '<div class="bc-row bc-card-row">' . brra_people_select( 'cards[' . $i . '][uid]', $cu, 'Add a card holder' ) . '<input type="text" name="cards[' . $i . '][last]" value="' . esc_attr( $last ) . '" placeholder="Last 4" maxlength="4" inputmode="numeric" class="bc-last4">' . ( $cu ? '<label class="bc-check"><input type="checkbox" name="cards[' . $i . '][remove]" value="1"> Remove</label>' : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			++$i;
		}
		echo '<button class="bc-btn is-sm">Save cards</button></form><p class="bc-muted">Only card holders can log card payments, for their own card.</p></details>';
	}
	echo $all_see ? '<p class="bc-note">Receipts are only visible to the Treasurer, Secretary and Chairman. Other committee members see just their own claims. Nobody can approve their own claim.</p>' : '';
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
