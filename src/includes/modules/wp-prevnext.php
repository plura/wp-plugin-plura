<?php

/**
 *	- plura_wp_get_nav_by_title 	- get nav by its title
 * 	- plura_wp_prevnext_nav 		- get prev next navigation
 * 	- plura_wp_traverse_nav_block	- traverse nav block for 'current', 'prev' and 'next items'
 * 	- plura_wp_breadcrumbs_nav 			- get nav breadcrumbs
 * 	- plura_wp_breadcrumbs_nav_html		- get nav breadcrumbs html
 */



/**
 * Retrieves navigation menu blocks by menu title
 *
 * @param string $title Title of the navigation menu to retrieve
 * @return array[] Array of parsed block arrays (empty array if no blocks found)
 */
function plura_wp_get_nav_by_title(string $title): array {
    $query = new WP_Query([
        'post_status'    => 'publish',
        'post_type'      => 'wp_navigation',
        'title'          => $title,
        'posts_per_page' => 1,
        'no_found_rows'  => true
    ]);

    if (!$query->have_posts()) {
        return [];
    }

    return parse_blocks($query->posts[0]->post_content);
}



/**
 * Traverses navigation, returning the active object and adjacent non-dummy link items.
 * Returns the active object along with objects immediately before/after it. For previous/next 
 * objects, only items with non-dummy links (#) will be included.
 * 
 * @param array $nav Navigation blocks array from 'core/navigation'
 * @param int|null $id Current post ID (uses get_the_ID() if null)
 * @param array|string|null $keys Desired return keys (defaults to ['prev', 'next', 'current'])
 * @param array|null $ref Reference array for storing traversal results
 * @param array|null $path Current breadcrumb path for recursion
 * @return array Updated reference array with requested navigation items
 */
function plura_wp_traverse_nav_block(
    array $nav,
    ?int $id = null,
    array|string|null $keys = null,
    ?array $ref = null,
    ?array $path = null
): array {
    $_id = $id ?: get_the_ID();
    $_keys = ['prev', 'next', 'current'];
    $_ref = $ref ?? [];

    foreach ($nav as $nav_item) {
        if (!empty($nav_item['blockName']) && preg_match('/core\/navigation-(link|submenu)/', $nav_item['blockName'])) {
            
            // Set nav item path - only when in recursive path building mode
            $_path = $path ?: [];
            if ($path) {
                $nav_item['_path'] = $_path;
            }

            // Check if current item matches ID
            // This identifies if $nav_item is 'current' by comparing IDs
            if (isset($nav_item['attrs']['id']) && $nav_item['attrs']['id'] === $_id) {
                $_ref['current'] = $nav_item;
            }

            // Handle prev/next navigation items:
            // - Only if current doesn't exist or item isn't current
            // - Exclude items with dummy links (#)
            if ((!isset($_ref['current']) || $_ref['current'] !== $nav_item) && ($nav_item['attrs']['url'] ?? '#') !== '#') {
               
                // If current doesn't exist yet, track previous items
                if (!isset($_ref['current'])) {
                    $_ref['prev'] = $nav_item;
                } 
                // Once current exists, track next item (then break)
                elseif (!isset($_ref['next'])) {
                    $_ref['next'] = $nav_item;
                    break;
                }
            }

            // Handle submenu recursion
            if ($nav_item['blockName'] === 'core/navigation-submenu' && !empty($nav_item['innerBlocks'])) {
                $_ref = plura_wp_traverse_nav_block(
                    nav: $nav_item['innerBlocks'],
                    id: $_id,
                    keys: $keys,
                    ref: $_ref,
                    path: array_merge($_path, [$nav_item])
                );
            }
        }
    }

    // Filter results to only include requested keys
    // Removes undesired keys from final return (e.g., only 'prev'/'next' for prevnext)
    if (!$path && $keys) {
        $filter_keys = is_string($keys) ? [$keys] : (array)$keys;
        
        // array_diff returns items from $_keys not present in $filter_keys
        // allowing us to unset the undesired keys
        foreach (array_diff($_keys, $filter_keys) as $key) {
            unset($_ref[$key]);
        }
    }

    return $_ref;
}




/**
 * Generates previous/next navigation HTML
 *
 * @param string $menu String identifying target menu (required)
 * @param string|null $class Optional CSS class(es) for the container
 * @param bool $breadcrumbs Whether to include breadcrumbs (default: true)
 * @param int|null $id Optional ID to target (defaults to current ID via get_the_ID())
 * @return string|null Returns prev/next navigation HTML or null if no navigation found
 */
