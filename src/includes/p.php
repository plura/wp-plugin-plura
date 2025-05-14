<?php

/**
 * Converts an array of attributes to HTML string
 * 
 * @param array<string, mixed> $atts Array of HTML attributes (name => value pairs)
 * @param bool $prefix Whether to prefix attributes with "data-"
 * @return string HTML attributes as a string
 */
function plura_attributes(array $atts, bool $prefix = false): string {
    $a = [];
    
    foreach ($atts as $k => $v) {
        // Handle boolean attributes
        if ($v === true) {
            $a[] = $k; // Just output the attribute name
            continue;
        }
        
        // Handle class arrays
        if ($k === 'class' && is_array($v)) {
            $v = implode(' ', $v);
        }
        
        // Normal key-value pairs
        $value = $k . '="' . htmlspecialchars((string)$v, ENT_QUOTES) . '"';
        
        if ($prefix) {
            $value = "data-" . $value;
        }
        
        $a[] = $value;
    }
    
    return implode(' ', $a);
}



function plura_wp_thumbnail( $postID, $size = 'large' ) {

	$img = has_post_thumbnail( $postID );

	if( $img ) {

		return wp_get_attachment_image_src( get_post_thumbnail_id( $postID ), $size);

	}

	return false;

}


/**
 * get all the breadcrumbs for an object (post, page or term)
 * @param  boolean $object object (post, page or term)
 * @param  boolean $self   includes self as 'crumb'
 * @param  boolean $id     optional id attribute
 * @param  boolean $html   return type
 * @return object          returns an array
 */
function plura_wp_breadcrumbs( $object = false, $self = false, $id = false, $html = true ) {

	$crumbs = [];

	if( is_archive() && !$object ) {

		$crumb = plura_wp_breadcrumbs_terms( get_queried_object()->term_id, get_queried_object()->taxonomy, $self );

		if( $crumb ) {
  
			$crumbs[] = $crumb;

		}

	} else {

		if( $object && is_int( $object ) ) {

			$object = get_post( $object );
		
		} else if(!$object) {

			$object = get_post();

		}

		if( ( is_single() && !$object ) || $object->post_type !== 'page' ) {

			$post_taxonomies = get_object_taxonomies( $object );

			if( !empty( $post_taxonomies ) ) {

				$terms = get_the_terms( $object, $post_taxonomies[0] );

				if( !empty( $terms ) ) {

					foreach( $terms as $term ) {

						$crumbs[] = plura_wp_breadcrumbs_terms( $term->term_id, $term->taxonomy, true );

					}

				}

			}

		} else if( ( is_page() && !$object ) || $object->post_type === 'page' ) {

			$ancestors = get_ancestors( $object->ID, get_post_type(), 'post_type' );

			if( !empty( $ancestors ) ) {

				$group = [];

				foreach( $ancestors as $ancestor ) {

					$group[] = plura_wp_breadcrumb( $ancestor );

				}

				$crumbs[] = $group;

			}

		}

	}


	if( has_filter('plura_wp_breadcrumbs') ) {

		$crumbs = apply_filters('plura_wp_breadcrumbs', $crumbs, $id );

	}


	if( !empty( $crumbs ) ) {

		if( $html ) {

			$return = [];

			if( !is_array( $crumbs[0] ) || !array_key_exists(0, $crumbs[0]) ) {

				$crumbs = [ $crumbs ];

			}

			foreach( $crumbs as $group ) {

				$g = [];

				foreach( $group as $crumb ) {

					$classes = ['plura-wp-breadcrumb'];

					if( !is_array( $crumb ) ) {

						$c = $crumb;

					} else { 

						$classes[] = 'has-link';

						$c = "<a href=\"" . $crumb['link'] . "\" title=\"" . $crumb['name'] . "\">" . $crumb['name'] . "</a>";

					}

					$atts = ['class' => implode(' ', $classes)];

					$g[] = "<li " . plura_attributes( $atts ) . ">" . $c . "</li>";

				}

				$return[] = "<ul class=\"plura-wp-breadcrumbs-group\">" . implode('', $g) . "</ul>";

			}

			$atts = ['class' => 'plura-wp-breadcrumbs'];

			if( $id ) {

				$atts['data-id'] = $id;

			}

			return "<div " . plura_attributes( $atts ) . ">" . implode('', $return) . "</div>";

		}

		return $crumbs;

	}

}


function plura_wp_breadcrumbs_terms( $termID, $taxonomy, $include = false ) {

	$crumbs = [];

	$ancestors = get_ancestors( $termID, $taxonomy );

	if( !empty( $ancestors ) ) {

		foreach( array_reverse( $ancestors ) as $ancestor ) {

			$crumbs[] = plura_wp_breadcrumb( $ancestor, $taxonomy );

		}

	}

	if( $include ) {

		$crumbs[] = plura_wp_breadcrumb( $termID, $taxonomy );

	}

	return $crumbs;

}


function plura_wp_breadcrumb( $id, $taxonomy = false ) {

	if( $taxonomy ) {

		return ['type' => 'term', 'link' => get_term_link( $id, $taxonomy ), 'name' => get_term( $id, $taxonomy )->name, 'id' => $id ];

	} else if( !is_int( $id ) ) {

		return ['type' => 'single', 'name' => $id, 'id' => $id];

	}

	return ['type' => 'single', 'link' => get_permalink( $id ), 'name' => get_the_title( $id ) ];

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


$P_TITLE_ARGS = ['html' => 1];

function plura_p_title( array $args = [] ) {

	global $P_TITLE_ARGS;

	$args = array_merge( $P_TITLE_ARGS, $args );

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

	if( has_filter('plura_p_title') ) {

		$text = apply_filters('plura_p_title', $text);

	}

	if( $args['html'] ) {

		return "<div class=\"p-title\">" . $text . "</div>";

	}

	return $text;

}

add_shortcode('plura-p-title', function( $atts ) {

	global $P_TITLE_ARGS;

	$args = shortcode_atts($P_TITLE_ARGS, $atts);

	return plura_p_title( $args );

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
