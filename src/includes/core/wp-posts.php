<?php

/**
 *	. Globals
 *		- Query Defaults
 *		- Posts Defaults
 *		- Post Defaults
 *		- Timeline Defaults
 *	. Posts 
 *		- Query
 *  	- Timeline Query
 *   	- Posts
 *    	- Related Posts
 *	. Post 
 *		- Link
 *		- Timeline Datetime
 *		- Timeline Status
 */



/**
 * Builds a WP_Query object with support for exclusion, taxonomy, timeline filtering, ordering, and site-specific params.
 *
 * @param int|null     $active             Optional. If set, filters posts by a meta key/value (e.g., status = 1).
 * @param string       $active_key         Optional. Meta key used for the active filter. Default ''.
 * @param int[]|int    $ids                Optional. Post ID or array of IDs to include (whitelist).
 * @param int[]|int    $exclude            Optional. Post ID or array of IDs to exclude (blacklist).
 * @param int          $limit              Optional. Max number of posts to fetch. Default -1 (all).
 * @param bool         $rand               Optional. Whether to randomize results. Default false.
 * @param string       $order              Optional. Ordering direction. Default 'DESC'.
 * @param string       $orderby            Optional. Field to order by. Default 'date'.
 * @param string|array $type               Optional. Post type(s) to query. Default 'post'.
 *
 * @param int[]|int    $terms              Optional. Term ID or array of IDs to include in taxonomy query.
 * @param string       $taxonomy           Optional. Taxonomy to filter by. Required if $terms is set.
 *
 * @param int|null     $timeline           Optional. Timeline filter value (0, 1, or -1).
 * @param string       $timeline_start_key Optional. Meta key for timeline start date.
 * @param string       $timeline_end_key   Optional. Meta key for timeline end date.
 *
 * @param array        $params             Optional. Site-specific parameters to be handled via filters.
 *
 * @param string       $context            Optional. String context passed to filters to modify query dynamically.
 *
 * @return WP_Query    The resulting query object.
 */
function plura_wp_posts_query(
	// Query
	?int $active = null,
	string $active_key = '',
	array|int $exclude = [],
	array|int $ids = [],
	int $limit = -1,
	string $order = 'DESC',
	string $orderby = 'date',
	bool $rand = false,
	string|array $type = 'post',

	// Taxonomy
	array|int $terms = [],
	string $taxonomy = '',

	// Timeline
	?int $timeline = null,
	string $timeline_end_key = '',
	string $timeline_start_key = '',

	// Extra
	array $params = [],

	// Filter / context
	string $context = ''
): WP_Query {
	$args = compact(
		'active',
		'active_key',
		'context',
		'exclude',
		'ids',
		'limit',
		'order',
		'orderby',
		'params',
		'rand',
		'taxonomy',
		'terms',
		'timeline',
		'timeline_end_key',
		'timeline_start_key',
		'type'
	);

	$query_params = [
		'post_type'      => $type,
		'posts_per_page' => $limit,
	];

	$meta = [];

	if (!empty($ids) && !empty($exclude)) {
		trigger_error('Both $ids and $exclude are set. These are mutually exclusive — only one should be used.', E_USER_WARNING);
	}

	if (!empty($ids)) {
		$query_params['post__in'] = (array) $ids;
	}

	if (!empty($exclude)) {
		$query_params['post__not_in'] = (array) $exclude;
	}

	// Taxonomy filtering
	if (!empty($terms) && !empty($taxonomy)) {
		$query_params['tax_query'] = [
			[
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => (array) $terms,
			],
		];
	}

	// Ordering
	if ($rand) {
		$query_params['orderby'] = 'rand';
	} elseif (!empty($timeline_start_key)) {
		$query_params = array_merge($query_params, [
			'meta_key'  => $timeline_start_key,
			'meta_type' => 'DATETIME',
			'orderby'   => [
				'end_date_clause'   => 'ASC',
				'start_date_clause' => 'DESC',
			],
		]);

		$meta = array_merge($meta, [
			'relation' => 'AND',
			'start_date_clause' => [
				'key'     => $timeline_start_key,
				'compare' => 'EXISTS',
				'type'    => 'DATETIME',
			],
			'end_date_clause' => [
				'relation' => 'OR',
				[
					'key'     => $timeline_end_key,
					'type'    => 'DATETIME',
					'compare' => 'EXISTS',
				],
				[
					'key'     => $timeline_end_key,
					'compare' => 'NOT EXISTS',
				],
			],
		]);
	} else {
		$query_params['orderby'] = $orderby;
		$query_params['order']   = $order;
	}

	// Active status
	if (!is_null($active)) {
		$value = $active ? 1 : 0;
		$key   = !empty($active_key) ? $active_key : 'status';

		$meta[] = [
			'key'     => $key,
			'value'   => $value,
			'compare' => '=',
		];
	}

	// Timeline status
	if (!is_null($timeline) && in_array($timeline, [0, 1, -1], true)) {
		$start = $timeline_start_key ?: 'start';
		$end   = $timeline_end_key   ?: 'end';

		$meta[] = plura_wp_posts_query_timeline($timeline, $start, $end);
	}

	if (!empty($meta)) {
		$query_params['meta_query'] = $meta;
	}

	$query_params = apply_filters('plura_wp_posts_query', $query_params, $args);

	return new WP_Query($query_params);
}



