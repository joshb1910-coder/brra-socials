<?php
/**
 * Events: a simple event type with date, times, RSVP and calendar files.
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

const BRRA_DEFAULT_VENUE = "St Mary's Social Club, Pine Street, Radcliffe, M26 2WQ";

add_action(
	'init',
	function () {
		register_post_type(
			'brra_event',
			array(
				'labels'        => array(
					'name'          => __( 'Events', 'brra' ),
					'singular_name' => __( 'Event', 'brra' ),
					'add_new_item'  => __( 'Add event', 'brra' ),
					'edit_item'     => __( 'Edit event', 'brra' ),
					'all_items'     => __( 'All events', 'brra' ),
				),
				'public'        => true,
				'show_in_rest'  => true,
				'menu_icon'     => 'dashicons-calendar-alt',
				'menu_position' => 5,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'rewrite'       => array( 'slug' => 'events' ),
				'has_archive'   => false,
			)
		);
		foreach ( brra_event_fields() as $key => $field ) {
			register_post_meta(
				'brra_event',
				'_brra_' . $key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
		add_shortcode( 'brra_next_event', 'brra_shortcode_next_event' );
		add_shortcode( 'brra_event_cards', 'brra_shortcode_event_cards' );
		add_shortcode( 'brra_events_list', 'brra_shortcode_events_list' );
		add_shortcode( 'brra_event_details', 'brra_shortcode_event_details' );
		add_shortcode( 'brra_rsvp', 'brra_shortcode_rsvp' );
	}
);

/**
 * Editable fields on each event.
 */
function brra_event_fields() {
	return array(
		'date'     => array( 'label' => 'Date', 'type' => 'date' ),
		'doors'    => array( 'label' => 'Doors open', 'type' => 'time', 'default' => '19:00' ),
		'start'    => array( 'label' => 'Starts', 'type' => 'time', 'default' => '19:30' ),
		'end'      => array( 'label' => 'Finishes', 'type' => 'time', 'default' => '21:30' ),
		'line'     => array( 'label' => 'One-line summary (shown in lists)', 'type' => 'text' ),
		'tag'      => array( 'label' => 'Tag (optional, e.g. Social or Provisional date)', 'type' => 'text' ),
		'location' => array( 'label' => 'Venue', 'type' => 'text', 'default' => BRRA_DEFAULT_VENUE ),
		'rsvp'     => array( 'label' => 'Take RSVPs', 'type' => 'checkbox', 'default' => '1' ),
	);
}

/**
 * One field of an event, with its default.
 *
 * @param int    $post_id Event ID.
 * @param string $key     Field key.
 */
function brra_event_get( $post_id, $key ) {
	$value  = get_post_meta( $post_id, '_brra_' . $key, true );
	$fields = brra_event_fields();
	if ( '' === $value && isset( $fields[ $key ]['default'] ) ) {
		return $fields[ $key ]['default'];
	}
	return $value;
}

/**
 * Start of an event as a DateTimeImmutable in the site's timezone.
 *
 * @param int    $post_id Event ID.
 * @param string $which   start, doors or end.
 */
function brra_event_time( $post_id, $which = 'start' ) {
	$date = brra_event_get( $post_id, 'date' );
	if ( ! $date ) {
		return null;
	}
	$time = brra_event_get( $post_id, $which );
	$dt   = date_create_immutable( $date . ' ' . ( $time ? $time : '00:00' ), wp_timezone() );
	return $dt ? $dt : null;
}

/**
 * Upcoming events, soonest first.
 *
 * @param int $limit How many (-1 for all).
 */
function brra_upcoming_events( $limit = -1 ) {
	return get_posts(
		array(
			'post_type'      => 'brra_event',
			'posts_per_page' => $limit,
			'meta_key'       => '_brra_date', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => '_brra_date',
					'value'   => wp_date( 'Y-m-d' ),
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		)
	);
}

/**
 * "7.00pm" style time.
 *
 * @param DateTimeImmutable|null $dt Time.
 */
function brra_pretty_time( $dt ) {
	if ( ! $dt ) {
		return '';
	}
	return strtolower( $dt->format( 'g.i' ) . $dt->format( 'A' ) );
}

/**
 * "Doors 7.00pm, start 7.30pm".
 *
 * @param int $post_id Event ID.
 */
