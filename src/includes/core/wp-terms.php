<?php

/**
 *	. Terms
 *		- Query
 *		- Terms
 *	. Term
 *		- Featured Image
 *		- Title
 */



/**
 * Builds a WP_Term_Query with support for inclusion, exclusion, parent, ordering, and site-specific params.
 *
 * @param string|array $taxonomy Optional. Taxonomy or taxonomies to query. Default 'category'.
 * @param int[]|int    $exclude  Optional. Term ID or array of IDs to exclude (blacklist).
 * @param int[]|int    $ids      Optional. Term ID or array of IDs to include (whitelist).
 * @param int          $limit    Optional. Max number of terms to fetch. Default -1 (all).
 * @param string       $order    Optional. Ordering direction. Default 'ASC'.
 * @param string       $orderby  Optional. Field to order by. Default 'name'.
 * @param int|null     $parent   Optional. Only terms directly beneath this term ID; 0 for top-level terms.
 *                               Default null (all levels).
 *
 * @param array        $params   Optional. Site-specific parameters to be handled via filters.
 *
 * @param string       $context  Optional. String context passed to filters to modify query dynamically.
 *
 * @return WP_Term_Query The resulting query object.
 */
function plura_wp_terms_query(
	string|array $taxonomy = 'category',

	// Query
	array|int $exclude = [],
	array|int $ids = [],
	int $limit = -1,
	string $order = 'ASC',
	string $orderby = 'name',
	?int $parent = null,

	// Extra
	array $params = [],

	// Filter / context
	string $context = ''
): WP_Term_Query {
	$args = compact(
		'context',
		'exclude',
		'ids',
		'limit',
		'order',
		'orderby',
		'params',
		'parent',
		'taxonomy'
	);

	$query_params = [
		'taxonomy' => $taxonomy,
		'order'    => $order,
		'orderby'  => $orderby,
	];

	if (!empty($ids) && !empty($exclude)) {
		trigger_error('Both $ids and $exclude are set. These are mutually exclusive — only one should be used.', E_USER_WARNING);
	}

	if (!empty($ids)) {
		$query_params['include'] = (array) $ids;
	}

	if (!empty($exclude)) {
		$query_params['exclude'] = (array) $exclude;
	}

	// WP_Term_Query runs 'number' through absint(), so -1 would fetch one term, not all
	if ($limit > 0) {
		$query_params['number'] = $limit;
	}

	if (!is_null($parent)) {
		$query_params['parent'] = $parent;
	}

	$query_params = apply_filters('plura_wp_terms_query', $query_params, $args);

	return new WP_Term_Query($query_params);
}



/**
 * Renders a list of terms using plura_wp_term(), or optionally returns raw term objects.
 *
 * Runs its own query unless $terms is provided. Either way, $parent picks where the list
 * starts and $depth how many levels of child terms are nested beneath each item.
 *
 * @param string|array   $taxonomy Taxonomy or array of taxonomies. Default 'category'.
 *
 * @param int[]|int      $exclude  Optional. Term ID or array of IDs to exclude (blacklist).
 * @param int[]|int      $ids      Optional. Term ID or array of IDs to include (whitelist).
 * @param int            $limit    Max number of top-level terms to show (default: -1 = all).
 * @param string         $order    Sort order ('ASC' or 'DESC'). Default 'ASC'.
 * @param string         $orderby  Field to order by (e.g. 'name', 'count', 'include'). Default 'name'.
 * @param int|null       $parent   Only terms directly beneath this term ID; 0 for top-level terms.
 *                                 Default null: every term, or in a tree, those whose parent isn't listed.
 * @param int            $depth    Levels to show: 1 = a flat list (default), 0 = the whole tree, n = n levels.
 *
 * @param string         $class    Additional CSS class(es) for the wrapper.
 * @param array          $data     Additional data-* attributes for the wrapper.
 * @param bool           $image    Whether to show each term's featured image. Default true.
 * @param int            $link     How terms link to their archives, as in plura_wp_term(): 0 = image and title (default),
 *                                 1 = the whole term, -1 = no links.
 * @param string|null    $label    Optional label added as a data-label attribute to the wrapper.
 * @param bool           $wrap     Whether to wrap the terms in a <div> container. Default true.
 *
 * @param WP_Term[]|null $terms    Optional preloaded terms. Query arguments are ignored; $parent, $depth
 *                                 and $limit still apply.
 * @param array          $params   Optional site-specific parameters passed to the query filter.
 * @param string|null    $context  Optional context string passed to every filter.
 * @param string         $output   Output format: 'html' (default) or 'objects' (the top-level WP_Term[]).
 *
 * @return string|WP_Term[]        HTML markup for the terms container or array of term objects.
 */
