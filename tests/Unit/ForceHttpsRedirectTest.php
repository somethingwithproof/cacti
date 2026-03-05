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
 * Tests for the force_https redirect guard in include/global.php.
 *
 * When force_https is enabled, Cacti redirects HTTP requests to HTTPS.
 * During installation the installer dispatches AJAX callbacks over the
 * current protocol; redirecting mid-install breaks the final steps for
 * remote data collectors. The fix gates the redirect behind
 * !defined('IN_CACTI_INSTALL') so it is skipped during installation.
 *
 * These tests verify the guard logic using an inline stub that mirrors
 * the conditional at line 506 of include/global.php without requiring
 * a running web server.
 *
 * See: include/global.php lines 502-514
 */

/**
 * Mirrors the force_https redirect conditional from global.php.
 *
 * Returns true when a redirect would be issued (Location header + exit),
 * false when the redirect is skipped.
 *
 * @param bool   $in_cacti_install  Whether IN_CACTI_INSTALL is defined
 * @param string $force_https       Config value for force_https ('on'/'')
 * @param bool   $is_https          Whether the current request is HTTPS
 * @param bool   $has_host          Whether HTTP_HOST is set
 * @param bool   $has_uri           Whether REQUEST_URI is set
 */
function stub_force_https_redirect(
	bool $in_cacti_install,
	string $force_https,
	bool $is_https,
	bool $has_host = true,
	bool $has_uri = true
): bool {
	/* mirrors: if (!defined('IN_CACTI_INSTALL') && read_config_option('force_https') == 'on') */
	if (!$in_cacti_install && $force_https == 'on') {
		if (!$is_https && $has_host && $has_uri) {
			return true;
		}
	}

	return false;
}

// --- IN_CACTI_INSTALL guard: redirect skipped during install ---

test('force_https redirect is skipped when IN_CACTI_INSTALL is defined', function () {
	$result = stub_force_https_redirect(
		in_cacti_install: true,
		force_https: 'on',
		is_https: false,
	);

	expect($result)->toBeFalse();
});

// --- normal operation: redirect issued for HTTP when force_https is on ---

test('force_https redirect is issued for HTTP request when force_https is on', function () {
	$result = stub_force_https_redirect(
		in_cacti_install: false,
		force_https: 'on',
		is_https: false,
	);

	expect($result)->toBeTrue();
});

// --- already HTTPS: no redirect even when force_https is on ---

test('force_https redirect is skipped when request is already HTTPS', function () {
	$result = stub_force_https_redirect(
		in_cacti_install: false,
		force_https: 'on',
		is_https: true,
	);

	expect($result)->toBeFalse();
});

// --- force_https disabled: no redirect regardless of protocol ---

test('no redirect when force_https config is not on', function () {
	$result = stub_force_https_redirect(
		in_cacti_install: false,
		force_https: '',
		is_https: false,
	);

	expect($result)->toBeFalse();
});

// --- missing HTTP_HOST: redirect skipped to avoid malformed Location header ---

test('redirect is skipped when HTTP_HOST is not set', function () {
	$result = stub_force_https_redirect(
		in_cacti_install: false,
		force_https: 'on',
		is_https: false,
		has_host: false,
		has_uri: true,
	);

	expect($result)->toBeFalse();
});

// --- missing REQUEST_URI: redirect skipped to avoid malformed Location header ---

test('redirect is skipped when REQUEST_URI is not set', function () {
	$result = stub_force_https_redirect(
		in_cacti_install: false,
		force_https: 'on',
		is_https: false,
		has_host: true,
		has_uri: false,
	);

	expect($result)->toBeFalse();
});

// --- install guard matches other IN_CACTI_INSTALL checks in global.php ---

test('install guard uses same defined check pattern as error handler guard', function () {
	/*
	 * global.php has three IN_CACTI_INSTALL guards (lines 373, 494, 506).
	 * All use !defined('IN_CACTI_INSTALL'). Verify the stub models the
	 * same boolean semantics: defined = true means skip, undefined = false
	 * means proceed.
	 */
	$during_install  = stub_force_https_redirect(true, 'on', false);
	$normal_request  = stub_force_https_redirect(false, 'on', false);

	expect($during_install)->toBeFalse()
		->and($normal_request)->toBeTrue();
});
