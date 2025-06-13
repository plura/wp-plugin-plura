<?php

/**
 * Get all the breadcrumbs for a post, page, or term.
 *
 * @param ?int  $object Optional object ID (post or term). If null, uses the current queried object.
 * @param bool  $self   Whether to include the object itself in the breadcrumbs.
 * @param ?int  $id     Optional ID for the wrapper element (used in HTML output).
 * @param bool  $html   Whether to return the output as HTML (true) or as an array (false).
 * @return array|string Breadcrumb data array or HTML string depending on $html.
 */
function plura_wp_breadcrumbs( ?int $object = null, bool $self = false, ?int $id = null, bool $html = true ) {

	$crumbs = [];

	if ( is_archive() && !$object ) {

		$crumb = plura_wp_breadcrumbs_terms(
			get_queried_object()->term_id,
			get_queried_object()->taxonomy,
			$self
		);

		if ( $crumb ) {
			$crumbs[] = $crumb;
		}

	} else {

		if ( $object && is_int( $object ) ) {
			$object = get_post( $object );
		} elseif ( !$object ) {
			$object = get_post();
		}

		if ( ( is_single() && !$object ) || $object->post_type !== 'page' ) {

			$post_taxonomies = get_object_taxonomies( $object );

			if ( !empty( $post_taxonomies ) ) {
				$terms = get_the_terms( $object, $post_taxonomies[0] );

				if ( !empty( $terms ) ) {
					foreach ( $terms as $term ) {
						$crumbs[] = plura_wp_breadcrumbs_terms( $term->term_id, $term->taxonomy, true );
					}
				}
			}

		} elseif ( ( is_page() && !$object ) || $object->post_type === 'page' ) {

			$ancestors = get_ancestors( $object->ID, get_post_type(), 'post_type' );

			if ( !empty( $ancestors ) ) {
				$group = [];

				foreach ( $ancestors as $ancestor ) {
					$group[] = plura_wp_breadcrumb( $ancestor );
				}

				$crumbs[] = $group;
			}
		}
	}

	if ( has_filter( 'plura_wp_breadcrumbs' ) ) {
		$crumbs = apply_filters( 'plura_wp_breadcrumbs', $crumbs, $id );
	}

	if ( !empty( $crumbs ) ) {
		if ( $html ) {
			$return = [];

			if ( !is_array( $crumbs[0] ) || !array_key_exists( 0, $crumbs[0] ) ) {
				$crumbs = [ $crumbs ];
			}

			foreach ( $crumbs as $group ) {
				$g = [];

				foreach ( $group as $crumb ) {
					$classes = [ 'plura-wp-breadcrumb' ];

					if ( !is_array( $crumb ) ) {
						$c = $crumb;
					} else {
						$c = plura_wp_link( html: $crumb['name'], obj: $crumb['obj'], atts: ['class' => 'plura-wp-breadcrumb-link']);
					}

					$atts = [ 'class' => implode( ' ', $classes ) ];
					$g[]  = '<li ' . plura_attributes( $atts ) . '>' . $c . '</li>';
				}

				$return[] = '<ul class="plura-wp-breadcrumbs-group">' . implode( '', $g ) . '</ul>';
			}

			$atts = [ 'class' => 'plura-wp-breadcrumbs' ];

			if ( $id ) {
				$atts['data-id'] = $id;
			}

			return '<div ' . plura_attributes( $atts ) . '>' . implode( '', $return ) . '</div>';
		}

		return $crumbs;
	}
}



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

	$ancestors = get_ancestors( $term_id, $taxonomy );

	if ( !empty( $ancestors ) ) {
		foreach ( array_reverse( $ancestors ) as $ancestor ) {
			$crumbs[] = plura_wp_breadcrumb( $ancestor, $taxonomy );
		}
	}

	if ( $include ) {
		$crumbs[] = plura_wp_breadcrumb( $term_id, $taxonomy );
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
function plura_wp_breadcrumb( int|string $id, string|false $taxonomy = false ): array {

	if ( $taxonomy ) {
		$term = get_term( $id, $taxonomy );

		return [
			'type' => 'term',
			'link' => get_term_link( $term ),
			'name' => $term->name,
			'id'   => $id,
			'obj'  => $term
		];
	}

	if ( ! is_int( $id ) ) {
		return [
			'type' => 'single',
			'name' => $id,
		];
	}

	return [
		'type' => 'single',
		'link' => get_permalink( $id ),
		'name' => get_the_title( $id ),
		'id'   => $id,
		'obj'  => get_post( $id )
	];
}


function plura_wp_breadcrumbs_shortcode( $args ) {

	$atts = shortcode_atts([
		'id' => 0,
		'object' => 0,
		'self' => 0
	], $args);

	return plura_wp_breadcrumbs( $atts['object'], $atts['self'], $atts['id'] );

}

add_shortcode('plura-wp-breadcrumbs', 'plura_wp_breadcrumbs_shortcode');



function plura_p_tags( $post, $html = true ) {

	if( is_int( $post ) ) {

		$post = get_post( $post );

	}

	$post_taxonomies = get_object_taxonomies( $post );

	if( !empty( $post_taxonomies ) ) {

		$tags = [];

		foreach( $post_taxonomies as $taxonomy ) {

			$terms = get_the_terms( $post, $taxonomy );

			foreach( $terms as $term ) {

				if( $html ) {

					$atts = ['class' => 'plura-p-tag'];

					$atts_link = ['title' => $term->name, 'href' => get_term_link( $term )];

					$tags[] = "<li " . plura_attributes( $atts ) . "><a " . plura_attributes( $atts_link ) . ">" . $term->name . "</a></li>";

				} else {

					$tags[] = $term;

				}

			}

			if( $html ) {

				$atts = ['class' => 'plura-p-tags', 'data-taxonomy' => $post_taxonomies[0]];

				return "<ul " . plura_attributes( $atts ) . ">" . implode('', $tags) . "</ul>";

			}

		}

		return $tags;

	}

}

function plura_p_tags_shortcode( $args ) {

	$atts = shortcode_atts(['post' => ''], $args);

	$id = empty( $atts['post'] ) ? get_the_ID() : $atts['id'];

	return plura_p_tags( $atts['post'] );

}

add_shortcode('plura-p-tags', 'plura_p_tags_shortcode');


function plura_wp_title( bool $html = true ) {

	if( is_page() || is_single() ) {

		$text = get_the_title();

	} else if( is_archive() ) {

		//https://www.binarymoon.co.uk/2017/02/hide-archive-title-prefix-wordpress/
		$title_parts = explode( ': ', get_the_archive_title(), 2 );

		$text = $title_parts[1];

	}

	if( empty($text) ) {

		$text = 'no title';

	}

	if( has_filter('plura_wp_title') ) {

		$text = apply_filters('plura_wp_title', $text);

	}

	if( $html ) {

		return "<div class=\"p-title\">" . $text . "</div>";

	}

	return $text;

}

add_shortcode('plura-wp-title', function( $atts ) {

	$args = shortcode_atts([
		'html' => 1
	], $atts);

	return plura_wp_title( ...$args );

});



function plura_p_posts_remote( $args ) {

	$url = $args['source'];

	unset( $args['source'] );

	$response = wp_remote_get( $url . '?' . http_build_query( $args ) );

	if( is_wp_error( $response ) ) {

		return __('Loading Failed...');

	}

	return json_decode( $response['body'] );
	
}





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
