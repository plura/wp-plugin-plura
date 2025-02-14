<?php

/**
 *	. API Request
 *	. Query
 * 	. Shortcode
 * 	. Filter
 * 	. Filter Data
 * 		- News Filter Data Item
 * 	. News Grid
 * 
 */



$GLOBALS['PLURA_WP_DYNAMIC_GRID_DEFAULTS'] = [
	'class' => '',
	'filter' => 1,
	'filter-group' => 0,
	'filter-type' => 'tag',
	'grid-item-hover' => 1
];


/* News: API Request */

add_action( 'rest_api_init', function () {
	
	register_rest_route( 'plura/v1', '/posts/', array(
		'methods' => 'GET',
		'callback' => 'plura_wp_dynamic_grid_ids',
  	) );

} );


function plura_wp_dynamic_grid_ids( WP_REST_Request $request = NULL ) {

	$tags = [];

	$tags_cond = 'AND';

	if( $request && $request->get_param('tags') ) {

		$tags = plura_explode(',', $request->get_param('tags') );

	}

	if( $request && $request->get_param('tags_cond') ) {

		$tags_cond = $request->get_param('tags_cond');

	}



	$query = plura_wp_dynamic_grid_query( $tags, $tags_cond );

	if( $query->have_posts() ) {

		$ids = [];

		foreach( $query->posts as $post ) {

			$ids[] = $post->ID;

		}

		return $ids;

	}

	return [];

}




/* News: Query */
function plura_wp_dynamic_grid_query( $tags = [], $tags_cond = 'AND' ) {

	global $sitepress;

	$query_atts = [
		'post_type' => 'cp_news',
		'posts_per_page' => -1
	];

	if( !empty( $tags ) ) {

		$query_atts['tax_query'] = [];

		foreach( $tags as $tag ) {

			$query_atts['tax_query'][] = [
				'taxonomy'  => 'cp_news_tag',
				'field'		=> 'term_id',
				'terms'		=> $tag
			];

		}

		if( count( $tags ) > 1 ) {

			$query_atts['tax_query']['relation']  = $tags_cond;

		}

	}

	return plura_wpml_query( $query_atts );

}




/* News: Shortcode */
add_shortcode('plura-wp-dynamic-grid', 'plura_wp_dynamic_grid');

function plura_wp_dynamic_grid( array $args ) {

	$args = shortcode_atts([
		'class' => '',
		'filter' => 1,
		'filter-group' => 0,
		'filter-type' => 'tag',
	    'grid-item-hover' => 1
	], $args);

	
	$html = [];

	$html[] = plura_wp_dynamic_grid_filter( [ 'group' => $args['filter-group'], 'type' => $args['filter-type'] ]);

	$html[] = plura_wp_dynamic_grid_items( [ 'grid-item-hover' => $args['grid-item-hover'] ] );


	$atts = ['class' => ['plura-wp-dynamic-grid'] ];

	if( !empty( $args['class'] ) ) {

		$atts['class'] = array_merge( $atts['class'], is_array( $args['class'] ) ? $args['class'] : [ $args['class'] ] );

	}

	return "<div " . plura_attributes( $atts ) . ">" . implode('', $html) . "</div>";

}



/* News: Filter */
function plura_wp_dynamic_grid_filter( array $args ) {

	$data = plura_wp_dynamic_grid_filter_data( $args['group'] );

	if( $data ) {

		$html = [];

		foreach( $data as $k => $group ) {

			$html[] = plura_wp_dynamic_grid_filter_obj( $group, $args['type'] );

			/*$classes = ['grid-filter-group'];

			$atts = ['class' => implode(' ', $classes), 'data-group' => $k + 1];

			$select = ["<option></option>"];

			foreach( $group as $term ) {

				$select[] = "<option value=\"" . $term['id'] . "\">" . $term['name'] . "</option>";

			}

			$html[] = "<select " . plura_attributes($atts) . ">" . implode('', $select) . "</select>";*/

		}


		$classes = ['plura-wp-dynamic-grid-filter'];

		$atts = ['class' => $classes];

		return "<div " . plura_attributes( $atts ) . ">" . implode('', $html) . "</div>";

	}

}



