<?php
/**
 * Decides whether a request carries valid API credentials.
 *
 * Deny-by-default per docs/adr/0001-rest-api-architecture.md: a request is
 * authenticated only when it presents a Bearer token whose SHA-256 hash matches
 * the stored `api_token_hash` option. Only the hash is ever stored, mirroring
 * the remember-me handling in `user_auth_cache`. Tokens are never accepted from
 * the query string. When no hash is configured the API stays closed.
 *
 * Fix for GHSA-mx5c-qj6m-2w89 / CVE-2026-39954.
 *
 * @param  string       $authorization The raw Authorization header value
 * @param  array        $query_params  Parsed query-string parameters
 * @param  string|false $expected_hash Stored SHA-256 token hash, or false when unset
 * @return bool         True only when a valid Bearer token is presented
 */
function api_authenticate(string $authorization, array $query_params, string|false $expected_hash) : bool {
	// A token in the URL leaks through logs and referrers; refuse it outright.
	foreach (['token', 'api_token', 'access_token'] as $leaky) {
		if (array_key_exists($leaky, $query_params)) {
			return false;
		}
	}

	if ($expected_hash === false || $expected_hash === '') {
		return false;
	}

	if (strncmp($authorization, 'Bearer ', 7) !== 0) {
		return false;
	}

	$presented = substr($authorization, 7);

	if ($presented === '') {
		return false;
	}

	return hash_equals($expected_hash, hash('sha256', $presented));
}
