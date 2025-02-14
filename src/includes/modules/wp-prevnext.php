<?php

/**
 *	- plura_wp_get_nav_by_title 	- get nav by its title
 * 	- plura_wp_prevnext_nav 		- get prev next navigation
 * 	- plura_wp_traverse_nav_block	- traverse nav block for 'current', 'prev' and 'next items'
 * 	- plura_wp_breadcrumbs_nav 			- get nav breadcrumbs
 * 	- plura_wp_breadcrumbs_nav_html		- get nav breadcrumbs html
 */

/*$PLURA_WP_BREADCRUMBS_NAV_DEFAULTS = [
	'class' => '',
	'id' => '',	
    'menu' => ''
];

$PLURA_WP_PREVNEXT_DEFAULTS = [
	'class' => '',
    'breadcrumbs' => 1,
    'id' => '',
    'menu' => ''
];*/

$GLOBALS['PLURA_WP_BREADCRUMBS_NAV_DEFAULTS'] = [
	'class' => '',
	'id' => '',	
	'menu' => ''
];

$GLOBALS['PLURA_WP_PREVNEXT_DEFAULTS'] = [
	'class' => '',
	'breadcrumbs' => 1,
	'id' => '',
	'menu' => ''
];


add_shortcode('plura-wp-prevnext-nav', function( $args ) {

    global $PLURA_WP_PREVNEXT_DEFAULTS;

    $atts = shortcode_atts( $PLURA_WP_PREVNEXT_DEFAULTS, $args );

    if( !empty( $atts['menu'] ) ) {

        return plura_wp_prevnext_nav( $atts );

    }

} );



function plura_wp_get_nav_by_title( $title ) {

    $params = [
        'post_status' => 'publish',
        'post_type' => 'wp_navigation',
        'title' => $title
    ];

    $query = new WP_Query( $params );

    if( $query->have_posts() ) {

        return parse_blocks( $query->posts[0]->post_content );

    }

    return false;

}


/**
 * 
 * @param  array $args {
 *                    
 *      parameters array                 
 *                     
 *		@type string  $class		returned html optional class
 *		@type int     $id 			optional id to be targeted. defaults to current ID via get_the_ID()
 *		@type string  $menu 		string identifying target menu
 * 
 * }
 * @return string returns prev/next navigation
 */
function plura_wp_prevnext_nav( $args ) {

    $nav = plura_wp_get_nav_by_title( $args['menu'] );

    if( $nav ) {

        $prev_next = plura_wp_traverse_nav_block( $nav, $args['id'], ['prev', 'next'] );

        if( !empty( $prev_next ) ) {

        	$classes = array_merge( ['plura-wp-prevnext-nav'], !isset( $args['class'] ) ? [] : p_explode(' ', $args['class'] ) );

        	$html = [];

        	foreach( $prev_next as $k => $nav_item ) {


        		$classes[] = 'has-' . $k;

        		$nav_item_title_atts = ['class' => 'plura-wp-prevnext-nav-item-title'];

        		$nav_item_title_link_atts = [
        			'class' => ['plura-wp-prevnext-nav-item-link'],
        			'href' => $nav_item['attrs']['url'],
        			'title' => $nav_item['attrs']['label']
        		];

        		$item_html = "<a " . plura_attributes( $nav_item_title_link_atts ) . ">" . $nav_item['attrs']['label'] . "</a>";

        		$item_html = ["<div " . plura_attributes( $nav_item_title_atts ) . ">" . $item_html . "</div>"];


        		//add breadcrumbs
        		if( isset( $nav_item['_path'] ) && plura_bool( $args['breadcrumbs'], true ) ) {

        			$classes[] = 'has-breadcrumbs';

        			$item_html[] = plura_wp_breadcrumbs_nav_html( $nav_item['_path'] );
 
        		}

        		$nav_item_atts = ['class' => ['plura-wp-prevnext-nav-item', 'plura-wp-prevnext-nav-item-' . $k] ];

        		$html[] = "<div " . plura_attributes( $nav_item_atts ) . ">" .  implode('', $item_html) . "</div>";
        	
        	}

        	$atts = ['class' => $classes];

        	return "<div " . plura_attributes( $atts ) . ">" . implode('', $html) . "</div>";

        }

    }

}



/**
 * traverses navigation, returning the active object and the objects immediately before
 * and after it. For the previous/next object, only those with non-dummy links (#) will
 * be accepted.
 * @param  array $nav  		'core/navigation' parsed blocks
 * @param  int $id   		current id, if none is given it will get_the_ID() instead
 * @param  array $keys  	desired return keys
 * @param  array $ref  		reference object for 'current', 'prev' and 'next' nav items
 * @param  array $path 		nav item 'breadcrumbs' path
 * @return array       		returns updated reference object
 */
