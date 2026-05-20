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
// str_contains / str_starts_with / str_ends_with modernization tests
//
// Validates that the PHP 8 string functions produce identical results
// to the legacy strpos/substr patterns they replaced throughout the
// codebase (lib/rrd.php, lib/functions.php, etc.).
// =====================================================================

// --- str_contains equivalence ---

test('str_contains matches strpos !== false for present substring', function () {
	$haystack = 'hello world';
	$needle   = 'world';

	$old = strpos($haystack, $needle) !== false;
	$new = str_contains($haystack, $needle);

	expect($new)->toBe($old)
		->and($new)->toBeTrue();
});

test('str_contains matches strpos === false for absent substring', function () {
	$haystack = 'hello';
	$needle   = 'xyz';

	$old = strpos($haystack, $needle) !== false;
	$new = str_contains($haystack, $needle);

	expect($new)->toBe($old)
		->and($new)->toBeFalse();
});

test('str_contains finds pipe-delimited query variables', function () {
	// Pattern from lib/rrd.php line ~3104: str_contains($txt_graph_item, '|query_')
	$cdef_string = 'CDEF:val=|query_ifHighSpeed|,8,*';

	expect(str_contains($cdef_string, '|query_ifHighSpeed|'))->toBeTrue()
		->and(str_contains($cdef_string, '|query_ifSpeed|'))->toBeFalse();
});

test('str_contains detects XML header', function () {
	// Pattern from lib/rrd.php: str_starts_with($output, '<?xml')
	$output = '<?xml version="1.0"?><rrd>data</rrd>';

	expect(str_contains($output, '<?xml'))->toBeTrue();
});

test('str_contains with empty needle always returns true', function () {
	// PHP 8 behavior: str_contains always returns true for empty needle
	expect(str_contains('hello', ''))->toBeTrue()
		->and(str_contains('', ''))->toBeTrue();
});

test('str_contains is case-sensitive', function () {
	expect(str_contains('Hello World', 'hello'))->toBeFalse()
		->and(str_contains('Hello World', 'Hello'))->toBeTrue();
});

// --- str_starts_with equivalence ---

test('str_starts_with matches substr comparison for matching prefix', function () {
	$os = strtoupper('WINDOWS');

	$old = strtoupper(substr($os, 0, 3)) === 'WIN';
	$new = str_starts_with($os, 'WIN');

	expect($new)->toBe($old)
		->and($new)->toBeTrue();
});

test('str_starts_with matches substr comparison for non-matching prefix', function () {
	$os = strtoupper('Linux');

	$old = strtoupper(substr($os, 0, 3)) === 'WIN';
	$new = str_starts_with(strtoupper($os), 'WIN');

	expect($new)->toBe($old)
		->and($new)->toBeFalse();
});

test('str_starts_with detects RRD command prefixes', function () {
	// Pattern from lib/rrd.php line ~360: str_starts_with($command_line, 'fetch')
	expect(str_starts_with('fetch /var/lib/cacti/rra/file.rrd', 'fetch'))->toBeTrue()
		->and(str_starts_with('info /var/lib/cacti/rra/file.rrd', 'info'))->toBeTrue()
		->and(str_starts_with('graph /var/lib/cacti/rra/file.rrd', 'fetch'))->toBeFalse();
});

test('str_starts_with with empty prefix returns true', function () {
	expect(str_starts_with('hello', ''))->toBeTrue();
});

test('str_starts_with returns false when haystack is shorter than needle', function () {
	expect(str_starts_with('hi', 'hello'))->toBeFalse();
});

// --- str_ends_with equivalence ---

test('str_ends_with matches substr comparison for matching suffix', function () {
	$filename = 'file.rrd';

	$old = substr($filename, -3) == 'rrd';
	$new = str_ends_with($filename, 'rrd');

	expect($new)->toBe($old)
		->and($new)->toBeTrue();
});

test('str_ends_with returns false for non-matching suffix', function () {
	$filename = 'file.xml';

	$old = substr($filename, -3) == 'rrd';
	$new = str_ends_with($filename, 'rrd');

	expect($new)->toBe($old)
		->and($new)->toBeFalse();
});

test('str_ends_with with empty suffix returns true', function () {
	expect(str_ends_with('hello', ''))->toBeTrue();
});

test('str_ends_with with single character', function () {
	expect(str_ends_with('path/', '/'))->toBeTrue()
		->and(str_ends_with('path', '/'))->toBeFalse();
});

test('str_ends_with detects file extensions correctly', function () {
	expect(str_ends_with('cacti_graph_123.png', '.png'))->toBeTrue()
		->and(str_ends_with('cacti_graph_123.png', '.jpg'))->toBeFalse()
		->and(str_ends_with('data.rrd', '.rrd'))->toBeTrue();
});