function plura_wp_terms(
	string|array $taxonomy = 'category',

	// Query
	array|int $exclude = [],
	array|int $ids = [],
	int $limit = -1,
	string $order = 'ASC',
	string $orderby = 'name',
	?int $parent = null,
	int $depth = 1,

	// Display
	string $class = '',
	array $data = [],
	bool $image = true,
	int $link = 0,
	?string $label = null,
	bool $wrap = true,

	// Source
	?array $terms = null,

	// Custom
	array $params = [],

	// Filter / scope
	?string $context = null,

	// Return type
	string $output = 'html'
): string|array {
	$tree = ($depth !== 1);

	if (is_null($terms)) {
		// A tree needs every level, so it queries the whole taxonomy and applies $parent and
		// $limit below — in the query they would drop the deeper levels.
		$terms = plura_wp_terms_query(
			context: $context ?? '',
			exclude: $exclude,
			ids: $ids,
			limit: $tree ? -1 : $limit,
			order: $order,
			orderby: $orderby,
			parent: $tree ? null : $parent,
			params: $params,
			taxonomy: $taxonomy
		)->terms ?: [];
	}

	// Kept whole so each item can nest its children from it
	$pool = $terms;

	if (! is_null($parent)) {
		$terms = array_filter($terms, fn($term) => $term->parent === $parent);
	} elseif ($tree) {
		// Terms whose parent isn't listed start the tree, as orphans do in WordPress's Walker
		$listed = wp_list_pluck($pool, 'term_id');
		$terms  = array_filter($terms, fn($term) => ! in_array($term->parent, $listed, true));
	}

	$terms = array_values($terms);

	if ($limit > 0) {
		$terms = array_slice($terms, 0, $limit);
	}

	if ($output === 'objects') {
		return $terms;
	}

	if (! $terms) {
		return '';
	}

	$html = array_map(
		fn($term, $index) => plura_wp_term(
			context: $context,
			depth: $depth,
			image: $image,
			index: $index,
			link: $link,
			term: $term,
			terms: $tree ? $pool : null
		),
		$terms,
		array_keys($terms)
	);

	if (! $wrap) {
		return implode('', $html);
	}

	// Read from the terms rather than $taxonomy, which preloaded terms don't have to match
	$atts = [
		'class'         => ['plura-wp-terms'],
		'data-taxonomy' => implode(',', array_unique(wp_list_pluck($terms, 'taxonomy'))),
	];

	if (! empty($class)) {
		$atts['class'] = array_merge($atts['class'], plura_explode(' ', $class));
	}

	if (! empty($label)) {
		$atts['data-label'] = $label;
	}

	$atts['data-link-type'] = $link;

	if (! empty($context)) {
		$atts['data-context'] = $context;
	}

	if (! empty($data)) {
		$atts = array_merge_recursive($atts, $data);
	}

	$atts = apply_filters('plura_wp_terms_atts', $atts, $terms, $context);

	return sprintf(
		'<div %s>%s</div>',
		plura_attributes($atts),
		implode('', $html)
	);
}

