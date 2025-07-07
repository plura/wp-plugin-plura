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
 * @param bool   $admin   Whether to enqueue assets in the admin area (default: true).
 */
function plura_wp_enqueue(array $scripts, bool $cache = true, string $prefix = '', bool $admin = true)
{

	// Exit early if we’re in admin and $admin is false
	if (!$admin && is_admin()) {
		return;
	}

	foreach ($scripts as $path => $options) {

		if (is_int($path)) {
			$path = $options;
			$options = [];
		}

		// Handle pattern like: /path/to/%s/script.%s (only for local files)
		if (strpos($path, '%s') !== false) {
			foreach (['css', 'js'] as $type) {
				$file = sprintf($path, $type, $type);
				if (file_exists($file)) {
					plura_wp_enqueue_asset($type, $file, $options, $cache, $prefix);
				}
			}
		} else {
			$ext = pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION);

			if (in_array($ext, ['css', 'js'], true)) {
				// Only enqueue if it's a valid URL (http(s) or protocol-relative) or an existing local file
				if (preg_match('#^(https?:)?//#', $path) || file_exists($path)) {
					plura_wp_enqueue_asset($ext, $path, $options, $cache, $prefix);
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
function plura_wp_enqueue_asset(string $type, string $file, array $options = [], bool $cache = true, string $prefix = '')
{
	$is_external = preg_match('#^https?://#', $file) || str_starts_with($file, '//');

	$base_name = basename(parse_url($file, PHP_URL_PATH));
	$slug = sanitize_title(preg_replace('/\.(css|js)$/', '', $base_name));

	$handle = $prefix . ($options['handle'] ?? $slug);
	$deps   = $options['deps']   ?? [];
	$media  = $options['media']  ?? 'all';
	$ver    = $is_external ? false : ($cache ? filemtime($file) : time());
	$url    = $is_external ? $file : plura_wp_file_url($file);

	if ($type === 'css' && !wp_style_is($handle, 'enqueued')) {
		wp_enqueue_style($handle, $url, $deps, $ver, $media);
	}
	if ($type === 'js' && !wp_script_is($handle, 'enqueued')) {
		wp_enqueue_script($handle, $url, $deps, $ver);
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
function plura_wp_file_url(string $file): string
{
	$wp_content_dir = wp_normalize_path(WP_CONTENT_DIR);
	$wp_content_url = content_url();

	$file_path = wp_normalize_path($file);

	if (strpos($file_path, $wp_content_dir) === 0) {
		$relative_path = ltrim(str_replace($wp_content_dir, '', $file_path), '/');
		return trailingslashit($wp_content_url) . $relative_path;
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
add_action('rest_api_init', function () {
	register_rest_route('pwp/v1', '/ids', [
		'methods'  => WP_REST_Server::READABLE,
		'callback' => 'plura_wp_ids',
		'args'     => [
			'ids' => [
				'required'          => true,
				'validate_callback' => function ($param) {
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
function plura_wp_ids(?WP_REST_Request $request = null): array
{
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

add_shortcode('plura-wp-datetime', function ($args) {
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
 * @param string                 $html    The inner HTML to wrap in the link.
 * @param WP_Post|WP_Term|string|null $target A WP_Post, WP_Term, or external URL. If null/invalid, returns $html.
 * @param array                  $atts    Optional attributes for the <a> tag.
 * @param bool                   $rel     Whether to add rel="noopener noreferrer" if target="_blank".
 * @param string|null            $title   Optional. Title attribute for the link (shown on hover). Defaults to post/term title.
 *
 * @return string The generated <a> tag wrapping the HTML, or the original HTML if no valid link target.
 */
function plura_wp_link(
	string $html,
	WP_Post|WP_Term|string|null $target = null,
	array $atts = [],
	bool $rel = false,
	?string $title = null
): string {
	if (! $target) {
		return $html;
	}

	$link_atts = [
		'class' => ['plura-wp-link'],
	];

	// Determine href and object-specific attributes
	if ($target instanceof WP_Post) {
		$href = get_permalink($target);
		$link_atts = array_merge($link_atts, [
			'title' => $title ?? $target->post_title,
			'data-plura-wp-link-target-type' => 'post',
		]);
	} elseif ($target instanceof WP_Term) {
		$href = get_term_link($target);
		$link_atts = array_merge($link_atts, [
			'title' => $title ?? $target->name,
			'data-plura-wp-link-target-type' => 'term',
		]);
	} elseif (is_string($target) && preg_match('#^https?://#', $target)) {
		$href = $target;
		if ($title) {
			$link_atts['title'] = $title;
		}
	} else {
		return $html;
	}

	$link_atts['href'] = $href;

	// Automatically add target="_blank" for external links (supports subdir installs)
	$site_url = rtrim(home_url(), '/');
	$link_url = rtrim($href, '/');
	// If link does NOT start with the current site URL, treat as external
	if (stripos($link_url, $site_url) !== 0) {
		$link_atts['target'] = '_blank';
	}

	// Merge user-defined attributes first
	if (! empty($atts)) {
		$link_atts = array_merge_recursive($link_atts, $atts);
	}

	// Add rel attribute if needed and final target is '_blank'
	if ($rel && ($link_atts['target'] ?? '') === '_blank') {
		$link_atts['rel'] = 'noopener noreferrer';
	}

	return sprintf('<a %s>%s</a>', plura_attributes($link_atts), $html);
}



/**
 * Retrieves image data for a given image attachment.
 *
 * Returns an array of image information, including `src`, `width`, `height`, `alt`, `id`, and `title`.
 * Useful for generating <img>, <picture>, or other custom image markup.
 *
 * @param int|WP_Post $attachment Attachment ID or WP_Post object. Must be an image.
 * @param string      $size       Image size to retrieve. Defaults to 'large'.
 *
 * @return array|null             Associative array of image data or null if invalid.
 */
function plura_wp_image_data(int|WP_Post $attachment, string $size = 'large'): ?array
{
	$attachment = get_post($attachment);

	if (
		! $attachment instanceof WP_Post ||
		'attachment' !== $attachment->post_type ||
		! wp_attachment_is_image($attachment)
	) {
		return null;
	}

	$src_data = wp_get_attachment_image_src($attachment->ID, $size);

	if (! $src_data) {
		return null;
	}

	return [
		'src'    => esc_url($src_data[0]),
		'width'  => (int) $src_data[1],
		'height' => (int) $src_data[2],
		'alt'    => esc_attr(get_post_meta($attachment->ID, '_wp_attachment_image_alt', true) ?: get_the_title($attachment)),
		'id'     => $attachment->ID,
		'title'  => esc_html(get_the_title($attachment)),
	];
}



/**
 * Generates an <img> HTML tag for a given image attachment.
 *
 * Uses `plura_wp_image_data()` to retrieve the image info and builds the final HTML <img> tag.
 * The default class 'plura-wp-image' is always included.
 *
 * @param int|WP_Post $attachment Attachment ID or WP_Post object. Must be an image.
 * @param string      $size       Image size to retrieve. Defaults to 'large'.
 * @param array       $atts       Optional HTML attributes. 'class' can be a string or an array.
 *
 * @return string|null            HTML <img> tag or null if image is invalid.
 */
function plura_wp_image(int|WP_Post $attachment, string $size = 'large', array $atts = []): ?string
{
	$data = plura_wp_image_data($attachment, $size);

	if (! $data) {
		return null;
	}

	// Handle 'class' attribute: string or array
	if (isset($atts['class'])) {
		$classes = is_array($atts['class'])
			? $atts['class']
			: explode(' ', $atts['class']);

		$atts['class'] = array_filter(array_map('trim', $classes));
	} else {
		$atts['class'] = [];
	}

	// Ensure default class is included
	if (! in_array('plura-wp-image', $atts['class'], true)) {
		array_unshift($atts['class'], 'plura-wp-image');
	}

	// Merge image data into attributes
	$atts = array_merge([
		'src'    => $data['src'],
		'width'  => $data['width'],
		'height' => $data['height'],
		'alt'    => $data['alt'],
	], $atts);

	return sprintf(
		'<img %s />',
		plura_attributes($atts)
	);
}



/**
 * Renders a gallery of image attachments from various possible sources.
 *
 * Accepts ACF-style arrays, image IDs, posts with featured images, or direct image attachments.
 *
 * @param array|int|string|WP_Post $source                   The source of images (e.g., array of image IDs or ACF field key).
 * @param string                   $source_key               Optional. Used if $source is an ACF field key (post ID or key).
 * @param bool                     $unique                   Optional. Remove duplicate image IDs. Default true.
 * @param bool                     $source_featured_image    Optional. Whether to prepend the source post's featured image. Default false.
 * @param string|null              $context                  Optional context string used in filters.
 *
 * @return string HTML markup of the rendered gallery, or an empty string if no images found.
 */
function plura_wp_gallery(
	array|int|string|WP_Post $source,
	string $source_key = '',
	bool $unique = true,
	bool $source_featured_image = false,
	?string $context = null
): string {
	$items = [];
	$ids = [];

	// Step 1: Get featured image ID from the source post, if requested
	if (
		$source_featured_image &&
		(is_numeric($source) || $source instanceof WP_Post)
	) {
		$items[] = is_numeric($source) ? (int) $source : $source->ID;
	}

	// Step 2: Resolve source if it's an ACF field key
	if (is_string($source_key) && (is_numeric($source) || $source instanceof WP_Post)) {
		$field_value = get_field($source_key, is_numeric($source) ? (int) $source : $source->ID);
		$items = array_merge($items, (array) (is_array($field_value) ? $field_value : []));
	}

	// Step 3: Normalize all source values into valid image attachment IDs
	foreach ($items as $item) {
		if (is_numeric($item)) {
			$id = (int) $item;
		} elseif (is_array($item) && isset($item['ID'])) {
			$id = (int) $item['ID'];
		} elseif ($item instanceof WP_Post) {
			$id = $item->ID;
		} else {
			continue;
		}

		if (wp_attachment_is_image($id)) {
			$ids[] = $id;
		} else {
			$thumb_id = get_post_thumbnail_id($id);
			if ($thumb_id && wp_attachment_is_image($thumb_id)) {
				$ids[] = $thumb_id;
			}
		}
	}

	// Step 4: Remove duplicates if requested
	if ($unique) {
		$ids = array_unique($ids);
	}

	// Step 4.5: Filter image IDs before rendering the gallery
	$ids = apply_filters('plura_wp_gallery', $ids, $source, $source_key, $context);

	// Step 5: Render gallery HTML
	$html = [];

	foreach (array_filter($ids) as $id) {
		$thumb = plura_wp_image_data($id, 'medium');
		$html[] = sprintf(
			'<div %s>%s</div>',
			plura_attributes(['class' => 'plura-wp-gallery-item', 'data-thumb-src' => $thumb['src'] ?? null]),
			plura_wp_image($id)
		);
	}

	if (!empty($html)) {
		$atts = ['class' => 'plura-wp-gallery'];

		return sprintf(
			'<div %s>%s</div>',
			plura_attributes($atts),
			implode("\n", $html)
		);
	}

	return '';
}

/**
 * Registers the [plura-wp-gallery] shortcode to render an image gallery.
 *
 * This shortcode pulls images from a specified ACF field key of a given post.
 * Optionally, it can prepend the post’s featured image to the gallery.
 *
 * Attributes:
 * - source (int, optional): Post ID to fetch images from. Defaults to current post if inside a loop.
 * - source_key (string, required): ACF field key containing the gallery/image data.
 * - source_featured_image (bool, optional): Whether to prepend the post’s featured image. Accepts true/false. Default: false.
 *
 * Examples:
 * [plura-wp-gallery source="123" source_key="gallery"]
 * [plura-wp-gallery source_key="images" source_featured_image="true"]
 */
add_shortcode('plura-wp-gallery', function ($args) {
	$atts = shortcode_atts([
		'source' => null,
		'source_key' => '',
		'source_featured_image' => false,
	], $args);

	$source = is_numeric($atts['source']) ? (int) $atts['source'] : (is_singular() ? get_the_ID() : 0);

	$add_featured_image = filter_var($atts['source_featured_image'], FILTER_VALIDATE_BOOLEAN);

	//echo 'add_featured_image: ' . $add_featured_image . '| source: '. $source;

	if ($source && ($add_featured_image || !empty($atts['source_key']))) {

		return plura_wp_gallery(
			source: $source,
			source_key: $atts['source_key'],
			source_featured_image: $add_featured_image
		);
	}

	return '';
});







/**
 * Returns the post thumbnail URL and size data for a given post.
 *
 * @param int|WP_Post $post Post ID or WP_Post object.
 * @param string      $size Optional. Image size to retrieve. Default 'large'.
 *
 * @return array|false Array of image data (URL, width, height, is_intermediate) or false if no thumbnail found.
 */
function plura_wp_thumbnail(int|WP_Post $post, string $size = 'large'): array|false
{
	$post = get_post($post);

	if (! $post instanceof WP_Post) {
		return false;
	}

	if (has_post_thumbnail($post)) {
		return wp_get_attachment_image_src(get_post_thumbnail_id($post), $size);
	}

	return false;
}





/* Layout: Nav List */
add_shortcode('plura-wp-nav-list', function ($args) {

	$args = shortcode_atts(['id' => '', 'class' => '', 'rel' => '', 'list' => 1, 'drop' => 1], $args);

	if (has_filter('pwp_nav_list')) {

		$id = apply_filters('pwp_nav_list', $args['rel']);
	}

	if (isset($id) || !empty($args['id'])) {

		$data = '';

		$html = [];

		if (!empty($args['class'])) {

			$classes = array_merge($classes, explode(',', $args['class']));
		}


		if ($args['list']) {

			$classes = ['menu', 'list'];

			$html[] = wp_nav_menu([

				'echo'          => 0,
				'items_wrap'	=> '<ul id="%1$s" class="%2$s">%3$s</ul>',
				'menu'          => isset($id) ? $id : $args['id'],
				'menu_class'    => implode(' ', $classes)

			]);
		}

		if ($args['drop']) {

			$classes = ['menu', 'drop'];

			$html[] = wp_nav_menu([

				'echo'          => 0,
				'items_wrap'	=> '<select class="%2$s">%3$s</select>',
				'menu'          => isset($id) ? $id : $args['id'],
				'menu_class'    => implode(' ', $classes),
				'walker'		=> new P_Walker_Nav_Menu_Dropdown()

			]);
		}

		if (!empty($html)) {

			$classes = ['plura-wp-nav-list'];

			if (!empty($args['class'])) {

				$classes = array_merge($classes, explode(',', $args['class']));
			}

			$atts = ['class' => implode(' ', $classes)];

			if (!empty($args['rel'])) {

				$atts['data-rel'] = $args['rel'];
			}

			return "<div " . plura_attributes($atts) . ">" . implode('', $html) . "</div>";
		}
	}
});
