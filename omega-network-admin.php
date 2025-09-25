<?php
/**
 *  Plugin Name: OMEGA Network Admin
 *	Plugin URI: https://omegabenefits.net
 *  Description: For Multi-Site Networks only! Organizes site listings for easier management
 *  Version: 1.3.4
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

add_action( 'admin_enqueue_scripts', function() {
	wp_enqueue_style( 'ona-style', plugin_dir_url( __FILE__ ) . 'ona.css', array(), filemtime(plugin_dir_path( __FILE__ ) . 'ona.css'), 'all' );
	wp_enqueue_script( 'ona-script', plugin_dir_url( __FILE__ ) . 'ona.js', array(), filemtime(plugin_dir_path( __FILE__ ) . 'ona.js'), 'all' );
});
add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style( 'ona-style', plugin_dir_url( __FILE__ ) . 'ona.css', array(), filemtime(plugin_dir_path( __FILE__ ) . 'ona.css'), 'all' );
});

// force plugin update checks when on Updates page
add_action( 'admin_init', function() {
	if ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] == 'update-core.php' ) {
		// wp-includes\update.php
		wp_clean_update_cache();
		// wp-includes\update.php
		wp_update_themes();
		// wp-includes\update.php
		wp_update_plugins();		
	}
});

// hide blavatar site icons in My Sites admin bar menu (avoids tons of 404s when those icons are missing)
add_filter( 'wp_admin_bar_show_site_icons', '__return_false' );

// prevents fatal error when ray() doesn't exist (plugin missing)
// MUST CHECK AFTER ALL PLUGINS LOADED (alphabetically we're before Spatie Ray!)
// https://github.com/spatie/wordpress-ray/discussions/9
add_action("plugins_loaded", function() {
	if ( !function_exists('ray') ) {
	
		class Ray_Dummy_Class {
			function __call($funName, $arguments) {
				return new Ray_Dummy_Class();
			}
			static function ray(...$args) {
				return new Ray_Dummy_Class();
			}
			function __get($propertyName) {
				return null;
			}
			function __set($property, $value) {
			}
		}
	
		function ray(...$args) {
			// when Ray plugin is missing, we fallback to storing ray calls
			// append log data to transient, will be checked at shutdown hook to render <script> for console
			$log = get_transient( 'ray_console' );
			if ( empty( $log ) || !is_array( $log ) ) $log = array();
			if ( is_array( $args ) ) {
				foreach ( $args as $arg ) {
					$log[] = $arg;
				}
			}
			set_transient( 'ray_console', $log, HOUR_IN_SECONDS );
			//
			return Ray_Dummy_Class::ray(...$args);
		}
	}
});

// dev testing sandbox
add_action( 'shutdown', function() {
	if ( wp_doing_ajax() ) return;
});


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
		'omega_has_divisions'	=> "Divis",
		'omega_archive_toggle'	=> "Arch",
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
	$sortable_columns['omega_clientname']   = array(
		'omega_clientname', // Menu's internal name, same as key in array
		true, // Initialise with my specified order, false to disregard
		'Client Name', // Short column name (abbreviation) for `abbr` attribute
		'Table ordered by Client name alphabetically.', // Translatable string of a brief description to be used for the current sorting
	);
	$sortable_columns['omega_last_export']   = 'omega_last_export';
	$sortable_columns['omega_system_version']   = 'omega_system_version';
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
			case "omega_has_divisions":
				$content = ( empty( $option ) ) ? '-' : '<span class="dashicons dashicons-groups"></span>';
			break;
			case "omega_archive_toggle":
				$content = ( empty( $option ) ) ? '-' : '<span class="dashicons dashicons-backup"></span>';
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

// sorts my-sites tiles
add_filter('get_blogs_of_user','ona_sort_my_sites_tiles', 10, 1 );
function ona_sort_my_sites_tiles($blogs) {
	if ( empty( $blogs ) || !is_array( $blogs ) ) return $blogs;
	// remove multisite primary first
	unset( $blogs[1] );
	
	// extend blog objects with our custom data
	$extblogs = [];	
	foreach ( $blogs as $blog ) {
		$siteobj = get_site( $blog->userblog_id );
		$blog->updated = $siteobj->last_updated;
		$blog->system = get_blog_option( $blog->userblog_id, 'omega_system_version', '1.0' );
		$pm = get_blog_option( $blog->userblog_id, 'omega_projectmanager' );
		$user = get_user_by( 'login', $pm );
		$blog->pm = ( empty( $pm ) || empty( $user ) ) ? "-" : $user->display_name;
		$blog->export = get_blog_option( $blog->userblog_id, 'omega_last_export_time', '' );
		$blog->preview = get_blog_option( $blog->userblog_id, 'omega_topbar_enable' );
		$blog->lang = get_blog_option( $blog->userblog_id, 'omega_multi_lang' );
		$blog->divisions = get_blog_option( $blog->userblog_id, 'omega_has_divisions' );
		$blog->year = get_blog_option( $blog->userblog_id, 'omega_current_year' );
		$blog->archive = get_blog_option( $blog->userblog_id, 'omega_archive_toggle' );
		$blog->exporterrors = get_blog_option( $blog->userblog_id, 'omega_export_404s' );
		$blog->redirecterrors = get_blog_option( $blog->userblog_id, 'omega_redirect_fails' );
		
		// $extblogs[$blog->userblog_id] = $blog;
		$extblogs[] = $blog;
	}
	
	$sortby = $_GET['sortby'] ?? false;
	$filterby = $_GET['filterby'] ?? false;
	
	// filter by arg FIRST, this removes sites from $extblogs to be sorted following
	if ( $filterby ) {
		foreach ( $extblogs as $blog_id => $blog ) {
			switch ( $filterby ) {
				case "divisions":
					if ( !wp_validate_boolean( $blog->divisions ) ) unset( $extblogs[$blog_id] );
					break;
				case "archive":
					if ( !wp_validate_boolean( $blog->archive ) ) unset( $extblogs[$blog_id] );
					break;
				case "lang":
					if ( !wp_validate_boolean( $blog->lang ) ) unset( $extblogs[$blog_id] );
					break;
				case "preview":
					if ( !wp_validate_boolean( $blog->preview ) ) unset( $extblogs[$blog_id] );
					break;
				case "current":
					if ( wp_validate_boolean( $blog->preview ) ) unset( $extblogs[$blog_id] );
					break;
				case "errors":
					if ( !wp_validate_boolean( $blog->exporterrors ) && !wp_validate_boolean( $blog->redirecterrors ) ) unset( $extblogs[$blog_id] );
					break;
			}
		}
	}
	
	// sort by LAST UPDATED
	if ( $sortby == 'lastupdated' ) {
		usort( $extblogs, function( $a, $b ) {
			return intval( strtotime($b->updated) ) <=> intval( strtotime($a->updated) );
		});
		return $extblogs; // replace with our sort
	}
	
	// sort by LAST STATIC EXPORT
	if ( $sortby == 'omega_last_export' ) {
		usort( $extblogs, function( $a, $b ) {
			return intval( strtotime($b->export) ) <=> intval( strtotime($a->export) );
		});
		return $extblogs; // replace with our sort
	}
	
	// sort by TEMPLATE SYSTEM VERSION
	if ( $sortby == 'omega_system_version' ) {
		usort( $extblogs, function( $a, $b ) {
			return floatval( $b->system ) <=> floatval( $a->system );
		});
		return $extblogs; // replace with our sort
	}
	
	// sort by ARCHIVE STATUS
	if ( $sortby == 'omega_archive_toggle' ) {
		usort( $extblogs, function( $a, $b ) {
			return floatval( $b->archive ) <=> floatval( $a->archive );
		});
		return $extblogs; // replace with our sort
	}
	
	// sort by LANGUAGE TRANSLATE
	if ( $sortby == 'omega_multi_lang' ) {
		usort( $extblogs, function( $a, $b ) {
			return floatval( $b->lang ) <=> floatval( $a->lang );
		});
		return $extblogs; // replace with our sort
	}
	
	// sort by PROJECT MANAGER	
	if ( $sortby == 'projectmanager' ) {
		uasort( $extblogs, function($a, $b) { 
			return strcasecmp($a->pm,$b->pm);
		});
		return $extblogs;
	}
	
	if ( $sortby == 'name' ) {
		// doing this by default anyways
	}
	
	// sorts my sites by blogname alphabetically (default is by site id, thus order of creation)
	uasort( $extblogs, function($a, $b) { 
		return strcasecmp($a->blogname,$b->blogname);
	});
	return $extblogs;
}

// default site query is just 100, make it bigger
add_filter( 'ms_sites_list_table_query_args', function( $args ) {
	$args['number'] = 1000;
	return $args;
});

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
		$html .= "<div class='icon'><img src='".get_site_icon_url( 32, '', $blog_obj->userblog_id )."' title='#".$blog_obj->userblog_id."'/></div>";
		
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
		// show icon if has any divisions
		if ( get_blog_option( $blog_obj->userblog_id, 'omega_has_divisions' ) ) {
			$html .= "<span class='divisions dashicons dashicons-groups'></span>";
		}
		// show icon if archive active
		if ( get_blog_option( $blog_obj->userblog_id, 'omega_archive_toggle' ) ) {
			$html .= "<span class='archive dashicons dashicons-backup'></span>";
		}

		$pm = get_blog_option( $blog_obj->userblog_id, 'omega_projectmanager' );
		$user = get_user_by( 'login', $pm );
		
		$html .= "<p class='projectmanager'>";
		$html .= "<span class='dashicons dashicons-admin-users'></span>";
		$html .= ( empty( $pm ) || empty( $user ) ) ? "?" : $user->display_name ;
		$html .= "</p>";
		
		$client_id = get_blog_option( $blog_obj->userblog_id, 'omega_client_id' );
		if ( $client_id ) $html .= "<p class='clientid'><span class='dashicons dashicons-editor-customchar'></span>$client_id</p>";
		
		$client_nick = get_blog_option( $blog_obj->userblog_id, 'omega_client_nickname' );
		if ( $client_nick ) $html .= "<p class='clientnick'><span class='dashicons dashicons-admin-comments'></span>$client_nick</p>";
		
		$year = get_blog_option( $blog_obj->userblog_id, 'omega_current_year' );
		if ( $year ) $html .= "<p class='currentyear'><span class='dashicons dashicons-calendar-alt'></span>$year</p>";
		
		$lastexport = get_blog_option( $blog_obj->userblog_id, 'omega_last_export_time' );
		$errors = get_blog_option( $blog_obj->userblog_id, 'omega_export_404s' );
		$fails = get_blog_option( $blog_obj->userblog_id, 'omega_redirect_fails' );
		
		$html .= "<p class='lastexport'>";
		$html .= "<span class='label'>Last Static Export</span>";
		$html .= ( empty( $lastexport ) ) ? "- <br /><br />" : human_time_diff( strtotime( date( "Y-m-d H:i:s" ) ) , strtotime( $lastexport ) ) . " ago <br />";
		$html .= "</p>";
		
		// warning flags
		if ( $errors ) $html .= "<a class='flags errors' href='".$blog_obj->siteurl."/wp-admin/'>404 Errors &nbsp;<span class='dashicons dashicons-warning'></span></a>";
		if ( $fails ) $html .= "<a class='flags fails' href='".$blog_obj->siteurl."/wp-admin/'>Redirect Fails &nbsp;<span class='dashicons dashicons-warning'></span></a>";
		
		$netlify_id = get_blog_option( $blog_obj->userblog_id, 'omega_netlify_id' );
		if ( $netlify_id ) {
			$html .= "<p class='netlify'>";
			$html .= '<a class="nounderline" target="_blank" href="https://app.netlify.com/sites/omega-'.$client_id.'/deploys"><img src="https://api.netlify.com/api/v1/badges/'.$netlify_id.'/deploy-status" /></a>';
			$html .= "</p>";
		}
		
		return $html;
	}
}

// does seem to filter the table array, but doesn't keep migration from creating what we killed???
add_filter ( 'wpmdb_tables', 'ona_filter_migrate_tables', 99, 2);
function ona_filter_migrate_tables( $tables, $scope ) {
	$kill = [];
	foreach ( $tables as $key => $val ) {
		if ( str_contains( $val, 'simply_static_pages' ) ) {
			$kill[] = $val;
			// ray("filtering out table > ".$val);
		}
	}
	$tables = array_diff( $tables, $kill );
	return array_values( $tables );
}

/**
 * Add a new admin bar menu item.
 *
 * @param WP_Admin_Bar $admin_bar Admin bar reference.
 */
