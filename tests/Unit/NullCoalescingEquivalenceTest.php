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
// Null coalescing (??) equivalence tests
//
// Validates that the ?? operator behaves identically to the old
// isset() ternary pattern used throughout the Cacti codebase.
// Ensures the modernization from `isset($x) ? $x : $default` to
// `$x ?? $default` is a safe, behavior-preserving transformation.
// =====================================================================

test('null coalescing returns value when key exists and is set', function () {
	$arr = ['key' => 'value'];

	$old = isset($arr['key']) ? $arr['key'] : 'default';
	$new = $arr['key'] ?? 'default';

	expect($new)->toBe($old)
		->and($new)->toBe('value');
});

test('null coalescing returns default when key is missing', function () {
	$arr = ['other' => 'value'];

	$old = isset($arr['key']) ? $arr['key'] : 'default';
	$new = $arr['key'] ?? 'default';

	expect($new)->toBe($old)
		->and($new)->toBe('default');
});

test('null coalescing returns default when value is null', function () {
	$arr = ['key' => null];

	$old = isset($arr['key']) ? $arr['key'] : 'default';
	$new = $arr['key'] ?? 'default';

	expect($new)->toBe($old)
		->and($new)->toBe('default');
});

test('null coalescing preserves zero (unlike empty check)', function () {
	$arr = ['key' => 0];

	$coalescing = $arr['key'] ?? 'default';
	$empty_check = !empty($arr['key']) ? $arr['key'] : 'default';

	// ?? correctly preserves 0; !empty() would return 'default'
	expect($coalescing)->toBe(0)
		->and($empty_check)->toBe('default')
		->and($coalescing)->not->toBe($empty_check);
});

test('null coalescing preserves empty string (unlike empty check)', function () {
	$arr = ['key' => ''];

	$coalescing = $arr['key'] ?? 'default';
	$empty_check = !empty($arr['key']) ? $arr['key'] : 'default';

	// ?? correctly preserves ''; !empty() would return 'default'
	expect($coalescing)->toBe('')
		->and($empty_check)->toBe('default');
});

test('null coalescing preserves false (unlike empty check)', function () {
	$arr = ['key' => false];

	$coalescing = $arr['key'] ?? 'default';
	$empty_check = !empty($arr['key']) ? $arr['key'] : 'default';

	expect($coalescing)->toBeFalse()
		->and($empty_check)->toBe('default');
});

test('null coalescing works for nested array access', function () {
	$arr = [['name' => 'eth0'], ['name' => 'eth1']];

	$old = isset($arr[0]['name']) ? $arr[0]['name'] : '';
	$new = $arr[0]['name'] ?? '';

	expect($new)->toBe($old)
		->and($new)->toBe('eth0');
});

test('null coalescing returns default for missing nested key', function () {
	$arr = [['name' => 'eth0']];

	$old = isset($arr[1]['name']) ? $arr[1]['name'] : '';
	$new = $arr[1]['name'] ?? '';

	expect($new)->toBe($old)
		->and($new)->toBe('');
});

test('null coalescing returns default for completely empty array', function () {
	$arr = [];

	$old = isset($arr['key']) ? $arr['key'] : 'N/A';
	$new = $arr['key'] ?? 'N/A';

	expect($new)->toBe($old)
		->and($new)->toBe('N/A');
});

test('null coalescing chains work left to right', function () {
	$primary   = ['setting' => null];
	$secondary = ['setting' => 'fallback'];

	$result = $primary['setting'] ?? $secondary['setting'] ?? 'ultimate_default';

	expect($result)->toBe('fallback');
});

test('null coalescing chain falls through to final default', function () {
	$primary   = ['setting' => null];
	$secondary = [];

	$result = $primary['setting'] ?? $secondary['setting'] ?? 'ultimate_default';

	expect($result)->toBe('ultimate_default');
});

test('null coalescing matches isset ternary for numeric string value', function () {
	$arr = ['poller_interval' => '300'];

	$old = isset($arr['poller_interval']) ? $arr['poller_interval'] : '60';
	$new = $arr['poller_interval'] ?? '60';

	expect($new)->toBe($old)
		->and($new)->toBe('300');
});

test('null coalescing with array_key_exists equivalence for missing key', function () {
	$arr = ['a' => 1, 'b' => 2];
	$key = 'c';

	$result = $arr[$key] ?? 'N/A';

	expect(array_key_exists($key, $arr))->toBeFalse()
		->and($result)->toBe('N/A');
});

test('null coalescing differs from array_key_exists when value is null', function () {
	$arr = ['key' => null];

	// array_key_exists returns true for null values; ?? returns default
	$exists    = array_key_exists('key', $arr);
	$coalesced = $arr['key'] ?? 'default';

	expect($exists)->toBeTrue()
		->and($coalesced)->toBe('default');
});
