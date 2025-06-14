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



/**
 * Enqueue multiple CSS or JS files using absolute paths or pattern-based paths.
 *
 * @param array  $scripts List of file paths or pattern paths (with optional options).
 *                        Each item can be:
 *                        - string path
 *                        - string => array (with 'deps', 'handle', 'media' keys)
 *                        - pattern path with "%s" (e.g. __DIR__ . '/%s/global.%s')
 * @param bool   $cache   Whether to use filemtime for cache busting (true) or false to use timestamp on each load.
 * @param string $prefix  String to prefix each handle with.
 */
function plura_wp_enqueue( array $scripts, bool $cache = true, string $prefix = '' ) {
	foreach ( $scripts as $path => $options ) {

		if ( is_int( $path ) ) {
			$path = $options;
			$options = [];
		}

		// Handle pattern like: /path/to/%s/script.%s (only for local files)
		if ( strpos( $path, '%s' ) !== false ) {
			foreach ( ['css', 'js'] as $type ) {
				$file = sprintf( $path, $type, $type );
				if ( file_exists( $file ) ) {
					plura_wp_enqueue_asset( $type, $file, $options, $cache, $prefix );
				}
			}
		} else {
			$ext = pathinfo( parse_url( $path, PHP_URL_PATH ), PATHINFO_EXTENSION );

			if ( in_array( $ext, ['css', 'js'], true ) ) {
				// Only enqueue if it's a valid URL (http(s) or protocol-relative) or an existing local file
				if ( preg_match( '#^(https?:)?//#', $path ) || file_exists( $path ) ) {
					plura_wp_enqueue_asset( $ext, $path, $options, $cache, $prefix );
				}
			}
		}
	}
}


/**
 * Enqueue a single CSS or JS file with optional dependencies and settings.
 *
 * @param string $type    Either 'css' or 'js'.
 * @param string $file    Absolute path or URL to the file to enqueue.
 * @param array  $options Optional settings:
 *                        - 'handle' => custom handle (defaults to filename slug)
 *                        - 'deps'   => array of dependencies
 *                        - 'media'  => only for CSS (defaults to 'all')
 * @param bool   $cache   Whether to use filemtime for version (true) or time() (false).
 * @param string $prefix  Optional prefix for auto-generated handles.
 */
function plura_wp_enqueue_asset( string $type, string $file, array $options = [], bool $cache = true, string $prefix = '' ) {
	$is_external = preg_match( '#^https?://#', $file ) || str_starts_with( $file, '//' );

	$base_name = basename( parse_url( $file, PHP_URL_PATH ) );
	$slug = sanitize_title( preg_replace( '/\.(css|js)$/', '', $base_name ) );

	$handle = $prefix . ( $options['handle'] ?? $slug );
	$deps   = $options['deps']   ?? [];
	$media  = $options['media']  ?? 'all';
	$ver    = $is_external ? false : ( $cache ? filemtime( $file ) : time() );
	$url    = $is_external ? $file : plura_wp_file_url( $file );

	if ( $type === 'css' && !wp_style_is( $handle, 'enqueued' ) ) {
		wp_enqueue_style( $handle, $url, $deps, $ver, $media );
	}
	if ( $type === 'js' && !wp_script_is( $handle, 'enqueued' ) ) {
		wp_enqueue_script( $handle, $url, $deps, $ver );
	}
}





/**
 * Convert an absolute file path (inside wp-content) to a corresponding URL.
 *
 * Uses WordPress internals to resolve the proper content URL.
 *
 * @param string $file Absolute file path (e.g., __DIR__ . '/js/script.js').
 * @return string Corresponding URL to be used in wp_enqueue_*.
 */
function plura_wp_file_url( string $file ): string {
	$wp_content_dir = wp_normalize_path( WP_CONTENT_DIR );
	$wp_content_url = content_url();

	$file_path = wp_normalize_path( $file );

	if ( strpos( $file_path, $wp_content_dir ) === 0 ) {
		$relative_path = ltrim( str_replace( $wp_content_dir, '', $file_path ), '/' );
		return trailingslashit( $wp_content_url ) . $relative_path;
	}

	// Fallback: return original path (may not work if outside wp-content).
	return $file;
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




/**
 * Generates a linked HTML element for WordPress posts, terms, or URLs.
 *
 * Automatically adds `target="_blank"` for external URLs (not within the current site).
 *
 * @param string                 $html   The inner HTML to wrap in the link.
 * @param WP_Post|WP_Term|string|null $target A WP_Post, WP_Term, or external URL. If null/invalid, returns $html.
 * @param array                  $atts   Optional attributes for the <a> tag.
 * @param bool                   $rel    Whether to add rel="noopener noreferrer" if target="_blank".
 *
 * @return string The generated <a> tag wrapping the HTML, or the original HTML if no valid link target.
 */
function plura_wp_link(
	string $html,
	WP_Post|WP_Term|string|null $target = null,
	array $atts = [],
	bool $rel = false
): string {
	if ( ! $target ) {
		return $html;
	}

	$link_atts = [
		'class' => ['plura-wp-link'],
	];

	// Determine href and object-specific attributes
	if ( $target instanceof WP_Post ) {
		$href = get_permalink( $target );
		$link_atts = array_merge( $link_atts, [
			'title' => $target->post_title,
			'data-plura-wp-link-target-type' => 'post',
		] );
	} elseif ( $target instanceof WP_Term ) {
		$href = get_term_link( $target );
		$link_atts = array_merge( $link_atts, [
			'title' => $target->name,
			'data-plura-wp-link-target-type' => 'term',
		] );
	} elseif ( is_string( $target ) && preg_match( '#^https?://#', $target ) ) {
		$href = $target;
	} else {
		return $html;
	}

	$link_atts['href'] = $href;

	// Automatically add target="_blank" for external links (supports subdir installs)
	$site_url = rtrim( home_url(), '/' );
	$link_url = rtrim( $href, '/' );
	if ( stripos( $link_url, $site_url ) !== 0 ) {
		$link_atts['target'] = '_blank';
	}

	// Merge user-defined attributes first
	if ( ! empty( $atts ) ) {
		$link_atts = array_merge_recursive( $link_atts, $atts );
	}

	// Add rel attribute if needed and final target is '_blank'
	if ( $rel && ( $link_atts['target'] ?? '' ) === '_blank' ) {
		$link_atts['rel'] = 'noopener noreferrer';
	}

	return sprintf( '<a %s>%s</a>', plura_attributes( $link_atts ), $html );
}



/**
 * Returns the post thumbnail URL and size data for a given post.
 *
 * @param int|WP_Post $post Post ID or WP_Post object.
 * @param string      $size Optional. Image size to retrieve. Default 'large'.
 *
 * @return array|false Array of image data (URL, width, height, is_intermediate) or false if no thumbnail found.
 */
function plura_wp_thumbnail( int|WP_Post $post, string $size = 'large' ): array|false {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	if ( has_post_thumbnail( $post ) ) {
		return wp_get_attachment_image_src( get_post_thumbnail_id( $post ), $size );
	}

	return false;
}





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

