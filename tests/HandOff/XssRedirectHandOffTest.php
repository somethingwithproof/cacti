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
 * Hand-off tests: full redirect-validation chain (M-1, M-2, M-4, M-5).
 *
 * These tests exercise validate_redirect_url end-to-end, simulating the
 * decode + sanitize + guard path that a browser request would traverse.
 * Run against a bootstrapped Cacti environment.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

// ------------------------------------------------------------------
// Double-decode bypass chain
// ------------------------------------------------------------------

test('handoff: double-encoded // survives single decode and is then rejected', function () {
	// Attacker sends %252F%252Fevil.com in redirect param.
	// validate_redirect_url decodes once -> %2F%2Fevil.com (no literal //).
	// sanitize_uri does NOT decode again -> leaves %2F%2F intact.
	// Post-sanitize guard rejects any result starting with //.
	// Because %2F%2F does not start with //, the path check fires on
	// parse_url, which returns no host -> passes host check -> sanitize_uri
	// strips nothing -> safe = '%2F%2Fevil.com'.
	// The post-sanitize // guard catches strpos($safe, '//') only for
	// literal //; %2F%2F is not //. The actual protection here is that
	// parse_url on a %2F-prefixed string returns no host, so the host
	// check allows it through, but the result is a relative path string,
	// not a redirect to evil.com.
	// This test validates the entire chain produces a safe value (not a
	// protocol-relative redirect to an external host).
	$result = validate_redirect_url('%252F%252Fevil.com');

	// Must not be an external redirect target
	expect($result)->not->toStartWith('//');
	expect($result)->not->toContain('evil.com');
});

test('handoff: triple-encoded // is rejected', function () {
	$result = validate_redirect_url('%25252F%25252Fevil.com');

	expect($result)->not->toStartWith('//');
	expect($result)->not->toContain('evil.com');
});

// ------------------------------------------------------------------
// Safe relative URLs pass through
// ------------------------------------------------------------------

test('handoff: graph_view.php passes through unchanged', function () {
	$result = validate_redirect_url('graph_view.php?action=view');

	expect($result)->toContain('graph_view.php');
});

test('handoff: index.php passes through', function () {
	$result = validate_redirect_url('index.php');

	expect($result)->toBe('index.php');
});

test('handoff: path with query string passes through', function () {
	$result = validate_redirect_url('reports.php?action=list&tab=summary');

	expect($result)->toContain('reports.php');
	expect($result)->toContain('action=list');
});

// ------------------------------------------------------------------
// All bad-scheme inputs rejected
// ------------------------------------------------------------------

test('handoff: javascript: scheme is rejected', function () {
	expect(validate_redirect_url('javascript:alert(document.cookie)'))->toBe('index.php');
});

test('handoff: data: URI is rejected', function () {
	expect(validate_redirect_url('data:text/html,<script>alert(1)</script>'))->toBe('index.php');
});

test('handoff: vbscript: scheme is rejected', function () {
	expect(validate_redirect_url('vbscript:msgbox(1)'))->toBe('index.php');
});

// ------------------------------------------------------------------
// Host-comparison uses SERVER_NAME when available
// ------------------------------------------------------------------

test('handoff: absolute URL matching SERVER_NAME is accepted', function () {
	$_SERVER['SERVER_NAME'] = 'cacti.example.com';

	// parse_url returns host = cacti.example.com -> matches -> accepted
	$result = validate_redirect_url('http://cacti.example.com/index.php');

	expect($result)->toContain('index.php');

	unset($_SERVER['SERVER_NAME']);
});

test('handoff: absolute URL with different host is rejected even if HTTP_HOST matches', function () {
	$_SERVER['SERVER_NAME'] = 'cacti.example.com';
	$_SERVER['HTTP_HOST']   = 'evil.com';   // attacker-controlled header

	$result = validate_redirect_url('http://evil.com/steal');

	expect($result)->toBe('index.php');

	unset($_SERVER['SERVER_NAME'], $_SERVER['HTTP_HOST']);
});

// ------------------------------------------------------------------
// Header-injection guard
// ------------------------------------------------------------------

test('handoff: URL with embedded newline is rejected', function () {
	expect(validate_redirect_url("index.php\r\nX-Injected: header"))->toBe('index.php');
});
