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

// =====================================================================
// IPv6 address detection and socket family selection tests
//
// Validates the filter_var + trim pattern used in lib/rrd.php and
// lib/ping.php to detect IPv6 addresses and choose AF_INET6 vs AF_INET.
// =====================================================================

test('filter_var detects bare IPv6 loopback ::1', function () {
	$server = '::1';
	$result = filter_var(trim($server, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

	expect($result)->not->toBeFalse();
});

test('filter_var detects bracketed IPv6 loopback [::1]', function () {
	$server = '[::1]';
	$result = filter_var(trim($server, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

	expect($result)->not->toBeFalse();
});

test('filter_var detects full IPv6 address 2001:db8::1', function () {
	$server = '2001:db8::1';
	$result = filter_var(trim($server, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

	expect($result)->not->toBeFalse();
});

test('filter_var detects bracketed full IPv6 address [2001:db8::1]', function () {
	$server = '[2001:db8::1]';
	$result = filter_var(trim($server, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

	expect($result)->not->toBeFalse();
});

test('filter_var rejects IPv6 link-local with zone ID fe80::1%eth0', function () {
	// filter_var rejects zone IDs (%eth0) — this is expected behavior
	$server = 'fe80::1%eth0';
	$result = filter_var(trim($server, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

	expect($result)->toBeFalse();
});

test('filter_var rejects IPv4 address 127.0.0.1 as IPv6', function () {
	$server = '127.0.0.1';
	$result = filter_var(trim($server, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

	expect($result)->toBeFalse();
});

test('filter_var rejects IPv4 address 192.168.1.1 as IPv6', function () {
	$server = '192.168.1.1';
	$result = filter_var(trim($server, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

	expect($result)->toBeFalse();
});

test('filter_var rejects hostname localhost as IPv6', function () {
	$server = 'localhost';
	$result = filter_var(trim($server, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

	expect($result)->toBeFalse();
});

test('filter_var rejects FQDN rrdproxy.example.com as IPv6', function () {
	$server = 'rrdproxy.example.com';
	$result = filter_var(trim($server, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

	expect($result)->toBeFalse();
});

test('bracket stripping with trim produces bare IPv6 from bracketed form', function () {
	expect(trim('[::1]', '[]'))->toBe('::1')
		->and(trim('[2001:db8::1]', '[]'))->toBe('2001:db8::1');
});

test('bracket stripping leaves IPv4 addresses unchanged', function () {
	expect(trim('192.168.1.1', '[]'))->toBe('192.168.1.1')
		->and(trim('127.0.0.1', '[]'))->toBe('127.0.0.1');
});

test('bracket stripping leaves bare IPv6 addresses unchanged', function () {
	expect(trim('::1', '[]'))->toBe('::1')
		->and(trim('2001:db8::1', '[]'))->toBe('2001:db8::1');
});

test('socket family selection returns AF_INET6 for IPv6 addresses', function () {
	$server = '[::1]';
	$socket_family = filter_var(trim($server, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? AF_INET6 : AF_INET;

	expect($socket_family)->toBe(AF_INET6);
});

test('socket family selection returns AF_INET for IPv4 addresses', function () {
	$server = '192.168.1.1';
	$socket_family = filter_var(trim($server, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? AF_INET6 : AF_INET;

	expect($socket_family)->toBe(AF_INET);
});

test('socket family selection returns AF_INET for hostnames', function () {
	$server = 'rrdproxy.example.com';
	$socket_family = filter_var(trim($server, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? AF_INET6 : AF_INET;

	expect($socket_family)->toBe(AF_INET);
});