/**
 * Generates a meta query array for timeline-based post queries
 *
 * @param int $timeline The timeline status (-1 = future, 0 = past, 1 = in progress)
 * @param string $timeline_start_key Meta key for start date (default: 'start')
 * @param string $timeline_end_key Meta key for end date (default: 'end')
 * @return array|WP_Error Returns meta query array or WP_Error on failure
 */
function plura_wp_posts_query_timeline(
	int $timeline,
	string $timeline_start_key = 'start',
	string $timeline_end_key = 'end'
) {
	// Validate empty keys first (fastest check)
	if (empty($timeline_start_key) || empty($timeline_end_key)) {
		return new WP_Error(
			'plura_invalid_keys',
			__('Both timeline keys must be provided', 'plura'),
			[
				'start_key' => $timeline_start_key,
				'end_key' => $timeline_end_key
			]
		);
	}

	// Validate timeline value before any processing
	if (!in_array($timeline, [-1, 0, 1], true)) {
		return new WP_Error(
			'plura_invalid_timeline',
			__('Timeline must be -1 (future), 0 (past), or 1 (in progress)', 'plura'),
			['received' => $timeline]
		);
	}

	// Only declare these when we know we'll need them
	$current_date = current_time('Ymd');
	$datetime_type = 'DATETIME';

	switch ($timeline) {
		case -1: // Future
			return [
				[
					'key' => $timeline_start_key,
					'value' => $current_date,
					'compare' => '>',
					'type' => $datetime_type
				]
			];

		case 0: // Past
			return [
				[
					'key' => $timeline_end_key,
					'value' => $current_date,
					'compare' => '<',
					'type' => $datetime_type
				]
			];

		case 1: // In Progress
			return [
				'relation' => 'AND',
				[
					'key' => $timeline_start_key,
					'value' => $current_date,
					'compare' => '<=',
					'type' => $datetime_type
				],
				[
					'relation' => 'OR',
					[
						'key' => $timeline_end_key,
						'value' => $current_date,
						'compare' => '>=',
						'type' => $datetime_type
					],
					[
						'key' => $timeline_end_key,
						'compare' => 'NOT EXISTS'
					],
					[
						'key' => $timeline_end_key,
						'value' => '',
						'compare' => '='
					]
				]
			];
	}
}



/**
 * Renders a list of posts using plura_wp_post(), or optionally returns raw post objects.
 *
 * Supports filtering, exclusion, ordering, timeline logic, taxonomy filtering,
 * and display tweaks like link formatting and CSS class injection.
 * Accepts preloaded posts or runs a query internally.
 *
 * @param string|array $type                     Post type or array of post types.
 *
 * @param int|null     $active                   Optional post ID to highlight as "active".
 * @param string       $active_key               Key used to determine the active post.
 * @param int[]|int    $exclude                  Optional. Post ID or array of IDs to exclude (blacklist).
 * @param int[]|int    $ids                      Optional. Post ID or array of IDs to include (whitelist).
 * @param int          $limit                    Max number of posts to show (default: -1 = all).
 * @param string       $order                    Sort order (e.g. 'ASC', 'DESC'). Default: 'DESC'.
 * @param string       $orderby                  Field to order by (e.g. 'date', 'title'). Default: 'date'.
 * @param int          $rand                     Whether to randomize results (1 = true).
 * @param int[]|int    $terms                    Optional term ID or array of term IDs.
 * @param string       $taxonomy                 Taxonomy to use for the $terms filter.
 *
 * @param int|null     $timeline                 Timeline filter flag (used in queries).
 * @param string       $timeline_start_key       ACF field key for timeline start.
 * @param string       $timeline_end_key         ACF field key for timeline end.
 * @param string       $timeline_datetime_format Datetime format for timeline display.
 * @param string       $timeline_datetime_source Format for parsing timeline raw values.
 *
 * @param string       $datetime_format          Format for post datetime (used in plura_wp_post()).
 * @param bool|string  $read_more                Whether to include a "read more" link (true/false or custom label).
 * @param int          $link                     Whether to wrap posts in a link (0 = partial links, 1 = wrap all, -1 = no links).
 * @param string       $class                    Additional CSS class(es) for the wrapper.
 * @param array        $data                     Additional data-* attributes for the wrapper.
 * @param string|null  $label                    Optional label added as a data-label attribute to the wrapper.
 * @param bool         $wrap                     Whether to wrap the posts in a <div> container. Default true.
 *
 * @param array|null   $posts                    Optional preloaded array of WP_Post objects.
 * @param array        $params                   Optional site-specific parameters passed to the query filter.
 * @param string|null  $context                  Optional context string used in post rendering.
 * @param string       $output                   Output format: 'html' (default) or 'objects' (raw WP_Post[]).
 *
 * @return string|WP_Post[]                      HTML markup for the posts container or array of post objects.
 */
