<?php
/**
 * Plugin Name: BRRA Site
 * Description: Events, committee and membership rules for Bury & Rochdale Referees' Association website.
 * Version: 0.5.0
 * Author: Bury & Rochdale RA
 * License: GPL-2.0-or-later
 * Requires PHP: 7.4
 * Text Domain: brra
 *
 * @package brra-site
 */

defined( 'ABSPATH' ) || exit;

define( 'BRRA_SITE_DIR', __DIR__ );
define( 'BRRA_SITE_URL', plugin_dir_url( __FILE__ ) );
define( 'BRRA_SITE_VERSION', '0.5.0' );

require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/people.php';
require_once __DIR__ . '/includes/membership.php';
require_once __DIR__ . '/includes/welcome-email.php';
require_once __DIR__ . '/includes/contact.php';
require_once __DIR__ . '/includes/attendance.php';
require_once __DIR__ . '/includes/newsletter.php';
require_once __DIR__ . '/includes/committee.php';
require_once __DIR__ . '/includes/committee-views.php';
require_once __DIR__ . '/includes/committee-money.php';
require_once __DIR__ . '/includes/wix.php';
require_once __DIR__ . '/includes/setup.php';
