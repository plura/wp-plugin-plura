<?php

/**
 * WPML: language checks, translated IDs, queries run in the default language, and
 * wpml-lang-* / wpmlobj-id-* body classes on singulars. Loaded only when WPML is active.
 */

// add wpml body class
add_filter('body_class', function ($classes) {
	global $sitepress;

	$c = [];

	if (is_singular() && class_exists('sitepress')) {
		$c[] = 'wpmlobj-id-' . plura_wpml_id(get_the_ID());

		$c[] = 'wpml-lang-' . strtolower($sitepress->get_current_language());
	}

	return array_merge($classes, $c);
});

/* POST FEATURED IMAGE */

// get object featured image
// if no post thumbnail is found, it searches an acf field
function plura_wpml_featured_image($postID, $acf_field = false, $size = 'large')
{
	$id = plura_wpml_featured_image_id($postID);

	if ($id) {
		foreach (['large', 'full', 'medium', 'thumbnail'] as $imgsize) {
			$img = wp_get_attachment_image_src($id, $imgsize);

			if ($img) {
				return $img;
			}
		}
	}

	return false;
}

// get object featured image id
// if no post thumbnail is found, it searches an acf field
function plura_wpml_featured_image_id($postID, $acf_field = false)
{
	if (has_post_thumbnail($postID)) {
		return get_post_thumbnail_id($postID);
	} elseif ($acf_field) {
		$gallery = get_field($acf_field, $postID);

		if ($gallery) {
			return $gallery[0]['ID'];
		}
	}

	return false;
}

/* WPML */

/**
 * Checks whether WPML is active.
 *
 * @return bool
 */
function plura_wpml()
{
	return class_exists('sitepress');
}

/**
 * Translates post or term IDs into another language through WPML.
 *
 * @param int|int[]|false $id      ID or IDs to translate. Default the current post.
 * @param bool|string     $default Target language: true for the default language (IDs are returned
 *                                 unchanged while it is the current one), a language code, or false
 *                                 for the current language. Default true.
 * @param string          $type    'post' for posts, anything else for terms. Default 'post'.
 *
 * @return int|int[]|false Translated ID(s), or $id unchanged when there is nothing to translate.
 */
function plura_wpml_id($id = false, $default = true, $type = 'post')
{
	global $sitepress;

	if (!$id) {
		$id = get_the_ID();
	}

	if (plura_wpml() && (!$default || is_string($default) || $sitepress->get_current_language() !== $sitepress->get_default_language())) {
		$objectIDs = is_array($id) ? $id : [$id];

		$ids = [];

		$lang = $default ? (is_string($default) ? $default : $sitepress->get_default_language()) : $sitepress->get_current_language();

		foreach ($objectIDs as $objectID) {
			if ($type === 'post') {
				$type = get_post_type($objectID);
			} else {
				$type = get_term($objectID)->taxonomy;
			}

			$ids[] = apply_filters('wpml_object_id', $objectID, $type, true, $lang);
		}

		if (!is_array($id)) {
			return $ids[0];
		}

		return $ids;
	}

	return $id;
}

/**
 * Runs a post or term query in WPML's default language, then switches back.
 *
 * @param array        $query_args WP_Query or WP_Term_Query arguments.
 * @param string|false $type       'terms' for a WP_Term_Query; anything else runs a WP_Query. Default false.
 *
 * @return WP_Query|WP_Term_Query The query, already run.
 */
function plura_wpml_query($query_args, $type = false)
{
	global $sitepress;

	if (plura_wpml() && $sitepress->get_current_language() !== $sitepress->get_default_language()) {
		$lang = $sitepress->get_current_language();

		$sitepress->switch_lang($sitepress->get_default_language());
	}

	if ($type === 'terms') {
		$query = new WP_Term_Query($query_args);
	} else {
		$query = new WP_Query($query_args);
	}

	if (isset($lang)) {
		$sitepress->switch_lang($lang);
	}

	return $query;
}

/**
 * Returns WPML's current language code.
 *
 * @return string|false Language code, or false when WPML is inactive.
 */
function plura_wpml_lang()
{
	global $sitepress;

	if (plura_wpml()) {
		return $sitepress->get_current_language();
	}

	return false;
}

// ESSENTIAL GRID
function plura_wpml_essential_grid($posts, $alias, $label = false)
{
	$ids = [];

	foreach ($posts as $post) {
		$ids[] = plura_wpml_id($post->ID);
	}

	$atts = ['class' => 'pwp-eg-holder'];

	if ($label) {
		$atts['data-label'] = $label;
	}

	$html = do_shortcode('[ess_grid alias="' . $alias . '" posts="' . implode(',', $ids) . '"]');

	return "<div " . plura_attributes($atts) . ">" . $html . "</div>";
}
