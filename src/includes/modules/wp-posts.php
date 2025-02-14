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



$GLOBALS['PLURA_WP_TIMELINE_DEFAULTS'] = [
	'timeline_end_key' => '',
	'timeline_start_key' => '',
	'timeline_datetime_format' => __('l, F jS, Y g:i A', 'plura'),
	'timeline_datetime_source' => 'Y-m-d H:i:s'
];



$GLOBALS['PLURA_WP_TIMELINE_DATETIME_DEFAULTS'] = array_merge($GLOBALS['PLURA_WP_TIMELINE_DEFAULTS'], [
	'class' => '',
	'id' => ''
]);



/**
 * Global defaults for the $PLURA_WP_POSTS_QUERY_DEFAULTS global variable settings used in the plura_wp_posts_query method.
 *
 * @global array $PLURA_WP_POSTS_QUERY_DEFAULTS {
 *     Default settings for the plura_wp_posts_query method.
 *
 *     @type string $active				The active status of the posts to retrieve. 1 will retrieve active posts, using a default 'status' post meta key. Use a string for alternative post meta key. Default is an empty string.
 *     @type string $active_key			The post meta key to retrieve the posts' active status. Default is an empty string.
 *     @type string $exclude			Post IDs to exclude from the query. Default is an empty string.
 *     @type int    $limit				The number of posts to retrieve. -1 means no limit, default is -1.
 *     @type string $rand				Whether to randomize the order of the posts. Default is an empty string.
 *     @type string $timeline			The post current timeline status. For 'past' use 0, for 'in progress' use 1, for 'future' use -1. Default is an empty string.
 *     @type string $timeline_end_key	The key for ending status date. Default is an empty string.
 *     @type string $timeline_start_key	The key for starting status date. Default is an empty string.
 *     @type string $type				The type of posts to retrieve. Default is 'post'.
 * }
 *
 * @return array Returns an array of the query posts.
 */
$GLOBALS['PLURA_WP_POSTS_QUERY_DEFAULTS'] = array_merge($GLOBALS['PLURA_WP_TIMELINE_DEFAULTS'], [
	'active' => '',
	'active_key' => '',
	'exclude' => '',
	'limit' => -1,
	'order' => '',
	'rand' => '',
	'timeline' => '',
	'timeline_end_key' => '',
	'timeline_start_key' => '',
	'type' => ''
]);



/**
 * Global defaults for the $PLURA_WP_POSTS_DEFAULTS global variable used in the plura_wp_posts method.
 *
 * @global array $PLURA_WP_POSTS_DEFAULTS {
 *     Default settings for the plura_wp_posts method.
 *
 *     @type string $active				The active status of the posts to retrieve. 1 will retrieve active posts, using a default 'status' post meta key. Use a string for alternative post meta key. Default is an empty string.
 *     @type string $active_key			The post meta key to retrieve the posts' active status. Default is an empty string.
 *     @type string $class				Additional CSS class for styling. Default is an empty string.
 *     @type string $exclude			Post IDs to exclude from the query. Default is an empty string.
 *     @type int    $limit				The number of posts to retrieve. -1 means no limit, default is -1.
 *     @type int    $link				Indicates whether the posts should have a single link (1), links for each post component (0), or no links (-1). Default is 0.
 *     @type string $rand				Whether to randomize the order of the posts. Default is an empty string.
 *     @type string $timeline			The post current timeline status. For 'past' use 0, for 'in progress' use 1, for 'future' use -1. Default is an empty string.
 *     @type string $timeline_end_key	The key for ending status date. Default is an empty string.
 *     @type string $timeline_start_key	The key for starting status date. Default is an empty string.
 *     @type string $tag				Used as reference to a function's instance. Default is an empty string.
 *     @type string $type				The type of posts to retrieve. Default is 'post'.
 * }
 *
 * @return false|string Returns false on failure or a string with the retrieved posts HTML on success.
 */
$GLOBALS['PLURA_WP_POSTS_DEFAULTS'] = array_merge( $GLOBALS['PLURA_WP_POSTS_QUERY_DEFAULTS'], [
	'class' => '',
	'link' => 0,
	'read-more' => 1,
	'tag' => ''
] );



