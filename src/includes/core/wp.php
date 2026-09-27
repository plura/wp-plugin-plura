<?php

/**
 * Shared WordPress building blocks: asset enqueueing, the /pwp/v1/ids REST endpoint, request
 * helpers, and the datetime, link and title renderers the other modules build on.
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
 *
 * @return void
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
 *                        - 'module' => true to add type="module" to the script tag
 * @param bool   $cache   Whether to use filemtime for version (true) or time() (false).
 * @param string $prefix  Optional prefix for auto-generated handles.
 *
 * @return void
 */
function plura_wp_enqueue_asset(string $type, string $file, array $options = [], bool $cache = true, string $prefix = '')
{
	$is_external = preg_match('#^https?://#', $file) || str_starts_with($file, '//');

	$base_name = basename(parse_url($file, PHP_URL_PATH));
	$slug = sanitize_title(preg_replace('/\.(css|js)$/', '', $base_name));

	$handle = $prefix . ($options['handle'] ?? $slug);
	$deps = $options['deps'] ?? [];
	$media = $options['media'] ?? 'all';
	$ver = $is_external ? false : ($cache ? filemtime($file) : time());
	$url = $is_external ? $file : plura_wp_file_url($file);

	if ($type === 'css' && !wp_style_is($handle, 'enqueued')) {
		wp_enqueue_style($handle, $url, $deps, $ver, $media);
	}
	if ($type === 'js' && !wp_script_is($handle, 'enqueued')) {
		wp_enqueue_script($handle, $url, $deps, $ver);

		if (!empty($options['module'])) {
			static $module_handles = [];

			if (empty($module_handles)) {
				add_filter('script_loader_tag', function (string $tag, string $h) use (&$module_handles): string {
					return isset($module_handles[$h])
						? str_replace('<script ', '<script type="module" ', $tag)
						: $tag;
				}, 10, 2);
			}

			$module_handles[$handle] = true;
		}
	}
}

/**
 * Convert an absolute file path (inside wp-content) to a corresponding URL.
 *
 * Uses WordPress internals to resolve the proper content URL.
 *
 * @param string $file Absolute file path (e.g., __DIR__ . '/js/script.js').
 *
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
				'description'       => __('Comma-separated list of post IDs', 'plura'),
			],
		],
		'permission_callback' => '__return_true',
	]);
});

/**
 * Retrieves post data for specified IDs from REST request
 *
 * @param WP_REST_Request|null $request Optional REST request object containing 'ids' parameter
 *
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
		fn($id) => $id > 0,
	);

	if (empty($ids)) {
		return [];
	}

	$query = new WP_Query([
		'post_type'      => 'any',
		'post__in'       => $ids,
		'posts_per_page' => count($ids),
		'no_found_rows'  => true,
		'orderby'        => 'post__in',
	]);

	if (!$query->have_posts()) {
		return [];
	}

	$data = [];
	foreach ($query->posts as $post) {
		$data[$post->ID] = [
			'title' => $post->post_title,
			'id'    => $post->ID,
			'url'   => get_permalink($post),
		];
	}

	return $data;
}

/**
 * Formats a datetime and returns it wrapped in a tag with HTML attributes.
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
	bool $relative = false,
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

		/**
		 * Filters the suffix of a relative date: plura_datetime_suffix_past ("ago") or
		 * plura_datetime_suffix_future ("from now").
		 *
		 * @param string $suffix Translated suffix.
		 */
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
		'class'                 => ['plura-wp-datetime'],
		'data-date-month'       => $datetime->format('F'),
		'data-date-month-short' => $datetime->format('M'),
		'datetime'              => $datetime->format(DateTime::ATOM), // ISO 8601
	];

	// Merge additional classes if provided
	if ($class !== null) {
		$atts['class'] = array_merge(
			$atts['class'],
			is_array($class) ? $class : preg_split('/\s+/', trim($class)),
		);
	}

	return sprintf(
		'<%1$s %2$s>%3$s</%1$s>',
		$tag,
		plura_attributes($atts),
		$display,
	);
}

/**
 * Shortcode [plura-wp-datetime]: renders plura_wp_datetime().
 *
 * With neither date nor id, shows the current post's date on single posts and the current time elsewhere.
 *
 * Attributes:
 * - date:     Date string to format.
 * - id:       Post ID whose date to show; overrides date.
 * - format:   Display format. Default 'l, F jS, Y g:i A'.
 * - source:   Format date is parsed with. Default 'Y-m-d H:i:s'.
 * - relative: Whether to show relative time ("3 days ago") instead. Default false.
 * - tag:      Wrapping tag. Default 'time'.
 * - class:    Extra CSS classes.
 *
 * @param array $args Shortcode attributes.
 *
 * @return string|null Datetime HTML, or null if the date can't be parsed.
 */
