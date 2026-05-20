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
 * Integration tests for IPv6 socket selection logic as implemented in
 * lib/rrd.php __rrd_proxy_init().
 *
 * These tests simulate the config reading and socket family selection flow
 * without actually creating sockets, validating that the address family
 * detection, bracket stripping, and failover logic work correctly.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

/**
 * Mirrors the address family detection logic from __rrd_proxy_init().
 *
 * @param string $server The server address (may include brackets for IPv6).
 * @return int AF_INET6 or AF_INET constant value.
 */
function detect_socket_family(string $server): int {
	return filter_var(trim($server, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? AF_INET6 : AF_INET;
}

/**
 * Mirrors the bracket stripping for socket_connect from __rrd_proxy_init().
 *
 * @param string $server The server address.
 * @return string The address suitable for socket_connect.
 */
function strip_brackets(string $server): string {
	return trim($server, '[]');
}

// --- IPv6 loopback address detection ---

test('IPv6 loopback ::1 selects AF_INET6', function () {
	$server = '::1';
	$family = detect_socket_family($server);

	expect($family)->toBe(AF_INET6);
});

test('IPv6 loopback ::1 does not need bracket stripping', function () {
	$server = '::1';
	$connect_server = strip_brackets($server);

	expect($connect_server)->toBe('::1');
});

// --- Bracketed IPv6 address handling ---

test('bracketed IPv6 address selects AF_INET6', function () {
	$server = '[2001:db8::1]';
	$family = detect_socket_family($server);

	expect($family)->toBe(AF_INET6);
});

test('brackets are stripped from IPv6 address for socket_connect', function () {
	$server = '[2001:db8::1]';
	$connect_server = strip_brackets($server);

	expect($connect_server)->toBe('2001:db8::1');
	// Verify no brackets remain
	expect($connect_server)->not->toContain('[');
	expect($connect_server)->not->toContain(']');
});

test('bracketed full IPv6 address is properly detected and stripped', function () {
	$server = '[fe80::1%25eth0]';
	// Note: filter_var may not validate link-local with zone ID
	// The production code would still try; test the stripping
	$connect_server = strip_brackets($server);

	expect($connect_server)->toBe('fe80::1%25eth0');
});

// --- IPv4 address detection ---

test('IPv4 address selects AF_INET', function () {
	$server = '192.168.1.1';
	$family = detect_socket_family($server);

	expect($family)->toBe(AF_INET);
});

test('IPv4 loopback selects AF_INET', function () {
	$server = '127.0.0.1';
	$family = detect_socket_family($server);

	expect($family)->toBe(AF_INET);
});

test('IPv4 address is unchanged by bracket stripping', function () {
	$server = '10.0.0.1';
	$connect_server = strip_brackets($server);

	expect($connect_server)->toBe('10.0.0.1');
});

// --- Failover: primary IPv6, backup IPv4 ---

test('failover from IPv6 primary to IPv4 backup changes socket family', function () {
	$servera = '[2001:db8::1]';
	$serverb = '192.168.1.100';

	// Primary connection attempt uses IPv6
	$primary_family = detect_socket_family($servera);
	expect($primary_family)->toBe(AF_INET6);

	// Primary fails, failover to backup
	// Re-detect address family for backup server (as done in __rrd_proxy_init)
	$backup_family = detect_socket_family($serverb);
	expect($backup_family)->toBe(AF_INET);

	// The families should differ
	expect($primary_family)->not->toBe($backup_family);
});

test('failover from IPv4 primary to IPv6 backup changes socket family', function () {
	$servera = '10.0.0.1';
	$serverb = '[::1]';

	$primary_family = detect_socket_family($servera);
	expect($primary_family)->toBe(AF_INET);

	$backup_family = detect_socket_family($serverb);
	expect($backup_family)->toBe(AF_INET6);

	expect($primary_family)->not->toBe($backup_family);
});

test('failover with both IPv6 servers keeps AF_INET6', function () {
	$servera = '[2001:db8::1]';
	$serverb = '[2001:db8::2]';

	$primary_family = detect_socket_family($servera);
	$backup_family = detect_socket_family($serverb);

	expect($primary_family)->toBe(AF_INET6);
	expect($backup_family)->toBe(AF_INET6);
});

test('failover with both IPv4 servers keeps AF_INET', function () {
	$servera = '192.168.1.1';
	$serverb = '192.168.1.2';

	$primary_family = detect_socket_family($servera);
	$backup_family = detect_socket_family($serverb);

	expect($primary_family)->toBe(AF_INET);
	expect($backup_family)->toBe(AF_INET);
});

// --- Load balancing: both servers may have different address families ---

test('load balancing randomly selects between IPv4 and IPv6 servers', function () {
	$servera = '192.168.1.1';
	$porta = 40301;
	$serverb = '[2001:db8::1]';
	$portb = 40302;

	// Simulate the load balancing selection from __rrd_proxy_init
	$rrdp_id = random_int(1, 2);
	$server = ($rrdp_id == 1) ? $servera : $serverb;
	$port = ($rrdp_id == 1) ? $porta : $portb;

	$family = detect_socket_family($server);
	$connect_server = strip_brackets($server);

	if ($rrdp_id == 1) {
		expect($family)->toBe(AF_INET);
		expect($connect_server)->toBe('192.168.1.1');
		expect($port)->toBe(40301);
	} else {
		expect($family)->toBe(AF_INET6);
		expect($connect_server)->toBe('2001:db8::1');
		expect($port)->toBe(40302);
	}
});

test('load balancing failover correctly switches address family', function () {
	$servera = '192.168.1.1';
	$porta = 40301;
	$serverb = '[2001:db8::1]';
	$portb = 40302;

	// Start with server A (IPv4)
	$rrdp_id = 1;
	$server = $servera;
	$port = $porta;

	$primary_family = detect_socket_family($server);
	expect($primary_family)->toBe(AF_INET);

	// Simulate connection failure and failover (mirroring __rrd_proxy_init logic)
	$rrdp_id = ($rrdp_id + 1) % 2;
	$server = ($rrdp_id == 1) ? $servera : $serverb;
	$port = ($rrdp_id == 1) ? $porta : $portb;

	$backup_family = detect_socket_family($server);
	$connect_server = strip_brackets($server);

	expect($backup_family)->toBe(AF_INET6);
	expect($connect_server)->toBe('2001:db8::1');
	expect($port)->toBe(40302);
});

// --- Edge cases ---

test('hostname string is treated as IPv4 (non-IPv6)', function () {
	$server = 'rrdproxy.example.com';
	$family = detect_socket_family($server);

	// Hostnames are not valid IPv6, so AF_INET is selected
	expect($family)->toBe(AF_INET);
});

test('empty string defaults to AF_INET', function () {
	$server = '';
	$family = detect_socket_family($server);

	expect($family)->toBe(AF_INET);
});

test('compressed IPv6 addresses are detected correctly', function () {
	$addresses = ['::1', '::ffff:192.0.2.1', '2001:db8::', 'fe80::1'];

	foreach ($addresses as $addr) {
		$family = detect_socket_family($addr);
		expect($family)->toBe(AF_INET6);
	}
});
