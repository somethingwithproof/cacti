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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

// GHSA-6gr7-53g8-vchq: open redirect through HTTP_REFERER.
// auth_login_redirect() used str_contains($referer, CACTI_PATH_URL) to decide
// whether the Referer header pointed at the local instance. Because
// CACTI_PATH_URL is typically a short path like "/cacti/" or "/", the
// substring test passed for attacker-hosted URLs that happened to contain the
// same fragment, letting an attacker drive a post-login redirect off site.
//
// The fix feeds $_SERVER['HTTP_REFERER'] through validate_redirect_url, which
// already enforces same-host and protocol checks.

test('validate_redirect_url rejects off-host urls that trick substring checks', function () {
	$_SERVER['HTTP_HOST'] = 'cacti.example.org';
	$default              = '/cacti/index.php';

	$malicious = 'https://attacker.example.com/cacti/landing';

	expect(validate_redirect_url($malicious, $default))->toBe($default);
});

test('validate_redirect_url rejects protocol-relative urls', function () {
	$_SERVER['HTTP_HOST'] = 'cacti.example.org';
	$default              = '/cacti/index.php';

	expect(validate_redirect_url('//attacker.example.com/path', $default))->toBe($default);
});

test('validate_redirect_url rejects javascript scheme', function () {
	$default = '/cacti/index.php';

	expect(validate_redirect_url('javascript:alert(1)', $default))->toBe($default);
});

test('validate_redirect_url keeps same-host referer', function () {
	$_SERVER['HTTP_HOST'] = 'cacti.example.org';
	$default              = '/cacti/index.php';

	$good = 'https://cacti.example.org/cacti/graph_view.php?page=1';

	expect(validate_redirect_url($good, $default))->toBe($good);
});

test('auth_login_redirect routes HTTP_REFERER through validate_redirect_url', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/auth.php');

	$openPos = strpos($source, 'function auth_login_redirect');
	expect($openPos)->not->toBeFalse();

	$slice = substr($source, $openPos, 5000);

	expect($slice)->toContain("validate_redirect_url(\$_SERVER['HTTP_REFERER']");
	expect($slice)->not->toContain("!str_contains(\$referer, CACTI_PATH_URL)");
});
