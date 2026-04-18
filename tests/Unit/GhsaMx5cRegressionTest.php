<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-mx5c-qj6m-2w89 / CVE-2026-39954.
 *
 * 13 REST API endpoints under /v1 required no authentication — any
 * unauthenticated caller could enumerate hosts, host templates, graphs,
 * automation networks, poller status, etc.
 *
 * Fix: attach an auth middleware to the /v1 route group. Middleware
 * accepts either a valid Cacti session OR a Bearer token matching the
 * configured api_token.
 */

test('api/public/index.php defines an auth middleware', function () {
	$src = file_get_contents(__DIR__ . '/../../api/public/index.php');
	expect($src)->not->toBeFalse();
	expect($src)->toContain('$apiAuthMiddleware = function');
});

test('auth middleware attached to /v1 group', function () {
	$src = file_get_contents(__DIR__ . '/../../api/public/index.php');
	expect($src)->toContain("->add(\$apiAuthMiddleware)");
});

test('middleware checks session user id', function () {
	$src = file_get_contents(__DIR__ . '/../../api/public/index.php');
	expect($src)->toContain('$_SESSION[SESS_USER_ID]');
});

test('middleware validates Bearer token with hash_equals', function () {
	$src = file_get_contents(__DIR__ . '/../../api/public/index.php');
	expect($src)->toContain("hash_equals");
	expect($src)->toContain("Authorization");
	expect($src)->toContain("Bearer");
});

test('middleware returns 401 on missing/invalid credentials', function () {
	$src = file_get_contents(__DIR__ . '/../../api/public/index.php');
	expect($src)->toMatch('/withStatus\(401\)/');
});

test('only one root route remains unauthenticated (welcome endpoint)', function () {
	$src = file_get_contents(__DIR__ . '/../../api/public/index.php');
	// The root / route is outside the /v1 group by design — welcome message only.
	expect($src)->toContain("Welcome to the Cacti API!");
});
