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

$ona_runtime_file = WPMU_PLUGIN_DIR . '/omega-network-admin/omega-network-admin.php';

// Give the runtime its logical WordPress path. Its __FILE__ may resolve to the
// target of a symlink, which is useful for local file reads but not for URLs.
define( 'ONA_MU_RUNTIME_FILE', $ona_runtime_file );

// Preserve this MU path when the runtime directory is a symlink. Without this
// mapping, PHP resolves __FILE__ to the symlink target and asset URLs point
// outside wp-content/mu-plugins.
wp_register_plugin_realpath( $ona_runtime_file );

require_once $ona_runtime_file;
