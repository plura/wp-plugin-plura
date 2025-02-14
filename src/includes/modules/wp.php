<?php

/**
 * 		. Fix
 * 		. Utils
 *		 	- Enqueue
 *    	. REST
 *    		- IDs
 *    	. Layout
 *    		- Datetime
 *     		- Nav List
 */


/**
 * Global defaults for WP DateTime settings in the PLURA theme.
 *
 * @global array $PLURA_WP_DATETIME_DEFAULTS {
 *     Default settings for DateTime display.
 *
 *     @type string $date   The date to be displayed. Default is an empty string.
 *     @type string $echo   The format to display the date. Default is 'l, F jS, Y g:i A'.
 *     @type string $format The format in which the date should be parsed. Default is an empty string.
 *     @type string $id     The ID of the DateTime element. Default is an empty string.
 *     @type string $tag    The HTML tag to wrap the DateTime element. Default is 'div'.
 * }
 */
$GLOBALS['PLURA_WP_DATETIME_DEFAULTS'] = [
	'atts' => '',
	'date' => '',
	'format' => __('l, F jS, Y g:i A', 'plura'),
	'source' => 'Y-m-d H:i:s',
	'id' => '',
	'tag' => 'div'
];




/* FIX: CF7 Breaking Spaces */
add_filter( 'wpcf7_autop_or_not', '__return_false' );





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



/* REST: IDs */
add_action( 'rest_api_init', function () {
	
	register_rest_route( 'pwp/v1', '/ids/', array(
		'methods' => 'GET',
		'callback' => 'plura_wp_ids',
  	) );

} );


function plura_wp_ids( WP_REST_Request $request = NULL ) {

	if( $request && $request->get_param('ids') ) {

		$ids = explode(',', $request->get_param('ids') );

		$query = new WP_Query(['post_type' => 'any', 'post__in' => $ids]);

		if( $query->have_posts() ) {

			$data = [];

			foreach( $query->posts as $post ) {

				$data[ $post->ID ] = ['title' => $post->post_title, 'id' => $post->ID, 'url' => get_permalink( $post )];

			}

			return $data;

		}

	}

	return [];

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
add_shortcode('plura-wp-datetime', function( $args ) {

	global $PLURA_WP_DATETIME_DEFAULTS;

	$atts = shortcode_atts( $PLURA_WP_DATETIME_DEFAULTS, $args );

	if( !empty( $atts['id'] ) || is_singular() ) {

		$atts['id'] = !empty( $atts['id'] ) ? $atts['id'] : get_the_ID();

		return plura_wp_datetime( $atts );

	}	
	
});



function plura_wp_datetime( $args = [] ) {

	global $PLURA_WP_DATETIME_DEFAULTS;

	$args = array_merge( $PLURA_WP_DATETIME_DEFAULTS, $args );

	if( $args['id'] ) {

		$date = get_the_date(  $args['source'] , $args['id'] );

	} 

	if( isset( $date ) || !empty( $args['date'] ) ) {

		$datetime = DateTime::createFromFormat( $args['source'] , isset( $date ) ? $date : $args['date'] );

	}

	if( isset( $datetime ) && $datetime ) {

		$a = [
			'class' => ['plura-wp-datetime'],
			'data-date-month' => $datetime->format('F'),
			'data-date-month-short' => $datetime->format('M')
		];

		if( !empty( $args['class'] ) ) {

			$a['class'] = array_merge( $a['class'], is_array( $args['class'] ) ? $args['class'] : plura_explode(',', $args['class'] ) );

		}

		return "<$args[tag] " . plura_attributes( $a ) . ">" . $datetime->format( $args['format'] ) . "</$args[tag]>";

	}

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

	   		return "<div " . p_attributes( $atts ) . ">" . implode('', $html) . "</div>";

	   }


    }

} );
