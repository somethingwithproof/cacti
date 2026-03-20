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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Tests for cacti_is_https() in lib/functions.php.
 *
 * cacti_is_https() centralises the $_SERVER['HTTPS'] / HTTP_X_FORWARDED_PROTO
 * check that was previously duplicated across global.php, auth_login.php, and
 * graph_xport.php.  All callers now use this single helper.
 */

require_once __DIR__ . '/../../lib/functions.php';

beforeEach(function () {
	global $config;
	unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
	$config['proxy_headers'] = false;
});

// --- Direct HTTPS header ---

test('returns true when HTTPS is "on"', function () {
	$_SERVER['HTTPS'] = 'on';
	expect(cacti_is_https())->toBeTrue();
});

test('returns true when HTTPS is "ON" (case-insensitive)', function () {
	$_SERVER['HTTPS'] = 'ON';
	expect(cacti_is_https())->toBeTrue();
});

test('returns true when HTTPS is "1"', function () {
	$_SERVER['HTTPS'] = '1';
	expect(cacti_is_https())->toBeTrue();
});

test('returns true when HTTPS is any non-off truthy string', function () {
	$_SERVER['HTTPS'] = 'anything';
	expect(cacti_is_https())->toBeTrue();
});

test('returns false when HTTPS is "off"', function () {
	$_SERVER['HTTPS'] = 'off';
	expect(cacti_is_https())->toBeFalse();
});

test('returns false when HTTPS is "OFF" (case-insensitive)', function () {
	$_SERVER['HTTPS'] = 'OFF';
	expect(cacti_is_https())->toBeFalse();
});

test('returns false when HTTPS is empty string', function () {
	$_SERVER['HTTPS'] = '';
	expect(cacti_is_https())->toBeFalse();
});

test('returns false when HTTPS is "0"', function () {
	$_SERVER['HTTPS'] = '0';
	expect(cacti_is_https())->toBeFalse();
});

test('returns false when HTTPS is not set', function () {
	expect(cacti_is_https())->toBeFalse();
});

// --- Reverse proxy: X-Forwarded-Proto ---

test('returns true when X-Forwarded-Proto is "https" and proxy_headers enabled', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
	expect(cacti_is_https())->toBeTrue();
});

test('returns true when X-Forwarded-Proto is "HTTPS" (case-insensitive)', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'HTTPS';
	expect(cacti_is_https())->toBeTrue();
});

test('returns false when X-Forwarded-Proto is "http" and proxy_headers enabled', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
	expect(cacti_is_https())->toBeFalse();
});

test('HTTPS header takes precedence over X-Forwarded-Proto', function () {
	$_SERVER['HTTPS']                  = 'on';
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
	expect(cacti_is_https())->toBeTrue();
});

test('returns false when both HTTPS off and X-Forwarded-Proto is http', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTPS']                  = 'off';
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
	expect(cacti_is_https())->toBeFalse();
});

test('X-Forwarded-Proto "https" overrides HTTPS "off" when proxy_headers enabled', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTPS']                  = 'off';
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
	expect(cacti_is_https())->toBeTrue();
});

// --- Untrusted proxy (proxy_headers disabled) ---

test('ignores X-Forwarded-Proto when proxy_headers is disabled', function () {
	global $config;
	$config['proxy_headers'] = false;
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
	expect(cacti_is_https())->toBeFalse();
});

test('ignores X-Forwarded-Proto when proxy_headers is not set', function () {
	global $config;
	unset($config['proxy_headers']);
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
	expect(cacti_is_https())->toBeFalse();
});

// --- Edge cases: non-standard HTTPS values ---

test('returns true when HTTPS is "false" (non-standard but not "off")', function () {
	$_SERVER['HTTPS'] = 'false';
	expect(cacti_is_https())->toBeTrue();
});

test('returns true when HTTPS is "no" (non-standard but not "off")', function () {
	$_SERVER['HTTPS'] = 'no';
	expect(cacti_is_https())->toBeTrue();
});

test('returns true when HTTPS is integer 1', function () {
	$_SERVER['HTTPS'] = 1;
	expect(cacti_is_https())->toBeTrue();
});

// --- Edge cases: non-standard X-Forwarded-Proto values ---

test('returns false when X-Forwarded-Proto is "ftp" and proxy_headers enabled', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'ftp';
	expect(cacti_is_https())->toBeFalse();
});

test('returns false when X-Forwarded-Proto is "true" and proxy_headers enabled', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'true';
	expect(cacti_is_https())->toBeFalse();
});

test('returns false when X-Forwarded-Proto is "1" and proxy_headers enabled', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = '1';
	expect(cacti_is_https())->toBeFalse();
});

// --- Edge cases: conflicting headers ---

test('HTTPS "on" wins over X-Forwarded-Proto "http" even with proxy_headers', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTPS']                  = 'on';
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
	expect(cacti_is_https())->toBeTrue();
});

test('HTTPS "off" falls through to X-Forwarded-Proto "https" when proxy_headers enabled', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTPS']                  = 'off';
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
	expect(cacti_is_https())->toBeTrue();
});

// --- Missing coverage: HTTPS falsy values fall through to X-Forwarded-Proto ---

test('HTTPS "0" falls through to X-Forwarded-Proto "https" when proxy_headers enabled', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTPS']                  = '0';
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
	expect(cacti_is_https())->toBeTrue();
});

test('HTTPS empty string falls through to X-Forwarded-Proto "https" when proxy_headers enabled', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTPS']                  = '';
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
	expect(cacti_is_https())->toBeTrue();
});

// --- Edge cases: empty and multi-value X-Forwarded-Proto ---

test('returns false when X-Forwarded-Proto is empty string and proxy_headers enabled', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = '';
	expect(cacti_is_https())->toBeFalse();
});

test('returns true when X-Forwarded-Proto is "https, http" (multi-value proxy chain)', function () {
	global $config;
	$config['proxy_headers'] = true;
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https, http';
	expect(cacti_is_https())->toBeTrue();
});
