<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* Guards the REST API auth checkpoint added for GHSA-mx5c-qj6m-2w89 /
 * CVE-2026-39954. api_authenticate() is the single decision every /v1 route
 * passes through; the behavioural tests pin the deny-by-default contract and
 * the source-scan tests confirm no route group escapes the middleware. */

require_once __DIR__ . '/../../api/include/auth.php';

$token      = 'a-real-token-value';
$token_hash = hash('sha256', $token);

test('rejects a request with no Authorization header', function () use ($token_hash) {
	expect(api_authenticate('', [], $token_hash))->toBeFalse();
});

test('rejects a Bearer token whose hash does not match', function () use ($token_hash) {
	expect(api_authenticate('Bearer wrong-token', [], $token_hash))->toBeFalse();
});

test('accepts a Bearer token whose hash matches the stored hash', function () use ($token, $token_hash) {
	expect(api_authenticate('Bearer ' . $token, [], $token_hash))->toBeTrue();
});

test('denies by default when no token hash is configured', function () use ($token) {
	expect(api_authenticate('Bearer ' . $token, [], false))->toBeFalse();
	expect(api_authenticate('Bearer ' . $token, [], ''))->toBeFalse();
});

test('rejects a token smuggled through the query string', function () use ($token, $token_hash) {
	// Even with an otherwise-valid header, a query-string token param is refused.
	expect(api_authenticate('Bearer ' . $token, ['token' => $token], $token_hash))->toBeFalse();
	expect(api_authenticate('Bearer ' . $token, ['api_token' => $token], $token_hash))->toBeFalse();
	expect(api_authenticate('Bearer ' . $token, ['access_token' => $token], $token_hash))->toBeFalse();
});

test('rejects a non-Bearer Authorization scheme', function () use ($token, $token_hash) {
	expect(api_authenticate('Basic ' . base64_encode('u:p'), [], $token_hash))->toBeFalse();
	expect(api_authenticate($token, [], $token_hash))->toBeFalse();
});

test('rejects an empty Bearer token', function () use ($token_hash) {
	expect(api_authenticate('Bearer ', [], $token_hash))->toBeFalse();
});

$index = file_get_contents(__DIR__ . '/../../api/public/index.php');

if ($index === false) {
	$index = '';
}

test('the /v1 group has the auth middleware attached', function () use ($index) {
	expect($index)->toContain("\$app->group('/v1'");
	expect($index)->toContain('})->add($apiAuthMiddleware);');
});

test('every data route is registered inside the /v1 group, behind the gate', function () use ($index) {
	// The only route defined outside /v1 is the constant welcome string. Any
	// $app->get()/$app->group() other than the root and the /v1 group would sit
	// outside the checkpoint, so pin the count.
	preg_match_all('/\$app->(get|post|put|delete|patch|group)\(/', $index, $m);
	// Exactly two top-level $app-> route registrations: GET '/' and group '/v1'.
	expect(count($m[0]))->toBe(2);
	expect($index)->toContain("\$app->get('/'");
	expect($index)->toContain("\$app->group('/v1'");
});

test('the middleware returns a 401 problem+json on failure', function () use ($index) {
	$start = strpos($index, '$apiAuthMiddleware = function');
	expect($start)->not->toBeFalse();

	$block = substr($index, $start, 900);
	expect($block)->toContain('read_config_option(\'api_token_hash\')');
	expect($block)->toContain('api_authenticate(');
	expect($block)->toContain('withStatus(401)');
	expect($block)->toContain('application/problem+json');
});
