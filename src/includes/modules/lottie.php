<?php

//https://docs.lottiefiles.com/lottie-player/components/lottie-player/properties
$P_LOTTIE_PARAMS = [
	'autoplay' => 1,
	'background' => 'transparent',
	'controls' => 0,
	'count' => '',
	'direction' => 1,
	'disableCheck' => 0,
	'hover' => 0,
	'intermission' => 1,
	'id' => '',
	'height' => '100%',
	'loop' => 0,
	'mode' => 'normal',
	'renderer' => 'svg',
	'speed' => 1,
	'src' => '',
	'width' => '100%'
];

function p_lottie( $args ) {

	$attributes = [];

	$style = [];

	$atts = ['background' => $args['background'], 'speed' => $args['speed'], 'src' => $args['src']];

	if( !empty( $args['id'] ) ) {

		$atts['id'] = $args['id'];

	}

	foreach( ['autoplay', 'controls', 'loop', 'hover'] as $a ) {

		if( $args[ $a ] ) {

			$attributes[] = $a;

		}

	}

	foreach( ['width', 'height'] as $s ) {

		if( $args[ $s ] ) {

			if( ctype_digit( $args[ $s ] ) ) {

				$args[$s] .= "px";

			}

			$style[] = $s . '=' . $args[ $s ];

		}

	}

	if( empty( $style ) ) {

		$atts['style'] = implode('; ', $attributes);

	}


	return "<lottie-player " . p_attributes( $atts ) . ( !empty( $attributes ) ? ' ' . implode(' ', $attributes) : '' ) . "></lottie-player>";

}


add_shortcode('p-lottie', function( $args ) {

	global $P_LOTTIE_PARAMS;

	$args = shortcode_atts($P_LOTTIE_PARAMS, $args);

	return p_lottie( $args );

} );

?>