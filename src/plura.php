<?php
/*
Plugin Name: Plura
Plugin URI:  https://plura.pt
Description: Plura enhances your WordPress site with a suite of powerful features designed to improve functionality and user experience.
Version:     1.0.0
Author:      Plura
Author URI:  https://plura.pt
Text Domain: plura
Domain Path: /languages
*/

// Your plugin code starts here

function plura_wp_init() {
    
	// Initialization code
	load_plugin_textdomain( 'plura', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 

}

add_action('init', 'plura_wp_init');


function plura_includes( array $modules, string $dir ) {

	if( !is_admin() ) {

		foreach ($modules as $module) {

			$path = $dir . "/" . $module . ".php";

			if( file_exists( $path ) ) {
	 
	    		include_once( $path );

			}

		}

	}

}


plura_includes( [

	'includes/p',
	'includes/modules/apis',
	'includes/modules/extra',
	'includes/modules/lottie',
	'includes/modules/navwalker',
	'includes/modules/restricted',
	'includes/modules/wp',
	'includes/modules/wp-dynamic-grid',
	'includes/modules/wp-posts',
	'includes/modules/wp-prevnext',
	'includes/modules/wp-revslider-egrid',
	'includes/modules/wp-wpml'

], dirname( __FILE__ ) );




function plura_wp_styles() {

	$plura_wp_data = [
		'home' => home_url(),
		'pluginURL' => plugin_dir_url( __FILE__ ), 
		'restURL' => rest_url(),
		'restNonce' => wp_create_nonce('wp_rest')
	];


	if( plura_wpml() ) {

		$plura_wp_data = array_merge( $plura_wp_data, [

			'lang' => $sitepress->get_current_language()

		]);

	}


	if( is_singular() ) {

		$plura_wp_data = array_merge($plura_wp_data, [
			'id' => get_queried_object()->ID,
			'title' => get_queried_object()->post_title,
			'type' => get_queried_object()->post_type,
			'url' => get_permalink( get_queried_object()->ID )
		]);

	} else if( is_archive() ) {

		$plura_wp_data = array_merge($plura_wp_data, [
			'archive' => 1,
			'type' => get_queried_object()->name
		]);

	}


	plura_wp_enqueue( scripts: [

		'p',
		
		'fx',
		'fx-infinitescroll',
		'fx-sticky',
		'wp-dynamic-grid',
		'wp-cf7',
		'wp-globals',
		'wp-posts',
		'wp-prevnext',

		'globals'
	
	], basefile: __FILE__, prefix: 'plura-', cache: false );


	wp_localize_script('plura-p', 'plura_wp_data', $plura_wp_data);


}

add_action( 'wp_enqueue_scripts', 'plura_wp_styles' );

