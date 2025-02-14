<?php


$P_RESTRICTED_AREA_VARS = [
	'class' => ''
];


add_shortcode('p-restricted-area', function( $atts ) {

	global $P_RESTRICTED_AREA_VARS;

	$args = shortcode_atts( $P_RESTRICTED_AREA_VARS, $atts );

	return sgl_restricted_area( $args );

} );


function sgl_restricted_area( $args ) {

	$atts = ['class' => ['p-restricted-area']];	

	$html = [];

	if( !is_user_logged_in() ) {

		$html[] = p_restricted_area_alert( __('This section is restricted and requires the necessary credentials for access. Please log in to proceed.', 'sgl-main') );

		$html[] = p_restricted_area_log();

	} else {

		$atts['class'][] = 'is-logged';

		$user = wp_get_current_user();

		if( p_restricted_user_valid( $user ) ) {

			$atts['class'][] = 'has-access';

			$html[] = p_restricted_area_alert( sprintf( __('Welcome, <strong>%s</strong>. You have successfully logged into the restricted area. Here, you will find access to valuable and sensitive files. Please ensure that you handle this information with the utmost care and confidentiality.', 'sgl-main'), $user->data->display_name ) );

			if( has_filter('p_restricted_area_data') ) {

				$data = apply_filters('p_restricted_area_data', get_the_ID() );

				if( $data ) {

					$a = ['class' => ['p-restricted-area-data']];

					$html[] = "<div " . p_attributes( $a ) . ">" . $data . "</div>";

				}

			}

		} else {

			$html[] = p_restricted_area_alert( sprintf( __('<strong>%s</strong>, your access was denied. You don\'t have the necessary credentials to view this section.', 'sgl-main'), $user->data->display_name ) );

		}

	}

	if( !empty( $args['class'] ) ) {

		$atts['class'] = array_merge( $atts['class'], explode(',', $args['class'] ) );

	}

	return "<div " . p_attributes( $atts ) . ">" . implode('', $html) . "</div>";

}




/**
 * restricted area log
 * @return string 	html
 */
function p_restricted_area_log() {

	$html = [];

	$login_form_args = [
		'echo'            => false,
		//'redirect'        => get_permalink( get_the_ID() ),
		'remember'        => true,
		'value_remember'  => true,
	];

	$html[] = preg_replace('/(<form)/', '$1 class="sgl-form"', wp_login_form( $login_form_args ) );

	$atts = ['class' => ['p-restricted-area-log'] ];

	return "<div " . p_attributes( $atts ) . ">" . implode('', $html) . "</div>";

}



function p_restricted_area_alert( $msg ) {

	$atts = ['class' => 'p-restricted-area-alert'];

	return "<div " . p_attributes( $atts ) . ">" . $msg . "</div>";

}



/**
 * [p_restricted_user_valid description]
 * @param  object $userID   user data
 * @return boolean          valid user true/false
 */
function p_restricted_user_valid( $user ) {

	if( has_filter('p_restricted_user_valid') ) {

		return apply_filters('p_restricted_user_valid', $user );

	}

	return true;

}

?>