function plura_wp_posts(
	string|array $type = 'post',

	// Query
	?int $active = null,
	string $active_key = '',
	array|int $exclude = [],
	array|int $ids = [],
	int $limit = -1,
	string $order = 'DESC',
	string $orderby = 'date',
	int $rand = 0,
	array|int $terms = [],
	string $taxonomy = '',

	// Timeline
	?int $timeline = null,
	string $timeline_datetime_format = 'l, F jS, Y g:i A',
	string $timeline_datetime_source = 'Y-m-d H:i:s',
	string $timeline_end_key = '',
	string $timeline_start_key = '',

	// Display
	string $class = '',
	array $data = [],
	string $datetime_format = 'l, F jS, Y g:i A',
	int $link = 0,
	bool|string $read_more = true,
	?string $label = null,
	bool $wrap = true,

	// Source
	?array $posts = null,

	// Custom
	array $params = [],

	// Filter / scope
	?string $context = null,

	// Return type
	string $output = 'html'

): string|array {
	if (! $posts) {
		$query = plura_wp_posts_query(
			active: $active,
			active_key: $active_key,
			context: $context ?? '',
			exclude: $exclude,
			ids: $ids,
			limit: $limit,
			order: $order,
			orderby: $orderby,
			rand: $rand,
			taxonomy: $taxonomy,
			terms: $terms,
			timeline: $timeline,
			timeline_end_key: $timeline_end_key,
			timeline_start_key: $timeline_start_key,
			type: $type,
			params: $params
		);

		if ($query->have_posts()) {
			$posts = $query->posts;

			if ($output === 'objects') {
				return $posts;
			}
		}
	}

	if ($posts) {
		if ($output === 'objects') {
			return $posts;
		}

		$html = array_map(
			fn($post) => plura_wp_post(
				context: $context,
				datetime_format: $datetime_format,
				post: $post,
				link: $link,
				read_more: $read_more,
				timeline_datetime_format: $timeline_datetime_format,
				timeline_datetime_source: $timeline_datetime_source,
				timeline_end_key: $timeline_end_key,
				timeline_start_key: $timeline_start_key,
				wrap: true
			),
			$posts
		);

		if (! $wrap) {
			return implode('', $html);
		}

		$atts = ['class' => ['plura-wp-posts']];

		if (! is_null($timeline)) {
			$atts['data-timeline'] = $timeline;
		}

		if (! empty($class)) {
			$atts['class'] = array_merge(
				$atts['class'],
				is_array($class) ? $class : plura_explode(' ', $class)
			);
		}

		$atts['data-type'] = is_array($type) ? implode(',', $type) : $type;

		if (! empty($label)) {
			$atts['data-label'] = $label;
		}

		$atts['data-link-type'] = $link;

		if (! empty($exclude)) {
			$atts['data-exclude'] = implode(',', (array) $exclude);
		}

		if (! empty($context)) {
			$atts['data-context'] = implode(',', (array) $context);
		}

		if (! empty($data)) {
			$atts = array_merge_recursive($atts, $data);
		}

		$atts = apply_filters('plura_wp_posts_atts', $atts, $posts);

		return sprintf(
			'<div %s>%s</div>',
			plura_attributes($atts),
			implode('', $html)
		);
	}

	return '';
}

/**
 * Shortcode [plura-wp-posts] to render posts using plura_wp_posts().
 *
 * Supports most parameters from plura_wp_posts() except:
 * - $params (array) for query filters,
 * - $posts (array|null) for preloaded posts,
 * - $data (array) for wrapper attributes.
 *
 * Only string and scalar params are accepted via shortcode attributes.
 */
add_shortcode('plura-wp-posts', function ($args) {
	$atts = shortcode_atts([
		'type' => 'post',

		// Query
		'active' => null,
		'active_key' => '',
		'exclude' => '',
		'ids' => '',
		'terms' => '',
		'taxonomy' => '',
		'limit' => -1,
		'rand' => false,
		'order' => 'DESC',
		'orderby' => 'date',

		// Timeline
		'timeline' => null,
		'timeline_start_key' => '',
		'timeline_end_key' => '',
		'timeline_datetime_format' => 'l, F jS, Y g:i A',
		'timeline_datetime_source' => 'Y-m-d H:i:s',

		// Content output
		'datetime_format' => 'l, F jS, Y g:i A',
		'read_more' => '1',
		'link' => 0,

		// Container / wrapping
		'class' => '',
		'label' => '',
		'wrap'  => true,

		// Filter / scope
		'context' => null,
	], $args);

	// Type casting and preprocessing
	$atts['active'] = is_numeric($atts['active']) ? (int) $atts['active'] : null;
	$atts['limit'] = (int) $atts['limit'];
	$atts['link'] = (int) $atts['link'];
	$atts['rand'] = filter_var($atts['rand'], FILTER_VALIDATE_BOOLEAN);
	$atts['wrap'] = filter_var($atts['wrap'], FILTER_VALIDATE_BOOLEAN);

	$atts['read_more'] = match (strtolower(trim($atts['read_more']))) {
		'0', 'false' => false,
		'1', 'true'  => true,
		default      => $atts['read_more'],
	};

	$atts['timeline'] = is_numeric($atts['timeline']) ? (int) $atts['timeline'] : null;

	$atts['type'] = array_filter(array_map('trim', explode(',', $atts['type'])));
	$atts['ids'] = array_filter(array_map('intval', explode(',', $atts['ids'])));
	$atts['terms'] = array_filter(array_map('intval', explode(',', $atts['terms'])));

	if (in_array(strtolower($atts['exclude']), ['true', '1'], true) && (is_single() || is_page())) {
		$atts['exclude'] = [get_the_ID()];
	} else {
		$atts['exclude'] = array_filter(array_map('intval', explode(',', $atts['exclude'])));
	}

	return plura_wp_posts(...$atts);
});




