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

require_once dirname(__DIR__, 2) . '/lib/ip_utils.php';

// --- cacti_ip_version ---

test('cacti_ip_version returns 4 for IPv4 addresses', function () {
	expect(cacti_ip_version('10.1.0.1'))->toBe(4);
	expect(cacti_ip_version('192.168.255.255'))->toBe(4);
	expect(cacti_ip_version('0.0.0.0'))->toBe(4);
	expect(cacti_ip_version('255.255.255.255'))->toBe(4);
});

test('cacti_ip_version returns 6 for IPv6 addresses', function () {
	expect(cacti_ip_version('::1'))->toBe(6);
	expect(cacti_ip_version('2001:db8::1'))->toBe(6);
	expect(cacti_ip_version('fe80::1%eth0'))->toBe(false);
	expect(cacti_ip_version('2001:0db8:85a3:0000:0000:8a2e:0370:7334'))->toBe(6);
});

test('cacti_ip_version returns false for invalid input', function () {
	expect(cacti_ip_version('garbage'))->toBe(false);
	expect(cacti_ip_version(''))->toBe(false);
	expect(cacti_ip_version('999.999.999.999'))->toBe(false);
	expect(cacti_ip_version('10.1.0'))->toBe(false);
});

// --- cacti_ip_to_binary / cacti_binary_to_ip roundtrip ---

test('IPv4 binary roundtrip preserves address', function () {
	$ips = ['10.1.0.1', '192.168.255.255', '0.0.0.0', '255.255.255.255'];

	foreach ($ips as $ip) {
		$binary = cacti_ip_to_binary($ip);
		expect($binary)->not->toBeFalse();
		expect(strlen($binary))->toBe(4);
		expect(cacti_binary_to_ip($binary))->toBe($ip);
	}
});

test('IPv6 binary roundtrip preserves address', function () {
	$binary = cacti_ip_to_binary('2001:db8::1');
	expect($binary)->not->toBeFalse();
	expect(strlen($binary))->toBe(16);
	expect(cacti_binary_to_ip($binary))->toBe('2001:db8::1');
});

test('binary conversion returns false for invalid input', function () {
	expect(cacti_ip_to_binary('garbage'))->toBe(false);
	expect(cacti_binary_to_ip('xxx'))->toBe(false);
	expect(cacti_binary_to_ip(''))->toBe(false);
});

// --- cacti_cidr_to_range ---

test('IPv4 /24 CIDR produces correct range', function () {
	$r = cacti_cidr_to_range('10.1.0.0/24');
	expect($r)->not->toBeFalse();
	expect($r['network'])->toBe('10.1.0.0');
	expect($r['broadcast'])->toBe('10.1.0.255');
	expect($r['prefix'])->toBe(24);
	expect($r['count'])->toBe('254');
	expect($r['start'])->toBe('10.1.0.1');
	expect($r['end'])->toBe('10.1.0.254');
	expect($r['version'])->toBe(4);
});

test('IPv4 /32 CIDR produces single host', function () {
	$r = cacti_cidr_to_range('192.168.1.1/32');
	expect($r['count'])->toBe('1');
	expect($r['start'])->toBe('192.168.1.1');
	expect($r['end'])->toBe('192.168.1.1');
});

test('IPv4 /31 CIDR produces point-to-point pair', function () {
	$r = cacti_cidr_to_range('10.0.0.0/31');
	expect($r['count'])->toBe('2');
	expect($r['start'])->toBe('10.0.0.0');
	expect($r['end'])->toBe('10.0.0.1');
});

test('IPv6 /120 CIDR produces correct range', function () {
	$r = cacti_cidr_to_range('2001:db8::/120');
	expect($r['network'])->toBe('2001:db8::');
	expect($r['broadcast'])->toBe('2001:db8::ff');
	expect($r['prefix'])->toBe(120);
	expect((int) $r['count'])->toBe(254);
	expect($r['start'])->toBe('2001:db8::1');
	expect($r['end'])->toBe('2001:db8::fe');
	expect($r['version'])->toBe(6);
});

