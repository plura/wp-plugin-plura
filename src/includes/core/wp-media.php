<?php

/**
 * Images: attachment data and <img> tags, galleries built from IDs or an ACF field, and post thumbnails.
 */

/**
 * Returns image data array for a given attachment ID or object.
 *
 * For raster images, includes src, width, height, alt, srcset, and sizes.
 * For SVGs, returns only src and alt — width/height are unreliable from WordPress
 * and srcset/sizes are not applicable to vector images.
 *
 * @param int|WP_Post $attachment Image ID or post object.
 * @param string      $size       Image size to retrieve (ignored for SVGs).
 *
 * @return array|null Image data array or null if invalid.
 */
function plura_wp_image_data(int|WP_Post $attachment, string $size = 'large'): ?array
{
	$post = get_post($attachment);

	if (!$post || 'attachment' !== $post->post_type || !wp_attachment_is_image($post->ID)) {
		return null;
	}

	$src_data = wp_get_attachment_image_src($post->ID, $size);

	if (!$src_data) {
		return null;
	}

	$alt = trim(get_post_meta($post->ID, '_wp_attachment_image_alt', true));

	if ($post->post_mime_type === 'image/svg+xml') {
		return [
			'src' => $src_data[0],
			'alt' => $alt,
		];
	}

	return [
		'src'    => $src_data[0],
		'width'  => $src_data[1],
		'height' => $src_data[2],
		'alt'    => $alt,
		'srcset' => wp_get_attachment_image_srcset($post->ID, $size),
		'sizes'  => wp_get_attachment_image_sizes($post->ID, $size),
	];
}

/**
 * Generates an <img> HTML tag for a given image attachment.
 *
 * Uses `plura_wp_image_data()` to retrieve the image info and builds the final HTML <img> tag.
 * Includes srcset, sizes, and loading attributes for responsive rendering.
 * The default class 'plura-wp-image' is always included.
 *
 * @param int|WP_Post  $attachment Attachment ID or WP_Post object. Must be an image.
 * @param string       $size       Image size to retrieve. Defaults to 'large'.
 * @param array        $atts       Optional HTML attributes. 'class' can be a string or an array.
 * @param string|false $loading    Optional loading strategy (e.g., 'lazy', 'eager'). Use false to disable.
 *
 * @return string|null HTML <img> tag or null if image is invalid.
 */
function plura_wp_image(
	int|WP_Post $attachment,
	string $size = 'large',
	array $atts = [],
	string|false $loading = 'lazy',
): ?string {
	$data = plura_wp_image_data($attachment, $size);

	if (!$data) {
		return null;
	}

	// Handle 'class' attribute: string or array
	$atts['class'] = isset($atts['class'])
		? array_filter(array_map('trim', is_array($atts['class']) ? $atts['class'] : explode(' ', $atts['class'])))
		: [];

	// Ensure default class is included
	if (!in_array('plura-wp-image', $atts['class'], true)) {
		array_unshift($atts['class'], 'plura-wp-image');
	}

	// Merge image data into attributes (width/height absent for SVGs)
	$atts = array_merge(array_filter([
		'src'    => $data['src'],
		'width'  => $data['width'] ?? null,
		'height' => $data['height'] ?? null,
		'alt'    => $data['alt'],
	], fn($v) => $v !== null), $atts);

	// Add responsive attributes
	if (!empty($data['srcset'])) {
		$atts['srcset'] = $data['srcset'];
	}
	if (!empty($data['sizes'])) {
		$atts['sizes'] = $data['sizes'];
	}

	// Add loading attribute if not set and $loading is enabled
	if ($loading !== false && !isset($atts['loading'])) {
		$atts['loading'] = $loading;
	}

	return sprintf(
		'<img %s />',
		plura_attributes($atts),
	);
}

/**
 * Shortcode [plura-wp-image]: renders plura_wp_image().
 *
 * Attributes:
 * - attachment: Attachment ID. Required.
 * - size:       Image size. Default 'large'.
 * - class:      CSS classes.
 * - alt:        Alt text override.
 * - loading:    'lazy', 'eager', or 'false' to omit the attribute. Default 'lazy'.
 *
 * @param array $args Shortcode attributes.
 *
 * @return string|null Image HTML, an empty string without an attachment, or null if it isn't an image.
 */