/* Posts: Related Posts */
add_shortcode('plura-wp-posts-related', function (array $args): string {
	$atts = shortcode_atts([
		'active' => null,
		'active_key' => '',
		'class' => '',
		'datetime_format' => 'l, F jS, Y g:i A',
		'exclude' => '',
		'limit' => -1,
		'link' => '0',
		'order' => '',
		'rand' => '0',
		'read_more' => '1',
		'tag' => '',
		'timeline' => null,
		'timeline_datetime_format' => 'l, F jS, Y g:i A',
		'timeline_datetime_source' => 'Y-m-d H:i:s',
		'timeline_end_key' => '',
		'timeline_start_key' => '',
		'type' => ''
	], $args, 'plura-wp-posts-related');

	// Type casting and validation
	$atts['active'] = is_numeric($atts['active']) ? (int)$atts['active'] : null;
	$atts['exclude'] = array_filter(
		array_map('trim', explode(',', $atts['exclude'])),
		fn($id) => is_numeric($id)
	);
	$atts['limit'] = (int)$atts['limit'];
	$atts['link'] = (int)$atts['link'];
	$atts['rand'] = (int)$atts['rand'];
	$atts['read_more'] = (int)$atts['read_more'];
	$atts['timeline'] = is_numeric($atts['timeline']) ? (int)$atts['timeline'] : null;
	$atts['type'] = array_filter(array_map('trim', explode(',', $atts['type'])));

	// Context-aware defaults
	if (empty($atts['type']) && is_single()) {
		$atts['type'] = [get_post_type()];
	}

	// Automatically exclude current post when:
	// 1. No manual exclusions provided
	// 2. On a single post page
	// 3. Current post type matches the requested types
	if (empty($atts['exclude']) && is_single() && in_array(get_post_type(), (array)$atts['type'])) {
		$atts['exclude'] = [get_the_ID()];
	}

	// Ensure random ordering if not specified
	if (empty($atts['order']) && empty($atts['rand'])) {
		$atts['rand'] = 1;
	}

	// Data attributes
	$atts['data'] = ['data-related' => implode(',', $atts['exclude'])];

	return plura_wp_posts(...$atts);
});



/**
 * Renders a customizable post block with support for featured image, title, excerpt, content, timeline, and read more link.
 *
 * Allows customization of output structure and filtering via hooks (`plura_wp_post`, `plura_wp_post_atts`).
 *
 * @param WP_Post|int  $post                      A WP_Post object or post ID.
 *
 * @param string       $class                     Optional CSS class(es) for the wrapper element.
 * @param string       $datetime_format           Format for main post datetime (default: post_date).
 * @param int          $link                      Defines how links are applied:
 *                                                0 = link inner elements (title, image, read more),
 *                                                1 = wrap the entire block in a link,
 *                                               -1 = disable all links.
 * @param array|string $meta                      Optional meta key(s) to include, passed to plura_wp_post_meta().
 * @param bool|string  $read_more                 Whether to show a read more link, or custom label (false = no, true = default, string = label).
 * @param bool         $wrap                      Whether to wrap output in a container (or full link if $link === 1).
 *
 * @param string       $timeline_datetime_format  Datetime format for timeline output.
 * @param string       $timeline_datetime_source  Source format to parse raw date values.
 * @param string       $timeline_end_key          Meta key for timeline end date.
 * @param string       $timeline_start_key        Meta key for timeline start date.
 *
 * @param string|null  $context                   Optional context tag used for filters (e.g. 'archive', 'homepage').
 *
 * @return string HTML markup of the rendered post.
 */