function plura_wp_traverse_nav_block( $nav, $id = null, $keys = null, $ref = null, $path = null ) {

	$_id = $id ? $id : get_the_ID();

	$_keys = ['prev', 'next', 'current'];

    $_ref = $ref ? $ref : [];

    foreach( $nav as $nav_item ) {

        if( !empty( $nav_item['blockName'] ) && preg_match('/core\/navigation-(link|submenu)/' , $nav_item['blockName']) ) {


			//set $nav_item path
			$_path = $path ? $path : [];

			if( $path ) {

				$nav_item['_path'] = $_path;

			}


			//check if $nav_item is 'current'.
			if( /*is_singular() &&*/ array_key_exists('id', $nav_item['attrs'] ) && $nav_item['attrs']['id'] === $_id ) {

				$_ref['current'] = $nav_item;

			}


			//if 'current' does not exist or $nav_item is not 'current' save prev/next. Exclude nav items with 'dummy' links for prev/next
			if( ( !array_key_exists('current', $_ref) || $_ref['current'] !== $nav_item ) &&  $nav_item['attrs']['url'] !== '#' ) {

				//if current does not exist add a prev in each loop, until current is found
				if( !array_key_exists('current', $_ref) ) {

					$_ref['prev'] = $nav_item;

				//if current already exists add a next
				} elseif( !array_key_exists('next', $_ref) ) {

					$_ref['next'] = $nav_item;

					break;

				}

			}


			//traverse submenu
			if( $nav_item['blockName'] === 'core/navigation-submenu' ) {

				if( !empty( $nav_item['innerBlocks'] ) ) {

					$_ref = plura_wp_traverse_nav_block( $nav_item['innerBlocks'], $_id, $keys, $_ref, array_merge( $_path, [ $nav_item ] ) );

				}

			}

        }

    }

    //remove undesired keys from final 'return' (ie. in prevnext only 'prev'/'next' are required)
    if( !$path && ( is_array( $keys ) || is_string( $keys ) ) ) {

    	if( is_string( $keys ) ) {

    		$keys = [ $keys ];

    	}

    	//array diff returns the items not found of the first array in common with the second array
    	//allowing to unset the 'undesired' key
    	foreach( array_diff($_keys, $keys) as $key ) {

    		unset( $_ref[ $key ] );

    	}  	

    }

    return $_ref;

}



function plura_wp_breadcrumbs_nav_html( $nav_item_path, $args = null ) {

	$crumbs_html = [];

	foreach( $nav_item_path as $crumb ) {

		$crumb_item_html = $crumb['attrs']['label'];

		if( $crumb['attrs']['url'] !== '#' ) {

			$crumb_item_link_atts = ['class' => 'plura-wp-breadcrumb-link', 'href' => $crumb['attrs']['url'], 'title' => $crumb['attrs']['label']];

			$crumb_item_html = "<a " . plura_attributes( $crumb_item_link_atts ) . ">" . $crumb_item_html . "</a>";

		}

		$crumb_item_atts = ['class' => 'plura-wp-breadcrumb'];

		$crumbs_html[] = "<li " . plura_attributes( $crumb_item_atts ) . ">" . $crumb_item_html . "</li>";

	}

	$atts = ['class' => ['plura-wp-breadcrumbs']];

	if( !empty( $args['class'] ) ) {

		$atts['class'] = array_merge( $atts['class'], is_array( $args['class'] ) ? $args['class'] : p_explode(' ', $args['class']) );

	}

	return "<div " . plura_attributes( $atts ) . "><ul class=\"plura-wp-breadcrumbs-group\">" . implode('', $crumbs_html) . "</ul></div>";

}



add_shortcode('plura-wp-breadcrumbs-nav', function( $args ) {

    global $PLURA_WP_BREADCRUMBS_NAV_DEFAULTS;

    $atts = shortcode_atts( $PLURA_WP_BREADCRUMBS_NAV_DEFAULTS, $args );

    if( !empty( $atts['menu'] ) ) {

    	if( empty( $atts['id'] ) ) {

    		$atts['id'] = get_the_ID();

    	} else {

			//intval is used b/c ID var might be a string (ie. when using shortcodes)
    		$atts['id'] = intval( $atts['id'] );

    	}

        return plura_wp_breadcrumbs_nav( $atts );

    }

} );



function plura_wp_breadcrumbs_nav( $args ) {

	$nav = plura_wp_get_nav_by_title( $args['menu'] );

	if( $nav ) {

		$id = !empty( $args['id'] ) ? $args['id'] :  null;

	    $items = plura_wp_traverse_nav_block( $nav, $id, ['current'] );

	    if( !empty( $items ) && isset( $items['current']['_path'] ) ) {

	    	return plura_wp_breadcrumbs_nav_html( $items['current']['_path'], $args );

	    }

	}

}

?>