test('IPv6 /128 CIDR produces single host', function () {
	$r = cacti_cidr_to_range('2001:db8::1/128');
	expect($r['count'])->toBe('1');
	expect($r['start'])->toBe('2001:db8::1');
	expect($r['end'])->toBe('2001:db8::1');
});

test('CIDR parsing rejects invalid input', function () {
	expect(cacti_cidr_to_range('garbage'))->toBe(false);
	expect(cacti_cidr_to_range('10.1.0.0/33'))->toBe(false);
	expect(cacti_cidr_to_range('2001:db8::/129'))->toBe(false);
	expect(cacti_cidr_to_range('10.1.0.0/-1'))->toBe(false);
});

// --- cacti_ip_increment ---

test('IPv4 increment works within octet', function () {
	expect(cacti_ip_increment('10.1.0.1'))->toBe('10.1.0.2');
	expect(cacti_ip_increment('10.1.0.1', 5))->toBe('10.1.0.6');
});

test('IPv4 increment carries across octets', function () {
	expect(cacti_ip_increment('10.1.0.255'))->toBe('10.1.1.0');
	expect(cacti_ip_increment('10.1.255.255'))->toBe('10.2.0.0');
});

test('IPv4 decrement works', function () {
	expect(cacti_ip_increment('10.1.1.0', -1))->toBe('10.1.0.255');
	expect(cacti_ip_increment('10.1.0.5', -3))->toBe('10.1.0.2');
});

test('IPv6 increment works', function () {
	expect(cacti_ip_increment('::1'))->toBe('::2');
	expect(cacti_ip_increment('::ff'))->toBe('::100');
	expect(cacti_ip_increment('2001:db8::ffff'))->toBe('2001:db8::1:0');
});

test('increment rejects overflow and underflow', function () {
	expect(cacti_ip_increment('255.255.255.255'))->toBe(false);
	expect(cacti_ip_increment('0.0.0.0', -1))->toBe(false);
});

test('increment rejects invalid input', function () {
	expect(cacti_ip_increment('garbage'))->toBe(false);
});

// --- cacti_ip_compare ---

test('IPv4 comparison works', function () {
	expect(cacti_ip_compare('10.1.0.1', '10.1.0.2'))->toBe(-1);
	expect(cacti_ip_compare('10.1.0.2', '10.1.0.1'))->toBe(1);
	expect(cacti_ip_compare('10.1.0.1', '10.1.0.1'))->toBe(0);
});

test('IPv6 comparison works', function () {
	expect(cacti_ip_compare('::1', '::2'))->toBe(-1);
	expect(cacti_ip_compare('2001:db8::2', '2001:db8::1'))->toBe(1);
	expect(cacti_ip_compare('::1', '::1'))->toBe(0);
});

test('comparison returns 0 for invalid or mismatched versions', function () {
	expect(cacti_ip_compare('garbage', '10.1.0.1'))->toBe(0);
	expect(cacti_ip_compare('10.1.0.1', '::1'))->toBe(0);
});

// --- cacti_ip_in_cidr ---

test('IPv4 address in CIDR', function () {
	expect(cacti_ip_in_cidr('10.1.0.5', '10.1.0.0/24'))->toBe(true);
	expect(cacti_ip_in_cidr('10.1.0.255', '10.1.0.0/24'))->toBe(true);
	expect(cacti_ip_in_cidr('10.1.1.0', '10.1.0.0/24'))->toBe(false);
	expect(cacti_ip_in_cidr('10.2.0.5', '10.1.0.0/24'))->toBe(false);
});

test('IPv6 address in CIDR', function () {
	expect(cacti_ip_in_cidr('2001:db8::5', '2001:db8::/120'))->toBe(true);
	expect(cacti_ip_in_cidr('2001:db8::ff', '2001:db8::/120'))->toBe(true);
	expect(cacti_ip_in_cidr('2001:db8::100', '2001:db8::/120'))->toBe(false);
});

