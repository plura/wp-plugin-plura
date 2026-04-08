<?php


//https://developers.google.com/apps-script/guides/v8-runtime


/* FIX: CF7 Breaking Spaces */
add_filter('wpcf7_autop_or_not', '__return_false');


/**
 * Send payload to a Google Apps Script endpoint.
 *
 * Accepts either a full Web App URL or just a script ID and normalizes it to:
 *   https://script.google.com/macros/s/{ID}/exec
 * Adds `_referrer` automatically.
 *
 * @param string $endpoint  GAS Web App URL OR script ID.
 * @param array  $payload   Key-value data to send (JSON-encoded; arrays preserved).
 * @param bool   $blocking  If true, wait for response (default: false).
 * @param int    $timeout   Request timeout in seconds (default: 4).
 * @return array|\WP_Error  Response from wp_remote_post() or WP_Error (when blocking and fails).
 */
function plura_to_sheets(
	string $endpoint,
	array $payload,
	bool $blocking = false,
	int $timeout = 4
) {
	$endpoint = trim($endpoint);
	if ($endpoint === '') {
		return new WP_Error('plura_empty_endpoint', 'Empty GAS endpoint');
	}
	// Normalize: if it's not a URL, treat as script ID
	if (!str_starts_with($endpoint, 'http://') && !str_starts_with($endpoint, 'https://')) {
		$endpoint = "https://script.google.com/macros/s/{$endpoint}/exec";
	}

	// Always include referrer
	$payload['_referrer'] = wp_get_referer() ?: home_url('/');

	$args = [
		'method'   => 'POST',
		'headers'  => ['Content-Type' => 'text/plain'], // ok for GAS parsing e.postData.contents
		'body'     => wp_json_encode($payload),
		'timeout'  => $timeout,
		'blocking' => $blocking,
	];

	$response = wp_remote_post($endpoint, $args);

	if ($blocking) {
		if (is_wp_error($response)) {
			error_log('[CF7→Sheets] HTTP error: ' . $response->get_error_message());
		} else {
			$code = wp_remote_retrieve_response_code($response);
			$body = wp_remote_retrieve_body($response);
			error_log('[CF7→Sheets] HTTP ' . $code . ' endpoint=' . $endpoint);
			error_log('[CF7→Sheets] BODY ' . $body);
		}
	}

	return $response;
}


/**
 * Register a CF7 hook to forward submissions to Google Sheets.
 *
 * Behavior:
 * - Match CF7 forms by **numeric ID**, **7-char shortcode hash**, or **title**.
 * - If 'whitelist' exists and is non-empty: include ONLY those keys (in that order).
 * - Else (open mode): include all user fields except CF7 internals and any in 'blacklist'.
 * - Per-form 'endpoint' overrides the global endpoint (full URL or script ID).
 * - Always forwards 'gas_config_key' if present in the submission (even in whitelist mode).
 *
 * @param string $endpoint Global GAS endpoint (URL or script ID).
 * @param array  $forms    List of form configs. Each entry:
 *                         [
 *                           'id'        => '101',             // optional: numeric post ID
 *                           'hash'      => '79d97e5',         // optional: 7-char shortcode hash
 *                           'title'     => 'My Form',         // optional: form title
 *                           'whitelist' => ['field-1', ...],  // optional (strict mode if non-empty)
 *                           'blacklist' => ['_url', ...],     // optional (only used when no whitelist)
 *                           'endpoint'  => 'AKfycbw...'       // optional per-form override
 *                         ]
 * @param bool  $blocking  If true, block until response (default: false).
 * @param int   $timeout   Timeout in seconds (default: 4).
 * @return void
 */
function plura_cf7_to_sheets(
	string $endpoint,
	array $forms,
	bool $blocking = false,
	int $timeout = 4
): void {
	add_action('wpcf7_mail_sent', function ($contact_form) use ($endpoint, $forms, $blocking, $timeout) {
		if (!$contact_form) return;

		$submission = WPCF7_Submission::get_instance();
		if (!$submission) return;

		// Current form identifiers (hash sliced to 7 chars to match shortcode)
		$current = [
			'id'    => method_exists($contact_form, 'id')    ? (string) $contact_form->id()      : '',
			'hash'  => method_exists($contact_form, 'hash')  ? (string) $contact_form->hash(7)   : '',
			'title' => method_exists($contact_form, 'title') ? (string) $contact_form->title()    : '',
		];

		$post_data = $submission->get_posted_data();

		foreach ($forms as $entry) {
			$want_id    = isset($entry['id'])    ? (string) $entry['id']    : '';
			$want_hash  = isset($entry['hash'])  ? (string) $entry['hash']  : '';
			$want_title = isset($entry['title']) ? (string) $entry['title'] : '';

			$matched =
				($want_id    !== '' && $want_id    === $current['id'])   ||
				($want_hash  !== '' && $want_hash  === $current['hash']) ||
				($want_title !== '' && $want_title === $current['title']);

			if (!$matched) continue;

			$whitelist = $entry['whitelist'] ?? null;
			$blacklist = $entry['blacklist'] ?? [];

			// 1) Decide which keys to process
			if (is_array($whitelist) && !empty($whitelist)) {
				// Strict mode: the whitelist defines inclusion + order
				$keys = $whitelist;
			} else {
				// Open mode: gather all user fields except CF7 internals and blacklist
				$blk  = is_array($blacklist) ? array_fill_keys($blacklist, true) : [];
				$keys = [];
				foreach ($post_data as $key => $_) {
					if ($key === '' || $key[0] === '_' || isset($blk[$key])) continue;
					$keys[] = $key; // preserves CF7's insertion order
				}
			}

			// 1b) Ensure router key always passes through (even in whitelist mode)
			if (isset($post_data['gas_config_key']) && !in_array('gas_config_key', $keys, true)) {
				$keys[] = 'gas_config_key';
			}

			// 2) Single normalization loop (keep arrays as arrays; support CF7's [] names)
			$payload = [];
			foreach ($keys as $key) {
				// allow CF7's [] naming for multi-value fields
				$sourceKey = array_key_exists($key, $post_data)
					? $key
					: (array_key_exists($key . '[]', $post_data) ? $key . '[]' : null);

				if ($sourceKey === null) continue;

				$val = $post_data[$sourceKey];

				if (is_array($val)) {
					$payload[$key] = array_values(array_map('trim', $val)); // keep as array
				} else {
					$payload[$key] = is_string($val) ? trim($val) : $val;
				}
			}

			// 3) Per-form endpoint override or fallback to global
			$form_endpoint = !empty($entry['endpoint']) ? $entry['endpoint'] : $endpoint;

			// Delegate sending (adds _referrer, normalizes endpoint, handles HTTP)
			plura_to_sheets(
				endpoint: $form_endpoint,
				payload: $payload,
				blocking: $blocking,
				timeout: $timeout
			);

			return; // stop after first match
		}
	});
}


