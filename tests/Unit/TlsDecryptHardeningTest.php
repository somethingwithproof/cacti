<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Source-scan tests for TLS/decrypt hardening.
 *
 * These tests verify static properties of the patched source files without
 * requiring a database or phpseclib — they read file contents directly.
 */

$functionsPath    = __DIR__ . '/../../lib/functions.php';
$packageReposPath = __DIR__ . '/../../package_repos.php';
$databasePath     = __DIR__ . '/../../lib/database.php';
$rrdPath          = __DIR__ . '/../../lib/rrd.php';

// H-2: get_default_contextoption must no longer hardcode verify_peer => false

test('get_default_contextoption does not hardcode verify_peer => false', function () use ($functionsPath) {
	$contents = file_get_contents($functionsPath);

	// Extract only the function body to avoid false positives from comments
	preg_match('/function get_default_contextoption.*?(?=\nfunction )/s', $contents, $m);
	$body = $m[0] ?? $contents;

	expect($body)->not->toContain("'verify_peer'       => false");
});

test('get_default_contextoption does not hardcode verify_peer_name => false', function () use ($functionsPath) {
	$contents = file_get_contents($functionsPath);

	preg_match('/function get_default_contextoption.*?(?=\nfunction )/s', $contents, $m);
	$body = $m[0] ?? $contents;

	expect($body)->not->toContain("'verify_peer_name'  => false");
});

test('get_default_contextoption does not hardcode allow_self_signed => true', function () use ($functionsPath) {
	$contents = file_get_contents($functionsPath);

	preg_match('/function get_default_contextoption.*?(?=\nfunction )/s', $contents, $m);
	$body = $m[0] ?? $contents;

	expect($body)->not->toContain("'allow_self_signed' => true");
});

test('get_default_contextoption checks allow_unsafe_https config option', function () use ($functionsPath) {
	$contents = file_get_contents($functionsPath);

	preg_match('/function get_default_contextoption.*?(?=\nfunction )/s', $contents, $m);
	$body = $m[0] ?? $contents;

	expect($body)->toContain("read_config_option('allow_unsafe_https')");
});

// H-3: package_repos.php must not skip TLS verification

test('package_repos.php does not disable verify_peer', function () use ($packageReposPath) {
	$contents = file_get_contents($packageReposPath);

	expect($contents)->not->toContain("'verify_peer'      => false");
});

test('package_repos.php does not disable verify_peer_name', function () use ($packageReposPath) {
	$contents = file_get_contents($packageReposPath);

	expect($contents)->not->toContain("'verify_peer_name' => false");
});

// H-4: db_dump_data must wrap credential values with cacti_escapeshellarg

test('db_dump_data wraps long-form credential values with cacti_escapeshellarg', function () use ($databasePath) {
	$contents = file_get_contents($databasePath);

	preg_match('/function db_dump_data.*?(?=\nfunction )/s', $contents, $m);
	$body = $m[0] ?? $contents;

	// The three credential_string concatenation branches must all use cacti_escapeshellarg
	expect($body)->toContain('cacti_escapeshellarg((string) $value)');
});

test('db_dump_data options parameter is documented as hardcoded-only', function () use ($databasePath) {
	$contents = file_get_contents($databasePath);

	preg_match('/function db_dump_data.*?(?=\nfunction )/s', $contents, $m);
	$body = $m[0] ?? $contents;

	expect($body)->toContain('hardcoded');
});

// H-5: decrypt() return type must be string|false

test('decrypt() return type is string|false', function () use ($rrdPath) {
	$contents = file_get_contents($rrdPath);

	expect($contents)->toMatch('/function decrypt\s*\(\s*string\s+\$input\s*\)\s*:\s*string\|false/');
});

test('decrypt() returns false on base64 decode failure, not the raw input', function () use ($rrdPath) {
	$contents = file_get_contents($rrdPath);

	preg_match('/function decrypt.*?(?=\nfunction )/s', $contents, $m);
	$body = $m[0] ?? $contents;

	// Must not fall back to returning $input when decoding fails
	expect($body)->not->toMatch('/\$aes_key === false.*return\s+\$input/s');

	// Must return false on the failure path
	expect($body)->toContain('return false;');
});
