<?php

/**
 * . Revolution Slider
 * 		- p_revslider
 * 		- plura_wp_revslider_bg_img
 * 		- plura_wp_revslider_bg_video
 * 		- plura_wp_revslider_bg_video_data
 * . Essential Grid
 */


// REVOLUTION SLIDER

$P_REVSLIDER = [
	'alias' => '',
	'img' => '',
	'video' => ''
];

function plura_wp_revslider( $args ) {

	global $P_REVSLIDER;

	$atts = array_merge( $P_REVSLIDER, $args );

	if( !empty( $atts['alias'] ) || ( !empty( $atts['img'] ) && !empty( $atts['video'] ) ) )  {

		if( !empty( $atts['img'] ) && !empty( $atts['video'] ) ) {

			if( plura_wp_revslider_bg_video( [], $atts['video'] ) ) {

				$id = $atts['video'];


			} else {

				$id = $atts['img'];

			}

			//retrieve alias
			$revslider = new RevSlider();

			foreach( $revslider->get_sliders() as $slider ) {

				if( $slider->id === $id ) {

					$alias =  $slider->alias;

					break;

				}

			}

		} else if( !empty( $atts['alias'] ) ) {

			$alias = $atts['alias'];

		}

		return do_shortcode('[rev_slider alias="' . $alias . '"][/rev_slider]');

	}

}

add_shortcode('plura-wp-revslider', function( $args) {

	global $P_REVSLIDER;

	return plura_wp_revslider( shortcode_atts( $P_REVSLIDER, $args ) );

} );


/**
 * in order for this function to work with revslider it's necessary to hack the add_slide_main_image
 *
 * hack:
 * 
 * 	- include the following in the revslider/includes/output.class.php add_slide_main_image method,
 *
 * 		after this line:
 *		
 *			$img = $this->get_image_data();
 *
 * 		add:
 * 		
 * 			//PLURA
 *		  	if( function_exists('plura_wp_revslider_bg_img') ) {
 *
 *				$img = plura_wp_revslider_bg_img( $img, $this->slider_id );
 *
 *			} 
 *			
 * For categories, the ACF plugin and an id value returned by a 'featured_image' image field are required by default.
 * 
 * @param  string $img 	revolution image src (if it exists) 
 * @return array|null
 */
function plura_wp_revslider_bg_img( $imgData, $sliderID = false ) {

	$obj = get_queried_object();

	//check if filter function exists
    if( has_filter('plura_wp_revslider_bg_img') ) {

		$data = apply_filters('plura_wp_revslider_bg_img', $imgData, intval( $sliderID ) );

		//if image data is returned
		if( $data && is_array( $data ) ) {

			return $data;

		//othewise it uses the functions default formula for pages/posts/categories
		//if a data "false" value is returned, no modification to revslider bg formula should be made
		} else if( $data && !empty( $imgData ) && is_array( $imgData ) ) {

			if( is_int( $data ) ) {

				$id = $data;

			} else if( is_singular() && ( has_post_thumbnail() || ( function_exists('p_wpml_id') && has_post_thumbnail( p_wpml_id() ) ) ) ) {

				$id = function_exists('p_wpml_id') ? get_post_thumbnail_id( p_wpml_id() ) : get_post_thumbnail_id();

			//categories should use ACF in order to retrieve a 'featured_image' field value
			} else if( class_exists('ACF') && isset( $obj->term_id ) ) {

				$id = get_field('featured_image', $obj );
			
			}

			if( isset( $id ) ) {

				$src = wp_get_attachment_image_src( $id, 'full' );

				if( $src ) {

					preg_match('/\/([^\/]+)\.[0-9a-z]+$/', $src[0], $matches);

					$imgData = array_merge( $imgData, [
						'src' => $src[0],
						'width' => $src[1],
						'height' => $src[2],
						'title' => $matches[1],
						'data-lazyload' => $src[0]
					]);

				}

			}

		}

	}

	return $imgData;

}


/**
 * 
 * in order to use this, it's necessary to create a "featured_video" custom field (ie. post, cpt, page, term)
 * 
 * hack:
 * 
 * 	- include the following in the revslider/includes/output.class.php add_background_video method,
 *
 * 		after this line:
 *		
 *			$type = $slide->get_param(array('bg', 'type'), 'trans'); 
 *
 * 		add:
 * 		
 * 			//PLURA
 *		  	if( function_exists('plura_wp_revslider_bg_video') ) {
 *
 *				$this->add_html_background_video();
 *
 *				return;
 *
 *			}
 *
 *	- include the following in the revslider/includes/output.class.php add_html_background_video method,
 *
 * 		before this line:
 *
 * 			echo $this->ld().RS_T7.'<rs-bgvideo '."\n";
 *
 * 		add:
 *
 *			//PLURA
 *		 	if( function_exists('plura_wp_revslider_bg_video') ) {
 *
 *				$pvideo = plura_wp_revslider_bg_video( $data, $this->slider_id );
 *
 * 				if( $pvideo ) {
 *
 * 					$data = $pvideo;
 *
 *				}
 *
 *			}
 *
 *  
 * @return null
 */

