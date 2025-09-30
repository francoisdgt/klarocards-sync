<?php

/**
 * Klaro Cards API client
 *
 * Handles HTTP calls to Klaro Cards API with error handling,
 * automatic retry and progressive timeouts.
 *
 * @package KlaroCardsSync
 * @since 0.6.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Makes API call to Klaro Cards with error handling and retry
 *
 * @param string $url API URL to call
 * @param string $api_token Authentication token
 * @param int $retry_count Number of attempts already made
 * @return array|WP_Error API data or error
 */
function kcsync_api_call($url, $api_token, $retry_count = 0)
{
	// Timeout and retry configuration
	$max_retries = 3;
	$base_timeout = 20; // Base timeout in seconds
	$timeout = $base_timeout * (1 + $retry_count); // Progressive timeout

	// API call with hardened defaults and error handling
	$response = wp_remote_get($url, [
		'headers' => [
			'Accept' => 'application/json',
			'Authorization' => "Bearer $api_token",
		],
		'timeout' => $timeout,
		'redirection' => 3,
		'user-agent' => 'klarocards-sync/0.7.2; ' . home_url(),
	]);

	if (is_wp_error($response)) {
		$error_code = $response->get_error_code();

		// Retry for certain error types (timeout, connection)
		if (($error_code === 'http_request_failed' || $error_code === 'timeout') && $retry_count < $max_retries) {
			$retry_count++;
			$delay = pow(2, $retry_count); // Exponential backoff: 2s, 4s, 8s

			error_log("Klaro Cards API: Tentative {$retry_count}/{$max_retries} échouée pour {$url}. Nouvelle tentative dans {$delay}s. Erreur: " . $response->get_error_message());

			sleep($delay);
			return kcsync_api_call($url, $api_token, $retry_count);
		}

		return $response;
	}

	$status_code = (int) wp_remote_retrieve_response_code($response);

	// Retry for 5xx errors (server errors) and 429 (Too Many Requests)
	if (($status_code >= 500 || $status_code === 429) && $retry_count < $max_retries) {
		$retry_count++;
		$delay = pow(2, $retry_count);

		error_log("Klaro Cards API: Erreur HTTP {$status_code} pour {$url}. Tentative {$retry_count}/{$max_retries} dans {$delay}s.");

		sleep($delay);
		return kcsync_api_call($url, $api_token, $retry_count);
	}

	// Check HTTP status code
	if ($status_code < 200 || $status_code >= 300) {
		return new WP_Error('kcsync_http_error', 'Klaro Cards API HTTP error', [
			'status' => $status_code,
			'url' => $url,
			'retry_count' => $retry_count,
		]);
	}

	// Parse JSON response
	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);

	if (json_last_error() !== JSON_ERROR_NONE) {
		return new WP_Error('kcsync_json_error', 'Invalid JSON from Klaro Cards API', [
			'url' => $url,
			'body' => $body,
			'retry_count' => $retry_count,
		]);
	}

	return $data; // Return parsed data
}