add_action( 'admin_bar_menu', 'omega_network_admin_bar_items', 100 );
function omega_network_admin_bar_items( $admin_bar ) {	
	if ( current_user_can( 'activate_plugins' ) ) {
		$admin_bar->add_node(
			array(
				'id'     => 'omega-network',
				'title'  => 'Network',
				'href'   => admin_url( 'my-sites.php?sortby=name' ),
			)
		);
		$admin_bar->add_node(
			array(
				'parent' => 'omega-network',
				'id'     => 'omega-sites-list-updated',
				'title'  => 'List by Last Updated',
				'href'   => network_admin_url( 'sites.php?orderby=lastupdated&order=desc' ),
			)
		);
		$admin_bar->add_node(
			array(
				'parent' => 'omega-network',
				'id'     => 'omega-sites-list-name',
				'title'  => 'List Sites by Name',
				'href'   => network_admin_url( 'sites.php?orderby=blogname&order=asc' ),
			)
		);
		$admin_bar->add_node(
			array(
				'parent' => 'omega-network',
				'id'     => 'omega-sites-grid-updated',
				'title'  => 'Grid by Last Updated',
				'href'   => admin_url( 'my-sites.php?sortby=lastupdated' ),
			)
		);
		$admin_bar->add_node(
			array(
				'parent' => 'omega-network',
				'id'     => 'omega-sites-grid-name',
				'title'  => 'Grid Layout by Name',
				'href'   => admin_url( 'my-sites.php?sortby=name' ),
			)
		);

		$admin_bar->add_node(
			array(
				'parent' => 'omega-network',
				'id'     => 'omega-users',
				'title'  => 'Users',
				'href'   => network_admin_url( 'users.php' ),
			)
		);
		$admin_bar->add_node(
			array(
				'parent' => 'omega-network',
				'id'     => 'omega-plugins',
				'title'  => 'Plugins',
				'href'   => network_admin_url( 'plugins.php' ),
			)
		);
		$admin_bar->add_node(
			array(
				'parent' => 'omega-network',
				'id'     => 'omega-addsite',
				'title'  => 'Add New Site',
				'href'   => network_admin_url( 'site-new.php' ),
			)
		);
		$admin_bar->add_node(
			array(
				'parent' => 'omega-network',
				'id'     => 'omega-migration',
				'title'  => 'Migration',
				'href'   => network_admin_url( 'settings.php?page=wp-migrate-db-pro' ),
			)
		);
		$admin_bar->add_node(
			array(
				'parent' => 'omega-network',
				'id'     => 'omega-export',
				'title'  => 'Export CSV',
				'href'   => network_admin_url( 'index.php?action=export_csv' ),
			)
		);
	}
}