function plura_wp_dynamic_grid_filter_obj( $data, $type ) {

	$html = [];

	foreach( $data as $term ) {

		$atts = ['class' => 'plura-wp-dynamic-grid-filter-item'];

		if( $type === 'select' ) {

			$atts['value'] = $term['id'];

			$html[] = "<option " . plura_attributes( $atts ) . ">". $term['name'] . "</option>";

		} else {

			$atts['data-id'] = $term['id'];

			$html[] = "<div " . plura_attributes( $atts ) . ">" . $term['name'] . "</div>";

		}

	}

	$atts = ['class' => 'plura-wp-dynamic-grid-filter-group', 'data-filter-type' => $type];

	$tag = $type === 'select' ? 'select' : 'div';

	return "<$tag " . plura_attributes( $atts ) . ">" . implode('', $html) . "</$tag>";
}




/* News: Filter Data */
function plura_wp_dynamic_grid_filter_data( $group = false ) {

	$query = [
		'taxonomy'   => 'cp_news_tag',
		'hide_empty' => true
	];

	$data = [];

	if( !$group ) {

		$data[] = plura_wp_dynamic_grid_filter_data_items( $query );
 
		/*$terms = plura_wpml_query($query, 'terms');

		foreach( $terms as $term ) {

			$data[] = ['id' => $term->term_id, 'name' => $term->name];

		}*/

	} else {

		$object = acf_get_field( 'field_63dd39b0dfb66' );

		if( $object ) {

			foreach( $object['choices'] as $k => $v ) {

				/*$group = [];

				$data = [];*/

				$query['meta_query'] = [
					[
						'key'       => 'toyno_work_tags_group',
						'value'     => $k,
						'compare'   => '='
					]
				];

				/*$terms = plura_wpml_query($query, 'terms');

				foreach( $terms as $term ) {

					$value = ['id' => $term->term_id, 'name' => $term->name];

					if( !in_array($value, $group) ) {

						$group[] = $value;

					}		

				}

				$data[] = $group;*/

				$data[] = plura_wp_dynamic_grid_filter_data_items( $query );

			}

		}

	}

	return $data;

}


function plura_wp_dynamic_grid_filter_data_items( $query ) {

	$items = [];

	$terms = plura_wpml_query($query, 'terms');

	foreach( $terms as $term ) {

		$item = ['id' => $term->term_id, 'name' => $term->name];

		if( !in_array($item, $items) ) {

			$items[] = $item;

		}

	}

	return $items;

}




/* News: Grid */
function plura_wp_dynamic_grid_items( array $args ) {

	$query = plura_wp_dynamic_grid_query();

	if( $query->have_posts() ) {

		$html = [];

		foreach( $query->posts as $post ) {

			$classes = ['plura-wp-dynamic-grid-item'];

			$entry = [];

			$p = get_post( plura_wpml_id( $post->ID, false ) );

			$img = plura_wp_thumbnail( $post->ID );

			$excerpt = get_the_excerpt( $post );


			if( $img ) {

				$atts_img = ['src' => $img[0], 'width' => $img[1], 'height' => $img[2], 'class' => 'plura-wp-dynamic-grid-item-featured-img' ];

				$entry[] = "<img " . plura_attributes( $atts_img ) . "/>";

				$classes[] = 'has-featured-img';

			}


			$info = ["<h3 class=\"plura-wp-dynamic-grid-item-title\">" . $p->post_title . "</h3>"];


			if( !empty( $excerpt ) ) {

				$info['excerpt'] = "<div class=\"plura-wp-dynamic-grid-item-excerpt\">" . $excerpt . "</div>";

				$classes[] = 'has-excerpt';

			}
			
			/*$meta = toyno_work_meta( ['fields' => ['client', 'year'], 'id' => $post->ID ] );

			if( $meta ) {

				$info[] = $meta;

			}*/


			$atts = [
				'class' => implode(' ', $classes),
				'data-id' => $post->ID,
				'href' => get_permalink( $post ),
				'title' => $p->post_title
			];


			if( has_filter('plura_wp_dynamic_grid_item') ) {

				$entry = apply_filters('plura_wp_dynamic_grid_item', $p, [ 
					'img' => $img,
					'title' => $info['title'],
					'excerpt' => !empty( $excerpt ) ? $info['excerpt'] : false
				] );

			} else {

				$atts_info = ['class' => 'plura-wp-dynamic-grid-item-info'];

				$entry[] = "<div " . plura_attributes( $atts_info ) . ">" . implode('', $info) . "</div>";

			}

			$html[] = "<a " . plura_attributes( $atts ) . ">" . implode('', $entry) . "</a>";

		}

		$classes = ['plura-wp-dynamic-grid-items'];

		if( $args['grid-item-hover'] ) {

			$classes[] = 'has-item-hover';

		}

		$atts = ['class' => $classes];

		return "<div " . plura_attributes( $atts ) . ">" . implode('', $html) . "</div>";

	}

}