add_shortcode('plura-wp-image', function ($args) {
	$atts = shortcode_atts([
		'attachment' => 0,
		'size'       => 'large',
		'class'      => '',
		'alt'        => '',
		'loading'    => 'lazy',
	], $args);

	$atts['attachment'] = (int) $atts['attachment'];

	if (!$atts['attachment']) {
		return '';
	}

	// Extract known parameters
	$attachment = $atts['attachment'];
	$size = $atts['size'];
	$loading = strtolower(trim($atts['loading']));

	// Normalize loading value
	$loading = match ($loading) {
		'false', '0', '' => false,
		default          => $loading,
	};

	// Remove handled keys from $atts before passing as HTML attributes
	unset($atts['attachment'], $atts['size'], $atts['loading']);

	return plura_wp_image($attachment, $size, $atts, $loading);
});

/**
 * Render a gallery of image attachments from explicit IDs and/or a post’s meta/ACF field.
 *
 * Precedence of sources:
 *  1) Featured image from $source when $source_featured_image is true
 *  2) Explicit attachment IDs from $ids
 *  3) Images returned by the $source_key meta/ACF field on $source
 *
 * @param array<int,int>|null $ids                   Explicit attachment IDs; null or [] to skip.
 * @param int|WP_Post|null    $source                Post ID/object to read from; null to skip.
 * @param string|null         $source_key            Meta/ACF field key on $source; null/'' to skip.
 * @param bool                $source_featured_image Prepend the featured image of $source if available. Default false.
 * @param bool                $unique                Remove duplicate image IDs. Default true.
 * @param string|null         $class                 Additional CSS class(es) as a space-delimited string.
 * @param string|null         $context               Optional context string passed to filters.
 * @param string              $item_class            Additional CSS class(es) for each item, space-delimited
 *                                                   (e.g. 'f-carousel__slide', which Fancybox's stylesheet sizes slides by).
 * @param string              $size                  Image size displayed in each item. Default 'large'.
 *
 * @return string HTML markup of the rendered gallery, or an empty string if no images found.
 */
function plura_wp_gallery(
	?array $ids = null,
	int|WP_Post|null $source = null,
	?string $source_key = null,
	bool $source_featured_image = false,
	bool $unique = true,
	?string $class = null,
	?string $context = null,

	// Display
	string $item_class = '',
	string $size = 'large',
): string {
	$items = [];
	$image_ids = [];

	// Step 1: Get featured image ID from the source post, if requested
	if ($source_featured_image && $source) {
		$items[] = $source instanceof WP_Post ? (int) $source->ID : (int) $source;
	}

	// Step 1.5: Merge explicit IDs (if any)
	if (!empty($ids)) {
		$items = array_merge($items, (array) $ids);
	}

	// Step 2: Resolve source if it's an ACF/meta field on a post
	if ($source_key && $source) {
		$post_id = $source instanceof WP_Post ? (int) $source->ID : (int) $source;
		$field_value = get_field($source_key, $post_id);
		$items = array_merge($items, (array) (is_array($field_value) ? $field_value : []));
	}

	// Step 3: Normalize all source values into valid image attachment IDs
	foreach ($items as $item) {
		if (is_numeric($item)) {
			$id = (int) $item;
		} elseif (is_array($item) && isset($item['ID'])) {
			$id = (int) $item['ID'];
		} elseif ($item instanceof WP_Post) {
			$id = (int) $item->ID;
		} else {
			continue;
		}

		if (wp_attachment_is_image($id)) {
			$image_ids[] = $id;
		} else {
			$thumb_id = get_post_thumbnail_id($id);
			if ($thumb_id && wp_attachment_is_image($thumb_id)) {
				$image_ids[] = (int) $thumb_id;
			}
		}
	}

	// Step 4: Remove duplicates if requested
	if ($unique) {
		$image_ids = array_unique($image_ids);
	}

	/**
	 * Filters the gallery's image IDs before rendering.
	 *
	 * @param int[]            $image_ids  Attachment IDs, in display order.
	 * @param int|WP_Post|null $source     Post the images were read from.
	 * @param string|null      $source_key Meta/ACF field read on $source.
	 * @param string|null      $context    Caller's context.
	 */
	$image_ids = apply_filters('plura_wp_gallery', $image_ids, $source, $source_key, $context);

	// Step 5: Render gallery HTML
	$item_atts = ['class' => ['plura-wp-gallery-item']];

	if (!empty($item_class)) {
		$item_atts['class'] = array_merge($item_atts['class'], plura_explode(' ', $item_class));
	}

	$html = [];
	foreach (array_filter($image_ids) as $id) {
		$thumb = plura_wp_image_data($id, 'medium');
		$html[] = sprintf(
			'<div %s>%s</div>',
			plura_attributes(array_merge($item_atts, ['data-thumb-src' => $thumb['src'] ?? null])),
			plura_wp_image($id, $size),
		);
	}

	if (!empty($html)) {
		$classes_extra = $class
			? array_filter(array_map('trim', explode(' ', trim((string) $class))), 'strlen')
			: [];

		$classes = array_values(array_unique(array_merge(['plura-wp-gallery'], $classes_extra)));

		return sprintf(
			'<div %s>%s</div>',
			plura_attributes(['class' => $classes]),
			implode("\n", $html),
		);
	}

	return '';
}