add_shortcode('plura-wp-datetime', function ($args) {
	$atts = shortcode_atts([
		'date'     => null,
		'class'    => null,
		'format'   => 'l, F jS, Y g:i A',
		'id'       => null,
		'source'   => 'Y-m-d H:i:s',
		'tag'      => 'time',
		'relative' => false,
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
 * @param string                      $html    The inner HTML to wrap in the link.
 * @param WP_Post|WP_Term|string|null $target  A WP_Post, WP_Term, or external URL. If null/invalid, returns $html.
 * @param array                       $atts    Optional attributes for the <a> tag.
 * @param bool                        $rel     Whether to add rel="noopener noreferrer" if target="_blank".
 * @param string|null                 $title   Optional. Title attribute for the link. Defaults to post/term title.
 * @param string|null                 $context Optional context string passed to the plura_wp_link_atts filter.
 *
 * @return string The generated <a> tag wrapping the HTML, or the original HTML if no valid link target.
 */
function plura_wp_link(
	string $html,
	WP_Post|WP_Term|string|null $target = null,
	array $atts = [],
	bool $rel = false,
	?string $title = null,
	?string $context = null,
): string {
	if (!$target) {
		return $html;
	}

	$link_atts = [
		'class' => ['plura-wp-link'],
	];

	// Determine href and object-specific attributes
	if ($target instanceof WP_Post) {
		$href = get_permalink($target);
		$link_atts = array_merge($link_atts, [
			'title'                          => $title ?? $target->post_title,
			'data-plura-wp-link-target-type' => 'post',
		]);
	} elseif ($target instanceof WP_Term) {
		$href = get_term_link($target);
		$link_atts = array_merge($link_atts, [
			'title'                          => $title ?? $target->name,
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
	$site_parts = parse_url(home_url());
	$link_parts = parse_url($href);

	$site_host = strtolower($site_parts['host'] ?? '');
	$site_path = rtrim($site_parts['path'] ?? '', '/');

	$link_host = strtolower($link_parts['host'] ?? '');
	$link_path = rtrim($link_parts['path'] ?? '', '/');

	// External if: different host, OR same host but path doesn't start with site base path
	$is_external = $link_host !== $site_host
		|| ($site_path !== '' && stripos($link_path, $site_path) !== 0);

	if ($is_external) {
		$link_atts['target'] = '_blank';
	}
	// Merge user-defined attributes first
	if (!empty($atts)) {
		$link_atts = array_merge_recursive($link_atts, $atts);
	}

	// Add rel attribute if needed and final target is '_blank'
	if ($rel && ($link_atts['target'] ?? '') === '_blank') {
		$link_atts['rel'] = 'noopener noreferrer';
	}

	/**
	 * Filters the attributes of the <a> tag.
	 *
	 * @param array                  $link_atts Link attributes, with $atts merged in.
	 * @param WP_Post|WP_Term|string $target    Post, term or URL being linked.
	 * @param string|null            $context   Caller's context.
	 */
	$link_atts = apply_filters('plura_wp_link_atts', $link_atts, $target, $context);

	return sprintf('<a %s>%s</a>', plura_attributes($link_atts), $html);
}

/**
 * Returns the post type the current request is about.
 *
 * Singulars and post type archives give their own type, and term archives the first type their
 * taxonomy is registered for. Date and author archives have no typed queried object, so they read
 * the post_type query var, which wp_get_archives() adds to its links for types other than 'post'.
 * Reads the main query, so it must run at 'wp' or later.
 *
 * @return string|null Post type slug, or null outside singulars and archives, or for an unregistered taxonomy.
 */
function plura_wp_request_post_type(): ?string
{
	$object = get_queried_object();

	if (is_singular()) {
		return get_post_type($object) ?: null;
	}

	if (!is_archive()) {
		return null;
	}

	if ($object instanceof WP_Post_Type) {
		return $object->name;
	}

	if ($object instanceof WP_Term) {
		$taxonomy = get_taxonomy($object->taxonomy);

		return $taxonomy && !empty($taxonomy->object_type) ? $taxonomy->object_type[0] : null;
	}

	$post_type = (array) get_query_var('post_type');

	return reset($post_type) ?: 'post';
}

/**
 * Resolves the current date archive's title and permalink from the query vars.
 *
 * Built from the query vars rather than core's `get_the_date()` approach, which
 * reads the global $post and so only gives the right answer inside the loop.
 *
 * @return array{0: string, 1: string}|null Title text and archive URL, or null if no year is set.
 */
function plura_wp_date_archive_title(): ?array
{
	$year = (int) get_query_var('year');
	$month = (int) get_query_var('monthnum');
	$day = (int) get_query_var('day');

	if (!$year) {
		return null;
	}

	if ($day) {
		return [
			date_i18n(get_option('date_format'), mktime(0, 0, 0, $month, $day, $year)),
			get_day_link($year, $month, $day),
		];
	}

	// Month/year formats stay in the 'default' textdomain on purpose, so they
	// pick up WordPress core's existing translations instead of needing our own.
	if ($month) {
		return [
			date_i18n(_x('F Y', 'monthly archives date format'), mktime(0, 0, 0, $month, 1, $year)),
			get_month_link($year, $month),
		];
	}

	return [
		date_i18n(_x('Y', 'yearly archives date format'), mktime(0, 0, 0, 1, 1, $year)),
		get_year_link($year),
	];
}

/**
 * Returns a title (post, term, or archive) as plain text or wrapped in HTML.
 *
 * With no $object, resolves from the current request: the queried object on
 * singulars and taxonomy/post type/author archives, and the query vars on date
 * archives, which have no queried object. Titles are always bare — no
 * "Category:"/"Archives:" prefix, unlike core's `get_the_archive_title()`.
 *
 * @param WP_Post|WP_Term|WP_Post_Type|WP_User|int|null $object  Optional. Post, term, post type or user object,
 *                                                               or a post ID. Default null (resolve from the request).
 * @param string|false                                  $tag     Optional. HTML tag to wrap the title in. Default 'h3'.
 *                                                               Pass false to return plain text only.
 * @param bool                                          $link    Optional. Whether to wrap the title in a link to the post, term or archive. Default false.
 * @param array|string|null                             $class   Optional. Additional CSS classes to add to the tag. Can be string or array. Default null.
 * @param string|null                                   $context Optional. Filter context for `plura_wp_title`. Default null.
 *
 * @return string|null The rendered title HTML or plain string, or null if nothing resolved.
 */
function plura_wp_title(
	WP_Post|WP_Term|WP_Post_Type|WP_User|int|null $object = null,
	string|false $tag = 'h3',
	bool $link = false,
	array|string|null $class = null,
	?string $context = null,
): ?string {
	// Only an omitted $object falls back to the request — an ID that resolves to
	// nothing must stay a miss, not silently become the current page.
	$from_request = ($object === null);

	if (is_int($object)) {
		$object = get_post($object);
	} elseif ($from_request) {
		$object = get_queried_object();
	}

	$target = null;

	if ($object instanceof WP_Post) {
		$type = 'post';
		$text = $object->post_title;
		$target = $object;
	} elseif ($object instanceof WP_Term) {
		$type = 'term';
		$text = $object->name;
		$target = $object;
	} elseif ($object instanceof WP_Post_Type) {
		$type = 'post-type';
		$text = $object->labels->name;
		$target = get_post_type_archive_link($object->name);
	} elseif ($object instanceof WP_User) {
		$type = 'author';
		$text = $object->display_name;
		$target = get_author_posts_url($object->ID);
	} elseif ($from_request && is_date()) {
		$date = plura_wp_date_archive_title();

		if (!$date) {
			return null;
		}

		$type = 'date';
		[$text, $target] = $date;
	} else {
		return null;
	}

	/**
	 * Filters the title text before it is escaped and wrapped.
	 *
	 * @param string                                    $text    Title text.
	 * @param WP_Post|WP_Term|WP_Post_Type|WP_User|null $object  Resolved object; null on date archives.
	 * @param string|null                               $context Caller's context.
	 * @param string                                    $type    'post', 'term', 'post-type', 'author' or 'date'.
	 */
	$text = apply_filters('plura_wp_title', $text, $object, $context, $type);

	if (empty($text)) {
		return null;
	}

	if ($tag !== false) {
		$classes = ['plura-wp-title', "plura-wp-{$type}-title"];

		if ($class) {
			$classes = array_merge(
				$classes,
				array_filter(
					array_map('trim', is_array($class) ? $class : explode(' ', $class)),
				),
			);
		}

		$html = sprintf(
			'<%1$s %3$s>%2$s</%1$s>',
			tag_escape($tag),
			esc_html($text),
			plura_attributes(['class' => $classes]),
		);
	} else {
		$html = esc_html($text);
	}

	if ($link) {
		$html = plura_wp_link(
			html: $html,
			target: $target,
			title: $text,
			context: $context,
		);
	}

	return $html;
}

/**
 * Shortcode [plura-wp-title]: renders plura_wp_title().
 *
 * Attributes:
 * - object:  Post ID. Omit, or pass anything non-numeric, to resolve the current request.
 * - tag:     Wrapping tag (h2, h3, ...), or "false"/"0" for plain text. Default 'h3'.
 * - link:    Whether to link the title. Default false.
 * - context: Filter context.
 *
 * @param array $atts Shortcode attributes.
 *
 * @return string|null Title HTML, or null if nothing resolved.
 */
function plura_wp_title_shortcode(array $atts): ?string
{
	$atts = shortcode_atts([
		'object'  => null,
		'tag'     => 'h3',
		'link'    => false,
		'context' => null,
	], $atts);

	// An explicit ID, otherwise null so plura_wp_title() resolves the request itself
	$object = is_numeric($atts['object']) ? intval($atts['object']) : null;

	$link = filter_var($atts['link'], FILTER_VALIDATE_BOOLEAN);
	$context = $atts['context'] ?: null;

	$tag = strtolower(trim($atts['tag']));
	$tag = in_array($tag, ['false', '0', ''], true) ? false : $tag;

	return plura_wp_title(
		object: $object,
		tag: $tag,
		link: $link,
		context: $context,
	);
}
add_shortcode('plura-wp-title', 'plura_wp_title_shortcode');
