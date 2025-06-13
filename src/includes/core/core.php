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
        if ($k === 'class') {
            $v = implode(' ', (array) $v);
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


/**
 * Sends a POST request using cURL with optional JSON or URL-encoded body formatting.
 *
 * @param string $url   The URL to send the request to.
 * @param array  $args  The request arguments. Supports:
 *                      - 'body'    (array) Required. The request body.
 *                      - 'headers' (array) Optional. An associative array of headers.
 * @param bool   $json  Whether to encode the body as JSON (true) or as URL-encoded form data (false).
 *
 * @return array        Response array with:
 *                      - 'body'  (string) The raw response body on success.
 *                      - 'error' (string) Present only if an error occurred (contains the error message).
 */
function plura_curl(string $url, array $args, bool $json = false): array {
    
    $ch = curl_init();
    
    // Convert the body array to JSON if $json is true, otherwise URL-encode it
    $post_fields = $json ? json_encode($args['body']) : http_build_query($args['body']);

    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    
    // Set headers if provided
    if (isset($args['headers'])) {
        $headers = [];
        foreach ($args['headers'] as $key => $value) {
            $headers[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    // Execute the request
    $response = curl_exec($ch);
    
    // Check for errors
    if ($response === false) {
        return [
            'error' => curl_error($ch),
            'body'  => ''
        ];
    }

    curl_close($ch);

    return [
        'body' => $response
    ];
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
