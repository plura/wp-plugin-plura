<?php

function plura_explode( string $separator, string $string, int $limit = PHP_INT_MAX ) {

	return array_map('trim', explode( $separator, $string, $limit) );

}


function plura_bool( $value, $bool = NULL ) {

	if( is_null( $bool ) ) {

		return in_array( $value, ['1', 1, 'true', true, '0', 0, 'false', false], true );

	} else if( $bool ) {

		return in_array( $value, ['1', 1, 'true', true], true);

	}

	return in_array( $value, ['0', 0, 'false', false], true);

}

?>