test('ip_in_cidr rejects cross-family checks', function () {
	expect(cacti_ip_in_cidr('10.1.0.1', '2001:db8::/32'))->toBe(false);
	expect(cacti_ip_in_cidr('::1', '10.0.0.0/8'))->toBe(false);
});

// --- cacti_ip_range_iterator ---

test('IPv4 range iterator yields correct count', function () {
	$count = 0;
	$first = null;
	$last  = null;

	foreach (cacti_ip_range_iterator('10.1.0.1', '10.1.0.5') as $ip) {
		if ($first === null) $first = $ip;
		$last = $ip;
		$count++;
	}

	expect($count)->toBe(5);
	expect($first)->toBe('10.1.0.1');
	expect($last)->toBe('10.1.0.5');
});

test('IPv6 range iterator yields correct count', function () {
	$count = 0;

	foreach (cacti_ip_range_iterator('2001:db8::1', '2001:db8::a') as $ip) {
		$count++;
	}

	expect($count)->toBe(10);
});

test('range iterator handles single IP', function () {
	$count = 0;

	foreach (cacti_ip_range_iterator('10.1.0.1', '10.1.0.1') as $ip) {
		$count++;
	}

	expect($count)->toBe(1);
});

test('range iterator yields nothing for reversed range', function () {
	$count = 0;

	foreach (cacti_ip_range_iterator('10.1.0.5', '10.1.0.1') as $ip) {
		$count++;
	}

	expect($count)->toBe(0);
});

// --- cacti_mask_to_cidr ---

test('subnet mask to CIDR conversion', function () {
	expect(cacti_mask_to_cidr('255.255.255.0'))->toBe(24);
	expect(cacti_mask_to_cidr('255.255.0.0'))->toBe(16);
	expect(cacti_mask_to_cidr('255.0.0.0'))->toBe(8);
	expect(cacti_mask_to_cidr('255.255.255.255'))->toBe(32);
	expect(cacti_mask_to_cidr('0.0.0.0'))->toBe(0);
	expect(cacti_mask_to_cidr('255.255.255.128'))->toBe(25);
});

test('non-contiguous mask returns false', function () {
	expect(cacti_mask_to_cidr('255.0.255.0'))->toBe(false);
});

test('invalid mask returns false', function () {
	expect(cacti_mask_to_cidr('garbage'))->toBe(false);
	expect(cacti_mask_to_cidr('::1'))->toBe(false);
});

// --- cacti_cidr_to_mask ---

test('CIDR to subnet mask conversion', function () {
	expect(cacti_cidr_to_mask(24))->toBe('255.255.255.0');
	expect(cacti_cidr_to_mask(16))->toBe('255.255.0.0');
	expect(cacti_cidr_to_mask(8))->toBe('255.0.0.0');
	expect(cacti_cidr_to_mask(32))->toBe('255.255.255.255');
	expect(cacti_cidr_to_mask(0))->toBe('0.0.0.0');
	expect(cacti_cidr_to_mask(25))->toBe('255.255.255.128');
});

test('out of range CIDR returns false', function () {
	expect(cacti_cidr_to_mask(-1))->toBe(false);
	expect(cacti_cidr_to_mask(33))->toBe(false);
});

// --- Edge cases ---

test('IPv4 /16 produces correct host count', function () {
	$r = cacti_cidr_to_range('172.16.0.0/16');
	expect((int) $r['count'])->toBe(65534);
	expect($r['start'])->toBe('172.16.0.1');
	expect($r['end'])->toBe('172.16.255.254');
});

test('IPv6 /64 produces large count', function () {
	$r = cacti_cidr_to_range('2001:db8::/64');
	expect($r)->not->toBeFalse();
	expect($r['count'])->not->toBe('0');
	expect(strlen($r['count']))->toBeGreaterThanOrEqual(5);
});

test('cacti_ip_increment handles large step for IPv4', function () {
	expect(cacti_ip_increment('10.0.0.0', 256))->toBe('10.0.1.0');
	expect(cacti_ip_increment('10.0.0.0', 65536))->toBe('10.1.0.0');
});
