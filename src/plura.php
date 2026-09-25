<?php
/*
Plugin Name: Plura
Plugin URI:  https://plura.pt
Description: Plura enhances your WordPress site with a suite of powerful features designed to improve functionality and user experience.
Version:     0.13.0
Author:      Plura
Author URI:  https://plura.pt
Text Domain: plura
Domain Path: /languages
*/

/**
 * Includes PHP module files from a given directory or as absolute paths.
 *
 * Each item in the `$modules` array can either be:
 * - a filename (without `.php`), relative to `$dir`
 * - an absolute path to a `.php` file
 *
 * `$admin` defaults to false, preserving the frontend-only behaviour this function has
 * always had — note plura_wp_enqueue()'s `$admin` defaults to the opposite. Pass true
 * when the modules register post types, taxonomies or shortcodes: wp-admin needs those
 * registered too, or they vanish from the dashboard.
 *
 * @param array<int, string> $modules List of module filenames or absolute paths.
 * @param string $dir Base directory path to prepend to filenames (if not absolute).
 * @param bool $admin Whether to include the modules in the admin area.
 *
 * @return void
 */
function plura_includes(array $modules, string $dir, bool $admin = false): void
{
	if (!$admin && is_admin()) {
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

$plura_modules = [

	'includes/core/core',
	'includes/core/navwalker',
	'includes/core/p',
	'includes/core/restricted',
	'includes/core/utils',
	'includes/core/wp',
	'includes/core/wp-dynamic-grid',
	'includes/core/wp-posts',
	'includes/core/wp-prevnext',
	'includes/core/wp-terms',

	'includes/integrations/apis',
	'includes/integrations/lottie',

];

if (class_exists('WPCF7'))                                       $plura_modules[] = 'includes/integrations/wp-cf7';
if (class_exists('RevSlider') || class_exists('Essential_Grid')) $plura_modules[] = 'includes/integrations/wp-revslider-egrid';
if (class_exists('SitePress'))                                   $plura_modules[] = 'includes/integrations/wp-wpml';

plura_includes($plura_modules, __DIR__);


add_action('init', function() {

	// Initialization code
	load_plugin_textdomain('plura', false, dirname(plugin_basename(__FILE__)) . '/languages');

});




/**
 * Builds the payload localized onto the plugin's base script as `plura_wp_data`.
 *
 * Reads the main query, so it must run at 'wp' or later — earlier it yields the
 * base keys alone.
 *
 * @return array<string, mixed> Base site/plugin keys, plus singular or archive context.
 */
function plura_wp_data(): array
{
	$data = [
		'home' => home_url(),
		'pluginURL' => plugin_dir_url(__FILE__),
		'restURL' => rest_url(),
		'restNonce' => wp_create_nonce('wp_rest')
	];

	$object = get_queried_object();

	if (is_singular()) {

		$data = array_merge($data, [
			'id' => $object->ID,
			'title' => $object->post_title,
			'type' => $object->post_type,
			'url' => get_permalink($object->ID)
		]);
	} else if (is_archive()) {

		// Each archive kind hands back a different queried object — post type, term or
		// user, and none at all on date archives — so 'type' has to be resolved per kind
		// to keep meaning the post type slug, the way it does on singulars.
		$archive = ['archive' => 1];

		if ($object instanceof WP_Post_Type) {

			$archive['type'] = $object->name;

		} else if ($object instanceof WP_Term) {

			$taxonomy = get_taxonomy($object->taxonomy);

			// First object type only, matching plura_p_date_archive()
			if ($taxonomy && !empty($taxonomy->object_type)) {
				$archive['type'] = $taxonomy->object_type[0];
			}

			$archive['taxonomy'] = $object->taxonomy;
			$archive['term'] = $object->term_id;

		} else if ($object instanceof WP_User) {

			$archive['author'] = $object->ID;

		} else if (is_date()) {

			$archive['date'] = array_filter([
				'year' => (int) get_query_var('year'),
				'month' => (int) get_query_var('monthnum'),
				'day' => (int) get_query_var('day')
			]);
		}

		$data = array_merge($data, $archive);
	}

	if (function_exists('plura_wpml') && plura_wpml()) {

		$data = array_merge($data, ['lang' => plura_wpml_lang()]);
	}

	return apply_filters('plura_wp_data', $data);
}


function plura_wp_styles()
{

	$plura_scripts = [

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

	];

	if (class_exists('WPCF7')) {
		$plura_scripts[] = __DIR__ . '/assets/%s/wp-cf7.%s';
	}

	plura_wp_enqueue(scripts: $plura_scripts, prefix: 'plura-', cache: false);

	wp_localize_script('plura-p', 'plura_wp_data', plura_wp_data());
}

add_action('wp_enqueue_scripts', 'plura_wp_styles');

