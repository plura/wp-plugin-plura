<?php

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


/**
 * Returns the title (post or term) as plain text or wrapped in HTML.
 *
 * @param WP_Post|WP_Term|int $object   Post/term object or post ID.
 *
 * @param string|false        $tag      Optional. HTML tag to wrap the title in. Default 'h3'.
 *                                      Pass false to return plain text only.
 * @param bool                $link     Optional. Whether to wrap the title in a link to the post or term archive. Default false.
 * @param array|string|null   $class    Optional. Additional CSS classes to add to the tag. Can be string or array. Default null.
 *
 * @param string|null         $context  Optional. Filter context for `plura_wp_title`. Default null.
 *
 * @return string|null                  The rendered title HTML or plain string, or null if the object is invalid.
 */
function plura_wp_title(
	WP_Term|WP_Post|int $object,
	string|false $tag = 'h3',
	bool $link = false,
	array|string|null $class = null,
	?string $context = null
): ?string {
	if ( is_int( $object ) ) {
		$object = get_post( $object );
	}

	if ( ! ( $object instanceof WP_Post || $object instanceof WP_Term ) ) {
		return null;
	}

	$text = apply_filters(
		'plura_wp_title',
		$object instanceof WP_Post ? $object->post_title : $object->name,
		$object,
		$context
	);

	if ( empty( $text ) ) {
		return null;
	}

	if ( $tag !== false ) {
		$type = $object instanceof WP_Post ? 'post' : 'term';

		$classes = [ 'plura-wp-title', "plura-wp-{$type}-title" ];

		if ( $class ) {
			$classes = array_merge(
				$classes,
				array_filter(
					array_map( 'trim', is_array( $class ) ? $class : explode( ' ', $class ) )
				)
			);
		}

		$html = sprintf(
			'<%1$s %3$s>%2$s</%1$s>',
			tag_escape( $tag ),
			esc_html( $text ),
			plura_attributes([ 'class' => $classes ])
		);
	} else {
		$html = esc_html( $text );
	}

	if ( $link ) {
		$html = plura_wp_link(
			html: $html,
			target: $object,
			title: $text
		);
	}

	return $html;
}


/**
 * Shortcode [plura-wp-title] to render the current post/term title.
 *
 * @param array $atts {
 *     @type int|string   $object   Post/term ID or a string like 'current' (default: current post or term).
 *     @type string|false $tag      Tag name (h2, h3, etc.) or "false"/"0" to disable wrapping.
 *     @type bool|string  $link     Whether to wrap the title in a link.
 *     @type string|null  $context  Optional context string for filtering.
 * }
 * @return string|null
 */
function plura_wp_title_shortcode( array $atts ): ?string {
	$atts = shortcode_atts([
		'object'  => null,
		'tag'     => 'h3',
		'link'    => false,
		'context' => null,
	], $atts );

	// Determine the object: explicit ID, current post, or current term
	if ( is_numeric( $atts['object'] ) ) {
		$object = intval( $atts['object'] );
	} else {
		$queried_object = get_queried_object();
		if ( $queried_object instanceof WP_Post || $queried_object instanceof WP_Term ) {
			$object = $queried_object;
		} else {
			return '';
		}
	}

	$link    = filter_var( $atts['link'], FILTER_VALIDATE_BOOLEAN );
	$context = $atts['context'] ?: null;

	$tag = strtolower( trim( $atts['tag'] ) );
	$tag = in_array( $tag, ['false', '0', ''], true ) ? false : $tag;

	return plura_wp_title(
		object: $object,
		tag: $tag,
		link: $link,
		context: $context
	);
}
add_shortcode( 'plura-wp-title', 'plura_wp_title_shortcode' );



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