// SHOW LOCAL DEV LABEL ONLY LOCALLY
$domain = explode( ".", parse_url( network_home_url(), PHP_URL_HOST ) );
$tld = end( $domain );
if ( $tld === 'local' ) {
	add_action( 'admin_bar_menu', 'omega_network_admin_bar_local', 101 );
}
function omega_network_admin_bar_local( $admin_bar ) {	
	$title = "LOCAL";
	if ( get_blog_option( get_current_blog_id(), 'omega_local_dev' ) ) $title = "LOCAL DEV";
	$admin_bar->add_node(
		array(
			'id'     => 'omega-local-dev',
			'title'  => $title,
		)
	);	
}


// renders sorting buttons on my sites grid page
add_action( 'myblogs_allblogs_options', function() {
	
	echo "<div id='modify-output'>";
	$sortby = ( $_GET['sortby'] ) ?? false;
	$filterby = ( $_GET['filterby'] ) ?? false;
	
	?>
	<div class="filterSites">
		<div class="label">FILTER<span style="vertical-align:middle; margin-right: 4px; margin-left: 4px;" class="dashicons dashicons-search"></span></div><input type='text' id='filterSites' placeholder='type client name'>
	</div>
	<?php
	
	echo "<a class='button button-secondary filterby' href='".add_query_arg( 'filterby', 'preview' )."' ".selected( $filterby, "preview", false ).">Filter by <span>Preview</span></a>";
	
	echo "<a class='button button-secondary filterby' href='".add_query_arg( 'filterby', 'current' )."' ".selected( $filterby, "current", false ).">Filter by <span>Current</span></a>";
	
	echo "<a class='button button-secondary filterby' href='".add_query_arg( 'filterby', 'archive' )."' ".selected( $filterby, "archive", false ).">Filter by <span>Archive</span></a>";
	
	echo "<a class='button button-secondary filterby' href='".add_query_arg( 'filterby', 'lang' )."' ".selected( $filterby, "lang", false ).">Filter by <span>Translation</span></a>";
	
	echo "<a class='button button-secondary filterby' href='".add_query_arg( 'filterby', 'divisions' )."' ".selected( $filterby, "divisions", false ).">Filter by <span>Divisions</span></a>";
	
	echo "<a class='button button-secondary filterby' href='".add_query_arg( 'filterby', 'errors' )."' ".selected( $filterby, "errors", false ).">Filter by <span>Errors</span></a>";
	
	echo "<a class='button button-secondary clearfilter' href='".remove_query_arg( 'filterby' )."'>Clear Filter</a>";
	
	?>
	<br/>
	<div class="sortSites">
		<div class="label">ORDER<span style="vertical-align:middle; margin-right: 4px; margin-left: 4px;" class="dashicons dashicons-arrow-down-alt"></span></div>
	</div>
	<?php
	
	echo "<a class='button button-secondary sortby' href='".add_query_arg( 'sortby', 'name' )."' ".selected( $sortby, "name", false ).">Sort by <span>Name</span></a>";
	
	echo "<a class='button button-secondary sortby' href='".add_query_arg( 'sortby', 'lastupdated' )."' ".selected( $sortby, "lastupdated", false ).">Sort by <span>Last Updated</span></a>";
	
	echo "<a class='button button-secondary sortby' href='".add_query_arg( 'sortby', 'omega_last_export' )."' ".selected( $sortby, "omega_last_export", false ).">Sort by <span>Last Export</span></a>";
	
	echo "<a class='button button-secondary sortby' href='".add_query_arg( 'sortby', 'projectmanager' )."' ".selected( $sortby, "projectmanager", false ).">Sort by <span>PM</span></a>";
	
	// echo "<a class='button button-secondary sortby' href='".add_query_arg( 'sortby', 'omega_system_version', admin_url( 'my-sites.php' ) )."' ".selected( $sortby, "omega_system_version", false ).">Sort by <span>System Version</span></a>";

	echo "</div>";

});


