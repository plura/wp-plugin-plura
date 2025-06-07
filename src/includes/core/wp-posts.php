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



/* Query */
function plura_wp_posts_query(
    ?int $active = null,
    string $active_key = '',
    string $context = '',
    array|string $exclude = [],
    int $limit = -1,
    string $order = '',
    int $rand = 0,
    ?int $timeline = null, // Changed to ?int
    string $timeline_end_key = '',
    string $timeline_start_key = '',
    string|array $type = 'post'
) {
    $args = [
        'active' => $active,
        'active_key' => $active_key,
        'context' => $context,
        'exclude' => $exclude,
        'limit' => $limit,
        'order' => $order,
        'rand' => $rand,
        'timeline' => $timeline,
        'timeline_end_key' => $timeline_end_key,
        'timeline_start_key' => $timeline_start_key,
        'type' => $type
    ];

    $query_params = [
        'post_type' => $type,
        'posts_per_page' => $limit
    ];

    $meta = [];

    // Handle exclusion
    if (!empty($exclude)) {
        $ids = is_array($exclude) ? $exclude : plura_explode(',', $exclude);
        if (!empty($ids)) {
            $query_params['post__not_in'] = $ids;
        }
    }

    // Handle ordering
    if (!empty($order)) {
        // todo: implement order logic
    } elseif ($rand) {
        $query_params['orderby'] = 'rand';
    } elseif (!empty($timeline_start_key)) {
        $query_params = array_merge($query_params, [
            'meta_key' => $timeline_start_key,
            'meta_type' => 'DATETIME',
            'orderby' => [
                'end_date_clause' => 'ASC',
                'start_date_clause' => 'DESC',
            ]
        ]);

        $meta = array_merge($meta, [
            'relation' => 'AND',
            'start_date_clause' => [
                'key' => $timeline_start_key,
                'compare' => 'EXISTS',
                'type' => 'DATETIME'
            ],
            'end_date_clause' => [
                'relation' => 'OR',
                [
                    'key' => $timeline_end_key,
                    'type' => 'DATETIME',
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => $timeline_end_key,
                    'compare' => 'NOT EXISTS',
                ]
            ]
        ]);
    }

    // Handle active status
    if (!is_null($active)) {
        $value = $active ? 1 : 0;
        $key = !empty($active_key) ? $active_key : 'status';
        $meta[] = [
            'key' => $key,
            'value' => $value,
            'compare' => '='
        ];
    }
    
    // Handle timeline status
    if (!is_null($timeline) && in_array($timeline, [0, 1, -1], true)) {
        $start = !empty($timeline_start_key) ? $timeline_start_key : 'start';
        $end = !empty($timeline_end_key) ? $timeline_end_key : 'end';
        $meta[] = plura_wp_posts_query_timeline($timeline, $start, $end);
    }

    if (!empty($meta)) {
        $query_params['meta_query'] = $meta;
    }

    if (has_filter('plura_wp_posts_query')) {
        $query_params = apply_filters('plura_wp_posts_query', $query_params, $args);
    }

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
 * Renders a list of posts using plura_wp_post(), wrapped in a container.
 *
 * Supports filtering, exclusion, ordering, timeline logic, and display tweaks
 * like link formatting and CSS class injection. Accepts preloaded posts or
 * runs a query internally.
 *
 * @param int|null    $active                     Optional post ID to highlight as "active".
 * @param string      $active_key                 Key used to determine the active post.
 * @param string      $class                      Additional CSS class(es) for the wrapper.
 * @param string|null $context                    Optional context string used in post rendering.
 * @param array       $data                       Additional data-* attributes for the wrapper.
 * @param string      $datetime_format            Format for post datetime (used in plura_wp_post()).
 * @param array|string $exclude                   Post IDs to exclude.
 * @param int         $limit                      Max number of posts to show (default: -1 = all).
 * @param int         $link                       Whether to wrap posts in a link (0, 1, or -1 for custom behavior).
 * @param string      $order                      Sort order (e.g., 'ASC', 'DESC').
 * @param array|null  $posts                      Optional preloaded array of WP_Post objects.
 * @param int         $rand                       Whether to randomize results (1 or 0).
 * @param int         $read_more                  Whether to include a "read more" link (0/1 or custom label).
 * @param int|null    $timeline                   Timeline filter flag (used in queries).
 * @param string      $timeline_datetime_format   Datetime format for timeline display.
 * @param string      $timeline_datetime_source   Format for parsing timeline raw values.
 * @param string      $timeline_end_key           ACF field key for timeline end.
 * @param string      $timeline_start_key         ACF field key for timeline start.
 * @param string|array $type                      Post type or array of post types.
 *
 * @return string HTML markup for the posts container and rendered post items.
 */
function plura_wp_posts(
	?int $active = null,
	string $active_key = '',
	string $class = '',
	?string $context = null,
	array $data = [],
	string $datetime_format = 'l, F jS, Y g:i A',
	array|string $exclude = [],
	int $limit = -1,
	int $link = 0,
	string $order = '',
	?array $posts = null,
	int $rand = 0,
	int $read_more = 1,
	?int $timeline = null,
	string $timeline_datetime_format = 'l, F jS, Y g:i A',
	string $timeline_datetime_source = 'Y-m-d H:i:s',
	string $timeline_end_key = '',
	string $timeline_start_key = '',
	string|array $type = 'post'
): string { 
	if (!$posts) {
		$query = plura_wp_posts_query(
			active: $active,
			active_key: $active_key,
			exclude: $exclude,
			limit: $limit,
			order: $order,
			rand: $rand,
			timeline: $timeline,
			timeline_end_key: $timeline_end_key,
			timeline_start_key: $timeline_start_key,
			type: $type
		);

		if ($query->have_posts()) {
			$posts = $query->posts;
		}
	}

	if ($posts) {
		$html = array_map(
			fn($post) => plura_wp_post(
				context: $context,
				datetime_format: $datetime_format,
				post: $post,
				link: $link,
				read_more: $read_more,
				single: false,
				timeline_datetime_format: $timeline_datetime_format,
				timeline_datetime_source: $timeline_datetime_source,
				timeline_end_key: $timeline_end_key,
				timeline_start_key: $timeline_start_key
			),
			$posts
		);

		$atts = ['class' => ['plura-wp-posts']];

		if (!is_null($timeline)) {
			$atts['data-timeline'] = $timeline;
		}

		if (!empty($class)) {
			$atts['class'] = array_merge(
				$atts['class'],
				is_array($class) ? $class : plura_explode(' ', $class)
			);
		}

		// Always add post type(s) as data attribute
		$atts['data-type'] = is_array($type) ? implode(',', $type) : $type;

		if ($link && $link !== -1) {
			$atts['data-link-type'] = 'full';
		}

		if (!empty($exclude)) {
			$atts['data-exclude'] = implode(',', $exclude);
		}

		if (!empty($data)) {
			$atts = array_merge_recursive($atts, $data);
		}

		return sprintf('<div %s>%s</div>', plura_attributes($atts), implode('', $html));
	}

	return '';
}

add_shortcode('plura-wp-posts', function($args) {
    $atts = shortcode_atts([
        'active' => null,
        'active_key' => '',
        'class' => '',
        'context' => null,
        'datetime_format' => 'l, F jS, Y g:i A',
        'exclude' => '',
        'limit' => -1,
        'link' => 0,
        'order' => '',
        'rand' => 0,
        'read_more' => 1,
        'timeline' => null,
        'timeline_datetime_format' => 'l, F jS, Y g:i A',
        'timeline_datetime_source' => 'Y-m-d H:i:s',
        'timeline_end_key' => '',
        'timeline_start_key' => '',
        'type' => 'post'
    ], $args);

    // Type casting and preprocessing
    $atts['active'] = is_numeric($atts['active']) ? (int) $atts['active'] : null;
    $atts['limit'] = (int) $atts['limit'];
    $atts['link'] = (int) $atts['link'];
    $atts['rand'] = (int) $atts['rand'];
    $atts['read_more'] = (int) $atts['read_more'];
    $atts['timeline'] = is_numeric($atts['timeline']) ? (int) $atts['timeline'] : null;
    $atts['type'] = array_filter(array_map('trim', explode(',', $atts['type'])));

    // Handle exclude parameter
    if (in_array(strtolower($atts['exclude']), ['true', '1'], true) && (is_single() || is_page())) {
        $atts['exclude'] = [get_the_ID()];
    } else {
        $atts['exclude'] = array_filter(array_map('trim', explode(',', $atts['exclude'])));
    }

    return plura_wp_posts(...$atts);
});



/* Posts: Related Posts */
add_shortcode('plura-wp-posts-related', function(array $args): string {
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
 * @param WP_Post|int $post                         A WP_Post object or post ID.
 * @param string      $class                        Optional CSS class(es) for the wrapper element.
 * @param string|null $context                      Optional context tag used for filters (e.g. 'archive', 'homepage').
 * @param string      $datetime_format              Format for main post datetime (default: post_date).
 * @param int         $link                         Whether to wrap the block in a link (0 = yes, 1 = no, -1 = none).
 * @param array|string $meta                        Optional meta key(s) to include, passed to plura_wp_post_meta.
 * @param int|string  $read_more                    Whether to show a read more link, or custom label (0 = no, 1 = default, string = label).
 * @param bool        $single                       Whether this is a single post view (adds metadata).
 * @param string      $timeline_datetime_format     Datetime format for timeline output.
 * @param string      $timeline_datetime_source     Source format to parse raw date values.
 * @param string      $timeline_end_key             Meta key for timeline end date.
 * @param string      $timeline_start_key           Meta key for timeline start date.
 * 
 * @return string HTML markup of the rendered post.
 */
function plura_wp_post(
	WP_Post|int $post,
	string $class = '',
	?string $context = null,
	string $datetime_format = 'l, F jS, Y g:i A',
	int $link = 0,
	array|string $meta = [],
	int $read_more = 1,
	bool $single = true,
	string $timeline_datetime_format = 'l, F jS, Y g:i A',
	string $timeline_datetime_source = 'Y-m-d H:i:s',
	string $timeline_end_key = '',
	string $timeline_start_key = ''
): string {
	// Convert ID to post object
	if (is_int($post)) {
		$post = get_post($post);
		if (!$post) {
			return '';
		}
	}

	// Prepare base arguments
	$args = compact(
		'post',
		'class',
		'context',
		'datetime_format',
		'link',
		'meta',
		'read_more',
		'single',
		'timeline_datetime_format',
		'timeline_datetime_source',
		'timeline_end_key',
		'timeline_start_key'
	);

	// Prepare base attributes
	$atts = ['class' => ['plura-wp-post'], 'data-id' => $post->ID];

	if ($single) {
		$atts['class'][] = 'plura-wp-post-is-single';
		$atts['data-type'] = get_post_type($post);
	}

	// Title
	$title = sprintf('<h3 class="plura-wp-post-title">%s</h3>', $post->post_title);
	if (!$link) {
		$title = plura_wp_link($title, $post);
	}

	// Content sections
	$content = [
		'title' => $title,
		'datetime' => plura_wp_datetime(
			date: $post->post_date,
			class: ['plura-wp-post-datetime'],
			source: 'Y-m-d H:i:s',
			format: $datetime_format
		),
		'excerpt' => sprintf('<div class="plura-wp-post-excerpt">%s</div>', get_the_excerpt($post)),
		'content' => sprintf('<div class="plura-wp-post-content">%s</div>', apply_filters('the_content', $post->post_content))
	];

	// Meta block (if any)
	if (!empty($meta)) {
		$content['meta'] = plura_wp_post_meta(post: $post, meta: $meta, html: true, context: $context);
	}

	// Read more link
	if (!$link && $read_more) {
		$read_more_label = is_string($read_more) ? $read_more : __('Learn more', 'plura');
		$content['read-more'] = plura_wp_link($read_more_label, $post, ['class' => 'plura-wp-post-read-more']);
	}

	// Featured image
	$img = plura_wp_thumbnail(plura_wpml_id($post->ID));
	if ($img) {
		$img_html = sprintf('<img %s />', plura_attributes([
			'src' => $img[0],
			'width' => $img[1],
			'height' => $img[2],
			'class' => 'plura-wp-post-featured-img'
		]));
		$content['featured-image'] = $link ? $img_html : plura_wp_link($img_html, $post);
	}

	// Timeline data
	$timeline_start_key = empty($timeline_start_key) ? 'start' : $timeline_start_key;
	$timeline_end_key = empty($timeline_end_key) ? 'end' : $timeline_end_key;

	$timeline_status = plura_wp_get_post_timeline_status($post->ID, $timeline_start_key, $timeline_end_key, true);
	if (in_array($timeline_status, [0, 1, -1], true)) {
		$atts['data-timeline'] = $timeline_status;
		$content['timeline'] = plura_wp_post_timeline_datetime(
			post: $post,
			timeline_start_key: $timeline_start_key,
			timeline_end_key: $timeline_end_key,
			timeline_datetime_format: $timeline_datetime_format,
			timeline_datetime_source: $timeline_datetime_source
		);
	}

	// Reorder and filter content
	$ordered_content = [];
	foreach (['featured-image', 'title', 'datetime', 'meta', 'timeline', 'excerpt', 'content', 'read-more'] as $key) {
		if (isset($content[$key])) {
			$ordered_content[$key] = $content[$key];
		}
	}

	// Apply content filters
	if (has_filter('plura_wp_post')) {
		$filtered_content = apply_filters('plura_wp_post', $ordered_content, $post, $context);
		$ordered_content = ($filtered_content !== $ordered_content) ? $filtered_content : $ordered_content;
	}

	// Hide full content if no filter override
	if (!has_filter('plura_wp_post') || $filtered_content === $ordered_content) {
		unset($ordered_content['content']);
	}

	// Filter attributes
	if (has_filter('plura_wp_post_atts')) {
		$filtered_atts = apply_filters('plura_wp_post_atts', $post, $args);
		if ($filtered_atts) {
			$atts = array_merge_recursive($atts, $filtered_atts);
		}
	}

	// Final HTML output
	$html = implode('', $ordered_content);

	return $link && !in_array($link, [-1, "-1"], true)
		? plura_wp_link($html, $post, $atts)
		: sprintf('<div %s>%s</div>', plura_attributes($atts), $html);
}

add_shortcode('plura-wp-post', function($args) {
	$atts = shortcode_atts([
		'post' => 0,
		'class' => '',
		'context' => null,
		'datetime_format' => 'l, F jS, Y g:i A',
		'link' => 0,
		'meta' => [],
		'read_more' => 1,
		'timeline_datetime_format' => 'l, F jS, Y g:i A',
		'timeline_datetime_source' => 'Y-m-d H:i:s',
		'timeline_end_key' => '',
		'timeline_start_key' => ''
	], $args);

	// Type casting
	$atts['post'] = (int) $atts['post'];
	$atts['link'] = (int) $atts['link'];
	$atts['read_more'] = is_numeric($atts['read_more']) ? (int) $atts['read_more'] : $atts['read_more'];

	return plura_wp_post(...$atts);
});




/* Post: Timeline Datetime */
add_shortcode('plura-wp-post-timeline-datetime', function($args) {
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
 * Retrieves one or more post meta values with optional HTML formatting.
 *
 * @param WP_Post|int        $post               The post object or ID.
 * @param array|string       $meta               Meta keys to retrieve. Accepts:
 *                                               - A single meta key as a string.
 *                                               - An indexed array of meta keys (strings).
 *                                               - An associative array with display keys (e.g., 'position' => 'acf_position').
 *                                               - An array of meta item arrays, where each item may include:
 *                                                 [
 *                                                   'key' => string                  // required ACF meta key
 *                                                   'label' => string                // optional label
 *                                                   'sanitize_callback' => callable // optional value sanitizer
 *                                                 ]
 * @param bool               $html               Whether to return HTML or raw values.
 * @param string|null        $context            Optional context string for filtering.
 * @param bool               $label              Whether to show labels for each meta field (if provided).
 * @param bool               $label_as_data_attr If true, label will be added as data-label; if false, as inner element.
 * @return array|string                          HTML string or array of values.
 */
function plura_wp_post_meta(
	WP_Post|int $post,
	array|string $meta = [],
	bool $html = true,
	?string $context = null,
	bool $label = true,
	bool $label_as_data_attr = false
): array|string {

	if ( is_int( $post ) ) {
		$post = get_post( $post );
	}

	if ( ! $post instanceof WP_Post ) {
		return $html ? '<div class="plura-wp-post-meta error">Invalid post</div>' : [];
	}

	$meta = (array) $meta;
	$output = [];

	foreach ( $meta as $item_key => $meta_item ) {

		$is_assoc       = is_array( $meta_item );
		$item_meta_key  = $is_assoc ? $meta_item['key'] : $meta_item;
		$item_label     = $is_assoc && isset( $meta_item['label'] ) ? $meta_item['label'] : null;
		$sanitize_cb    = $is_assoc && isset( $meta_item['sanitize_callback'] ) && is_callable( $meta_item['sanitize_callback'] )
			? $meta_item['sanitize_callback']
			: null;

		// Get value, allowing filter override (handles groups/complex values)
		$value = get_field( $item_meta_key, $post->ID );

		if ( has_filter( 'plura_wp_post_meta_item_value' ) ) {
			$value = apply_filters( 'plura_wp_post_meta_item_value', $value, $post, $item_meta_key, $context );
		}

		// Optional custom sanitation
		if ( $sanitize_cb ) {
			$value = call_user_func( $sanitize_cb, $value );
		}

		// Skip non-scalar values (e.g., arrays, objects) for HTML rendering
		if ( $html && !is_scalar( $value ) ) {
			trigger_error(
				sprintf('[plura_wp_post_meta] Skipping non-scalar meta value for key: "%s".', $item_meta_key),
				E_USER_WARNING
			);
			continue;
		}

		if ( $html ) {
			$label_html = '';
			$attr = [
				'class' => 'plura-wp-post-meta-item',
			];

			if ( is_string( $item_key ) ) {
				$attr['data-type'] = $item_key;
			}

			if ( $item_label && $label && $label_as_data_attr ) {
				$attr['data-label'] = $item_label;
			}

			if ( $label && $item_label && !$label_as_data_attr ) {
				$label_html = sprintf(
					'<div class="plura-wp-post-meta-item-label">%s</div>',
					esc_html( $item_label )
				);
			}

			$output[] = sprintf(
				'<div %s>%s<div class="plura-wp-post-meta-item-value">%s</div></div>',
				plura_attributes( $attr ),
				$label_html,
				$value
			);
		} else {
			$output[ $item_key ] = $value;
		}
	}

	if ( $html ) {
		return sprintf(
			'<div %s>%s</div>',
			plura_attributes([ 'class' => 'plura-wp-post-meta' ]),
			implode( '', $output )
		);
	}

	return $output;
}