/**
 * Global defaults for WP Post settings in the PLURA theme.
 *
 * @global array $PLURA_WP_POST_DEFAULTS {
 *     Default settings for WP Posts.
 *
 *     @type string $class  The CSS class for the post. Default is an empty string.
 *     @type string $id     The ID of the post. Default is an empty string. If empty, get_the_ID() will be used instead.
 *     @type int    $link   Indicates whether the post should have a single link (1), links for each post components (0) or no links (-1). Default is 0.
 *     @type int    $single Indicates whether the post is a single post. 1 means true, default is 1.
 *     @type string $tag    A reference tag for the post. Default is an empty string.
 * }
 */
$GLOBALS['PLURA_WP_POST_DEFAULTS'] = array_merge($GLOBALS['PLURA_WP_TIMELINE_DEFAULTS'], [
	'class' => '',
	'id' => '',
	'link' => 0,
	'read-more' => 1,
	'single' => 1,
	'tag' => ''
]);






/* Query */
function plura_wp_posts_query( $args = [] ) {

	global $PLURA_WP_POSTS_QUERY_DEFAULTS;

	$args = array_merge( $PLURA_WP_POSTS_QUERY_DEFAULTS, $args );

	$query_params = [
		'post_type' => $args['type'],
		'posts_per_page' => $args['limit']
	];

	$meta = [];


	if( isset( $args['exclude'] ) ) {

		if( plura_bool( $args['exclude'] ) && is_single() ) {

			$ids = [ get_the_ID() ];

		} else if( is_array( $args['exclude'] ) || !empty( $args['exclude'] ) ) {

			$ids = is_array( $args['exclude'] ) ? $args['exclude'] : plura_explode(',', $args['exclude']);

		}

		if( isset( $ids ) ) {

			$query_params['post__not_in'] = $ids;

		}

	}


	//order
	if( !empty( $args['order'] ) ) {

		//todo

	//random order
	} else if( !empty( $args['rand'] ) ) {

		$query_params['orderby'] = 'rand';
	
	// order by timeline
	} else if( !empty( $args['timeline_start_key'] ) ) {

		$query_params = array_merge( $query_params, [
			'meta_key' => $args['timeline_start_key'],
			'meta_type' => 'DATETIME',
			'orderby' => [
				'end_date_clause' => 'ASC',    // Order first by end_date: those without end_date come first
				'start_date_clause' => 'DESC', // Then order by start_date descending: earliest start_date comes last
			]
		]);

		$meta = array_merge( $meta, [
			
			'relation' => 'AND',
			
			'start_date_clause' => [
				'key'		=> $args['timeline_start_key'],
				'compare'	=> 'EXISTS',
				'type' => 'DATETIME'
			],

			'end_date_clause' => [
				
				'relation' => 'OR',

				[
					'key' => $args['timeline_end_key'],
					'type' => 'DATETIME',
					'compare' => 'EXISTS',
				],

				[
					'key' => $args['timeline_end_key'],
					'compare' => 'NOT EXISTS',
				]
			
			]		

		]);

	}


	//active status
	if( !empty( $args['active'] ) ) {

		$value = !plura_bool( $args['active'] ) || plura_bool( $args['active'], true ) ? 1 : 0;

		$key = !empty( $args['active_key'] ) ? $args['active_key'] : ( !plura_bool( $args['active'] ) ? $args['active'] : 'status' );

		$meta[] = [
			'key'       => $key,
			'value'     => $value,
			'compare'   => '='
		];

	}
	
	//status 
	if( in_array( $args['timeline'], [0, "0", 1, "1", -1, "-1"], true) ) {

		$start = !empty($args['timeline_start_key']) ? $args['timeline_start_key'] : 'start';

		$end = !empty($args['timeline_end_key']) ? $args['timeline_end_key'] : 'end';

		$meta[] = plura_wp_posts_query_timeline( $args['timeline'], $start, $end );

	}



	if( !empty( $meta ) ) {

		$query_params['meta_query'] = $meta;

	}

	if( has_filter('plura_wp_posts_query') ) {

		$query_params = apply_filters('plura_wp_posts_query', $query_params, $args );

	}

	return new WP_Query( $query_params );

}



