<?php

/**
 * External services: SharePoint lists through Microsoft Graph.
 */

/**
 * Adds an item to a SharePoint list through Microsoft Graph, authenticating as an Azure AD app.
 *
 * Errors and Graph's response are written to the PHP error log; nothing is returned.
 *
 * @param array  $data          List item fields, keyed by column name.
 * @param string $client_id     Azure AD app (client) ID.
 * @param string $client_secret Azure AD app secret.
 * @param string $tenant_id     Azure AD tenant ID.
 * @param string $site_id       SharePoint site ID.
 * @param string $list_id       SharePoint list ID.
 *
 * @return void
 */
function plura_data_to_sharepoint(array $data, string $client_id, string $client_secret, string $tenant_id, string $site_id, string $list_id): void
{
	// 1. Authenticate to SharePoint (Get Access Token)
	$token_url = "https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/token";

	$auth_response = plura_curl($token_url, [
		'body' => [
			'grant_type'    => 'client_credentials',
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'scope'         => "https://graph.microsoft.com/.default",
		],
		'headers' => [
			'Content-Type' => 'application/x-www-form-urlencoded', // Required for form submission
		],
	]);

	if (isset($auth_response['error'])) {
		// Check the output for errors
		error_log("Authentication error: " . $auth_response['error']);

		return;
	}

	$auth_body = json_decode($auth_response['body'], true);

	if (!isset($auth_body['access_token'])) {
		error_log("Authentication failed: " . $auth_response['body']);

		return;
	}

	// 2. Prepare the API request to insert into SharePoint
	$sharepoint_api_url = "https://graph.microsoft.com/v1.0/sites/$site_id/lists/$list_id/items";

	$headers = [
		'Authorization' => 'Bearer ' . $auth_body['access_token'],
		'Accept'        => 'application/json',
		'Content-Type'  => 'application/json',
	];

	// 3. Send the request to SharePoint
	$response = plura_curl($sharepoint_api_url, [
		'headers' => $headers,
		'body'    => ['fields' => $data],
	], true); // Pass true for JSON-encoded body

	if (isset($response['body'])) {
		$response_data = json_decode($response['body'], true);

		if (isset($response_data['error'])) {
			error_log("SharePoint response error: " . json_encode($response_data['error']));
		} else {
			error_log("SharePoint response: " . $response['body']);
		}
	}
}

function plura_p_posts_remote($args)
{
	$url = $args['source'];

	unset($args['source']);

	$response = wp_remote_get($url . '?' . http_build_query($args));

	if (is_wp_error($response)) {
		return __('Loading Failed...');
	}

	return json_decode($response['body']);
}
