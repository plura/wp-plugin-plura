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

/**
 * Includes PHP module files from a given directory or as absolute paths, only on the frontend.
 *
 * Each item in the `$modules` array can either be:
 * - a filename (without `.php`), relative to `$dir`
 * - an absolute path to a `.php` file
 *
 * @param array<int, string> $modules List of module filenames or absolute paths.
 * @param string $dir Base directory path to prepend to filenames (if not absolute).
 *
 * @return void
 */
function plura_includes(array $modules, string $dir): void
{
	if (is_admin()) {
		return;
	}

	foreach ($modules as $module) {
		// If it's an absolute path, use as-is; otherwise, build the path using $dir
		$is_absolute = str_starts_with($module, '/') || preg_match('#^[a-zA-Z]:[\\/]{1}#', $module);
		$path = $is_absolute
			? $module
			: rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $module . '.php';

		// Include the file if it exists and is a regular file
		if (is_file($path)) {
			include_once $path;
		}
	}
}

plura_includes([

	'includes/core/core',
	'includes/core/navwalker',
	'includes/core/p',
	'includes/core/restricted',
	'includes/core/utils',
	'includes/core/wp',
	'includes/core/wp-dynamic-grid',
	'includes/core/wp-posts',
	'includes/core/wp-prevnext',

	'includes/integrations/apis',
	'includes/integrations/lottie',
	'includes/integrations/wp-revslider-egrid',
	'includes/integrations/wp-wpml'

], dirname(__FILE__));


add_action('init', function() {

	// Initialization code
	load_plugin_textdomain('plura', false, dirname(plugin_basename(__FILE__)) . '/languages');

});




function plura_wp_styles()
{

	$plura_wp_data = [
		'home' => home_url(),
		'pluginURL' => plugin_dir_url(__FILE__),
		'restURL' => rest_url(),
		'restNonce' => wp_create_nonce('wp_rest')
	];

	if (is_singular()) {

		$plura_wp_data = array_merge($plura_wp_data, [
			'id' => get_queried_object()->ID,
			'title' => get_queried_object()->post_title,
			'type' => get_queried_object()->post_type,
			'url' => get_permalink(get_queried_object()->ID)
		]);
	} else if (is_archive()) {

		$plura_wp_data = array_merge($plura_wp_data, [
			'archive' => 1,
			'type' => get_queried_object()->name
		]);
	}

	if (plura_wpml()) {

		$plura_wp_data = array_merge($plura_wp_data, [

			'lang' => plura_wpml_lang()

		]);
	}

	plura_wp_enqueue(scripts: [

		__DIR__ . '/assets/js/p.js',

		__DIR__ . '/assets/base.css',

		__DIR__ . '/assets/css/fx.css',

		__DIR__ . '/assets/js/fx-infinitescroll.js',
		__DIR__ . '/assets/js/fx-sticky.js',
		__DIR__ . '/assets/js/fx-text-toggle.js',
		__DIR__ . '/assets/js/utils-autoscroll.js',

		__DIR__ . '/assets/%s/wp-globals.%s',
		__DIR__ . '/assets/%s/wp-globals-theme.css',
		__DIR__ . '/assets/%s/wp-dynamic-grid.%s',
		__DIR__ . '/assets/js/wp-prevnext.js',

		__DIR__ . '/assets/%s/wp-cf7.%s'

	], prefix: 'plura-', cache: false);

	wp_localize_script('plura-p', 'plura_wp_data', $plura_wp_data);
}

add_action('wp_enqueue_scripts', 'plura_wp_styles');
