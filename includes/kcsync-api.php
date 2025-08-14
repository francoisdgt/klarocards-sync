<?php

if (!defined('ABSPATH')) exit;

function kcsync_api_call($url, $api_token) {
	// API call with hardened defaults and error handling
	$response = wp_remote_get($url, [
		'headers' => [
			'Accept' => 'application/json',
			'Authorization' => "Bearer $api_token",
		],
		'timeout' => 15,
		'redirection' => 3,
		'user-agent' => 'klarocards-sync/0.5.1; ' . home_url(),
	]);

	if (is_wp_error($response)) {
		return $response;
	}

	$status_code = (int) wp_remote_retrieve_response_code($response);
	if ($status_code < 200 || $status_code >= 300) {
		return new WP_Error('kcsync_http_error', 'Klaro Cards API HTTP error', [
			'status' => $status_code,
			'url' => $url,
		]);
	}

	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		return new WP_Error('kcsync_json_error', 'Invalid JSON from Klaro Cards API', [
			'url' => $url,
			'body' => $body,
		]);
	}

	return $data; // returning the response body as array
}