function plura_wp_post(
	WP_Post|int $post,

	// General output
	string $class = '',
	string $datetime_format = 'l, F jS, Y g:i A',
	int $link = 0,
	array|string $meta = [],
	bool|string $read_more = true,
	bool $wrap = true,

	// Timeline
	string $timeline_datetime_format = 'l, F jS, Y g:i A',
	string $timeline_datetime_source = 'Y-m-d H:i:s',
	string $timeline_end_key = '',
	string $timeline_start_key = '',

	// Filter / scope
	?string $context = null
): string {
	if (is_int($post)) {
		$post = get_post($post);
		if (! $post) {
			return '';
		}
	}

	$args = compact(
		'post',
		'class',
		'context',
		'datetime_format',
		'link',
		'meta',
		'read_more',
		'timeline_datetime_format',
		'timeline_datetime_source',
		'timeline_end_key',
		'timeline_start_key',
		'wrap'
	);

	$atts = [
		'class'     => ['plura-wp-post'],
		'data-id'   => $post->ID,
		'data-type' => get_post_type($post),
	];

	if (! empty($class)) {
		$atts['class'] = array_merge(
			$atts['class'],
			is_array($class) ? $class : plura_explode(' ', $class)
		);
	}

	$content = [
		'datetime' => plura_wp_datetime(
			date: $post->post_date,
			class: ['plura-wp-post-datetime'],
			source: 'Y-m-d H:i:s',
			format: $datetime_format
		),
		'excerpt' => sprintf('<div class="plura-wp-post-excerpt">%s</div>', get_the_excerpt($post)),
		'content' => sprintf('<div class="plura-wp-post-content">%s</div>', apply_filters('the_content', $post->post_content)),
	];

	// Title
	$title = plura_wp_title(
		object: $post,
		tag: 'h3',
		link: ($link === 0),
		context: $context
	);

	if (! empty($title)) {
		$content['title'] = $title;
		$title_text = plura_wp_title(object: $post, tag: false, link: false);
	}

	// Meta
	if (! empty($meta)) {
		$content['meta'] = plura_wp_post_meta(
			post: $post,
			meta: $meta,
			html: true,
			context: $context
		);
	}

	// Read more
	if ($link === 0 && $read_more) {
		$read_more_label = is_string($read_more) ? $read_more : __('Learn more', 'plura');
		$content['read-more'] = plura_wp_link(
			html: $read_more_label,
			target: $post,
			atts: ['class' => 'plura-wp-post-read-more'],
			title: $title_text ?? null
		);
	}

	// Featured image
	$img = plura_wp_post_featured_image(post: $post, context: $context);
	if ($img) {
		$content['featured-image'] = ($link === 0)
			? plura_wp_link(html: $img, target: $post, title: $title_text ?? null)
			: $img;
	}

	// Timeline
	$timeline_start_key = empty($timeline_start_key) ? 'start' : $timeline_start_key;
	$timeline_end_key   = empty($timeline_end_key)   ? 'end'   : $timeline_end_key;

	$timeline_status = plura_wp_get_post_timeline_status(
		$post->ID,
		$timeline_start_key,
		$timeline_end_key,
		true
	);

	if (in_array($timeline_status, [-1, 0, 1], true)) {
		$atts['data-timeline'] = $timeline_status;

		$content['timeline'] = plura_wp_post_timeline_datetime(
			post: $post,
			timeline_start_key: $timeline_start_key,
			timeline_end_key: $timeline_end_key,
			timeline_datetime_format: $timeline_datetime_format,
			timeline_datetime_source: $timeline_datetime_source
		);
	}

	// Final ordering and filtering
	$ordered_content = [];
	foreach (['featured-image', 'title', 'datetime', 'meta', 'timeline', 'excerpt', 'content', 'read-more'] as $key) {
		if (isset($content[$key])) {
			$ordered_content[$key] = $content[$key];
		}
	}

	if (has_filter('plura_wp_post')) {
		$filtered_content = apply_filters(
			'plura_wp_post',
			$ordered_content,     // content for filtering
			$post,
			$context,
			$ordered_content      // original content
		);
		$ordered_content = ($filtered_content !== $ordered_content) ? $filtered_content : $ordered_content;
	}

	if (! has_filter('plura_wp_post') || $filtered_content === $ordered_content) {
		unset($ordered_content['content']);
	}

	// Final filter for atts — now includes all changes (like timeline status, classes, etc)
	$atts = apply_filters('plura_wp_post_atts', $atts, $post, $context);

	$html = implode('', $ordered_content);

	if (! $wrap) {
		return $html;
	}

	// Full block link
	return ($link === 1)
		? plura_wp_link(html: $html, target: $post, atts: $atts, title: $title_text ?? null)
		: sprintf('<div %s>%s</div>', plura_attributes($atts), $html);
}

/**
 * Shortcode [plura-wp-post] to render a single post using plura_wp_post().
 *
 * Supports most parameters from plura_wp_post().
 * Only string and scalar parameters are supported.
 */
add_shortcode('plura-wp-post', function ($args) {
	$atts = shortcode_atts([
		'post' => 0,

		// General output
		'class' => '',
		'datetime_format' => 'l, F jS, Y g:i A',
		'link' => 0,
		'meta' => [],
		'read_more' => true,
		'wrap' => true,

		// Timeline
		'timeline_datetime_format' => 'l, F jS, Y g:i A',
		'timeline_datetime_source' => 'Y-m-d H:i:s',
		'timeline_end_key' => '',
		'timeline_start_key' => '',

		// Filter / scope
		'context' => null
	], $args);

	// Type casting
	$atts['post'] = (int) $atts['post'];
	$atts['link'] = (int) $atts['link'];

	// read_more: bool|string
	$atts['read_more'] = match (strtolower(trim($atts['read_more']))) {
		'0', 'false' => false,
		'1', 'true'  => true,
		default      => $atts['read_more'],
	};

	// wrap: cast to bool
	$atts['wrap'] = filter_var($atts['wrap'], FILTER_VALIDATE_BOOLEAN);

	return plura_wp_post(...$atts);
});




/* Post: Timeline Datetime */
add_shortcode('plura-wp-post-timeline-datetime', function ($args) {
	$atts = shortcode_atts([
		'post' => 0,  // Post ID (0 will fall back to current post)
		'timeline_start_key' => null,
		'timeline_end_key' => null,
		'timeline_datetime_format' => 'l, F jS, Y g:i A',
		'timeline_datetime_source' => 'Y-m-d H:i:s'
	], $args);

	// Convert to integer (safe for both strings and numbers)
	$atts['post'] = (int) $atts['post'];

	// Only proceed if we have a valid post ID or are in single view
	if ($atts['post'] || is_single()) {
		// Use provided ID or fall back to current post
		$atts['post'] = $atts['post'] ?: get_the_ID();

		return plura_wp_post_timeline_datetime(...$atts);
	}

	return ''; // Return empty string if no valid post
});


/**
 * Generates timeline date/time HTML for a post
 *
 * @param WP_Post $post Post object or ID
 * @param string|null $timeline_start_key ACF field name for start date
 * @param string|null $timeline_end_key ACF field name for end date
 * @param string $timeline_datetime_format Date format string
 * @param string $timeline_datetime_source Source format for date parsing
 * @return string|false HTML string for the timeline or false if no dates found
 */