function plura_wp_revslider_bg_video( $vidData, $sliderID = false )  {

	//check if filter function exists
    if( class_exists('ACF') && has_filter('plura_wp_revslider_bg_video') ) {

    	$obj = get_queried_object();

    	$data = apply_filters('plura_wp_revslider_bg_video', $vidData, intval( $sliderID ) );

    	//if data is int
		if( $data && ( is_int( $data ) || is_string( $data ) ) ) {

			$source = $data;

		//if data is a post or term
		} else if( $data && is_a( $data, 'WP_Post' ) || is_a( $data, 'WP_Term' ) ) {

			$source = is_a( $data, 'WP_Post' ) ? $data->ID : $data;

		//if data === true check if current queried object is a term or post
		} else if( $data && ( is_a( $obj, 'WP_Post' ) || is_a( $obj, 'WP_Term' ) ) ) {

	        if( is_a( $obj, 'WP_Term' ) ) {

	            $source = $obj;

	        } else {

	            $source = function_exists('p_wpml_id') ? p_wpml_id( $obj->ID ) : $obj->ID;

	        }

		}

		//if a source exists
		if( isset( $source ) ) {

			if( is_int( $source ) || is_a('WP_Term', $source) ) {

				$file = get_field('featured_video', $source);

				if( $file && is_array( $file ) && array_key_exists('url', $file) ) {

					$url = $file['url'];

				}

			} else if( is_string( $source ) ) {

				$url = $source;

			}

			if( isset( $url ) ) {

				return plura_wp_revslider_bg_video_data( $url );

			}

		}

	}

	return $vidData;

}


function plura_wp_revslider_bg_video_data( $url ) {

	$data = [

		'video' => [
			'w' => '100%',
			'h' => '100%',
			'nse' => false,
			'l' => 1,
			'ptimer' => null,
			'vfc' => 1
		]

	];

	$url = preg_replace('/https?:/', '', $url);	

	//if( preg_match('/\.mp4(?!\.)/', $url ) ) {

		$data['mp4'] = $url;

	//} elseif( preg_match('/\.webm(?!\.)/', $url ) ) {
		
		//$data['webm'] = $url;
	
	//}

	return $data;

}


/*
(
    [video] => Array
        (
            [w] => 100%
            [h] => 100%
            [nse] => false
            [l] => 1 //loop
            [ptimer] => 
            [vfc] => 1 //video fit cover
        )

    [mp4] => //amaro-vanveggel.com/wp/wp-content/uploads/2023/03/Taryn_Elliott_video1_optimized.mp4
)
 */



//ESSENTIAL GRID
/**
 * in order for this plugin function with terms it's necessary to hack some files.
 *
 *
 *	ADD POST DATA
 * 
 * 	- include the following change in the essential-grid/public/essential-grid.class.php get_post_media_source_data method.
 *
 *
 * 		replace this line:
 * 		
 * 			$post_media_source_data = $base->get_post_media_source_data($post['ID'], $post_media_source_type, $media_sources, $image_sizes);
 *
 *		with:
 *
 * 			$post_media_source_data = $base->get_post_media_source_data($post['ID'], $post_media_source_type, $media_sources, $image_sizes, $post);
 *
 * 
 * 	- include the following change in the essential-grid/includes/base.class.php get_post_media_source_data method.
 *
 * 
 * 		replace this line:
 *		
 *			public function get_post_media_source_data($post_id, $image_type, $media_sources, $image_size = array())
 *
 * 		with:
 * 		
 * 			public function get_post_media_source_data($post_id, $image_type, $media_sources, $image_size = array(), $post)
 * 			
 *
 * 		replace this line:
 *
 * 			return apply_filters('essgrid_modify_media_sources', $ret, $post_id);
 *
 * 		with:
 *
 * 			return apply_filters('essgrid_modify_media_sources', $ret, $post_id, $post);
 * 
 */

$P_EGRID_DUMMY_POST = [
	'ID' => '',
	'post_author' => '',
	'post_date' => '',
	'post_date_gmt' => '',
	'post_content' => '',
	'post_title' => '',
	'post_excerpt' => '',
	'post_status' => '',
	'comment_status' => '',
	'ping_status' => '',
	'post_password' => '',
	'post_name' => '',
	'to_ping' => '',
	'pinged' => '',
	'post_modified' => '',
	'post_modified_gmt' => '',
	'post_content_filtered' => '',
	'post_parent' => '',
	'guid' => '',
	'menu_order' => '',
	'post_type' => '',
	'post_mime_type' => '',
	'comment_count' => '',
	'filter' => '',
	'ancestors' => [],
	'page_template' => '',
	'post_category' => [],
	'tags_input' => []
];
