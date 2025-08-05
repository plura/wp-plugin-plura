<?php

/**
 * Renders a WordPress-compatible component based on a manifest file.
 *
 * This function loads HTML, optionally inlines SVG images, and enqueues associated scripts.
 * It also applies filters to allow overriding the manifest path and data.
 *
 * @param string      $manifest  Path to the manifest JSON file (relative or absolute).
 * @param string      $id        Optional ID to assign to the wrapper element.
 * @param bool        $img2svg   Whether to replace <img> tags with inline SVG sources.
 * @param string|null $context   Optional context string for filters and logic.
 *
 * @return string The rendered HTML output or a comment if errors occur.
 */
function plura_wp_component(string $manifest, string $id = '', bool $img2svg = true, ?string $context = null): string
{
	// If not an absolute path or URL, treat as relative to theme directory
	if (!preg_match('#^([a-z]+:)?//#i', $manifest) && !str_starts_with($manifest, '/')) {
		$manifest = get_stylesheet_directory() . '/' . ltrim($manifest, '/');
	}

	$original_manifest = $manifest;

	// Allow override of manifest path via filter
	$manifest = apply_filters('plura_wp_component_manifest', $manifest, [
		'id'      => $id,
		'img2svg' => $img2svg,
		'context' => $context,
	]);

	if (!file_exists($manifest)) {
		return '<!-- Component manifest file not found -->';
	}

	$manifest_dir = dirname(realpath($manifest));

	// Optionally include a PHP file with logic for this component
	$component_php = $manifest_dir . '/index.php';
	if (file_exists($component_php)) {
		require_once $component_php;
	}

	$data = json_decode(file_get_contents($manifest), true);

	if (json_last_error() !== JSON_ERROR_NONE) {
		return '<!-- Invalid component manifest JSON -->';
	}

	// Prepare args for manifest data filter
	$args = [
		'id'       => $id,
		'img2svg'  => $img2svg,
		'context'  => $context,
		'manifest' => $manifest,
	];

	if ($manifest !== $original_manifest) {
		$args['manifest_original'] = $original_manifest;
	}

	// Allow filtering of manifest data before use
	$data = apply_filters('plura_wp_component_manifest_data', $data, $args);

	$html_file = $data['html'] ?? '';

	if (!$html_file) {
		return '<!-- Component HTML file not specified -->';
	}

	$html_path = $manifest_dir . '/' . ltrim($html_file, '/');

	if (!file_exists($html_path)) {
		return '<!-- Component HTML file not found -->';
	}

	$html = file_get_contents($html_path);

	if ($img2svg) {
		$html = plura_img2svg($html, rtrim($manifest_dir, '/') . '/');
	}

	$html_base_url = content_url(str_replace(WP_CONTENT_DIR, '', $manifest_dir));
	$html = plura_rel2url($html, rtrim($html_base_url, '/') . '/');

	// Enqueue scripts and styles
	if (!empty($data['scripts']) && is_array($data['scripts'])) {
		$scripts = [];

		foreach ($data['scripts'] as $key => $value) {
			if (preg_match('#^https?://#', $key)) {
				$full_path = $key;
			} elseif (str_starts_with($key, '/')) {
				$full_path = $key;
			} else {
				$full_path = $manifest_dir . '/' . ltrim($key, '/');
			}

			$scripts[$full_path] = $value;
		}

		plura_wp_enqueue(scripts: $scripts, prefix: 'plura-wp-component-');
	}

	// Build wrapper
	$attributes = [
		'id'    => $id,
		'class' => 'plura-wp-component',
	];

	// Run do_shortcode to process any shortcodes inside the component HTML
	return sprintf('<div %s>%s</div>', plura_attributes($attributes), do_shortcode($html));
}


/**
 * Shortcode handler for rendering a Plura WP component.
 *
 * Usage:
 * [plura-wp-component manifest="path/to/manifest.json" id="optional-id" img2svg="true" context="optional-context"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
add_shortcode('plura-wp-component', function ($atts) {
	$atts = shortcode_atts([
		'manifest' => '',
		'id'       => '',
		'img2svg'  => true,
		'context'  => null,
	], $atts);

	return plura_wp_component(
		$atts['manifest'],
		$atts['id'],
		filter_var($atts['img2svg'], FILTER_VALIDATE_BOOLEAN),
		$atts['context'] !== null ? (string) $atts['context'] : null
	);
});



add_shortcode('plura-wp-component-banner', function ($atts) {

	return plura_wp_component(
		manifest: __DIR__ . '/../../components/banner/manifest.json',
		context: 'plura-wp-component-banner'
	);
});



/**
 * Replaces <img> elements referencing local SVG files with inline SVG content.
 *
 * @param string  $html      The HTML content containing <img> tags.
 * @param string  $base_path Optional base path to prepend to relative SVG paths.
 * @param bool    $wrap      Whether to wrap the inline SVG in a div with class 'ph-svg-wrapper'.
 * @return string            The modified HTML with inline SVGs.
 */
