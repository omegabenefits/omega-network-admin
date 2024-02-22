<?php
/**
 *  Plugin Name: OMEGA Network Admin
 *	Plugin URI: https://omegabenefits.net
 *  Description: For Multi-Site Networks only! Organizes site listings for easier management
 *  Version: 1.0.1
 *  Author: Omega Benefits
 *	Author URI: https://omegabenefits.net
 *  License: GPL-2.0+
 *	Network: true
 */


 /**
  * 3rd-party class for our self-hosted updates
  */
 require_once plugin_dir_path( __FILE__ ) . "plugin-update-checker/plugin-update-checker.php";
 use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
 $MyUpdateChecker = PucFactory::buildUpdateChecker(
	 "http://dashboard.hrbenefits.net/wp-update-server/?action=get_metadata&slug=omega-network-admin", //Metadata URL.
	 __FILE__, //Full path to the main plugin file.
	 "omega-network-admin" //Plugin slug. Usually it's the same as the name of the directory.
 );


wp_enqueue_style( 'ona-style', plugin_dir_url( __FILE__ ) . 'ona.css', array(), "1.0.1", 'all' );


/**
 * Adds the columns headings
 * NOTE: the array keys for Omega content need to match the saved wp_options IDs
 *
 * @param  array $columns
 * @return array
 */
function omeganetwork_add_sites_column_heading( $columns ) {
	$custom = array(
		'cb'      				=> $columns['cb'],
		'blogname' 				=> 'Staging Domain',
		'omega_clientname' 		=> "Client",
		'omega_public_domain'	=> 'Public Domain',
		'omega_plugin'			=> "Plugin",
		'omega_system_version' 	=> 'System',
		'omega_last_export'		=> 'Last Static Export',
		'omega_topbar_enable'	=> "Topbar",

		'lastupdated'			=> 'Last Updated',
		// 'blog_id'				=> 'Subsite ID',
		// 'public'				=> 'Public',
		'users'					=> 'Users',
	);
	return $custom;
}
add_filter( 'wpmu_blogs_columns', 'omeganetwork_add_sites_column_heading', 99 );


/**
 * Adds the sortable columns
 *
 * @param  array $sortable_columns
 * @return array
 */
function omeganetwork_columns_sortable( $sortable_columns ) {
	// $sortable_columns['omega_system_version']  = 'omega_system_version';
	$sortable_columns['omega_last_export']   = 'omega_last_export';
	// $sortable_columns['blog_id']  = 'blog_id';
	// $sortable_columns['public']   = 'public';
	// $sortable_columns['deleted']  = 'deleted';
	// $sortable_columns['archived'] = 'archived';
	// $sortable_columns['site_id']  = 'site_id';
	return $sortable_columns;
}
add_filter( 'manage_sites-network_sortable_columns', 'omeganetwork_columns_sortable' );



/**
 * Adds the content of the columns
 *
 * @global wpdb $wpdb WordPress database object
 * @param string $column_name
 * @param int $blog_id
 */
function omeganetwork_columns_content( $column_name, $blog_id ) {
	// for omega options
	if ( str_contains( $column_name, "omega_" ) ) {
		$option = get_blog_option( $blog_id, $column_name );
		$content = "";
		switch ( $column_name ) {
			case "omega_system_version":
				$content = ( empty( $option ) ) ? "-" : $option;
			break;
			case "omega_public_domain":
				$content = ( empty( $option ) ) ? "-" : "<a href='https://{$option}' target='_blank'>".$option."</a>";
			break;
			case "omega_clientname":
				// this is a weird one because the ID 'blogname' is being used by Core to display the primary column with all the links and hover, but instead of showing the actual blogname, it renders local domain name! So we are fetching blogname to display in our custom column (there's no omega_clientname option, just a dummy ID)
				$content = get_blog_option( $blog_id, 'blogname');
			break;
			case "omega_topbar_enable":
				$content = ( empty( $option ) ) ? '-' : '<span class="dashicons dashicons-flag"></span>';
			break;
			case "omega_last_export":
				$content = ( empty( $option ) ) ? "-" : human_time_diff( strtotime( date( "Y-m-d H:i:s" ) ) , strtotime( $option ) ) . " ago <br />". $option;
			break;
			case "omega_plugin":
				switch_to_blog( $blog_id );
				if ( in_array( "omega-system/omega-system.php", (array) get_option( 'active_plugins', array() ) ) ) {
					$content = '<span class="dashicons dashicons-superhero-alt"></span>';
				} else {
					$content = "-";	
				}
				restore_current_blog();
			break;
			default:
				$content = "-";
			break;
		}
		echo $content;
		return;
	}

	// if it's a wp_blogs column
	global $wpdb;
	// Find the columns of wp_blogs table.
	$existing_columns = $wpdb->get_col( 'DESC ' . $wpdb->base_prefix . 'blogs' );
	
	// array(
	//   0 => "blog_id",
	//   1 => "site_id",
	//   2 => "domain",
	//   3 => "path",
	//   4 => "registered",
	//   5 => "last_updated",
	//   6 => "public",
	//   7 => "archived",
	//   8 => "mature",
	//   9 => "spam",
	//   10 => "deleted",
	//   11 => "lang_id"
	// )
	
	if ( in_array( $column_name, $existing_columns, true ) ) {
		$prepared_statement = $wpdb->prepare( 'SELECT ' . esc_sql( $column_name ) . ' from ' . $wpdb->base_prefix . 'blogs where blog_id= %d', $blog_id );
		$content = $wpdb->get_var( $prepared_statement );
		echo esc_html( $content );
	}
}
add_action( 'manage_sites_custom_column', 'omeganetwork_columns_content', 10, 2 );