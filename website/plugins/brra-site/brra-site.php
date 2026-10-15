<?php
/**
 * Plugin Name: BRRA Site
 * Description: Events, committee and membership rules for the Bury & Rochdale Referees' Association website.
 * Version: 0.1.0
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
define( 'BRRA_SITE_VERSION', '0.1.0' );

require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/people.php';
require_once __DIR__ . '/includes/membership.php';
