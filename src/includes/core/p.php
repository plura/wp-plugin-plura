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
 * Resolves the current date archive's title and permalink from the query vars.
 *
 * Built from the query vars rather than core's `get_the_date()` approach, which
 * reads the global $post and so only gives the right answer inside the loop.
 *
 * @return array{0: string, 1: string}|null Title text and archive URL, or null if no year is set.
 */
function plura_wp_date_archive_title(): ?array {
	$year  = (int) get_query_var( 'year' );
	$month = (int) get_query_var( 'monthnum' );
	$day   = (int) get_query_var( 'day' );

	if ( ! $year ) {
		return null;
	}

	if ( $day ) {
		return [
			date_i18n( get_option( 'date_format' ), mktime( 0, 0, 0, $month, $day, $year ) ),
			get_day_link( $year, $month, $day ),
		];
	}

	// Month/year formats stay in the 'default' textdomain on purpose, so they
	// pick up WordPress core's existing translations instead of needing our own.
	if ( $month ) {
		return [
			date_i18n( _x( 'F Y', 'monthly archives date format' ), mktime( 0, 0, 0, $month, 1, $year ) ),
			get_month_link( $year, $month ),
		];
	}

	return [
		date_i18n( _x( 'Y', 'yearly archives date format' ), mktime( 0, 0, 0, 1, 1, $year ) ),
		get_year_link( $year ),
	];
}


/**
 * Returns a title (post, term, or archive) as plain text or wrapped in HTML.
 *
 * With no $object, resolves from the current request: the queried object on
 * singulars and taxonomy/post type/author archives, and the query vars on date
 * archives, which have no queried object. Titles are always bare — no
 * "Category:"/"Archives:" prefix, unlike core's `get_the_archive_title()`.
 *
 * @param WP_Post|WP_Term|WP_Post_Type|WP_User|int|null $object  Optional. Post, term, post type or user object,
 *                                                               or a post ID. Default null (resolve from the request).
 *
 * @param string|false        $tag      Optional. HTML tag to wrap the title in. Default 'h3'.
 *                                      Pass false to return plain text only.
 * @param bool                $link     Optional. Whether to wrap the title in a link to the post, term or archive. Default false.
 * @param array|string|null   $class    Optional. Additional CSS classes to add to the tag. Can be string or array. Default null.
 *
 * @param string|null         $context  Optional. Filter context for `plura_wp_title`. Default null.
 *
 * @return string|null                  The rendered title HTML or plain string, or null if nothing resolved.
 */
function plura_wp_title(
	WP_Post|WP_Term|WP_Post_Type|WP_User|int|null $object = null,
	string|false $tag = 'h3',
	bool $link = false,
	array|string|null $class = null,
	?string $context = null
): ?string {
	// Only an omitted $object falls back to the request — an ID that resolves to
	// nothing must stay a miss, not silently become the current page.
	$from_request = ( $object === null );

	if ( is_int( $object ) ) {
		$object = get_post( $object );
	} elseif ( $from_request ) {
		$object = get_queried_object();
	}

	$target = null;

	if ( $object instanceof WP_Post ) {
		$type   = 'post';
		$text   = $object->post_title;
		$target = $object;

	} elseif ( $object instanceof WP_Term ) {
		$type   = 'term';
		$text   = $object->name;
		$target = $object;

	} elseif ( $object instanceof WP_Post_Type ) {
		$type   = 'post-type';
		$text   = $object->labels->name;
		$target = get_post_type_archive_link( $object->name );

	} elseif ( $object instanceof WP_User ) {
		$type   = 'author';
		$text   = $object->display_name;
		$target = get_author_posts_url( $object->ID );

	} elseif ( $from_request && is_date() ) {
		$date = plura_wp_date_archive_title();

		if ( ! $date ) {
			return null;
		}

		$type              = 'date';
		[ $text, $target ] = $date;

	} else {
		return null;
	}

	$text = apply_filters( 'plura_wp_title', $text, $object, $context, $type );

	if ( empty( $text ) ) {
		return null;
	}

	if ( $tag !== false ) {
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
			target: $target,
			title: $text
		);
	}

	return $html;
}


/**
 * Shortcode [plura-wp-title] to render a post, term or archive title.
 *
 * @param array $atts {
 *     @type int|string   $object   Post ID, or anything non-numeric to resolve the current request (default).
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

	// An explicit ID, otherwise null so plura_wp_title() resolves the request itself
	$object = is_numeric( $atts['object'] ) ? intval( $atts['object'] ) : null;

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
