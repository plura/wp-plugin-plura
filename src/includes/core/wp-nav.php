<?php

/**
 *	- plura_wp_get_nav_by_title 	- get nav by its title
 * 	- plura_wp_prevnext_nav 		- get prev next navigation
 * 	- plura_wp_traverse_nav_block	- traverse nav block for 'current', 'prev' and 'next items'
 * 	- plura_wp_breadcrumbs_nav 			- get nav breadcrumbs
 * 	- plura_wp_breadcrumbs_nav_html		- get nav breadcrumbs html
 * 	- plura_wp_breadcrumbs				- get post/page/term breadcrumbs from its hierarchy
 * 	- P_Walker_Nav_Menu_Dropdown		- menu walker rendering <option> items
 * 	- [plura-wp-nav-list]				- menu as a list and/or <select>
 * 	- plura_p_date_archive				- yearly archive links for the current post type
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




/**
 * Generate breadcrumbs for a post, page, or term object.
 *
 * If no object is given, the function falls back to the current queried object.
 * Supports posts, pages, and terms. If rendering HTML, adds classes to structure each breadcrumb group.
 *
 * @param WP_Post|WP_Term|int|null $object   Optional. Object to build breadcrumbs for (post, page, or term). Defaults to current queried object.
 * @param bool                     $self     Optional. Whether to include the object itself as the final breadcrumb. Default false.
 * @param string|null              $class    Optional. Additional class to add to the container <div>.
 * @param bool                     $html     Optional. Whether to return rendered HTML. If false, returns array structure. Default true.
 * @param string|null              $context  Optional. Context identifier, passed to the 'plura_wp_breadcrumbs' filter.
 *
 * @return string|array                      Rendered HTML string or array of breadcrumb groups depending on $html.
 */
function plura_wp_breadcrumbs( WP_Post|WP_Term|int|null $object = null, bool $self = false, ?string $class = null, bool $html = true, ?string $context = null ) {
	$crumbs = [];

	// Normalize $object
	if ( is_null( $object ) ) {
		$object = get_queried_object();

	} elseif ( is_int( $object ) ) {
		$_post = get_post( $object );
		if ( $_post ) {
			$object = $_post;
		} else {
			$object = null; // No term guessing
		}

	} elseif ( ! ( $object instanceof WP_Post || $object instanceof WP_Term ) ) {
		$object = null;
	}

	// Handle term
	if ( $object instanceof WP_Term ) {
		$crumb = plura_wp_breadcrumbs_terms(
			$object->term_id,
			$object->taxonomy,
			$self
		);

		if ( $crumb ) {
			$crumbs[] = $crumb;
		}

	// Handle post or page
	} elseif ( $object instanceof WP_Post ) {
		$post_type = $object->post_type;

		if ( $post_type !== 'page' ) {
			$taxonomies = get_object_taxonomies( $post_type );

			if ( !empty( $taxonomies ) ) {
				$terms = get_the_terms( $object, $taxonomies[0] );

				if ( !empty( $terms ) && !is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$crumbs[] = plura_wp_breadcrumbs_terms( $term->term_id, $term->taxonomy, true );
					}
				}
			}

		} else {
			$ancestors = get_ancestors( $object->ID, $post_type, 'post_type' );

			if ( !empty( $ancestors ) ) {
				$group = [];

				foreach ( $ancestors as $ancestor ) {
					$group[] = plura_wp_breadcrumb( $ancestor );
				}

				$crumbs[] = $group;
			}
		}
	}

	// Ensure array-of-groups structure BEFORE applying filters
/* 	if ( !is_array( $crumbs[0] ) || !array_key_exists( 0, $crumbs[0] ) ) {
		$crumbs = [ $crumbs ];
	} */

	// Allow filtering
	$crumbs = apply_filters( 'plura_wp_breadcrumbs', $crumbs, $object, $context );

	// Render
 	if ( !empty( $crumbs ) ) {
		if ( $html ) {
			$return = [];

			foreach ( $crumbs as $group ) {
				$g = [];

				foreach ( $group as $i => $crumb ) {
					$classes = [ 'plura-wp-breadcrumb' ];

					if ( $i === array_key_last( $group ) ) {
						$classes[] = 'is-current';
					}

					if ( !is_array( $crumb ) ) {
						$c = plura_wp_breadcrumb( $crumb );
						if( !$c ) {
							continue;
						}
					}

					$c = plura_wp_link(
						html: $crumb['name'],
						target: $crumb['obj'],
						atts: [ 'class' => 'plura-wp-breadcrumb-link' ]
					);

					$g[] = sprintf( '<li %s>%s</li>', plura_attributes( [ 'class' => $classes ] ), $c );
				}

				$return[] = sprintf( '<ul %s>%s</ul>', plura_attributes( [ 'class' => 'plura-wp-breadcrumbs-group' ] ), implode( '', $g ) );
			}

			$atts = [ 'class' => 'plura-wp-breadcrumbs' . ( $class ? " {$class}" : '' ) ];

			return '<div ' . plura_attributes( $atts ) . '>' . implode( '', $return ) . '</div>';
		}

		return $crumbs;
	} 

	return '';
}