function plura_wp_post_timeline_datetime(
	WP_Post|int $post,
	?string $timeline_start_key = null,
	?string $timeline_end_key = null,
	string $timeline_datetime_format = 'l, F jS, Y g:i A',
	string $timeline_datetime_source = 'Y-m-d H:i:s'
): string|false {
	// Handle post parameter (convert ID to WP_Post object if needed)
	if (is_int($post)) {
		$post = get_post($post);
		if (!$post) {
			return false;
		}
	}

	$atts = ['class' => ['plura-wp-post-timeline']];
	$entry = [];

	// Process start date if key provided
	if ($timeline_start_key) {
		$datetime = get_field($timeline_start_key, $post->ID);
		if ($datetime) {
			$atts['class'][] = 'has-start';
			$entry[] = plura_wp_datetime(
				date: $datetime,
				class: ['plura-wp-post-timeline-item', 'plura-wp-post-timeline-start'],
				source: $timeline_datetime_source,
				format: $timeline_datetime_format
			);
		}
	}

	// Process end date if key provided
	if ($timeline_end_key) {
		$datetime = get_field($timeline_end_key, $post->ID);
		if ($datetime) {
			$atts['class'][] = 'has-end';
			$entry[] = plura_wp_datetime(
				date: $datetime,
				class: ['plura-wp-post-timeline-item', 'plura-wp-post-timeline-end'],
				source: $timeline_datetime_source,
				format: $timeline_datetime_format
			);
		}
	}

	return $entry
		? sprintf('<div %s>%s</div>', plura_attributes($atts), implode('', $entry))
		: false;
}



/**
 * Determines the timeline status of a post based on start/end dates
 *
 * @param int $post_id The WordPress post ID
 * @param string $timeline_start_key Meta key for start date (default: 'start')
 * @param string $timeline_end_key Meta key for end date (default: 'end')
 * @param bool $int Whether to return integer codes (true) or string labels (false)
 * @return int|string|false Returns status as: 
 *   1/-1/0 (if $int=true), 
 *   'in_progress'/'future'/'past' (if $int=false), 
 *   or false if undetermined
 */
function plura_wp_get_post_timeline_status(
	int $post_id,
	string $timeline_start_key = 'start',
	string $timeline_end_key = 'end',
	bool $int = true
) {
	$current_date = date('Ymd'); // Format date as Ymd for comparison

	// Retrieve and sanitize dates
	$start_date = (string) get_post_meta($post_id, $timeline_start_key, true);
	$end_date = (string) get_post_meta($post_id, $timeline_end_key, true);

	// Return false if no start date exists
	if (empty($start_date)) {
		return false;
	}

	// Check timeline status
	if ($start_date > $current_date) {
		return $int ? -1 : 'future';
	}

	if (empty($end_date) || $end_date >= $current_date) {
		return $int ? 1 : 'in_progress';
	}

	if ($end_date < $current_date) {
		return $int ? 0 : 'past';
	}

	return false;
}



/**
 * Returns the HTML for the post title element or just the plain title text.
 *
 * @param WP_Post|int  $post       A WP_Post object or post ID.
 * @param string|false $tag        HTML tag to use (e.g. h3, h2). If false, no tag is used.
 * @param bool         $link       Whether to wrap the title in a link (default: false).
 * @param string|null  $context    Optional context tag for filters.
 *
 * @return string|null             Title HTML or plain text, or null if no title found.
 */
function plura_wp_post_title(
	WP_Post|int $post,
	string|false $tag = 'h3',
	bool $link = false,
	?string $context = null
): ?string {
	if (is_int($post)) {
		$post = get_post($post);
	}

	if (! $post instanceof WP_Post) {
		return null;
	}

	$text = apply_filters('plura_wp_post_title', $post->post_title, $post, $context);

	if (empty($text)) {
		return null;
	}

	if ($tag !== false) {
		$html = sprintf(
			'<%1$s %3$s>%2$s</%1$s>',
			tag_escape($tag),
			esc_html($text),
			plura_attributes(['class' => 'plura-wp-post-title'])
		);
	} else {
		$html = esc_html($text);
	}

	if ($link) {
		$html = plura_wp_link(
			html: $html,
			target: $post,
			title: $text
		);
	}

	return $html;
}


/**
 * Shortcode [plura-wp-post-title] to render the current post title.
 *
 * @param array $atts {
 *     @type int         $id      Post ID. Defaults to current post.
 *     @type bool|string $link    Whether to link the title (true, false, "0", "1").
 *     @type string      $tag     HTML tag to use (e.g. h2, h3). Use "false" or "0" to disable.
 *     @type string      $context Optional filter context.
 * }
 * @return string|null
 */
function plura_wp_post_title_shortcode(array $atts): ?string
{
	$atts = shortcode_atts([
		'id'      => get_the_ID(),
		'link'    => false,
		'tag'     => 'h3',
		'context' => null,
	], $atts);

	$post    = intval($atts['id']);
	$link    = filter_var($atts['link'], FILTER_VALIDATE_BOOLEAN);
	$context = $atts['context'] ?: null;

	// Handle string values like "false", "0" => false
	$tag = strtolower(trim($atts['tag']));
	$tag = in_array($tag, ['false', '0', ''], true) ? false : $tag;

	return plura_wp_post_title($post, $tag, $link, $context);
}
add_shortcode('plura-wp-post-title', 'plura_wp_post_title_shortcode');








