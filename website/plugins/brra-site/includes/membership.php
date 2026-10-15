<?php
/**
 * Membership rules on top of Paid Memberships Pro:
 * - first-time members automatically get the new-member discount (25% by default);
 * - every season membership ends on 31 August after the season it covers.
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings, with defaults.
 */
function brra_membership_settings() {
	return wp_parse_args(
		(array) get_option( 'brra_membership', array() ),
		array(
			'season_levels' => array(),  // Level IDs that run for a season and get the new-member price.
			'discount'      => 25,       // Percent off for first-time members.
		)
	);
}

/**
 * Past members' emails (lower case), from the list the committee keeps up to date.
 */
function brra_past_member_emails() {
	$list = get_option( 'brra_past_member_emails', array() );
	return is_array( $list ) ? $list : array();
}

/**
 * Has this email ever been a member? Checks the imported list and this site's own membership history.
 *
 * @param string $email Email address.
 */
function brra_is_returning_member( $email ) {
	global $wpdb;
	$email = strtolower( trim( $email ) );
	if ( ! $email ) {
		return false;
	}
	if ( in_array( $email, brra_past_member_emails(), true ) ) {
		return true;
	}
	$user = get_user_by( 'email', $email );
	if ( ! $user || empty( $wpdb->pmpro_memberships_users ) ) {
		return false;
	}
	// Any membership this person has held here, past or present, counts.
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pmpro_memberships_users} WHERE user_id = %d", $user->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Is this one of the season membership levels?
 *
 * @param object|int $level Level or level ID.
 */
function brra_is_season_level( $level ) {
	$id = is_object( $level ) ? (int) $level->id : (int) $level;
	return in_array( $id, array_map( 'intval', brra_membership_settings()['season_levels'] ), true );
}

/**
 * Email of the person checking out: the logged-in user's, or the one typed into the form.
 */