function plura_wp_breadcrumbs_shortcode( $args ) {
	$atts = shortcode_atts([
		'object'  => 0,
		'self'    => 0,
		'class'   => '',
		'context' => ''
	], $args );

	// Normalize
	$object  = is_numeric( $atts['object'] ) && (int) $atts['object'] > 0 ? (int) $atts['object'] : null;
	$self    = (bool) $atts['self'];
	$class   = trim( $atts['class'] ) ?: null;
	$context = trim( $atts['context'] ) ?: null;

	return plura_wp_breadcrumbs(
		object: $object,
		self: $self,
		class: $class,
		html: true,
		context: $context
	);
}

add_shortcode( 'plura-wp-breadcrumbs', 'plura_wp_breadcrumbs_shortcode' );



/**
 * Generates an array of breadcrumb items for a term and its ancestors.
 *
 * @param int     $term_id  The ID of the term.
 * @param string  $taxonomy The taxonomy the term belongs to.
 * @param bool    $include  Whether to include the term itself in the breadcrumbs.
 * @return array            An array of breadcrumb items (as returned by plura_wp_breadcrumb).
 */
function plura_wp_breadcrumbs_terms( int $term_id, string $taxonomy, bool $include = false ): array {
	$crumbs = [];

	$ancestors = get_ancestors( $term_id, $taxonomy, 'taxonomy' );

	if ( !empty( $ancestors ) ) {
		foreach ( array_reverse( $ancestors ) as $ancestor ) {
			$crumbs[] = plura_wp_breadcrumb( get_term( $ancestor, $taxonomy ) );
		}
	}

	if ( $include ) {
		$crumbs[] = plura_wp_breadcrumb( get_term( $term_id, $taxonomy ) );
	}

	return $crumbs;
}


/**
 * Generates a breadcrumb item for a term or a post/page.
 *
 * @param int|string $id        The post ID, term ID, or a plain string (used as label without link).
 * @param string|false $taxonomy Optional. The taxonomy name if the ID refers to a term. Default false.
 * @return array                An associative array with breadcrumb data.
 */
function plura_wp_breadcrumb( WP_Post|WP_Term|int|string $object ): ?array {
	if ( is_int( $object ) ) {
		$object = get_post( $object );

		if ( ! $object ) {
			return null;
		}
	}

	if ( $object instanceof WP_Term ) {
		return [
			'type' => 'term',
			'link' => get_term_link( $object ),
			'name' => $object->name,
			'id'   => $object->term_id,
			'obj'  => $object
		];
	} else if ( $object instanceof WP_Post ) {
		return [
			'type' => 'post',
			'link' => get_permalink( $object ),
			'name' => get_the_title( $object ),
			'id'   => $object->ID,
			'obj'  => $object
		];
	} else if ( is_string( $object ) ) {
		return [
			'type' => 'string',
			'name' => $object
		];
	}

	return null;
}




//https://wordpress.stackexchange.com/a/27498
//https://www.billerickson.net/code/wordpress-menu-as-select-dropdown/


class P_Walker_Nav_Menu_Dropdown extends Walker_Nav_Menu {

    // don't output children opening tag (`<ul>`)
    public function start_lvl(&$output, $depth = 0, $args = NULL){}

	// don't output children closing tag    
	public function end_lvl(&$output, $depth = 0, $args = NULL){}

	public function start_el(&$output, $data_object, $depth = 0, $args = NULL, $current_object_id = 0){

		// add spacing to the title based on the current depth
		$data_object->title = str_repeat("&nbsp;", $depth * 4) . $data_object->title;

		// call the prototype and replace the <li> tag
		// from the generated markup... 
		parent::start_el($output, $data_object, $depth, $args);


		$val = "<option value=\"" . $data_object->object_id . "\"";


		if( $data_object->current ) {

			$output = str_replace('<li', $val . ' selected', $output);

		} else {

			$output = str_replace('<li', $val, $output);

		}      

    }

    // replace closing </li> with the closing option tag
    public function end_el(&$output, $data_object, $depth = 0, $args = NULL) {

		$output .= "</option>\n";
    
    }

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




function plura_p_date_archive() {

	if( is_archive() ) {

		if( is_post_type_archive() ) {

			$post_type = get_queried_object()->name;

			$atts = [
				'data-archive-request-obj' => 'is-archive',
				'data-archive-post-type' => $post_type
			];

		} else if( isset( get_queried_object()->term_id ) ) {

			$post_type = get_taxonomy( get_queried_object()->taxonomy )->object_type[0];

			$term_id = get_queried_object()->term_id;
			
			$atts = [
				'data-archive-request-obj' => 'term',
				'data-archive-post-type' => $post_type
			];

		}		

	} else if( is_singular() ) {

		$post_type = get_post_type();

		$atts = [
			'data-archive-request-obj' => 'single',
			'data-archive-post-type' => $post_type
		];

	}

	if( !empty( $post_type ) ) {


		$atts['class'] = 'plura-p-date-archive';

		return "<ul " . plura_attributes( $atts ) . ">" . wp_get_archives( array('echo' => 0, 'type' => 'yearly', 'post_type' => $post_type ) ) . "</ul>";

	}

}

add_shortcode('plura-p-date-archive', 'plura_p_date_archive');
