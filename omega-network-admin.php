<?php
/**
 *  Plugin Name: OMEGA Network Admin
 *	Plugin URI: https://omegabenefits.net
 *  Description: For Multi-Site Networks only! Organizes site listings for easier management
 *  Version: 1.2.2
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
	 "https://omegabenefits.net/wp-update-server/?action=get_metadata&slug=omega-network-admin", //Metadata URL.
	 __FILE__, //Full path to the main plugin file.
	 "omega-network-admin" //Plugin slug. Usually it's the same as the name of the directory.
 );


wp_enqueue_style( 'ona-style', plugin_dir_url( __FILE__ ) . 'ona.css', array(), "1.2.1", 'all' );


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
		// 'omega_plugin'			=> "Plugin",
		'omega_projectmanager' 	=> 'PM',
		'omega_system_version' 	=> 'System',
		'omega_last_export'		=> 'Last Static Export',
		'omega_topbar_enable'	=> "Preview",
		'omega_multi_lang'	    => "Lang",
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
				$content = ( empty( $option ) ) ? "<span style='color:#bbb;'>1.0</span>" : $option;
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
			case "omega_multi_lang":
				$content = ( empty( $option ) ) ? '-' : '<span class="dashicons dashicons-translation"></span>';
			break;
			case "omega_last_export":
				$content = ( empty( $option ) ) ? "-" : human_time_diff( strtotime( date( "Y-m-d H:i:s" ) ) , strtotime( $option ) ) . " ago <br />". $option;
			break;
			case "omega_projectmanager":
				$pm = get_blog_option( $blog_id, 'omega_projectmanager');
				$user = get_user_by( 'login', $pm );
				$content = ( empty( $pm ) || empty( $user ) ) ? "-" : $user->display_name;
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


function ona_sort_my_sites($blogs) {
	$fsort = function($a, $b) { 
		return strcasecmp($a->blogname,$b->blogname);
	};
	uasort($blogs, $fsort);
	return $blogs;
}
add_filter('get_blogs_of_user','ona_sort_my_sites');


// add_action( 'admin_bar_menu' , 'ona_admin_bar_menu' );
function ona_admin_bar_menu() {
	global $wp_admin_bar;
	
	$blog_names = array();
	$sites = $wp_admin_bar->user->blogs;
	foreach( $sites as $site_id=>$site ) {
		$blog_names[$site_id] = strtoupper( $site->blogname );
	}
	
	// Remove main blog from list...we want that to show at the top
	unset( $blog_names[1] );
	
	// Order by name
	asort( $blog_names );
	
	// Create new array
	$wp_admin_bar->user->blogs = array();
	
	// Add main blog back in to list
	if($sites[1]){
		$wp_admin_bar->user->blogs[1] = $sites[1];
	}
	
	// Add others back in alphabetically
	foreach( $blog_names as $site_id=>$name ) {
		$wp_admin_bar->user->blogs[$site_id] = $sites[$site_id];
	}
} 

add_filter('myblogs_blog_actions', 'ona_site_actions', 10, 2 );
function ona_site_actions($actions, $user_blog) {
	// +"userblog_id": 31
	//   +"blogname": "West Coast University"
	//   +"domain": "acc-wcu.omegastaging.local"
	//   +"path": "/"
	//   +"site_id": 1
	//   +"siteurl": "https://acc-wcu.omegastaging.local"
	//   +"archived": "0"
	//   +"mature": "0"
	//   +"spam": "0"
	//   +"deleted": "0"
	$publicdomain = get_blog_option( $user_blog->userblog_id, 'omega_public_domain' );
	
	// ray($actions, $user_blog);
	$new_actions = "";
	$new_actions .= "<a href='".$user_blog->siteurl."/wp-admin/'><span class='dashicons dashicons-admin-settings'></span>Dashboard</a>";
	$new_actions .= "<a href='".$user_blog->siteurl."'><span class='dashicons dashicons-welcome-view-site'></span>Staging</a>";
	$new_actions .= "<a href='https://".$publicdomain."' target='_blank'><span class='dashicons dashicons-admin-site-alt3'></span>Public</a>";
	return $new_actions;
}

// My Sites Tiles extras
// renders HTML output below each site's action links
add_filter( 'myblogs_options', 'ona_site_meta', 10, 2);
function ona_site_meta( $settings_html, $blog_obj ) {
	// only do for site boxes, not the global context
	if ( is_object( $blog_obj ) ) {
		$html = "";
		$html .= "<div class='icon'><img src='".get_site_icon_url( 32, '', $blog_obj->userblog_id )."'/></div>";
		
		$version = get_blog_option( $blog_obj->userblog_id, 'omega_system_version' );
		$html .= "<p class='version'>v";
		$html .= ( empty( $version ) ) ? "1.0" : $version;
		$html .= "</p>";
		
		// topbar preview mode
		if ( get_blog_option( $blog_obj->userblog_id, 'omega_topbar_enable' ) ) {
			$html .= "<span class='preview dashicons dashicons-flag'></span>";
		}
		// show icon if multiple languages
		if ( get_blog_option( $blog_obj->userblog_id, 'omega_multi_lang' ) ) {
			$html .= "<span class='lang dashicons dashicons-translation'></span>";
		}

		$pm = get_blog_option( $blog_obj->userblog_id, 'omega_projectmanager' );
		$user = get_user_by( 'login', $pm );
		
		$html .= "<p class='projectmanager'>";
		$html .= "<span class='dashicons dashicons-admin-users'></span>";
		$html .= ( empty( $pm ) || empty( $user ) ) ? "?" : $user->display_name ;
		$html .= "</p>";
		
		$lastexport = get_blog_option( $blog_obj->userblog_id, 'omega_last_export' );
		
		$html .= "<p class='lastexport'>";
		$html .= "<span class='label'>Last Static Export</span>";
		$html .= ( empty( $lastexport ) ) ? "- <br /><br />" : human_time_diff( strtotime( date( "Y-m-d H:i:s" ) ) , strtotime( $lastexport ) ) . " ago <br /><span class='rawtime'>". $lastexport. "</span>";
		$html .= "</p>";
		
		return $html;
	}
}

add_filter ( 'wpmdb_tables', 'ona_filter_migrate_tables', 99, 2);
function ona_filter_migrate_tables( $tables, $scope ) {
	foreach ( $tables as $key => $val ) {
		if ( str_contains( $val, 'simply_static_pages' ) ) {
			unset( $tables[$key] );
			ray("migration excluded > ".$val);
		}
	}
	// ray(array_values($tables))->hide();
	return array_values($tables);
}