/**
 * Shortcode [plura-wp-terms] to render terms using plura_wp_terms().
 *
 * Supports most parameters from plura_wp_terms() except:
 * - $params (array) for query filters,
 * - $terms (array|null) for preloaded terms,
 * - $data (array) for wrapper attributes.
 *
 * `parent="current"` lists the children of the term archive being viewed, and nothing elsewhere.
 */
add_shortcode('plura-wp-terms', function ($args) {
	$atts = shortcode_atts([
		'taxonomy' => 'category',

		// Query
		'exclude' => '',
		'ids' => '',
		'limit' => -1,
		'order' => 'ASC',
		'orderby' => 'name',
		'parent' => null,
		'depth' => 1,

		// Display
		'class' => '',
		'image' => true,
		'label' => '',
		'link' => 0,
		'wrap' => true,

		// Filter / scope
		'context' => null,
	], $args);

	// Type casting and preprocessing
	$atts['depth'] = (int) $atts['depth'];
	$atts['limit'] = (int) $atts['limit'];
	$atts['link'] = (int) $atts['link'];
	$atts['image'] = filter_var($atts['image'], FILTER_VALIDATE_BOOLEAN);
	$atts['wrap'] = filter_var($atts['wrap'], FILTER_VALIDATE_BOOLEAN);

	$atts['taxonomy'] = array_filter(array_map('trim', explode(',', $atts['taxonomy'])));
	$atts['ids'] = array_filter(array_map('intval', explode(',', $atts['ids'])));
	$atts['exclude'] = array_filter(array_map('intval', explode(',', $atts['exclude'])));

	if ($atts['parent'] === 'current') {
		$object = get_queried_object();

		if (! $object instanceof WP_Term) {
			return '';
		}

		$atts['parent'] = $object->term_id;
	} else {
		$atts['parent'] = is_numeric($atts['parent']) ? (int) $atts['parent'] : null;
	}

	return plura_wp_terms(...$atts);
});



/**
 * Renders a single term as its featured image and title, linked to the term archive.
 *
 * Child terms nest beneath as their own plura_wp_terms() list when $depth isn't 1.
 * Allows customization of output structure via hooks (`plura_wp_term`, `plura_wp_term_atts`).
 *
 * @param WP_Term|int    $term    A WP_Term object or term ID.
 *
 * @param string         $class   Optional CSS class(es) for the wrapper element.
 * @param bool           $image   Whether to include the featured image. Default true.
 * @param int            $link    Defines how links are applied:
 *                                0 = link the image and title, as one link (default),
 *                                1 = make the wrapper itself the link,
 *                               -1 = disable all links.
 * @param bool           $wrap    Whether to wrap output in a container (or full link if $link === 1).
 *
 * @param int            $depth   Levels to show, counting this term: 1 = no children (default), 0 = all.
 * @param WP_Term[]|null $terms   Terms to find the children in. Default null (queried).
 *
 * @param string|null    $context Optional context tag used for filters.
 * @param int|null       $index   Optional index for the term in a list.
 *
 * @return string HTML markup of the rendered term.
 */
