<?php
/*
   Plugin Name: WordPress Easy Custom Error Pages
   Version: 1.0.0
   Description: 
   Plugin URI: http://www.mattroyal.co.za/plugins/wordpress-easy-custom-error-pages/
   Author: Matt Royal
   Author URI: http://www.mattroyal.co.za/
   Requires at least: 3.8
   Tested up to: 3.9.1
   Text Domain: easy-custom-error-pages
   License: GPLv3
  */

if ( !defined( 'ABSPATH' ) ) exit;

function wcep_i18n_init() {
		$pluginDir = dirname(plugin_basename(__FILE__));
		load_plugin_textdomain('easy-custom-error-pages', false, $pluginDir . '/lang/');
	}

wcep_i18n_init();

///////////////////////////////////////////////////////////////////
 // 1. Check .htaccess & wp-contetent/themes folder writeable //
//////////////////////////////////////////////////////////////////

function wcep_check_permissions() {
	
	$check_files = array( $htaccess = get_home_path() .'.htaccess', $theme = get_stylesheet_directory() );
	$message = '';
	
		foreach ($check_files as $file) {
			
			if (! is_writable(dirname($file))) {
				echo $message .= '<div class="error"><p><strong> ' . dirname($file) . '</strong> is not writable! Please CHMOD 777</p></div>';
			} 
			
		}

}

add_action( 'admin_notices', 'wcep_check_permissions' );

///////////////////////////////////////////////////////////////////
 // 2. Duplicate page.php rename and create page templates //
 // 401.php, 403.php, 500.php >> Template Name: Error 401
//////////////////////////////////////////////////////////////////

function wcep_create_error_pages() {
	
	if( is_writable( dirname ( get_stylesheet_directory() ) ) ) {
	  
		$error_codes = array( 
			'Bad Request' 			=> '400',
			'Unauthorized' 			=> '401', 
			'Payment Required' 		=> '402',
			'permission_denied' 	=> '403',
			'page_not_found' 		=> '404' ,
			'Request Timeout' 		=> '408',
			'internal_server_error' => '500',
			'Bad Gateway' 			=> '502',
			'Service Unavailable' 	=> '503',
		  	'Gateway Timeout'		=> '504'
		);
	
	  	foreach ($error_codes as $error_code => $error_value) {
	
			// create a template file for the single post type if it doesn't exist    
			if(!file_exists(get_stylesheet_directory() . '/' . $error_value . '.php')) {
				
				if(file_exists(get_stylesheet_directory() . '/page.php')) {
					
					copy(get_stylesheet_directory() . '/page.php', get_stylesheet_directory() . '/' . $error_value . '.php');
				}
			}
			
		}
		
	}
}

add_action( 'admin_init', 'wcep_create_error_pages' );

function wcep_create_page_templates() {
	
	$error_codes = array( 
			'Bad Request' 			=> '400',
			'Unauthorized' 			=> '401', 
			'Payment Required' 		=> '402',
			'permission_denied' 	=> '403',
			'page_not_found' 		=> '404' ,
			'Request Timeout' 		=> '408',
			'internal_server_error' => '500',
			'Bad Gateway' 			=> '502',
			'Service Unavailable' 	=> '503',
		  	'Gateway Timeout'		=> '504'
		);
	
	foreach ($error_codes as $error_code => $error_value) {
		
		$file_name = get_stylesheet_directory() . '/' . $error_value . '.php';
		$old_template = file_get_contents($file_name);
		
		$this_string = '<?php /** Template Name: '. $error_value .' Page */ ?>';
		if( strpos( $old_template, $this_string ) === false) {
			
			$new_content = '<?php /** Template Name: '. $error_value .' Page */ ?>';
			$handle = fopen( $file_name, 'r+' ) or die('Cannot open file:  '.$file_name);
			fwrite($handle, $new_content."\n".$old_template);
			fclose($handle);
		
		}
		
	}

}

add_action( 'admin_init', 'wcep_create_page_templates' );


///////////////////////////////////////////////////////////////////
 // 3. Programatically create new pages in DB //
 // Set page templates to those created above ^^^^
//////////////////////////////////////////////////////////////////


function wcep_create_db_error_pages() {
	
	$error_codes = array( 
			'Bad Request' 			=> '400',
			'Unauthorized' 			=> '401', 
			'Payment Required' 		=> '402',
			'permission_denied' 	=> '403',
			'page_not_found' 		=> '404' ,
			'Request Timeout' 		=> '408',
			'internal_server_error' => '500',
			'Bad Gateway' 			=> '502',
			'Service Unavailable' 	=> '503',
		  	'Gateway Timeout'		=> '504'
		);
	
	foreach ( $error_codes as $error_code => $error_value ) {

		  // Setup the author, slug, and title for the post
		  $error_slug	 	= 'error-' . $error_value;
		  $error_title		= 'Error ' . $error_value;
		  $error_template	= $error_value . '.php'; 		  
		
		  // If the page doesn't already exist, then create it
		  if( get_page_by_title( $error_title ) == null ) {
		
				  // Set the post ID so that we know the post was created successfully
				  $post_id = wp_insert_post(
					  array(
						'post_type'       =>  	'page',
						'post_status'     =>  	'publish',
						'comment_status'  =>  	'closed',
						'ping_status'     =>  	'closed',
						'post_name'       =>  	$error_slug,
						'post_title'      =>  	$error_title,
						'post_content'    => 	$error_value .' '. $error_code,
						'page_template'   => 	$error_template
					  )
				  );
				  
				  if ( $post_id && ! is_wp_error( $post_id ) ){
					  update_post_meta( $post_id, '_wp_page_template', $error_template );
				  }
		
		  } 
	}

} 

add_filter( 'admin_init', 'wcep_create_db_error_pages' );

///////////////////////////////////////////////////////////////////
 // 4. Write HTACCESS File //
 // Add the generated error pages to Htaccess
//////////////////////////////////////////////////////////////////

function wcep_custom_error_pages() {
	
	$error_codes = array( 
			'Bad Request' 			=> '400',
			'Unauthorized' 			=> '401', 
			'Payment Required' 		=> '402',
			'permission_denied' 	=> '403',
			'page_not_found' 		=> '404' ,
			'Request Timeout' 		=> '408',
			'internal_server_error' => '500',
			'Bad Gateway' 			=> '502',
			'Service Unavailable' 	=> '503',
		  	'Gateway Timeout'		=> '504'
		);

    // Get HTACCESS path & dynamic website url
    $htaccess_file = get_home_path() . '.htaccess';
    $website_url = get_bloginfo('url').'/';

    // Check & prevent writing error pages more than once
    $check_file = file_get_contents($htaccess_file);
    $this_string = '# BEGIN WordPress Error Pages';

    if( strpos( $check_file, $this_string ) === false) {

    // Setup Error page locations dynamically
	$error_pages = '';
    $error_pages .= PHP_EOL. PHP_EOL . '# BEGIN WordPress Error Pages'. PHP_EOL. PHP_EOL;	
	foreach ( $error_codes as $error_code => $error_value ) {
		$error_pages .= 'ErrorDocument '. $error_value .' '.$website_url.'error-'.$error_value.PHP_EOL;
	}
    $error_pages .= PHP_EOL. '# END WordPress Error Pages'. PHP_EOL;

    // Write the error page locations to HTACCESS
    $htaccess = fopen( $htaccess_file, 'a+');
    fwrite( $htaccess, $error_pages );
    fclose($htaccess);

    }

}

add_action( 'admin_init', 'wcep_custom_error_pages' );