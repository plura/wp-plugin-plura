<?php

/**
 * 		. Utils
 *		 	- Enqueue
 *    	. REST
 *    		- IDs
 *    	. Layout
 *    		- Datetime
 *     		- Nav List
 */



/* Utils: Enqueue */
function plura_wp_enqueue( $scripts, $basefile, $prefix = '', $dir = 'includes/', $deps = [], $cache = true ) {

	foreach($scripts as $key) {

		foreach( ['css', 'js'] as $type ) {

			$path = $dir . "{$type}/{$key}.{$type}"; 

			if( file_exists( dirname( $basefile ) . "/" . $path ) ) {

				if( $type === 'css' ) {

					wp_enqueue_style( $prefix . $key, plugins_url( $path, $basefile ), $deps, $cache ? false : time() );

				} else {

					wp_enqueue_script( $prefix . $key, plugins_url( $path, $basefile ), $deps, $cache ? false : time() );
				
				}

			}

		}

	}	


}



/**
 * Register REST API endpoint for batch post data retrieval
 * 
 * Endpoint: GET /pwp/v1/ids?ids=1,2,3
 * Returns: { [id]: { title: string, id: int, url: string } }
 */
add_action('rest_api_init', function() {
    register_rest_route('pwp/v1', '/ids', [
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'plura_wp_ids',
        'args'     => [
            'ids' => [
                'required'          => true,
                'validate_callback' => function($param) {
                    return is_string($param) && preg_match('/^\d+(,\d+)*$/', $param);
                },
                'sanitize_callback' => 'sanitize_text_field',
                'description'      => __('Comma-separated list of post IDs', 'plura')
            ]
        ],
        'permission_callback' => '__return_true'
    ]);
});


/**
 * Retrieves post data for specified IDs from REST request
 *
 * @param WP_REST_Request|null $request Optional REST request object containing 'ids' parameter
 * @return array<int,array{title:string,id:int,url:string}> Associative array of post data keyed by ID
 */
function plura_wp_ids(?WP_REST_Request $request = null): array {
    // Early return if no valid request or IDs parameter
    if (!$request || !$request->get_param('ids')) {
        return [];
    }

    $ids = array_filter(
        array_map('intval', explode(',', $request->get_param('ids'))),
        fn($id) => $id > 0
    );

    if (empty($ids)) {
        return [];
    }

    $query = new WP_Query([
        'post_type'      => 'any',
        'post__in'       => $ids,
        'posts_per_page' => count($ids),
        'no_found_rows'  => true,
        'orderby'        => 'post__in'
    ]);

    if (!$query->have_posts()) {
        return [];
    }

    $data = [];
    foreach ($query->posts as $post) {
        $data[$post->ID] = [
            'title' => $post->post_title,
            'id'    => $post->ID,
            'url'   => get_permalink($post)
        ];
    }

    return $data;
}



/*function plura_wp_enqueue( $scripts, $prefix = '' ) {

	foreach($scripts as $key) {

		foreach( ['css', 'js'] as $type ) {

			$path = "includes/{$type}/{$key}.{$type}";
 
			if( file_exists( dirname( __FILE__, 3 ) . "/" . $path ) ) {

				if( $type === 'css' ) {

					wp_enqueue_style( $prefix . $key, plugins_url( $path, dirname( __FILE__, 2 ) ), [], time() );

				} else {

					wp_enqueue_script( $prefix . $key, plugins_url( $path, dirname( __FILE__, 2 ) ), [], time() );
				
				}

			}

		}

	}

} */




/* Layout: Datetime */