add_filter('wpmu_signup_user_notification', 'ona_signup_user_notification', 10, 4);
function ona_signup_user_notification($user, $user_email, $key, $meta) {
	// Manually activate the newly created user account
	wpmu_activate_signup( $key );
	// Return false to prevent WordPress from sending the user signup email (which includes the account activation link)
	return false;
}
add_action("admin_action_export_csv", "export_sites_csv");
function export_sites_csv() {
	$blogs = get_sites( [ 'number' => 999 ] ); // default query is 100
	if ( empty( $blogs ) || !is_array( $blogs ) ) return;
	array_shift( $blogs ); // remove primary network site
	$data = [];
	$data[] = [ "BlogID","ClientName","ClientID","TLD","StagingDomain","PublicDomain","PM","Language","Divisions" ];
	foreach ( $blogs as $blog ) {
		$line = [];
		$line[] = $blog->blog_id;
		$line[] = get_blog_option( $blog->blog_id, 'blogname');
		$line[] = get_blog_option( $blog->blog_id, 'omega_client_id' );
		$line[] = preg_replace("/^([a-zA-Z0-9].*\.)?([a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z.]{2,})$/", '$2', get_blog_option( $blog->blog_id, 'omega_public_domain' ) );
		$line[] = "https://".get_blog_option( $blog->blog_id, 'omega_local_domain' );
		$line[] = "https://".get_blog_option( $blog->blog_id, 'omega_public_domain' );
		$pm = get_blog_option( $blog->blog_id, 'omega_projectmanager' );
			$user = get_user_by( 'login', $pm );
		$line[] = ( empty( $pm ) || empty( $user ) ) ? "-" : $user->display_name;
		$line[] = get_blog_option( $blog->blog_id, 'omega_multi_lang' );
		$line[] = get_blog_option( $blog->blog_id, 'omega_has_divisions' );
		
		$data[] = $line;
	}
// ray($data);
	$filename = 'staging-sites-export.csv';
	$filepath = ABSPATH . $filename;
	$file = fopen( $filepath, "w");
	foreach ($data as $line) {
		fputcsv($file, $line);
	}
	$saved = fclose($file);
	
	// PHP headers for download
	ob_start();
	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header('Content-type: application/octet-stream');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Content-Transfer-Encoding: binary');
	header('Content-Length: ' . filesize( $filepath ) );
	header('Accept-Ranges: bytes');
	ob_end_clean();
	@readfile( $filepath );
	unlink( $filepath );
}