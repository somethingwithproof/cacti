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
 * Regression tests for the double-decode open-redirect bypass (M-2).
 *
 * sanitize_uri must not call urldecode() internally — callers that need
 * decoding handle it before calling.  validate_redirect_url must reject
 * double-encoded protocol-relative URLs that resolve to //evil.com after
 * the single decode it performs.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

// ------------------------------------------------------------------
// sanitize_uri — no internal urldecode
// ------------------------------------------------------------------

test('sanitize_uri does not decode percent-encoded slashes', function () {
	// %2F%2Fevil.com must stay encoded; re-decoding would yield //evil.com
	$result = sanitize_uri('%2F%2Fevil.com');

	expect($result)->not->toStartWith('//');
	// The encoded slashes themselves must survive (strip_tags / char removal
	// leaves %2F intact since % is not in the drop list)
	expect($result)->toContain('%2F');
});

test('sanitize_uri passes normal relative paths unchanged', function () {
	expect(sanitize_uri('graph_view.php'))->toContain('graph_view.php');
});

test('sanitize_uri strips dangerous characters without decoding', function () {
	// Angle brackets are in the drop list; percent-encoding must not be expanded
	$result = sanitize_uri('page.php?a=%3Cscript%3E');

	expect($result)->not->toContain('<script>');
});

// ------------------------------------------------------------------
// sanitize_uri source-level guard
// ------------------------------------------------------------------

test('sanitize_uri source does not call urldecode', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/functions.php');

	// Extract just the sanitize_uri function body for a targeted check
	preg_match('/function sanitize_uri\b.*?\n\}/s', $src, $matches);

	expect($matches)->not->toBeEmpty();
	expect($matches[0])->not->toContain('urldecode(');
});

// ------------------------------------------------------------------
// validate_redirect_url — double-encode bypass rejection
// ------------------------------------------------------------------

test('validate_redirect_url rejects double-encoded protocol-relative URL', function () {
	// %252F%252Fevil.com  ->  urldecode once  ->  %2F%2Fevil.com
	// sanitize_uri (no re-decode) leaves %2F%2F, but the post-sanitize //
	// check in validate_redirect_url must catch any literal // that survives
	$result = validate_redirect_url('%252F%252Fevil.com');

	expect($result)->toBe('index.php');
	expect($result)->not->toStartWith('//');
});

test('validate_redirect_url rejects single-encoded protocol-relative URL', function () {
	$result = validate_redirect_url('%2F%2Fevil.com');

	expect($result)->toBe('index.php');
});

test('validate_redirect_url rejects bare protocol-relative URL', function () {
	$result = validate_redirect_url('//evil.com');

	expect($result)->toBe('index.php');
});

test('validate_redirect_url accepts normal relative path', function () {
	$result = validate_redirect_url('graph_view.php?action=view');

	expect($result)->toContain('graph_view.php');
	expect($result)->not->toBe('index.php');
});

test('validate_redirect_url returns default for empty input', function () {
	expect(validate_redirect_url(''))->toBe('index.php');
});

test('validate_redirect_url rejects javascript: scheme', function () {
	expect(validate_redirect_url('javascript:alert(1)'))->toBe('index.php');
});

test('validate_redirect_url rejects path traversal', function () {
	expect(validate_redirect_url('../../../etc/passwd'))->toBe('index.php');
});

// ------------------------------------------------------------------
// validate_redirect_url source-level guards (M-4/M-5)
// ------------------------------------------------------------------

test('validate_redirect_url prefers SERVER_NAME over HTTP_HOST', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/html_utility.php');

	preg_match('/function validate_redirect_url\b.*?\n\}/s', $src, $matches);

	expect($matches)->not->toBeEmpty();
	expect($matches[0])->toContain('SERVER_NAME');
	// HTTP_HOST is still the fallback, so it may appear, but SERVER_NAME must come first
	$pos_name = strpos($matches[0], 'SERVER_NAME');
	$pos_host = strpos($matches[0], 'HTTP_HOST');
	expect($pos_name)->toBeLessThan($pos_host);
});

test('validate_redirect_url has post-sanitize // guard', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/html_utility.php');

	preg_match('/function validate_redirect_url\b.*?\n\}/s', $src, $matches);

	expect($matches)->not->toBeEmpty();
	// The defense-in-depth check must appear after the sanitize_uri call
	$pos_sanitize = strpos($matches[0], 'sanitize_uri(');
	$pos_guard    = strpos($matches[0], "strpos(\$safe, '//')");
	expect($pos_guard)->toBeGreaterThan($pos_sanitize);
});