/**
 * Shortcode [plura-wp-gallery]: renders plura_wp_gallery().
 *
 * Renders nothing unless it has ids, or a source with source_key or source_featured_image.
 *
 * Attributes:
 * - ids:                   Comma-separated attachment IDs.
 * - source:                Post ID to read from. Default the current post on singulars.
 * - source_key:            Meta/ACF field on source that holds the images.
 * - source_featured_image: Whether to prepend the source's featured image. Default false.
 * - unique:                Whether to drop duplicate images. Default true.
 * - class:                 Extra CSS classes for the wrapper.
 * - item_class:            Extra CSS classes for each item.
 * - size:                  Image size shown in each item. Default 'large'.
 * - context:               Filter context.
 *
 * @param array $args Shortcode attributes.
 *
 * @return string Gallery HTML, or an empty string.
 */
add_shortcode('plura-wp-gallery', function ($args) {
	$atts = shortcode_atts([
		'ids'                   => null,
		'source'                => null,
		'source_key'            => '',
		'source_featured_image' => false,
		'unique'                => true,
		'class'                 => '',
		'context'               => null,
		'item_class'            => '',
		'size'                  => 'large',
	], $args, 'plura-wp-gallery');

	// Parse ids (CSV or array)
	$explicit_ids = [];
	if (!empty($atts['ids'])) {
		$explicit_ids = is_array($atts['ids'])
			? array_map('intval', $atts['ids'])
			: wp_parse_id_list($atts['ids']);
	}

	// Resolve source (int|WP_Post|null) with singular fallback
	$source = is_numeric($atts['source']) ? (int) $atts['source'] : (is_singular() ? get_the_ID() : null);

	$add_featured_image = filter_var($atts['source_featured_image'], FILTER_VALIDATE_BOOLEAN);
	$unique = filter_var($atts['unique'], FILTER_VALIDATE_BOOLEAN);

	// Only render when we actually have something to show
	if (!empty($explicit_ids) || ($source && ($add_featured_image || !empty($atts['source_key'])))) {
		return plura_wp_gallery(
			ids: !empty($explicit_ids) ? $explicit_ids : null,
			source: $source,
			source_key: $atts['source_key'] ?: null,
			source_featured_image: $add_featured_image,
			unique: $unique,
			class: ($atts['class'] !== '') ? $atts['class'] : null,
			context: $atts['context'] ?: null,
			item_class: $atts['item_class'],
			size: $atts['size'],
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

	if (!$post instanceof WP_Post) {
		return false;
	}

	if (has_post_thumbnail($post)) {
		return wp_get_attachment_image_src(get_post_thumbnail_id($post), $size);
	}

	return false;
}
