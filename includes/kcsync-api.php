<?php

if (!defined('ABSPATH')) exit;

function kcsync_api_call($url, $api_token, $retry_count = 0) {
	// Configuration des timeouts et réessais
	$max_retries = 3;
	$base_timeout = 20; // timeout de base plus généreux
	$timeout = $base_timeout * (1 + $retry_count); // timeout progressif
	
	// API call with hardened defaults and error handling
	$response = wp_remote_get($url, [
		'headers' => [
			'Accept' => 'application/json',
			'Authorization' => "Bearer $api_token",
		],
		'timeout' => $timeout,
		'redirection' => 3,
		'user-agent' => 'klarocards-sync/0.5.4; ' . home_url(),
	]);

	if (is_wp_error($response)) {
		$error_code = $response->get_error_code();
		
		// Réessayer pour certains types d'erreurs (timeout, connexion)
		if (($error_code === 'http_request_failed' || $error_code === 'timeout') && $retry_count < $max_retries) {
			$retry_count++;
			$delay = pow(2, $retry_count); // backoff exponentiel : 2s, 4s, 8s
			
			error_log("Klaro Cards API: Tentative {$retry_count}/{$max_retries} échouée pour {$url}. Nouvelle tentative dans {$delay}s. Erreur: " . $response->get_error_message());
			
			sleep($delay);
			return kcsync_api_call($url, $api_token, $retry_count);
		}
		
		return $response;
	}

	$status_code = (int) wp_remote_retrieve_response_code($response);
	
	// Réessayer pour les erreurs 5xx (erreurs serveur) et certains 4xx
	if (($status_code >= 500 || $status_code === 429) && $retry_count < $max_retries) {
		$retry_count++;
		$delay = pow(2, $retry_count);
		
		error_log("Klaro Cards API: Erreur HTTP {$status_code} pour {$url}. Tentative {$retry_count}/{$max_retries} dans {$delay}s.");
		
		sleep($delay);
		return kcsync_api_call($url, $api_token, $retry_count);
	}
	
	if ($status_code < 200 || $status_code >= 300) {
		return new WP_Error('kcsync_http_error', 'Klaro Cards API HTTP error', [
			'status' => $status_code,
			'url' => $url,
			'retry_count' => $retry_count,
		]);
	}

	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		return new WP_Error('kcsync_json_error', 'Invalid JSON from Klaro Cards API', [
			'url' => $url,
			'body' => $body,
			'retry_count' => $retry_count,
		]);
	}

	return $data; // returning the response body as array
}