function brra_event_times_text( $post_id ) {
	$doors = brra_event_get( $post_id, 'doors' ) ? brra_event_time( $post_id, 'doors' ) : null;
	$start = brra_event_time( $post_id, 'start' );
	if ( $doors && $start && $doors < $start ) {
		return sprintf( 'Doors %s, start %s', brra_pretty_time( $doors ), brra_pretty_time( $start ) );
	}
	return $start ? 'Starts ' . brra_pretty_time( $start ) : '';
}

/**
 * Image URL for an event.
 *
 * @param int    $post_id Event ID.
 * @param string $size    Image size.
 */
function brra_event_image( $post_id, $size = 'large' ) {
	$url = get_the_post_thumbnail_url( $post_id, $size );
	return $url ? $url : '';
}

/**
 * Add-to-calendar link.
 *
 * @param int $post_id Event ID.
 */
function brra_event_ics_url( $post_id ) {
	return add_query_arg( 'brra_ics', $post_id, home_url( '/' ) );
}

/**
 * [brra_next_event compact="1"]
 *
 * @param array $atts Shortcode attributes.
 */
function brra_shortcode_next_event( $atts ) {
	$atts   = shortcode_atts( array( 'compact' => '0' ), $atts );
	$events = brra_upcoming_events( 1 );
	if ( ! $events ) {
		return '<p>No events are planned right now. Check back soon.</p>';
	}
	$e       = $events[0];
	$start   = brra_event_time( $e->ID );
	$compact = '1' === $atts['compact'];
	$img     = brra_event_image( $e->ID );
	$line    = $compact ? brra_event_get( $e->ID, 'line' ) . ' ' . brra_event_times_text( $e->ID ) . ' at ' . strtok( brra_event_get( $e->ID, 'location' ), ',' ) . '.' : ( has_excerpt( $e ) ? get_the_excerpt( $e ) : brra_event_get( $e->ID, 'line' ) );

	ob_start();
	?>
	<div class="brra-next<?php echo $compact ? ' is-compact is-raised' : ''; ?>">
		<?php if ( $img ) : ?>
			<img class="brra-next__img" src="<?php echo esc_url( $img ); ?>" alt="">
		<?php endif; ?>
		<div class="brra-next__body">
			<span class="brra-next__date"><?php echo $compact ? 'Next up · ' : ''; ?><?php echo esc_html( $start ? wp_date( 'l j F Y', $start->getTimestamp() ) : '' ); ?></span>
			<h2 class="brra-next__title"><?php echo esc_html( get_the_title( $e ) ); ?></h2>
			<p class="brra-next__line"><?php echo esc_html( trim( $line ) ); ?></p>
			<?php if ( ! $compact ) : ?>
				<p class="brra-next__line"><strong><?php echo esc_html( brra_event_times_text( $e->ID ) ); ?></strong> · <?php echo esc_html( brra_event_get( $e->ID, 'location' ) ); ?></p>
			<?php endif; ?>
			<div class="brra-next__actions">
				<?php if ( brra_event_get( $e->ID, 'rsvp' ) ) : ?>
					<a class="brra-btn" href="<?php echo esc_url( get_permalink( $e ) . '#rsvp' ); ?>">I'm going</a>
				<?php endif; ?>
				<?php if ( $compact ) : ?>
					<a class="brra-btn is-ghost" href="<?php echo esc_url( get_permalink( $e ) ); ?>">Event details</a>
				<?php else : ?>
					<a class="brra-btn is-ghost" href="<?php echo esc_url( brra_event_ics_url( $e->ID ) ); ?>">Add to calendar</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * [brra_event_cards count="3" skip="1"]
 *
 * @param array $atts Shortcode attributes.
 */
function brra_shortcode_event_cards( $atts ) {
	$atts   = shortcode_atts( array( 'count' => 3, 'skip' => 1 ), $atts );
	$events = array_slice( brra_upcoming_events( (int) $atts['count'] + (int) $atts['skip'] ), (int) $atts['skip'] );
	if ( ! $events ) {
		return '';
	}
	$out = '<div class="brra-event-cards">';
	foreach ( $events as $e ) {
		$start = brra_event_time( $e->ID );
		$img   = brra_event_image( $e->ID );
		$out  .= '<a class="brra-event-card" href="' . esc_url( get_permalink( $e ) ) . '">';
		$out  .= $img ? '<img src="' . esc_url( $img ) . '" alt="">' : '';
		$out  .= '<span class="brra-event-card__body"><span class="brra-event-card__date">' . esc_html( $start ? wp_date( 'D j F Y', $start->getTimestamp() ) : '' ) . '</span>';
		$out  .= '<span class="brra-event-card__title">' . esc_html( get_the_title( $e ) ) . '</span>';
		$out  .= '<span class="brra-event-card__line">' . esc_html( brra_event_get( $e->ID, 'line' ) ) . '</span></span></a>';
	}
	return $out . '</div>';
}

/**
 * [brra_events_list skip="1"]
 *
 * @param array $atts Shortcode attributes.
 */
function brra_shortcode_events_list( $atts ) {
	$atts   = shortcode_atts( array( 'skip' => 1 ), $atts );
	$events = array_slice( brra_upcoming_events(), (int) $atts['skip'] );
	if ( ! $events ) {
		return '<p>More dates will be added soon.</p>';
	}
	$out       = '<div class="brra-events">';
	$last_year = null;
	foreach ( $events as $e ) {
		$start = brra_event_time( $e->ID );
		if ( ! $start ) {
			continue;
		}
		$year = $start->format( 'Y' );
		if ( null !== $last_year && $year !== $last_year ) {
			$out .= '<div class="brra-event-year">' . esc_html( $year ) . '</div>';
		}
		$last_year = $year;
		$img       = brra_event_image( $e->ID, 'medium_large' );
		$tag       = brra_event_get( $e->ID, 'tag' );
		$out      .= '<div class="brra-event-row">';
		$out      .= '<div class="brra-datebox" aria-hidden="true"><b>' . esc_html( $start->format( 'j' ) ) . '</b><span>' . esc_html( strtoupper( $start->format( 'M' ) ) ) . '</span></div>';
		$out      .= $img ? '<img src="' . esc_url( $img ) . '" alt="">' : '';
		$out      .= '<div class="brra-event-row__text"><a class="brra-event-row__title" href="' . esc_url( get_permalink( $e ) ) . '">' . esc_html( get_the_title( $e ) ) . '</a>';
		$out      .= $tag ? '<span class="brra-tag">' . esc_html( $tag ) . '</span>' : '';
		$out      .= '<span class="brra-event-row__line"><span class="screen-reader-text">' . esc_html( wp_date( 'l j F Y', $start->getTimestamp() ) ) . '. </span>' . esc_html( brra_event_get( $e->ID, 'line' ) ) . '</span></div>';
		$out      .= '<a class="brra-event-row__more" href="' . esc_url( get_permalink( $e ) ) . '" aria-label="' . esc_attr( 'Details: ' . get_the_title( $e ) ) . '">Details</a>';
		$out      .= '</div>';
	}
	return $out . '</div>';
}

/**
 * [brra_event_details] on a single event.
 */
function brra_shortcode_event_details() {
	$id    = get_the_ID();
	$start = brra_event_time( $id );
	if ( ! $start ) {
		return '';
	}
	$out  = '<div class="brra-facts" style="max-width:none">';
	$out .= '<div><small>WHEN</small>' . esc_html( wp_date( 'l j F Y', $start->getTimestamp() ) ) . '</div>';
	$out .= '<div><small>TIME</small>' . esc_html( brra_event_times_text( $id ) ) . '</div>';
	$out .= '<div><small>WHERE</small>' . esc_html( brra_event_get( $id, 'location' ) ) . '</div>';
	$out .= '</div>';
	$out .= '<p style="margin-top:20px"><a class="brra-btn is-gold" href="' . esc_url( brra_event_ics_url( $id ) ) . '">Add to calendar</a> <a class="brra-btn is-ghost" style="border-color:#fff;color:#fff" href="https://www.google.com/maps/search/?api=1&amp;query=' . rawurlencode( brra_event_get( $id, 'location' ) ) . '">Directions</a></p>';
	return $out;
}

/**
 * [brra_rsvp] form on a single event.
 */
function brra_shortcode_rsvp() {
	$id = get_the_ID();
	if ( 'brra_event' !== get_post_type( $id ) || ! brra_event_get( $id, 'rsvp' ) ) {
		return '';
	}
	$start = brra_event_time( $id );
	if ( $start && $start < new DateTimeImmutable( 'now', wp_timezone() ) ) {
		return '<p>This event has finished.</p>';
	}
	$list  = get_post_meta( $id, '_brra_rsvps', true );
	$count = is_array( $list ) ? count( $list ) : 0;
	$done  = isset( $_GET['rsvp'] ) && 'thanks' === $_GET['rsvp']; // phpcs:ignore WordPress.Security.NonceVerification
	ob_start();
	?>
	<div class="brra-rsvp" id="rsvp">
		<h2 style="margin:0;font-size:24px">Coming along?</h2>
		<?php if ( $done ) : ?>
			<p class="brra-notice">Thanks, you're on the list. See you there.</p>
		<?php else : ?>
			<p style="margin:0">Let us know so we can plan food and seating. Members and non-members are welcome.</p>
			<form class="brra-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:grid;gap:14px">
				<input type="hidden" name="action" value="brra_rsvp">
				<input type="hidden" name="event" value="<?php echo esc_attr( $id ); ?>">
				<?php wp_nonce_field( 'brra_rsvp_' . $id ); ?>
				<label>Your name <input type="text" name="rsvp_name" required autocomplete="name"></label>
				<label>Email <input type="email" name="rsvp_email" required autocomplete="email"></label>
				<label style="position:absolute;left:-9999px" aria-hidden="true">Leave blank <input type="text" name="rsvp_website" tabindex="-1" autocomplete="off"></label>
				<p style="margin:0"><button class="brra-btn" type="submit">I'm going</button></p>
			</form>
		<?php endif; ?>
		<?php if ( $count ) : ?>
			<p style="margin:0;font-size:14px;color:var(--wp--preset--color--slate)"><?php echo esc_html( sprintf( _n( '%d person is going so far.', '%d people are going so far.', $count, 'brra' ), $count ) ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	// Collapse whitespace between tags so wpautop doesn't add empty paragraphs.
	return preg_replace( '/>\s+</', '><', ob_get_clean() );
}

/**
 * Save an RSVP.
 */
function brra_handle_rsvp() {
	$id = isset( $_POST['event'] ) ? absint( $_POST['event'] ) : 0;
	if ( ! $id || 'brra_event' !== get_post_type( $id ) || ! check_admin_referer( 'brra_rsvp_' . $id ) ) {
		wp_die( 'Sorry, something went wrong. Please go back and try again.' );
	}
	if ( ! empty( $_POST['rsvp_website'] ) ) {
		wp_safe_redirect( get_permalink( $id ) );
		exit;
	}
	$name  = isset( $_POST['rsvp_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rsvp_name'] ) ) : '';
	$email = isset( $_POST['rsvp_email'] ) ? sanitize_email( wp_unslash( $_POST['rsvp_email'] ) ) : '';
	if ( $name && is_email( $email ) ) {
		$list = get_post_meta( $id, '_brra_rsvps', true );
		$list = is_array( $list ) ? $list : array();
		$list[ strtolower( $email ) ] = array( 'name' => $name, 'email' => $email, 'at' => current_time( 'mysql' ) );
		update_post_meta( $id, '_brra_rsvps', $list );
	}
	wp_safe_redirect( add_query_arg( 'rsvp', 'thanks', get_permalink( $id ) ) . '#rsvp' );
	exit;
}
add_action( 'admin_post_brra_rsvp', 'brra_handle_rsvp' );
add_action( 'admin_post_nopriv_brra_rsvp', 'brra_handle_rsvp' );

// Calendar file download.
add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['brra_ics'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$id    = absint( $_GET['brra_ics'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$start = brra_event_time( $id );
		if ( 'brra_event' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || ! $start ) {
			return;
		}
		$end  = brra_event_time( $id, 'end' );
		$end  = ( $end && $end > $start ) ? $end : $start->modify( '+2 hours' );
		$utc  = new DateTimeZone( 'UTC' );
		$esc  = function ( $s ) {
			return str_replace( array( '\\', ';', ',', "\n" ), array( '\\\\', '\;', '\,', '\n' ), $s );
		};
		$body = implode(
			"\r\n",
			array(
				'BEGIN:VCALENDAR',
				'VERSION:2.0',
				'PRODID:-//Bury & Rochdale RA//Events//EN',
				'BEGIN:VEVENT',
				'UID:brra-event-' . $id . '@' . wp_parse_url( home_url(), PHP_URL_HOST ),
				'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
				'DTSTART:' . $start->setTimezone( $utc )->format( 'Ymd\THis\Z' ),
				'DTEND:' . $end->setTimezone( $utc )->format( 'Ymd\THis\Z' ),
				'SUMMARY:' . $esc( html_entity_decode( get_the_title( $id ) ) ),
				'LOCATION:' . $esc( brra_event_get( $id, 'location' ) ),
				'URL:' . get_permalink( $id ),
				'END:VEVENT',
				'END:VCALENDAR',
			)
		);
		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_title( get_the_title( $id ) ) . '.ics"' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
);

// Event details box in the editor.
add_action(
	'add_meta_boxes_brra_event',
	function () {
		add_meta_box( 'brra_event_details', __( 'Event details', 'brra' ), 'brra_event_meta_box', 'brra_event', 'side', 'high' );
		add_meta_box( 'brra_event_rsvps', __( 'Who is going', 'brra' ), 'brra_event_rsvp_box', 'brra_event', 'normal', 'default' );
	}
);

/**
 * Event details fields.
 *
 * @param WP_Post $post Event.
 */
function brra_event_meta_box( $post ) {
	wp_nonce_field( 'brra_event_save', 'brra_event_nonce' );
	foreach ( brra_event_fields() as $key => $field ) {
		$value = brra_event_get( $post->ID, $key );
		echo '<p><label style="display:block;font-weight:600;margin-bottom:4px" for="brra_' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . '</label>';
		if ( 'checkbox' === $field['type'] ) {
			echo '<input type="checkbox" id="brra_' . esc_attr( $key ) . '" name="brra[' . esc_attr( $key ) . ']" value="1"' . checked( $value, '1', false ) . '>';
		} else {
			echo '<input style="width:100%" type="' . esc_attr( $field['type'] ) . '" id="brra_' . esc_attr( $key ) . '" name="brra[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
		}
		echo '</p>';
	}
}

/**
 * List of RSVPs.
 *
 * @param WP_Post $post Event.
 */
function brra_event_rsvp_box( $post ) {
	$list = get_post_meta( $post->ID, '_brra_rsvps', true );
	if ( ! is_array( $list ) || ! $list ) {
		echo '<p>No one yet.</p>';
		return;
	}
	echo '<p><strong>' . count( $list ) . '</strong> going.</p><table class="widefat striped"><thead><tr><th>Name</th><th>Email</th><th>When</th></tr></thead><tbody>';
	foreach ( $list as $row ) {
		echo '<tr><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( $row['email'] ) . '</td><td>' . esc_html( $row['at'] ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

add_action(
	'save_post_brra_event',
	function ( $post_id ) {
		if ( ! isset( $_POST['brra_event_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['brra_event_nonce'] ), 'brra_event_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$input = isset( $_POST['brra'] ) ? (array) wp_unslash( $_POST['brra'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( brra_event_fields() as $key => $field ) {
			$value = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
			if ( 'checkbox' === $field['type'] ) {
				$value = $value ? '1' : '0';
			}
			update_post_meta( $post_id, '_brra_' . $key, $value );
		}
	}
);

// Date column in the events list.
add_filter(
	'manage_brra_event_posts_columns',
	function ( $cols ) {
		return array_slice( $cols, 0, 2, true ) + array( 'brra_date' => 'Event date', 'brra_going' => 'Going' ) + $cols;
	}
);
add_action(
	'manage_brra_event_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'brra_date' === $col ) {
			$start = brra_event_time( $post_id );
			echo esc_html( $start ? wp_date( 'D j M Y, g.ia', $start->getTimestamp() ) : '—' );
		} elseif ( 'brra_going' === $col ) {
			$list = get_post_meta( $post_id, '_brra_rsvps', true );
			echo esc_html( is_array( $list ) ? count( $list ) : 0 );
		}
	},
	10,
	2
);