/**
 * Formats and outputs a datetime with HTML attributes.
 *
 * Supports localization, HTML customization, and optional relative time formatting.
 *
 * @param DateTime|string|null $date     The date to format (DateTime object, date string, or null)
 * @param string|array|null    $class    CSS class(es) for the wrapper element
 * @param string               $format   Date format string (default: 'l, F jS, Y g:i A')
 * @param int|null             $id       Post ID to fetch the post date (overrides $date if provided)
 * @param string               $source   Source date format for parsing string dates (default: 'Y-m-d H:i:s')
 * @param string               $tag      HTML tag to wrap the date (default: 'time')
 * @param bool                 $relative Whether to show relative time instead of formatted date (default: false)
 *
 * @return string|null Formatted HTML string or null if the date is invalid
 */
function plura_wp_datetime(
	DateTime|string|null $date = null,
	string|array|null $class = null,
	string $format = 'l, F jS, Y g:i A',
	?int $id = null,
	string $source = 'Y-m-d H:i:s',
	string $tag = 'time',
	bool $relative = false
): ?string {
	// If an ID is provided, get the post date
	if ($id) {
		$date = get_the_date($source, $id);
	}

	// Create DateTime object from either string or existing DateTime
	$datetime = $date instanceof DateTime ? $date : null;
	if (!$datetime && $date !== null && $date !== '') {
		$datetime = DateTime::createFromFormat($source, $date);

		// Fallback to flexible parser if format fails
		if (!$datetime && is_string($date)) {
			try {
				$datetime = new DateTime($date);
			} catch (Exception $e) {
				return null;
			}
		}
	}

	if (!$datetime) {
		return null;
	}

	$timestamp = $datetime->getTimestamp();

	// Format display value
	if ($relative) {
		$diff = human_time_diff($timestamp, time());

		$suffix = $timestamp < time()
			? apply_filters('plura_datetime_suffix_past', __('ago'))
			: apply_filters('plura_datetime_suffix_future', __('from now'));

		$suffix_html = sprintf('<span class="relative-suffix">%s</span>', esc_html($suffix));

		$display = esc_html($diff) . ' ' . $suffix_html;
	} else {
		$display = date_i18n($format, $timestamp);
	}

	// Build attributes array
	$atts = [
		'class' => ['plura-wp-datetime'],
		'data-date-month' => $datetime->format('F'),
		'data-date-month-short' => $datetime->format('M'),
		'datetime' => $datetime->format(DateTime::ATOM), // ISO 8601
	];

	// Merge additional classes if provided
	if ($class !== null) {
		$atts['class'] = array_merge(
			$atts['class'],
			is_array($class) ? $class : preg_split('/\s+/', trim($class))
		);
	}

	return sprintf(
		'<%1$s %2$s>%3$s</%1$s>',
		$tag,
		plura_attributes($atts),
		$display
	);
}

add_shortcode('plura-wp-datetime', function($args) {
    $atts = shortcode_atts([
        'date' => null,       // Date string or timestamp
        'class' => null,      // Optional CSS class
        'format' => 'l, F jS, Y g:i A',
        'id' => null,         // Optional post ID
        'source' => 'Y-m-d H:i:s',
        'tag' => 'time',      // HTML wrapper tag
        'relative' => false   // Use relative time format (e.g., "3 days ago")
    ], $args);

    $atts['id'] = $atts['id'] !== null ? (int) $atts['id'] : null;
    $atts['relative'] = filter_var($atts['relative'], FILTER_VALIDATE_BOOLEAN);

	if (empty($atts['date']) && empty($atts['id'])) {
		if (is_single()) {
			$atts['id'] = get_the_ID();
		} else {
			$atts['date'] = date_i18n($atts['source']);
		}
	}

    return plura_wp_datetime(...$atts);
});