/* Timeline Query */
function plura_wp_posts_query_timeline($timeline, $timeline_start_key = 'start', $timeline_end_key = 'end') {
	
	// Ensure that the keys are not empty
	if (empty($timeline_start_key) || empty($timeline_end_key)) {
		
		return new WP_Error('invalid_keys', 'Both timeline_start_key and timeline_end_key must be provided.');
	
	}

	$current_date = date('Ymd'); // Format date as Ymd for ACF comparison

	// Map possible values to status types
	$timeline_map = [
		'past' => ['past', 0, '0'],
		'in_progress' => ['in_progress', 1, '1'],
		'future' => ['future', -1, '-1']
	];

	// Determine the correct status based on the input
	if (in_array($timeline, $timeline_map['past'], true)) {
		$timeline = 'past';
	} elseif (in_array($timeline, $timeline_map['in_progress'], true)) {
		$timeline = 'in_progress';
	} elseif (in_array($timeline, $timeline_map['future'], true)) {
		$timeline = 'future';
	} else {
		return new WP_Error('invalid_status', 'Invalid post progress status specified.');
	}

	switch ($timeline) {
		case 'past':
			
			return [
				[
					'key' => $timeline_end_key,
					'value' => $current_date,
					'compare' => '<',
					'type' => 'DATETIME'
				]
			];

		case 'in_progress':
			
			return [
				'relation' => 'AND',
				[
					'key' => $timeline_start_key,
					'value' => $current_date,
					'compare' => '<=',
					'type' => 'DATETIME'
				],
				[
					'relation' => 'OR',
					[
						'key' => $timeline_end_key,
						'value' => $current_date,
						'compare' => '>=',
						'type' => 'DATETIME'
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

		case 'future':
			
			return [
				[
					'key' => $timeline_start_key,
					'value' => $current_date,
					'compare' => '>',
					'type' => 'DATETIME'
				]
			];
	}
}



/* Posts */
add_shortcode('plura-wp-posts', function( $args ) {

	global $PLURA_WP_POSTS_DEFAULTS;

	$atts = shortcode_atts( $PLURA_WP_POSTS_DEFAULTS, $args );

	return plura_wp_posts( $atts );

});


function plura_wp_posts( array $args = [], array $data = [], array $posts = null ) {

	global $PLURA_WP_POSTS_DEFAULTS;

	if( !$posts ) {

		$query = plura_wp_posts_query( $args );

		if( $query->have_posts() ) {

			$posts = $query->posts;

		}

	} else {

		$args = array_merge( $PLURA_WP_POSTS_DEFAULTS, $args );

	}

	if( $posts ) {

		$html = [];

		foreach ($posts as $post) {

			$html[] = plura_wp_post( $post, $args );

		}

		$atts = ['class' => ['plura-wp-posts']];
	
		foreach (['active', 'class', 'link', 'timeline', 'type'] as $key) {
	
			if (!array_key_exists($key, $args)) {
				continue;
			}

			switch ($key) {
				case 'active':
				case 'timeline':
					if (plura_bool($args[$key])) {
						$atts['data-' . $key] = $args[$key];
					}
					break;
		
				case 'class':
					if (!empty($args['class'])) {
						$atts['class'] = array_merge(
							$atts['class'],
							is_array($args['class']) ? $args['class'] : plura_explode(' ', $args['class'])
						);
					}
					break;
		
				case 'type':
					$atts['data-type'] = $args['type'];
					break;
		
				case 'link':
					if ($args['link'] && !in_array($args['link'], [-1, "-1"], true)) {
						$atts['data-link-type'] = 'full';
					}
					break;
			}
		}	


		if( !empty( $data ) ) {

			$atts = array_merge_recursive( $atts, $data );

		}

		return "<div " . plura_attributes( $atts ) . ">" . implode('', $html) . "</div>";
	
	}

}



/* Posts: Related Posts */
add_shortcode('plura-wp-posts-related', function( $args ) {

	global $PLURA_WP_POSTS_DEFAULTS;

	$args = shortcode_atts($PLURA_WP_POSTS_DEFAULTS, $args);

	if( empty( $args['type'] ) && is_single() ) {

		$args['type'] = get_post_type();

	}

	if( empty( $args['exclude'] ) && is_single() && get_post_type() === $args['type'] ) {

		$args['exclude'] = [ get_the_ID() ];		

	}

	if( empty( $args['rand'] ) ) {

		$args['rand'] = 1;

	}

	return plura_wp_posts( $args, ['data-excluded' => 1] );

} );




/* Post */
add_shortcode('plura-wp-post', function( $args ) {

	global $PLURA_WP_POST_DEFAULTS;

	$atts = shortcode_atts($PLURA_WP_POST_DEFAULTS, $args);

	if( !empty( $atts['id'] ) ) {

		$id = $atts['id'];

		return plura_wp_post( $id, $atts );

	}

});


function plura_wp_post( WP_Post|int $post, array $args = [] ): string {

	if( is_int( $post ) || ( is_string( $post ) && ctype_digit( $post ) ) ) {

		$post = get_post( $post );

	}

	$link = array_key_exists('link', $args) && plura_bool( $args['link'], true );

	$atts = ['class' => ['plura-wp-post'] ];

	if( isset( $args['single'] ) ) {

		$atts['class'][] = 'plura-wp-post-is-single';

		$atts['data-type'] = get_post_type( $post );

	}


	$title = "<h3 class=\"plura-wp-post-title\">" . $post->post_title . "</h3>";

	if( !$args['link'] ) {

		$title = plura_wp_link( $title, $post );

	}

	$content = "<div class=\"plura-wp-post-content\">" . apply_filters('the_content', $post->post_content) . "</div>";

	$excerpt = "<div class=\"plura-wp-post-excerpt\">" . get_the_excerpt( $post ) . "</div>";

	$entry = ['title' => $title, 'excerpt' => $excerpt, 'content' => $content];

	if( !$args['link'] && $args['read-more'] ) {

		$read_more_label = is_string( $args['read-more'] ) ? $args['read-more'] : __('Learn more', 'plura');

		$entry['read-more'] = plura_wp_link( $read_more_label, $post, ['class' => 'plura-wp-post-read-more'] );

	}

	//featured image
	$img = plura_wp_thumbnail( plura_wpml_id( $post->ID ) );

	if( $img ) {

		$atts['class'][] = 'plura-wp-post-has-featured-img';

		$atts_img = ['src' => $img[0], 'width' => $img[1], 'height' => $img[2], 'class' => 'plura-wp-post-featured-img' ];

		$entry_img = "<img " . plura_attributes( $atts_img ) . "/>";

		if( !$args['link'] ) {

			$entry_img = plura_wp_link( $entry_img, $post );

		}

		$entry['featured-image'] = $entry_img;

	}

	
	//check timeline status, if data's available
	$timeline_start_key = empty( $args['timeline_start_key'] ) ? 'start' : $args['timeline_start_key'];
	
	$timeline_end_key = empty( $args['timeline_end_key'] ) ? 'end' : $args['timeline_end_key'];

	$timeline = plura_wp_get_post_timeline_status( $post->ID, $timeline_start_key, $timeline_end_key, true);

	if( in_array( $timeline, [0, 1, -1], true ) ) {

		$atts['data-timeline'] = $timeline;

		$entry['timeline'] = plura_wp_post_timeline_datetime( $post, $timeline_start_key, $timeline_end_key, $args['timeline_datetime_format'], $args['timeline_datetime_source']);

	}


	//default order
	$entry_reordered = [];

	foreach( ['featured-image', 'title', 'timeline', 'excerpt', 'content', 'read-more'] as $key ) {

		if( array_key_exists( $key, $entry ) ) {

			$entry_reordered[ $key ] = $entry[ $key ];

		}

	}

	$entry = $entry_reordered;


	//if filter's found, customize post
	if( has_filter('plura_wp_post') ) {

		$entry_filtered = apply_filters('plura_wp_post', $entry, $post, isset( $args['tag'] ) ? $args['tag'] : '' );

	}


	//if filtered $entry is different than $entry, save the new version
	if( isset( $entry_filtered ) && $entry !== $entry_filtered ) {

		$entry = $entry_filtered;

	//if not remove content (only show excerpt) in the default version
	} else {

		unset( $entry['content'] );

	}


	if( has_filter('plura_wp_post_atts') ) {

		$post_data = apply_filters('plura_wp_post_atts', $post, $args);

		if( $post_data ) {

			$atts = array_merge_recursive( $atts , $post_data );

		}

	}

	if( $args['link'] && !in_array($args['link'], [-1, "-1"], true) ) {

		return plura_wp_link( implode('', $entry), $post, $atts );

	}
	
	return "<div " . plura_attributes( $atts ) . ">" . implode('', $entry) . "</div>";

}



/* Post: Link */
function plura_wp_link( string $html, $obj, array $obj_atts = [] ): string {

	$atts = [
		'class' 	=> ['plura-wp-link'],
		//'href'		=> get_permalink( $post ), 
		'target'	=> '_blank',
		//'title' 	=> $post->post_title
	];

	if( is_a( $obj, 'WP_Post' ) ) {

		$atts = array_merge($atts, ['href' => get_permalink( $obj ), 'title' => $obj->post_title, 'data-plura-wp-link-target-type' => 'post']);

	} else if( is_a( $obj, 'WP_Term' ) ) {

		$atts = array_merge($atts, ['href' => get_term_link( $obj ), 'title' => $obj->name, 'data-plura-wp-link-target-type' => 'term']);

	} else {

		return $html;

	}

	if( !empty( $obj_atts ) ) {

		$atts = array_merge_recursive( $atts, $obj_atts );

	}

	return "<a " . plura_attributes( $atts ) . ">" . $html . "</a>";

}



/* Post: Timeline Datetime */
add_shortcode('plura-wp-post-timeline-datetime', function( $args ) {

	$args = shortcode_atts( $GLOBALS['PLURA_WP_TIMELINE_DATETIME_DEFAULTS'], $args );

	if( isset( $args['id'] ) || is_single() ) {

		$post = get_post( $args['id'] ?? get_the_ID() );

		return plura_wp_post_timeline_datetime( $post, $args['timeline_start_key'], $args['timeline_end_key'], $args['timeline_datetime_format'], $args['timeline_datetime_source']);

	}

} );


function plura_wp_post_timeline_datetime( $post, $timeline_start_key, $timeline_end_key, $timeline_datetime_format = '', $timeline_datetime_source = '' ) {

	$atts = ['class' => ['plura-wp-post-timeline']];

	$entry = [];

	if( !empty( $timeline_start_key ) ) {

		$datetime = get_field( $timeline_start_key, $post->ID);

		if( $datetime ) {

			$atts['class'][] = 'has-start';

			$entry[] = plura_wp_datetime( ['class' => ['plura-wp-post-timeline-item', 'plura-wp-post-timeline-start'], 'date' => $datetime, 'source' => $timeline_datetime_source, 'format' => $timeline_datetime_format] );

		}

	}

	if( !empty( $timeline_end_key ) ) {

		$datetime = get_field($timeline_end_key, $post->ID);

		if( $datetime ) {

			$atts['class'][] = 'has-end';

			$entry[] = plura_wp_datetime( ['class' => ['plura-wp-post-timeline-item', 'plura-wp-post-timeline-end'], 'date' => $datetime, 'source' => $timeline_datetime_source, 'format' => $timeline_datetime_format] );

		}

	}

	if( !empty( $entry ) ) {

		return "<div " . plura_attributes( $atts ) . ">" . implode('', $entry) . "</div>";

	}

	return false;

}



/* Post: Timeline Status */
function plura_wp_get_post_timeline_status($post_id, $timeline_start_key = 'start', $timeline_end_key = 'end', $int = true) {
    
    $current_date = date('Ymd'); // Format date as Ymd for comparison

    // Retrieve custom fields
    $start_date = get_post_meta($post_id, $timeline_start_key, true);
    $end_date = get_post_meta($post_id, $timeline_end_key, true);

    // Ensure that dates are in 'Ymd' format
    $start_date = !empty($start_date) ? $start_date : '';
    $end_date = !empty($end_date) ? $end_date : '';


    // Handle empty or missing start date
    if (empty($start_date)) {
        return false;
    }

    // Check if the post is in progress
    if ($start_date <= $current_date && (empty($end_date) || $end_date >= $current_date)) {
        return $int ? 1 : 'in_progress';
    }

    // Check if the post is in the past
    if (!empty($end_date) && $end_date < $current_date) {
        return $int ? 0 : 'past';
    }

    // Check if the post is in the future
    if ($start_date > $current_date) {
        return $int ? -1 : 'future';
    }

    // If none of the above conditions are met, return false
    return false;

}

