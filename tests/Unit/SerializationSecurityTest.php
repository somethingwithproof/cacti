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

require_once __DIR__ . '/../../lib/functions.php';

// --- sanitize_unserialize_selected_items ---

test('sanitize_unserialize_selected_items returns array for valid numeric serialized data', function () {
	expect(sanitize_unserialize_selected_items(serialize([1, 2, 3])))->toBe([1, 2, 3]);
});

test('sanitize_unserialize_selected_items returns false for empty string', function () {
	expect(sanitize_unserialize_selected_items(''))->toBeFalse();
});

test('sanitize_unserialize_selected_items returns false for non-string input', function () {
	expect(sanitize_unserialize_selected_items(42))->toBeFalse();
});

test('sanitize_unserialize_selected_items rejects bare object injection payload', function () {
	// Does not start with a: so the regex guard fires before unserialize
	expect(sanitize_unserialize_selected_items('O:4:"Evil":0:{}'))->toBeFalse();
});

test('sanitize_unserialize_selected_items rejects object injection embedded in array', function () {
	// Manually constructed payload: array containing a serialized object reference
	$payload = 'a:1:{i:0;O:4:"Evil":0:{}}';
	expect(sanitize_unserialize_selected_items($payload))->toBeFalse();
});

test('sanitize_unserialize_selected_items rejects array with non-numeric string values', function () {
	expect(sanitize_unserialize_selected_items(serialize(['drop table users', '1'])))->toBeFalse();
});

test('sanitize_unserialize_selected_items rejects nested arrays', function () {
	expect(sanitize_unserialize_selected_items(serialize([[1, 2], 3])))->toBeFalse();
});

test('sanitize_unserialize_selected_items handles addslashes-escaped serialized input', function () {
	// stripslashes is applied before validation; slashed form must round-trip correctly
	$slashed = addslashes(serialize([10, 20]));
	expect(sanitize_unserialize_selected_items($slashed))->toBe([10, 20]);
});

// --- sanitize_cdef ---

test('sanitize_cdef leaves clean CDEF expression unchanged', function () {
	expect(sanitize_cdef('a,b,+,100,*'))->toBe('a,b,+,100,*');
});

test('sanitize_cdef strips backtick injection', function () {
	expect(sanitize_cdef('a,`command`,+'))->toBe('a,command,+');
});

test('sanitize_cdef strips shell semicolon metachar', function () {
	expect(sanitize_cdef('a;rm -rf;b'))->toBe('arm -rfb');
});

test('sanitize_cdef strips pipe character', function () {
	expect(sanitize_cdef('a|b'))->toBe('ab');
});

test('sanitize_cdef strips angle brackets', function () {
	expect(sanitize_cdef('<script>'))->toBe('script');
});

test('sanitize_cdef strips all dangerous chars in one pass', function () {
	expect(sanitize_cdef('^$<>`\'"|[]{}; !'))->toBe(' ');
});

// --- is_ipaddress ---

test('is_ipaddress accepts valid IPv4', function () {
	expect(is_ipaddress('192.168.1.1'))->toBeTrue();
});

test('is_ipaddress accepts loopback IPv6', function () {
	expect(is_ipaddress('::1'))->toBeTrue();
});

test('is_ipaddress accepts full-form IPv6', function () {
	expect(is_ipaddress('2001:0db8:85a3:0000:0000:8a2e:0370:7334'))->toBeTrue();
});

test('is_ipaddress rejects non-IP string', function () {
	expect(is_ipaddress('not.an.ip'))->toBeFalse();
});

test('is_ipaddress rejects empty string', function () {
	expect(is_ipaddress(''))->toBeFalse();
});

test('is_ipaddress rejects partial IPv4', function () {
	expect(is_ipaddress('192.168.1'))->toBeFalse();
});

// --- is_mac_address ---

test('is_mac_address accepts colon-separated MAC', function () {
	expect(is_mac_address('00:1A:2B:3C:4D:5E'))->toBeTruthy();
});

test('is_mac_address accepts dash-separated MAC', function () {
	expect(is_mac_address('00-1A-2B-3C-4D-5E'))->toBeTruthy();
});

test('is_mac_address rejects truncated MAC', function () {
	expect(is_mac_address('00:1A:2B'))->toBeFalsy();
});

test('is_mac_address rejects non-hex characters', function () {
	expect(is_mac_address('GG:HH:II:JJ:KK:LL'))->toBeFalsy();
});

test('is_mac_address rejects empty string', function () {
	expect(is_mac_address(''))->toBeFalsy();
});

// --- is_hex_string ---

test('is_hex_string accepts hex- prefix and mutates result', function () {
	$input = 'Hex-AA BB CC';
	$result = is_hex_string($input);
	expect($result)->toBeTrue();
	expect($input)->toBe('AA BB CC');
});

test('is_hex_string with hex-string: prefix hits hex- branch first', function () {
	// 'hex-string:' starts with 'hex-', so the first branch strips 'hex-'
	// leaving 'string:AA BB' which fails the 2-char octet check
	$input = 'hex-string:AA BB';
	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects empty string', function () {
	$input = '';
	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects string without hex prefix', function () {
	$input = 'AA BB CC';
	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects single-octet value (length must be >1 parts)', function () {
	$input = 'Hex-AA';
	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects non-hex characters after prefix', function () {
	$input = 'Hex-GG HH';
	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects odd-length octet after prefix', function () {
	$input = 'Hex-A BB';
	expect(is_hex_string($input))->toBeFalse();
});