function plura_wp_prevnext_nav(
    string $menu,
    ?string $class = null,
    bool $breadcrumbs = true,
    ?int $id = null
): ?string {
    $nav = plura_wp_get_nav_by_title($menu);

    if (!$nav) {
        return null;
    }

    $prev_next = plura_wp_traverse_nav_block($nav, $id, ['prev', 'next']);

    if (empty($prev_next)) {
        return null;
    }

    $classes = ['plura-wp-prevnext-nav'];
    if ($class) {
        $classes = array_merge($classes, plura_explode(' ', $class));
    }

    $html = [];
    
    foreach ($prev_next as $k => $nav_item) {
        $classes[] = 'has-' . $k;

        // Build link HTML
        $link_html = sprintf(
            '<a %s>%s</a>',
            plura_attributes([
                'class' => ['plura-wp-prevnext-nav-item-link'],
                'href' => $nav_item['attrs']['url'],
                'title' => $nav_item['attrs']['label']
            ]),
            htmlspecialchars($nav_item['attrs']['label'], ENT_QUOTES)
        );

        $item_html = [
            sprintf(
                '<div %s>%s</div>',
                plura_attributes(['class' => 'plura-wp-prevnext-nav-item-title']),
                $link_html
            )
        ];

        // Add breadcrumbs if available and enabled
        if (isset($nav_item['_path']) && $breadcrumbs) {
            $classes[] = 'has-breadcrumbs';
            $item_html[] = plura_wp_breadcrumbs_nav_html($nav_item['_path']);
        }

        $html[] = sprintf(
            '<div %s>%s</div>',
            plura_attributes([
                'class' => [
                    'plura-wp-prevnext-nav-item',
                    'plura-wp-prevnext-nav-item-' . $k
                ]
            ]),
            implode('', $item_html)
        );
    }

    return sprintf(
        '<div %s>%s</div>',
        plura_attributes(['class' => $classes]),
        implode('', $html)
    );
}

add_shortcode('plura-wp-prevnext-nav', function(array $args): ?string {
    $atts = shortcode_atts([
        'menu' => '',
        'class' => '',
        'breadcrumbs' => 'true'
    ], $args, 'plura-wp-prevnext-nav');

    // Convert string boolean to real boolean
    $atts['breadcrumbs'] = filter_var($atts['breadcrumbs'], FILTER_VALIDATE_BOOLEAN);

    if (empty($atts['menu'])) {
        return null;
    }

    return plura_wp_prevnext_nav(...$atts);
});




/**
 * Generates breadcrumbs navigation for WordPress
 *
 * @param string $menu The menu title to use for breadcrumbs
 * @param string|null $class CSS class for the breadcrumbs container
 * @param int|null $id HTML ID for the breadcrumbs container
 * @return string|null The breadcrumbs HTML or null if no valid path found
 */
function plura_wp_breadcrumbs_nav(
    string $menu,
    ?string $class = null,
    ?int $id = null
): ?string {
    $nav = plura_wp_get_nav_by_title($menu);

    if ($nav) {
        $items = plura_wp_traverse_nav_block($nav, $id, ['current']);

        if (!empty($items) && isset($items['current']['_path'])) {
            return plura_wp_breadcrumbs_nav_html(
                nav_item_path: $items['current']['_path'],
                class: $class,
                id: $id
            );
        }
    }

    return null;
}

add_shortcode('plura-wp-breadcrumbs-nav', function(array $args): ?string {
    $atts = shortcode_atts([
        'menu' => '',
        'class' => '',
        'id' => '0'
    ], $args, 'plura-wp-breadcrumbs-nav');

    // Convert ID to integer (0 becomes default in main function)
    $atts['id'] = (int)$atts['id'];

    if (empty($atts['menu'])) {
        return null;
    }

    return plura_wp_breadcrumbs_nav(...$atts);
});




/**
 * Generates HTML for breadcrumbs navigation
 *
 * @param array $nav_item_path The navigation item path array
 * @param string|null $class Additional CSS class(es) for the breadcrumbs container
 * @param int|null $id HTML ID for the breadcrumbs container
 * @return string The generated breadcrumbs HTML
 */
function plura_wp_breadcrumbs_nav_html(
    array $nav_item_path,
    ?string $class = null,
    ?int $id = null
): string {
    $crumbs_html = [];

    foreach ($nav_item_path as $crumb) {
        $label = htmlspecialchars($crumb['attrs']['label'], ENT_QUOTES);
        $url = htmlspecialchars($crumb['attrs']['url'], ENT_QUOTES);

        $label = ($url !== '#')
            ? sprintf(
                '<a %s>%s</a>',
                plura_attributes([
                    'class' => 'plura-wp-breadcrumb-link',
                    'href' => $url,
                    'title' => $label
                ]),
                $label
              )
            : $label;

        $crumbs_html[] = sprintf(
            '<li %s>%s</li>',
            plura_attributes(['class' => 'plura-wp-breadcrumb']),
            $label
        );
    }

    $atts = ['class' => ['plura-wp-breadcrumbs']];
    
    if ($id) {
        $atts['id'] = $id;
    }

    if ($class) {
        $atts['class'] = array_merge(
            $atts['class'],
            is_array($class) ? $class : plura_explode(' ', $class)
        );
    }

    return sprintf(
        '<div %s><ul class="plura-wp-breadcrumbs-group">%s</ul></div>',
        plura_attributes($atts),
        implode('', $crumbs_html)
    );
}
