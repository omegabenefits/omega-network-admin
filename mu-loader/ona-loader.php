<?php
/**
 * Plugin Name: OMEGA Network Admin
 * Plugin URI: https://omegabenefits.net
 * Description: Required network-management tools for WordPress multisite.
 * Author: Omega Benefits
 * Author URI: https://omegabenefits.net
 * License: GPL-2.0+
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

// Install this file once, directly in wp-content/mu-plugins. WordPress loads
// must-use plugins before network-activated and regular plugins; routine
// releases only replace the omega-network-admin runtime directory.
define( 'ONA_MU_PLUGIN_FILE', basename( __FILE__ ) );

require_once WPMU_PLUGIN_DIR . '/omega-network-admin/omega-network-admin.php';