/**
 * Retrieves one or more post meta values with optional HTML formatting.
 *
 * @param WP_Post|int  $post                The post object or ID.
 * @param array|string $meta                Meta keys to retrieve. Accepts:
 *                                          - A single meta key as a string.
 *                                          - An indexed array of meta keys (strings).
 *                                          - An associative array with display keys (e.g., 'position' => 'acf_position').
 *                                          - An array of meta item arrays, where each item may include:
 *                                              [
 *                                                  'key'               => string                  // required ACF meta key
 *                                                  'label'             => string                  // optional label
 *                                                  'sanitize_callback' => callable                // optional value transformer
 *                                                  'raw_html'          => bool                    // if true, disables esc_html()
 *                                              ]
 * @param bool         $html                Whether to return HTML or raw values.
 * @param string|null  $context             Optional context string for filtering.
 * @param bool         $label               Whether to show labels for each meta field (if provided).
 * @param bool         $label_as_data_attr  If true, label will be added as data-label; if false, as inner element.
 * @param bool         $skip_empty          Whether to skip empty/null values.
 * @return array|string                     HTML string or array of values.
 */
function plura_wp_post_meta(
	WP_Post|int $post,
	array|string $meta = [],
	bool $html = true,
	?string $context = null,
	bool $label = true,
	bool $label_as_data_attr = false,
	bool $skip_empty = true
): array|string {

	if (is_int($post)) {
		$post = get_post($post);
	}

	if (! $post instanceof WP_Post) {
		return $html ? '<div class="plura-wp-post-meta error">Invalid post</div>' : [];
	}

	$meta   = (array) $meta;
	$output = [];

	foreach ($meta as $item_key => $meta_item) {

		$is_assoc       = is_array($meta_item);
		$item_meta_key  = $is_assoc ? $meta_item['key'] : $meta_item;
		$item_label     = $is_assoc && isset($meta_item['label']) ? $meta_item['label'] : null;
		$sanitize_cb    = $is_assoc && isset($meta_item['sanitize_callback']) && is_callable($meta_item['sanitize_callback'])
			? $meta_item['sanitize_callback']
			: null;
		$raw_html       = $is_assoc && ! empty($meta_item['raw_html']);

		// Get value (ACF or WP meta), filtered
		$value = get_field($item_meta_key, $post->ID);
		$value = apply_filters('plura_wp_post_meta_item_value', $value, $post, $item_meta_key, $context);

		// Custom value transformation
		if ($sanitize_cb) {
			$value = call_user_func($sanitize_cb, $value);
		}

		// Skip empty/null values if requested
		if ($skip_empty && ($value === null || $value === '')) {
			continue;
		}

		// Skip non-scalar values when rendering HTML
		if ($html && ! is_scalar($value)) {
			trigger_error(
				sprintf('[plura_wp_post_meta] Skipping non-scalar meta value for key: "%s".', $item_meta_key),
				E_USER_WARNING
			);
			continue;
		}

		if ($html) {
			$label_html = '';
			$attr       = ['class' => 'plura-wp-post-meta-item'];

			if (is_string($item_key)) {
				$attr['data-type'] = $item_key;
			}

			if ($item_label && $label && $label_as_data_attr) {
				$attr['data-label'] = $item_label;
			}

			if ($label && $item_label && ! $label_as_data_attr) {
				$label_html = sprintf(
					'<div class="plura-wp-post-meta-item-label">%s</div>',
					esc_html($item_label)
				);
			}

			$value_html = $raw_html ? $value : esc_html($value);

			$output[] = sprintf(
				'<div %s>%s<div class="plura-wp-post-meta-item-value">%s</div></div>',
				plura_attributes($attr),
				$label_html,
				$value_html
			);
		} else {
			$output[$item_key] = $value;
		}
	}

	if ($html) {
		return sprintf(
			'<div %s>%s</div>',
			plura_attributes(['class' => 'plura-wp-post-meta']),
			implode('', $output)
		);
	}

	return $output;
}




/**
 * Renders the featured image (<img>) for a given post.
 *
 * Wrapper for `plura_wp_image()` using the post thumbnail ID.
 * Returns null if the post is invalid or has no thumbnail.
 *
 * @param WP_Post|int  $post    The post object or ID.
 * @param string       $size    Image size to retrieve. Defaults to 'large'.
 * @param array        $atts    Additional HTML attributes passed to `plura_wp_image()`.
 * @param string|null  $context Optional context tag for filters.
 *
 * @return string|null          HTML <img> tag or null if no image is found.
 */
function plura_wp_post_featured_image(
	WP_Post|int $post,
	string $size = 'large',
	array $atts = [],
	?string $context = null
): ?string {
	$post = get_post($post);

	if (! $post instanceof WP_Post) {
		return null;
	}

	// Default attributes
	$default_atts = [
		'class' => ['plura-wp-post-featured-image'],
		'data-post-type' => get_post_type($post),
	];

	// Merge defaults with user-provided atts
	$atts = array_merge($default_atts, $atts);

	$thumb_id = get_post_thumbnail_id($post);
	$result = $thumb_id ? plura_wp_image($thumb_id, $size, $atts) : null;

	// Filter the final rendered featured image HTML.
	// Can be used to inject fallback logic if no thumbnail is present.
	return apply_filters('plura_wp_post_featured_image', $result, $post, $size, $atts, $context);
}

