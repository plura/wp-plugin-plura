<?php


function plura_data_to_sharepoint(array $data, string $client_id, string $client_secret, string $tenant_id, string $site_id, string $list_id): void {
    
    // 1. Authenticate to SharePoint (Get Access Token)
    $token_url = "https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/token";

    $auth_response = plura_curl($token_url, array(
        'body' => array(
            'grant_type'    => 'client_credentials',
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'scope'         => "https://graph.microsoft.com/.default"
        ),
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded' // Required for form submission
        )
    ));


    if ( isset( $auth_response['error'] ) ) {

        // Check the output for errors
        error_log("Authentication error: " . $auth_response['error']);
    
        return;
    
    }

    
    $auth_body = json_decode($auth_response['body'], true);
   
    if ( !isset( $auth_body['access_token'] ) ) {
        
        error_log("Authentication failed: " . $auth_response['body']);
        
        return;
    
    }


    // 2. Prepare the API request to insert into SharePoint
    $sharepoint_api_url = "https://graph.microsoft.com/v1.0/sites/$site_id/lists/$list_id/items";
    
    $headers = array(
        'Authorization' => 'Bearer ' . $auth_body['access_token'],
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/json'
    );
    


    // 3. Send the request to SharePoint
    $response = plura_curl($sharepoint_api_url, array(
        'headers' => $headers,
        'body'    => ['fields' => $data]
    ), true); // Pass true for JSON-encoded body


    if ( isset( $response['body'] ) ) {

        $response_data = json_decode($response['body'], true);

        if (isset($response_data['error'])) {

            error_log("SharePoint response error: " . json_encode($response_data['error']));
        
        } else {
            
            error_log("SharePoint response: " . $response['body']);
        
        }
    
    }

}


