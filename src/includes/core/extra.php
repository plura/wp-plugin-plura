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