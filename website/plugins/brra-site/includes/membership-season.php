<?php
/**
 * When membership can be bought, and for which season.
 *
 * - Online membership closes 28 days before the AGM.
 * - It reopens for the new season once the committee confirms the new price
 *   (Memberships > New member price), and not before the AGM itself.
 * - If a new season starts on 1 June without a confirmed price, it stays closed until one is set.
 * - Returning members just choose Full Member: the site knows they've been a member before,
 *   charges the full price and sends the welcome-back email.
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * The AGM that ends this season (held in the year the next season starts, before June).
 * Uses the date set in the settings, otherwise the event called "AGM" or "Annual General Meeting".
 *
 * @param int $year Year of the AGM.
 * @return DateTimeImmutable|null
 */
function brra_agm_date( $year ) {
	$set = get_option( 'brra_agm_date', '' );
	if ( $set && (int) substr( $set, 0, 4 ) === (int) $year ) {
		$d = date_create_immutable( $set . ' 19:30', wp_timezone() );
		if ( $d ) {
			return $d;
		}
	}
	if ( ! post_type_exists( 'brra_event' ) ) {
		return null;
	}
	$events = get_posts(
		array(
			'post_type'      => 'brra_event',
			'posts_per_page' => 20,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => '_brra_date',
					'value'   => array( $year . '-01-01', $year . '-06-30' ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
		)
	);
	foreach ( $events as $e ) {
		if ( preg_match( '/\bAGM\b|annual general meeting/i', $e->post_title ) ) {
			return brra_event_time( $e->ID, 'start' );
		}
	}
	return null;
}

/**
 * The season that has been opened for sale most recently (start year). Starts as the current season.
 */
function brra_season_opened() {
	$v = get_option( 'brra_season_opened', '' );
	if ( '' === $v ) {
		$v = brra_current_season()[0];
		update_option( 'brra_season_opened', $v );
	}
	return (int) $v;
}

/**
 * Apply a confirmed new-season price to the season membership levels, once.
 *
 * @param array $next Confirmed season: season, price.
 */
function brra_apply_next_season( $next ) {
	global $wpdb;
	foreach ( brra_membership_settings()['season_levels'] as $id ) {
		$wpdb->update( $wpdb->pmpro_membership_levels, array( 'initial_payment' => (float) $next['price'] ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	update_option( 'brra_season_opened', (int) $next['season'] );
}

/**
 * Where we are in the membership year.
 *
 * @param int|null $now Timestamp (for testing).
 * @return array{state:string,season:int[],agm:?DateTimeImmutable,close:?DateTimeImmutable,next:int,confirmed:?array,reason:string}
 */
function brra_membership_window( $now = null ) {
	$now  = $now ? $now : (int) apply_filters( 'brra_membership_now', time() );
	$cur  = brra_current_season( $now );
	$next = $cur[1];
	$agm  = brra_agm_date( $next );
	$cl   = $agm ? $agm->setTime( 0, 0 )->modify( '-28 days' ) : null;
	$conf = get_option( 'brra_next_season', array() );
	$conf = is_array( $conf ) && ! empty( $conf['season'] ) && (int) $conf['season'] === $next ? $conf : null;
	$out  = array(
		'state'     => 'open',
		'season'    => $cur,
		'agm'       => $agm,
		'close'     => $cl,
		'next'      => $next,
		'confirmed' => $conf,
		'reason'    => '',
	);

	// The new season's price is confirmed and the AGM has happened (or there's no AGM date): sell the new season.
	if ( $conf && ( ! $agm || $now >= $agm->setTime( 0, 0 )->getTimestamp() ) ) {
		if ( brra_season_opened() < $next ) {
			brra_apply_next_season( $conf );
		}
		$out['season'] = array( $next, $next + 1 );
		return $out;
	}
	// A new season has started without its price being confirmed.
	if ( brra_season_opened() < $cur[0] ) {
		$out['state']  = 'closed';
		$out['next']   = $cur[0];
		$out['season'] = $cur;
		$out['reason'] = 'unconfirmed';
		return $out;
	}
	// Within 28 days of the AGM.
	if ( $cl && $now >= $cl->getTimestamp() ) {
		$out['state']  = 'closed';
		$out['reason'] = 'agm';
		return $out;
	}
	return $out;
}

/**
 * Season a membership bought now covers, e.g. array( 2026, 2027 ).
 */
function brra_sales_season() {
	return brra_membership_window()['season'];
}

/**
 * "2026/27".
 *
 * @param int[] $s Season.
 */
function brra_season_text( $s ) {
	return sprintf( '%d/%02d', $s[0], $s[1] % 100 );
}

/**
 * Full price of the first season level.
 */
function brra_full_price() {
	global $wpdb;
	foreach ( brra_membership_settings()['season_levels'] as $id ) {
		$p = $wpdb->get_var( $wpdb->prepare( "SELECT initial_payment FROM {$wpdb->pmpro_membership_levels} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( null !== $p ) {
			return (float) $p;
		}
	}
	return 20.0;
}

/**
 * The first season level's ID.
 */
function brra_full_level_id() {
	$ids = brra_membership_settings()['season_levels'];
	return $ids ? (int) reset( $ids ) : 0;
}

/**
 * "£20" without pence when whole.
 *
 * @param float $p Price.
 */
function brra_price_text( $p ) {
	return '£' . ( floor( $p ) == $p ? number_format( $p, 0 ) : number_format( $p, 2 ) ); // phpcs:ignore Universal.Operators.StrictComparisons
}

/**
 * Sentence saying membership is closed, and why.
 */
function brra_membership_closed_text() {
	$w    = brra_membership_window();
	$next = brra_season_text( array( $w['next'], $w['next'] + 1 ) );
	if ( 'agm' === $w['reason'] && $w['agm'] && time() < $w['agm']->getTimestamp() ) {
		$t = sprintf( 'Membership for %s opens once the new season\'s price is set at the AGM on %s.', $next, $w['agm']->format( 'l j F' ) );
	} else {
		$t = sprintf( 'Membership for %s opens as soon as the new season\'s price is confirmed.', $next );
	}
	if ( 'agm' === $w['reason'] ) {
		$t .= sprintf( ' If you\'re already a member, your membership carries on until 31 August %d.', $w['next'] );
	}
	return $t;
}

// Shortcodes for the Membership page, so the season and prices keep themselves up to date.
add_action(
	'init',
	function () {
		add_shortcode(
			'brra_season',
			function () {
				return esc_html( brra_season_text( brra_sales_season() ) );
			}
		);
		add_shortcode(
			'brra_price',
			function ( $atts ) {
				$atts = shortcode_atts( array( 'type' => 'full' ), $atts );
				$full = brra_full_price();
				return esc_html( brra_price_text( 'new' === $atts['type'] ? brra_new_member_price( $full ) : $full ) );
			}
		);
		add_shortcode(
			'brra_discount',
			function () {
				return esc_html( (string) (float) brra_membership_settings()['discount'] );
			}
		);
		add_shortcode(
			'brra_membership_notice',
			function () {
				if ( 'closed' !== brra_membership_window()['state'] ) {
					return '';
				}
				return '<p class="brra-notice brra-closed-notice">' . esc_html( brra_membership_closed_text() ) . '</p>';
			}
		);
	}
);

// While closed, the join page shows why instead of the form.
add_filter(
	'pmpro_pages_shortcode_checkout',
	function ( $html ) {
		if ( 'closed' !== brra_membership_window()['state'] ) {
			return $html;
		}
		return '<div class="brra-closed"><h2>Membership is closed for now</h2><p>' . esc_html( brra_membership_closed_text() ) . '</p><p>Questions? Email <a href="mailto:mail@buryrefs.org.uk">mail@buryrefs.org.uk</a>.</p></div>';
	}
);
add_filter(
	'pmpro_registration_checks',
	function ( $ok ) {
		if ( $ok && 'closed' === brra_membership_window()['state'] ) {
			pmpro_setMessage( brra_membership_closed_text(), 'pmpro_error' );
			return false;
		}
		return $ok;
	}
);

/**
 * The person's latest season membership row, if any.
 *
 * @param int $uid User ID.
 */
function brra_latest_membership( $uid ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( "SELECT membership_id, status, enddate FROM {$wpdb->pmpro_memberships_users} WHERE user_id = %d ORDER BY id DESC LIMIT 1", $uid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// My Account: a Renew box, only once the AGM has happened and the new season's price is confirmed,
// up to 31 August when last season's memberships end. Members not yet covered for the new season see it.
add_filter(
	'pmpro_pages_shortcode_account',
	function ( $html ) {
		if ( ! is_user_logged_in() ) {
			return $html;
		}
		$w   = brra_membership_window();
		$now = (int) apply_filters( 'brra_membership_now', time() );
		if ( 'open' !== $w['state'] || (int) wp_date( 'Y', $now ) !== $w['season'][0] || (int) wp_date( 'n', $now ) > 8 ) {
			return $html;
		}
		$m = brra_latest_membership( get_current_user_id() );
		if ( ! $m ) {
			return $html;
		}
		$active = 'active' === $m->status;
		if ( $active && ( ! $m->enddate || '0000-00-00 00:00:00' === $m->enddate || (int) substr( $m->enddate, 0, 4 ) >= $w['season'][1] ) ) {
			return $html; // Life member, or already renewed.
		}
		$url = function_exists( 'pmpro_url' ) ? pmpro_url( 'checkout', '?pmpro_level=' . brra_full_level_id() ) : home_url( '/join/' );
		$box = '<h2>' . esc_html( 'Renew for ' . brra_season_text( $w['season'] ) ) . '</h2><p>' . esc_html( sprintf( 'Full membership is %s for the %s season and runs until 31 August %d.', brra_price_text( brra_full_price() ), brra_season_text( $w['season'] ), $w['season'][1] ) ) . ( $active ? ' ' . esc_html( sprintf( 'Your current membership ends on %s.', mysql2date( 'j F Y', $m->enddate ) ) ) : '' ) . '</p><p><a class="wp-element-button" href="' . esc_url( $url ) . '">Renew my membership</a></p>';
		return '<div class="brra-renew-box">' . $box . '</div>' . $html;
	}
);

// Settings: AGM date and confirming the new season's price (on Memberships > New member price).
add_action(
	'admin_post_brra_season_save',
	function () {
		if ( ! current_user_can( 'pmpro_memberships_menu' ) ) {
			wp_die( 'Sorry, you can\'t do that.' );
		}
		check_admin_referer( 'brra_season_save' );
		$date = isset( $_POST['agm_date'] ) ? sanitize_text_field( wp_unslash( $_POST['agm_date'] ) ) : '';
		update_option( 'brra_agm_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '' );
		$msg = 'agm';
		if ( ! empty( $_POST['confirm'] ) ) {
			$price = isset( $_POST['price'] ) ? (float) str_replace( array( '£', ',' ), '', sanitize_text_field( wp_unslash( $_POST['price'] ) ) ) : 0;
			$w     = brra_membership_window();
			if ( $price > 0 ) {
				update_option( 'brra_next_season', array( 'season' => $w['next'], 'price' => $price, 'by' => get_current_user_id(), 'at' => time() ) );
				$msg = 'confirmed';
			}
		}
		if ( ! empty( $_POST['undo'] ) ) {
			delete_option( 'brra_next_season' );
			$msg = 'undone';
		}
		wp_safe_redirect( admin_url( 'admin.php?page=brra-membership&season=' . $msg ) );
		exit;
	}
);

/**
 * The "Season and renewals" box on the settings screen.
 */
function brra_season_admin_box() {
	$w    = brra_membership_window();
	$next = brra_season_text( array( $w['next'], $w['next'] + 1 ) );
	$msgs = array(
		'agm'       => 'Saved.',
		'confirmed' => 'New season price confirmed.',
		'undone'    => 'Confirmation removed.',
	);
	$m    = isset( $_GET['season'] ) ? sanitize_key( $_GET['season'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( isset( $msgs[ $m ] ) ) {
		echo '<div class="notice notice-success"><p>' . esc_html( $msgs[ $m ] ) . '</p></div>';
	}
	echo '<h2>Season and renewals</h2>';
	if ( 'closed' === $w['state'] ) {
		$status = 'Closed. ' . brra_membership_closed_text();
	} else {
		$status = sprintf( 'Open: people joining now get the %s season, at %s.', brra_season_text( $w['season'] ), brra_price_text( brra_full_price() ) );
		if ( $w['close'] && $w['season'][0] < $w['next'] ) {
			$status .= sprintf( ' Closes on %s, 28 days before the AGM.', $w['close']->format( 'l j F Y' ) );
		}
	}
	echo '<p><strong>' . esc_html( $status ) . '</strong></p>';
	if ( ! $w['agm'] && $w['season'][0] < $w['next'] ) {
		echo '<p>No AGM date found for ' . esc_html( (string) $w['next'] ) . '. Add the AGM to Events, or set the date below, so membership closes 28 days before.</p>';
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="brra_season_save">
		<?php wp_nonce_field( 'brra_season_save' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="agm_date">AGM date</label></th>
				<td><input type="date" id="agm_date" name="agm_date" value="<?php echo esc_attr( get_option( 'brra_agm_date', '' ) ); ?>">
				<p class="description">Leave blank to use the AGM in Events<?php echo $w['agm'] ? esc_html( ' (' . $w['agm']->format( 'j F Y' ) . ')' ) : ''; ?>.</p></td>
			</tr>
			<?php if ( $w['season'][0] < $w['next'] || 'closed' === $w['state'] ) : ?>
			<tr>
				<th scope="row"><label for="price">Full membership for <?php echo esc_html( $next ); ?></label></th>
				<td>
					<?php if ( $w['confirmed'] ) : ?>
						<p><?php echo esc_html( sprintf( 'Confirmed at %s on %s. Renewals open %s.', brra_price_text( (float) $w['confirmed']['price'] ), wp_date( 'j F Y', (int) $w['confirmed']['at'] ), $w['agm'] && time() < $w['agm']->getTimestamp() ? 'on the day of the AGM' : 'now' ) ); ?></p>
						<?php if ( 'closed' === $w['state'] || $w['season'][0] < $w['next'] ) : ?>
							<button class="button" name="undo" value="1">Undo</button>
						<?php endif; ?>
					<?php else : ?>
						£ <input type="text" id="price" name="price" inputmode="decimal" size="6" value="<?php echo esc_attr( number_format( brra_full_price(), 2 ) ); ?>">
						<button class="button button-primary" name="confirm" value="1">Confirm price and open renewals</button>
						<p class="description">Press this once the committee or the AGM has agreed the price. First-time members still get <?php echo esc_html( (string) (float) brra_membership_settings()['discount'] ); ?>% off. Renewals open straight away, or on the day of the AGM if it hasn't happened yet.</p>
					<?php endif; ?>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php submit_button( 'Save AGM date', 'secondary' ); ?>
	</form>
	<?php
}

/*
 * Test site only (WordPress Playground runs on SQLite): Paid Memberships Pro looks up a member's
 * levels with MySQL time-zone functions that SQLite doesn't have, so My Account says "no active
 * membership". On real hosting (MySQL) none of this runs.
 */
if ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
	/**
	 * A member's active levels, without MySQL-only functions.
	 *
	 * @param int $uid User ID.
	 */
	function brra_sqlite_levels( $uid ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT l.id AS ID, l.id AS id, mu.id AS subscription_id, l.name, l.description, l.confirmation, l.expiration_number, l.expiration_period, l.allow_signups, mu.initial_payment, mu.billing_amount, mu.cycle_number, mu.cycle_period, mu.billing_limit, mu.trial_amount, mu.trial_limit, mu.status, mu.code_id, mu.startdate, mu.enddate FROM {$wpdb->pmpro_membership_levels} l JOIN {$wpdb->pmpro_memberships_users} mu ON l.id = mu.membership_id WHERE mu.user_id = %d AND mu.status = 'active' ORDER BY mu.id DESC", $uid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $rows as $r ) {
			$r->startdate = $r->startdate ? strtotime( $r->startdate . ' UTC' ) : null;
			$r->enddate   = $r->enddate && '0000-00-00 00:00:00' !== $r->enddate ? strtotime( $r->enddate . ' UTC' ) : null;
		}
		return (array) $rows;
	}
	add_filter(
		'pmpro_get_membership_levels_for_user',
		function ( $levels, $uid ) {
			return empty( $levels ) ? brra_sqlite_levels( $uid ) : $levels;
		},
		10,
		2
	);
	add_filter(
		'pmpro_get_membership_level_for_user',
		function ( $level, $uid ) {
			if ( ! empty( $level ) ) {
				return $level;
			}
			$all = brra_sqlite_levels( $uid );
			return $all ? $all[0] : $level;
		},
		10,
		2
	);
}