function plura_img2svg(string $html, string $base_path = '', bool $wrap = false): string
{
	if (empty($base_path)) {
		$base_path = get_stylesheet_directory();
	}

	libxml_use_internal_errors(true);
	$dom = new DOMDocument();
	$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	libxml_clear_errors();

	$imgs = $dom->getElementsByTagName('img');

	// Loop backwards to safely replace nodes
	for ($i = $imgs->length - 1; $i >= 0; $i--) {
		$img = $imgs->item($i);
		$src = $img->getAttribute('src');

		// Skip external URLs
		if (preg_match('#^(https?:)?//#', $src)) {
			continue;
		}

		// Only process .svg files
		if (strtolower(pathinfo($src, PATHINFO_EXTENSION)) !== 'svg') {
			continue;
		}

		// Normalize and prepend base path
		$svg_path = rtrim($base_path, '/') . '/' . ltrim($src, '/');

		if (!file_exists($svg_path)) {
			continue;
		}

		// Load the SVG
		$svg_dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$svg_content = file_get_contents($svg_path);
		$svg_dom->loadXML($svg_content);
		libxml_clear_errors();

		$imported_svg = $dom->importNode($svg_dom->documentElement, true);

		if ($imported_svg) {
			// Transfer ID
			if ($img->hasAttribute('id')) {
				$imported_svg->setAttribute('id', $img->getAttribute('id'));
			}

			// Merge class attributes
			if ($img->hasAttribute('class')) {
				$img_class = trim($img->getAttribute('class'));
				$svg_class = trim($imported_svg->getAttribute('class') ?? '');
				$merged_class = trim($svg_class . ' ' . $img_class);
				$imported_svg->setAttribute('class', $merged_class);
			}

			// Replace <img> with inline <svg>
			if ($wrap) {
				$wrapper = $dom->createElement('div');
				$wrapper->setAttribute('class', 'ph-svg-wrapper');
				$wrapper->appendChild($imported_svg);
				$img->parentNode->replaceChild($wrapper, $img);
			} else {
				$img->parentNode->replaceChild($imported_svg, $img);
			}
		}
	}

	// Clean output: remove <html> and <body> if present
	$body = $dom->getElementsByTagName('body')->item(0);

	if ($body) {
		$output = '';
		foreach ($body->childNodes as $child) {
			$output .= $dom->saveHTML($child);
		}
		return $output;
	}

	return $dom->saveHTML();
}



/**
 * Converts relative paths to absolute URLs in specific HTML tags.
 *
 * @param string $html The input HTML content.
 * @param string $base_url The base URL used to convert relative paths.
 * @return string The HTML with updated absolute paths.
 */
function plura_rel2url(string $html, string $base_url): string
{
	libxml_use_internal_errors(true);
	$dom = new DOMDocument();
	$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	libxml_clear_errors();

	$tag_attr_map = [
		'img'    => ['src', 'srcset'],
		'script' => ['src'],
		'link'   => ['href'],
		'source' => ['src', 'srcset'],
	];

	foreach ($tag_attr_map as $tag => $attrs) {
		$elements = $dom->getElementsByTagName($tag);
		foreach ($elements as $el) {
			foreach ($attrs as $attr) {
				if (!$el->hasAttribute($attr)) continue;

				$original = $el->getAttribute($attr);

				// Skip if absolute URL or data URI
				if (preg_match('#^(https?:)?//#', $original) || str_starts_with($original, 'data:')) {
					continue;
				}

				// Handle srcset specially (comma-separated URLs)
				if ($attr === 'srcset') {
					$srcset_parts = array_map('trim', explode(',', $original));
					$new_srcset = [];

					foreach ($srcset_parts as $part) {
						// Split by space to separate URL and descriptor (e.g., "image.jpg 2x")
						if (preg_match('/^(\S+)(\s+\S+)?$/', $part, $matches)) {
							$url = $matches[1];
							$desc = $matches[2] ?? '';
							$abs = rtrim($base_url, '/') . '/' . ltrim($url, '/');
							$new_srcset[] = $abs . $desc;
						}
					}
					$el->setAttribute('srcset', implode(', ', $new_srcset));
				} else {
					$absolute = rtrim($base_url, '/') . '/' . ltrim($original, '/');
					$el->setAttribute($attr, $absolute);
				}
			}
		}
	}

	return $dom->saveHTML();
}