function plura_wp_term(
	WP_Term|int $term,

	// General output
	string $class = '',
	bool $image = true,
	int $link = 0,
	bool $wrap = true,

	// Hierarchy
	int $depth = 1,
	?array $terms = null,

	// Filter / scope
	?string $context = null,
	?int $index = null
): string {
	$term = get_term($term);

	if (! $term instanceof WP_Term) {
		return '';
	}

	$atts = [
		'class'         => ['plura-wp-term'],
		'data-id'       => $term->term_id,
		'data-taxonomy' => $term->taxonomy,
	];

	if (! empty($class)) {
		$atts['class'] = array_merge($atts['class'], plura_explode(' ', $class));
	}

	$content = [];

	if ($image) {
		$img = plura_wp_term_featured_image(term: $term, context: $context);

		if ($img) {
			$content['featured-image'] = $img;
		}
	}

	$title = plura_wp_title(object: $term, tag: 'span', context: $context);

	if (! empty($title)) {
		$content['title'] = $title;
	}

	if ($depth !== 1) {
		$children = plura_wp_terms(
			context: $context,
			depth: $depth === 0 ? 0 : $depth - 1,
			image: $image,
			link: $link,
			parent: $term->term_id,
			taxonomy: $term->taxonomy,
			terms: $terms
		);

		if ($children) {
			$content['children'] = $children;
		}
	}

	$content = apply_filters('plura_wp_term', $content, $term, $context, $index);

	$atts = apply_filters('plura_wp_term_atts', $atts, $term, $context);

	// Children hold their own links, and links can't nest, so they stay outside the term's —
	// after the wrapper itself when $link === 1 makes it the link
	$children = $content['children'] ?? '';
	unset($content['children']);

	$html = implode('', $content);

	if ($link === 0 && $html !== '') {
		$html = plura_wp_link(html: $html, target: $term, context: $context);
	}

	if (! $wrap) {
		return $html . $children;
	}

	// Full block link
	return ($link === 1)
		? plura_wp_link(html: $html, target: $term, atts: $atts, context: $context) . $children
		: sprintf('<div %s>%s</div>', plura_attributes($atts), $html . $children);
}



/**
 * Renders the featured image (<img>) for a given term.
 *
 * Uses the term's `featured_image` meta, falling back to the featured image of the newest
 * post in the term, so taxonomies without an image field of their own still get one.
 *
 * @param WP_Term|int  $term    The term object or ID.
 * @param string       $size    Image size to retrieve. Defaults to 'large'.
 * @param array        $atts    Additional HTML attributes passed to the image.
 * @param string|null  $context Optional context tag for filters.
 *
 * @return string|null          HTML <img> tag or null if no image is found.
 */
function plura_wp_term_featured_image(
	WP_Term|int $term,
	string $size = 'large',
	array $atts = [],
	?string $context = null
): ?string {
	$term = get_term($term);

	if (! $term instanceof WP_Term) {
		return null;
	}

	// Default attributes
	$default_atts = [
		'class'         => ['plura-wp-term-featured-image'],
		'data-taxonomy' => $term->taxonomy,
	];

	// Merge defaults with user-provided atts
	$atts = array_merge($default_atts, $atts);

	// Read as meta rather than through get_field(): ACF stores an image field's attachment
	// ID there whatever return format the field is set to
	$image_id = (int) get_term_meta($term->term_id, 'featured_image', true);
	$result   = $image_id ? plura_wp_image($image_id, $size, $atts) : null;

	$post = null;

	if (! $result) {
		$post = get_posts([
			'post_type'      => get_taxonomy($term->taxonomy)->object_type ?? 'any',
			'posts_per_page' => 1,
			'tax_query'      => [
				[
					'taxonomy' => $term->taxonomy,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				],
			],
		])[0] ?? null;

		// Rendered as the post's own image, so any fallback a site gives posts applies here too
		$result = $post ? plura_wp_post_featured_image($post, $size, $atts, $context) : null;
	}

	// Filter the final rendered featured image HTML. $post is the post it was borrowed from,
	// or null when it is the term's own.
	return apply_filters('plura_wp_term_featured_image', $result, $term, $size, $atts, $context, $post);
}



/**
 * Returns the HTML for a term title or just the plain text.
 *
 * @deprecated 0.12.2 Use plura_wp_title( object: $term ), which adds the plura_wp_title filter, context and linking.
 *
 * @param WP_Term        $term     The term object.
 * @param string|false   $tag      HTML tag to use (e.g. h3). False to return plain text.
 *
 * @return string|null             Title HTML or plain text, or null if invalid.
 */
function plura_wp_term_title(WP_Term $term, string|false $tag = 'h3'): ?string
{
	if (empty($term->name)) {
		return null;
	}

	if ($tag === false) {
		return esc_html($term->name);
	}

	return sprintf(
		'<%1$s %3$s>%2$s</%1$s>',
		tag_escape($tag),
		esc_html($term->name),
		plura_attributes(['class' => 'plura-wp-term-title'])
	);
}
