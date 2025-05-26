<?php

if (!defined('ABSPATH')) exit;

function kcsync_api_call($url, $api_token) {
    //api call
    $response = wp_remote_get($url, [
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => "Bearer $api_token"
        ]
    ]);
    
    $body = wp_remote_retrieve_body( $response );
    return json_decode($body, true); // returning the response body as array
}