function brra_checkout_email() {
	if ( is_user_logged_in() ) {
		return wp_get_current_user()->user_email;
	}
	return isset( $_REQUEST['bemail'] ) ? sanitize_email( wp_unslash( $_REQUEST['bemail'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
}

/**
 * Discounted price for a full price.
 *
 * @param float $full Full price.
 */
function brra_new_member_price( $full ) {
	return round( (float) $full * ( 100 - (float) brra_membership_settings()['discount'] ) / 100, 2 );
}

/**
 * Season that a membership bought today covers, e.g. array( 2026, 2027 ). Seasons start on 1 June.
 *
 * @param int|null $timestamp When.
 */
function brra_current_season( $timestamp = null ) {
	$timestamp = $timestamp ? $timestamp : time();
	$year      = (int) wp_date( 'Y', $timestamp );
	$month     = (int) wp_date( 'n', $timestamp );
	return $month >= 6 ? array( $year, $year + 1 ) : array( $year - 1, $year );
}

// Apply the new-member price at checkout. The server decides; the page only previews it.
add_filter(
	'pmpro_checkout_level',
	function ( $level ) {
		if ( empty( $level ) || ! brra_is_season_level( $level ) || (float) $level->initial_payment <= 0 ) {
			return $level;
		}
		$email = brra_checkout_email();
		if ( is_email( $email ) && ! brra_is_returning_member( $email ) ) {
			$level->brra_full_price = $level->initial_payment;
			$level->initial_payment = brra_new_member_price( $level->initial_payment );
		}
		return $level;
	},
	20
);

// Season memberships end on 31 August at the end of the season.
add_filter(
	'pmpro_checkout_end_date',
	function ( $enddate, $user_id, $level ) {
		if ( ! brra_is_season_level( $level ) ) {
			return $enddate;
		}
		$season = brra_current_season();
		return $season[1] . '-08-31 23:59:59';
	},
	10,
	3
);

// Plain-English price and length on the checkout and levels pages.
add_filter(
	'pmpro_level_cost_text',
	function ( $text, $level ) {
		if ( ! brra_is_season_level( $level ) ) {
			return $text;
		}
		$season = brra_current_season();
		$full   = isset( $level->brra_full_price ) ? $level->brra_full_price : $level->initial_payment;
		return sprintf( 'The price for the %d/%02d season is <strong>%s</strong>.', $season[0], $season[1] % 100, pmpro_formatPrice( $full ) );
	},
	10,
	2
);
add_filter(
	'pmpro_level_expiration_text',
	function ( $text, $level ) {
		if ( ! brra_is_season_level( $level ) ) {
			return $text;
		}
		$season = brra_current_season();
		return sprintf( 'Membership runs until 31 August %d. Renewals are due on 1 June.', $season[1] );
	},
	10,
	2
);

// Price preview box under the level price; updated as the email is typed.
add_action(
	'pmpro_checkout_after_level_cost',
	function ( $level ) {
		if ( empty( $level ) || ! brra_is_season_level( $level ) ) {
			return;
		}
		$full = isset( $level->brra_full_price ) ? $level->brra_full_price : $level->initial_payment;
		$now  = $level->initial_payment;
		$new  = (float) $now < (float) $full;
		?>
		<div class="brra-price-box" id="brra-price-box" aria-live="polite"
			data-full="<?php echo esc_attr( pmpro_formatPrice( $full ) ); ?>"
			data-new="<?php echo esc_attr( pmpro_formatPrice( brra_new_member_price( $full ) ) ); ?>"
			data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-level="<?php echo esc_attr( $level->id ); ?>">
			<span>
				<span class="brra-price-box__label"><?php echo esc_html( $level->name ); ?></span>
				<span class="brra-price-box__note<?php echo $new ? ' is-new' : ''; ?>" data-role="note">
					<?php
					echo esc_html(
						$new
							? sprintf( 'New member price applied: %d%% off your first season.', brra_membership_settings()['discount'] )
							: ( is_user_logged_in() ? 'Welcome back. Thanks for renewing.' : sprintf( 'First time joining? Enter your email below and the %s price applies automatically.', pmpro_formatPrice( brra_new_member_price( $full ) ) ) )
					);
					?>
				</span>
			</span>
			<span>
				<span class="brra-price-box__was" data-role="was"<?php echo $new ? '' : ' hidden'; ?>><?php echo esc_html( pmpro_formatPrice( $full ) ); ?></span>
				<span class="brra-price-box__price" data-role="price"><?php echo esc_html( pmpro_formatPrice( $now ) ); ?></span>
			</span>
		</div>
		<?php
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( function_exists( 'pmpro_is_checkout' ) && pmpro_is_checkout() ) {
			wp_enqueue_script( 'brra-checkout', BRRA_SITE_URL . 'assets/checkout.js', array(), BRRA_SITE_VERSION, true );
		}
	}
);

/**
 * Price preview for an email typed at checkout.
 */
function brra_ajax_price_check() {
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$level = isset( $_POST['level'] ) ? absint( $_POST['level'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! is_email( $email ) || ! brra_is_season_level( $level ) ) {
		wp_send_json( array( 'state' => 'unknown' ) );
	}
	wp_send_json( array( 'state' => brra_is_returning_member( $email ) ? 'returning' : 'new' ) );
}
add_action( 'wp_ajax_brra_price_check', 'brra_ajax_price_check' );
add_action( 'wp_ajax_nopriv_brra_price_check', 'brra_ajax_price_check' );

// Settings screen: Memberships > New member price.
add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'pmpro-dashboard', 'New member price', 'New member price', 'pmpro_memberships_menu', 'brra-membership', 'brra_membership_admin_page' );
	},
	20
);

/**
 * Settings screen.
 */
function brra_membership_admin_page() {
	if ( isset( $_POST['brra_membership_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['brra_membership_nonce'] ), 'brra_membership_save' ) && current_user_can( 'pmpro_memberships_menu' ) ) {
		$raw    = isset( $_POST['past_emails'] ) ? sanitize_textarea_field( wp_unslash( $_POST['past_emails'] ) ) : '';
		$emails = array();
		foreach ( preg_split( '/[\s,;]+/', strtolower( $raw ) ) as $e ) {
			if ( is_email( $e ) ) {
				$emails[] = $e;
			}
		}
		update_option( 'brra_past_member_emails', array_values( array_unique( $emails ) ), false );
		$levels = isset( $_POST['season_levels'] ) ? array_map( 'absint', (array) $_POST['season_levels'] ) : array();
		update_option(
			'brra_membership',
			array(
				'season_levels' => $levels,
				'discount'      => isset( $_POST['discount'] ) ? min( 100, max( 0, (float) $_POST['discount'] ) ) : 25,
			)
		);
		echo '<div class="notice notice-success"><p>Saved.</p></div>';
	}
	$settings = brra_membership_settings();
	$levels   = function_exists( 'pmpro_getAllLevels' ) ? pmpro_getAllLevels( true ) : array();
	?>
	<div class="wrap">
		<h1>New member price</h1>
		<p>Anyone whose email isn't on the list below, and who has never held a membership on this site, automatically gets the new-member discount at checkout.</p>
		<form method="post">
			<?php wp_nonce_field( 'brra_membership_save', 'brra_membership_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">Discount for first-time members</th>
					<td><input type="number" name="discount" min="0" max="100" step="1" value="<?php echo esc_attr( $settings['discount'] ); ?>"> %</td>
				</tr>
				<tr>
					<th scope="row">Season memberships</th>
					<td>
						<?php foreach ( $levels as $l ) : ?>
							<label style="display:block"><input type="checkbox" name="season_levels[]" value="<?php echo esc_attr( $l->id ); ?>" <?php checked( brra_is_season_level( $l ) ); ?>> <?php echo esc_html( $l->name ); ?></label>
						<?php endforeach; ?>
						<p class="description">These run until 31 August after the season and get the new-member price.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="past_emails">Past members' emails</label></th>
					<td>
						<textarea id="past_emails" name="past_emails" rows="14" cols="60"><?php echo esc_textarea( implode( "\n", brra_past_member_emails() ) ); ?></textarea>
						<p class="description">One per line. Paste in everyone who has ever been a member, from the old website and the Membership Secretary's records. <?php echo esc_html( count( brra_past_member_emails() ) ); ?> on the list.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