/* Layout: Nav List */
add_shortcode('plura-wp-nav-list', function( $args ) {

    $args = shortcode_atts(['id' => '', 'class' => '', 'rel' => '', 'list' => 1, 'drop' => 1], $args);

    if( has_filter('pwp_nav_list') ) {

        $id = apply_filters('pwp_nav_list', $args['rel'] );

    }

    if( isset( $id ) || !empty( $args['id'] ) ) {

    	$data = '';

    	$html = [];

    	if( !empty( $args['class'] ) ) {

    		$classes = array_merge( $classes, explode(',', $args['class'] ) );

    	}


    	if( $args['list'] ) {

    		$classes = ['menu', 'list'];

	    	$html[] = wp_nav_menu([

				'echo'          => 0,
				'items_wrap'	=> '<ul id="%1$s" class="%2$s">%3$s</ul>',
				'menu'          => isset( $id ) ? $id : $args['id'],
				'menu_class'    => implode(' ', $classes)

	        ]);

	   }

	   if( $args['drop'] ) {

	   	    $classes = ['menu', 'drop'];

   	    	$html[] = wp_nav_menu([

   				'echo'          => 0,
   				'items_wrap'	=> '<select class="%2$s">%3$s</select>',
   				'menu'          => isset( $id ) ? $id : $args['id'],
   				'menu_class'    => implode(' ', $classes),
   				'walker'		=> new P_Walker_Nav_Menu_Dropdown()

   	        ]);

	   }

	   if( !empty( $html ) ) {

	   		$classes = ['plura-wp-nav-list'];

	   		if( !empty( $args['class'] ) ) {

	   			$classes = array_merge( $classes, explode(',', $args['class'] ) );

	   		}

	   		$atts = ['class' => implode(' ', $classes) ];

	   		if( !empty( $args['rel'] ) ) {

	   			$atts['data-rel'] = $args['rel'];

	   		}

	   		return "<div " . plura_attributes( $atts ) . ">" . implode('', $html) . "</div>";

	   }


    }

} );



/**
 * Generates a linked HTML element for WordPress posts or terms.
 *
 * @param string $html The inner HTML content to wrap in the link.
 * @param WP_Post|WP_Term|null $obj The WordPress object to link to (post or term). If null or invalid, returns $html.
 * @param array $obj_atts Additional attributes for the <a> element (merged with defaults).
 * @param bool|string $target Target behavior:
 *     - true (default): Adds target="_blank" only if the link is external to the current site.
 *     - string: Adds target with the specified value (e.g., '_blank', '_self').
 *     - false or empty: No target attribute added.
 * @param bool $rel Whether to add rel="noopener noreferrer" if target is '_blank' (default: false).
 *
 * @return string The generated <a> tag wrapping the given HTML, or original HTML if $obj is invalid.
 */
function plura_wp_link(
	string $html,
	WP_Post|WP_Term|null $obj = null,
	array $obj_atts = [],
	bool|string $target = true,
	bool $rel = false
): string {
	if (!$obj) {
		return $html;
	}

	$atts = [
		'class' => ['plura-wp-link'],
	];

	// Determine href and object-specific attributes
	if ($obj instanceof WP_Post) {
		$href = get_permalink($obj);
		$atts = array_merge($atts, [
			'title' => $obj->post_title,
			'data-plura-wp-link-target-type' => 'post'
		]);
	} elseif ($obj instanceof WP_Term) {
		$href = get_term_link($obj);
		$atts = array_merge($atts, [
			'title' => $obj->name,
			'data-plura-wp-link-target-type' => 'term'
		]);
	} else {
		return $html;
	}

	$atts['href'] = $href;

	// Handle target logic
	if ($target === true) {
		$site_url = rtrim(home_url(), '/');
		$link_url = rtrim($href, '/');

		// If link does NOT start with the current site URL, treat as external
		if (stripos($link_url, $site_url) !== 0) {
			$atts['target'] = '_blank';
		}
	} elseif (is_string($target) && !empty($target)) {
		$atts['target'] = $target;
	}

	// Handle rel="noopener noreferrer" when needed
	if ($rel && isset($atts['target']) && $atts['target'] === '_blank') {
		$atts['rel'] = 'noopener noreferrer';
	}

	// Merge additional attributes
	if (!empty($obj_atts)) {
		$atts = array_merge_recursive($atts, $obj_atts);
	}

	return sprintf('<a %s>%s</a>', plura_attributes($atts), $html);
}