/**
 * Shortcode [plura-wp-post-featured-image] to render the current post featured image.
 *
 * @param array $atts {
 *     @type int         $id      Post ID. Defaults to current post.
 *     @type string      $size    Image size. Defaults to 'large'.
 *     @type string      $class   CSS class to apply to <img>.
 *     @type string|null $context Optional context tag for filters.
 * }
 *
 * @return string|null
 */
function plura_wp_post_featured_image_shortcode(array $atts): ?string
{
	$atts = shortcode_atts([
		'id'      => get_the_ID(),
		'size'    => 'large',
		'class'   => '',
		'context' => null,
	], $atts);

	$img_atts = [];

	if (! empty($atts['class'])) {
		$img_atts['class'] = $atts['class'];
	}

	return plura_wp_post_featured_image(
		post: intval($atts['id']),
		size: $atts['size'],
		atts: $img_atts,
		context: $atts['context'] ?: null
	);
}
add_shortcode('plura-wp-post-featured-image', 'plura_wp_post_featured_image_shortcode');





/**
 * Retrieves all taxonomy terms associated with a given post, optionally filtered by allowed taxonomies.
 *
 * @param int              $post_id             The ID of the post to get terms for.
 * @param array|string     $allowed_taxonomies  Optional. A taxonomy name or array of names to filter which taxonomies are included.
 *                                              If empty, all taxonomies for the post type will be included.
 *
 * @return array An associative array of taxonomies and their respective terms.
 *               Example: [ 'category' => [ WP_Term, ... ], 'post_tag' => [ WP_Term, ... ] ]
 */
function plura_wp_post_terms_data(int|WP_Post $post, array|string $allowed_taxonomies = []): array
{
	if (is_string($allowed_taxonomies)) {
		$allowed_taxonomies = [$allowed_taxonomies];
	}

	$post = get_post($post);
	if (! $post instanceof WP_Post) {
		return [];
	}

	$all_taxonomies = get_object_taxonomies($post->post_type);
	$taxonomies = !empty($allowed_taxonomies)
		? array_intersect($all_taxonomies, $allowed_taxonomies)
		: $all_taxonomies;

	$all_terms = [];

	foreach ($taxonomies as $taxonomy) {
		$terms = get_the_terms($post, $taxonomy);
		if (! is_wp_error($terms) && ! empty($terms)) {
			$all_terms[$taxonomy] = $terms;
		}
	}

	return apply_filters('plura_wp_post_terms_data', $all_terms, $post);
}



/**
 * Renders the taxonomy terms of a given post as HTML.
 *
 * @param int|WP_Post  $post                The post ID or WP_Post object.
 * @param array|string $allowed_taxonomies  Optional. A taxonomy name or array of names to include. If empty, all taxonomies will be used.
 * @param bool         $taxonomy            Optional. Whether to show taxonomy labels. Default true.
 * @param bool         $link                Optional. Whether to wrap terms in links to their archive pages. Default true.
 *
 * @return string|null The generated HTML string of terms grouped by taxonomy, or null if there are no terms.
 */
function plura_wp_post_terms(int|WP_Post $post, array|string $allowed_taxonomies = [], bool $taxonomy = true, bool $link = true): ?string
{
	$post = get_post($post);
	if (! $post instanceof WP_Post) {
		return null;
	}

	$terms_by_tax = plura_wp_post_terms_data($post, $allowed_taxonomies);
	if (empty($terms_by_tax)) {
		return null;
	}

	$html = [];

	foreach ($terms_by_tax as $tax => $terms) {
		$taxdata = get_taxonomy($tax);
		if (! $taxdata) {
			continue;
		}

		$html_tax = [];

		if ($taxonomy) {
			$html_tax[] = sprintf(
				'<div %s>%s</div>',
				plura_attributes(['class' => 'plura-wp-post-terms-tax-title']),
				esc_html($taxdata->label)
			);
		}

		$html_tax_terms = [];

		foreach ($terms as $term) {
			$title = sprintf(
				'<span %s>%s</span>',
				plura_attributes(['class' => 'plura-wp-post-term-title']),
				esc_html($term->name)
			);

			if ($link) {
				$title = plura_wp_link(html: $title, target: $term, atts: ['class' => 'plura-wp-post-term-link']);
			}

			$html_tax_terms[] = sprintf(
				'<div %s>%s</div>',
				plura_attributes(['class' => 'plura-wp-post-term', 'data-id' => $term->term_id]),
				$title
			);
		}

		$html_tax[] = sprintf(
			'<div %s>%s</div>',
			plura_attributes(['class' => 'plura-wp-post-terms-group']),
			implode('', $html_tax_terms)
		);

		$html[] = sprintf(
			'<div %s>%s</div>',
			plura_attributes([
				'class' => 'plura-wp-post-terms-taxonomy',
				'data-taxonomy' => $taxdata->name,
				'data-taxonomy-name' => $taxdata->label
			]),
			implode('', $html_tax)
		);
	}

	return sprintf(
		'<div %s>%s</div>',
		plura_attributes(['class' => 'plura-wp-post-terms']),
		implode('', $html)
	);
}


/**
 * Returns the HTML for a term title or just the plain text.
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
